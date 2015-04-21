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

}

$GLOBALS['WC_Agms'] = new WC_Agms();



