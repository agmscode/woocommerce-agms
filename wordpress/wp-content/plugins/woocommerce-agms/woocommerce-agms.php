<?php
/*
Plugin Name: AGMS - WooCommerce Gateway
Plugin URI: http://www.onlinepaymentprocessing.com/
Description: Extends WooCommerce by Adding the AGMS Gateway.
Version: 1
Author: Maanas Royy, AGMS
Author URI: https://github.com/maanas
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Include our Gateway Class and Register Agms Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'agms_gateway_init', 0 );
function agms_gateway_init() {
    /* If the parent WC_Payment_Gateway class doesn't exist
     * it means WooCommerce is not installed on the site
     * so do nothing
     */
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

    // If we made it this far, then include our Gateway Class
    include_once( 'woocommerce-agms-transaction.php' );

    // Now that we have successfully included our class,
    // Lets add it too WooCommerce
    add_filter( 'woocommerce_payment_gateways', 'agms_transaction_gateway' );
    function agms_transaction_gateway( $methods ) {
        $methods[] = 'AgmsTransaction_Gateway';
        return $methods;
    }
}


// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'agms_gateways_action_links' );
function agms_gateways_action_links( $links ) {
    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'agms-transaction' ) . '</a>',
    );

    // Merge our new link with the default ones
    return array_merge( $plugin_links, $links );
}