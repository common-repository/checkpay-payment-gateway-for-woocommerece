<?php
/*
 * Plugin Name: Checkpay Payment Gateway for Woocommerece
 * Description: Checkpay Payment Gateway for Woocommerece
 * Version: 1.0
 * Author: Checkpay
 * Author URI: https://www.checkpay.ca/
 * Text Domain: checkpay-payment-gateway-for-woocommerece
 * Domain Path: /lang
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

add_action( 'plugins_loaded', 'checkpay_init', 11 );

function checkpay_init() {
	load_plugin_textdomain( 'checkpay-payment-gateway-for-woocommerece', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
	header("Access-Control-Allow-Origin: *");
    if( class_exists( 'WC_Payment_Gateway' ) ) {
		include_once( 'includes/class-wc-checkpay-api.php' );
		include_once( 'includes/class-wc-checkpay-gateway-wechat.php' );
		include_once( 'includes/class-wc-checkpay-gateway-alipay.php' );

	}
	define('CHECKPAY_FILE',__FILE__);
	define('CHECKPAY_URL',rtrim(plugin_dir_url(CHECKPAY_FILE),'/'));

	global $Wechat;
	$Wechat= new WC_Checkpay_Wechat();

	add_action ( 'woocommerce_receipt_'.$Wechat->id, array ($Wechat,'wc_receipt'),10,1);

	global $Alipay;
	$Alipay= new WC_Checkpay_Alipay();

	// add_action ( 'woocommerce_receipt_'.$Alipay->id, array ($Alipay,'wc_receipt'),10,1);


	add_filter( 'woocommerce_payment_gateways', 'add_checkpay_gateways' );
	function add_checkpay_gateways( $methods ) {
		$methods[] = 'WC_CheckPay_Wechat';
		$methods[] = 'WC_CheckPay_Alipay';
		return $methods;
	}

	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'checkpay_action_links' );
	function checkpay_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=checkpay' ) . '">' .  'Settings' . '</a>',
		);
		return array_merge( $plugin_links, $links );
	}
}



?>
