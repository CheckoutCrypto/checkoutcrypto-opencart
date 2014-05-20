<?php
/*
Copyright (c) 2013 John Atkinson (jga)
*/
require('cc.php');	


class ControllerPaymentCheckoutCrypto extends Controller {
	private $coincode = "POT";
    private $payment_module_name  = 'checkoutcrypto';
	protected function index() {
    $coincode = "POT";
	$this->load->model('setting/setting');
        $this->language->load('payment/'.$this->payment_module_name);
    	$this->data['button_checkoutcrypto_pay'] = $this->language->get('button_checkoutcrypto_pay');
    	$this->data['text_please_send'] = $this->language->get('text_please_send');
    	$this->data['text_pot_to'] = $this->language->get('text_pot_to');
    	$this->data['text_to_complete'] = $this->language->get('text_to_complete');
    	$this->data['text_click_pay'] = $this->language->get('text_click_pay');
    	$this->data['text_uri_compatible'] = $this->language->get('text_uri_compatible');
    	$this->data['text_click_here'] = $this->language->get('text_click_here');
    	$this->data['text_pre_timer'] = $this->language->get('text_pre_timer');
    	$this->data['text_post_timer'] = $this->language->get('text_post_timer');
		$this->data['text_countdown_expired'] = $this->language->get('text_countdown_expired');
    	$this->data['text_if_not_redirect'] = $this->language->get('text_if_not_redirect');
		$this->data['error_msg'] = $this->language->get('error_msg');
		$this->data['error_confirm'] = $this->language->get('error_confirm');
		$this->data['error_incomplete_pay'] = $this->language->get('error_incomplete_pay');
		$this->data['checkoutcrypto_countdown_timer'] = $this->config->get('checkoutcrypto_countdown_timer');
		$checkoutcrypto_pot_decimal = $this->config->get('checkoutcrypto_pot_decimal');
		
		$this->checkUpdate();
	
        $this->load->model('checkout/order');
		$order_id = $this->session->data['order_id'];
		$order = $this->model_checkout_order->getOrder($order_id);

        $current_default_currency = $this->config->get('config_currency');

		$this->data['checkoutcrypto_total'] = sprintf("%.".$checkoutcrypto_pot_decimal."f", round($this->currency->convert($order['total'], $current_default_currency, 'POT'),$checkoutcrypto_pot_decimal));  /// <----- REMINDER TO SET TO DYNAMIC DEFAULT CURRENCY 
		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET checkoutcrypto_total = '" . $this->data['checkoutcrypto_total'] . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");


	$apikey = $this->config->get('checkoutcrypto_api_key');
    $ccApi = new CheckoutCryptoApi($apikey);
	$response = $ccApi->query(array('action' => 'getnewaddress','apikey' => $apikey, 'coin' => $coincode, 'amount'=> $this->data['checkoutcrypto_total']));  // $request['coin_name']
/*  ADDRESS GENERATED, let's get it
*/
	if($response['response']['status'] == "success" ){
		$queue = $response['response']['queue_id'];
        $result = $this->ccApiOrderStatus($queue);


		/*  ADDRESS GRABBED, let's set it
         */
        if($result['status'] == "success" ){
			$this->data['checkoutcrypto_address'] = $result['address'];
			$this->db->query("UPDATE `" . DB_PREFIX . "order` SET checkoutcrypto_address = '" . $this->data['checkoutcrypto_address'] . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");
		}

        if(!(isset($this->data['checkoutcrypto_address']))) {
            $address = NULL;
            $count = 0;
            while($address == NULL AND $count < 25) {
                $count++;
                $result = $this->ccApiOrderStatus($queue);
                if(isset($result['address'])) {
                    $address = $result['address'];
                    $this->data['checkoutcrypto_address'] = $address;
                } else {
                    sleep(1);
                }
            }
        }

        if(isset($this->data['checkoutcrypto_address'])) {
            try {
                $this->db->query("UPDATE `" . DB_PREFIX . "order` SET checkoutcrypto_order_id = " . (int)$queue . ", checkoutcrypto_address = '" . $this->data['checkoutcrypto_address'] . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");
                $this->db->query("INSERT INTO `".DB_PREFIX."cc_orders` (`order_id`, `coin_code`, `coin_name`, `coin_rate`, `coin_paid`, `coin_address`, `order_status`, `cc_queue_id`, `cc_queue_id_tmp`) VALUES ($order_id, '$coincode', 'Potcoin', ".$this->data['checkoutcrypto_total'].", 0, '".$this->data['checkoutcrypto_address']."', 'pending', $queue, $queue)");
            } catch (exception $e) {
                //var_dump($e);
                break;
            }
        } else {
            $this->data['checkoutcrypto_address'] = 'Unknown error';
        }
	}
		 $this->data['error'] = false;
		
		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/checkoutcrypto.tpl')) {
			$this->template = $this->config->get('config_template') . '/template/payment/checkoutcrypto.tpl';
		} else {
			$this->template = 'default/template/payment/checkoutcrypto.tpl';
		}	
		
		$this->render();
	}


	function ccApiOrderStatus($queue_id) {

	$apikey = $this->config->get('checkoutcrypto_api_key');

    try {
        $ccApi = new CheckoutCryptoApi($apikey);
        $response = $ccApi->query(array('action' => 'getstatus','apikey' => $apikey, 'orderid' => $queue_id));
    } catch (exception $e) {
		//var_dump($e);
    }

        if(isset($response['response']['status'])) {
            $result['status'] = $response['response']['status'];
            if(isset($response['response']['address'])) {
                $result['address'] = $response['response']['address'];
            } else {
                $result['address'] = FALSE;
            }
            if(isset($response['response']['balance'])) {
                $result['balance'] = $response['response']['balance'];
            } else {
                $result['balance'] = FALSE;
            }
            $result['success'] = TRUE;
            return $result;
        } else {
            $result['success'] = FALSE;
           return $result;
        }
    }

    public function confirm_sent() {
        $coincode = 'POT';

        $this->load->model('checkout/order');
		$order_id = $this->session->data['order_id'];
        $order = $this->model_checkout_order->getOrder($order_id);
		$current_default_currency = $this->config->get('config_currency');	
		$checkoutcrypto_pot_decimal = $this->config->get('checkoutcrypto_pot_decimal');
        $apikey = $this->config->get('checkoutcrypto_api_key');
        $query = $this->db->query("SELECT cc_queue_id_tmp, coin_address, coin_rate FROM `" . DB_PREFIX . "cc_orders` WHERE order_id = ".(int)$order_id);

        $this->checkUpdate();

        $row = $query->row;
        $queue = $row['cc_queue_id_tmp'];
        $checkoutcrypto_total = sprintf("%.".$checkoutcrypto_pot_decimal."f", round((float)$row['coin_rate'], $checkoutcrypto_pot_decimal));

        $order['checkoutcrypto_address'] = $row['coin_address'];
        $checkoutcrypto_address = $order['checkoutcrypto_address'];

        if(!empty($queue)) {
            $response = $this->ccApiOrderStatus($queue);

            if(isset($response['status'])) {
                if($response['status']  == "success" ){
                    if(!empty($response['balance'])) { //looks like coin was received
                        $received_amount = $response['balance'];
                        //verify that balance reported from server matches local value
    
                        if(round((float)$received_amount,$checkoutcrypto_pot_decimal) >= round((float)$checkoutcrypto_total,$checkoutcrypto_pot_decimal)) {
                            $order = $this->model_checkout_order->getOrder($order_id);
                            $this->model_checkout_order->confirm($order_id, $this->config->get('checkoutcrypto_order_status_id'));
                            echo "1";
                        } else { //coin deposited but not enough
                            $retry = TRUE;
                            echo "0";
                        }
                    } else {
                        $retry = TRUE;
                        echo "0"; //we need to go for another ride on the ferris wheel
                    }
                } elseif ($response['status'] == 'pending') { //no coin deposited yet
                    $retry = TRUE;
                    echo "0";
                }
            } else {
                //General error, could not contact server or malformed response
            }
        } else { //queue is not set, getreceivedbyaddress and update tmp queue
            $retry = TRUE;
        }

        if(isset($retry) AND $retry === TRUE) {
            try {
                $ccApi = new CheckoutCryptoApi($apikey);
                $response = $ccApi->query(array('action' => 'getreceivedbyaddress','apikey' => $apikey, 'coin' => $coincode, 'address' => $checkoutcrypto_address, 'confirms' => '1', 'amount' => $checkoutcrypto_total));
            } catch (exception $e) {
                //var_dump($e);
            }

            if(isset($response['response']['orderid'])) {
                $new_queue = $response['response']['orderid'];

                try {
                    $this->db->query("UPDATE " . DB_PREFIX . "cc_orders SET cc_queue_id_tmp = ".(int)$new_queue." WHERE order_id = ".(int)$order_id);
                } catch (exception $e) {
                    //var_dump($e);
                }
            } else {
                //General error, malformed response
            }
        } else {
            //var_dump('NOPE!');
        }
    }
	
	public function checkUpdate() {
        $coincode = "POT";

			$data = array();
			try {
                $query = $this->db->query("SELECT coin_rate FROM " . DB_PREFIX . "cc_coins WHERE coin_code = 'POT'");
            } catch (exception $e) {
                //var_dump($e);
            }
            if(!$query->row) {
                $coin_code = 'POT';
                $coin_name = 'Potcoin';
                $coin_rate = 123;
                $coin_img = 'some/path/file.png';

				try {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "cc_coins (coin_code, coin_name, coin_rate, coin_img, date_added) VALUES ('".$coin_code."', '".$coin_name."', ".$coin_rate.", '".$coin_img."', NOW())");
                } catch (exception $e) {
                    //var_dump($e);
                }
            }
	
			$this->runUpdate();
	}
	
	public function runUpdate() {
		$coincode = 'POT';

	    $apikey = $this->config->get('checkoutcrypto_api_key');

        $ccApi = new CheckoutCryptoApi($apikey);
        $response = $ccApi->query(array('action' => 'getrate','apikey' => $apikey, 'coin' => $coincode)); 

        $value_usd = $response['response']['rates']['USD_'.$coincode];
        $value = (1 / $value_usd);

		if ((float)$value) {
            try {
                $this->db->query("UPDATE " . DB_PREFIX . "currency SET date_modified = '" .  $this->db->escape(date('Y-m-d H:i:s')) . "', value = " . (float)$value . " WHERE code = '" . $this->db->escape($coincode) . "'");
            } catch (exception $e) {
                //var_dump($e);
            }
		}
	}
}
?>
