<?php
/**
 * Login / Registration template (dark / transparent, palette-driven).
 *
 * Designed to sit on a dark page section: the container background is
 * transparent and text is light. Override by copying to
 * yourtheme/wc-wholesale-offers/login-register.php.
 *
 * @var string $default_tab Which tab to show first (login|register).
 *
 * @package WC_Wholesale_Offers
 */

defined( 'ABSPATH' ) || exit;

$wwo_default_tab = isset( $default_tab ) ? $default_tab : 'login';

// Surface any messages passed back via query string (sanitised here).
$wwo_error    = isset( $_GET['wwo_error'] ) ? sanitize_key( wp_unslash( $_GET['wwo_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$wwo_notice   = isset( $_GET['wwo_notice'] ) ? sanitize_key( wp_unslash( $_GET['wwo_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$wwo_redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// Inline eye icons (show / hide password).
$wwo_eye     = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>';
$wwo_eye_off = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
?>
<div class="wwo-auth wwo-auth--dark <?php echo isset( $layout_class ) ? esc_attr( $layout_class ) : ''; ?>" data-default-tab="<?php echo esc_attr( $wwo_default_tab ); ?>">
	<div class="wwo-auth__card">

		<?php if ( $wwo_error ) : ?>
			<div class="wwo-alert wwo-alert--error" role="alert"><?php echo esc_html( WWO_Registration::message_for( $wwo_error, 'error' ) ); ?></div>
		<?php endif; ?>
		<?php if ( $wwo_notice ) : ?>
			<div class="wwo-alert wwo-alert--<?php echo 'pending' === $wwo_notice ? 'info' : 'success'; ?>" role="status"><?php echo esc_html( WWO_Registration::message_for( $wwo_notice, 'notice' ) ); ?></div>
		<?php endif; ?>

		<?php // --- Login form --- ?>
		<form class="wwo-form wwo-form--login" method="post" data-panel="login">

			<div class="wwo-field">
				<label class="wwo-label" for="wwo-login-user"><?php esc_html_e( 'Email Address', 'wc-wholesale-offers' ); ?></label>
				<input id="wwo-login-user" type="text" name="username" autocomplete="username" placeholder="<?php esc_attr_e( 'name@example.com', 'wc-wholesale-offers' ); ?>" required>
			</div>

			<div class="wwo-field wwo-field--password">
				<label class="wwo-label" for="wwo-login-pass"><?php esc_html_e( 'Password', 'wc-wholesale-offers' ); ?></label>
				<div class="wwo-input-wrap">
					<input id="wwo-login-pass" type="password" name="password" autocomplete="current-password" placeholder="<?php esc_attr_e( 'Password', 'wc-wholesale-offers' ); ?>" required>
					<button type="button" class="wwo-eye" aria-label="<?php esc_attr_e( 'Show password', 'wc-wholesale-offers' ); ?>" data-show="<?php echo esc_attr( $wwo_eye ); ?>" data-hide="<?php echo esc_attr( $wwo_eye_off ); ?>"><?php echo $wwo_eye; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				</div>
				<a class="wwo-forgot" href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Forgot password?', 'wc-wholesale-offers' ); ?></a>
			</div>

			<button type="button" class="wwo-btn wwo-btn--outline" data-tab-link="register"><?php esc_html_e( 'Create an Account', 'wc-wholesale-offers' ); ?></button>

			<label class="wwo-checkbox">
				<input type="checkbox" name="remember" value="1">
				<span><?php esc_html_e( 'Keep me signed in on this device.', 'wc-wholesale-offers' ); ?></span>
			</label>

			<input type="hidden" name="wwo_action" value="login">
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $wwo_redirect ); ?>">
			<?php wp_nonce_field( 'wwo_login', 'wwo_login_nonce' ); ?>

			<button type="submit" class="wwo-btn wwo-btn--submit"><?php esc_html_e( 'Login', 'wc-wholesale-offers' ); ?></button>
		</form>

		<?php // --- Register form --- ?>
		<form class="wwo-form wwo-form--register" method="post" data-panel="register">

			<div class="wwo-grid-2">
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
				<label class="wwo-label" for="wwo-reg-email"><?php esc_html_e( 'Email Address', 'wc-wholesale-offers' ); ?></label>
				<input id="wwo-reg-email" type="email" name="email" autocomplete="email" placeholder="<?php esc_attr_e( 'name@example.com', 'wc-wholesale-offers' ); ?>" required>
			</div>

			<div class="wwo-field wwo-field--password">
				<label class="wwo-label" for="wwo-reg-pass"><?php esc_html_e( 'Password', 'wc-wholesale-offers' ); ?></label>
				<div class="wwo-input-wrap">
					<input id="wwo-reg-pass" type="password" name="password" autocomplete="new-password" minlength="8" placeholder="<?php esc_attr_e( 'Min. 8 characters', 'wc-wholesale-offers' ); ?>" required>
					<button type="button" class="wwo-eye" aria-label="<?php esc_attr_e( 'Show password', 'wc-wholesale-offers' ); ?>" data-show="<?php echo esc_attr( $wwo_eye ); ?>" data-hide="<?php echo esc_attr( $wwo_eye_off ); ?>"><?php echo $wwo_eye; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				</div>
			</div>

			<div class="wwo-field">
				<label class="wwo-label" for="wwo-reg-role"><?php esc_html_e( 'Account type', 'wc-wholesale-offers' ); ?></label>
				<select id="wwo-reg-role" name="account_role" class="wwo-select">
					<option value="customer"><?php esc_html_e( 'Customer (retail)', 'wc-wholesale-offers' ); ?></option>
					<option value="<?php echo esc_attr( WWO_Roles::ROLE ); ?>"><?php esc_html_e( 'Wholesale Customer', 'wc-wholesale-offers' ); ?></option>
				</select>
				<span class="wwo-field__hint wwo-wholesale-hint" hidden><?php esc_html_e( 'Wholesale accounts require admin approval before trade pricing is unlocked.', 'wc-wholesale-offers' ); ?></span>
			</div>

			<label class="wwo-checkbox">
				<input type="checkbox" name="agree" value="1" required>
				<span><?php esc_html_e( 'Creating an account means you’re okay with our Terms of Service, Privacy Policy, and our default Notification Settings.', 'wc-wholesale-offers' ); ?></span>
			</label>

			<input type="hidden" name="wwo_action" value="register">
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $wwo_redirect ); ?>">
			<?php wp_nonce_field( 'wwo_register', 'wwo_register_nonce' ); ?>

			<button type="submit" class="wwo-btn wwo-btn--submit"><?php esc_html_e( 'Create Account', 'wc-wholesale-offers' ); ?></button>

			<p class="wwo-form__switch"><?php esc_html_e( 'Already have an account?', 'wc-wholesale-offers' ); ?> <button type="button" class="wwo-link" data-tab-link="login"><?php esc_html_e( 'Sign in', 'wc-wholesale-offers' ); ?></button></p>
		</form>

	</div>
</div>
