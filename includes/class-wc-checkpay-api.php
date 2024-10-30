<?php

if (!defined('ABSPATH')) {
	exit;
}

class Checkpay_API
{

	private static $merchant_id = '';

	private static $merchant_secret =  '';

	public static function set_merchant_id($merchant_id)
	{
		self::$merchant_id = $merchant_id;
	}

	public static function get_merchant_id()
	{
		if (!self::$merchant_id) {
			$options = get_option('woocommerce_checkpay_settings');

			if (isset($options['merchant_id'])) {
				self::set_merchant_id($options['merchant_id']);
			}
		}
		return self::$merchant_id;
	}

	public static function set_merchant_secret($merchant_secret)
	{
		self::$merchant_secret = $merchant_secret;
	}

	public static function get_merchant_secret()
	{
		if (!self::$merchant_secret) {
			$options = get_option('woocommerce_checkpay_settings');

			if (isset($options['merchant_secret'])) {
				self::set_merchant_secret($options['merchant_secret']);
			}
		}
		return self::$merchant_secret;
	}

	public static function make_nonce()
	{
		$result = '';
		$char_list = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		$nonce_length = rand(8, 32);
		for ($i = 1; $i <= $nonce_length; $i++) {
			$result = $result . $char_list[rand(0, strlen($char_list) - 1)];
		}
		return $result;
	}

	public static function generate_md5_sign($request_type, $nonce, $timestamp)
	{
		$merchant_id = self::get_merchant_id();
		$merchant_secret = self::get_merchant_secret();
		$pre_sign_str = $request_type . '&merchantId=' . $merchant_id . '&nonce=' . $nonce . '&timestamp=' . $timestamp . '&' . $merchant_secret;
		$sign = strtolower(hash('md5', $pre_sign_str));
		return $sign;
	}

	public static function create_order($order, $payment_gateway, $gateway_channel, $redirect)
	{

        $logger = wc_get_logger();
        $logger->debug( 'debug message, create order', array( 'source' => 'woocommerce-checkpay' ) );
		$currency = method_exists($order, 'get_currency') ? $order->get_currency() : $order->currency;

		if ($currency != 'CAD') {
			throw new Exception('Only CAD is Supported');
		}

		$order_id = method_exists($order, 'get_id') ? $order->get_id() : $order->id;
		$merchant_order_id = time() . $order_id;
		update_post_meta($order_id, 'merchant_order_id', $merchant_order_id);
		$merchant_id = self::get_merchant_id();
		$nonce = self::make_nonce();
		$timestamp = time();
		$sign = self::generate_md5_sign('POST', $nonce, $timestamp);
		update_post_meta($order_id, 'payment_gateway', $payment_gateway);

		$blog_info = get_bloginfo('name');

		$url = 'https://api.checkpay.ca/api/v1/orders?merchantId=' . $merchant_id . '&nonce=' . $nonce . '&timestamp=' . $timestamp . '&sign=' . $sign;
        $head = array('Content-Type' => 'application/json; charset=utf-8');

		$data = new stdClass();
		$data->merchant_order_id = $merchant_order_id;
		$data->payment_gateway = $payment_gateway;
		$data->redirect_url =  $redirect;
		$data->notify_url =  get_site_url() . '/?wc-api=wc_checkpay_notify';
		$data->transaction_amount = (int) ($order->get_total() * 100);
		$data->gateway_channel = $gateway_channel;
		$data->description = 'Payment from ' . $blog_info;
		if (!$data->transaction_amount >= 1) {
			throw new Exception('The minimal amount is $0.01');
		}

		$data = json_encode($data);

        $args = array(
            'body'        => $data,
            'timeout'     => '120',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => $head,
        );

        $response = wp_remote_post( $url, $args );
        $httpStatusCode = wp_remote_retrieve_response_code( $response );

		if ($httpStatusCode != 201) {
			throw new Exception("invalid httpstatus:{$httpStatusCode} ,response:$response,detail_error:" . $httpStatusCode, $httpStatusCode);
		}

		$result =  wp_remote_retrieve_body( $response );

		$resArr = json_decode($result, false);
		if (!$resArr) {
			throw new Exception('This request has been rejected by the checkpay service!');
		}

		update_post_meta($order_id, 'checkpay_order_id', $resArr->order_id);
		update_post_meta($order_id, 'merchant_order_id', $resArr->merchant_order_id);

		return $resArr;
	}

	public static function create_refund($amount, $order_id, $reason)
	{
		$merchant_order_id = get_post_meta($order_id, 'merchant_order_id',true);


		if ($reason == '') {
			$reason = 'Refund by Merchant: ' . get_bloginfo('name');
		}
		$merchant_id = self::get_merchant_id();
		$nonce = self::make_nonce();
		$timestamp = time();
		$sign = self::generate_md5_sign('POST', $nonce, $timestamp);


		$url = 'https://api.checkpay.ca/api/v1/refund?merchantId=' . $merchant_id . '&nonce=' . $nonce . '&timestamp=' . $timestamp . '&sign=' . $sign;
        $head = array('Content-Type' => 'application/json; charset=utf-8');


		$data = new stdClass();
		$data->amount = $amount;
		$data->merchant_order_id = $merchant_order_id;
		$data->merchant_refund_id = time() . $order_id;
		$data->refund_reason = $reason;

		$data = json_encode($data);

        $args = array(
            'body'        => $data,
            'timeout'     => '120',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => $head,
        );

        $response = wp_remote_post( $url, $args );
        $result =  wp_remote_retrieve_body( $response );
        $res = json_decode($result, false);

		return $res;
	}

	public function get_order_status($checkpay_order_id)
	{

		$merchant_id = self::get_merchant_id();
		$nonce = self::make_nonce();
		$timestamp = time();
		$sign = self::generate_md5_sign('GET', $nonce, $timestamp);

		$url = 'https://api.checkpay.ca/api/v1/orders/' . $checkpay_order_id . '?merchantId=' . $merchant_id . '&nonce=' . $nonce . '&timestamp=' . $timestamp . '&sign=' . $sign;

        $response = wp_remote_get( $url );
        $result =  wp_remote_retrieve_body( $response );
		$res = json_decode($result, false);
		if (!$res) {
			return new WP_Error('refuse_error', $result);
		}

		return $res;
	}

	public static function wc_checkpay_notify()
	{
        $logger = wc_get_logger();
        $logger->debug( 'debug message, test working', array( 'source' => 'woocommerce-checkpay' ) );

		$json = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';

		if (empty($json)) {
			$json = file_get_contents("php://input");
		}



		if (empty($json)) {
			print json_encode(array('status' => 'FAIL'));
			exit;
		}


		$response = json_decode($json, false);


		// hash one by one
		$merchant_secret = self::get_merchant_secret();
		$pre_sign_arr = json_decode($json, true);
		unset($pre_sign_arr['sign']);
		ksort($pre_sign_arr);
		$pre_sign_str = 'POST';
		foreach ($pre_sign_arr as $key => $value) {
		    if (!empty($value)) {
			    $pre_sign_str = $pre_sign_str . '&' . $key . '=' . $value;
		    }
		}
		$pre_sign_str = $pre_sign_str . '&' . $merchant_secret;
		$sign = strtolower(hash('md5', $pre_sign_str));

		if ($sign != $response->sign) {
			print json_encode(array('status' => 'FAIL'));
			exit;
		}
		$order_id = $response->order_id;

		$orders = wc_get_orders(array('meta_key' => 'merchant_order_id', 'meta_value' => $order_id));

		if (count($orders) == 0)
		{
			print json_encode(array('status' => 'FAIL'));
			exit;
		}

		$order = $orders[0];

		if (!$order || !$order->needs_payment()) {
			print json_encode(array('status' => 'SUCCESS'));
			exit;
		}

		if ($response->status != 'COMPLETED') {
			print json_encode(array('status' => 'FAIL'));
			exit;
		}

		$checkpay_order_id = get_post_meta($order->get_id(), 'checkpay_order_id', true);
		$res = self::get_order_status($checkpay_order_id);
		if ($res->status != 'COMPLETED') {
			print json_encode(array('status' => 'FAIL'));
			exit;
		}
		try {
			$order->payment_complete($response->order_id);
		} catch (Exception $e) {
			print json_encode(array('status' => 'FAIL'));
			exit;
		}

		print json_encode(array('status' => 'SUCCESS'));
		exit;
	}
}
