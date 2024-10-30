<?php

if (!defined('ABSPATH')) {
	exit;
}
class WC_Checkpay_Wechat extends WC_Payment_Gateway
{


	public function __construct()
	{
		$this->icon = CHECKPAY_URL . '/assets/images/wechat.png';
		$this->id = "checkpay";
		$this->method_description = __('Wechat Payment Gateway powered by <a href="https://www.checkpay.ca" target="_blank">Checkpay</a>','checkpay-payment-gateway-for-woocommerece');
		$this->has_fields = false;
		$this->method_title = __('Checkpay - WeChat','checkpay-payment-gateway-for-woocommerece');
		$this->supports[] = 'refunds';
		$this->init_form_fields();
		$this->init_settings();

		foreach ($this->settings as $setting_key => $value) {
			$this->$setting_key = $value;
		}
		if (is_admin()) {
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		}
		add_action("wp_ajax_checkpay_order_status", array($this, 'order_paid'));
		add_action("wp_ajax_nopriv_checkpay_order_status", array($this, 'order_paid'));
		add_action('woocommerce_api_wc_checkpay_notify', array('Checkpay_API', 'wc_checkpay_notify'));
		add_action('wp_enqueue_scripts', array($this, 'my_admin_scripts'));
	}

	public function my_admin_scripts()
	{
		wp_register_style('checkpay-style', plugins_url('assets/css/style.css', CHECKPAY_FILE), array());
		wp_enqueue_style('checkpay-style');
	}

	public function get_icon()
	{

		$icons_str = '<img src="' . CHECKPAY_URL . '/assets/images/wechat.png" class="icon-size" alt="Wechat Pay" />';

		return apply_filters('woocommerce_gateway_icon', $icons_str, $this->id);
	}

	public function is_available()
	{

		if (!$this->merchant_id || !$this->merchant_secret) {
			return false;
		}

		if ($this->enabled == 'no') {
			return false;
		}

		if (wp_is_mobile() && !$this->is_in_wechat()) {
			return false;
		}
		return $this->enabled;
	}


	function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'title' => 'Enable/Disable',
				'type' => 'checkbox',
				'label' => __('Enable Checkpay - WeChat','checkpay-payment-gateway-for-woocommerece'),
				'default' => 'no',
				'section' => 'default',
                'description' => sprintf( __( '* To Enable Alipay <a href="%s" target="_blank">here</a>.', 'checkpay-payment-gateway-for-woocommerece' ),
                admin_url( 'admin.php?page=wc-settings&tab=checkout&section=checkpay_alipay' )
                )
			),
			'title' => array(
				'title' => 'Title',
				'type' => 'text',
				'default' =>  __('WeChat Pay','checkpay-payment-gateway-for-woocommerece'),
				'desc_tip' => true,
				'css' => 'width:512px',
				'section' => 'default'
			),
			'description' => array(
				'title' => 'Description',
				'type' => 'textarea',
				'default' => __('Pay With Wechat App','checkpay-payment-gateway-for-woocommerece'),
				'desc_tip' => true,
				'css' => 'width:512px',
				'section' => 'default'
			),
			'merchant_id' => array(
				'title' => 'Merchant ID',
				'type' => 'text',
				'css' => 'width:512px',
				'section' => 'default',
				'description' => __('To open a merchant account, please Contact Us at +1-778-317-9562','checkpay-payment-gateway-for-woocommerece')
			),
			'merchant_secret' => array(
				'title' => 'Merchant Secret',
				'type' => 'text',
				'css' => 'width:512px',
				'section' => 'default'
			)

		);
	}

	public  function is_in_wechat()
	{
		return strripos($_SERVER['HTTP_USER_AGENT'], 'micromessenger') == true;
	}

	public function process_payment($order_id)
	{
		$order = new WC_Order($order_id);
		if (!$order || !$order->needs_payment()) {
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url($order)
			);
		}
		try {
			if ($this->is_in_wechat()) {
				$result = Checkpay_API::create_order($order, 'wechatpay', "jsapi", $this->get_return_url($order));
				return array(
					'result'   => 'success',
					'redirect' => $result->internal_pay_url
				);
			} else {
				$result = Checkpay_API::create_order($order, 'wechatpay', "native", $this->get_return_url($order));
				return array(
					'result'   => 'success',
					'redirect' => $result->pay_url
				);
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	public function process_refund($order_id, $refund_amount = null, $reason = '')
	{
		$order = new WC_Order($order_id);
		if (!$order) {
			return new WP_Error('invalid_order', 'Wrong Order');
		}

		$total_amount = (int) ($order->get_total() * 100);
		$refund_amount = (int) ($refund_amount * 100);
		if ($refund_amount <= 0 || $refund_amount > $total_amount) {
			return new WP_Error('invalid_order', 'Invalid Amount ');
		}

		$resArr = Checkpay_API::create_refund($refund_amount, $order_id, $reason);


		if (!$resArr) {
			return new WP_Error('refuse_error');
		}

		if ($resArr->status != 'SUCCESS') {
			return new WP_Error('refuse_error', sprintf('ERROR CODE:%s', empty($resArr->status) ? $resArr->status : $resArr->status));
		}
		return true;
	}

	public function order_paid()
	{
		$order_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : 0;

		if (!$order_id) {
			echo json_encode(array(
				'status' => 'unpaid'
			));
			exit;
		}

		$order = new WC_Order($order_id);
		if (!$order || $order->needs_payment()) {
			echo json_encode(array(
				'status' => 'unpaid'
			));
			exit;
		}

		echo json_encode(array(
			'status' => 'paid'
		));
		exit;
	}
}
