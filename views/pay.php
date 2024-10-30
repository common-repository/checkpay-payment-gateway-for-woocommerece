<?php
define('WP_USE_THEMES', false);

$request = shortcode_atts(array(
    'oid' => NULL,
    'notice_str' => null
), filter_var_array(stripslashes_deep($_REQUEST),FILTER_SANITIZE_STRING));
$Alipay = new WC_Checkpay_Alipay();
if($Alipay->is_in_wechat()){
?>
<html>
<head>
    <meta http-equiv="content-type" content="text/html;charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>支付宝</title>

<body style="padding:0;margin:0;">
    <img alt="支付宝" src=<?php echo __('alipay-english.png', 'checkpay-payment-gateway-for-woocommerece') ?> style="max-width: 100%;">
</body>

</html>
<?php
exit;
}
else{
try {
    $order_id = isset($_REQUEST['id']) ? sanitize_text_field($_REQUEST['id']) : '';
    $result = $Alipay->process_payment($order_id);
    wp_redirect($result['redirect']);
} catch (Exception $e) {
    wp_die($e->getMessage());
}
}
