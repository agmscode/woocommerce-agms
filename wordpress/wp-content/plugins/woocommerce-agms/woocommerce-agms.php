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

add_action('plugins_loaded', 'woocommerce_agms_init', 0);

function woocommerce_agms_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    // Include Agms Gateway Class
    include_once('woocommerce-agms-gateway.php');

    /**
     * Add Agms Gateway to WooCommerces list of Gateways
     */
    function woocommerce_add_agms_gateway($methods)
    {
        $methods[] = 'WC_Agms_Gateway';
        return $methods;
    }

    // Hooks
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_agms_gateway');
}

function agms_gateways_action_links($links)
{
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . __('Settings', 'agms-gateway') . '</a>',
    );

    // Merge our new link with the default ones
    return array_merge($plugin_links, $links);
}
// Add admin action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'agms_gateways_action_links');



