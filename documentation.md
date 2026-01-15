# My Abandoned Cart Documentation

## Introduction

**My Abandoned Cart** is a comprehensive solution for WooCommerce to recover lost sales. It tracks whenever a user adds items to their cart but fails to complete the purchase. The plugin captures guest data, sends automated notifications, and provides a seamless restoration process.

## How It Works

### 1. Cart Tracking & Identification
The plugin monitors the WooCommerce cart session using a multi-layered identification strategy:
- **Session ID**: Primary tracking for the active browsing session.
- **Phone (Required)**: Used as the main persistent identifier for guest users.
- **Email (Optional)**: Used for identification if provided.

The system intelligently merges or updates records if a user returns in a new session but provides the same phone number or email, ensuring a single, accurate profile for each customer.

### 2. Data Accuracy
To prevent data duplication and "replaced" data issues, the plugin:
- Ensures empty email addresses never match across different guest sessions.
- Uses strict specific-ID matching when marking carts as recovered, ensuring only the correct customer's record is updated when an order is completed.

### 3. Recovery Workflow
The plugin employs a 3-step reminder system:

| Reminder | Timing | Content | Coupon |
| :------- | :----- | :------ | :----- |
| 1st | 30 Minutes | Friendly nudge | No |
| 2nd | 24 Hours | Urgency + Offer | 10% Off |
| 3rd | 48 Hours | Final Reminder | 10% Off |

### 4. Notification Channels
- **Email**: Sends a beautifully formatted HTML email showing the abandoned items and a restore button.
- **SMS**: Sends a concise text message via **BulkSMSBD** with a direct restoration link.

## Customization Settings

The plugin provides extensive customization options through the WordPress admin interface under **In complete order > Settings**:

### Reminder Timing
- Configure custom delays for each reminder (in minutes)
- Default: 30 min, 24 hours, 48 hours
- Adjust based on your business needs

### Email Templates
- Custom email subject line
- WYSIWYG editor for email body
- Supports dynamic placeholders for personalization

### SMS Templates
- Customizable SMS message text
- Supports the same placeholders as email

### Coupon Configuration
- Enable/disable automatic coupon generation
- Choose discount type: Percentage or Fixed Cart
- Set discount amount
- Select which reminders (2nd, 3rd, or both) include coupons

### Template Placeholders
All templates support these dynamic placeholders:
- `{customer_name}` - Customer's name
- `{restore_link}` - Cart restoration link
- `{coupon_code}` - Generated coupon code
- `{site_name}` - Your site name

## Admin Features

### In Complete Order (Dashboard)
Access this via the **In complete order** menu. Here you can:
- See a list of all abandoned carts.
- View customer names, emails, and phone numbers.
- Check which reminders have been sent.
- Get the specific restore link for any cart.
- Perform bulk deletions.

### Settings
Located under **In complete order > Settings**:
- Configure **BulkSMSBD API Key** and **Sender ID** to enable SMS notifications.

## Technical Details

### Database
Upon activation, the plugin creates a custom table `{wp_prefix}abandoned_carts` to store:
- User/Session ID
- Guest Name, Email, Phone
- Cart Data (JSON)
- Last Activity Timestamp
- Reminder Status (Sent/Not Sent)
- Unique Restore Key
- Generated Coupon Codes
- Order Analytics (Recovery Status, Amount, Order ID)

### Cron Job
The plugin registers a recurring hourly cron event `ac_cron_event` to scan the database and send reminders based on the timing rules.

### AJAX Capture
The script `assets/js/ac-script.js` listens for input changes on the checkout fields and sends data to the server without requiring a page reload or form submission.

## Restoration Process
When a customer clicks a restore link, they are redirected to the site with a special query parameter `ac_restore`. The plugin identifies the key, clears the current cart, and repopulates it with the saved items, taking the user directly to where they left off.

## Support & Contribution
For issues or feature requests, please visit the [GitHub Repository](https://github.com/coderjahidul/my-abandoned-cart).

---
*Created by MD Jahidul Islam Sabuz*
