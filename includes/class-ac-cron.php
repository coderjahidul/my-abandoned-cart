<?php
if (!defined('ABSPATH')) exit;

class AC_Cron {

    public function __construct() {
        // Hook to custom cron event
        add_action('ac_cron_event', array($this, 'send_reminders'));
    }

    public function send_reminders() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'abandoned_carts';

        $carts = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'abandoned'");

        foreach ($carts as $cart) {
            $name = $cart->name ?: 'গ্রাহক';
            $restore_link = add_query_arg('ac_restore', $cart->restore_key, site_url('/'));
            $cart_items = json_decode($cart->cart_data, true);

            // Get timing settings (in minutes)
            $reminder1_delay = get_option('ac_reminder1_delay', 30) * 60; // Convert to seconds
            $reminder2_delay = get_option('ac_reminder2_delay', 1440) * 60;
            $reminder3_delay = get_option('ac_reminder3_delay', 2880) * 60;

            // Determine which reminder to send
            $reminder_number = 0;
            $last_activity_time = strtotime($cart->last_activity);
            $current_time = current_time('timestamp');

            if (!$cart->reminder1_sent && ($current_time - $last_activity_time) >= $reminder1_delay) {
                $reminder_number = 1;
            } elseif (!$cart->reminder2_sent && ($current_time - $last_activity_time) >= $reminder2_delay) {
                $reminder_number = 2;
            } elseif (!$cart->reminder3_sent && ($current_time - $last_activity_time) >= $reminder3_delay) {
                $reminder_number = 3;
            }

            if ($reminder_number === 0) continue;

            // Check if coupon should be generated
            $coupon_code = '';
            $coupon_enabled = get_option('ac_coupon_enabled', '1');
            $coupon_reminders = explode(',', get_option('ac_coupon_reminder', '2,3'));
            
            if ($coupon_enabled === '1' && in_array((string)$reminder_number, $coupon_reminders)) {
                $coupon_type = get_option('ac_coupon_type', 'percent');
                $coupon_amount = get_option('ac_coupon_amount', 10);
                $coupon_code = AC_Coupon::generate_coupon($coupon_amount, $coupon_type);
            }

            // Prepare placeholders
            $placeholders = array(
                '{customer_name}' => $name,
                '{restore_link}' => $restore_link,
                '{coupon_code}' => $coupon_code,
                '{site_name}' => get_bloginfo('name')
            );

            // Get active notification channels
            $channels = explode(',', get_option('ac_notification_channels', 'email,sms'));
            
            // Email if email exists and email channel is active
            if (!empty($cart->email) && in_array('email', $channels)) {
                $headers = array('Content-Type: text/html; charset=UTF-8');

                // Get email template from settings
                $email_subject = get_option('ac_email_subject', 'আপনি আপনার কার্ট শেষ করেননি!');
                $email_template = get_option('ac_email_template', $this->get_default_email_template());
                
                // Replace placeholders
                $subject = str_replace(array_keys($placeholders), array_values($placeholders), $email_subject);
                $body = str_replace(array_keys($placeholders), array_values($placeholders), $email_template);
                
                // Wrap in HTML structure
                $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $body . '</body></html>';

                wp_mail($cart->email, $subject, $body, $headers);
            }

            // SMS if phone exists and SMS channel is active
            if (!empty($cart->phone) && in_array('sms', $channels)) {
                $phone = preg_replace('/[^0-9+]/', '', $cart->phone);
                
                // Get SMS template from settings
                $sms_template = get_option('ac_sms_template', 'হ্যালো {customer_name}, আপনার কার্টে পণ্য রয়েছে। শেষ করতে দেখুন: {restore_link}');
                
                // Replace placeholders
                $message = str_replace(array_keys($placeholders), array_values($placeholders), $sms_template);
                
                // Use new SMS Gateway class
                AC_SMS_Gateway::send($phone, $message);
            }
            
            // WhatsApp if phone exists and WhatsApp channel is active
            if (!empty($cart->phone) && in_array('whatsapp', $channels)) {
                $phone = preg_replace('/[^0-9+]/', '', $cart->phone);
                
                // Get WhatsApp template from settings
                $whatsapp_template = get_option('ac_whatsapp_template', 'হ্যালো {customer_name}, আপনার কার্টে পণ্য রয়েছে। শেষ করতে দেখুন: {restore_link}');
                
                // Replace placeholders
                $message = str_replace(array_keys($placeholders), array_values($placeholders), $whatsapp_template);
                
                // Use WhatsApp class
                AC_WhatsApp::send($phone, $message);
            }

            // Mark reminder as sent
            $reminder_field = 'reminder' . $reminder_number . '_sent';
            $wpdb->update($table_name, array($reminder_field => 1, 'coupon_code' => $coupon_code), array('id' => $cart->id), array('%d','%s'), array('%d'));
        }
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

    public static function send_message($phone, $message) {
        $url = "http://bulksmsbd.net/api/smsapi";
        $api_key = get_option('ac_sms_api_key');
        $senderid = get_option('ac_sms_sender_id');

        $data = [
            "api_key" => $api_key,
            "senderid" => $senderid,
            "number" => $phone,
            "message" => $message
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            return "Curl error: " . $error_msg;
        }

        curl_close($ch);
        return $response;
    }
}
