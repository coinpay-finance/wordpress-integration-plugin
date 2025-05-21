<?php
/*
Plugin Name: CoinPay WooCommerce Gateway
Description: افزونه درگاه پرداخت رمزارزی CoinPay برای ووکامرس
Version: 1.0.0
Author: Saeed
*/

if (!defined('ABSPATH')) exit;

add_action('plugins_loaded', 'coinpay_init_gateway_class');

function coinpay_init_gateway_class() {
    if (!class_exists('WC_Payment_Gateway')) return;

    require_once plugin_dir_path(__FILE__) . 'class-wc-gateway-coinpay.php';

    add_filter('woocommerce_payment_gateways', function($methods) {
        $methods[] = 'WC_Gateway_CoinPay';
        return $methods;
    });
}
