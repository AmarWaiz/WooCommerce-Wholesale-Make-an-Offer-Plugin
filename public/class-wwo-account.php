<?php
/**
 * WooCommerce My Account integration: an "My Offers" tab.
 *
 * @package WC_Wholesale_Offers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds the offers endpoint to the My Account area.
 */
class WWO_Account {

	const ENDPOINT = 'wwo-offers';

	/**
	 * Hook account menu + endpoint output.
	 */
	public function __construct() {
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu_item' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'endpoint_content' ) );
		add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_var' ) );

		// Show a pending banner at the top of My Account for unapproved wholesale users.
		add_action( 'woocommerce_account_dashboard', array( $this, 'pending_banner' ) );

		// Surface post-auth notices (e.g. "welcome", "waiting for approval").
		add_action( 'woocommerce_before_account_navigation', array( $this, 'query_notice' ) );
	}

	/**
	 * Render a notice passed via the ?wwo_notice query arg after a redirect.
	 */
	public function query_notice() {
		if ( empty( $_GET['wwo_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$code    = sanitize_key( wp_unslash( $_GET['wwo_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = WWO_Registration::message_for( $code, 'notice' );
		if ( $message ) {
			printf(
				'<div class="wwo-alert wwo-alert--%1$s">%2$s</div>',
				'pending' === $code ? 'info' : 'success',
				esc_html( $message )
			);
		}
	}

	/**
	 * Register the endpoint query var with WooCommerce.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_var( $vars ) {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	/**
	 * Insert the menu item before "Logout".
	 *
	 * @param array $items Menu items.
	 * @return array
	 */
	public function menu_item( $items ) {
		// Only show to wholesale customers.
		if ( ! WWO_Roles::is_wholesale() ) {
			return $items;
		}

		$new = array();
		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				$new[ self::ENDPOINT ] = __( 'My Offers', 'wc-wholesale-offers' );
			}
			$new[ $key ] = $label;
		}
		// If logout wasn't present, append.
		if ( ! isset( $new[ self::ENDPOINT ] ) ) {
			$new[ self::ENDPOINT ] = __( 'My Offers', 'wc-wholesale-offers' );
		}
		return $new;
	}

	/**
	 * Render the offers endpoint content.
	 */
	public function endpoint_content() {
		$user_id = get_current_user_id();

		// Reset unread badge when the customer views their offers.
		update_user_meta( $user_id, 'wwo_unread', 0 );

		$offers   = WWO_DB::get_user_offers( $user_id );
		$template = WWO_PLUGIN_DIR . 'templates/account-offers.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}

	/**
	 * Pending-approval banner on the account dashboard.
	 */
	public function pending_banner() {
		if ( WWO_Roles::is_wholesale() && ! WWO_Roles::is_approved_wholesale() ) {
			$status = WWO_Roles::get_status( get_current_user_id() );
			$msg    = ( WWO_Roles::STATUS_REJECTED === $status )
				? __( 'Your wholesale application was not approved. Please contact us for details.', 'wc-wholesale-offers' )
				: __( 'Your wholesale account is waiting for admin approval. Wholesale pricing and offers will unlock once approved.', 'wc-wholesale-offers' );

			printf(
				'<div class="wwo-alert wwo-alert--info">%s</div>',
				esc_html( $msg )
			);
		}
	}
}
