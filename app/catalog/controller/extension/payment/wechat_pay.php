<?php
/**
 * @package		OpenCart
 * @author		Meng Wenbin
 * @copyright	Copyright (c) 2010 - 2017, Chengdu Guangda Network Technology Co. Ltd. (https://www.opencart.cn/)
 * @license		https://opensource.org/licenses/GPL-3.0
 * @link		https://www.opencart.cn
 */
use Wechat\Lib\Tools;

class ControllerExtensionPaymentWechatPay extends Controller {
	public function index() {
		$data['button_confirm'] = $this->language->get('button_confirm');

		$data['redirect'] = $this->url->link('extension/payment/wechat_pay/qrcode');

		return $this->load->view('extension/payment/wechat_pay', $data);
	}

    /**
     * 生成支付签名
     * @param array $option
     * @param string $partnerKey
     * @return string
     */
    static public function getPaySign($option, $partnerKey) {
        ksort($option);
        $buff = '';
        foreach ($option as $k => $v) {
            $buff .= "{$k}={$v}&";
        }
        //echo "{$buff}key={$partnerKey}";
        //echo "KEY RAW!!!!";
        return strtoupper(md5("{$buff}key={$partnerKey}"));
	}
	
    static public function httpPost($url, $data_string) {
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
	
	public function getOpenID($appid, $secret, $code) {
		$url = "https://api.weixin.qq.com/sns/oauth2/access_token" . "?appid={$appid}&secret={$secret}&code={$code}" . "&grant_type=authorization_code";
		$result = Tools::httpGet($url);
        if ($result) {
            $json = json_decode($result, true);
            if (!$json || !empty($json['errcode'])) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                Tools::log("WechatOauth::getOauthAuth Fail.{$this->errMsg} [{$this->errCode}]", 'ERR');
                return false;
            } else if ($json['errcode'] == 0) {
                return $json["openid"];
            }
        }
        return false;
	}

	public function uePay(){
		//https://open.weixin.qq.com/connect/oauth2/authorize?appid=wxe247769eb88def6e&redirect_uri=http%3A%2F%2Fsntong.synology.me&response_type=code&scope=snsapi_base&state=STATE#wechat_redirect

		$uePayUrl = 'http://183.6.50.156:14031/weChatPay/entry.do';

		$key = $this->config->get('payment_wechat_pay_api_secret');
		$key = '3315C66DF6265C47BC1';

		echo $key;
		$postData = array(
			'arguments'         => array("orderNo"=>"20180214143250"),
			'appSource'			=>  "1",
			'appVersion'		=>  "1.2",
			'requestType'		=>  "JSAPI",
			'merchantNo'		=>  $this->config->get('payment_wechat_pay_app_id'),
		);

		$keyOption = array_merge($postData, $postData["arguments"]);
		unset($keyOption["arguments"]);
		var_dump($keyOption);
		echo 'postData';
		$postData['clientSign'] = self::getPaySign($keyOption, $key);
		var_dump($postData);

		$postJson = Tools::json_encode($postData);
		var_dump($postJson);
		#echo 'POST!!';
		#$uePayResult = self::httpPost($uePayUrl, $postJson);
		#echo 'RESULT!!';
		#var_dump($uePayResult);
		echo 'getPrepayId!!';
		$result = $pay->getPrepayId(NULL, $subject, $order_id,  1, $notify_url, $trade_type = "JSAPI", NULL);
		var_dump($result);
		//$result = $pay->getPrepayId(NULL, $subject, $order_id, $total_amount * 100, $notify_url, $trade_type = "NATIVE", NULL, $currency);

	}
	
	public function test() {
		//$options = array(
		//	'appid'			 =>  'wxe247769eb88def6e',
		//	'appsecret'		 =>  '3315c66df6265c47bc1bce401e9c08c9'
		//);

		$options = array(
			'appid'			 =>  'wx8419eb47c3415f1a',
			'appsecret'		 =>  '44481f03441c07062e30b275a63919b2'
		);

		$json = array();
		$json['result'] = true;
		if (isset($this->request->get['code'])){
			$code = $this->request->get['code'];
			$json['code'] = $code;
			$json['openid'] = $this->getOpenID($options['appid'], $options['appsecret'], $code);
		}

		$this->response->setOutput(json_encode($json));
	}

	public function qrcode() {

		$this->load->language('extension/payment/wechat_pay');

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
			'href' => $this->url->link('extension/payment/wechat_pay/qrcode')
		);

		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		//echo 'testing1';
		$order_id = trim($order_info['order_id']);
		$data['order_id'] = $order_id;
		$subject = trim($this->config->get('config_name'));
		$currency = $this->config->get('payment_wechat_pay_currency');
		$total_amount = trim($this->currency->format($order_info['total'], $currency, '', false));
		$notify_url = HTTPS_SERVER . "payment_callback/wechat_pay"; //$this->url->link('wechat_pay/callback');
		//echo 'testing2';
		$options = array(
			'appid'			 =>  $this->config->get('payment_wechat_pay_app_id'),
			'appsecret'		 =>  $this->config->get('payment_wechat_pay_app_secret'),
			'mch_id'			=>  $this->config->get('payment_wechat_pay_mch_id'),
			'partnerkey'		=>  $this->config->get('payment_wechat_pay_api_secret')
		);

		\Wechat\Loader::config($options);
		$pay = new \Wechat\WechatPay();

		$result = $pay->getPrepayId(NULL, $subject, $order_id, $total_amount * 100, $notify_url, $trade_type = "NATIVE", NULL, $currency);

		$data['error'] = '';
		$data['code_url'] = '';
		if($result === FALSE){
			$data['error_warning'] = $pay->errMsg;
		} else {
			$data['code_url'] = $result;
		}

		$data['action_success'] = $this->url->link('checkout/success');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('extension/payment/wechat_pay_qrcode', $data));
	}

	public function isOrderPaid() {
		$json = array();

		$json['result'] = false;

		if (isset($this->request->get['order_id'])) {
			$order_id = $this->request->get['order_id'];

			$this->load->model('checkout/order');
			$order_info = $this->model_checkout_order->getOrder($order_id);

			if ($order_info['order_status_id'] == $this->config->get('payment_wechat_pay_completed_status_id')) {
				$json['result'] = true;
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function callback() {
		$options = array(
			'appid'			 =>  $this->config->get('payment_wechat_pay_app_id'),
			'appsecret'		 =>  $this->config->get('payment_wechat_pay_app_secret'),
			'mch_id'			=>  $this->config->get('payment_wechat_pay_mch_id'),
			'partnerkey'		=>  $this->config->get('payment_wechat_pay_api_secret')
		);

		\Wechat\Loader::config($options);
		$pay = new \Wechat\WechatPay();
		$notifyInfo = $pay->getNotify();

		if ($notifyInfo === FALSE) {
			$this->log->write('Wechat Pay Error: ' . $pay->errMsg);
		} else {
			if ($notifyInfo['result_code'] == 'SUCCESS' && $notifyInfo['return_code'] == 'SUCCESS') {
				$order_id = $notifyInfo['out_trade_no'];
				$this->load->model('checkout/order');
				$order_info = $this->model_checkout_order->getOrder($order_id);
				if ($order_info) {
					$order_status_id = $order_info["order_status_id"];
					if (!$order_status_id) {
						$this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_wechat_pay_completed_status_id'));
					}
				}
				return xml(['return_code' => 'SUCCESS', 'return_msg' => 'DEAL WITH SUCCESS']);
			}
		}
	}
}
