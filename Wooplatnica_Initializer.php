<?php defined('ABSPATH') or die('No script kiddies please!');

/**
 * Plugin Name: Croatian payment slip generator for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/croatian-payment-slip-generator-for-woocommerce/
 * Description: Make it easy for your customers from Croatia to perform direct bank transfer with generated payment slip, along with barcode for mBanking.
 * Requires PHP: 5.6
 * Version: 1.0
 * Text Domain: wooplatnica-croatia
 * Author: Marinela Levak
 * Author URI: https://github.com/marlevak
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

$plugin = new Wooplatnica_Initializer();

class Wooplatnica_Initializer {
    public function __construct() {
        add_action('plugins_loaded', [$this, 'init'], 0);
    }

    function init() {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }
        $module_classes = [
            'Wooplatnica'            => true,
            'Payment_Slip_Data'      => false,
            'WC_Gateway_Wooplatnica' => false,
        ];

        add_action( 'woocommerce_loaded', array( $this, 'load_plugin' ) );

        foreach ($module_classes as $module_class => $init) {
            require path_join(dirname(__FILE__), "src/$module_class.php");
            if ($init) {
                $$module_class = new $module_class();
            }
        }
    }
}
