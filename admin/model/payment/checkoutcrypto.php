<?php
class ModelPaymentCheckoutCrypto extends Model {

     public function install() {
        $this->db->query("CREATE TABLE ".DB_PREFIX."cc_coins (
          `id` int(11) NOT NULL AUTO_INCREMENT,
			`coin_code` varchar(10) NOT NULL,
			`coin_name` varchar(50) NOT NULL,
			`coin_rate` decimal(30,8) NOT NULL DEFAULT 0.00000000,
			`coin_img` varchar(250) NOT NULL,
			`cc_balance` decimal(30,8) NOT NULL DEFAULT 0.00000000,
          `date_added` datetime NOT NULL,
          PRIMARY KEY (`id`)
        )");

        $this->db->query("CREATE TABLE ".DB_PREFIX."cc_orders (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `order_id` int(11) NOT NULL,
            `coin_code` varchar(10) NOT NULL,
            `coin_name` varchar(50) NOT NULL,
            `coin_rate` decimal(30,8) NOT NULL DEFAULT 0.00000000,
            `coin_paid` varchar(250) NOT NULL,
            `coin_address`varchar(250) DEFAULT NULL,
            `order_status` varchar(250) NOT NULL,
            `cc_queue_id` varchar(250) NOT NULL,
            `cc_queue_id_tmp` varchar(250) NOT NULL,
            `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        )");

     }

     public function uninstall() {
         $this->db->query("DROP TABLE ".DB_PREFIX."cc_orders");
     }


}

?>
