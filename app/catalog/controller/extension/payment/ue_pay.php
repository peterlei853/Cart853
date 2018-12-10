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
				echo $result;
                Tools::log("WechatOauth::getOauthAuth Fail.{$this->errMsg} [{$this->errCode}]", 'ERR');
                return false;
            } else if ($json['errcode'] == 0) {
                return $json["openid"];
            }
        }
        return false;
	}

	private function getClientSign($postData, $key){
		$keyOption = array_merge($postData, $postData["arguments"]);
		unset($keyOption["arguments"]);
		//var_dump($keyOption);
		return self::getPaySign($keyOption, $key);
	}

	public function genOrderNo(){
		return date("YmdHis");
	}

	public function uePost($requestType, $arguments){
		//$uePayUrl = 'http://183.6.50.156:14031/weChatPay/entry.do';
		//$ueMerchantNo = '000030053310001';
		//$ueKey = '3315C66DF6265C47BC1BCE401E9C08C9';
		$uePayUrl = 'https://openapi.uepay.mo/weChatPay/entry.do';
		$ueMerchantNo = '001020453997690';
		$ueKey = '109ef195631ffee72eae389e3b501574';
		
		$postData = array(
			'arguments'         =>  $arguments,
			'appSource'			=>  "1",
			'appVersion'		=>  "1.2",
			'requestType'		=>  $requestType,
			'merchantNo'		=>  $ueMerchantNo,
		);
		$postData['clientSign'] = $this->getClientSign($postData, $ueKey);
		$postJson = Tools::json_encode($postData);
		var_dump($postJson);
		$uePayResult = self::httpPost($uePayUrl, $postJson);
		return $uePayResult;
	}

	public function ueQuery(){
		$arguments = array(
			'orderNo' => '20181205143305'
		);
		return $this->uePost('QUERY', $arguments);
	}

	public function uePrePay($openid, $amt){
		//20181205143250
		$arguments = array(
			'orderNo' => '20181205143305',
			'body' => 'Test Good',
			'amt' => strval($amt * 100),
			"payMethod" => "wx",
			'openid' => $openid,
			'attach' => 'test'
		);
		$result = $this->uePost('JSAPI', $arguments);
		if ($result) {
			$json = json_decode($result, true);
			if (!$json || $json['result'] !== 'true') {
				echo 'ERROR!!';
				echo $result;
                return false;
            } else if ($json['result'] == 'true') {
				echo 'GOOD!!';
                return $json['results'];
            }
		}
		return false;
	}
	
	public function test() {
		$options = array(
			'appid'			 =>  'wxe247769eb88def6e',
			'appsecret'		 =>  'a8c5677da0d1e0c48f4b84b693863bfb'
		); //sandbox , production is same

		//$options = array(
		//	'appid'			 =>  'wx8419eb47c3415f1a',
		//	'appsecret'		 =>  '44481f03441c07062e30b275a63919b2'
		//); //test

		$json = array();
		$json['result'] = true;
		if (isset($this->request->get['code'])){
			$code = $this->request->get['code'];
			$json['code'] = $code;
			$json['openid'] = $this->getOpenID($options['appid'], $options['appsecret'], $code);

			if ($json['openid']){
				$amt = 0.01;
				//$this->ueQuery();
				$data = $this->uePrePay($json['openid'], $amt);
				if($data){
					echo 'GOOD2';
					$data['debug'] = json_encode($data);
					var_dump($data);
					return $this->response->setOutput($this->load->view('extension/payment/ue_pay', $data));
				}
			}
		}

		$data = array(
			'debug' => json_encode($json),
			'appId' => '123'
		);
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
