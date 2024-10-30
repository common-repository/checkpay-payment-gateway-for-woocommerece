<?php

if (!defined('ABSPATH')) {
	exit;
}
class WC_Checkpay_Alipay extends WC_Payment_Gateway
{


	public function __construct()
	{
		$this->icon = CHECKPAY_URL . '/assets/images/alipay.png';
		$this->id = "checkpay_alipay";
		$this->method_description = __('Alipay Payment Gateway powered by <a href="https://www.checkpay.ca" target="_blank">Checkpay</a>','checkpay-payment-gateway-for-woocommerece');
		$this->has_fields = false;
		$this->method_title = __('Checkpay - Alipay','checkpay-payment-gateway-for-woocommerece');
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

		$icons_str = '<img src="' . CHECKPAY_URL . '/assets/images/alipay.png" class="icon-size" alt="Alipay Pay" />';

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
		return $this->enabled;
	}


	function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'title' => 'Enable/Disable',
				'type' => 'checkbox',
				'label' => __('Enable Checkpay - Alipay','checkpay-payment-gateway-for-woocommerece'),
				'default' => 'no',
				'section' => 'default'
			),
			'title' => array(
				'title' => 'Title',
				'type' => 'text',
				'default' =>  __('Alipay Pay','checkpay-payment-gateway-for-woocommerece'),
				'desc_tip' => true,
				'css' => 'width:512px',
				'section' => 'default'
			),
			'description' => array(
				'title' => 'Description',
				'type' => 'textarea',
				'default' => __('Pay With Alipay App','checkpay-payment-gateway-for-woocommerece'),
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

	public function is_app_client(){
	    if(!isset($_SERVER['HTTP_USER_AGENT'])){
			return false;
		}

		$u=strtolower($_SERVER['HTTP_USER_AGENT']);
		if($u==null||strlen($u)==0){
			return false;
		}

		preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/',$u,$res);

		if($res&&count($res)>0){
			return true;
		}

		if(strlen($u)<4){
			return false;
		}

		preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/',substr($u,0,4),$res);
		if($res&&count($res)>0){
			return true;
		}

		$ipadchar = "/(ipad|ipad2)/i";
		preg_match($ipadchar,$u,$res);
		if($res&&count($res)>0){
			return true;
		}

		return false;
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
				return array(
					'result'   => 'success',
					'redirect' => add_query_arg('lang', sanitize_text_field($_GET['lang']), CHECKPAY_URL.'/views/pay.php?id='.$order_id)
				);
			} else {
				if($this->is_app_client()){
					$result = Checkpay_API::create_order($order, 'alipay', "jsapi", $this->get_return_url($order));
					return array(
						'result'   => 'success',
						'redirect' => $result->internal_pay_url
					);
				} else {
					$result = Checkpay_API::create_order($order, 'alipay', "native", $this->get_return_url($order));
					return array(
						'result'   => 'success',
						'redirect' => $result->pay_url
					);
				}
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
