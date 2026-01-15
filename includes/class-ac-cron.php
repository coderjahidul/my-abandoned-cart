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

        $carts = $wpdb->get_results("SELECT * FROM $table_name");

        foreach ($carts as $cart) {
            $name = $cart->name ?: 'গ্রাহক';
            $restore_link = add_query_arg('ac_restore', $cart->restore_key, site_url('/'));
            $cart_items = json_decode($cart->cart_data, true);

            // Determine which reminder to send
            $reminder_number = 0;
            $last_activity_time = strtotime($cart->last_activity);
            $current_time = current_time('timestamp');

            if (!$cart->reminder1_sent && ($current_time - $last_activity_time) >= 1800) {
                $reminder_number = 1;
            } elseif (!$cart->reminder2_sent && ($current_time - $last_activity_time) >= 86400) {
                $reminder_number = 2;
            } elseif (!$cart->reminder3_sent && ($current_time - $last_activity_time) >= 172800) {
                $reminder_number = 3;
            }

            if ($reminder_number === 0) continue;

            // Optional coupon
            $coupon_code = '';
            if ($reminder_number >= 2) {
                $coupon_code = AC_Coupon::generate_coupon(10); // 10% discount
            }

            // Email if email exists
            if (!empty($cart->email)) {
                $headers = array('Content-Type: text/html; charset=UTF-8');

                ob_start();
                $cart_items_var = $cart_items;
                $restore_link_var = $restore_link;
                $name_var = $name;
                $coupon_code_var = $coupon_code;
                include plugin_dir_path(__FILE__) . '../templates/email-template.php';
                $body = ob_get_clean();

                $subject = "আপনি আপনার কার্ট শেষ করেননি!";
                wp_mail($cart->email, $subject, $body, $headers);
            }

            // WhatsApp if phone exists
            if (!empty($cart->phone)) {
                $phone = preg_replace('/[^0-9]/', '', $cart->phone);
                $message = "হ্যালো $name, আপনার কার্টে পণ্য রয়েছে। শেষ করতে দেখুন: $restore_link";
                if ($coupon_code) {
                    $message .= " কুপন: $coupon_code";
                }
                self::send_message($phone, $message);
            }

            // Mark reminder as sent
            $reminder_field = 'reminder' . $reminder_number . '_sent';
            $wpdb->update($table_name, array($reminder_field => 1, 'coupon_code' => $coupon_code), array('id' => $cart->id), array('%d','%s'), array('%d'));
        }
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
