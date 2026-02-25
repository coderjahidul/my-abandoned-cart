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
        add_action('wp_ajax_ac_mark_recovered', array($this, 'ajax_mark_recovered'));
        add_action('wp_ajax_ac_get_cart_details', array($this, 'ajax_get_cart_details'));
    }

    public function enqueue_admin_scripts($hook)
    {
        if (strpos($hook, 'abandoned-carts') === false && strpos($hook, 'ac-dashboard') === false && strpos($hook, 'ac-info') === false) {
            return;
        }
        wp_enqueue_style('ac-admin-style', plugins_url('assets/css/ac-admin.css', dirname(__FILE__)), array(), '2.0.0');
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

        add_submenu_page(
            'abandoned-carts',
            'Plugin Info',
            'Plugin Info',
            'manage_options',
            'ac-info',
            array($this, 'info_page')
        );
    }

    public function info_page()
    {
        ?>
        <div class="wrap">
            <h1>My Abandoned Cart - Plugin Info</h1>

            <div class="ac-info-card">
                <h2>My Abandoned Cart <span class="ac-version-badge">v2.0</span></h2>
                <p><strong>Comprehensive solution for WooCommerce to recover lost sales.</strong></p>
                <p>Created by <strong>MD Jahidul Islam Sabuz</strong></p>

                <hr style="margin: 24px 0; border: 0; border-top: 1px solid #dcdcde;">

                <h3>🚀 How It Works</h3>
                <p>The plugin tracks whenever a user adds items to their cart but fails to complete the purchase. It captures
                    guest data, sends automated notifications, and provides a seamless restoration process.</p>

                <h3>🔄 Recovery Workflow</h3>
                <ul>
                    <li><strong>1st Reminder:</strong> Sent after 30 minutes (Friendly nudge)</li>
                    <li><strong>2nd Reminder:</strong> Sent after 24 hours (Urgency + 10% Off Coupon)</li>
                    <li><strong>3rd Reminder:</strong> Sent after 48 hours (Final Reminder + 10% Off Coupon)</li>
                </ul>

                <h3>📢 Notification Channels</h3>
                <ul>
                    <li><strong>Email:</strong> Beautifully formatted HTML emails with restore links.</li>
                    <li><strong>SMS:</strong> Integration with BulkSMSBD, Twilio, Nexmo, SSL Wireless, and Banglalink.</li>
                    <li><strong>WhatsApp:</strong> Send messages via Twilio or UltraMsg.</li>
                </ul>

                <h3>📊 Marketing Integration</h3>
                <ul>
                    <li><strong>Mailchimp:</strong> Auto-sync abandoned cart contacts to your audience.</li>
                    <li><strong>Brevo:</strong> Sync contacts to Brevo lists for targeted campaigns.</li>
                </ul>

                <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ddd;">

                <p>
                    <a href="https://github.com/coderjahidul/my-abandoned-cart" target="_blank"
                        class="button button-primary">Visit GitHub Repository</a>
                </p>
            </div>
        </div>
        <?php
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
        echo '</form>';
        ?>

        <!-- ===== Cart View Modal ===== -->
        <div id="ac-view-modal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.6);overflow-y:auto;padding:20px 0;">
            <div style="background:#fff;border-radius:8px;max-width:820px;margin:0 auto;box-shadow:0 10px 50px rgba(0,0,0,.3);overflow:hidden;">
                <!-- Modal Header -->
                <div style="background:#1d2327;padding:14px 22px;display:flex;align-items:center;justify-content:space-between;">
                    <h2 style="margin:0;color:#fff;font-size:15px;font-weight:600;">
                        &#128722; Cart Details &mdash;
                        <span id="ac-modal-customer-name" style="font-weight:400;opacity:.85;"></span>
                    </h2>
                    <button id="ac-modal-close" style="background:none;border:none;color:#aaa;font-size:26px;line-height:1;cursor:pointer;padding:0 4px;">&times;</button>
                </div>
                <!-- Modal Body -->
                <div id="ac-modal-body" style="padding:24px;">
                    <p style="color:#888;text-align:center;padding:30px 0;">Loading&hellip;</p>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function ($) {

            /* ======== VIEW CART MODAL ======== */
            $(document).on('click', '.ac-view-cart-btn', function () {
                var cartId = $(this).data('id');
                var nonce  = $(this).data('nonce');

                $('#ac-modal-customer-name').text('Loading…');
                $('#ac-modal-body').html('<p style="color:#888;text-align:center;padding:30px 0;">Loading&hellip;</p>');
                $('#ac-view-modal').fadeIn(200);

                $.ajax({
                    url:  ajaxurl,
                    type: 'POST',
                    data: { action: 'ac_get_cart_details', cart_id: cartId, nonce: nonce },
                    success: function (res) {
                        if (!res.success) {
                            $('#ac-modal-body').html('<p style="color:red;padding:20px;">' + res.data.message + '</p>');
                            return;
                        }
                        var d = res.data;
                        $('#ac-modal-customer-name').text(d.name || d.email || '#' + cartId);

                        /* --- Customer info grid --- */
                        var infoRows = [
                            ['&#128100; Name',    d.name],
                            ['&#9993; Email',     d.email ? '<a href="mailto:'+d.email+'" style="color:#2271b1;">'+d.email+'</a>' : ''],
                            ['&#128222; Phone',   d.phone],
                            ['&#128205; Address', d.address],
                            ['&#9719; Status',    d.status ? '<span style="text-transform:capitalize;font-weight:600;color:' + (d.status==='recovered'?'#46b450':'#f0ad4e') + ';">' + d.status + '</span>' : ''],
                            ['&#128336; Activity',d.last_activity],
                            ['&#127991; Coupon',  d.coupon_code ? '<code style="background:#e8f0fe;padding:2px 8px;border-radius:3px;font-size:12px;">'+d.coupon_code+'</code>' : '']
                        ].filter(function(r){ return r[1]; });

                        var info = '<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px 28px;background:#f6f7f7;border-radius:6px;padding:16px;margin-bottom:22px;font-size:13px;">';
                        infoRows.forEach(function(r){
                            info += '<div><strong style="display:block;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#888;margin-bottom:3px;">'+r[0]+'</strong><span>'+r[1]+'</span></div>';
                        });
                        info += '</div>';

                        /* --- Products table --- */
                        var tbl = '<div style="overflow-x:auto;">';
                        tbl += '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
                        tbl += '<thead><tr style="background:#f6f7f7;">';
                        ['Product','Qty','Unit Price','Total'].forEach(function(h,i){
                            var align = i === 0 ? 'left' : (i === 1 ? 'center' : 'right');
                            tbl += '<th style="padding:9px 12px;text-align:'+align+';border-bottom:2px solid #ddd;white-space:nowrap;">'+h+'</th>';
                        });
                        tbl += '</tr></thead><tbody>';

                        if (d.products && d.products.length) {
                            d.products.forEach(function (p) {
                                tbl += '<tr style="border-bottom:1px solid #f0f0f0;transition:background .15s;" onmouseover="this.style.background=\'#fafafa\'" onmouseout="this.style.background=\'\'">';

                                /* Product cell */
                                tbl += '<td style="padding:12px;">';
                                tbl += '<div style="display:flex;align-items:center;gap:12px;">';
                                if (p.thumb) {
                                    tbl += '<img src="'+p.thumb+'" style="width:52px;height:52px;object-fit:cover;border-radius:5px;border:1px solid #e2e4e7;flex-shrink:0;">';
                                }
                                tbl += '<div>';
                                tbl += '<strong style="font-size:13px;">'+p.name+'</strong>';
                                if (p.variation) tbl += '<div style="color:#888;font-size:11px;margin-top:2px;">'+p.variation+'</div>';
                                if (p.sku) tbl += '<div style="color:#bbb;font-size:11px;">SKU: '+p.sku+'</div>';
                                tbl += '</div></div></td>';

                                tbl += '<td style="padding:12px;text-align:center;font-weight:600;">'+p.quantity+'</td>';
                                tbl += '<td style="padding:12px;text-align:right;color:#555;">'+p.price_html+'</td>';
                                tbl += '<td style="padding:12px;text-align:right;font-weight:700;color:#1d2327;">'+p.line_total_html+'</td>';
                                tbl += '</tr>';
                            });

                            /* Cart total row */
                            tbl += '<tfoot><tr>';
                            tbl += '<td colspan="3" style="padding:12px;text-align:right;font-size:14px;font-weight:700;border-top:2px solid #ddd;">Cart Total</td>';
                            tbl += '<td style="padding:12px;text-align:right;font-size:15px;font-weight:800;color:#2271b1;border-top:2px solid #ddd;">'+d.cart_total_html+'</td>';
                            tbl += '</tr></tfoot>';
                        } else {
                            tbl += '<tr><td colspan="4" style="padding:20px;color:#888;text-align:center;">No product data recorded for this cart.</td></tr>';
                        }
                        tbl += '</table></div>';

                        $('#ac-modal-body').html(info + tbl);
                    },
                    error: function () {
                        $('#ac-modal-body').html('<p style="color:red;padding:20px;">Request failed. Please try again.</p>');
                    }
                });
            });

            /* Close modal on X or backdrop click */
            $('#ac-modal-close').on('click', function(){ $('#ac-view-modal').fadeOut(200); });
            $('#ac-view-modal').on('click', function(e){ if (e.target === this) $(this).fadeOut(200); });
            $(document).on('keydown', function(e){ if (e.key === 'Escape') $('#ac-view-modal').fadeOut(200); });

            /* ======== MARK RECOVERED ======== */
            $(document).on('click', '.ac-mark-recovered-btn', function () {
                var btn    = $(this);
                var cartId = btn.data('id');
                var nonce  = btn.data('nonce');
                var fb     = $('#ac-rf-' + cartId);

                btn.prop('disabled', true).text('Creating order…');
                fb.html('');

                $.ajax({
                    url:  ajaxurl,
                    type: 'POST',
                    data: { action: 'ac_mark_recovered', cart_id: cartId, nonce: nonce },
                    success: function (response) {
                        if (response.success) {
                            var orderId  = response.data.order_id;
                            var orderUrl = response.data.order_url;
                            btn.replaceWith('<span class="ac-badge ac-badge-recovered">&#10003; Recovered</span>');
                            btn.closest('tr').find('.ac-badge-abandoned')
                                .removeClass('ac-badge-abandoned').addClass('ac-badge-recovered').text('Recovered');
                            fb.html('<span style="color:#46b450;">&#10003; Order #' + orderId + ' created! <a href="' + orderUrl + '">View order &rarr;</a></span>');
                            setTimeout(function () { window.location.href = orderUrl; }, 1500);
                        } else {
                            btn.prop('disabled', false).text('Mark Recovered');
                            fb.html('<span style="color:#dc3232;">&#10007; ' + response.data.message + '</span>');
                        }
                    },
                    error: function () {
                        btn.prop('disabled', false).text('Mark Recovered');
                        fb.html('<span style="color:#dc3232;">&#10007; Request failed. Please try again.</span>');
                    }
                });
            });
        });
        </script>
        <?php
        echo '</div>';
    }

    /**
     * AJAX handler: return full cart + product details for the View modal.
     */
    public function ajax_get_cart_details()
    {
        $id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0;
        if (!$id) {
            wp_send_json_error(['message' => 'Invalid cart ID.']);
        }

        if (!check_ajax_referer('ac_view_cart_' . $id, 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed.']);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}abandoned_carts WHERE id = %d LIMIT 1", $id
        ));
        if (!$row) {
            wp_send_json_error(['message' => 'Cart not found.']);
        }

        $cart_items = json_decode($row->cart_data, true);
        $products   = [];
        $cart_total = 0.0;

        if (is_array($cart_items)) {
            foreach ($cart_items as $item) {
                $product_id   = intval($item['product_id']   ?? 0);
                $variation_id = intval($item['variation_id'] ?? 0);
                $quantity     = intval($item['quantity']     ?? 1);

                $product = ($variation_id > 0) ? wc_get_product($variation_id) : wc_get_product($product_id);
                if (!$product) {
                    continue;
                }

                $price      = (float) $product->get_price();
                $line_total = $price * $quantity;
                $cart_total += $line_total;

                /* Build variation label */
                $variation_label = '';
                if ($variation_id && !empty($item['variation']) && is_array($item['variation'])) {
                    $parts = [];
                    foreach ($item['variation'] as $attr => $val) {
                        if ($val !== '') {
                            $label   = wc_attribute_label(str_replace('attribute_', '', $attr));
                            $parts[] = esc_html($label) . ': ' . esc_html($val);
                        }
                    }
                    $variation_label = implode(' | ', $parts);
                }

                /* Thumbnail URL */
                $thumb_id  = $product->get_image_id();
                $thumb_url = $thumb_id
                    ? wp_get_attachment_image_url($thumb_id, 'thumbnail')
                    : wc_placeholder_img_src('thumbnail');

                $products[] = [
                    'name'            => esc_html($product->get_name()),
                    'sku'             => esc_html($product->get_sku()),
                    'thumb'           => esc_url($thumb_url),
                    'variation'       => $variation_label,
                    'quantity'        => $quantity,
                    'price'           => $price,
                    'price_html'      => wc_price($price),
                    'line_total'      => $line_total,
                    'line_total_html' => wc_price($line_total),
                ];
            }
        }

        wp_send_json_success([
            'name'            => esc_html($row->name      ?? ''),
            'email'           => esc_html($row->email     ?? ''),
            'phone'           => esc_html($row->phone     ?? ''),
            'address'         => esc_html($row->address   ?? ''),
            'status'          => esc_html($row->status    ?? ''),
            'last_activity'   => esc_html($row->last_activity ?? ''),
            'coupon_code'     => esc_html($row->coupon_code  ?? ''),
            'products'        => $products,
            'cart_total'      => $cart_total,
            'cart_total_html' => wc_price($cart_total),
        ]);
    }

    public function settings_page()
    {
        // Get default email template
        $default_email_template = $this->get_default_email_template();

        ?>
        <div class="wrap">
            <h1>Abandoned Cart Settings</h1>



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



                    <!-- Notification Channels -->
                    <div class="ac-integration-section">
                        <h3>📢 Notification Channels</h3>
                        <p>Select which channels to use for sending abandoned cart reminders:</p>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Active Channels</th>
                                <td>
                                    <?php
                                    $channels = get_option('ac_notification_channels', array('email', 'sms'));
                                    if (is_string($channels)) {
                                        $channels = explode(',', $channels);
                                    }
                                    if (!is_array($channels)) {
                                        $channels = array();
                                    }
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
                                        <option value="twilio" <?php selected(get_option('ac_sms_gateway'), 'twilio'); ?>>Twilio
                                        </option>
                                        <option value="nexmo" <?php selected(get_option('ac_sms_gateway'), 'nexmo'); ?>>
                                            Nexmo/Vonage</option>
                                        <option value="sslwireless" <?php selected(get_option('ac_sms_gateway'), 'sslwireless'); ?>>SSL Wireless (Bangladesh)</option>
                                        <option value="banglalink" <?php selected(get_option('ac_sms_gateway'), 'banglalink'); ?>>Banglalink (Bangladesh)</option>
                                    </select>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><label for="ac_sms_test_number">Test Phone Number</label></th>
                                <td>
                                    <input type="text" id="ac_sms_test_number" name="ac_sms_test_number"
                                        value="<?php echo esc_attr(get_option('ac_sms_test_number')); ?>" class="regular-text"
                                        placeholder="+8801XXXXXXXXX" />
                                    <button type="button" class="button ac-test-btn" onclick="acTestSMS()">Test
                                        Connection</button>
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
                                    <td><input type="text" id="ac_sms_api_key" name="ac_sms_api_key"
                                            value="<?php echo esc_attr(get_option('ac_sms_api_key')); ?>"
                                            class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_sms_sender_id">Sender ID</label></th>
                                    <td><input type="text" id="ac_sms_sender_id" name="ac_sms_sender_id"
                                            value="<?php echo esc_attr(get_option('ac_sms_sender_id')); ?>"
                                            class="regular-text" /></td>
                                </tr>
                            </table>
                        </div>

                        <!-- Twilio SMS Fields -->
                        <div class="ac-gateway-fields" data-gateway="twilio" style="display:none;">
                            <h4>Twilio SMS Configuration</h4>
                            <table class="form-table">
                                <tr valign="top">
                                    <th scope="row"><label for="ac_twilio_sms_account_sid">Account SID</label></th>
                                    <td><input type="text" id="ac_twilio_sms_account_sid" name="ac_twilio_sms_account_sid"
                                            value="<?php echo esc_attr(get_option('ac_twilio_sms_account_sid')); ?>"
                                            class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_twilio_sms_auth_token">Auth Token</label></th>
                                    <td><input type="password" id="ac_twilio_sms_auth_token" name="ac_twilio_sms_auth_token"
                                            value="<?php echo esc_attr(get_option('ac_twilio_sms_auth_token')); ?>"
                                            class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_twilio_sms_number">From Number</label></th>
                                    <td><input type="text" id="ac_twilio_sms_number" name="ac_twilio_sms_number"
                                            value="<?php echo esc_attr(get_option('ac_twilio_sms_number')); ?>"
                                            class="regular-text" placeholder="+1234567890" /></td>
                                </tr>
                            </table>
                        </div>

                        <!-- Nexmo Fields -->
                        <div class="ac-gateway-fields" data-gateway="nexmo" style="display:none;">
                            <h4>Nexmo/Vonage Configuration</h4>
                            <table class="form-table">
                                <tr valign="top">
                                    <th scope="row"><label for="ac_nexmo_api_key">API Key</label></th>
                                    <td><input type="text" id="ac_nexmo_api_key" name="ac_nexmo_api_key"
                                            value="<?php echo esc_attr(get_option('ac_nexmo_api_key')); ?>"
                                            class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_nexmo_api_secret">API Secret</label></th>
                                    <td><input type="password" id="ac_nexmo_api_secret" name="ac_nexmo_api_secret"
                                            value="<?php echo esc_attr(get_option('ac_nexmo_api_secret')); ?>"
                                            class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_nexmo_from">From Name</label></th>
                                    <td><input type="text" id="ac_nexmo_from" name="ac_nexmo_from"
                                            value="<?php echo esc_attr(get_option('ac_nexmo_from', 'AbandonedCart')); ?>"
                                            class="regular-text" /></td>
                                </tr>
                            </table>
                        </div>

                        <!-- SSL Wireless Fields -->
                        <div class="ac-gateway-fields" data-gateway="sslwireless" style="display:none;">
                            <h4>SSL Wireless Configuration</h4>
                            <table class="form-table">
                                <tr valign="top">
                                    <th scope="row"><label for="ac_sslwireless_api_token">API Token</label></th>
                                    <td><input type="text" id="ac_sslwireless_api_token" name="ac_sslwireless_api_token"
                                            value="<?php echo esc_attr(get_option('ac_sslwireless_api_token')); ?>"
                                            class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_sslwireless_sid">SID</label></th>
                                    <td><input type="text" id="ac_sslwireless_sid" name="ac_sslwireless_sid"
                                            value="<?php echo esc_attr(get_option('ac_sslwireless_sid')); ?>"
                                            class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_sslwireless_csms_id">CSMS ID</label></th>
                                    <td><input type="text" id="ac_sslwireless_csms_id" name="ac_sslwireless_csms_id"
                                            value="<?php echo esc_attr(get_option('ac_sslwireless_csms_id')); ?>"
                                            class="regular-text" /></td>
                                </tr>
                            </table>
                        </div>

                        <!-- Banglalink Fields -->
                        <div class="ac-gateway-fields" data-gateway="banglalink" style="display:none;">
                            <h4>Banglalink Configuration</h4>
                            <table class="form-table">
                                <tr valign="top">
                                    <th scope="row"><label for="ac_banglalink_username">Username</label></th>
                                    <td><input type="text" id="ac_banglalink_username" name="ac_banglalink_username"
                                            value="<?php echo esc_attr(get_option('ac_banglalink_username')); ?>"
                                            class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_banglalink_password">Password</label></th>
                                    <td><input type="password" id="ac_banglalink_password" name="ac_banglalink_password"
                                            value="<?php echo esc_attr(get_option('ac_banglalink_password')); ?>"
                                            class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_banglalink_cli">CLI</label></th>
                                    <td><input type="text" id="ac_banglalink_cli" name="ac_banglalink_cli"
                                            value="<?php echo esc_attr(get_option('ac_banglalink_cli')); ?>"
                                            class="regular-text" /></td>
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
                                    <input type="text" id="ac_whatsapp_test_number" name="ac_whatsapp_test_number"
                                        value="<?php echo esc_attr(get_option('ac_whatsapp_test_number')); ?>"
                                        class="regular-text" placeholder="+8801XXXXXXXXX" />
                                    <button type="button" class="button ac-test-btn" onclick="acTestWhatsApp()">Test
                                        Connection</button>
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
                                    <td><input type="text" id="ac_twilio_account_sid" name="ac_twilio_account_sid"
                                            value="<?php echo esc_attr(get_option('ac_twilio_account_sid')); ?>"
                                            class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_twilio_auth_token">Auth Token</label></th>
                                    <td><input type="password" id="ac_twilio_auth_token" name="ac_twilio_auth_token"
                                            value="<?php echo esc_attr(get_option('ac_twilio_auth_token')); ?>"
                                            class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_twilio_whatsapp_number">WhatsApp Number</label></th>
                                    <td>
                                        <input type="text" id="ac_twilio_whatsapp_number" name="ac_twilio_whatsapp_number"
                                            value="<?php echo esc_attr(get_option('ac_twilio_whatsapp_number')); ?>"
                                            class="regular-text" placeholder="whatsapp:+14155238886" />
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
                                    <td><input type="text" id="ac_ultramsg_instance_id" name="ac_ultramsg_instance_id"
                                            value="<?php echo esc_attr(get_option('ac_ultramsg_instance_id')); ?>"
                                            class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><label for="ac_ultramsg_token">Token</label></th>
                                    <td><input type="text" id="ac_ultramsg_token" name="ac_ultramsg_token"
                                            value="<?php echo esc_attr(get_option('ac_ultramsg_token')); ?>"
                                            class="regular-text" /></td>
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
                                    <input type="text" id="ac_mailchimp_api_key" name="ac_mailchimp_api_key"
                                        value="<?php echo esc_attr(get_option('ac_mailchimp_api_key')); ?>"
                                        class="regular-text" />
                                    <button type="button" class="button ac-test-btn" onclick="acTestMailchimp()">Test
                                        Connection</button>
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
                                    <input type="text" id="ac_brevo_api_key" name="ac_brevo_api_key"
                                        value="<?php echo esc_attr(get_option('ac_brevo_api_key')); ?>" class="regular-text" />
                                    <button type="button" class="button ac-test-btn" onclick="acTestBrevo()">Test
                                        Connection</button>
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
                    <div style="margin-top: 20px;">
                        <?php submit_button('Save Integration Settings', 'primary', 'submit', false); ?>
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
                                    value="<?php echo esc_attr(get_option('ac_reminder1_delay', 30)); ?>" class="small-text"
                                    min="1" />
                                <span>minutes</span>
                                <p class="description">Default: 30 minutes (0.5 hours)</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="ac_reminder2_delay">2nd Reminder Delay</label></th>
                            <td>
                                <input type="number" id="ac_reminder2_delay" name="ac_reminder2_delay"
                                    value="<?php echo esc_attr(get_option('ac_reminder2_delay', 1440)); ?>" class="small-text"
                                    min="1" />
                                <span>minutes</span>
                                <p class="description">Default: 1440 minutes (24 hours)</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="ac_reminder3_delay">3rd Reminder Delay</label></th>
                            <td>
                                <input type="number" id="ac_reminder3_delay" name="ac_reminder3_delay"
                                    value="<?php echo esc_attr(get_option('ac_reminder3_delay', 2880)); ?>" class="small-text"
                                    min="1" />
                                <span>minutes</span>
                                <p class="description">Default: 2880 minutes (48 hours)</p>
                            </td>
                        </tr>
                    </table>
                    <div style="margin-top: 20px;">
                        <?php submit_button('Save Reminder Settings', 'primary', 'submit', false); ?>
                    </div>
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
                    <div style="margin-top: 20px;">
                        <?php submit_button('Save Email Template', 'primary', 'submit', false); ?>
                    </div>
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
                    <div style="margin-top: 20px;">
                        <?php submit_button('Save SMS Template', 'primary', 'submit', false); ?>
                    </div>
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
                    <div style="margin-top: 20px;">
                        <?php submit_button('Save WhatsApp Template', 'primary', 'submit', false); ?>
                    </div>
                </div>

                <!-- Coupon Settings Tab -->
                <div class="ac-tab-content" data-tab="coupon">
                    <h2>Coupon Settings</h2>
                    <p>Configure automatic coupon generation for reminders.</p>

                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><label for="ac_coupon_enabled">Enable Coupons</label></th>
                            <td>
                                <input type="checkbox" id="ac_coupon_enabled" name="ac_coupon_enabled" value="1" <?php checked(get_option('ac_coupon_enabled', '1'), '1'); ?> />
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
                                    value="<?php echo esc_attr(get_option('ac_coupon_amount', 10)); ?>" class="small-text"
                                    min="0" step="0.01" />
                                <p class="description">For percentage: enter 10 for 10%. For fixed: enter amount in your
                                    currency.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="ac_coupon_reminder">Include Coupon In</label></th>
                            <td>
                                <label><input type="checkbox" name="ac_coupon_reminder[]" value="2" <?php $reminders = explode(',', get_option('ac_coupon_reminder', '2,3'));
                                checked(in_array('2', $reminders), true); ?> />
                                    2nd Reminder</label><br>
                                <label><input type="checkbox" name="ac_coupon_reminder[]" value="3" <?php checked(in_array('3', $reminders), true); ?> />
                                    3rd Reminder</label>
                                <p class="description">Select which reminders should include a coupon code.</p>
                            </td>
                        </tr>
                    </table>
                    <div style="margin-top: 20px;">
                        <?php submit_button('Save Coupon Settings', 'primary', 'submit', false); ?>
                    </div>
                </div>
            </form>

            <script>
                jQuery(document).ready(function ($) {
                    // Tab switching
                    $('.ac-tab-btn').on('click', function () {
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
                        beforeSend: function () {
                            jQuery('#ac-sms-test-result').html('<span style="color:#0073aa;">Testing...</span>');
                        },
                        success: function (response) {
                            if (response.success) {
                                jQuery('#ac-sms-test-result').html('<span style="color:#46b450;">✓ ' + response.data.message + '</span>');
                            } else {
                                jQuery('#ac-sms-test-result').html('<span style="color:#dc3232;">✗ ' + response.data.message + '</span>');
                            }
                        },
                        error: function () {
                            jQuery('#ac-sms-test-result').html('<span style="color:#dc3232;">✗ Connection failed</span>');
                        }
                    });
                }

                function acTestWhatsApp() {
                    jQuery.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: { action: 'ac_test_whatsapp_connection' },
                        beforeSend: function () {
                            jQuery('#ac-whatsapp-test-result').html('<span style="color:#0073aa;">Testing...</span>');
                        },
                        success: function (response) {
                            if (response.success) {
                                jQuery('#ac-whatsapp-test-result').html('<span style="color:#46b450;">✓ ' + response.data.message + '</span>');
                            } else {
                                jQuery('#ac-whatsapp-test-result').html('<span style="color:#dc3232;">✗ ' + response.data.message + '</span>');
                            }
                        },
                        error: function () {
                            jQuery('#ac-whatsapp-test-result').html('<span style="color:#dc3232;">✗ Connection failed</span>');
                        }
                    });
                }

                function acTestMailchimp() {
                    jQuery.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: { action: 'ac_test_mailchimp_connection' },
                        beforeSend: function () {
                            jQuery('#ac-mailchimp-test-result').html('<span style="color:#0073aa;">Testing...</span>');
                        },
                        success: function (response) {
                            if (response.success) {
                                jQuery('#ac-mailchimp-test-result').html('<span style="color:#46b450;">✓ ' + response.data.message + '</span>');
                            } else {
                                jQuery('#ac-mailchimp-test-result').html('<span style="color:#dc3232;">✗ ' + response.data.message + '</span>');
                            }
                        },
                        error: function () {
                            jQuery('#ac-mailchimp-test-result').html('<span style="color:#dc3232;">✗ Connection failed</span>');
                        }
                    });
                }

                function acTestBrevo() {
                    jQuery.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: { action: 'ac_test_brevo_connection' },
                        beforeSend: function () {
                            jQuery('#ac-brevo-test-result').html('<span style="color:#0073aa;">Testing...</span>');
                        },
                        success: function (response) {
                            if (response.success) {
                                jQuery('#ac-brevo-test-result').html('<span style="color:#46b450;">✓ ' + response.data.message + '</span>');
                            } else {
                                jQuery('#ac-brevo-test-result').html('<span style="color:#dc3232;">✗ ' + response.data.message + '</span>');
                            }
                        },
                        error: function () {
                            jQuery('#ac-brevo-test-result').html('<span style="color:#dc3232;">✗ Connection failed</span>');
                        }
                    });
                }

                function acLoadMailchimpLists() {
                    jQuery.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: { action: 'ac_get_mailchimp_lists' },
                        beforeSend: function () {
                            jQuery('#ac-mailchimp-test-result').html('<span style="color:#0073aa;">Loading lists...</span>');
                        },
                        success: function (response) {
                            if (response.success) {
                                var select = jQuery('#ac_mailchimp_list_id');
                                select.empty().append('<option value="">Select a list...</option>');
                                jQuery.each(response.data.lists, function (i, list) {
                                    select.append('<option value="' + list.id + '">' + list.name + '</option>');
                                });
                                jQuery('#ac-mailchimp-test-result').html('<span style="color:#46b450;">✓ Lists loaded</span>');
                            } else {
                                jQuery('#ac-mailchimp-test-result').html('<span style="color:#dc3232;">✗ ' + response.data.message + '</span>');
                            }
                        },
                        error: function () {
                            jQuery('#ac-mailchimp-test-result').html('<span style="color:#dc3232;">✗ Failed to load lists</span>');
                        }
                    });
                }

                function acLoadBrevoLists() {
                    jQuery.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: { action: 'ac_get_brevo_lists' },
                        beforeSend: function () {
                            jQuery('#ac-brevo-test-result').html('<span style="color:#0073aa;">Loading lists...</span>');
                        },
                        success: function (response) {
                            if (response.success) {
                                var select = jQuery('#ac_brevo_list_id');
                                select.empty().append('<option value="">Select a list...</option>');
                                jQuery.each(response.data.lists, function (i, list) {
                                    select.append('<option value="' + list.id + '">' + list.name + '</option>');
                                });
                                jQuery('#ac-brevo-test-result').html('<span style="color:#46b450;">✓ Lists loaded</span>');
                            } else {
                                jQuery('#ac-brevo-test-result').html('<span style="color:#dc3232;">✗ ' + response.data.message + '</span>');
                            }
                        },
                        error: function () {
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
    public function ajax_test_sms_connection()
    {
        $result = AC_SMS_Gateway::test_connection();
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    public function ajax_test_whatsapp_connection()
    {
        $result = AC_WhatsApp::test_connection();
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    public function ajax_test_mailchimp_connection()
    {
        $result = AC_Mailchimp::test_connection();
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    public function ajax_test_brevo_connection()
    {
        $result = AC_Brevo::test_connection();
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    public function ajax_get_mailchimp_lists()
    {
        $lists = AC_Mailchimp::get_lists();
        if (!empty($lists)) {
            wp_send_json_success(array('lists' => $lists));
        } else {
            wp_send_json_error(array('message' => 'No lists found or API error'));
        }
    }

    public function ajax_get_brevo_lists()
    {
        $lists = AC_Brevo::get_lists();
        if (!empty($lists)) {
            wp_send_json_success(array('lists' => $lists));
        } else {
            wp_send_json_error(array('message' => 'No lists found or API error'));
        }
    }

    public function ajax_mark_recovered()
    {
        $id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0;
        if (!$id) {
            wp_send_json_error(array('message' => 'Invalid cart ID.'));
        }

        if (!check_ajax_referer('ac_mark_recovered_' . $id, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'abandoned_carts';

        // Load the abandoned cart row
        $cart_row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d LIMIT 1",
            $id
        ));

        if (!$cart_row) {
            wp_send_json_error(array('message' => 'Cart not found.'));
        }

        // Parse saved cart items
        $cart_items = json_decode($cart_row->cart_data, true);
        if (empty($cart_items) || !is_array($cart_items)) {
            wp_send_json_error(array('message' => 'Cart data is empty or invalid.'));
        }

        // ---- Create WooCommerce Order ----
        $order = wc_create_order();
        if (is_wp_error($order)) {
            wp_send_json_error(array('message' => 'Failed to create order: ' . $order->get_error_message()));
        }

        // Add line items from the saved cart data
        foreach ($cart_items as $item) {
            $product_id = isset($item['product_id']) ? intval($item['product_id']) : 0;
            $variation_id = isset($item['variation_id']) ? intval($item['variation_id']) : 0;
            $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;

            if (!$product_id) {
                continue;
            }

            $product = $variation_id ? wc_get_product($variation_id) : wc_get_product($product_id);
            if (!$product) {
                continue;
            }

            $order->add_product($product, $quantity, array(
                'variation' => isset($item['variation']) ? $item['variation'] : array(),
            ));
        }

        // Set billing address from saved data
        $name_parts = explode(' ', $cart_row->name, 2);
        $first_name = $name_parts[0] ?? '';
        $last_name = $name_parts[1] ?? '';

        // Parse the stored address string back into parts (comma-separated)
        $addr_parts = array_map('trim', explode(',', $cart_row->address ?? ''));

        $order->set_billing_first_name($first_name);
        $order->set_billing_last_name($last_name);
        $order->set_billing_email($cart_row->email ?? '');
        $order->set_billing_phone($cart_row->phone ?? '');
        $order->set_billing_address_1($addr_parts[0] ?? '');
        $order->set_billing_address_2($addr_parts[1] ?? '');
        $order->set_billing_city($addr_parts[2] ?? '');
        $order->set_billing_state($addr_parts[3] ?? '');
        $order->set_billing_postcode($addr_parts[4] ?? '');
        $order->set_billing_country($addr_parts[5] ?? '');

        // Copy billing to shipping as well
        $order->set_shipping_first_name($first_name);
        $order->set_shipping_last_name($last_name);
        $order->set_shipping_address_1($addr_parts[0] ?? '');
        $order->set_shipping_address_2($addr_parts[1] ?? '');
        $order->set_shipping_city($addr_parts[2] ?? '');
        $order->set_shipping_state($addr_parts[3] ?? '');
        $order->set_shipping_postcode($addr_parts[4] ?? '');
        $order->set_shipping_country($addr_parts[5] ?? '');

        // Link to WP user if available
        if (!empty($cart_row->user_id)) {
            $order->set_customer_id(intval($cart_row->user_id));
        }

        // Note on the order
        $order->add_order_note(
            __('Order created manually via Abandoned Cart Recovery.', 'my-abandoned-cart'),
            0,
            false
        );

        // Apply coupon if one was generated for this cart
        if (!empty($cart_row->coupon_code)) {
            $order->apply_coupon(strtolower($cart_row->coupon_code));
        }

        $order->calculate_totals();
        $order->set_status('pending');
        $order->save();

        $order_id = $order->get_id();
        $order_url = admin_url('post.php?post=' . $order_id . '&action=edit');

        // Mark the abandoned cart as recovered
        $wpdb->update(
            $table_name,
            array(
                'status' => 'recovered',
                'recovered_at' => current_time('mysql'),
                'order_id' => $order_id,
                'recovered_amount' => $order->get_total(),
            ),
            array('id' => $id),
            array('%s', '%s', '%d', '%f'),
            array('%d')
        );

        wp_send_json_success(array(
            'message' => 'Order #' . $order_id . ' created successfully.',
            'order_id' => $order_id,
            'order_url' => $order_url,
        ));
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
            'address' => 'Address',
            'last_activity' => 'Last Activity',
            'status' => 'Status',
            'reminders_sent' => 'Reminders Sent',
            'coupon_code' => 'Coupon',
            // 'restore_link' => 'Restore Link',
            'actions' => 'Actions',
            'order_recovered' => 'Order Recovered',
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
            case 'address':
                return esc_html(!empty($item->address) ? $item->address : '—');
            case 'status':
                $status = isset($item->status) ? $item->status : 'abandoned';
                if ($status === 'recovered') {
                    return '<span class="ac-badge ac-badge-recovered">Recovered</span>';
                }
                return '<span class="ac-badge ac-badge-abandoned">Abandoned</span>';
            case 'reminders_sent':
                return 'R1:' . ($item->reminder1_sent ? 'Yes' : 'No') . ', ' .
                    'R2:' . ($item->reminder2_sent ? 'Yes' : 'No') . ', ' .
                    'R3:' . ($item->reminder3_sent ? 'Yes' : 'No');
            // case 'restore_link':
            //     $link = add_query_arg('ac_restore', $item->restore_key, site_url('/'));
            //     return '<a href="' . esc_url($link) . '" target="_blank">Restore</a>';
            case 'actions':
                $view_nonce = wp_create_nonce('ac_view_cart_' . $item->id);
                return sprintf(
                    '<button type="button" class="button ac-view-cart-btn" '
                    . 'data-id="%d" data-nonce="%s" '
                    . 'title="View Cart Details" style="padding:2px 8px;font-size:11px;">'
                    . '&#128065; View</button>',
                    intval($item->id),
                    esc_attr($view_nonce)
                );
            case 'order_recovered':
                $status = isset($item->status) ? $item->status : 'abandoned';
                if ($status === 'recovered') {
                    return '<span class="ac-badge ac-badge-recovered">&#10003; Recovered</span>';
                }
                $nonce = wp_create_nonce('ac_mark_recovered_' . $item->id);
                return sprintf(
                    '<button type="button" class="button button-primary ac-mark-recovered-btn" '
                    . 'data-id="%d" data-nonce="%s" style="font-size:11px;padding:2px 8px;">'
                    . 'Mark Recovered</button>'
                    . '<span class="ac-recovered-feedback" id="ac-rf-%d" style="margin-left:6px;"></span>',
                    intval($item->id),
                    esc_attr($nonce),
                    intval($item->id)
                );
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
