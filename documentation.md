# My Abandoned Cart Documentation

## Introduction

**My Abandoned Cart** is a comprehensive solution for WooCommerce to recover lost sales. It tracks whenever a user adds items to their cart but fails to complete the purchase. The plugin captures guest data, sends automated notifications, and provides a seamless restoration process.

## How It Works

### 1. Cart Tracking
The plugin monitors the WooCommerce cart session. For logged-in users, it uses their account information. For guests, it utilizes an AJAX-based capture mechanism that triggers when they enter their details on the checkout page.

### 2. Recovery Workflow
The plugin employs a 3-step reminder system:

| Reminder | Timing | Content | Coupon |
| :------- | :----- | :------ | :----- |
| 1st | 30 Minutes | Friendly nudge | No |
| 2nd | 24 Hours | Urgency + Offer | 10% Off |
| 3rd | 48 Hours | Final Reminder | 10% Off |

### 3. Notification Channels
- **Email**: Sends a beautifully formatted HTML email showing the abandoned items and a restore button.
- **SMS**: Sends a concise text message via **BulkSMSBD** with a direct restoration link.

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
