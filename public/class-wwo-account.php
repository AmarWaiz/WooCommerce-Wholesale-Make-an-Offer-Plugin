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

		// Quick-action cards on the dashboard.
		add_action( 'woocommerce_account_dashboard', array( $this, 'dashboard_cards' ), 20 );

		// Surface post-auth notices (e.g. "welcome", "waiting for approval").
		add_action( 'woocommerce_before_account_navigation', array( $this, 'query_notice' ) );
	}

	/**
	 * Render quick-action cards on the account dashboard.
	 */
	public function dashboard_cards() {
		$icons = array(
			'orders'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
			'downloads'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
			'edit-address' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
			'edit-account' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
			'offers'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41 13.42 20.6a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>',
		);

		$cards = array(
			array( 'url' => wc_get_account_endpoint_url( 'orders' ),       'icon' => $icons['orders'],       'title' => __( 'Orders', 'wc-wholesale-offers' ),         'desc' => __( 'View and track your orders', 'wc-wholesale-offers' ) ),
			array( 'url' => wc_get_account_endpoint_url( 'downloads' ),    'icon' => $icons['downloads'],    'title' => __( 'Downloads', 'wc-wholesale-offers' ),      'desc' => __( 'Access your downloadable files', 'wc-wholesale-offers' ) ),
			array( 'url' => wc_get_account_endpoint_url( 'edit-address' ), 'icon' => $icons['edit-address'], 'title' => __( 'Addresses', 'wc-wholesale-offers' ),      'desc' => __( 'Manage shipping & billing', 'wc-wholesale-offers' ) ),
			array( 'url' => wc_get_account_endpoint_url( 'edit-account' ), 'icon' => $icons['edit-account'], 'title' => __( 'Account details', 'wc-wholesale-offers' ), 'desc' => __( 'Update your profile & password', 'wc-wholesale-offers' ) ),
		);

		if ( WWO_Roles::is_wholesale() ) {
			$cards[] = array(
				'url'   => wc_get_account_endpoint_url( self::ENDPOINT ),
				'icon'  => $icons['offers'],
				'title' => __( 'My Offers', 'wc-wholesale-offers' ),
				'desc'  => __( 'Track your price offers', 'wc-wholesale-offers' ),
			);
		}

		echo '<div class="wwo-dash-cards">';
		foreach ( $cards as $card ) {
			printf(
				'<a class="wwo-dash-card" href="%1$s"><span class="wwo-dash-card__icon">%2$s</span><span class="wwo-dash-card__body"><span class="wwo-dash-card__title">%3$s</span><span class="wwo-dash-card__desc">%4$s</span></span><span class="wwo-dash-card__arrow">&rarr;</span></a>',
				esc_url( $card['url'] ),
				$card['icon'], // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted inline SVG.
				esc_html( $card['title'] ),
				esc_html( $card['desc'] )
			);
		}
		echo '</div>';
	}

	/**
	 * Render a notice passed via the ?wwo_notice query arg after a redirect.
	 *
	 * The "pending" state is authoritative via pending_banner() (which reads the
	 * real account status), so we deliberately ignore a stale ?wwo_notice=pending
	 * left in the URL — otherwise an already-approved customer who refreshes the
	 * page would keep seeing "waiting for approval".
	 */
	public function query_notice() {
		if ( empty( $_GET['wwo_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$code = sanitize_key( wp_unslash( $_GET['wwo_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Never trust a URL param for the pending state.
		if ( 'pending' === $code ) {
			return;
		}

		$message = WWO_Registration::message_for( $code, 'notice' );
		if ( $message ) {
			printf(
				'<div class="wwo-alert wwo-alert--success">%s</div>',
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
