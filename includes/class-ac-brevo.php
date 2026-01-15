<?php
if (!defined('ABSPATH')) exit;

/**
 * Brevo (Sendinblue) Integration Class
 * Syncs abandoned cart data to Brevo contacts
 */
class AC_Brevo {

    public function __construct() {
        // Constructor for future hooks
    }

    /**
     * Sync contact to Brevo
     */
    public static function sync_contact($email, $data = array()) {
        $enabled = get_option('ac_brevo_enabled', '0');
        
        if ($enabled !== '1') {
            return array('success' => false, 'message' => 'Brevo sync is disabled');
        }

        $api_key = get_option('ac_brevo_api_key');
        $list_id = get_option('ac_brevo_list_id');

        if (empty($api_key)) {
            return array('success' => false, 'message' => 'Brevo API key not configured');
        }

        $url = "https://api.brevo.com/v3/contacts";

        // Prepare contact data
        $contact_data = array(
            'email' => $email,
            'attributes' => array(
                'FIRSTNAME' => isset($data['name']) ? $data['name'] : '',
                'SMS' => isset($data['phone']) ? $data['phone'] : ''
            ),
            'updateEnabled' => true
        );

        // Add custom attributes
        if (isset($data['cart_value'])) {
            $contact_data['attributes']['CART_VALUE'] = $data['cart_value'];
        }
        if (isset($data['last_activity'])) {
            $contact_data['attributes']['LAST_ACTIVITY'] = $data['last_activity'];
        }

        // Add to list if specified
        if (!empty($list_id)) {
            $contact_data['listIds'] = array((int)$list_id);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'api-key: ' . $api_key,
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($contact_data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return array('success' => false, 'message' => 'Curl error: ' . $error);
        }
        
        curl_close($ch);

        if ($http_code == 201 || $http_code == 204) {
            return array('success' => true, 'message' => 'Contact synced to Brevo');
        } else {
            return array('success' => false, 'message' => 'Brevo error: ' . $response);
        }
    }

    /**
     * Test Brevo connection
     */
    public static function test_connection() {
        $api_key = get_option('ac_brevo_api_key');

        if (empty($api_key)) {
            return array('success' => false, 'message' => 'Brevo API key not configured');
        }

        $url = "https://api.brevo.com/v3/account";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('api-key: ' . $api_key));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200) {
            return array('success' => true, 'message' => 'Brevo connection successful');
        } else {
            return array('success' => false, 'message' => 'Brevo connection failed: ' . $response);
        }
    }

    /**
     * Get Brevo lists
     */
    public static function get_lists() {
        $api_key = get_option('ac_brevo_api_key');

        if (empty($api_key)) {
            return array();
        }

        $url = "https://api.brevo.com/v3/contacts/lists?limit=50";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('api-key: ' . $api_key));
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
