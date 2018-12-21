<?php
/**
 * @package		OpenCart
 * @author		Meng Wenbin
 * @copyright	Copyright (c) 2010 - 2017, Chengdu Guangda Network Technology Co. Ltd. (https://www.opencart.cn/)
 * @license		https://opensource.org/licenses/GPL-3.0
 * @link		https://www.opencart.cn
 */
use Wechat\Lib\Tools;

class ControllerExtensionPaymentUePay extends Controller {
	public function index() {
		$data['button_confirm'] = $this->language->get('button_confirm');

		$data['redirect'] = $this->url->link('extension/payment/ue_pay/qrcode');

		return $this->load->view('extension/payment/ue_pay', $data);
	}

	public function query(){
		if (isset($this->request->get['order_id'])) {
			$order_id = $this->request->get['order_id'];
			$result = $this->ueQuery($order_id);
			if ($result){
				if($result['result'] == 'true'){
					$data['message'] = json_encode($result['results']); //TODO
				}else{
					$data['message'] = $result['enMessage'];
				}
				$data['debug'] = json_encode($result);
			}
			return $this->setPayOutput('extension/payment/ue_pay_confirm', $data);
		}
	}

	public function complete(){
		if (isset($this->request->get['order_id'])) {
			$this->load->model('checkout/order');
			$order_id = $this->request->get['order_id'];
			$order_info = $this->model_checkout_order->getOrder($order_id);
			if ($order_info) {
				$isPaid = $this->isUeOrderPaid($order_id);
				if ($isPaid){
					$order_status_id = $order_info["order_status_id"];
					if (!$order_status_id) {
						$this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_ue_pay_completed_status_id'));
					}
					$data['action_success'] = $this->url->link('checkout/success');
				}else{
					$data = array('error_warning' => '支付未完成, 無法完成訂單');
				}
				return $this->setPayOutput('extension/payment/ue_pay_confirm', $data);
			}else{
				$data = array('error_warning' => '支付完成失敗');
				return $this->setPayOutput('extension/payment/ue_pay_confirm', $data);
			}
		}else{
			$data = array('error_warning' => '無效參數');
			return $this->setPayOutput('extension/payment/ue_pay_confirm', $data);
		}
	}


	public function pay(){
		if (isset($this->request->get['order_id']) && isset($this->request->get['code'])) {
			$order_id = trim($this->request->get['order_id']);
			$options = $this->uePayOptions();
			$open_id = $this->getOpenID($options['appid'], $options['appsecret']);

			if ($open_id) {
				$data = $this->ueRequestPay($order_id, $open_id);
				if($data){
					$data['complete_url'] = HTTP_SERVER . "index.php?route=extension/payment/ue_pay/complete&order_id=" . $order_id;
					$data['debug'] = json_encode($data);
					$data['ready'] = true;
					$data['message'] = '請求支付中...';
					return $this->setPayOutput('extension/payment/ue_pay_confirm', $data);
				}else{
					$data = array('error_warning' => '請求 uePay 支付失敗');
					return $this->setPayOutput('extension/payment/ue_pay_confirm', $data);
				}

			}else{
				$data = array('error_warning' => '取得 open id 失敗');
				return $this->setPayOutput('extension/payment/ue_pay_confirm', $data);
			}
			
		}else{
			$data = array('error_warning' => 'wechat auth 失敗');
			return $this->setPayOutput('extension/payment/ue_pay_confirm', $data);
			return false;
		}
	}

	public function qrcode() {

		$this->load->language('extension/payment/ue_pay');

		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->addScript('catalog/view/javascript/qrcode.js');

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_checkout'),
			'href' => $this->url->link('checkout/checkout', '', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_qrcode'),
			'href' => $this->url->link('extension/payment/ue_pay/qrcode')
		);
		$order_id = $this->session->data['order_id'];

		$appid = $this->config->get('payment_ue_pay_app_id');
		$callback = HTTP_SERVER . "index.php?route=extension/payment/ue_pay/pay&order_id=" . $order_id;
		$weOAuth = new \Wechat\WechatOauth(array('appid'=>$appid));
		$data['code_url'] =  $weOAuth->getOauthRedirect($callback, 'STATE');//TODO
		$data['query_url'] =  HTTP_SERVER . "index.php?route=extension/payment/ue_pay/query&order_id=" . $order_id;
		$data['order_id'] = $order_id;

		$data['action_success'] = $this->url->link('checkout/success');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
		$this->response->setOutput($this->load->view('extension/payment/ue_pay_qrcode', $data));
	}

	public function isOrderPaid() {
		$json = array();
    
		$json['result'] = false;
    
		if (isset($this->request->get['order_id'])) {
			$order_id = $this->request->get['order_id'];
    
			$this->load->model('checkout/order');
			$order_info = $this->model_checkout_order->getOrder($order_id);
    
			if ($order_info['order_status_id'] == $this->config->get('payment_ue_pay_completed_status_id')) {
				$json['result'] = true;
			}
		}
    
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function getUePayOrderId($order_id){
		//$history_total = $this->model_sale_order->getTotalOrderHistories($order_id);
		$seq = '001'; //TODO 001 get order history.
		return $order_id . $seq; 
	}

	private function isUeOrderPaid($order_id){
		$result = $this->ueQuery($order_id);
		if ($result && $result['result'] == 'true'){
			return $result['results']['status'] == "01";
		}else{
			return false;
		}
	}

	private function uePayOptions(){
		return array(
			'appid'			 =>  $this->config->get('payment_ue_pay_app_id'),
			'appsecret'		 =>  $this->config->get('payment_ue_pay_app_secret'),
			'mch_id'			=>  $this->config->get('payment_ue_pay_mch_id'),
			'api_secret'		=>  $this->config->get('payment_ue_pay_api_secret'),
			'api_url'		=>  $this->config->get('payment_ue_pay_api_url')
		);
	}

	private function setPayOutput($view, $data){
		$this->load->language('extension/payment/ue_pay');
		$this->document->setTitle($this->language->get('heading_title'));

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view($view, $data));
	}

	private function getOpenID($appid, $secret) {
		$weOAuth = new \Wechat\WechatOauth(array('appid'=>$appid, 'appsecret'=>$secret));
		$result =  $weOAuth->getOauthAccessToken();
		if ($result) {
			return $result["openid"];
		}else{
			return false;
		}
	}

	private function getClientSign($postData, $key){
		$keyOption = array_merge($postData, $postData["arguments"]);
		unset($keyOption["arguments"]);
		return self::getPaySign($keyOption, $key);
	}

	private function genOrderNo(){
		return date("YmdHis");
	}

	private function uePost($requestType, $arguments){
		$options = $this->uePayOptions();
		$uePayUrl = $options['api_url']; //'https://openapi.uepay.mo/weChatPay/entry.do'
		$ueMerchantNo = $options['mch_id']; //'001020453997690'
		$ueKey = $options['api_secret']; //'109ef195631ffee72eae389e3b501574'
		
		$postData = array(
			'arguments'         =>  $arguments,
			'appSource'			=>  "1",
			'appVersion'		=>  "1.2",
			'requestType'		=>  $requestType,
			'merchantNo'		=>  $ueMerchantNo,
		);

		$postData['clientSign'] = $this->getClientSign($postData, $ueKey);
		$postJson = Tools::json_encode($postData);
		$uePayResult = self::httpPost($uePayUrl, $postJson);
		return $uePayResult;
	}

	private function ueQuery($order_id){
		$arguments = array(
			'orderNo' => $this->getUePayOrderId($order_id)
		);
		$result = $this->uePost('QUERY', $arguments);
		if ($result == false){
			return false;
		}
		return json_decode($result, true);
	}

	private function ueRequestPay($order_id, $open_id){
		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($order_id);
		$subject = trim($this->config->get('config_name'));
		$currency = $this->config->get('payment_ue_pay_currency');
		$total_amount = trim($this->currency->format($order_info['total'], $currency, '', false));
		
		$ue_order_id = $this->getUePayOrderId($order_id); //FORTEST ONLY

		$arguments = array(
			'orderNo' => '' . $ue_order_id,
			'body' => '' . $order_id,
			'amt' => strval((int)($total_amount * 100)),
			"payMethod" => "wx",
			'openid' => $open_id
		);

		$result = $this->uePost('JSAPI', $arguments);
		
		if ($result) {
			$json = json_decode($result, true);
			if (!$json || $json['result'] !== 'true') {
				if ($json["retCode"] == "4002"){
					//TODO handle duplicate, cancel the order.
				}
                return false;
            } else if ($json['result'] == 'true') {
                return $json['results'];
            }
		}else{
			return false;
		}
	}

	/**
     * 生成支付签名
     * @param array $option
     * @param string $partnerKey
     * @return string
     */
    static public function getPaySign($option, $partnerKey) {
		//TODO: refactor
        ksort($option);
        $buff = '';
        foreach ($option as $k => $v) {
            $buff .= "{$k}={$v}&";
        }
        return strtoupper(md5("{$buff}key={$partnerKey}"));
	}
	
    static public function httpPost($url, $data_string) {
		//TODO: refactor
        $ch = curl_init($url);                                                                      
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
            'Content-Type: application/json',                                                                                
            'Content-Length: ' . strlen($data_string))                                                                       
        );                                                                                                                   
        $result = curl_exec($ch);                                                                   
        curl_close($ch);
        if ($result) {
            return $result;
        }
        return false;
	}

	//public function callback() {
	//	//TODO, this is wechat not uepay
	//	$options = array(
	//		'appid'			 =>  $this->config->get('payment_ue_pay_app_id'),
	//		'appsecret'		 =>  $this->config->get('payment_ue_pay_app_secret'),
	//		'mch_id'			=>  $this->config->get('payment_ue_pay_mch_id'),
	//		'partnerkey'		=>  $this->config->get('payment_ue_pay_api_secret')
	//	);
    //
	//	\Wechat\Loader::config($options);
	//	$pay = new \Wechat\WechatPay();
	//	$notifyInfo = $pay->getNotify();
    //
	//	if ($notifyInfo === FALSE) {
	//		$this->log->write('Wechat Pay Error: ' . $pay->errMsg);
	//	} else {
	//		if ($notifyInfo['result_code'] == 'SUCCESS' && $notifyInfo['return_code'] == 'SUCCESS') {
	//			$order_id = $notifyInfo['out_trade_no'];
	//			$this->load->model('checkout/order');
	//			$order_info = $this->model_checkout_order->getOrder($order_id);
	//			if ($order_info) {
	//				$order_status_id = $order_info["order_status_id"];
	//				if (!$order_status_id) {
	//					$this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_ue_pay_completed_status_id'));
	//				}
	//			}
	//			return xml(['return_code' => 'SUCCESS', 'return_msg' => 'DEAL WITH SUCCESS']);
	//		}
	//	}
	//}
}
