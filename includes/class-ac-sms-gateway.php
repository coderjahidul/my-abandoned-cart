<?php
if (!defined('ABSPATH')) exit;

/**
 * SMS Gateway Manager
 * Supports multiple SMS providers with abstraction layer
 */
class AC_SMS_Gateway {

    /**
     * Send SMS via configured gateway
     */
    public static function send($phone, $message) {
        $gateway = get_option('ac_sms_gateway', 'bulksmsbd');
        
        switch ($gateway) {
            case 'bulksmsbd':
                return self::send_via_bulksmsbd($phone, $message);
            case 'twilio':
                return self::send_via_twilio($phone, $message);
            case 'nexmo':
                return self::send_via_nexmo($phone, $message);
            case 'sslwireless':
                return self::send_via_sslwireless($phone, $message);
            case 'banglalink':
                return self::send_via_banglalink($phone, $message);
            default:
                return array('success' => false, 'message' => 'Invalid SMS gateway');
        }
    }

    /**
     * BulkSMSBD (existing provider)
     */
    private static function send_via_bulksmsbd($phone, $message) {
        $api_key = get_option('ac_sms_api_key');
        $sender_id = get_option('ac_sms_sender_id');

        if (empty($api_key) || empty($sender_id)) {
            return array('success' => false, 'message' => 'BulkSMSBD credentials not configured');
        }

        $url = "http://bulksmsbd.net/api/smsapi";
        $data = array(
            'api_key' => $api_key,
            'senderid' => $sender_id,
            'number' => preg_replace('/[^0-9]/', '', $phone),
            'message' => $message
        );

        return self::send_request($url, $data, 'BulkSMSBD');
    }

    /**
     * Twilio SMS
     */
    private static function send_via_twilio($phone, $message) {
        $account_sid = get_option('ac_twilio_sms_account_sid');
        $auth_token = get_option('ac_twilio_sms_auth_token');
        $from_number = get_option('ac_twilio_sms_number');

        if (empty($account_sid) || empty($auth_token) || empty($from_number)) {
            return array('success' => false, 'message' => 'Twilio SMS credentials not configured');
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/$account_sid/Messages.json";
        $data = array(
            'From' => $from_number,
            'To' => preg_replace('/[^0-9+]/', '', $phone),
            'Body' => $message
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$account_sid:$auth_token");
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 201) {
            return array('success' => true, 'message' => 'SMS sent via Twilio');
        } else {
            return array('success' => false, 'message' => 'Twilio error: ' . $response);
        }
    }

    /**
     * Nexmo (Vonage) SMS
     */
    private static function send_via_nexmo($phone, $message) {
        $api_key = get_option('ac_nexmo_api_key');
        $api_secret = get_option('ac_nexmo_api_secret');
        $from = get_option('ac_nexmo_from', 'AbandonedCart');

        if (empty($api_key) || empty($api_secret)) {
            return array('success' => false, 'message' => 'Nexmo credentials not configured');
        }

        $url = "https://rest.nexmo.com/sms/json";
        $data = array(
            'api_key' => $api_key,
            'api_secret' => $api_secret,
            'from' => $from,
            'to' => preg_replace('/[^0-9]/', '', $phone),
            'text' => $message
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        
        if (isset($result['messages'][0]['status']) && $result['messages'][0]['status'] == '0') {
            return array('success' => true, 'message' => 'SMS sent via Nexmo');
        } else {
            return array('success' => false, 'message' => 'Nexmo error: ' . $response);
        }
    }

    /**
     * SSL Wireless (Bangladesh)
     */
    private static function send_via_sslwireless($phone, $message) {
        $api_token = get_option('ac_sslwireless_api_token');
        $sid = get_option('ac_sslwireless_sid');
        $csms_id = get_option('ac_sslwireless_csms_id');

        if (empty($api_token) || empty($sid)) {
            return array('success' => false, 'message' => 'SSL Wireless credentials not configured');
        }

        $url = "https://smsplus.sslwireless.com/api/v3/send-sms";
        $data = array(
            'api_token' => $api_token,
            'sid' => $sid,
            'csms_id' => $csms_id,
            'msisdn' => preg_replace('/[^0-9]/', '', $phone),
            'sms' => $message
        );

        return self::send_request($url, $data, 'SSL Wireless');
    }

    /**
     * Banglalink SMS (Bangladesh)
     */
    private static function send_via_banglalink($phone, $message) {
        $username = get_option('ac_banglalink_username');
        $password = get_option('ac_banglalink_password');
        $cli = get_option('ac_banglalink_cli');

        if (empty($username) || empty($password)) {
            return array('success' => false, 'message' => 'Banglalink credentials not configured');
        }

        $url = "http://sms.banglalink.net/sendSMS";
        $data = array(
            'userID' => $username,
            'passwd' => $password,
            'message' => $message,
            'msisdn' => preg_replace('/[^0-9]/', '', $phone),
            'cli' => $cli
        );

        return self::send_request($url, $data, 'Banglalink');
    }

    /**
     * Generic HTTP request helper
     */
    private static function send_request($url, $data, $provider_name) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return array('success' => false, 'message' => "Curl error: $error");
        }
        
        curl_close($ch);
        return array('success' => true, 'message' => "SMS sent via $provider_name", 'response' => $response);
    }

    /**
     * Test SMS connection
     */
    public static function test_connection() {
        $test_phone = get_option('ac_sms_test_number');
        
        if (empty($test_phone)) {
            return array('success' => false, 'message' => 'Please configure a test phone number');
        }
        
        $test_message = "Test SMS from Abandoned Cart Plugin";
        return self::send($test_phone, $test_message);
    }
}
