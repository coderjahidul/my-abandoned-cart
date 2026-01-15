<?php
if (!defined('ABSPATH')) exit;

class AC_Tracker {

    public function __construct() {
        // add_action('woocommerce_add_to_cart', array($this, 'track_cart'), 10, 6);
        add_action('woocommerce_checkout_update_user_meta', array($this, 'save_checkout_data'), 10, 2);
        add_action('woocommerce_checkout_order_processed', array($this, 'clear_cart'), 10, 3);
        add_action('init', array($this, 'maybe_restore_cart'));

        // Enqueue JS
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public static function create_table() {
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
            coupon_code VARCHAR(50) DEFAULT NULL
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
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

    public function save_checkout_data($user_id, $posted) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'abandoned_carts';

        $email  = isset($posted['billing_email']) ? sanitize_email($posted['billing_email']) : '';
        $name   = isset($posted['billing_first_name']) ? sanitize_text_field($posted['billing_first_name']) : '';
        $phone  = isset($posted['billing_phone']) ? sanitize_text_field($posted['billing_phone']) : '';

        $cart_items = WC()->cart->get_cart();
        $cart_data  = json_encode($cart_items);

        $wpdb->insert($table_name, array(
            'user_id'       => $user_id ? $user_id : 0,
            'session_id'    => WC()->session->get_customer_id(),
            'email'         => $email,
            'name'          => $name,
            'phone'         => $phone,
            'cart_data'     => $cart_data,
            'last_activity' => current_time('mysql'),
            'restore_key'   => wp_generate_uuid4(),
        ));
    }



    public function clear_cart($order_id, $posted_data, $order) {
        if (!$order instanceof WC_Order) return;

        $user_id = $order->get_user_id();

        // Guest users-এর জন্য session_id-ও ব্যবহার করা যেতে পারে যদি প্রয়োজন
        $session_id = WC()->session ? WC()->session->get_customer_id() : '';

        global $wpdb;
        $table_name = $wpdb->prefix . 'abandoned_carts';

        if ($user_id) {
            // Logged-in user হলে user_id দিয়ে ডিলিট
            $wpdb->delete($table_name, array('user_id' => $user_id), array('%d'));
        } else if ($session_id) {
            // Guest user হলে session_id দিয়ে ডিলিট
            $wpdb->delete($table_name, array('session_id' => $session_id), array('%s'));
        }
    }


    public function maybe_restore_cart() {
        // Check if restore link exists in URL
        if (!isset($_GET['ac_restore'])) return;

        $restore_key = sanitize_text_field($_GET['ac_restore']);
        if (empty($restore_key)) return;

        global $wpdb;
        $table_name = $wpdb->prefix . 'abandoned_carts';

        // Get cart entry from DB
        $cart_entry = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE restore_key = %s", $restore_key)
        );

        if (!$cart_entry) return;

        // Decode cart data
        $cart_items = json_decode($cart_entry->cart_data, true);
        if (!is_array($cart_items)) return;

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

        // Redirect to Checkout page
        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }

    public function enqueue_scripts() {
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
