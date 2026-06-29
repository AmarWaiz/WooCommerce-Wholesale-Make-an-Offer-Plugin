<?php
/**
 * Plugin Name:       WooCommerce Wholesale & Make an Offer
 * Plugin URI:        https://example.com/wc-wholesale-offers
 * Description:        Role-based wholesale registration & pricing plus a real-time "Make an Offer" price negotiation workflow for WooCommerce.
 * Version:           1.0.0
 * Author:            Amar Waiz
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wc-wholesale-offers
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 * WC tested up to:   9.0
 *
 * @package WC_Wholesale_Offers
 */

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Core constants.
 */
define( 'WWO_VERSION', '1.0.0' );
define( 'WWO_PLUGIN_FILE', __FILE__ );
define( 'WWO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WWO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WWO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Versioning helper for assets. Uses the file's modification time so that any
 * edit to a CSS/JS file automatically busts the browser cache (no manual
 * version bumps needed during development).
 *
 * @param string $relative Path relative to the plugin root, e.g. 'assets/js/wwo-public.js'.
 * @return string
 */
function wwo_asset_ver( $relative ) {
	$path = WWO_PLUGIN_DIR . ltrim( $relative, '/\\' );
	if ( file_exists( $path ) ) {
		return WWO_VERSION . '.' . filemtime( $path );
	}
	return WWO_VERSION;
}

/**
 * Bootstrap the plugin once all plugins are loaded so we can verify WooCommerce.
 */
function wwo_bootstrap() {
	// Hard dependency: WooCommerce must be active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wwo_missing_woocommerce_notice' );
		return;
	}

	require_once WWO_PLUGIN_DIR . 'includes/class-wwo-plugin.php';
	WWO_Plugin::instance();
}
add_action( 'plugins_loaded', 'wwo_bootstrap' );

/**
 * Admin notice shown when WooCommerce is not available.
 */
function wwo_missing_woocommerce_notice() {
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'WooCommerce Wholesale & Make an Offer requires WooCommerce to be installed and active.', 'wc-wholesale-offers' );
	echo '</p></div>';
}

/**
 * Declare HPOS (High-Performance Order Storage) compatibility.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Activation: create tables, register roles, schedule cron, flush rewrites.
 */
function wwo_activate() {
	require_once WWO_PLUGIN_DIR . 'includes/class-wwo-activator.php';
	WWO_Activator::activate();
}
register_activation_hook( __FILE__, 'wwo_activate' );

/**
 * Deactivation: clear scheduled events and flush rewrites.
 */
function wwo_deactivate() {
	require_once WWO_PLUGIN_DIR . 'includes/class-wwo-deactivator.php';
	WWO_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'wwo_deactivate' );
