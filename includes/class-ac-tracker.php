<?php
if (!defined('ABSPATH'))
    exit;

class AC_Tracker
{

    public function __construct()
    {
        // add_action('woocommerce_add_to_cart', array($this, 'track_cart'), 10, 6);
        add_action('woocommerce_checkout_update_user_meta', array($this, 'save_checkout_data'), 10, 2);
        add_action('woocommerce_checkout_order_processed', array($this, 'clear_cart'), 10, 3);
        add_action('init', array($this, 'maybe_restore_cart'));

        // Enqueue JS
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public static function create_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'abandoned_carts';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NULL,
            session_id VARCHAR(255) NULL,
            email VARCHAR(255),
            name VARCHAR(255),
            phone VARCHAR(50),
            cart_data LONGTEXT,
            restore_key VARCHAR(255),
            last_activity DATETIME,
            reminder1_sent TINYINT(1) DEFAULT 0,
            reminder2_sent TINYINT(1) DEFAULT 0,
            reminder3_sent TINYINT(1) DEFAULT 0,
            coupon_code VARCHAR(50) DEFAULT NULL,
            status VARCHAR(20) DEFAULT 'abandoned',
            recovered_at DATETIME NULL,
            order_id BIGINT NULL,
            recovered_amount DECIMAL(10,2) DEFAULT 0
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Backup check: dbDelta can sometimes fail to add columns if formatting isn't perfect
        $row = $wpdb->get_row("SELECT * FROM $table_name LIMIT 1");
        if ($row && !isset($row->status)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN status VARCHAR(20) DEFAULT 'abandoned'");
        }
        if ($row && !isset($row->recovered_at)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN recovered_at DATETIME NULL");
        }
        if ($row && !isset($row->order_id)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN order_id BIGINT NULL");
        }
        if ($row && !isset($row->recovered_amount)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN recovered_amount DECIMAL(10,2) DEFAULT 0");
        }
    }

    // public function track_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    //     $cart = WC()->cart->get_cart();
    //     $cart_data = json_encode($cart);

    //     $user_id = get_current_user_id() ?: NULL;
    //     $session_id = WC()->session ? WC()->session->get_customer_id() : session_id();
    //     $email = '';
    //     $name = '';
    //     $phone = '';

    //     if (is_user_logged_in()) {
    //         $user = wp_get_current_user();
    //         $email = $user->user_email;
    //         $name = $user->display_name;
    //     }

    //     $restore_key = wp_generate_password(20, false);

    //     global $wpdb;
    //     $table_name = $wpdb->prefix . 'abandoned_carts';

    //     $wpdb->replace(
    //         $table_name,
    //         array(
    //             'user_id' => $user_id,
    //             'session_id' => $session_id,
    //             'email' => $email,
    //             'name' => $name,
    //             'phone' => $phone,
    //             'cart_data' => $cart_data,
    //             'restore_key' => $restore_key,
    //             'last_activity' => current_time('mysql'),
    //             'reminder1_sent' => 0,
    //             'reminder2_sent' => 0,
    //             'reminder3_sent' => 0
    //         ),
    //         array('%d','%s','%s','%s','%s','%s','%s','%s','%d','%d','%d')
    //     );
    // }

    public function save_checkout_data($user_id, $posted)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'abandoned_carts';

        $email = isset($posted['billing_email']) ? sanitize_email($posted['billing_email']) : '';
        $name = isset($posted['billing_first_name']) ? sanitize_text_field($posted['billing_first_name']) : '';
        $phone = isset($posted['billing_phone']) ? sanitize_text_field($posted['billing_phone']) : '';
        $session_id = WC()->session->get_customer_id();

        // Check if entry already exists for this session, email, or phone (match email/phone only if not empty)
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE (session_id = %s OR (email = %s AND email != '') OR (phone = %s AND phone != '')) AND status = 'abandoned' LIMIT 1",
            $session_id,
            $email,
            $phone
        ));

        $cart_items = WC()->cart->get_cart();
        $cart_data = json_encode($cart_items);

        $data = array(
            'user_id' => $user_id ? $user_id : (is_user_logged_in() ? get_current_user_id() : 0),
            'session_id' => $session_id,
            'email' => $email,
            'name' => $name,
            'phone' => $phone,
            'cart_data' => $cart_data,
            'last_activity' => current_time('mysql'),
        );

        if ($existing) {
            $wpdb->update($table_name, $data, array('id' => $existing));
        } else {
            $data['restore_key'] = wp_generate_uuid4();
            $wpdb->insert($table_name, $data);
        }
        
        // Sync to marketing tools if enabled
        if (!empty($email)) {
            $sync_data = array(
                'name' => $name,
                'phone' => $phone
            );
            
            // Sync to Mailchimp
            if (get_option('ac_mailchimp_enabled', '0') === '1') {
                AC_Mailchimp::sync_contact($email, $sync_data);
            }
            
            // Sync to Brevo
            if (get_option('ac_brevo_enabled', '0') === '1') {
                AC_Brevo::sync_contact($email, $sync_data);
            }
        }
    }



    public function clear_cart($order_id, $posted_data, $order)
    {
        if (!$order instanceof WC_Order)
            return;

        global $wpdb;
        $table_name = $wpdb->prefix . 'abandoned_carts';

        // Check if this session has a recovered cart ID
        $recovered_id = WC()->session ? WC()->session->get('ac_recovered_id') : 0;

        if ($recovered_id) {
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'recovered',
                    'recovered_at' => current_time('mysql'),
                    'order_id' => $order_id,
                    'recovered_amount' => $order->get_total()
                ),
                array('id' => $recovered_id),
                array('%s', '%s', '%d', '%f'),
                array('%d')
            );
            WC()->session->set('ac_recovered_id', null);
        } else {
            // If not directly recovered via link, try to find by session, email, or phone and mark as recovered
            $email = $order->get_billing_email();
            $phone = $order->get_billing_phone();
            $session_id = (WC()->session) ? WC()->session->get_customer_id() : '';

            // Find specific cart ID
            $cart_id = 0;
            if ($session_id) {
                $cart_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table_name WHERE session_id = %s AND status = 'abandoned' ORDER BY last_activity DESC LIMIT 1",
                    $session_id
                ));
            }

            if (!$cart_id && !empty($email)) {
                $cart_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table_name WHERE email = %s AND status = 'abandoned' ORDER BY last_activity DESC LIMIT 1",
                    $email
                ));
            }

            if (!$cart_id && !empty($phone)) {
                $cart_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table_name WHERE phone = %s AND status = 'abandoned' ORDER BY last_activity DESC LIMIT 1",
                    $phone
                ));
            }

            if ($cart_id) {
                $wpdb->update(
                    $table_name,
                    array(
                        'status' => 'recovered',
                        'recovered_at' => current_time('mysql'),
                        'order_id' => $order_id,
                        'recovered_amount' => $order->get_total()
                    ),
                    array('id' => $cart_id),
                    array('%s', '%s', '%d', '%f'),
                    array('%d')
                );
            }
        }
    }


    public function maybe_restore_cart()
    {
        // Check if restore link exists in URL
        if (!isset($_GET['ac_restore']))
            return;

        $restore_key = sanitize_text_field($_GET['ac_restore']);
        if (empty($restore_key))
            return;

        global $wpdb;
        $table_name = $wpdb->prefix . 'abandoned_carts';

        // Get cart entry from DB
        $cart_entry = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE restore_key = %s", $restore_key)
        );

        if (!$cart_entry)
            return;

        // Decode cart data
        $cart_items = json_decode($cart_entry->cart_data, true);
        if (!is_array($cart_items))
            return;

        // Empty current WooCommerce cart
        WC()->cart->empty_cart();

        // Add each item to cart
        foreach ($cart_items as $item) {
            WC()->cart->add_to_cart(
                $item['product_id'],
                $item['quantity'],
                $item['variation_id'] ?? 0,
                $item['variation'] ?? array(),
                $item['cart_item_data'] ?? array()
            );
        }

        // Store the ID in session to track recovery
        WC()->session->set('ac_recovered_id', $cart_entry->id);

        // Redirect to Checkout page
        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script(
            'ac-script',
            plugin_dir_url(__FILE__) . '../assets/js/ac-script.js',
            array('jquery'),
            '1.0',
            true
        );

        wp_localize_script('ac-script', 'ac_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ac_nonce')
        ));
    }

}
