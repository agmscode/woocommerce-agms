<?php
/*
Plugin Name: WooCommerce Agms Gateway
Plugin URI: http://www.onlinepaymentprocessing.com/
Description: Extends WooCommerce by Adding the AGMS Payment Gateway.
Version: 0.1.0
Author: Maanas Royy, AGMS
Author URI: https://github.com/maanas
License: MIT
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


class WC_Agms
{
    public function __construct()
    {
        // Hooks
        add_filter( 'woocommerce_payment_gateways', array( $this, 'add_agms_gateway' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'agms_gateways_action_links' ) );
        // Add Action for return handler
        add_action( 'woocommerce_api_wc_agms', array( $this, 'return_handler' ) );


    }

    /**
     * Add Agms Gateway to WooCommerces list of Gateways
     *
     * @access      public
     * @param       array $methods
     * @return      array
     */
    public function add_agms_gateway( $methods ) {
        if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
            return;
        }
        // Include Agms Gateway Class
        include_once('woocommerce-agms-gateway.php');

        $methods[] = 'WC_Agms_Gateway';
        return $methods;
    }

    // Add admin action links
    public function agms_gateways_action_links( $links )
    {
        $plugin_links = array(
            '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'agms-transaction' ) . '</a>',
        );

        // Merge our new link with the default ones
        return array_merge( $plugin_links, $links );
    }

    /**
     * Return handler for Hosted Payments
     */
    public function return_handler() {
        @ob_clean();
        header( 'HTTP/1.1 200 OK' );
        print "hello";
        var_dump($_POST);
//        if ( ( $r['response_code'] == 1 ) ) {
//            // Payment has been successful
//            $customer_order->add_order_note( __( 'Agms Gateway payment completed.', 'agms_gateway' ) );
//
//            // Mark order as Paid
//            $customer_order->payment_complete();
//
//            // Empty the cart (Very important step)
//            $woocommerce->cart->empty_cart();
//
//            // Redirect to thank you page
//            return array(
//                'result'   => 'success',
//                'redirect' => $this->get_return_url( $customer_order ),
//            );
//        } else {
//            // Transaction was not succesful
//            // Add notice to the cart
//            wc_add_notice( $r['response_reason_text'], 'error' );
//            // Add note to the order for your reference
//            $customer_order->add_order_note( 'Error: '. $r['response_reason_text'] );
//        }
    }
}

$GLOBALS['WC_Agms'] = new WC_Agms();



