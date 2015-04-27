<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/*
 * Agms Methods for Gateway
 */
include_once('agms.php');

/**
 * Agms Gateway
 *
 * @class 		WC_Agms_Gateway
 * @extends		WC_Payment_Gateway
 * @version		0.2.0
 * @package		WooCommerce/Classes/Payment
 * @author      Maanas Royy
 */

class WC_Agms_Gateway extends WC_Payment_Gateway {
    /*
     * Constructor
     * Setup our Gateway's id, description and other values
     */
    function __construct() {
        global $woocommerce;

        // The global ID for this Payment method
        $this->id = "agms_gateway";

        // The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
        $this->method_title = __( "Agms Gateway", 'agms_gateway' );

        // The description for this Payment Gateway, shown on the actual Payment options page on the backend
        $this->method_description = __( "Agms Payment Gateway Plug-in for WooCommerce", 'agms_gateway');

        // The title to be used for the vertical tabs that can be ordered top to bottom
        $this->title = __( "Agms Gateway", 'agms_gateway' );

        // If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
        $this->icon = null;

        // Bool. Can be set to true if you want payment fields to show on the checkout
        // if doing a direct integration, which we are doing in this case
        $this->has_fields = true;

        // Supports the default credit card form
        $this->supports = array('default_credit_card_form');

        // This basically defines your settings which are then loaded with init_settings()
        $this->init_form_fields();

        // After init_settings() is called, you can get the settings and load them into variables, e.g:
        // $this->title = $this->get_option( 'title' );
        $this->init_settings();

        // Turn these settings into variables we can use
        foreach ( $this->settings as $setting_key => $value ) {
            $this->$setting_key = $value;
        }

        // Lets check for SSL
        // add_action( 'admin_notices', array( $this,  'do_ssl_check' ) );

        add_action('agms_init', array( $this, 'agms_successful_request'));
        // Add Action for return handle (update for woocommerce >2.0)
        add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( &$this, 'check_agms_response' ) );

        // Save settings
        if ( is_admin() ) {
            // Versions over 2.0
            // Save our administration options. Since we are not going to be doing anything special
            // we have not defined 'process_admin_options' in this class so the method in the parent
            // class will be used instead
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            }
            else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            }
        }
    } // End __construct()

    // Build the administration fields for this specific Gateway
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'     => __( 'Enable / Disable', 'agms_gateway' ),
                'label'     => __( 'Enable this payment gateway', 'agms_gateway' ),
                'type'      => 'checkbox',
                'default'   => 'no',
            ),
            'mode' => array(
                'title'     => __( 'Gateway Mode', 'agms_gateway' ),
                'label'     => __( 'Select Gateway Mode', 'agms_gateway' ),
                'type'      => 'select',
                'desc_tip' => __('Select the Gateway Mode'),
                'default'   => 'direct',
                'options'   => array('direct' => 'Direct Mode', 'form' => 'Form Mode')
            ),
            'title' => array(
                'title'     => __( 'Title', 'agms_gateway' ),
                'type'      => 'text',
                'desc_tip'  => __( 'Payment title the customer will see during the checkout process.', 'agms_gateway' ),
                'default'   => __( 'Credit card', 'agms_gateway' ),
            ),
            'description' => array(
                'title'     => __( 'Description', 'agms_gateway' ),
                'type'      => 'textarea',
                'desc_tip'  => __( 'Payment description the customer will see during the checkout process.', 'agms_gateway' ),
                'default'   => __( 'Pay securely using your credit card.', 'agms_gateway' ),
                'css'       => 'max-width:350px;'
            ),
            'username' => array(
                'title'     => __( 'Agms Gateway Username', 'agms_gateway' ),
                'type'      => 'text',
                'desc_tip'  => __( 'This is the Username provided by AGMS when you signed up for an account.', 'agms_gateway' ),
            ),
            'password' => array(
                'title'     => __( 'Agms Gateway Password', 'agms_gateway' ),
                'type'      => 'password',
                'desc_tip'  => __( 'This is the Password provided by AGMS when you signed up for an account.', 'agms_gateway' ),
            ),
            'account' => array(
                'title'     => __( 'Agms Gateway Account', 'agms_gateway' ),
                'type'      => 'text',
                'desc_tip'  => __( 'This is the Account Number provided by AGMS when you signed up for an account.', 'agms_gateway' ),
            ),
            'api_key' => array(
                'title'     => __( 'Agms Gateway API Key', 'agms_gateway' ),
                'type'      => 'text',
                'desc_tip'  => __( 'This is the API Key provided by AGMS when you signed up for an account.', 'agms_gateway' ),
            ),
        );
    } // End init_form_fields()

    /*
     * Payment form on checkout
     */
    public function payment_fields(){
        if($this->mode == 'direct'){
            $this->credit_card_form(array( 'fields_have_names' => false ));
        }
    }
    // Generic function to trigger action
    public function process_payment($order_id){
        if ($this->mode == 'form') {
            $this->has_fields = false;
            return $this->process_form_payment($order_id);
        }
        elseif ($this->mode == 'direct') {
            return $this->process_standard_payment($order_id);
        }
        else {
            return $this->process_standard_payment($order_id);
        }
    }

    // Submit payment and handle form payment response
    private function process_form_payment( $order_id ) {
        global $woocommerce;

        // Get this Order's information so that we know
        // who to charge and how much
        $customer_order = new WC_Order( $order_id );

        //  URL to post to
        $environment_url = 'https://gateway.agms.com/roxapi/AGMS_HostedPayment.asmx';

        // Agms Gateway Payload
        $payload = array(
            // Agms Gateway Credentials and API Info
            "GatewayUserName"     => $this->username,
            "GatewayPassword"     => $this->password,

            // Order total
            "Amount"              => $customer_order->order_total,

            "TransactionType"     => 'sale',
            "InvoiceID"           => str_replace( "#", "", $customer_order->get_order_number() ),

            // Billing Information
            "FirstName"             => $customer_order->billing_first_name,
            "LastName"              => $customer_order->billing_last_name,
            "Address1"              => $customer_order->billing_address_1,
            "City"                  => $customer_order->billing_city,
            "State"                 => $customer_order->billing_state,
            "Zip"                   => $customer_order->billing_postcode,
            "Country"               => $customer_order->billing_country,
            "Phone"                 => $customer_order->billing_phone,
            "EMail"                 => $customer_order->billing_email,

            // Shipping Information
            "ShippingFirstName"     => $customer_order->shipping_first_name,
            "ShippingLastName"      => $customer_order->shipping_last_name,
            "ShippingCompany"       => $customer_order->shipping_company,
            "ShippingAddress1"      => $customer_order->shipping_address_1,
            "ShippingCity"          => $customer_order->shipping_city,
            "ShippingCountry"       => $customer_order->shipping_country,
            "ShippingState"         => $customer_order->shipping_state,
            "ShippingZip"           => $customer_order->shipping_postcode,

            // Some Customer Information
            "OrderID"             => $customer_order->user_id,
            "IPAddress"         => $_SERVER['REMOTE_ADDR'],

            // Default Values
            "UsageCount"        => 1,
            "HPPFormat"         => 1,
            "RetURL"            => WC()->api_request_url( 'WC_Agms_Gateway' )
        );

        // Send this payload to Agms Gateway for processing
        $response = wp_remote_post( $environment_url, array(
                'method'    => 'POST',
                'body'      => Agms::buildRequestBody($payload, 'ReturnHostedPaymentSetup'),
                'timeout'   => 90,
                'sslverify' => true,
                'headers'   => Agms::buildRequestHeader('ReturnHostedPaymentSetup')
            )
        );
        if ( is_wp_error( $response ) )
            throw new Exception( __( 'We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'agms_gateway' ) );

        if ( empty( $response['body'] ) )
            throw new Exception( __( 'Agms Gateway\'s Response was empty.', 'agms_gateway' ) );


        // Retrieve the body's response if no errors found
        $response_body = wp_remote_retrieve_body( $response );
        // Parse the response into something we can read
        $hash = Agms::parseResponse($response_body, 'ReturnHostedPaymentSetup');

        return array(
            'result'   => 'success',
            'redirect' => 'https://gateway.agms.com/HostedPaymentForm/HostedPaymentPage.aspx?hash=' . $hash
        );

    }

    /**
     * Response handler for Hosted Payments
     */
    public function check_agms_response() {
        @ob_clean();
        if ( ! empty( $_REQUEST ) ) {
            header( 'HTTP/1.1 200 OK' );
            do_action( "agms_init", $_REQUEST );
        } else {
            wp_die( __("Agms Request Failure", 'agms_gateway') );
        }
    }

    /**
     * Process Agms Response and update the order information
     *
     * @access public
     * @param array $posted
     * @return void
     */
    function agms_successful_request( $posted ) {
        global $woocommerce;
        // Custom holds post ID
        if ( ! empty( $posted['referenceCode'] ) && ! empty( $posted['order_id'] ) ) {

            $order = $this->get_payulatam_order( $posted );

            if ( 'yes' == $this->debug )
                $this->log->add( 'payulatam', 'Found order #' . $order->id );

            // Lowercase returned variables
            //$posted['lapTransactionState'] 	= ( $posted['lapTransactionState'] );
            //$posted['lapPaymentMethodType'] = ( $posted['lapPaymentMethodType'] );

            // Sandbox fix
            if ( $posted['authorizationCode'] == '123456' && $posted['lapTransactionState'] == 'PENDING' )
                $posted['lapTransactionState'] = 'APPROVED';

            if ( 'yes' == $this->debug )
                $this->log->add( 'payulatam', 'Payment status: ' . $posted['lapTransactionState'] );
            if (!empty($posted['lapTransactionState'])) {
                // We are here so lets check status and do actions
                switch ( $posted['lapTransactionState'] ) {
                    case 'APPROVED' :
                    case 'PENDING' :
                    case 'PENDING_TRANSACTION_CONFIRMATION' :

                        // Check order not already completed
                        if ( $order->status == 'completed' ) {
                            if ( 'yes' == $this->debug )
                                $this->log->add( 'payulatam', 'Aborting, Order #' . $order->id . ' is already complete.' );
                            exit;
                        }

                        // Validate Amount
                        if ( $order->get_total() != $posted['TX_VALUE'] ) {

                            if ( 'yes' == $this->debug )
                                $this->log->add( 'payulatam', __('Payment error: Amounts do not match (Amount ' . $posted['TX_VALUE'] . ')', 'payu-latam-woocommerce') );

                            // Put this order on-hold for manual checking
                            $order->update_status( 'on-hold', sprintf( __( 'Validation error: PayU Latam amounts do not match (gross %s).', 'payu-latam-woocommerce'), $posted['TX_VALUE'] ) );

                            $this->msg['message'] = sprintf( __( 'Validation error: PayU Latam amounts do not match (gross %s).', 'payu-latam-woocommerce'), $posted['TX_VALUE'] );
                            $this->msg['class'] = 'woocommerce-error';

                            exit;
                        }

                        // Validate Merchand id
                        if ( strcasecmp( trim( $posted['merchantId'] ), trim( $this->merchant_id ) ) != 0 ) {
                            if ( 'yes' == $this->debug )
                                $this->log->add( 'payulatam', __("Payment was made to another merchantId: {$posted['merchantId']} our merchantId is {$this->merchant_id }", 'payu-latam-woocommerce') );

                            // Put this order on-hold for manual checking
                            $order->update_status( 'on-hold', sprintf( __( 'Validation error: Payment in PayU Latam comes from another id (%s).', 'payu-latam-woocommerce'), $posted['merchantId'] ) );
                            $this->msg['message'] = sprintf( __( 'Validation error: Payment in PayU Latam comes from another id (%s).', 'payu-latam-woocommerce'), $posted['merchantId'] );
                            $this->msg['class'] = 'woocommerce-error';

                            exit;
                        }

                        // Store PP Details
                        if ( ! empty( $posted['buyerEmail'] ) )
                            update_post_meta( $order->id, __('Payer PayU Latam email', 'payu-latam-woocommerce'), $posted['buyerEmail'] );
                        if ( ! empty( $posted['transactionId'] ) )
                            update_post_meta( $order->id, __('Transaction ID', 'payu-latam-woocommerce'), $posted['transactionId'] );
                        if ( ! empty( $posted['trazabilityCode'] ) )
                            update_post_meta( $order->id, __('Trasability Code', 'payu-latam-woocommerce'), $posted['trazabilityCode'] );
                        /*if ( ! empty( $posted['last_name'] ) )
                            update_post_meta( $order->id, 'Payer last name', $posted['last_name'] );*/
                        if ( ! empty( $posted['lapPaymentMethodType'] ) )
                            update_post_meta( $order->id, __('Payment type', 'payu-latam-woocommerce'), $posted['lapPaymentMethodType'] );

                        if ( $posted['lapTransactionState'] == 'APPROVED' ) {
                            $order->add_order_note( __( 'PayU Latam payment approved', 'payu-latam-woocommerce') );
                            $this->msg['message'] =  $this->msg_approved;
                            $this->msg['class'] = 'woocommerce-message';
                            $order->payment_complete();
                            $woocommerce->cart->empty_cart();

                        } else {
                            $order->update_status( 'on-hold', sprintf( __( 'Payment pending: %s', 'payu-latam-woocommerce'), $posted['lapResponseCode'] ) );
                            $this->msg['message'] = $this->msg_pending;
                            $this->msg['class'] = 'woocommerce-info';
                        }

                        if ( 'yes' == $this->debug )
                            $this->log->add( 'payulatam', __('Payment complete.', 'payu-latam-woocommerce') );

                        break;
                    case 'DECLINED' :
                    case 'ANTIFRAUD_REJECTED' :
                    case 'INSUFFICIENT_FUNDS' :
                    case 'PAYMENT_NETWORK_REJECTED' :
                    case 'INTERNAL_PAYMENT_PROVIDER_ERROR' :
                    case 'ERROR' :
                    case 'ENTITY_DECLINED' :
                    case 'ENTITY_MESSAGING_ERROR' :
                    case 'NOT_ACCEPTED_TRANSACTION' :
                    case 'BANK_UNREACHABLE' :
                    case 'INVALID_CARD' :
                    case 'INVALID_TRANSACTION' :
                    case 'EXPIRED_CARD' :
                    case 'RESTRICTED_CARD' :
                    case 'ABANDONED_TRANSACTION':
                    case 'INTERNAL_PAYMENT_PROVIDER_ERROR' :
                    case 'INACTIVE_PAYMENT_PROVIDER' :
                    case 'DIGITAL_CERTIFICATE_NOT_FOUND' :
                    case 'INVALID_EXPIRATION_DATE_OR_SECURITY_CODE' :
                    case 'INSUFFICIENT_FUNDS' :
                    case 'CREDIT_CARD_NOT_AUTHORIZED_FOR_INTERNET_TRANSACTIONS' :
                        // Order failed
                        $order->update_status( 'failed', sprintf( __( 'Payment rejected via PayU Latam. Error type: %s', 'payu-latam-woocommerce'), ( $posted['lapTransactionState'] ) ) );
                        $this->msg['message'] = $this->msg_declined ;
                        $this->msg['class'] = 'woocommerce-error';
                        break;
                    default :
                        $order->update_status( 'failed', sprintf( __( 'Payment rejected via PayU Latam. Error type: %s', 'payu-latam-woocommerce'), ( $posted['lapTransactionState'] ) ) );
                        $this->msg['message'] = $this->msg_cancel ;
                        $this->msg['class'] = 'woocommerce-error';
                        break;
                }

            } else if(!empty($posted['transactionState'])) {
                $codes=array('1' => 'CAPTURING_DATA' ,'2' => 'NEW' ,'101' => 'FX_CONVERTED' ,'102' => 'VERIFIED' ,'103' => 'SUBMITTED' ,'4' => 'APPROVED' ,'6' => 'DECLINED' ,'104' => 'ERROR' ,'7' => 'PENDING' ,'5' => 'EXPIRED'  );
                $state=$posted['transactionState'];
                // We are here so lets check status and do actions
                switch ( $codes[$state] ) {
                    case 'APPROVED' :
                    case 'PENDING' :
                    case 'NEW' :
                    case 'FX_CONVERTED' :
                    case 'VERIFIED' :
                    case 'SUBMITTED' :
                    case 'CAPTURING_DATA' :

                        // Check order not already completed
                        if ( $order->status == 'completed' ) {
                            if ( 'yes' == $this->debug )
                                $this->log->add( 'payulatam', __('Aborting, Order #' . $order->id . ' is already complete.', 'payu-latam-woocommerce') );
                            exit;
                        }

                        // Validate Amount
                        if ( $order->get_total() != $posted['TX_VALUE'] ) {

                            if ( 'yes' == $this->debug )
                                $this->log->add( 'payulatam', __('Payment error: Amounts do not match (amount ' . $posted['TX_VALUE'] . ')', 'payu-latam-woocommerce') );

                            // Put this order on-hold for manual checking
                            $order->update_status( 'on-hold', sprintf( __( 'Validation error: PayU Latam amounts do not match (gross %s).', 'payu-latam-woocommerce'), $posted['TX_VALUE'] ) );

                            $this->msg['message'] = sprintf( __( 'Validation error: PayU Latam amounts do not match (gross %s).', 'payu-latam-woocommerce'), $posted['TX_VALUE'] );
                            $this->msg['class'] = 'woocommerce-error';

                            exit;
                        }

                        // Validate Merchand id
                        if ( strcasecmp( trim( $posted['merchantId'] ), trim( $this->merchant_id ) ) != 0 ) {
                            if ( 'yes' == $this->debug )
                                $this->log->add( 'payulatam', __("Payment was made to another merchantId: {$posted['merchantId']} our merchantId is {$this->merchant_id }", 'payu-latam-woocommerce') );

                            // Put this order on-hold for manual checking
                            $order->update_status( 'on-hold', sprintf( __( 'Validation error: Payment in PayU Latam comes from another id (%s).', 'payu-latam-woocommerce'), $posted['merchantId'] ) );
                            $this->msg['message'] = sprintf( __( 'Validation error: Payment in PayU Latam comes from another id (%s).', 'payu-latam-woocommerce'), $posted['merchantId'] );
                            $this->msg['class'] = 'woocommerce-error';

                            exit;
                        }

                        // Store PP Details
                        if ( ! empty( $posted['buyerEmail'] ) )
                            update_post_meta( $order->id, __('Payer PayU Latam email', 'payu-latam-woocommerce'), $posted['buyerEmail'] );
                        if ( ! empty( $posted['transactionId'] ) )
                            update_post_meta( $order->id, __('Transaction ID', 'payu-latam-woocommerce'), $posted['transactionId'] );
                        if ( ! empty( $posted['trazabilityCode'] ) )
                            update_post_meta( $order->id, __('Trasability Code', 'payu-latam-woocommerce'), $posted['trazabilityCode'] );
                        /*if ( ! empty( $posted['last_name'] ) )
                            update_post_meta( $order->id, 'Payer last name', $posted['last_name'] );*/
                        if ( ! empty( $posted['lapPaymentMethodType'] ) )
                            update_post_meta( $order->id, __('Payment type', 'payu-latam-woocommerce'), $posted['lapPaymentMethodType'] );

                        if ( $codes[$state] == 'APPROVED' ) {
                            $order->add_order_note( __( 'PayU Latam payment approved', 'payu-latam-woocommerce') );
                            $this->msg['message'] = $this->msg_approved;
                            $this->msg['class'] = 'woocommerce-message';
                            $order->payment_complete();
                        } else {
                            $order->update_status( 'on-hold', sprintf( __( 'Payment pending: %s', 'payu-latam-woocommerce'), $codes[$state] ) );
                            $this->msg['message'] = $this->msg_pending;
                            $this->msg['class'] = 'woocommerce-info';
                        }

                        if ( 'yes' == $this->debug )
                            $this->log->add( 'payulatam', __('Payment complete.', 'payu-latam-woocommerce'));

                        break;
                    case 'DECLINED' :
                    case 'EXPIRED' :
                    case 'ABANDONED_TRANSACTION':
                    case 'ERROR' :
                        // Order failed
                        $order->update_status( 'failed', sprintf( __( 'Payment rejected via PayU Latam. Error type: %s', 'payu-latam-woocommerce'), ( $codes[$state] ) ) );
                        $this->msg['message'] = $this->msg_declined ;
                        $this->msg['class'] = 'woocommerce-error';
                        break;
                    default :
                        $order->update_status( 'failed', sprintf( __( 'Payment rejected via PayU Latam.', 'payu-latam-woocommerce'), ( $codes[$state] ) ) );
                        $this->msg['message'] = $this->msg_cancel ;
                        $this->msg['class'] = 'woocommerce-error';
                        break;
                }
            }

            $redirect_url = ($this->redirect_page_id=="" || $this->redirect_page_id==0)?get_site_url() . "/":get_permalink($this->redirect_page_id);
            //For wooCoomerce 2.0
            $redirect_url = add_query_arg( array('msg'=> urlencode($this->msg['message']), 'type'=>$this->msg['class']), $redirect_url );

            wp_redirect( $redirect_url );
            exit;
        }
        //confirmation process
        if ( ! empty( $posted['state_pol'] ) && ! empty( $posted['order_id'] ) ) {
            $order = $this->get_payulatam_order( $posted );

            if ( 'yes' == $this->debug )
                $this->log->add( 'payulatam', 'Found order #' . $order->id );

            if(!empty($posted['state_pol'])) {
                $codes=array('1' => 'CAPTURING_DATA' ,'2' => 'NEW' ,'101' => 'FX_CONVERTED' ,'102' => 'VERIFIED' ,'103' => 'SUBMITTED' ,'4' => 'APPROVED' ,'6' => 'DECLINED' ,'104' => 'ERROR' ,'7' => 'PENDING' ,'5' => 'EXPIRED'  );
                $state=$posted['state_pol'];

                if ( 'yes' == $this->debug )
                    $this->log->add( 'payulatam', 'Payment status: ' . $codes[$state] );

                // We are here so lets check status and do actions
                switch ( $codes[$state] ) {
                    case 'APPROVED' :
                    case 'PENDING' :
                    case 'NEW' :
                    case 'FX_CONVERTED' :
                    case 'VERIFIED' :
                    case 'SUBMITTED' :
                    case 'CAPTURING_DATA' :

                        // Check order not already completed
                        if ( $order->status == 'completed' ) {
                            if ( 'yes' == $this->debug )
                                $this->log->add( 'payulatam', __('Aborting, Order #' . $order->id . ' is already complete.', 'payu-latam-woocommerce') );
                            exit;
                        }

                        // Validate Amount
                        if ( $order->get_total() != $posted['value'] ) {

                            if ( 'yes' == $this->debug )
                                $this->log->add( 'payulatam', __('Payment error: Amounts do not match (amount ' . $posted['value'] . ')', 'payu-latam-woocommerce') );

                            // Put this order on-hold for manual checking
                            $order->update_status( 'on-hold', sprintf( __( 'Validation error: PayU Latam amounts do not match (gross %s).', 'payu-latam-woocommerce'), $posted['value'] ) );

                            $this->msg['message'] = sprintf( __( 'Validation error: PayU Latam amounts do not match (gross %s).', 'payu-latam-woocommerce'), $posted['value'] );
                            $this->msg['class'] = 'woocommerce-error';

                            exit;
                        }

                        // Validate Merchand id
                        if ( strcasecmp( trim( $posted['merchant_id'] ), trim( $this->merchant_id ) ) != 0 ) {
                            if ( 'yes' == $this->debug )
                                $this->log->add( 'payulatam', __("Payment was made to another merchantId: {$posted['merchant_id']} our merchantId is {$this->merchant_id }", 'payu-latam-woocommerce') );

                            // Put this order on-hold for manual checking
                            $order->update_status( 'on-hold', sprintf( __( 'Validation error: Payment in PayU Latam comes from another id (%s).', 'payu-latam-woocommerce'), $posted['merchant_id'] ) );
                            $this->msg['message'] = sprintf( __( 'Validation error: Payment in PayU Latam comes from another id (%s).', 'payu-latam-woocommerce'), $posted['merchant_id'] );
                            $this->msg['class'] = 'woocommerce-error';

                            exit;
                        }

                        // Store PP Details
                        if ( ! empty( $posted['email_buyer'] ) )
                            update_post_meta( $order->id, __('PayU Latam Client email', 'payu-latam-woocommerce'), $posted['email_buyer'] );
                        if ( ! empty( $posted['transaction_id'] ) )
                            update_post_meta( $order->id, __('Transaction ID', 'payu-latam-woocommerce'), $posted['transaction_id'] );
                        if ( ! empty( $posted['reference_pol'] ) )
                            update_post_meta( $order->id, __('Trasability Code', 'payu-latam-woocommerce'), $posted['reference_pol'] );
                        if ( ! empty( $posted['sign'] ) )
                            update_post_meta( $order->id, __('Tash Code', 'payu-latam-woocommerce'), $posted['sign'] );
                        if ( ! empty( $posted['ip'] ) )
                            update_post_meta( $order->id, __('Transaction IP', 'payu-latam-woocommerce'), $posted['ip'] );

                        update_post_meta( $order->id, __('Extra Data', 'payu-latam-woocommerce'), 'response_code_pol: '.$posted['response_code_pol'].' - '.'state_pol: '.$posted['state_pol'].' - '.'payment_method: '.$posted['payment_method'].' - '.'transaction_date: '.$posted['transaction_date'].' - '.'currency: '.$posted['currency'] );
                        /*if ( ! empty( $posted['last_name'] ) )
                            update_post_meta( $order->id, 'Payer last name', $posted['last_name'] );*/
                        if ( ! empty( $posted['payment_method_type'] ) )
                            update_post_meta( $order->id, __('Payment type', 'payu-latam-woocommerce'), $posted['payment_method_type'] );

                        if ( $codes[$state] == 'APPROVED' ) {
                            $order->add_order_note( __( 'PayU Latam payment approved', 'payu-latam-woocommerce') );
                            $this->msg['message'] =  $this->msg_approved;
                            $this->msg['class'] = 'woocommerce-message';
                            $order->payment_complete();
                        } else {
                            $order->update_status( 'on-hold', sprintf( __( 'Payment pending: %s', 'payu-latam-woocommerce'), $codes[$state] ) );
                            $this->msg['message'] = $this->msg_pending;
                            $this->msg['class'] = 'woocommerce-info';
                        }

                        if ( 'yes' == $this->debug )
                            $this->log->add( 'payulatam', __('Payment complete.', 'payu-latam-woocommerce'));

                        break;
                    case 'DECLINED' :
                    case 'EXPIRED' :
                    case 'ERROR' :
                    case 'ABANDONED_TRANSACTION':
                        // Order failed
                        $order->update_status( 'failed', sprintf( __( 'Payment rejected via PayU Latam. Error type: %s', 'payu-latam-woocommerce'), ( $codes[$state] ) ) );
                        $this->msg['message'] = $this->msg_declined ;
                        $this->msg['class'] = 'woocommerce-error';
                        break;
                    default :
                        $order->update_status( 'failed', sprintf( __( 'Payment rejected via PayU Latam.', 'payu-latam-woocommerce'), ( $codes[$state] ) ) );
                        $this->msg['message'] = $this->msg_cancel ;
                        $this->msg['class'] = 'woocommerce-error';
                        break;
                }
            }

            $redirect_url = ($this->redirect_page_id=="" || $this->redirect_page_id==0)?get_site_url() . "/":get_permalink($this->redirect_page_id);
            //For wooCoomerce 2.0
            $redirect_url = add_query_arg( array('msg'=> urlencode($this->msg['message']), 'type'=>$this->msg['class']), $redirect_url );

            //wp_redirect( $redirect_url );
            exit;
        }
    }

    // Submit payment and handle standard payment response
    private function process_standard_payment( $order_id ) {
        global $woocommerce;

        // Get this Order's information so that we know
        // who to charge and how much
        $customer_order = new WC_Order( $order_id );

        //  URL to post to
        $environment_url = 'https://gateway.agms.com/roxapi/agms.asmx';

        // Agms Gateway Payload
        $payload = array(
            // Agms Gateway Credentials and API Info
            "GatewayUserName"     => $this->username,
            "GatewayPassword"     => $this->password,

            // Order total
            "Amount"              => $customer_order->order_total,

            // Credit Card Information
            "CCNumber"            => str_replace( array(' ', '-' ), '', $_POST['agms_gateway-card-number'] ),
            "CVV"                 => ( isset( $_POST['agms_gateway-card-cvc'] ) ) ? $_POST['agms_gateway-card-cvc'] : '',
            "CCExpDate"           => str_replace( array( '/', ' '), '', $_POST['agms_gateway-card-expiry'] ),

            "TransactionType"     => 'sale',
            "InvoiceID"           => str_replace( "#", "", $customer_order->get_order_number() ),

            // Billing Information
            "FirstName"             => $customer_order->billing_first_name,
            "LastName"              => $customer_order->billing_last_name,
            "Address1"              => $customer_order->billing_address_1,
            "City"                  => $customer_order->billing_city,
            "State"                 => $customer_order->billing_state,
            "Zip"                   => $customer_order->billing_postcode,
            "Country"               => $customer_order->billing_country,
            "Phone"                 => $customer_order->billing_phone,
            "EMail"                 => $customer_order->billing_email,

            // Shipping Information
            "ShippingFirstName"     => $customer_order->shipping_first_name,
            "ShippingLastName"      => $customer_order->shipping_last_name,
            "ShippingCompany"       => $customer_order->shipping_company,
            "ShippingAddress1"      => $customer_order->shipping_address_1,
            "ShippingCity"          => $customer_order->shipping_city,
            "ShippingCountry"       => $customer_order->shipping_country,
            "ShippingState"         => $customer_order->shipping_state,
            "ShippingZip"           => $customer_order->shipping_postcode,

            // Some Customer Information
            "OrderID"             => $customer_order->user_id,
            "IPAddress"         => $_SERVER['REMOTE_ADDR'],

        );


        // Send this payload to Agms Gateway for processing
        $response = wp_remote_post( $environment_url, array(
            'method'    => 'POST',
            'body'      => Agms::buildRequestBody($payload, 'ProcessTransaction'),
            'timeout'   => 90,
            'sslverify' => true,
            'headers'   => Agms::buildRequestHeader('ProcessTransaction')
            )
        );

        if ( is_wp_error( $response ) )
            throw new Exception( __( 'We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'agms_gateway' ) );

        if ( empty( $response['body'] ) )
            throw new Exception( __( 'Agms Gateway\'s Response was empty.', 'agms_gateway' ) );


        // Retrieve the body's resopnse if no errors found
        $response_body = wp_remote_retrieve_body( $response );

        // Parse the response into something we can read
        $resp = Agms::parseResponse($response_body, 'ProcessTransaction');

        // Get the values we need
        $r['response_code']             = $resp['STATUS_CODE'];
        $r['response_reason_code']      = $resp["TRANSACTION_ID"];
        $r['response_reason_text']      = $resp["STATUS_MSG"];

        // Test the code to know if the transaction went through or not.
        // 1 means the transaction was a success
        if ( ( $r['response_code'] == 1 ) ) {
            // Payment has been successful
            $customer_order->add_order_note( __( 'Agms Gateway payment completed.', 'agms_gateway' ) );

            // Mark order as Paid
            $customer_order->payment_complete();

            // Empty the cart (Very important step)
            $woocommerce->cart->empty_cart();

            // Redirect to thank you page
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url( $customer_order ),
            );
        } else {
            // Transaction was not succesful
            // Add notice to the cart
            wc_add_notice( $r['response_reason_text'], 'error' );
            // Add note to the order for your reference
            $customer_order->add_order_note( 'Error: '. $r['response_reason_text'] );
        }

    }


}