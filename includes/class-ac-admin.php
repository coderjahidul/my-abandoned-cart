<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AC_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function admin_menu() {
        add_menu_page(
            'In complete order',
            'In complete order',
            'manage_options',
            'abandoned-carts',
            array($this, 'admin_page'),
            'dashicons-cart',
            56
        );

        add_submenu_page(
            'abandoned-carts',
            'Settings',
            'Settings',
            'manage_options',
            'ac-settings',
            array($this, 'settings_page')
        );
    }

    public function register_settings() {
        register_setting('ac_settings_group', 'ac_sms_api_key');
        register_setting('ac_settings_group', 'ac_sms_sender_id');
    }

    public function admin_page() {
        echo '<div class="wrap"><h1>In complete order</h1>';

        $ac_list_table = new AC_List_Table();
        $ac_list_table->prepare_items();

        echo '<form method="post">';
        $ac_list_table->search_box('Search Carts', 'ac_search');
        $ac_list_table->display();
        echo '</form></div>';
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>BulkSMS API Settings</h1>
            <p>Configure your <strong>BulkSMS BD</strong> account details below to send SMS reminders.</p>
            <form method="post" action="options.php">
                <?php settings_fields('ac_settings_group'); ?>
                <?php do_settings_sections('ac_settings_group'); ?>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="ac_sms_api_key">API Key</label></th>
                        <td>
                            <input type="text" 
                                   id="ac_sms_api_key" 
                                   name="ac_sms_api_key"
                                   value="<?php echo esc_attr(get_option('ac_sms_api_key')); ?>" 
                                   class="regular-text"
                                   placeholder="Enter your BulkSMSBD API key" />
                            <p class="description">Get your API key from your BulkSMSBD account.</p>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><label for="ac_sms_sender_id">Sender ID</label></th>
                        <td>
                            <input type="text" 
                                   id="ac_sms_sender_id" 
                                   name="ac_sms_sender_id"
                                   value="<?php echo esc_attr(get_option('ac_sms_sender_id')); ?>" 
                                   class="regular-text"
                                   placeholder="Enter your Sender ID" />
                            <p class="description">Your approved sender name registered with BulkSMSBD.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save Settings'); ?>
            </form>

            <p style="margin-top: 20px;">
                🔗 <a href="https://bulksmsbd.net/" target="_blank" style="text-decoration:none; font-weight:bold; color:#0073aa;">
                    Visit BulkSMSBD Website
                </a> — Create an account or get your API credentials.
            </p>
        </div>
        <?php
    }
}

class AC_List_Table extends WP_List_Table {

    private $data;

    public function __construct() {
        parent::__construct([
            'singular' => 'abandoned_cart',
            'plural'   => 'abandoned_carts',
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb'             => '<input type="checkbox" />',
            'id'             => 'ID',
            'email'          => 'Email',
            'name'           => 'Name',
            'phone'          => 'Phone',
            'last_activity'  => 'Last Activity',
            'reminders_sent' => 'Reminders Sent',
            'coupon_code'    => 'Coupon',
            'restore_link'   => 'Restore Link'
        ];
    }

    public function get_bulk_actions() {
        return [
            'delete' => 'Delete'
        ];
    }

    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="cart_ids[]" value="%s" />', $item->id);
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
            case 'email':
            case 'name':
            case 'phone':
            case 'last_activity':
            case 'coupon_code':
                return esc_html($item->$column_name);
            case 'reminders_sent':
                return 'R1:' . ($item->reminder1_sent ? 'Yes' : 'No') . ', ' .
                       'R2:' . ($item->reminder2_sent ? 'Yes' : 'No') . ', ' .
                       'R3:' . ($item->reminder3_sent ? 'Yes' : 'No');
            case 'restore_link':
                $link = add_query_arg('ac_restore', $item->restore_key, site_url('/'));
                return '<a href="' . esc_url($link) . '" target="_blank">Restore</a>';
            default:
                return '';
        }
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'abandoned_carts';

        // Handle bulk delete
        if ('delete' === $this->current_action()) {
            if (!empty($_POST['cart_ids'])) {
                $ids = array_map('intval', $_POST['cart_ids']);
                $ids = implode(',', $ids);
                $wpdb->query("DELETE FROM $table_name WHERE id IN ($ids)");
            }
        }

        // Search
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $where = '';
        if ($search) {
            $where = $wpdb->prepare(
                " WHERE email LIKE %s OR name LIKE %s OR phone LIKE %s ",
                "%$search%",
                "%$search%",
                "%$search%"
            );
        }

        // Pagination
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where");
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page
        ]);

        $this->items = $wpdb->get_results("SELECT * FROM $table_name $where ORDER BY last_activity DESC LIMIT $offset, $per_page");

        $this->_column_headers = [$this->get_columns(), [], ['cb']];
    }
}

/**
 * Example: Use saved BulkSMS credentials
 */
// function ac_send_sms($phone, $message) {
//     $url = "http://bulksmsbd.net/api/smsapi";
//     $api_key = get_option('ac_sms_api_key');
//     $senderid = get_option('ac_sms_sender_id');

//     $data = [
//         "api_key" => $api_key,
//         "senderid" => $senderid,
//         "number" => $phone,
//         "message" => $message,
//     ];

//     $response = wp_remote_post($url, [
//         'body' => $data
//     ]);

//     return wp_remote_retrieve_body($response);
// }
