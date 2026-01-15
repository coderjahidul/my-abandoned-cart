<?php
if (!defined('ABSPATH')) exit;

/**
 * Variables passed from Cron:
 * $cart_items_var : array of cart items
 * $restore_link_var : string, restore link
 * $name_var : string, customer name
 * $coupon_code_var : string, optional coupon
 */
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>আপনি আপনার কার্ট শেষ করেননি!</title>
  <style>
    body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin:0; padding:0;}
    .container { max-width: 600px; margin: 30px auto; background: #fff; padding: 20px; border-radius: 8px;}
    h2 { color: #333; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0;}
    th, td { padding: 10px; border: 1px solid #ddd; text-align: left;}
    a.button { background: #0073aa; color: #fff; padding: 12px 20px; text-decoration: none; border-radius: 5px;}
    a.button:hover { background: #005177;}
    .coupon { background: #f0f8ff; padding: 10px; margin: 20px 0; border: 1px dashed #0073aa; }
  </style>
</head>
<body>
<div class="container">
  <h2>হ্যালো <?php echo esc_html($name_var); ?>,</h2>
  <p>আপনি আপনার কার্টে কিছু প্রোডাক্ট রেখেছেন কিন্তু Checkout শেষ করেননি।</p>
  
  <table>
    <thead>
      <tr>
        <th>প্রোডাক্ট</th>
        <th>পরিমাণ</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($cart_items_var as $item) : ?>
        <tr>
          <td><?php echo esc_html($item['data']['name']); ?></td>
          <td><?php echo esc_html($item['quantity']); ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <p>Checkout করতে নিচের বাটনে ক্লিক করুন:</p>
  <a href="<?php echo esc_url($restore_link_var); ?>" class="button">Checkout Now</a>

  <?php if (!empty($coupon_code_var)) : ?>
    <div class="coupon">
      <strong>বিশেষ অফার:</strong> আপনার ডিসকাউন্ট কুপন কোড: <strong><?php echo esc_html($coupon_code_var); ?></strong>
      <br>Checkout করার সময় এটি ব্যবহার করুন।
    </div>
  <?php endif; ?>

  <p>ধন্যবাদ,<br><?php echo get_bloginfo('name'); ?></p>
</div>
</body>
</html>
