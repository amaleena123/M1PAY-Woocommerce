<?php
// includes/blocks/class-wc-gateway-m1pay-blocks.php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Gateway_M1Pay_Blocks extends AbstractPaymentMethodType {
    public const PAYMENT_METHOD_NAME = 'm1pay';

    protected $name = self::PAYMENT_METHOD_NAME;

    public function initialize() {
        $gateways = WC()->payment_gateways()->payment_gateways();
        $this->gateway = $gateways[ self::PAYMENT_METHOD_NAME ] ?? null;
    }

    public function is_active() : bool {
        return $this->gateway && 'yes' === $this->gateway->enabled;
    }

    public function get_payment_method_script_handles() {
        $asset_path = plugin_dir_path(__FILE__) . '../../build/index.asset.php';
        $asset      = file_exists($asset_path) ? include $asset_path : [
            'dependencies' => ['wp-element','wc-blocks-registry','wc-settings'],
            'version' => '1.0.0'
        ];

        wp_register_script(
            'm1pay-blocks',
            plugins_url('../../build/index.js', __FILE__),
            $asset['dependencies'],
            $asset['version'],
            true
        );
        return ['m1pay-blocks'];
    }

    public function get_payment_method_data() {
        return [
            'title'       => $this->gateway->title ?? 'M1Pay',
            'description' => $this->gateway->description ?? '',
        ];
    }
}
