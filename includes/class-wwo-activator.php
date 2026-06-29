<?php
/**
 * Fired during plugin activation.
 *
 * @package WC_Wholesale_Offers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles table creation, role registration and scheduling.
 */
class WWO_Activator {

	/**
	 * Run activation routines.
	 */
	public static function activate() {
		self::create_tables();
		self::register_roles();
		self::schedule_events();

		// Store the plugin version for future upgrade routines.
		update_option( 'wwo_db_version', WWO_VERSION );

		// Set sensible default options if not present.
		self::set_default_options();

		// Create a front-end login/registration page with the shortcode.
		self::create_login_page();

		// Rewrite rules for the My Account offers endpoint.
		add_rewrite_endpoint( 'wwo-offers', EP_ROOT | EP_PAGES );
		flush_rewrite_rules();
	}

	/**
	 * Create the login/registration page if it does not already exist.
	 */
	public static function create_login_page() {
		$existing = (int) get_option( 'wwo_login_page_id' );
		if ( $existing && 'publish' === get_post_status( $existing ) ) {
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Account Access', 'wc-wholesale-offers' ),
				'post_name'    => 'account-access',
				'post_content' => '[wwo_login_register]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( 'wwo_login_page_id', (int) $page_id );
		}
	}

	/**
	 * Create custom database tables for offers and their negotiation history.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$offers          = $wpdb->prefix . 'wwo_offers';
		$history         = $wpdb->prefix . 'wwo_offer_history';

		// Main offers table. One row per active negotiation thread.
		$sql_offers = "CREATE TABLE {$offers} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			variation_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			original_price DECIMAL(19,4) NOT NULL DEFAULT 0,
			current_price DECIMAL(19,4) NOT NULL DEFAULT 0,
			agreed_price DECIMAL(19,4) DEFAULT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			turn VARCHAR(10) NOT NULL DEFAULT 'admin',
			round_count SMALLINT(5) UNSIGNED NOT NULL DEFAULT 1,
			expires_at DATETIME DEFAULT NULL,
			used TINYINT(1) NOT NULL DEFAULT 0,
			order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY product_id (product_id),
			KEY status (status),
			KEY user_product (user_id, product_id, variation_id)
		) {$charset_collate};";

		// History/audit table. One row per action in a thread.
		$sql_history = "CREATE TABLE {$history} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			offer_id BIGINT(20) UNSIGNED NOT NULL,
			actor VARCHAR(10) NOT NULL DEFAULT 'customer',
			action VARCHAR(20) NOT NULL,
			price DECIMAL(19,4) DEFAULT NULL,
			note TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY offer_id (offer_id)
		) {$charset_collate};";

		dbDelta( $sql_offers );
		dbDelta( $sql_history );
	}

	/**
	 * Register the wholesale customer role and add capabilities.
	 */
	public static function register_roles() {
		require_once WWO_PLUGIN_DIR . 'includes/class-wwo-roles.php';
		WWO_Roles::add_roles();
	}

	/**
	 * Schedule the recurring cron used to expire stale offers/accepted prices.
	 */
	public static function schedule_events() {
		if ( ! wp_next_scheduled( 'wwo_expire_offers_event' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'wwo_expire_offers_event' );
		}
	}

	/**
	 * Seed default options (colors, behaviour).
	 */
	public static function set_default_options() {
		$defaults = array(
			'wwo_color_primary'      => '#332A28',
			'wwo_color_secondary'    => '#F0D1AD',
			'wwo_color_light'        => '#FFFFFF',
			'wwo_color_accent'       => '#050909',
			'wwo_max_rounds'         => 3,
			'wwo_accept_expiry_hrs'  => 48,
			'wwo_rate_limit_count'   => 5,
			'wwo_rate_limit_window'  => 3600,
			'wwo_notify_admin_email' => get_option( 'admin_email' ),
			'wwo_auto_approve'       => 'no',
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key, false ) ) {
				add_option( $key, $value );
			}
		}
	}
}
