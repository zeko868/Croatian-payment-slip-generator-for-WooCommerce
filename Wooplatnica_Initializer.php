<?php defined('ABSPATH') or die('No script kiddies please!');

/**
 * Plugin Name: Croatian payment slip generator for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/croatian-payment-slip-generator-for-woocommerce/
 * Description: Make it easy for your customers from Croatia to perform direct bank transfer with generated payment slip, along with barcode for mBanking.
 * Requires PHP: 5.6
 * Version: 1.0
 * Text Domain: croatian-payment-slip-generator-for-woocommerce
 * Author: Marinela Levak
 * Author URI: https://github.com/marlevak
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

$plugin = new Wooplatnica_Initializer();

class Wooplatnica_Initializer {

    /**
     * @var string
     */
    static $plugin_id = 'wooplatnica-croatia';


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
        add_filter( 'plugin_action_links', array( $this, 'add_settings_link' ), 10, 2 );
        register_uninstall_hook(__FILE__, array( get_class($this), 'delete_plugin_data' ) );

        foreach ($module_classes as $module_class => $init) {
            require path_join(dirname(__FILE__), "src/$module_class.php");
            if ($init) {
                $$module_class = new $module_class();
            }
        }
    }

    function add_settings_link($links, $file) {
        if ($file === plugin_basename(__FILE__)) {
            $links[] = '<a href="' . get_admin_url( null, 'admin.php?page=wc-settings&tab=checkout&section=' . self::$plugin_id ) . '">' . __( 'Settings' ) . '</a>';
        }
        return $links;
    }

    static function delete_plugin_data() {
        $plugin_id = self::$plugin_id;
        $stored_payment_slips_tmp_directory = path_join(sys_get_temp_dir(), "$plugin_id-data");
        if (file_exists($stored_payment_slips_tmp_directory)) {
            self::delete_recursively($stored_payment_slips_tmp_directory);
        }
        
        /*
         * Only remove ALL plugin settings data if WC_REMOVE_ALL_DATA constant is set to true in user's
         * wp-config.php. This is to prevent data loss when deleting the plugin from the backend
         * and to ensure only the site owner can perform this action.
         */
        if ( defined( 'WC_REMOVE_ALL_DATA' ) && true === WC_REMOVE_ALL_DATA ) {
            // Delete options.
            delete_option( "woocommerce_{$plugin_id}_settings" );
        }
    }

    private static function delete_recursively($dir_path) {
        $file_iterator = new FilesystemIterator($dir_path);
        foreach ($file_iterator as $file_info) {
            $file_path = $file_info->getPathname();
            if (is_dir($file_path)) {
                self::delete_recursively($file_path);
            }
            else {
                unlink($file_path);
            }
        }
        return rmdir($dir_path);
    }
}
