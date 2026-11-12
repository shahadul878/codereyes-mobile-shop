<?php
/**
 * Plugin Name:       Codereyes Mobile Shop
 * Description:       Run a mobile phone shop online and in store: IMEI stock, point of sale with receipts, repair job tickets with customer status tracking, customers, and WooCommerce stock sync.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            H M Shahadul Islam
 * Author URI:        https://github.com/shahadul878
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       codereyes-mobile-shop
 * WC tested up to:   11.0
 *
 * @package CodereyesMobileShop
 */

defined( 'ABSPATH' ) || exit;

define( 'CMSHOP_VERSION', '1.0.0' );
define( 'CMSHOP_FILE', __FILE__ );
define( 'CMSHOP_DIR', plugin_dir_path( __FILE__ ) );
define( 'CMSHOP_URL', plugin_dir_url( __FILE__ ) );

require_once CMSHOP_DIR . 'includes/class-cmshop-plugin.php';

register_activation_hook( __FILE__, array( 'Cmshop_Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'Cmshop_Plugin', 'boot' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', CMSHOP_FILE, true );
		}
	}
);
