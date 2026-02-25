<?php
/*
Plugin Name: My Abandoned Cart
Plugin URI: https://github.com/coderjahidul/my-abandoned-cart
Description: Complete Production-Ready WooCommerce Abandoned Cart Plugin with Guest Restore & Multiple Reminder Emails.
Version: 2.0
Author: MD Jahidul Islam Sabuz
Author URI: https://github.com/coderjahidul
*/

if (!defined('ABSPATH'))
    exit;

require_once plugin_dir_path(__FILE__) . 'includes/class-ac-tracker.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ac-cron.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ac-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ac-coupon.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ac-whatsapp.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ac-sms-gateway.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ac-mailchimp.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ac-brevo.php';

register_activation_hook(__FILE__, 'ac_plugin_activate');
function ac_plugin_activate()
{
    AC_Tracker::create_table();
    if (!wp_next_scheduled('ac_cron_event')) {
        wp_schedule_event(time(), 'hourly', 'ac_cron_event');
    }
}

register_deactivation_hook(__FILE__, 'ac_plugin_deactivate');
function ac_plugin_deactivate()
{
    wp_clear_scheduled_hook('ac_cron_event');
}

// Enqueue frontend JS
function ac_enqueue_scripts()
{
    wp_enqueue_script(
        'ac-script',
        plugin_dir_url(__FILE__) . 'assets/js/ac-script.js',
        array('jquery'),
        '1.0',
        true
    );

    wp_localize_script('ac-script', 'ac_ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ac_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'ac_enqueue_scripts');

add_action('wp_ajax_ac_capture_guest', 'ac_capture_guest');
add_action('wp_ajax_nopriv_ac_capture_guest', 'ac_capture_guest');

function ac_capture_guest()
{
    check_ajax_referer('ac_nonce', 'security');

    global $wpdb;
    $table_name = $wpdb->prefix . 'abandoned_carts';

    $email = sanitize_email($_POST['email'] ?? '');
    $name = sanitize_text_field($_POST['name'] ?? '');
    $phone = sanitize_text_field($_POST['phone'] ?? '');
    $session_id = WC()->session->get_customer_id();

    // Build address from the posted billing fields (only if fields were sent)
    $address_parts = array_filter([
        sanitize_text_field($_POST['billing_address_1'] ?? ''),
        sanitize_text_field($_POST['billing_address_2'] ?? ''),
        sanitize_text_field($_POST['billing_city'] ?? ''),
        sanitize_text_field($_POST['billing_state'] ?? ''),
        sanitize_text_field($_POST['billing_postcode'] ?? ''),
        sanitize_text_field($_POST['billing_country'] ?? ''),
    ]);
    $address = implode(', ', $address_parts);

    // Look for ANY existing row for this session/email/phone —
    // regardless of status — so we ALWAYS update, never duplicate-insert.
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id, address FROM $table_name
         WHERE (session_id = %s OR (email = %s AND email != '') OR (phone = %s AND phone != ''))
         ORDER BY id DESC LIMIT 1",
        $session_id,
        $email,
        $phone
    ));

    $data = [
        'user_id' => is_user_logged_in() ? get_current_user_id() : 0,
        'session_id' => $session_id,
        'email' => $email,
        'name' => $name,
        'phone' => $phone,
        // Keep the previously captured address if the new one is empty
        // (prevents a partial-field blur from blanking a complete address)
        'address' => !empty($address) ? $address : ($existing->address ?? ''),
        'cart_data' => json_encode(WC()->cart->get_cart()),
        'last_activity' => current_time('mysql'),
        'status' => 'abandoned',
    ];

    if ($existing) {
        $wpdb->update($table_name, $data, ['id' => $existing->id]);
        wp_send_json_success('Guest data updated');
    } else {
        $data['restore_key'] = wp_generate_uuid4();
        $wpdb->insert($table_name, $data);
        wp_send_json_success('Guest data stored');
    }
}

new AC_Tracker();
new AC_Cron();
new AC_Admin();
new AC_Coupon();
