<?php
/*
Copyright (c) 2013 John Atkinson (jga)
*/
require('cc.php');	


class ControllerPaymentCheckoutCrypto extends Controller {
    private $payment_module_name  = 'checkoutcrypto';
    protected function index() {
	    $this->load->model('setting/setting');
        $this->language->load('payment/'.$this->payment_module_name);
    	$this->data['button_checkoutcrypto_pay'] = $this->language->get('button_checkoutcrypto_pay');
    	$this->data['text_please_send'] = $this->language->get('text_please_send');
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
		
		$this->checkUpdate();
	
        $this->load->model('checkout/order');
		$order_id = $this->session->data['order_id'];
		$order = $this->model_checkout_order->getOrder($order_id);

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

    private function coin_exist($coin_code) {
        $coins = $this->order_coins(FALSE);
        $coin_exist = FALSE;

        foreach ($coins as $coin) {
            $db_coin = $coin['coin_code'];
            if($db_coin == $coin_code) {
                $coin_exist = TRUE;
            }
        }
        return $coin_exist;
    }


    public function order_details() {
        $coin_code = (string)strtoupper($_POST['coin_code']);

        $coin_exist = $this->coin_exist($coin_code);

        if(isset($coin_exist) AND $coin_exist === TRUE) {

            $order_id = $this->session->data['order_id'];
            $order = $this->model_checkout_order->getOrder($order_id);

            //Check if order exists
            try {
                $query = $this->db->query("SELECT coin_code, coin_rate, coin_address FROM `" . DB_PREFIX . "cc_orders` WHERE order_id = '". (int)$order_id."'");
                $order_exist = $query->row;
            } catch (exception $e) {
                var_dump($e);
            }

            $new_order = TRUE;
            if(isset($order_exist) AND $order_exist != NULL) {
                //Verify if we have changed coin
                if($order_exist['coin_code'] != $coin_code) {
                    $new_order = TRUE;

                    //delete old order entry
                    try {
                        $query = $this->db->query("DELETE FROM `" . DB_PREFIX . "cc_orders` WHERE order_id = '". (int)$order_id."'");
                    } catch (exception $e) {
                        //var_dump($e);
                    }
                } else {
                    $new_order = FALSE;
                    //Use cached data
                    $checkoutcrypto_total = $order_exist['coin_rate'];
                    $address = $order_exist['coin_address'];
                }
            }

            if($new_order != FALSE) {
                //Update order with coin total
                $current_default_currency = $this->config->get('config_currency');
                $checkoutcrypto_cc_decimal = $this->config->get('checkoutcrypto_cc_decimal');
                $checkoutcrypto_total = sprintf("%.".$checkoutcrypto_cc_decimal."f", round($this->currency->convert($order['total'], $current_default_currency, substr($coin_code,0 ,3)),$checkoutcrypto_cc_decimal));
                $this->db->query("UPDATE `" . DB_PREFIX . "order` SET checkoutcrypto_total = '" . $checkoutcrypto_total . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");

                //Query api for new address
                $apikey = $this->config->get('checkoutcrypto_api_key');
                $ccApi = new CheckoutCryptoApi($apikey);
                $response = $ccApi->query(array('action' => 'getnewaddress','apikey' => $apikey, 'coin' => $coin_code, 'amount'=> $checkoutcrypto_total));
        
                //Read response and check status of order
                if($response['response']['status'] == "success" ){
                    $queue = $response['response']['queue_id'];
                    $result = $this->ccApiOrderStatus($queue);

                    //Try to retrieve address up to 25 times
                    $address = NULL;
                    $count = 0;
                    while($address == NULL AND $count < 25) {
                        $count++;

                        if($queue != NULL) {
                            $result = $this->ccApiOrderStatus($queue);
                            if(isset($result['address'])) {
                                $address = $result['address'];
                            } else {
                                sleep(1);
                            }
                        }
                    }

                    if($address != NULL) {
    
                        //Update database with address
                        try {
                            $this->db->query("UPDATE `" . DB_PREFIX . "order` SET checkoutcrypto_order_id = " . (int)$queue . ", checkoutcrypto_address = '" . $address . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");
                            $this->db->query("INSERT INTO `".DB_PREFIX."cc_orders` (`order_id`, `coin_code`, `coin_name`, `coin_rate`, `coin_paid`, `coin_address`, `order_status`, `cc_queue_id`, `cc_queue_id_tmp`) VALUES ($order_id, '$coin_code', '$coin_code', ".$checkoutcrypto_total.", 0, '".$address."', 'pending', $queue, $queue)");
                        } catch (exception $e) {
                            //var_dump($e);
                        }
                        //Inform client of order details
                        $output = array();

                        $output['status'] = 'success';
                        $output['coin_address'] = $address;
                        $output['coin_amount'] = $checkoutcrypto_total;

                        echo json_encode($output);
                    }
                } else {
                    echo json_encode(array('status' => 'pending'));
                }
            } else {
                $output = array();
                $output['status'] = 'success';
                $output['coin_address'] = $address;
                $output['coin_amount'] = $checkoutcrypto_total;
                echo json_encode($output);
            }
        } else {
                echo json_encode(array('status' => 'failure', 'message' => 'Invalid coin'));
        }
    }

    public function order_coins($json = TRUE) {
        $this->load->model('checkout/order');
        $order_id = $this->session->data['order_id'];
        $order = $this->model_checkout_order->getOrder($order_id);
        $query = $this->db->query("SELECT coin_code, coin_name, coin_rate, coin_img FROM `" . DB_PREFIX . "cc_coins`");

        $coins = array();
        foreach ($query->rows as $coin) {
            $coin['coin_img'] = $coin['coin_img'];
            $coins[$coin['coin_code']] = $coin;
        }
        if($json === TRUE) {
            echo json_encode($coins);
        } else {
            return $coins;
        }
    }

    public function order_coins_display($json = TRUE) {
        $this->load->model('checkout/order');
        $order_id = $this->session->data['order_id'];
        $order = $this->model_checkout_order->getOrder($order_id);
        $query = $this->db->query("SELECT coin_code, coin_name, coin_rate, coin_img FROM `" . DB_PREFIX . "cc_coins`");
		$count = 0;
        $coins = array();
        foreach ($query->rows as $coin) {
            $coin['coin_img'] = $coin['coin_img'];
            $coins[$count] = $coin;
			$count = $count + 1;
        }
        if($json === TRUE) {
            echo json_encode($coins);
        } else {
            return $coins;
        }
    }

    public function confirm_sent() {

        $this->load->model('checkout/order');
		$order_id = $this->session->data['order_id'];
        $order = $this->model_checkout_order->getOrder($order_id);
		$current_default_currency = $this->config->get('config_currency');	
		$checkoutcrypto_cc_decimal = $this->config->get('checkoutcrypto_cc_decimal');
        $apikey = $this->config->get('checkoutcrypto_api_key');
        $query = $this->db->query("SELECT cc_queue_id_tmp, coin_address, coin_code, coin_rate FROM `" . DB_PREFIX . "cc_orders` WHERE order_id = ".(int)$order_id);

        $this->checkUpdate();

        $row = $query->row;
        $coin_code = $row['coin_code'];
        $queue = $row['cc_queue_id_tmp'];
        $checkoutcrypto_total = sprintf("%.".$checkoutcrypto_cc_decimal."f", round((float)$row['coin_rate'], $checkoutcrypto_cc_decimal));

        $order['checkoutcrypto_address'] = $row['coin_address'];
        $checkoutcrypto_address = $order['checkoutcrypto_address'];

        if(!empty($queue)) {
            $response = $this->ccApiOrderStatus($queue);

            if(isset($response['status'])) {
                if($response['status']  == "success" ){
                    if(!empty($response['balance'])) { //looks like coin was received
                        $received_amount = $response['balance'];
                        //verify that balance reported from server matches local value
    
                        if(round((float)$received_amount,$checkoutcrypto_cc_decimal) >= round((float)$checkoutcrypto_total,$checkoutcrypto_cc_decimal)) {
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
                $response = $ccApi->query(array('action' => 'getreceivedbyaddress','apikey' => $apikey, 'coin' => $coin_code, 'address' => $checkoutcrypto_address, 'confirms' => '1', 'amount' => $checkoutcrypto_total));
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

    private function ccApiExchangeRate($coin_code) {
        $apikey = $this->config->get('checkoutcrypto_api_key');

        $ccApi = new CheckoutCryptoApi($apikey);
        $response = $ccApi->query(array('action' => 'getrate','apikey' => $apikey, 'coin' => $coin_code));

        $value_usd = $response['response']['rates']['USD_'.$coin_code];
        $value = (1 / $value_usd);

        if ((float)$value) {
            return (float)$value;
        }
        return FALSE;   
    }
	
    public function checkUpdate() {
        //Get available coins
        $coins = $this->order_coins(FALSE);
        foreach ($coins as $coin) {
            //Check that all coins are in database
            $coin_code = $coin['coin_code'];
            try {
                $query = $this->db->query("SELECT value,date_modified FROM " . DB_PREFIX . "currency WHERE code = '" . $this->db->escape(substr($coin_code, 0, 3)) . "'");
            } catch (exception $e) {
                //var_dump($e);
            }
            //If coin does not exist insert it
            if(!isset($query->row['value'])) {
                try {
                    $checkoutcrypto_cc_decimal = $this->config->get('checkoutcrypto_cc_decimal');
                    $value = $this->ccApiExchangeRate($coin_code);
                    $this->db->query("INSERT INTO " . DB_PREFIX . "currency (title, code, symbol_right, decimal_place, value, status, date_modified) VALUES ('checkoutcrypto', '" . $this->db->escape(substr($coin_code, 0, 3)) . "' , '" . $this->db->escape($coin_code) . "', '" . $checkoutcrypto_cc_decimal . "', ". $value .", 1, NOW())");
                } catch (exception $e) {
                    //var_dump($e);
                }
            } else {
                //coin exists, check if we need to update it
                $date = (int)time();
                $db_date = (int)strtotime($query->row['date_modified']);
                //Refresh if data older than 15 min
                if(($date - $db_date) >= 900) {
                    $this->runUpdate($coin_code);
                }
            }
        }
	}
	
	public function runUpdate($coin_code) {
	    $apikey = $this->config->get('checkoutcrypto_api_key');

        $ccApi = new CheckoutCryptoApi($apikey);
        $response = $ccApi->query(array('action' => 'getrate','apikey' => $apikey, 'coin' => $coin_code)); 

        //Get the value when compared to USD and convert it to a format opencart likes
        $value_usd = $response['response']['rates']['USD_'.$coin_code];
        $value = (1 / $value_usd);

		if ((float)$value) {
            try {
                $this->db->query("UPDATE " . DB_PREFIX . "currency SET date_modified = '" .  $this->db->escape(date('Y-m-d H:i:s')) . "', value = " . (float)$value . " WHERE code = '" . $this->db->escape(substr($coin_code, 0, 3)) . "'");
            } catch (exception $e) {
                //var_dump($e);
            }
		}
	}
}
?>
