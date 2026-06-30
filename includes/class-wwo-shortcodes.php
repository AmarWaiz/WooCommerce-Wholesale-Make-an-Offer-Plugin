<?php
/**
 * Front-end shortcodes.
 *
 * @package WC_Wholesale_Offers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers shortcodes and loads their templates.
 */
class WWO_Shortcodes {

	/**
	 * Register shortcodes.
	 */
	public function __construct() {
		add_shortcode( 'wwo_login_register', array( $this, 'login_register' ) );
		// Alias kept for flexibility; behaves the same as the main shortcode.
		add_shortcode( 'wwo_dashboard', array( $this, 'login_register' ) );
	}

	/**
	 * One shortcode for the whole account experience:
	 *  - logged OUT  → styled login / registration form,
	 *  - logged IN   → the account dashboard (My Account), styled to match.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function login_register( $atts ) {
		$atts = shortcode_atts(
			array(
				'default' => 'login',       // login|register.
				'layout'  => 'transparent', // transparent (for dark page sections) | boxed (self-contained dark card).
			),
			$atts,
			'wwo_login_register'
		);

		wp_enqueue_style( 'wwo-login' );
		wp_enqueue_style( 'wwo-public' );

		// Logged in → render the account dashboard inline (same page, no redirect).
		if ( is_user_logged_in() ) {
			$boxed = ( 'boxed' === $atts['layout'] ) ? ' wwo-account-embed--boxed' : '';
			return '<div class="wwo-account-embed' . esc_attr( $boxed ) . '">' . do_shortcode( '[woocommerce_my_account]' ) . '</div>';
		}

		// Logged out → login / registration form.
		wp_enqueue_script( 'wwo-public' );

		ob_start();
		$template = WWO_PLUGIN_DIR . 'templates/login-register.php';
		if ( file_exists( $template ) ) {
			$default_tab  = ( 'register' === $atts['default'] ) ? 'register' : 'login';
			$layout_class = ( 'boxed' === $atts['layout'] ) ? 'wwo-auth--boxed' : '';
			include $template;
		}
		return self::no_autop( ob_get_clean() );
	}

	/**
	 * Collapse whitespace between HTML tags so WordPress's wpautop() does not
	 * inject stray <p>/<br> tags into the form (which create empty gaps,
	 * especially around the hidden inputs before the submit button).
	 *
	 * Only whitespace that sits purely between tags is removed, so visible text
	 * content and inline spacing are preserved.
	 *
	 * @param string $html Raw markup.
	 * @return string
	 */
	private static function no_autop( $html ) {
		$collapsed = preg_replace( '/>\s+</', '><', (string) $html );
		return null === $collapsed ? (string) $html : $collapsed;
	}
}
