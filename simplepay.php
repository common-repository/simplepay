<?php
/**
 * Plugin Name:     SimplePay
 * Plugin URI:      https://simplepay.cl/plugins/woocommerce
 * Description:     Acepta Chauchas como medio de pago en tu comercio.
 * Author:          SimplePay Chile
 * Author URI:      https://simplepay.cl
 * Text Domain:     simplepay
 * Domain Path:     /languages
 * Version:         0.1.4
 *
 * @package         SimplePay
 */
if (!defined('ABSPATH')) {
    exit;
}

require __DIR__ . '/vendor/autoload.php';

class SimplePayPlugin {

    protected $gateway = 'WC_SimplePay_Gateway';
    /**
     * @var Singleton The reference the *Singleton* instance of this class
     */
    private static $instance;

    /**
     * Returns the *Singleton* instance of this class.
     *
     * @return Singleton The *Singleton* instance.
     */
    public static function getInstance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Protected constructor to prevent creating a new instance of the
     * *Singleton* via the `new` operator from outside of this class.
     */
    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
    }

    public function init()
    {
        require_once dirname( __FILE__ ) . '/includes/WC_SimplePay_Gateway.php';

        add_filter( 'woocommerce_payment_gateways', function($methods) {
            $methods[] = $this->gateway;
            return $methods;
        });
        
        add_filter( 'woocommerce_currencies', function ( $currencies ) {
            $currencies['CHA'] = __( 'Chauchas', 'woocommerce' );
            return $currencies;
        });

        add_filter('woocommerce_currency_symbol', function($currency_symbol, $currency) {
            switch( $currency ) {
                case 'CHA': $currency_symbol = 'CHA'; break;
            }
            return $currency_symbol;
        }, 10, 2);

    }



}

SimplePayPlugin::getInstance();
