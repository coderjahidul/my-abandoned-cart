<?php
if (!defined('ABSPATH')) exit;

/**
 * Mailchimp Integration Class
 * Syncs abandoned cart data to Mailchimp audience
 */
class AC_Mailchimp {

    public function __construct() {
        // Constructor for future hooks
    }

    /**
     * Sync contact to Mailchimp
     */
    public static function sync_contact($email, $data = array()) {
        $enabled = get_option('ac_mailchimp_enabled', '0');
        
        if ($enabled !== '1') {
            return array('success' => false, 'message' => 'Mailchimp sync is disabled');
        }

        $api_key = get_option('ac_mailchimp_api_key');
        $list_id = get_option('ac_mailchimp_list_id');

        if (empty($api_key) || empty($list_id)) {
            return array('success' => false, 'message' => 'Mailchimp credentials not configured');
        }

        // Extract datacenter from API key
        $datacenter = substr($api_key, strpos($api_key, '-') + 1);
        $url = "https://$datacenter.api.mailchimp.com/3.0/lists/$list_id/members/" . md5(strtolower($email));

        // Prepare member data
        $member_data = array(
            'email_address' => $email,
            'status_if_new' => 'subscribed',
            'merge_fields' => array(
                'FNAME' => isset($data['name']) ? $data['name'] : '',
                'PHONE' => isset($data['phone']) ? $data['phone'] : ''
            ),
            'tags' => array('abandoned_cart')
        );

        // Add custom merge fields
        if (isset($data['cart_value'])) {
            $member_data['merge_fields']['CARTVALUE'] = $data['cart_value'];
        }
        if (isset($data['last_activity'])) {
            $member_data['merge_fields']['LASTACTIV'] = $data['last_activity'];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "anystring:$api_key");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($member_data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return array('success' => false, 'message' => 'Curl error: ' . $error);
        }
        
        curl_close($ch);

        if ($http_code == 200) {
            return array('success' => true, 'message' => 'Contact synced to Mailchimp');
        } else {
            return array('success' => false, 'message' => 'Mailchimp error: ' . $response);
        }
    }

    /**
     * Test Mailchimp connection
     */
    public static function test_connection() {
        $api_key = get_option('ac_mailchimp_api_key');

        if (empty($api_key)) {
            return array('success' => false, 'message' => 'Mailchimp API key not configured');
        }

        $datacenter = substr($api_key, strpos($api_key, '-') + 1);
        $url = "https://$datacenter.api.mailchimp.com/3.0/ping";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "anystring:$api_key");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200) {
            return array('success' => true, 'message' => 'Mailchimp connection successful');
        } else {
            return array('success' => false, 'message' => 'Mailchimp connection failed: ' . $response);
        }
    }

    /**
     * Get Mailchimp lists/audiences
     */
    public static function get_lists() {
        $api_key = get_option('ac_mailchimp_api_key');

        if (empty($api_key)) {
            return array();
        }

        $datacenter = substr($api_key, strpos($api_key, '-') + 1);
        $url = "https://$datacenter.api.mailchimp.com/3.0/lists?count=100";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "anystring:$api_key");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        
        if (isset($result['lists'])) {
            return $result['lists'];
        }

        return array();
    }
}
