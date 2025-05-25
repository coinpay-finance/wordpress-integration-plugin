<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Coinpay_Gateway_Blocks_Support extends AbstractPaymentMethodType {

    private $gateway;

    protected $name = 'WC_COINPAY';

    public function initialize() {
        $this->settings = get_option( "woocommerce_{$this->name}_settings", array() );

        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway  = $gateways[ $this->name ];
    }

    public function is_active() {
        return ! empty( $this->settings[ 'enabled' ] ) && 'yes' === $this->settings[ 'enabled' ];
    }

    public function get_payment_method_script_handles() {

        wp_register_script(
            'wc-coinpay-blocks-integration',
            plugin_dir_url( __DIR__ ) . 'assets/js/index.js',
            array(
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ),
            null,
            true
        );

        return array( 'wc-coinpay-blocks-integration' );

    }

    public function get_payment_method_data() {
        return array(
            'title'        => $this->get_setting( 'title' ),
            'description'  => $this->get_setting( 'description' ),
            'icon'         => plugin_dir_url( __DIR__ ) . 'assets/images/logo.svg',
        );
    }
}