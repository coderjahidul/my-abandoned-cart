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
        
        // AJAX handlers for integration testing
        add_action('wp_ajax_ac_test_sms_connection', array($this, 'ajax_test_sms_connection'));
        add_action('wp_ajax_ac_test_whatsapp_connection', array($this, 'ajax_test_whatsapp_connection'));
        add_action('wp_ajax_ac_test_mailchimp_connection', array($this, 'ajax_test_mailchimp_connection'));
        add_action('wp_ajax_ac_test_brevo_connection', array($this, 'ajax_test_brevo_connection'));
        add_action('wp_ajax_ac_get_mailchimp_lists', array($this, 'ajax_get_mailchimp_lists'));
        add_action('wp_ajax_ac_get_brevo_lists', array($this, 'ajax_get_brevo_lists'));
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
        // SMS Settings (Legacy BulkSMSBD)
        register_setting('ac_settings_group', 'ac_sms_api_key');
        register_setting('ac_settings_group', 'ac_sms_sender_id');
        
        // SMS Gateway Selection
        register_setting('ac_settings_group', 'ac_sms_gateway', array('default' => 'bulksmsbd'));
        register_setting('ac_settings_group', 'ac_sms_enabled', array('default' => '1'));
        register_setting('ac_settings_group', 'ac_sms_test_number');
        
        // Twilio SMS
        register_setting('ac_settings_group', 'ac_twilio_sms_account_sid');
        register_setting('ac_settings_group', 'ac_twilio_sms_auth_token');
        register_setting('ac_settings_group', 'ac_twilio_sms_number');
        
        // Nexmo/Vonage
        register_setting('ac_settings_group', 'ac_nexmo_api_key');
        register_setting('ac_settings_group', 'ac_nexmo_api_secret');
        register_setting('ac_settings_group', 'ac_nexmo_from', array('default' => 'AbandonedCart'));
        
        // SSL Wireless (Bangladesh)
        register_setting('ac_settings_group', 'ac_sslwireless_api_token');
        register_setting('ac_settings_group', 'ac_sslwireless_sid');
        register_setting('ac_settings_group', 'ac_sslwireless_csms_id');
        
        // Banglalink (Bangladesh)
        register_setting('ac_settings_group', 'ac_banglalink_username');
        register_setting('ac_settings_group', 'ac_banglalink_password');
        register_setting('ac_settings_group', 'ac_banglalink_cli');
        
        // WhatsApp Settings
        register_setting('ac_settings_group', 'ac_whatsapp_enabled', array('default' => '0'));
        register_setting('ac_settings_group', 'ac_whatsapp_provider', array('default' => 'twilio'));
        register_setting('ac_settings_group', 'ac_twilio_account_sid');
        register_setting('ac_settings_group', 'ac_twilio_auth_token');
        register_setting('ac_settings_group', 'ac_twilio_whatsapp_number');
        register_setting('ac_settings_group', 'ac_ultramsg_instance_id');
        register_setting('ac_settings_group', 'ac_ultramsg_token');
        register_setting('ac_settings_group', 'ac_whatsapp_test_number');
        
        // Mailchimp Settings
        register_setting('ac_settings_group', 'ac_mailchimp_enabled', array('default' => '0'));
        register_setting('ac_settings_group', 'ac_mailchimp_api_key');
        register_setting('ac_settings_group', 'ac_mailchimp_list_id');
        
        // Brevo Settings
        register_setting('ac_settings_group', 'ac_brevo_enabled', array('default' => '0'));
        register_setting('ac_settings_group', 'ac_brevo_api_key');
        register_setting('ac_settings_group', 'ac_brevo_list_id');
        
        // Notification Channels
        register_setting('ac_settings_group', 'ac_notification_channels', array('default' => 'email,sms'));
        
        // Reminder Timing Settings (in minutes)
        register_setting('ac_settings_group', 'ac_reminder1_delay', array('default' => 30));
        register_setting('ac_settings_group', 'ac_reminder2_delay', array('default' => 1440));
        register_setting('ac_settings_group', 'ac_reminder3_delay', array('default' => 2880));
        
        // Email Template Settings
        register_setting('ac_settings_group', 'ac_email_subject', array('default' => 'আপনি আপনার কার্ট শেষ করেননি!'));
        register_setting('ac_settings_group', 'ac_email_template');
        
        // SMS Template Settings
        register_setting('ac_settings_group', 'ac_sms_template', array('default' => 'হ্যালো {customer_name}, আপনার কার্টে পণ্য রয়েছে। শেষ করতে দেখুন: {restore_link}'));
        
        // WhatsApp Template Settings
        register_setting('ac_settings_group', 'ac_whatsapp_template', array('default' => 'হ্যালো {customer_name}, আপনার কার্টে পণ্য রয়েছে। শেষ করতে দেখুন: {restore_link}'));
        
        // Coupon Settings
        register_setting('ac_settings_group', 'ac_coupon_enabled', array('default' => '1'));
        register_setting('ac_settings_group', 'ac_coupon_type', array('default' => 'percent'));
        register_setting('ac_settings_group', 'ac_coupon_amount', array('default' => 10));
        register_setting('ac_settings_group', 'ac_coupon_reminder', array('default' => '2,3'));
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
        // Get default email template
        $default_email_template = $this->get_default_email_template();
        
        ?>
        <div class="wrap">
            <h1>Abandoned Cart Settings</h1>
            
            <style>
                .ac-tabs { margin: 20px 0; border-bottom: 1px solid #ccc; }
                .ac-tabs button { background: #f1f1f1; border: 1px solid #ccc; border-bottom: none; padding: 10px 20px; margin-right: 5px; cursor: pointer; }
                .ac-tabs button.active { background: #fff; font-weight: bold; }
                .ac-tab-content { display: none; padding: 20px; background: #fff; border: 1px solid #ccc; }
                .ac-tab-content.active { display: block; }
                .ac-placeholder-help { background: #f0f8ff; padding: 10px; margin: 10px 0; border-left: 4px solid #0073aa; }
                .ac-placeholder-help code { background: #e8e8e8; padding: 2px 6px; border-radius: 3px; }
            </style>
            
            <div class="ac-tabs">
                <button class="ac-tab-btn active" data-tab="integrations">Integrations</button>
                <button class="ac-tab-btn" data-tab="timing">Reminder Timing</button>
                <button class="ac-tab-btn" data-tab="email">Email Template</button>
                <button class="ac-tab-btn" data-tab="sms-template">SMS Template</button>
                <button class="ac-tab-btn" data-tab="whatsapp-template">WhatsApp Template</button>
                <button class="ac-tab-btn" data-tab="coupon">Coupon Settings</button>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('ac_settings_group'); ?>
                <?php do_settings_sections('ac_settings_group'); ?>
                
                
                <!-- Integrations Tab -->
                <div class="ac-tab-content active" data-tab="integrations">
                    <h2>Integration Settings</h2>
                    <p>Configure notification channels and third-party integrations for abandoned cart recovery.</p>
                    
                    <style>
                        .ac-integration-section { margin: 30px 0; padding: 20px; background: #f9f9f9; border-left: 4px solid #0073aa; }
                        .ac-integration-section h3 { margin-top: 0; }
                        .ac-test-btn { margin-left: 10px; }
                        .ac-gateway-fields { margin-top: 15px; padding: 15px; background: #fff; border: 1px solid #ddd; }
                    </style>
                    
                    <!-- Notification Channels -->
                    <div class="ac-integration-section">
                        <h3>📢 Notification Channels</h3>
                        <p>Select which channels to use for sending abandoned cart reminders:</p>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Active Channels</th>
                                <td>
                                    <?php 
                                    $channels = explode(',', get_option('ac_notification_channels', 'email,sms'));
                                    ?>
                                    <label><input type="checkbox" name="ac_notification_channels[]" value="email" <?php checked(in_array('email', $channels), true); ?> /> Email</label><br>
                                    <label><input type="checkbox" name="ac_notification_channels[]" value="sms" <?php checked(in_array('sms', $channels), true); ?> /> SMS</label><br>
                                    <label><input type="checkbox" name="ac_notification_channels[]" value="whatsapp" <?php checked(in_array('whatsapp', $channels), true); ?> /> WhatsApp</label>
                                    <p class="description">Reminders will be sent through all selected channels.</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- SMS Gateway Settings -->
                    <div class="ac-integration-section">
                        <h3>📱 SMS Gateway</h3>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row"><label for="ac_sms_enabled">Enable SMS</label></th>
                                <td>
                                    <input type="checkbox" id="ac_sms_enabled" name="ac_sms_enabled" value="1" <?php checked(get_option('ac_sms_enabled', '1'), '1'); ?> />
                                    <label for="ac_sms_enabled">Send SMS reminders</label>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><label for="ac_sms_gateway">SMS Provider</label></th>
                                <td>
                                    <select id="ac_sms_gateway" name="ac_sms_gateway" class="regular-text">
                                        <option value="bulksmsbd" <?php selected(get_option('ac_sms_gateway', 'bulksmsbd'), 'bulksmsbd'); ?>>BulkSMSBD (Bangladesh)</option>
                                        <option value="twilio" <?php selected(get_option('ac_sms_gateway'), 'twilio'); ?>>Twilio</option>
                                        <option value="nexmo" <?php selected(get_option('ac_sms_gateway'), 'nexmo'); ?>>Nexmo/Vonage</option>
                                        <option value="sslwireless" <?php selected(get_option('ac_sms_gateway'), 'sslwireless'); ?>>SSL Wireless (Bangladesh)</option>
                                        <option value="banglalink" <?php selected(get_option('ac_sms_gateway'), 'banglalink'); ?>>Banglalink (Bangladesh)</option>
                                    </select>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><label for="ac_sms_test_number">Test Phone Number</label></th>
                                <td>
                                    <input type="text" id="ac_sms_test_number" name="ac_sms_test_number" value="<?php echo esc_attr(get_option('ac_sms_test_number')); ?>" class="regular-text" placeholder="+8801XXXXXXXXX" />
                                    <button type="button" class="button ac-test-btn" onclick="acTestSMS()">Test Connection</button>
                                    <p class="description">Phone number for testing SMS delivery.</p>
                                    <div id="ac-sms-test-result" style="margin-top:10px;"></div>
                                </td>
                            </tr>
                        </table>
                        
                        <!-- BulkSMSBD Fields -->
                        <div class="ac-gateway-fields" data-gateway="bulksmsbd">
                            <h4>BulkSMSBD Configuration</h4>
                            <table class="form-table">
                                <tr valign="top">
                                    <th scope="row"><label for="ac_sms_api_key">API Key</label></th>
                                    <td><input type="text" id="ac_sms_api_key" name="ac_sms_api_key" value="<?php echo esc_attr(get_option('ac_sms_api_key')); ?>" class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_sms_sender_id">Sender ID</label></th>
                                    <td><input type="text" id="ac_sms_sender_id" name="ac_sms_sender_id" value="<?php echo esc_attr(get_option('ac_sms_sender_id')); ?>" class="regular-text" /></td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Twilio SMS Fields -->
                        <div class="ac-gateway-fields" data-gateway="twilio" style="display:none;">
                            <h4>Twilio SMS Configuration</h4>
                            <table class="form-table">
                                <tr valign="top">
                                    <th scope="row"><label for="ac_twilio_sms_account_sid">Account SID</label></th>
                                    <td><input type="text" id="ac_twilio_sms_account_sid" name="ac_twilio_sms_account_sid" value="<?php echo esc_attr(get_option('ac_twilio_sms_account_sid')); ?>" class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_twilio_sms_auth_token">Auth Token</label></th>
                                    <td><input type="password" id="ac_twilio_sms_auth_token" name="ac_twilio_sms_auth_token" value="<?php echo esc_attr(get_option('ac_twilio_sms_auth_token')); ?>" class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_twilio_sms_number">From Number</label></th>
                                    <td><input type="text" id="ac_twilio_sms_number" name="ac_twilio_sms_number" value="<?php echo esc_attr(get_option('ac_twilio_sms_number')); ?>" class="regular-text" placeholder="+1234567890" /></td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Nexmo Fields -->
                        <div class="ac-gateway-fields" data-gateway="nexmo" style="display:none;">
                            <h4>Nexmo/Vonage Configuration</h4>
                            <table class="form-table">
                                <tr valign="top">
                                    <th scope="row"><label for="ac_nexmo_api_key">API Key</label></th>
                                    <td><input type="text" id="ac_nexmo_api_key" name="ac_nexmo_api_key" value="<?php echo esc_attr(get_option('ac_nexmo_api_key')); ?>" class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_nexmo_api_secret">API Secret</label></th>
                                    <td><input type="password" id="ac_nexmo_api_secret" name="ac_nexmo_api_secret" value="<?php echo esc_attr(get_option('ac_nexmo_api_secret')); ?>" class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_nexmo_from">From Name</label></th>
                                    <td><input type="text" id="ac_nexmo_from" name="ac_nexmo_from" value="<?php echo esc_attr(get_option('ac_nexmo_from', 'AbandonedCart')); ?>" class="regular-text" /></td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- SSL Wireless Fields -->
                        <div class="ac-gateway-fields" data-gateway="sslwireless" style="display:none;">
                            <h4>SSL Wireless Configuration</h4>
                            <table class="form-table">
                                <tr valign="top">
                                    <th scope="row"><label for="ac_sslwireless_api_token">API Token</label></th>
                                    <td><input type="text" id="ac_sslwireless_api_token" name="ac_sslwireless_api_token" value="<?php echo esc_attr(get_option('ac_sslwireless_api_token')); ?>" class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_sslwireless_sid">SID</label></th>
                                    <td><input type="text" id="ac_sslwireless_sid" name="ac_sslwireless_sid" value="<?php echo esc_attr(get_option('ac_sslwireless_sid')); ?>" class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_sslwireless_csms_id">CSMS ID</label></th>
                                    <td><input type="text" id="ac_sslwireless_csms_id" name="ac_sslwireless_csms_id" value="<?php echo esc_attr(get_option('ac_sslwireless_csms_id')); ?>" class="regular-text" /></td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Banglalink Fields -->
                        <div class="ac-gateway-fields" data-gateway="banglalink" style="display:none;">
                            <h4>Banglalink Configuration</h4>
                            <table class="form-table">
                                <tr valign="top">
                                    <th scope="row"><label for="ac_banglalink_username">Username</label></th>
                                    <td><input type="text" id="ac_banglalink_username" name="ac_banglalink_username" value="<?php echo esc_attr(get_option('ac_banglalink_username')); ?>" class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_banglalink_password">Password</label></th>
                                    <td><input type="password" id="ac_banglalink_password" name="ac_banglalink_password" value="<?php echo esc_attr(get_option('ac_banglalink_password')); ?>" class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_banglalink_cli">CLI</label></th>
                                    <td><input type="text" id="ac_banglalink_cli" name="ac_banglalink_cli" value="<?php echo esc_attr(get_option('ac_banglalink_cli')); ?>" class="regular-text" /></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- WhatsApp Settings -->
                    <div class="ac-integration-section">
                        <h3>💬 WhatsApp</h3>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row"><label for="ac_whatsapp_enabled">Enable WhatsApp</label></th>
                                <td>
                                    <input type="checkbox" id="ac_whatsapp_enabled" name="ac_whatsapp_enabled" value="1" <?php checked(get_option('ac_whatsapp_enabled', '0'), '1'); ?> />
                                    <label for="ac_whatsapp_enabled">Send WhatsApp reminders</label>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><label for="ac_whatsapp_provider">WhatsApp Provider</label></th>
                                <td>
                                    <select id="ac_whatsapp_provider" name="ac_whatsapp_provider" class="regular-text">
                                        <option value="twilio" <?php selected(get_option('ac_whatsapp_provider', 'twilio'), 'twilio'); ?>>Twilio</option>
                                        <option value="ultramsg" <?php selected(get_option('ac_whatsapp_provider'), 'ultramsg'); ?>>UltraMsg</option>
                                    </select>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><label for="ac_whatsapp_test_number">Test WhatsApp Number</label></th>
                                <td>
                                    <input type="text" id="ac_whatsapp_test_number" name="ac_whatsapp_test_number" value="<?php echo esc_attr(get_option('ac_whatsapp_test_number')); ?>" class="regular-text" placeholder="+8801XXXXXXXXX" />
                                    <button type="button" class="button ac-test-btn" onclick="acTestWhatsApp()">Test Connection</button>
                                    <p class="description">WhatsApp number for testing.</p>
                                    <div id="ac-whatsapp-test-result" style="margin-top:10px;"></div>
                                </td>
                            </tr>
                        </table>
                        
                        <!-- Twilio WhatsApp Fields -->
                        <div class="ac-whatsapp-fields" data-provider="twilio">
                            <h4>Twilio WhatsApp Configuration</h4>
                            <table class="form-table">
                                <tr valign="top">
                                    <th scope="row"><label for="ac_twilio_account_sid">Account SID</label></th>
                                    <td><input type="text" id="ac_twilio_account_sid" name="ac_twilio_account_sid" value="<?php echo esc_attr(get_option('ac_twilio_account_sid')); ?>" class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_twilio_auth_token">Auth Token</label></th>
                                    <td><input type="password" id="ac_twilio_auth_token" name="ac_twilio_auth_token" value="<?php echo esc_attr(get_option('ac_twilio_auth_token')); ?>" class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_twilio_whatsapp_number">WhatsApp Number</label></th>
                                    <td>
                                        <input type="text" id="ac_twilio_whatsapp_number" name="ac_twilio_whatsapp_number" value="<?php echo esc_attr(get_option('ac_twilio_whatsapp_number')); ?>" class="regular-text" placeholder="whatsapp:+14155238886" />
                                        <p class="description">Format: whatsapp:+14155238886</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- UltraMsg Fields -->
                        <div class="ac-whatsapp-fields" data-provider="ultramsg" style="display:none;">
                            <h4>UltraMsg Configuration</h4>
                            <table class="form-table">
                                <tr valign="top">
                                    <th scope="row"><label for="ac_ultramsg_instance_id">Instance ID</label></th>
                                    <td><input type="text" id="ac_ultramsg_instance_id" name="ac_ultramsg_instance_id" value="<?php echo esc_attr(get_option('ac_ultramsg_instance_id')); ?>" class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_ultramsg_token">Token</label></th>
                                    <td><input type="text" id="ac_ultramsg_token" name="ac_ultramsg_token" value="<?php echo esc_attr(get_option('ac_ultramsg_token')); ?>" class="regular-text" /></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Mailchimp Settings -->
                    <div class="ac-integration-section">
                        <h3>📧 Mailchimp</h3>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row"><label for="ac_mailchimp_enabled">Enable Mailchimp</label></th>
                                <td>
                                    <input type="checkbox" id="ac_mailchimp_enabled" name="ac_mailchimp_enabled" value="1" <?php checked(get_option('ac_mailchimp_enabled', '0'), '1'); ?> />
                                    <label for="ac_mailchimp_enabled">Sync abandoned carts to Mailchimp</label>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><label for="ac_mailchimp_api_key">API Key</label></th>
                                <td>
                                    <input type="text" id="ac_mailchimp_api_key" name="ac_mailchimp_api_key" value="<?php echo esc_attr(get_option('ac_mailchimp_api_key')); ?>" class="regular-text" />
                                    <button type="button" class="button ac-test-btn" onclick="acTestMailchimp()">Test Connection</button>
                                    <button type="button" class="button" onclick="acLoadMailchimpLists()">Load Lists</button>
                                    <p class="description">Get your API key from Mailchimp account settings.</p>
                                    <div id="ac-mailchimp-test-result" style="margin-top:10px;"></div>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><label for="ac_mailchimp_list_id">Audience/List</label></th>
                                <td>
                                    <select id="ac_mailchimp_list_id" name="ac_mailchimp_list_id" class="regular-text">
                                        <option value="">Select a list...</option>
                                    </select>
                                    <p class="description">Select the Mailchimp audience to sync contacts to.</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Brevo Settings -->
                    <div class="ac-integration-section">
                        <h3>📨 Brevo (Sendinblue)</h3>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row"><label for="ac_brevo_enabled">Enable Brevo</label></th>
                                <td>
                                    <input type="checkbox" id="ac_brevo_enabled" name="ac_brevo_enabled" value="1" <?php checked(get_option('ac_brevo_enabled', '0'), '1'); ?> />
                                    <label for="ac_brevo_enabled">Sync abandoned carts to Brevo</label>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><label for="ac_brevo_api_key">API Key</label></th>
                                <td>
                                    <input type="text" id="ac_brevo_api_key" name="ac_brevo_api_key" value="<?php echo esc_attr(get_option('ac_brevo_api_key')); ?>" class="regular-text" />
                                    <button type="button" class="button ac-test-btn" onclick="acTestBrevo()">Test Connection</button>
                                    <button type="button" class="button" onclick="acLoadBrevoLists()">Load Lists</button>
                                    <p class="description">Get your API key from Brevo account settings.</p>
                                    <div id="ac-brevo-test-result" style="margin-top:10px;"></div>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><label for="ac_brevo_list_id">Contact List</label></th>
                                <td>
                                    <select id="ac_brevo_list_id" name="ac_brevo_list_id" class="regular-text">
                                        <option value="">Select a list...</option>
                                    </select>
                                    <p class="description">Select the Brevo list to sync contacts to.</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Reminder Timing Tab -->
                <div class="ac-tab-content" data-tab="timing">
                    <h2>Reminder Timing</h2>
                    <p>Set custom delays for each reminder (in minutes).</p>
                    
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><label for="ac_reminder1_delay">1st Reminder Delay</label></th>
                            <td>
                                <input type="number" id="ac_reminder1_delay" name="ac_reminder1_delay"
                                    value="<?php echo esc_attr(get_option('ac_reminder1_delay', 30)); ?>" class="small-text" min="1" />
                                <span>minutes</span>
                                <p class="description">Default: 30 minutes (0.5 hours)</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="ac_reminder2_delay">2nd Reminder Delay</label></th>
                            <td>
                                <input type="number" id="ac_reminder2_delay" name="ac_reminder2_delay"
                                    value="<?php echo esc_attr(get_option('ac_reminder2_delay', 1440)); ?>" class="small-text" min="1" />
                                <span>minutes</span>
                                <p class="description">Default: 1440 minutes (24 hours)</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="ac_reminder3_delay">3rd Reminder Delay</label></th>
                            <td>
                                <input type="number" id="ac_reminder3_delay" name="ac_reminder3_delay"
                                    value="<?php echo esc_attr(get_option('ac_reminder3_delay', 2880)); ?>" class="small-text" min="1" />
                                <span>minutes</span>
                                <p class="description">Default: 2880 minutes (48 hours)</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Email Template Tab -->
                <div class="ac-tab-content" data-tab="email">
                    <h2>Email Template</h2>
                    <p>Customize the email subject and body sent to customers.</p>
                    
                    <div class="ac-placeholder-help">
                        <strong>Available Placeholders:</strong><br>
                        <code>{customer_name}</code> - Customer's name<br>
                        <code>{restore_link}</code> - Cart restoration link<br>
                        <code>{coupon_code}</code> - Generated coupon code (if applicable)<br>
                        <code>{site_name}</code> - Your site name
                    </div>
                    
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><label for="ac_email_subject">Email Subject</label></th>
                            <td>
                                <input type="text" id="ac_email_subject" name="ac_email_subject"
                                    value="<?php echo esc_attr(get_option('ac_email_subject', 'আপনি আপনার কার্ট শেষ করেননি!')); ?>" 
                                    class="large-text" />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="ac_email_template">Email Body</label></th>
                            <td>
                                <?php
                                $content = get_option('ac_email_template', $default_email_template);
                                wp_editor($content, 'ac_email_template', array(
                                    'textarea_name' => 'ac_email_template',
                                    'textarea_rows' => 15,
                                    'media_buttons' => false,
                                    'teeny' => false,
                                    'tinymce' => array(
                                        'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,link,alignleft,aligncenter,alignright,undo,redo',
                                    )
                                ));
                                ?>
                                <p class="description">Use the placeholders above in your email template.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- SMS Template Tab -->
                <div class="ac-tab-content" data-tab="sms-template">
                    <h2>SMS Template</h2>
                    <p>Customize the SMS message sent to customers.</p>
                    
                    <div class="ac-placeholder-help">
                        <strong>Available Placeholders:</strong><br>
                        <code>{customer_name}</code> - Customer's name<br>
                        <code>{restore_link}</code> - Cart restoration link<br>
                        <code>{coupon_code}</code> - Generated coupon code (if applicable)<br>
                        <code>{site_name}</code> - Your site name
                    </div>
                    
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><label for="ac_sms_template">SMS Message</label></th>
                            <td>
                                <textarea id="ac_sms_template" name="ac_sms_template" rows="5" class="large-text"><?php 
                                    echo esc_textarea(get_option('ac_sms_template', 'হ্যালো {customer_name}, আপনার কার্টে পণ্য রয়েছে। শেষ করতে দেখুন: {restore_link}')); 
                                ?></textarea>
                                <p class="description">Keep SMS messages concise. Use placeholders for dynamic content.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- WhatsApp Template Tab -->
                <div class="ac-tab-content" data-tab="whatsapp-template">
                    <h2>WhatsApp Template</h2>
                    <p>Customize the WhatsApp message sent to customers.</p>
                    
                    <div class="ac-placeholder-help">
                        <strong>Available Placeholders:</strong><br>
                        <code>{customer_name}</code> - Customer's name<br>
                        <code>{restore_link}</code> - Cart restoration link<br>
                        <code>{coupon_code}</code> - Generated coupon code (if applicable)<br>
                        <code>{site_name}</code> - Your site name
                    </div>
                    
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><label for="ac_whatsapp_template">WhatsApp Message</label></th>
                            <td>
                                <textarea id="ac_whatsapp_template" name="ac_whatsapp_template" rows="5" class="large-text"><?php 
                                    echo esc_textarea(get_option('ac_whatsapp_template', 'হ্যালো {customer_name}, আপনার কার্টে পণ্য রয়েছে। শেষ করতে দেখুন: {restore_link}')); 
                                ?></textarea>
                                <p class="description">Keep WhatsApp messages concise. Use placeholders for dynamic content.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Coupon Settings Tab -->
                <div class="ac-tab-content" data-tab="coupon">
                    <h2>Coupon Settings</h2>
                    <p>Configure automatic coupon generation for reminders.</p>
                    
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><label for="ac_coupon_enabled">Enable Coupons</label></th>
                            <td>
                                <input type="checkbox" id="ac_coupon_enabled" name="ac_coupon_enabled" value="1" 
                                    <?php checked(get_option('ac_coupon_enabled', '1'), '1'); ?> />
                                <label for="ac_coupon_enabled">Generate coupons for reminders</label>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="ac_coupon_type">Discount Type</label></th>
                            <td>
                                <select id="ac_coupon_type" name="ac_coupon_type">
                                    <option value="percent" <?php selected(get_option('ac_coupon_type', 'percent'), 'percent'); ?>>Percentage Discount</option>
                                    <option value="fixed_cart" <?php selected(get_option('ac_coupon_type', 'percent'), 'fixed_cart'); ?>>Fixed Cart Discount</option>
                                </select>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="ac_coupon_amount">Discount Amount</label></th>
                            <td>
                                <input type="number" id="ac_coupon_amount" name="ac_coupon_amount"
                                    value="<?php echo esc_attr(get_option('ac_coupon_amount', 10)); ?>" 
                                    class="small-text" min="0" step="0.01" />
                                <p class="description">For percentage: enter 10 for 10%. For fixed: enter amount in your currency.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="ac_coupon_reminder">Include Coupon In</label></th>
                            <td>
                                <label><input type="checkbox" name="ac_coupon_reminder[]" value="2" 
                                    <?php $reminders = explode(',', get_option('ac_coupon_reminder', '2,3')); checked(in_array('2', $reminders), true); ?> /> 
                                    2nd Reminder</label><br>
                                <label><input type="checkbox" name="ac_coupon_reminder[]" value="3" 
                                    <?php checked(in_array('3', $reminders), true); ?> /> 
                                    3rd Reminder</label>
                                <p class="description">Select which reminders should include a coupon code.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button('Save All Settings'); ?>
            </form>
            
            <script>
                jQuery(document).ready(function($) {
                    // Tab switching
                    $('.ac-tab-btn').on('click', function() {
                        var tab = $(this).data('tab');
                        $('.ac-tab-btn').removeClass('active');
                        $('.ac-tab-content').removeClass('active');
                        $(this).addClass('active');
                        $('.ac-tab-content[data-tab="' + tab + '"]').addClass('active');
                    });
                    
                    // SMS Gateway field visibility
                    function toggleSMSGatewayFields() {
                        var gateway = $('#ac_sms_gateway').val();
                        $('.ac-gateway-fields').hide();
                        $('.ac-gateway-fields[data-gateway="' + gateway + '"]').show();
                    }
                    
                    $('#ac_sms_gateway').on('change', toggleSMSGatewayFields);
                    toggleSMSGatewayFields(); // Initialize on load
                    
                    // WhatsApp Provider field visibility
                    function toggleWhatsAppFields() {
                        var provider = $('#ac_whatsapp_provider').val();
                        $('.ac-whatsapp-fields').hide();
                        $('.ac-whatsapp-fields[data-provider="' + provider + '"]').show();
                    }
                    
                    $('#ac_whatsapp_provider').on('change', toggleWhatsAppFields);
                    toggleWhatsAppFields(); // Initialize on load
                });
                
                // AJAX Test Functions
                function acTestSMS() {
                    jQuery.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: { action: 'ac_test_sms_connection' },
                        beforeSend: function() {
                            jQuery('#ac-sms-test-result').html('<span style="color:#0073aa;">Testing...</span>');
                        },
                        success: function(response) {
                            if (response.success) {
                                jQuery('#ac-sms-test-result').html('<span style="color:#46b450;">✓ ' + response.data.message + '</span>');
                            } else {
                                jQuery('#ac-sms-test-result').html('<span style="color:#dc3232;">✗ ' + response.data.message + '</span>');
                            }
                        },
                        error: function() {
                            jQuery('#ac-sms-test-result').html('<span style="color:#dc3232;">✗ Connection failed</span>');
                        }
                    });
                }
                
                function acTestWhatsApp() {
                    jQuery.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: { action: 'ac_test_whatsapp_connection' },
                        beforeSend: function() {
                            jQuery('#ac-whatsapp-test-result').html('<span style="color:#0073aa;">Testing...</span>');
                        },
                        success: function(response) {
                            if (response.success) {
                                jQuery('#ac-whatsapp-test-result').html('<span style="color:#46b450;">✓ ' + response.data.message + '</span>');
                            } else {
                                jQuery('#ac-whatsapp-test-result').html('<span style="color:#dc3232;">✗ ' + response.data.message + '</span>');
                            }
                        },
                        error: function() {
                            jQuery('#ac-whatsapp-test-result').html('<span style="color:#dc3232;">✗ Connection failed</span>');
                        }
                    });
                }
                
                function acTestMailchimp() {
                    jQuery.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: { action: 'ac_test_mailchimp_connection' },
                        beforeSend: function() {
                            jQuery('#ac-mailchimp-test-result').html('<span style="color:#0073aa;">Testing...</span>');
                        },
                        success: function(response) {
                            if (response.success) {
                                jQuery('#ac-mailchimp-test-result').html('<span style="color:#46b450;">✓ ' + response.data.message + '</span>');
                            } else {
                                jQuery('#ac-mailchimp-test-result').html('<span style="color:#dc3232;">✗ ' + response.data.message + '</span>');
                            }
                        },
                        error: function() {
                            jQuery('#ac-mailchimp-test-result').html('<span style="color:#dc3232;">✗ Connection failed</span>');
                        }
                    });
                }
                
                function acTestBrevo() {
                    jQuery.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: { action: 'ac_test_brevo_connection' },
                        beforeSend: function() {
                            jQuery('#ac-brevo-test-result').html('<span style="color:#0073aa;">Testing...</span>');
                        },
                        success: function(response) {
                            if (response.success) {
                                jQuery('#ac-brevo-test-result').html('<span style="color:#46b450;">✓ ' + response.data.message + '</span>');
                            } else {
                                jQuery('#ac-brevo-test-result').html('<span style="color:#dc3232;">✗ ' + response.data.message + '</span>');
                            }
                        },
                        error: function() {
                            jQuery('#ac-brevo-test-result').html('<span style="color:#dc3232;">✗ Connection failed</span>');
                        }
                    });
                }
                
                function acLoadMailchimpLists() {
                    jQuery.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: { action: 'ac_get_mailchimp_lists' },
                        beforeSend: function() {
                            jQuery('#ac-mailchimp-test-result').html('<span style="color:#0073aa;">Loading lists...</span>');
                        },
                        success: function(response) {
                            if (response.success) {
                                var select = jQuery('#ac_mailchimp_list_id');
                                select.empty().append('<option value="">Select a list...</option>');
                                jQuery.each(response.data.lists, function(i, list) {
                                    select.append('<option value="' + list.id + '">' + list.name + '</option>');
                                });
                                jQuery('#ac-mailchimp-test-result').html('<span style="color:#46b450;">✓ Lists loaded</span>');
                            } else {
                                jQuery('#ac-mailchimp-test-result').html('<span style="color:#dc3232;">✗ ' + response.data.message + '</span>');
                            }
                        },
                        error: function() {
                            jQuery('#ac-mailchimp-test-result').html('<span style="color:#dc3232;">✗ Failed to load lists</span>');
                        }
                    });
                }
                
                function acLoadBrevoLists() {
                    jQuery.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: { action: 'ac_get_brevo_lists' },
                        beforeSend: function() {
                            jQuery('#ac-brevo-test-result').html('<span style="color:#0073aa;">Loading lists...</span>');
                        },
                        success: function(response) {
                            if (response.success) {
                                var select = jQuery('#ac_brevo_list_id');
                                select.empty().append('<option value="">Select a list...</option>');
                                jQuery.each(response.data.lists, function(i, list) {
                                    select.append('<option value="' + list.id + '">' + list.name + '</option>');
                                });
                                jQuery('#ac-brevo-test-result').html('<span style="color:#46b450;">✓ Lists loaded</span>');
                            } else {
                                jQuery('#ac-brevo-test-result').html('<span style="color:#dc3232;">✗ ' + response.data.message + '</span>');
                            }
                        },
                        error: function() {
                            jQuery('#ac-brevo-test-result').html('<span style="color:#dc3232;">✗ Failed to load lists</span>');
                        }
                    });
                }
            </script>
        </div>
        <?php
    }
    
    private function get_default_email_template()
    {
        return '<h2>হ্যালো {customer_name},</h2>
<p>আপনি আপনার কার্টে কিছু প্রোডাক্ট রেখেছেন কিন্তু Checkout শেষ করেননি।</p>
<p>Checkout করতে নিচের বাটনে ক্লিক করুন:</p>
<p><a href="{restore_link}" style="background: #0073aa; color: #fff; padding: 12px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">Checkout Now</a></p>
<p><strong>বিশেষ অফার:</strong> আপনার ডিসকাউন্ট কুপন কোড: <strong>{coupon_code}</strong></p>
<p>ধন্যবাদ,<br>{site_name}</p>';
    }
    
    // AJAX Handlers for Integration Testing
    public function ajax_test_sms_connection() {
        $result = AC_SMS_Gateway::test_connection();
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function ajax_test_whatsapp_connection() {
        $result = AC_WhatsApp::test_connection();
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function ajax_test_mailchimp_connection() {
        $result = AC_Mailchimp::test_connection();
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function ajax_test_brevo_connection() {
        $result = AC_Brevo::test_connection();
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function ajax_get_mailchimp_lists() {
        $lists = AC_Mailchimp::get_lists();
        if (!empty($lists)) {
            wp_send_json_success(array('lists' => $lists));
        } else {
            wp_send_json_error(array('message' => 'No lists found or API error'));
        }
    }
    
    public function ajax_get_brevo_lists() {
        $lists = AC_Brevo::get_lists();
        if (!empty($lists)) {
            wp_send_json_success(array('lists' => $lists));
        } else {
            wp_send_json_error(array('message' => 'No lists found or API error'));
        }
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
