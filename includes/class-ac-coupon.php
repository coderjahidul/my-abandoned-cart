<?php
if (!defined('ABSPATH')) exit;

class AC_Coupon {

    public function __construct() {
        // Constructor, যদি প্রয়োজন future hooks যুক্ত করতে পারেন
    }

    /**
     * Generate WooCommerce coupon
     * 
     * @param int $discount_percentage Discount percentage (default 10)
     * @return string $code Coupon code
     */
    public static function generate_coupon($discount = 10) {
        $code = 'AC-' . strtoupper(wp_generate_password(6, false)); // Example: AC-AB12CD

        // Create coupon post
        $coupon = array(
            'post_title'   => $code,
            'post_content' => '',
            'post_status'  => 'publish',
            'post_author'  => 1,
            'post_type'    => 'shop_coupon'
        );

        $new_coupon_id = wp_insert_post($coupon);

        // Set coupon meta
        update_post_meta($new_coupon_id, 'discount_type', 'percent'); // Percentage discount
        update_post_meta($new_coupon_id, 'coupon_amount', $discount);
        update_post_meta($new_coupon_id, 'individual_use', 'no');
        update_post_meta($new_coupon_id, 'usage_limit', 1);
        update_post_meta($new_coupon_id, 'usage_limit_per_user', 1);
        update_post_meta($new_coupon_id, 'exclude_sale_items', 'yes');

        return $code;
    }
}
