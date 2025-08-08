<?php
/*
Plugin Name: M1Pay Block Gateway
Description: M1Pay - Custom WooCommerce payment gateway for Checkout Block.
Version: 1.0
Author: Amalina Nusy for M1Pay - MobilityOne Sdn Bhd
Author URI : https://www.m1pay.com.my/
*/

if (!defined('ABSPATH')) exit;

define('M1PAY_GATEWAY_PATH', plugin_dir_path(__FILE__));
define('M1PAY_KEY_DIR', wp_upload_dir()['basedir'] . '/m1pay-keys/');

add_filter('woocommerce_payment_gateways', function($methods) {
    $methods[] = 'WC_Gateway_M1Pay';
    return $methods;
});

add_action('plugins_loaded', function () {
    if (!class_exists('WC_Payment_Gateway')) return;

    require_once __DIR__ . '/includes/class-wc-gateway-m1pay.php';

    // Daftar integrasi Blocks
    add_action('woocommerce_blocks_loaded', function () {
        if (class_exists(\Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry::class)) {
            require_once __DIR__ . '/includes/blocks/class-wc-gateway-m1pay-block.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function ($registry) { $registry->register(new WC_Gateway_M1Pay_Blocks()); }
            );
        }
    });
});
