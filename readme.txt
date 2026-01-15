=== My Abandoned Cart ===
Contributors: coderjahidul
Tags: woocommerce, abandoned cart, recovery, guest restore, sms reminder, email reminder
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 2.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Complete Production-Ready WooCommerce Abandoned Cart Plugin with Guest Restore & Multiple Reminder Emails.

== Description ==

My Abandoned Cart helps you recover lost revenue by tracking incomplete orders and sending automated reminders to customers. It works for both registered users and guests.

= Features =
*   Automated Cart Tracking for Guests and Registered Users.
*   Capture Guest Phone (Required) and Email (Optional) via AJAX.
*   Intelligent Identification: Prevents data replacement using Phone and Session ID.
*   Fixed: Order recovery now accurately identifies specific guest records.
*   3-Step Reminder System (30m, 24h, 48h).
*   Automatic 10% Coupon Generation for 2nd and 3rd reminders.
*   Email Notifications with Cart Summary.
*   SMS Notifications via BulkSMSBD API.
*   One-Click Cart Restoration Links.
*   Customizable Reminder Timing (adjust intervals for each reminder).
*   WYSIWYG Email Template Editor with placeholder support.
*   SMS Template Customization.
*   Configurable Coupon Settings (type, amount, which reminders).
*   Clean Admin Dashboard to Manage Carts.

== Installation ==

1. Upload the `my-abandoned-cart` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure your BulkSMSBD credentials under 'In complete order' > 'Settings'.

== Screenshots ==

1. The Abandoned Carts Dashboard.
2. Plugin Settings for BulkSMSBD.

== Changelog ==

= 2.2 =
*   Added Customization Settings: Users can now configure reminder timing, email/SMS templates, and coupon settings through the admin interface.
*   WYSIWYG Email Template Editor with placeholder support ({customer_name}, {restore_link}, {coupon_code}, {site_name}).
*   Configurable coupon type (percentage or fixed cart discount) and amount.
*   Select which reminders (2nd, 3rd, or both) should include coupons.

= 2.1 =
*   Refined Guest Identification: Phone is now a required identifier, Email is optional.
*   Fixed Data Replacement issue: Empty emails no longer cause records to be overwritten.
*   Optimized Recovery Logic: Fixed issue where multiple records were marked as "Recovered" by a single order.

= 2.0 =
*   Added BulkSMSBD integration.
*   Improved guest capture AJAX.
*   Added automatic coupon generation.
*   Enhanced email templates.

= 1.0 =
*   Initial release.
