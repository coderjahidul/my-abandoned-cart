<?php
if (!defined('ABSPATH')) exit;

/**
 * WhatsApp Integration Class
 * Supports multiple WhatsApp providers: Twilio, UltraMsg
 */
class AC_WhatsApp {

    public function __construct() {
        // Constructor for future hooks if needed
    }

    /**
     * Send WhatsApp message based on configured provider
     * 
     * @param string $phone Phone number
     * @param string $message Message content
     * @return array Response with success status and message
     */
    public static function send($phone, $message) {
        $enabled = get_option('ac_whatsapp_enabled', '0');
        
        if ($enabled !== '1') {
            return array('success' => false, 'message' => 'WhatsApp is disabled');
        }

        $provider = get_option('ac_whatsapp_provider', 'twilio');
        
        switch ($provider) {
            case 'twilio':
                return self::send_via_twilio($phone, $message);
            case 'ultramsg':
                return self::send_via_ultramsg($phone, $message);
            default:
                return array('success' => false, 'message' => 'Invalid WhatsApp provider');
        }
    }

    /**
     * Send WhatsApp message via Twilio
     */
    private static function send_via_twilio($phone, $message) {
        $account_sid = get_option('ac_twilio_account_sid');
        $auth_token = get_option('ac_twilio_auth_token');
        $from_number = get_option('ac_twilio_whatsapp_number'); // e.g., whatsapp:+14155238886

        if (empty($account_sid) || empty($auth_token) || empty($from_number)) {
            return array('success' => false, 'message' => 'Twilio credentials not configured');
        }

        // Format phone number for WhatsApp
        $to_number = 'whatsapp:' . preg_replace('/[^0-9+]/', '', $phone);

        $url = "https://api.twilio.com/2010-04-01/Accounts/$account_sid/Messages.json";
        
        $data = array(
            'From' => $from_number,
            'To' => $to_number,
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
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return array('success' => false, 'message' => 'Curl error: ' . $error);
        }
        
        curl_close($ch);

        if ($http_code == 201) {
            return array('success' => true, 'message' => 'WhatsApp sent via Twilio');
        } else {
            return array('success' => false, 'message' => 'Twilio error: ' . $response);
        }
    }

    /**
     * Send WhatsApp message via UltraMsg
     */
    private static function send_via_ultramsg($phone, $message) {
        $instance_id = get_option('ac_ultramsg_instance_id');
        $token = get_option('ac_ultramsg_token');

        if (empty($instance_id) || empty($token)) {
            return array('success' => false, 'message' => 'UltraMsg credentials not configured');
        }

        // Format phone number (remove + and spaces)
        $phone = preg_replace('/[^0-9]/', '', $phone);

        $url = "https://api.ultramsg.com/$instance_id/messages/chat";
        
        $data = array(
            'token' => $token,
            'to' => $phone,
            'body' => $message
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return array('success' => false, 'message' => 'Curl error: ' . $error);
        }
        
        curl_close($ch);

        $result = json_decode($response, true);
        
        if (isset($result['sent']) && $result['sent'] === true) {
            return array('success' => true, 'message' => 'WhatsApp sent via UltraMsg');
        } else {
            return array('success' => false, 'message' => 'UltraMsg error: ' . $response);
        }
    }

    /**
     * Test WhatsApp connection
     */
    public static function test_connection() {
        $provider = get_option('ac_whatsapp_provider', 'twilio');
        
        // Use a test message
        $test_message = "Test message from Abandoned Cart Plugin";
        $test_phone = get_option('ac_whatsapp_test_number');
        
        if (empty($test_phone)) {
            return array('success' => false, 'message' => 'Please configure a test phone number');
        }
        
        return self::send($test_phone, $test_message);
    }
}
