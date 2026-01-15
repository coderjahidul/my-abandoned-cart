<?php
if (!defined('ABSPATH'))
    exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AC_Admin
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array('AC_Tracker', 'create_table')); // Ensure table columns exist
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function enqueue_admin_scripts($hook)
    {
        if (strpos($hook, 'abandoned-carts') === false && strpos($hook, 'ac-dashboard') === false) {
            return;
        }
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true);
    }

    public function admin_menu()
    {
        add_menu_page(
            'Abandoned Carts',
            'Abandoned Carts',
            'manage_options',
            'abandoned-carts',
            array($this, 'dashboard_page'),
            'dashicons-chart-area',
            56
        );

        add_submenu_page(
            'abandoned-carts',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'abandoned-carts',
            array($this, 'dashboard_page')
        );

        add_submenu_page(
            'abandoned-carts',
            'Carts List',
            'Carts List',
            'manage_options',
            'ac-list',
            array($this, 'admin_page')
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

    public function register_settings()
    {
        register_setting('ac_settings_group', 'ac_sms_api_key');
        register_setting('ac_settings_group', 'ac_sms_sender_id');
    }

    public function dashboard_page()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'abandoned_carts';

        // Summary Data
        $total_abandoned = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'abandoned'");
        $total_recovered = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'recovered'");
        $total_revenue = $wpdb->get_var("SELECT SUM(recovered_amount) FROM $table_name WHERE status = 'recovered'");
        $recovery_rate = ($total_abandoned + $total_recovered) > 0 ? round(($total_recovered / ($total_abandoned + $total_recovered)) * 100, 2) : 0;

        // Chart Data (Last 7 Days)
        $chart_days = [];
        $abandoned_counts = [];
        $recovered_counts = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $chart_days[] = $date;

            $abandoned_counts[] = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE DATE(last_activity) = %s",
                $date
            ));

            $recovered_counts[] = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE DATE(recovered_at) = %s",
                $date
            ));
        }

        ?>
        <div class="wrap ac-dashboard">
            <h1>Abandoned Cart Analytics</h1>

            <style>
                .ac-stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 20px;
                    margin: 20px 0;
                }

                .ac-stat-card {
                    background: #fff;
                    padding: 20px;
                    border-radius: 8px;
                    border-left: 5px solid #0073aa;
                    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
                }

                .ac-stat-card h3 {
                    margin: 0;
                    color: #666;
                    font-size: 14px;
                    text-transform: uppercase;
                }

                .ac-stat-card .value {
                    font-size: 28px;
                    font-weight: bold;
                    margin-top: 10px;
                    color: #222;
                }

                .ac-chart-container {
                    background: #fff;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
                    margin-top: 20px;
                }
            </style>

            <div class="ac-stats-grid">
                <div class="ac-stat-card">
                    <h3>Total Abandoned</h3>
                    <div class="value"><?php echo number_format($total_abandoned); ?></div>
                </div>
                <div class="ac-stat-card" style="border-left-color: #46b450;">
                    <h3>Total Recovered</h3>
                    <div class="value"><?php echo number_format($total_recovered); ?></div>
                </div>
                <div class="ac-stat-card" style="border-left-color: #ffb900;">
                    <h3>Recovery Rate</h3>
                    <div class="value"><?php echo $recovery_rate; ?>%</div>
                </div>
                <div class="ac-stat-card" style="border-left-color: #9b59b6;">
                    <h3>Revenue Recovered</h3>
                    <div class="value"><?php echo wc_price($total_revenue ?: 0); ?></div>
                </div>
            </div>

            <div class="ac-chart-container">
                <h3>7-Day Recovery Performance</h3>
                <canvas id="acChart" height="100"></canvas>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const ctx = document.getElementById('acChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: <?php echo json_encode($chart_days); ?>,
                            datasets: [{
                                label: 'Abandoned Carts',
                                data: <?php echo json_encode($abandoned_counts); ?>,
                                borderColor: '#0073aa',
                                backgroundColor: 'rgba(0, 115, 170, 0.1)',
                                fill: true,
                                tension: 0.3
                            }, {
                                label: 'Recovered Carts',
                                data: <?php echo json_encode($recovered_counts); ?>,
                                borderColor: '#46b450',
                                backgroundColor: 'rgba(70, 180, 80, 0.1)',
                                fill: true,
                                tension: 0.3
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { position: 'top' }
                            },
                            scales: {
                                y: { beginAtZero: true, ticks: { stepSize: 1 } }
                            }
                        }
                    });
                });
            </script>
        </div>
        <?php
    }

    public function admin_page()
    {
        echo '<div class="wrap"><h1>Carts List</h1>';

        $ac_list_table = new AC_List_Table();
        $ac_list_table->prepare_items();

        echo '<form method="post">';
        $ac_list_table->search_box('Search Carts', 'ac_search');
        $ac_list_table->display();
        echo '</form></div>';
    }

    public function settings_page()
    {
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
                            <input type="text" id="ac_sms_api_key" name="ac_sms_api_key"
                                value="<?php echo esc_attr(get_option('ac_sms_api_key')); ?>" class="regular-text"
                                placeholder="Enter your BulkSMSBD API key" />
                            <p class="description">Get your API key from your BulkSMSBD account.</p>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><label for="ac_sms_sender_id">Sender ID</label></th>
                        <td>
                            <input type="text" id="ac_sms_sender_id" name="ac_sms_sender_id"
                                value="<?php echo esc_attr(get_option('ac_sms_sender_id')); ?>" class="regular-text"
                                placeholder="Enter your Sender ID" />
                            <p class="description">Your approved sender name registered with BulkSMSBD.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save Settings'); ?>
            </form>

            <p style="margin-top: 20px;">
                🔗 <a href="https://bulksmsbd.net/" target="_blank"
                    style="text-decoration:none; font-weight:bold; color:#0073aa;">
                    Visit BulkSMSBD Website
                </a> — Create an account or get your API credentials.
            </p>
        </div>
        <?php
    }
}

class AC_List_Table extends WP_List_Table
{

    private $data;

    public function __construct()
    {
        parent::__construct([
            'singular' => 'abandoned_cart',
            'plural' => 'abandoned_carts',
            'ajax' => false
        ]);
    }

    public function get_columns()
    {
        return [
            'cb' => '<input type="checkbox" />',
            'id' => 'ID',
            'email' => 'Email',
            'name' => 'Name',
            'phone' => 'Phone',
            'last_activity' => 'Last Activity',
            'status' => 'Status',
            'reminders_sent' => 'Reminders Sent',
            'coupon_code' => 'Coupon',
            'restore_link' => 'Restore Link'
        ];
    }

    public function get_bulk_actions()
    {
        return [
            'delete' => 'Delete'
        ];
    }

    public function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="cart_ids[]" value="%s" />', $item->id);
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'id':
            case 'email':
            case 'name':
            case 'phone':
            case 'last_activity':
            case 'coupon_code':
                return esc_html($item->$column_name);
            case 'status':
                $status = isset($item->status) ? $item->status : 'abandoned';
                if ($status === 'recovered') {
                    return '<span class="badge" style="background:#46b450;color:#fff;padding:3px 8px;border-radius:4px;">Recovered</span>';
                }
                return '<span class="badge" style="background:#666;color:#fff;padding:3px 8px;border-radius:4px;">Abandoned</span>';
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

    public function prepare_items()
    {
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
            'per_page' => $per_page
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
