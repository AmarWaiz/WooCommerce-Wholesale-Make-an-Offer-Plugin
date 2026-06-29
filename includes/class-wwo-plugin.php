<?php
/**
 * Main plugin orchestrator (singleton).
 *
 * @package WC_Wholesale_Offers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads dependencies and registers global hooks.
 */
final class WWO_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var WWO_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Sub-components, keyed by name.
	 *
	 * @var array
	 */
	private $components = array();

	/**
	 * Retrieve the singleton instance.
	 *
	 * @return WWO_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor: load files and init.
	 */
	private function __construct() {
		$this->includes();
		$this->init_components();
		$this->register_hooks();
	}

	/**
	 * Load required class files.
	 */
	private function includes() {
		$dir = WWO_PLUGIN_DIR . 'includes/';

		require_once $dir . 'class-wwo-roles.php';
		require_once $dir . 'class-wwo-db.php';
		require_once $dir . 'class-wwo-offers.php';
		require_once $dir . 'class-wwo-pricing.php';
		require_once $dir . 'class-wwo-registration.php';
		require_once $dir . 'class-wwo-notifications.php';
		require_once $dir . 'class-wwo-settings.php';
		require_once $dir . 'class-wwo-shortcodes.php';
		require_once $dir . 'class-wwo-ajax.php';

		require_once WWO_PLUGIN_DIR . 'public/class-wwo-public.php';
		require_once WWO_PLUGIN_DIR . 'public/class-wwo-account.php';

		if ( is_admin() ) {
			require_once WWO_PLUGIN_DIR . 'admin/class-wwo-admin.php';
			require_once WWO_PLUGIN_DIR . 'admin/class-wwo-product-fields.php';
		}
	}

	/**
	 * Instantiate components.
	 */
	private function init_components() {
		$this->components['roles']         = new WWO_Roles();
		$this->components['offers']        = new WWO_Offers();
		$this->components['pricing']       = new WWO_Pricing();
		$this->components['registration']  = new WWO_Registration();
		$this->components['notifications'] = new WWO_Notifications();
		$this->components['settings']      = new WWO_Settings();
		$this->components['shortcodes']    = new WWO_Shortcodes();
		$this->components['ajax']          = new WWO_Ajax();
		$this->components['public']        = new WWO_Public();
		$this->components['account']       = new WWO_Account();

		if ( is_admin() ) {
			$this->components['admin']          = new WWO_Admin();
			$this->components['product_fields'] = new WWO_Product_Fields();
		}
	}

	/**
	 * Register globally relevant hooks.
	 */
	private function register_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_account_endpoint' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 99 );
		add_action( 'admin_init', array( $this, 'maybe_upgrade_db' ) );

		// Cron handler to expire offers and accepted prices.
		add_action( 'wwo_expire_offers_event', array( WWO_Offers::class, 'expire_due_offers' ) );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wc-wholesale-offers', false, dirname( WWO_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Register the My Account offers endpoint.
	 */
	public function register_account_endpoint() {
		add_rewrite_endpoint( 'wwo-offers', EP_ROOT | EP_PAGES );
	}

	/**
	 * Flush rewrite rules once per plugin version after the endpoint has been
	 * registered. This ensures the My Account "My Offers" endpoint resolves
	 * without the admin having to manually re-save permalinks.
	 */
	public function maybe_flush_rewrite_rules() {
		if ( get_option( 'wwo_rewrite_version' ) !== WWO_VERSION ) {
			flush_rewrite_rules();
			update_option( 'wwo_rewrite_version', WWO_VERSION );
		}
	}

	/**
	 * Make sure the custom tables exist. Guards against the case where the
	 * activation hook never ran (e.g. files updated in place), which would
	 * silently prevent offers from being saved.
	 */
	public function maybe_upgrade_db() {
		global $wpdb;

		$table  = $wpdb->prefix . 'wwo_offers';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB

		if ( $exists !== $table || get_option( 'wwo_db_version' ) !== WWO_VERSION ) {
			require_once WWO_PLUGIN_DIR . 'includes/class-wwo-activator.php';
			WWO_Activator::create_tables();
			update_option( 'wwo_db_version', WWO_VERSION );
		}
	}

	/**
	 * Access a component by key.
	 *
	 * @param string $key Component key.
	 * @return object|null
	 */
	public function get( $key ) {
		return isset( $this->components[ $key ] ) ? $this->components[ $key ] : null;
	}
}
