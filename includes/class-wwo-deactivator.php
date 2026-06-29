<?php
/**
 * Fired during plugin deactivation.
 *
 * @package WC_Wholesale_Offers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cleans up scheduled events on deactivation. Tables and roles are preserved
 * unless the plugin is fully uninstalled (see uninstall.php).
 */
class WWO_Deactivator {

	/**
	 * Run deactivation routines.
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'wwo_expire_offers_event' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'wwo_expire_offers_event' );
		}

		flush_rewrite_rules();
	}
}
