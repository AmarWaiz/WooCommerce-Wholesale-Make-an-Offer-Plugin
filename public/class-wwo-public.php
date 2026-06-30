<?php
/**
 * Front-end controller: assets and the "Make an Offer" UI.
 *
 * @package WC_Wholesale_Offers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers public assets and injects the offer UI on product pages.
 */
class WWO_Public {

	/**
	 * Hook assets and product-page output.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		// Place the Make an Offer block after the add-to-cart area.
		add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'render_make_an_offer' ), 20 );
	}

	/**
	 * Register (and conditionally enqueue) front-end assets.
	 */
	public function register_assets() {
		wp_register_style( 'wwo-login', WWO_PLUGIN_URL . 'assets/css/wwo-login.css', array(), wwo_asset_ver( 'assets/css/wwo-login.css' ) );
		wp_register_style( 'wwo-public', WWO_PLUGIN_URL . 'assets/css/wwo-public.css', array(), wwo_asset_ver( 'assets/css/wwo-public.css' ) );

		wp_register_script( 'wwo-public', WWO_PLUGIN_URL . 'assets/js/wwo-public.js', array( 'jquery' ), wwo_asset_ver( 'assets/js/wwo-public.js' ), true );

		/*
		 * Use a SAME-ORIGIN, host-relative admin-ajax URL. If admin_url() returns
		 * a different host/scheme than the page the visitor is on (e.g. www vs
		 * non-www, or http vs https behind a proxy), the browser would not send
		 * the WordPress auth cookie and every request would look logged-out —
		 * which fails the nonce check. Posting to the current origin guarantees
		 * the cookie travels with the request.
		 */
		$ajax_url = admin_url( 'admin-ajax.php', 'relative' );
		if ( ! $ajax_url ) {
			$ajax_url = admin_url( 'admin-ajax.php' );
		}

		$data = array(
			'ajaxUrl'      => $ajax_url,
			'ajaxUrlFull'  => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'wwo_public' ),
			'loggedIn'     => is_user_logged_in() ? 1 : 0,
			'pollInterval' => (int) apply_filters( 'wwo_poll_interval_ms', 15000 ),
			'i18n'         => array(
				'submitting'    => __( 'Submitting…', 'wc-wholesale-offers' ),
				'counterPrompt' => __( 'Enter your counter price:', 'wc-wholesale-offers' ),
				'confirmReject' => __( 'Withdraw this offer?', 'wc-wholesale-offers' ),
				'error'         => __( 'Something went wrong. Please try again.', 'wc-wholesale-offers' ),
				'loggedOut'     => __( 'You appear to be logged out. Please reload the page and sign in again.', 'wc-wholesale-offers' ),
			),
		);
		wp_localize_script( 'wwo-public', 'WWO_Public', $data );

		// Always load public styles + script on the storefront so polling works on My Account too.
		wp_enqueue_style( 'wwo-public' );
		if ( is_user_logged_in() ) {
			wp_enqueue_script( 'wwo-public' );
		}
	}

	/**
	 * Render the Make an Offer block for approved wholesale customers.
	 */
	public function render_make_an_offer() {
		if ( ! WWO_Roles::is_approved_wholesale() ) {
			return;
		}

		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		// Only simple/variable purchasable products.
		if ( ! $product->is_type( array( 'simple', 'variable' ) ) ) {
			return;
		}

		$user_id     = get_current_user_id();
		$base_price  = WWO_Pricing::get_base_wholesale_price( $product );
		$active      = WWO_DB::get_active_offer( $user_id, $product->get_id(), 0 );
		$redeemable  = WWO_DB::get_redeemable_offer( $user_id, $product->get_id(), 0 );

		wp_enqueue_script( 'wwo-public' );

		$template = WWO_PLUGIN_DIR . 'templates/make-an-offer.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}
}
