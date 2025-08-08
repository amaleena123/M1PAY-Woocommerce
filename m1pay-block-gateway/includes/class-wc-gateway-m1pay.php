<?php
if (!defined('ABSPATH')) exit;

class WC_Gateway_M1Pay extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'm1pay';
        $this->method_title = 'M1Pay';
        $this->method_description = 'M1Pay Payment Gateway';
        $this->has_fields = false;
        $this->supports = ['products'];

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->enabled = $this->get_option('enabled');

	////
	$this->merchant_id = $this->get_option('merchant_id');
        $this->client_id = $this->get_option('client_id');
        $this->client_secret = $this->get_option('client_secret');
        $this->environment = $this->get_option('environment');
        $this->private_key_path = get_option('woocommerce_m1pay_private_key_path');
        $this->public_key_path = get_option('woocommerce_m1pay_public_key_path');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
	    add_action('admin_init', [$this, 'handle_file_upload']);

        add_action('woocommerce_api_m1pay_response', [$this, 'handle_frontend_response']);
        add_action('woocommerce_api_m1pay_callback', [$this, 'handle_backend_response']);
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable M1Pay Payment Gateway',
                'default' => 'yes'
            ],
            'title' => [
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'Title shown at checkout.',
                'default'     => 'M1Pay',
                'desc_tip'    => true,
            ],
	    ////
	    'merchant_id' => ['title' => 'Merchant ID', 'type' => 'text'],
            'client_id' => ['title' => 'Client ID', 'type' => 'text'],
            'client_secret' => ['title' => 'Client Secret', 'type' => 'password'],
            'environment' => [
                'title' => 'M1Pay Environment',
                'type' => 'select',
                'options' => ['sandbox' => 'Sandbox', 'production' => 'Production'],
                'default' => 'sandbox',
            ],
            'private_key_file' => [
                'title' => 'Private Key (.key)',
                'type' => 'file',
                'description' => 'Upload file private key. Retreive the file from Merchant Portal',
            ],
            'public_key_file' => [
                'title' => 'Public Key (.crt)',
                'type' => 'file',
                'description' => 'Upload file public key. Retrive the file from Merchant Portal',
            ],

        ];
    }

    public function handle_file_upload() {
        if (!empty($_FILES['woocommerce_m1pay_private_key_file']['tmp_name'])) {
            $upload_dir = M1PAY_KEY_DIR;
            if (!file_exists($upload_dir)) {
                wp_mkdir_p($upload_dir);
            }

            $merchant_id = $this->get_option('merchant_id');
            $filename = sanitize_file_name($merchant_id) . '.key';
            $destination = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['woocommerce_m1pay_private_key_file']['tmp_name'], $destination)) {
                update_option('woocommerce_m1pay_private_key_path', $destination);
            }
        }

        if (!empty($_FILES['woocommerce_m1pay_public_key_file']['tmp_name'])) {
            $upload_dir = M1PAY_KEY_DIR;
            if (!file_exists($upload_dir)) {
                wp_mkdir_p($upload_dir);
            }

            $filename = 'public.crt';
            $destination = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['woocommerce_m1pay_public_key_file']['tmp_name'], $destination)) {
                update_option('woocommerce_m1pay_public_key_path', $destination);
            }
        }
    }

    public function is_available() {
        return true;
    }

    private function get_keycloak_url() {
        return ($this->environment === 'production')
            ? 'https://keycloak.m1pay.com.my/auth/realms/m1pay-users/protocol/openid-connect/token'
            : 'https://keycloak.m1pay.com.my/auth/realms/master/protocol/openid-connect/token';
    }

    private function get_monepay_url() {
        return ($this->environment === 'production')
            ? 'https://gateway.m1pay.com.my/wall/api/transaction'
            : 'https://gateway.m1payall.com/m1paywall/api/transaction';
    }

    private function get_transaction_details($transaction_id, $access_token) {
        $env = $this->environment === 'production' ? 'PROD' : 'UAT';

        $url = $env === 'UAT'
            ? "https://gateway.m1payall.com/m1paywall/api/m-1-pay-transactions/$transaction_id"
            : "https://gateway.m1pay.com.my/wall/api/m-1-pay-transactions/$transaction_id";

        $this->debugmone('info',"GET TXN URL:".$url);

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) return null;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body;
    }

    private function strToHex($string) {
        $hex = '';
        for ($i=0; $i<strlen($string); $i++){
                $ord = ord($string[$i]);
                $hexCode = dechex($ord);
                $hex .= substr('0'.$hexCode, -2);
        }
        return strToUpper($hex);
    }

    private function hexToStr($hex){
        $string='';
        for ($i=0; $i < strlen($hex)-1; $i+=2){
                $string .= chr(hexdec($hex[$i].$hex[$i+1]));
        }
        return $string;
    }

    private function get_keycloak_token() {

        $response = wp_remote_post($this->get_keycloak_url() , [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
            ],
        ]);

        if (is_wp_error($response)) {
            error_log('Keycloak token request failed: ' . $response->get_error_message());
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data['access_token'] ?? null;
    }


    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $amount = number_format($order->get_total(), 2, '.', '');
        $currency = get_woocommerce_currency();
        $email = $order->get_billing_email();
        $phone = $order->get_billing_phone();
        $orderId = $order->get_id();
        $desc = 'WC Order ' . $orderId;

        $order->update_status('on-hold', 'Awaiting M1Pay payment');

        $payloadm1pay = [
            'merchantId' => $this->client_id,
            'transactionAmount' => $amount,
            'transactionCurrency' => $currency,
            'merchantOrderNo' => $orderId,
            'emailAddress' => $email,
            'phoneNumber' => $phone,
            'productDescription' => $desc,
            //'channel' => '', //if open with respective value, need to show payment channel at checkour. for now leave it empty
            //'fpxBank' => '', //list of bank when select channel = ONLINE_BANKING. for now leave it empty
            'exchangeOrderNo' => $orderId,
            'skipConfirmation' => 'false' //set true if want skip M1Pay Confirmation page
        ];

        $raw_data = $payloadm1pay['productDescription'].'|'
                .$payloadm1pay['transactionAmount'].'|'
                .$payloadm1pay['exchangeOrderNo'].'|'
                .$payloadm1pay['merchantOrderNo'].'|'
                .$payloadm1pay['transactionCurrency'].'|'
                .$payloadm1pay['emailAddress'].'|'
                .$payloadm1pay['merchantId'];

        $signature = '';
        if (file_exists($this->private_key_path)) {
            $private_key = file_get_contents($this->private_key_path);

            $pkeyid = openssl_get_privatekey($private_key);
            openssl_sign($raw_data, $signed, $pkeyid, "sha1WithRSAEncryption");
            $signature = $this->strToHex($signed);
        }

        $payloadm1pay['signedData'] = $signature;

        $token = $this->get_keycloak_token();

        // 1.Get tne M1Pay URL for cURL
        $payment_url = $this->get_monepay_url();

        //2. cURL
        $response = wp_remote_post($payment_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payloadm1pay)
        ]);

        $redirect = trim(wp_remote_retrieve_body($response));

	$this->debugmone('debug','M1Pay Pay URL:'.$redirect);

        return [
            'result' => 'success',
            'redirect' => $redirect
        ];
    }

    public function handle_frontend_response() {
        $transaction_id = sanitize_text_field($_GET['transactionId'] ?? '');

        if (empty($transaction_id)) {
            wc_add_notice('Missing transaction ID', 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        $access_token = $this->get_keycloak_token();

        if (!$access_token) {
            wc_add_notice('Unable to authenticate with M1Pay', 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        $transaction_data = $this->get_transaction_details($transaction_id, $access_token);

        if (!$transaction_data || empty($transaction_data['merchantOrderNo'])) {
            wc_add_notice('Transaction not found.', 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        $m1pay_mer_orderid = $transaction_data['merchantOrderNo'];
        $txn_status = $transaction_data['transactionStatus']; //M1Pay Txn Status

        $this->debugmone('info',"TXN DATA:".print_r($transaction_data, true));

        $order = $this->get_wc_order_info($m1pay_mer_orderid, "front");
        $get_order_status = $order->get_status(); //WC Order Status
        if ($txn_status === "APPROVED" || $txn_status === "CAPTURED" || $txn_status === "SUCCESSFUL" || $txn_status === "COMPLETED" ){
            if(!in_array($get_order_status,array('processing','completed'))) {
                $order->payment_complete($txn_id);
                wc_add_notice('Payment successful', 'success');
            }
        } else {
            $order->update_status('failed', 'Payment failed via M1Pay');
            wc_add_notice('Payment failed', 'error');
        }

        wp_redirect($this->get_return_url($order));
        exit;

    }

    public function handle_backend_response(){

        //the incoming data from M1Pay - POST method
        $m_txnAmount       = sanitize_text_field($_POST['transactionAmount'] ?? '');
        $m_fpxTxnId        = sanitize_text_field($_POST['fpxTxnId'] ?? '');
        $m_sellerOrderNo   = sanitize_text_field($_POST['sellerOrderNo'] ?? '');
        $m_status          = sanitize_text_field($_POST['status'] ?? '');
        $m_merchantOrderNo = sanitize_text_field($_POST['merchantOrderNo'] ?? '');
        $m_description     = sanitize_text_field($_POST['description'] ?? '');
        $m_signedData      = isset($_POST['signedData']) ? trim($_POST['signedData']) : '';

        //verify data from M1Pay
        $raw_data = $m_txnAmount
                ."|".$m_fpxTxnId
                ."|".$m_sellerOrderNo
                ."|".$m_status
                ."|".$m_merchantOrderNo;

        $signature = $this->hexToStr($m_signedData);

        $this->debugmone('debug', 'RAW:'.$raw_data);

        $match = false;
        if ( file_exists( $this->public_key_path ) ) {
            // Read public key file
            $pub_key = file_get_contents( $this->public_key_path );

            if ( $pub_key === false ) {
                $this->debugmone('error', 'Unable to read public key file.');
                $match = false;
            }

            // Get the resource of  public key
            $pubkeyid = openssl_pkey_get_public( $pub_key );

            if ( $pubkeyid === false ) {
                $this->debugmone('error','Invalid public key format.');
                $match = false;
            }

            // Verify signature
            $r = openssl_verify( $raw_data, $signature, $pubkeyid, "sha1WithRSAEncryption" );

            if ( $r === 1 ) {
                $this->debugmone('info','Signature verification success.');
                $match =  true;
            } elseif ( $r === 0 ) {
                $this->debugmone('error','Signature verification failed.');
                $match = false;
            } else {
                $this->debugmone('error','Signature verification error: ' . openssl_error_string());
                $match =  false;
            }
        } else {
            $this->debugmone('error','Public key file not found: ' . $this->public_key_path);
            $match = false;
        }

        if($match){ //the "match" is true

            $order = $this->get_wc_order_info($m_merchantOrderNo, "back");

            //Get current order status
            $get_order_status = $order->get_status();

            if(!in_array($get_order_status,array('processing','completed'))) {
                $order->add_order_note('M1Pay Payment Status: '.$m_status);
                if ($m_status === "APPROVED" || $m_status === "CAPTURED" || $m_status === "SUCCESSFUL" || $m_status === "COMPLETED" ) {
                    $order->payment_complete($m_sellerOrderNo);
                    $order->add_order_note( '(CB) M1Pay Transaction ID: ' . $m_sellerOrderNo); //CB - Callback
                } else {
                    $order->update_status($m_status, sprintf(__('Payment %s via M1Pay.', 'woocommerce')));
                }
            }

        }
    }

    public function get_wc_order_info($order_id, $end){

        $order = wc_get_order($order_id);
        if (!$order) {
           wc_add_notice('Order not found.', 'error');
           if ($end == "front"){
               wp_redirect(wc_get_checkout_url());
           }
           exit;
        }
        return $order;
    }

    protected function debugmone($type, $text){
        $logger = wc_get_logger();
        $context = [ 'source' => 'm1pay' ]; // Optional: helps filter logs

        if($type == 'debug'){
                // Log an info message
                $logger->debug( $text, $context );
        }

        if($type == 'info'){
                // Log an info message
                $logger->info( $text, $context );
        }

        if($type == 'error'){
                // Log an info message
                $logger->error( $text, $context );
        }
   }

}
