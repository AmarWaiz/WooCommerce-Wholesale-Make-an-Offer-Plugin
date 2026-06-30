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

		// Send already-logged-in visitors of the login page to the real
		// WooCommerce My Account dashboard (avoids the "stuck on a mini panel"
		// confusion and keeps the standard dashboard + logout link in one place).
		add_action( 'template_redirect', array( $this, 'redirect_logged_in_from_login_page' ) );
	}

	/**
	 * Redirect logged-in users away from the dedicated login page.
	 */
	public function redirect_logged_in_from_login_page() {
		if ( ! is_user_logged_in() || is_admin() ) {
			return;
		}

		$login_page_id = (int) get_option( 'wwo_login_page_id' );
		if ( ! $login_page_id || ! is_page( $login_page_id ) ) {
			return;
		}

		// Don't redirect if the login page is also the My Account page (avoids loops).
		$myaccount_id = (int) wc_get_page_id( 'myaccount' );
		if ( $login_page_id === $myaccount_id ) {
			return;
		}

		// Preserve any notice (e.g. "registered") so it can be surfaced if needed.
		$target = wc_get_page_permalink( 'myaccount' );
		if ( ! empty( $_GET['wwo_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$target = add_query_arg( 'wwo_notice', sanitize_key( wp_unslash( $_GET['wwo_notice'] ) ), $target ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Render the combined login/registration page.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function login_register( $atts ) {
		$atts = shortcode_atts(
			array(
				'default' => 'login', // login|register.
			),
			$atts,
			'wwo_login_register'
		);

		if ( is_user_logged_in() ) {
			// If this shortcode lives ON the WooCommerce My Account page, hand off
			// to WooCommerce so the real account dashboard renders (instead of a
			// bare panel). This is what makes /my-account/ look right when the
			// page contains this shortcode.
			if ( function_exists( 'is_account_page' ) && is_account_page() ) {
				return do_shortcode( '[woocommerce_my_account]' );
			}

			// Otherwise show a styled, friendly panel.
			wp_enqueue_style( 'wwo-login' );
			return $this->logged_in_panel();
		}

		// Ensure the login styles/scripts are present even outside normal flow.
		wp_enqueue_style( 'wwo-login' );
		wp_enqueue_script( 'wwo-public' );

		ob_start();
		$template = WWO_PLUGIN_DIR . 'templates/login-register.php';
		if ( file_exists( $template ) ) {
			$default_tab = ( 'register' === $atts['default'] ) ? 'register' : 'login';
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

	/**
	 * Simple panel shown to logged-in users on the login page.
	 *
	 * @return string
	 */
	private function logged_in_panel() {
		$user    = wp_get_current_user();
		$account = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url();
		$logout  = wp_logout_url( add_query_arg( 'wwo_notice', 'loggedout', $account ) );

		ob_start();
		?>
		<div class="wwo-auth wwo-auth--loggedin">
			<div class="wwo-card">
				<h2 class="wwo-card__title">
					<?php
					/* translators: %s: display name */
					printf( esc_html__( 'Welcome back, %s', 'wc-wholesale-offers' ), esc_html( $user->display_name ) );
					?>
				</h2>
				<?php if ( WWO_Roles::is_wholesale() && ! WWO_Roles::is_approved_wholesale() ) : ?>
					<p class="wwo-alert wwo-alert--info">
						<?php esc_html_e( 'Your wholesale account is waiting for admin approval.', 'wc-wholesale-offers' ); ?>
					</p>
				<?php endif; ?>
				<p>
					<a class="wwo-btn wwo-btn--primary" href="<?php echo esc_url( $account ); ?>"><?php esc_html_e( 'Go to my account', 'wc-wholesale-offers' ); ?></a>
					<a class="wwo-btn wwo-btn--ghost" href="<?php echo esc_url( $logout ); ?>"><?php esc_html_e( 'Log out', 'wc-wholesale-offers' ); ?></a>
				</p>
			</div>
		</div>
		<?php
		return self::no_autop( ob_get_clean() );
	}
}
