<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Functions for interfacing with Stripe's API
 *
 * @class       AgmsTransaction
 * @version     0.1.0
 * @author      Maanas Royy
 */

class AgmsTransaction_Gateway extends WC_Payment_Gateway {
    // Setup our Gateway's id, description and other values
    function __construct() {

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
        $this->supports = array( 'default_credit_card_form' );

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
        add_action( 'admin_notices', array( $this,  'do_ssl_check' ) );

        // Save settings
        if ( is_admin() ) {
            // Versions over 2.0
            // Save our administration options. Since we are not going to be doing anything special
            // we have not defined 'process_admin_options' in this class so the method in the parent
            // class will be used instead
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
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

    // Submit payment and handle response
    public function process_payment( $order_id ) {
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
            'body'      => $this->buildPayload($payload),
            'timeout'   => 90,
            'sslverify' => false,
            'headers'   => array(
                "Accept" => "application/xml",
                "Content-type" => "text/xml; charset=utf-8",
                "SOAPAction" => "https://gateway.agms.com/roxapi/ProcessTransaction"
            )
        ) );

        if ( is_wp_error( $response ) )
            throw new Exception( __( 'We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'agms_gateway' ) );

        if ( empty( $response['body'] ) )
            throw new Exception( __( 'Agms Gateway\'s Response was empty.', 'agms_gateway' ) );


        // Retrieve the body's resopnse if no errors found
        $response_body = wp_remote_retrieve_body( $response );

        // Parse the response into something we can read
        $resp = $this->parseResponse($response_body);

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


    // Check if we are forcing SSL on checkout pages
    // Custom function not required by the Gateway
    public function do_ssl_check() {
        if( $this->enabled == "yes" ) {
            if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
                echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";  
            }
        }       
    }


    // Build payload in xml format
    private function buildPayload($data, $op='ProcessTransaction'){
        $xmlHeader = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
xmlns:xsd="http://www.w3.org/2001/XMLSchema"
xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <ProcessTransaction xmlns="https://gateway.agms.com/roxapi/">
      <objparameters>';
        $xmlFooter = '</objparameters>
    </ProcessTransaction>
  </soap:Body>
</soap:Envelope>';
        $xmlBody = '';
        foreach ($data as $key => $value) {
            $xmlBody = $xmlBody . "<$key>$value</$key>";
        }
        $payload = $xmlHeader . $xmlBody . $xmlFooter;
        return $payload;
    }

    // Parse Response from xml to object
    private function parseResponse($data, $op='ProcessTransaction'){
        $xml = new \SimpleXMLElement($data);
        $xml = $xml->xpath('/soap:Envelope/soap:Body');
        $xml = $xml[0];
        $data = json_decode(json_encode($xml));
        $opResponse = $op . 'Response';
        $opResult = $op . 'Result';
        $arr = $this->object2array($data->$opResponse->$opResult);
        return $arr;
    }

    /**
     * Convert object to array
     *
     * @return array
     */
    private function object2array($data)
    {
        if (is_array($data) || is_object($data)) {
            $result = array();
            foreach ($data as $key => $value) {
                $result[$key] = $this->object2array($value);
            }
            return $result;
        }
        return $data;
    }
}