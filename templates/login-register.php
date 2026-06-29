<?php
/**
 * Login / Registration template.
 *
 * This is a baseline, palette-driven layout. It is intended to be visually
 * fine-tuned to the supplied design image. Override by copying to
 * yourtheme/wc-wholesale-offers/login-register.php.
 *
 * @var string $default_tab Which tab to show first (login|register).
 *
 * @package WC_Wholesale_Offers
 */

defined( 'ABSPATH' ) || exit;

$wwo_default_tab = isset( $default_tab ) ? $default_tab : 'login';

// Surface any messages passed back via query string (sanitised here).
$wwo_error  = isset( $_GET['wwo_error'] ) ? sanitize_key( wp_unslash( $_GET['wwo_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$wwo_notice = isset( $_GET['wwo_notice'] ) ? sanitize_key( wp_unslash( $_GET['wwo_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$wwo_redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wwo-auth" data-default-tab="<?php echo esc_attr( $wwo_default_tab ); ?>">
	<div class="wwo-auth__wrapper">

		<?php // Left/brand panel — replace imagery to match the supplied design. ?>
		<aside class="wwo-auth__brand" aria-hidden="true">
			<div class="wwo-auth__brand-inner">
				<div class="wwo-auth__logo"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
				<h2 class="wwo-auth__brand-title"><?php esc_html_e( 'Wholesale that works for you', 'wc-wholesale-offers' ); ?></h2>
				<p class="wwo-auth__brand-text"><?php esc_html_e( 'Register for a wholesale account, unlock trade pricing, and make an offer on the products you love.', 'wc-wholesale-offers' ); ?></p>
			</div>
		</aside>

		<?php // Form panel. ?>
		<div class="wwo-auth__panel">

			<?php if ( $wwo_error ) : ?>
				<div class="wwo-alert wwo-alert--error" role="alert"><?php echo esc_html( WWO_Registration::message_for( $wwo_error, 'error' ) ); ?></div>
			<?php endif; ?>
			<?php if ( $wwo_notice ) : ?>
				<div class="wwo-alert wwo-alert--<?php echo 'pending' === $wwo_notice ? 'info' : 'success'; ?>" role="status"><?php echo esc_html( WWO_Registration::message_for( $wwo_notice, 'notice' ) ); ?></div>
			<?php endif; ?>

			<div class="wwo-tabs" role="tablist">
				<button type="button" class="wwo-tab" data-tab="login" role="tab"><?php esc_html_e( 'Sign in', 'wc-wholesale-offers' ); ?></button>
				<button type="button" class="wwo-tab" data-tab="register" role="tab"><?php esc_html_e( 'Create account', 'wc-wholesale-offers' ); ?></button>
			</div>

			<?php // --- Login form --- ?>
			<form class="wwo-form wwo-form--login" method="post" data-panel="login">
				<h1 class="wwo-form__title"><?php esc_html_e( 'Welcome back', 'wc-wholesale-offers' ); ?></h1>
				<p class="wwo-form__subtitle"><?php esc_html_e( 'Sign in to your account to continue.', 'wc-wholesale-offers' ); ?></p>

				<label class="wwo-field">
					<span class="wwo-field__label"><?php esc_html_e( 'Username or email', 'wc-wholesale-offers' ); ?></span>
					<input type="text" name="username" autocomplete="username" required>
				</label>

				<label class="wwo-field">
					<span class="wwo-field__label"><?php esc_html_e( 'Password', 'wc-wholesale-offers' ); ?></span>
					<input type="password" name="password" autocomplete="current-password" required>
				</label>

				<div class="wwo-field-row">
					<label class="wwo-checkbox">
						<input type="checkbox" name="remember" value="1">
						<span><?php esc_html_e( 'Remember me', 'wc-wholesale-offers' ); ?></span>
					</label>
					<a class="wwo-link" href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Forgot password?', 'wc-wholesale-offers' ); ?></a>
				</div>

				<input type="hidden" name="wwo_action" value="login">
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $wwo_redirect ); ?>">
				<?php wp_nonce_field( 'wwo_login', 'wwo_login_nonce' ); ?>

				<button type="submit" class="wwo-btn wwo-btn--primary wwo-btn--block"><?php esc_html_e( 'Sign in', 'wc-wholesale-offers' ); ?></button>

				<p class="wwo-form__switch"><?php esc_html_e( "Don't have an account?", 'wc-wholesale-offers' ); ?> <button type="button" class="wwo-link" data-tab-link="register"><?php esc_html_e( 'Register', 'wc-wholesale-offers' ); ?></button></p>
			</form>

			<?php // --- Register form --- ?>
			<form class="wwo-form wwo-form--register" method="post" data-panel="register">
				<h1 class="wwo-form__title"><?php esc_html_e( 'Create your account', 'wc-wholesale-offers' ); ?></h1>
				<p class="wwo-form__subtitle"><?php esc_html_e( 'Choose retail or wholesale to get started.', 'wc-wholesale-offers' ); ?></p>

				<div class="wwo-grid-2">
					<label class="wwo-field">
						<span class="wwo-field__label"><?php esc_html_e( 'First name', 'wc-wholesale-offers' ); ?></span>
						<input type="text" name="first_name" autocomplete="given-name">
					</label>
					<label class="wwo-field">
						<span class="wwo-field__label"><?php esc_html_e( 'Last name', 'wc-wholesale-offers' ); ?></span>
						<input type="text" name="last_name" autocomplete="family-name">
					</label>
				</div>

				<label class="wwo-field">
					<span class="wwo-field__label"><?php esc_html_e( 'Email address', 'wc-wholesale-offers' ); ?></span>
					<input type="email" name="email" autocomplete="email" required>
				</label>

				<label class="wwo-field">
					<span class="wwo-field__label"><?php esc_html_e( 'Password', 'wc-wholesale-offers' ); ?></span>
					<input type="password" name="password" autocomplete="new-password" minlength="8" required>
					<span class="wwo-field__hint"><?php esc_html_e( 'At least 8 characters.', 'wc-wholesale-offers' ); ?></span>
				</label>

				<label class="wwo-field">
					<span class="wwo-field__label"><?php esc_html_e( 'Account type', 'wc-wholesale-offers' ); ?></span>
					<select name="account_role" class="wwo-select">
						<option value="customer"><?php esc_html_e( 'Customer (retail)', 'wc-wholesale-offers' ); ?></option>
						<option value="<?php echo esc_attr( WWO_Roles::ROLE ); ?>"><?php esc_html_e( 'Wholesale Customer', 'wc-wholesale-offers' ); ?></option>
					</select>
					<span class="wwo-field__hint wwo-wholesale-hint" hidden><?php esc_html_e( 'Wholesale accounts require admin approval before trade pricing is unlocked.', 'wc-wholesale-offers' ); ?></span>
				</label>

				<input type="hidden" name="wwo_action" value="register">
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $wwo_redirect ); ?>">
				<?php wp_nonce_field( 'wwo_register', 'wwo_register_nonce' ); ?>

				<button type="submit" class="wwo-btn wwo-btn--primary wwo-btn--block"><?php esc_html_e( 'Create account', 'wc-wholesale-offers' ); ?></button>

				<p class="wwo-form__switch"><?php esc_html_e( 'Already have an account?', 'wc-wholesale-offers' ); ?> <button type="button" class="wwo-link" data-tab-link="login"><?php esc_html_e( 'Sign in', 'wc-wholesale-offers' ); ?></button></p>
			</form>

		</div>
	</div>
</div>
