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
  <title>আপনার কার্টে এখনও আইটেম আছে!</title>
  <style>
    body { font-family: Arial, sans-serif; background-color: #f9f9f9; margin:0; padding:0;}
    .container { max-width: 600px; margin: 30px auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);}
    h2 { color: #0073aa; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0;}
    th, td { padding: 10px; border: 1px solid #ddd; text-align: left;}
    a.button { background: #28a745; color: #fff; padding: 12px 20px; text-decoration: none; border-radius: 5px; display: inline-block;}
    a.button:hover { background: #1e7e34;}
    .coupon { background: #fff3cd; padding: 10px; margin: 20px 0; border: 1px dashed #ffc107; color: #856404;}
  </style>
</head>
<body>
<div class="container">
  <h2>হ্যালো <?php echo esc_html($name_var); ?>,</h2>
  <p>আপনার কার্টে এখনও কিছু প্রোডাক্ট রয়েছে। এই সুযোগ মিস করবেন না!</p>
  
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
      <strong>বিশেষ ডিসকাউন্ট!</strong> আপনার কুপন কোড: <strong><?php echo esc_html($coupon_code_var); ?></strong>
      <br>Checkout করার সময় ব্যবহার করুন।
    </div>
  <?php endif; ?>

  <p>ধন্যবাদ,<br><?php echo get_bloginfo('name'); ?></p>
</div>
</body>
</html>
