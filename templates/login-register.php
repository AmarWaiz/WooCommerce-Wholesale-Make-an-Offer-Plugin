<?php
/**
 * Login / Registration template — self-contained, responsive two-column card.
 *
 * Drop [wwo_login_register] on any page; the form is fully styled on its own
 * (no theme/Elementor setup, no images needed). Override by copying to
 * yourtheme/wc-wholesale-offers/login-register.php.
 *
 * @var string $default_tab Which tab to show first (login|register).
 *
 * @package WC_Wholesale_Offers
 */

defined( 'ABSPATH' ) || exit;

// Surface any messages passed back via query string (sanitised here).
$wwo_error    = isset( $_GET['wwo_error'] ) ? sanitize_key( wp_unslash( $_GET['wwo_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$wwo_notice   = isset( $_GET['wwo_notice'] ) ? sanitize_key( wp_unslash( $_GET['wwo_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$wwo_redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// Password-reset context: a valid link carries ?wwo_reset=1&wwo_key=…&wwo_login=….
// Params are namespaced (wwo_*) so WooCommerce's account-page reset handler,
// which hijacks the bare key/login params, never intercepts our link.
$wwo_is_reset    = ! empty( $_GET['wwo_reset'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$wwo_reset_key   = isset( $_GET['wwo_key'] ) ? sanitize_text_field( wp_unslash( $_GET['wwo_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$wwo_reset_login = isset( $_GET['wwo_login'] ) ? sanitize_text_field( wp_unslash( $_GET['wwo_login'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$wwo_tab         = isset( $_GET['wwo_tab'] ) ? sanitize_key( wp_unslash( $_GET['wwo_tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// Decide which panel opens first: a reset link wins, then an explicit ?wwo_tab,
// then whatever the shortcode requested, else the sign-in form.
if ( $wwo_is_reset ) {
	$wwo_default_tab = 'reset';
} elseif ( 'lost' === $wwo_tab ) {
	$wwo_default_tab = 'lost';
} elseif ( isset( $default_tab ) && 'register' === $default_tab ) {
	$wwo_default_tab = 'register';
} else {
	$wwo_default_tab = 'login';
}

// Inline eye icons (show / hide password).
$wwo_eye     = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>';
$wwo_eye_off = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

$wwo_site = get_bloginfo( 'name' );
?>
<div class="wwo-auth" data-default-tab="<?php echo esc_attr( $wwo_default_tab ); ?>">
	<div class="wwo-auth__card">

		<?php // Decorative brand panel (CSS only — no image). ?>
		<aside class="wwo-auth__aside">
			<div class="wwo-auth__aside-inner">

				<span class="wwo-auth__brand"><?php echo esc_html( $wwo_site ); ?></span>

				<div class="wwo-auth__aside-mid">
					<h2 class="wwo-auth__aside-title"><?php esc_html_e( 'Welcome to your account', 'wc-wholesale-offers' ); ?></h2>
					<p class="wwo-auth__aside-text"><?php esc_html_e( 'Sign in, or create an account in seconds, to manage everything in one place.', 'wc-wholesale-offers' ); ?></p>

					<ul class="wwo-auth__features">
						<li><?php esc_html_e( 'Track your orders and downloads', 'wc-wholesale-offers' ); ?></li>
						<li><?php esc_html_e( 'Unlock exclusive wholesale pricing', 'wc-wholesale-offers' ); ?></li>
						<li><?php esc_html_e( 'Make an offer and negotiate your price', 'wc-wholesale-offers' ); ?></li>
					</ul>
				</div>

				<p class="wwo-auth__aside-foot"><?php esc_html_e( 'Secure sign-in · Your details stay private', 'wc-wholesale-offers' ); ?></p>

			</div>
		</aside>

		<?php // Form column. ?>
		<div class="wwo-auth__main">

			<div class="wwo-tabs" role="tablist">
				<button type="button" class="wwo-tab" data-tab="login" role="tab"><?php esc_html_e( 'Sign in', 'wc-wholesale-offers' ); ?></button>
				<button type="button" class="wwo-tab" data-tab="register" role="tab"><?php esc_html_e( 'Create account', 'wc-wholesale-offers' ); ?></button>
			</div>

			<?php if ( $wwo_error ) : ?>
				<div class="wwo-alert wwo-alert--error wwo-alert--dismissible" role="alert"><span class="wwo-alert__text"><?php echo esc_html( WWO_Registration::message_for( $wwo_error, 'error' ) ); ?></span><button type="button" class="wwo-alert__close" aria-label="<?php esc_attr_e( 'Dismiss', 'wc-wholesale-offers' ); ?>">&times;</button></div>
			<?php endif; ?>
			<?php if ( $wwo_notice ) : ?>
				<div class="wwo-alert wwo-alert--<?php echo 'pending' === $wwo_notice ? 'info' : 'success'; ?> wwo-alert--dismissible" role="status"><span class="wwo-alert__text"><?php echo esc_html( WWO_Registration::message_for( $wwo_notice, 'notice' ) ); ?></span><button type="button" class="wwo-alert__close" aria-label="<?php esc_attr_e( 'Dismiss', 'wc-wholesale-offers' ); ?>">&times;</button></div>
			<?php endif; ?>

			<?php // --- Login form --- ?>
			<form class="wwo-form wwo-form--login" method="post" data-panel="login">
				<p class="wwo-form__lead"><?php esc_html_e( 'Welcome back. Please sign in to continue.', 'wc-wholesale-offers' ); ?></p>

				<div class="wwo-field">
					<label class="wwo-label" for="wwo-login-user"><?php esc_html_e( 'Email or username', 'wc-wholesale-offers' ); ?></label>
					<input id="wwo-login-user" type="text" name="username" autocomplete="username" placeholder="<?php esc_attr_e( 'name@example.com', 'wc-wholesale-offers' ); ?>" required>
				</div>

				<div class="wwo-field">
					<label class="wwo-label" for="wwo-login-pass"><?php esc_html_e( 'Password', 'wc-wholesale-offers' ); ?></label>
					<div class="wwo-input-wrap">
						<input id="wwo-login-pass" type="password" name="password" autocomplete="current-password" placeholder="<?php esc_attr_e( 'Enter your password', 'wc-wholesale-offers' ); ?>" required>
						<button type="button" class="wwo-eye" aria-label="<?php esc_attr_e( 'Show password', 'wc-wholesale-offers' ); ?>" data-show="<?php echo esc_attr( $wwo_eye ); ?>" data-hide="<?php echo esc_attr( $wwo_eye_off ); ?>"><?php echo $wwo_eye; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
					</div>
				</div>

				<div class="wwo-row">
					<label class="wwo-checkbox">
						<input type="checkbox" name="remember" value="1">
						<span><?php esc_html_e( 'Remember me', 'wc-wholesale-offers' ); ?></span>
					</label>
					<button type="button" class="wwo-link" data-tab-link="lost"><?php esc_html_e( 'Forgot password?', 'wc-wholesale-offers' ); ?></button>
				</div>

				<input type="hidden" name="wwo_action" value="login">
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $wwo_redirect ); ?>">
				<?php wp_nonce_field( 'wwo_login', 'wwo_login_nonce' ); ?>

				<button type="submit" class="wwo-btn wwo-btn--primary"><?php esc_html_e( 'Sign in', 'wc-wholesale-offers' ); ?></button>

				<p class="wwo-switch"><?php esc_html_e( "Don't have an account?", 'wc-wholesale-offers' ); ?> <button type="button" class="wwo-link" data-tab-link="register"><?php esc_html_e( 'Create one', 'wc-wholesale-offers' ); ?></button></p>
			</form>

			<?php // --- Register form --- ?>
			<form class="wwo-form wwo-form--register" method="post" data-panel="register">
				<p class="wwo-form__lead"><?php esc_html_e( 'Create your account in a few seconds.', 'wc-wholesale-offers' ); ?></p>

				<div class="wwo-grid">
					<div class="wwo-field">
						<label class="wwo-label" for="wwo-reg-first"><?php esc_html_e( 'First name', 'wc-wholesale-offers' ); ?></label>
						<input id="wwo-reg-first" type="text" name="first_name" autocomplete="given-name" placeholder="<?php esc_attr_e( 'First name', 'wc-wholesale-offers' ); ?>">
					</div>
					<div class="wwo-field">
						<label class="wwo-label" for="wwo-reg-last"><?php esc_html_e( 'Last name', 'wc-wholesale-offers' ); ?></label>
						<input id="wwo-reg-last" type="text" name="last_name" autocomplete="family-name" placeholder="<?php esc_attr_e( 'Last name', 'wc-wholesale-offers' ); ?>">
					</div>
				</div>

				<div class="wwo-field">
					<label class="wwo-label" for="wwo-reg-email"><?php esc_html_e( 'Email address', 'wc-wholesale-offers' ); ?></label>
					<input id="wwo-reg-email" type="email" name="email" autocomplete="email" placeholder="<?php esc_attr_e( 'name@example.com', 'wc-wholesale-offers' ); ?>" required>
				</div>

				<div class="wwo-field">
					<label class="wwo-label" for="wwo-reg-pass"><?php esc_html_e( 'Password', 'wc-wholesale-offers' ); ?></label>
					<div class="wwo-input-wrap">
						<input id="wwo-reg-pass" type="password" name="password" autocomplete="new-password" minlength="8" placeholder="<?php esc_attr_e( 'At least 8 characters', 'wc-wholesale-offers' ); ?>" required>
						<button type="button" class="wwo-eye" aria-label="<?php esc_attr_e( 'Show password', 'wc-wholesale-offers' ); ?>" data-show="<?php echo esc_attr( $wwo_eye ); ?>" data-hide="<?php echo esc_attr( $wwo_eye_off ); ?>"><?php echo $wwo_eye; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
					</div>
				</div>

				<div class="wwo-field">
					<label class="wwo-label" for="wwo-reg-role"><?php esc_html_e( 'Account type', 'wc-wholesale-offers' ); ?></label>
					<select id="wwo-reg-role" name="account_role" class="wwo-select">
						<option value="customer"><?php esc_html_e( 'Customer (retail)', 'wc-wholesale-offers' ); ?></option>
						<option value="<?php echo esc_attr( WWO_Roles::ROLE ); ?>"><?php esc_html_e( 'Wholesale Customer', 'wc-wholesale-offers' ); ?></option>
					</select>
					<span class="wwo-hint wwo-wholesale-hint" hidden><?php esc_html_e( 'Wholesale accounts require admin approval before trade pricing is unlocked.', 'wc-wholesale-offers' ); ?></span>
				</div>

				<input type="hidden" name="wwo_action" value="register">
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $wwo_redirect ); ?>">
				<?php wp_nonce_field( 'wwo_register', 'wwo_register_nonce' ); ?>

				<button type="submit" class="wwo-btn wwo-btn--primary"><?php esc_html_e( 'Create account', 'wc-wholesale-offers' ); ?></button>

				<p class="wwo-switch"><?php esc_html_e( 'Already have an account?', 'wc-wholesale-offers' ); ?> <button type="button" class="wwo-link" data-tab-link="login"><?php esc_html_e( 'Sign in', 'wc-wholesale-offers' ); ?></button></p>
			</form>

			<?php // --- Lost password form (request a reset link) --- ?>
			<form class="wwo-form wwo-form--lost" method="post" data-panel="lost">
				<p class="wwo-form__lead"><?php esc_html_e( 'Forgot your password? Enter your email or username and we will send you a link to set a new one.', 'wc-wholesale-offers' ); ?></p>

				<div class="wwo-field">
					<label class="wwo-label" for="wwo-lost-user"><?php esc_html_e( 'Email or username', 'wc-wholesale-offers' ); ?></label>
					<input id="wwo-lost-user" type="text" name="user_login" autocomplete="username" placeholder="<?php esc_attr_e( 'name@example.com', 'wc-wholesale-offers' ); ?>" required>
				</div>

				<input type="hidden" name="wwo_action" value="lost_password">
				<?php wp_nonce_field( 'wwo_lost_password', 'wwo_lost_nonce' ); ?>

				<button type="submit" class="wwo-btn wwo-btn--primary"><?php esc_html_e( 'Send reset link', 'wc-wholesale-offers' ); ?></button>

				<p class="wwo-switch"><?php esc_html_e( 'Remembered it?', 'wc-wholesale-offers' ); ?> <button type="button" class="wwo-link" data-tab-link="login"><?php esc_html_e( 'Back to sign in', 'wc-wholesale-offers' ); ?></button></p>
			</form>

			<?php // --- Reset password form (shown when arriving from the email link) --- ?>
			<form class="wwo-form wwo-form--reset" method="post" data-panel="reset">
				<p class="wwo-form__lead"><?php esc_html_e( 'Choose a new password for your account.', 'wc-wholesale-offers' ); ?></p>

				<div class="wwo-field">
					<label class="wwo-label" for="wwo-reset-pass"><?php esc_html_e( 'New password', 'wc-wholesale-offers' ); ?></label>
					<div class="wwo-input-wrap">
						<input id="wwo-reset-pass" type="password" name="password" autocomplete="new-password" minlength="8" placeholder="<?php esc_attr_e( 'At least 8 characters', 'wc-wholesale-offers' ); ?>" required>
						<button type="button" class="wwo-eye" aria-label="<?php esc_attr_e( 'Show password', 'wc-wholesale-offers' ); ?>" data-show="<?php echo esc_attr( $wwo_eye ); ?>" data-hide="<?php echo esc_attr( $wwo_eye_off ); ?>"><?php echo $wwo_eye; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
					</div>
				</div>

				<div class="wwo-field">
					<label class="wwo-label" for="wwo-reset-pass2"><?php esc_html_e( 'Confirm new password', 'wc-wholesale-offers' ); ?></label>
					<div class="wwo-input-wrap">
						<input id="wwo-reset-pass2" type="password" name="password_confirm" autocomplete="new-password" minlength="8" placeholder="<?php esc_attr_e( 'Re-enter your new password', 'wc-wholesale-offers' ); ?>" required>
						<button type="button" class="wwo-eye" aria-label="<?php esc_attr_e( 'Show password', 'wc-wholesale-offers' ); ?>" data-show="<?php echo esc_attr( $wwo_eye ); ?>" data-hide="<?php echo esc_attr( $wwo_eye_off ); ?>"><?php echo $wwo_eye; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
					</div>
				</div>

				<input type="hidden" name="wwo_action" value="reset_password">
				<input type="hidden" name="reset_key" value="<?php echo esc_attr( $wwo_reset_key ); ?>">
				<input type="hidden" name="reset_login" value="<?php echo esc_attr( $wwo_reset_login ); ?>">
				<?php wp_nonce_field( 'wwo_reset_password', 'wwo_reset_nonce' ); ?>

				<button type="submit" class="wwo-btn wwo-btn--primary"><?php esc_html_e( 'Set new password', 'wc-wholesale-offers' ); ?></button>

				<p class="wwo-switch"><button type="button" class="wwo-link" data-tab-link="login"><?php esc_html_e( 'Back to sign in', 'wc-wholesale-offers' ); ?></button></p>
			</form>

		</div>
	</div>
</div>
