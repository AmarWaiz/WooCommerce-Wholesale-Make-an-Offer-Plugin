<?php
/**
 * Uninstall routine. Removes plugin data when the user deletes the plugin.
 *
 * @package WC_Wholesale_Offers
 */

// Exit if accessed directly or not invoked by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Remove custom tables.
$tables = array(
	$wpdb->prefix . 'wwo_offers',
	$wpdb->prefix . 'wwo_offer_history',
);
foreach ( $tables as $table ) {
	// Table name cannot be parameterised; it is built from a trusted prefix.
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
}

// Remove options.
$options = array(
	'wwo_db_version',
	'wwo_color_primary',
	'wwo_color_secondary',
	'wwo_color_light',
	'wwo_color_accent',
	'wwo_max_rounds',
	'wwo_accept_expiry_hrs',
	'wwo_rate_limit_count',
	'wwo_rate_limit_window',
	'wwo_notify_admin_email',
	'wwo_auto_approve',
	'wwo_login_page_id',
	'wwo_rewrite_version',
);
foreach ( $options as $option ) {
	delete_option( $option );
}

// Remove the custom role.
remove_role( 'wholesale_customer' );

// Clear scheduled events.
wp_clear_scheduled_hook( 'wwo_expire_offers_event' );
