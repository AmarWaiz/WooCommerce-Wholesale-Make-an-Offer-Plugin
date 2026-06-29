<?php
/**
 * Front-end login & registration handling.
 *
 * @package WC_Wholesale_Offers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Processes the custom login/registration forms and admin approval actions.
 */
class WWO_Registration {

	/**
	 * Hook form processors.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'maybe_process_forms' ) );
	}

	/**
	 * Dispatch form handlers based on the posted action.
	 */
	public function maybe_process_forms() {
		if ( empty( $_POST['wwo_action'] ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['wwo_action'] ) );

		switch ( $action ) {
			case 'login':
				$this->handle_login();
				break;
			case 'register':
				$this->handle_register();
				break;
		}
	}

	/**
	 * Process the login form.
	 */
	private function handle_login() {
		if ( ! isset( $_POST['wwo_login_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wwo_login_nonce'] ) ), 'wwo_login' ) ) {
			$this->redirect_with( array( 'wwo_error' => 'nonce' ) );
		}

		$creds = array(
			'user_login'    => isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '',
			'user_password' => isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '', // Passwords are not sanitised.
			'remember'      => ! empty( $_POST['remember'] ),
		);

		$user = wp_signon( $creds, is_ssl() );

		if ( is_wp_error( $user ) ) {
			$this->redirect_with( array( 'wwo_error' => 'login' ) );
		}

		$redirect = $this->get_redirect_target();
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Process the registration form.
	 */
	private function handle_register() {
		if ( ! isset( $_POST['wwo_register_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wwo_register_nonce'] ) ), 'wwo_register' ) ) {
			$this->redirect_with( array( 'wwo_error' => 'nonce' ) );
		}

		if ( ! get_option( 'users_can_register' ) && ! apply_filters( 'wwo_force_allow_registration', true ) ) {
			$this->redirect_with( array( 'wwo_error' => 'disabled' ) );
		}

		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$password   = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$role_input = isset( $_POST['account_role'] ) ? sanitize_key( wp_unslash( $_POST['account_role'] ) ) : 'customer';

		// Only two roles are selectable on the front end.
		$role = ( WWO_Roles::ROLE === $role_input ) ? WWO_Roles::ROLE : 'customer';

		// Validation.
		$errors = new WP_Error();
		if ( ! is_email( $email ) ) {
			$errors->add( 'email', __( 'Please provide a valid email address.', 'wc-wholesale-offers' ) );
		}
		if ( email_exists( $email ) ) {
			$errors->add( 'email_exists', __( 'An account already exists with that email address.', 'wc-wholesale-offers' ) );
		}
		if ( strlen( $password ) < 8 ) {
			$errors->add( 'password', __( 'Password must be at least 8 characters.', 'wc-wholesale-offers' ) );
		}

		if ( $errors->has_errors() ) {
			$this->redirect_with(
				array(
					'wwo_error' => $errors->get_error_code(),
				)
			);
		}

		// Derive a username from the email local-part, ensuring uniqueness.
		$base     = sanitize_user( current( explode( '@', $email ) ), true );
		$username = $base;
		$i        = 1;
		while ( username_exists( $username ) ) {
			$username = $base . $i;
			++$i;
		}

		$user_id = wp_insert_user(
			array(
				'user_login' => $username,
				'user_email' => $email,
				'user_pass'  => $password,
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'role'       => $role,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			$this->redirect_with( array( 'wwo_error' => 'create' ) );
		}

		if ( WWO_Roles::ROLE === $role ) {
			// Wholesale registration.
			$auto = 'yes' === get_option( 'wwo_auto_approve', 'no' );
			WWO_Roles::set_status( $user_id, $auto ? WWO_Roles::STATUS_APPROVED : WWO_Roles::STATUS_PENDING );

			/**
			 * Fire notifications for a new pending wholesale account.
			 */
			do_action( 'wwo_wholesale_registered', $user_id, $auto );

			if ( $auto ) {
				$this->login_and_redirect( $user_id, array( 'wwo_notice' => 'wholesale_active' ) );
			}

			// Pending: do NOT auto-login. Show the waiting message.
			$this->redirect_with( array( 'wwo_notice' => 'pending' ) );
		}

		// Retail customer: activate immediately and log in.
		do_action( 'wwo_customer_registered', $user_id );
		$this->login_and_redirect( $user_id, array( 'wwo_notice' => 'registered' ) );
	}

	/**
	 * Log a user in programmatically and redirect.
	 *
	 * @param int   $user_id User ID.
	 * @param array $args     Query args for the redirect.
	 */
	private function login_and_redirect( $user_id, $args ) {
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );

		$redirect = $this->get_redirect_target();
		wp_safe_redirect( add_query_arg( $args, $redirect ) );
		exit;
	}

	/**
	 * Resolve where to send the user after auth (My Account by default).
	 *
	 * @return string
	 */
	private function get_redirect_target() {
		if ( ! empty( $_POST['redirect_to'] ) ) {
			$candidate = esc_url_raw( wp_unslash( $_POST['redirect_to'] ) );
			// wp_safe_redirect only allows local hosts; pass through.
			return $candidate;
		}
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			return wc_get_page_permalink( 'myaccount' );
		}
		return home_url();
	}

	/**
	 * Redirect back to the login/registration page with query args, then exit.
	 *
	 * Uses the dedicated login page (which renders the [wwo_login_register]
	 * shortcode) rather than the HTTP referer, so notices like the wholesale
	 * "waiting for approval" message are always displayed even when the browser
	 * strips the Referer header.
	 *
	 * @param array $args Query args.
	 */
	private function redirect_with( $args ) {
		$base = $this->get_login_page_url();
		wp_safe_redirect( add_query_arg( $args, remove_query_arg( array( 'wwo_error', 'wwo_notice' ), $base ) ) );
		exit;
	}

	/**
	 * Resolve the URL of the front-end login/registration page.
	 *
	 * @return string
	 */
	private function get_login_page_url() {
		$page_id = (int) get_option( 'wwo_login_page_id' );
		if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
			return get_permalink( $page_id );
		}
		// Fall back to the referer, then the home page.
		return wp_get_referer() ? wp_get_referer() : home_url();
	}

	/**
	 * Map an error/notice code to a human message (used by templates).
	 *
	 * @param string $code Code.
	 * @param string $type error|notice.
	 * @return string
	 */
	public static function message_for( $code, $type = 'error' ) {
		$errors = array(
			'nonce'        => __( 'Security check failed. Please try again.', 'wc-wholesale-offers' ),
			'login'        => __( 'Invalid username/email or password.', 'wc-wholesale-offers' ),
			'disabled'     => __( 'Registration is currently disabled.', 'wc-wholesale-offers' ),
			'email'        => __( 'Please provide a valid email address.', 'wc-wholesale-offers' ),
			'email_exists' => __( 'An account already exists with that email address.', 'wc-wholesale-offers' ),
			'password'     => __( 'Password must be at least 8 characters.', 'wc-wholesale-offers' ),
			'create'       => __( 'We could not create your account. Please try again.', 'wc-wholesale-offers' ),
		);
		$notices = array(
			'pending'          => __( 'Your account is waiting for admin approval.', 'wc-wholesale-offers' ),
			'wholesale_active' => __( 'Welcome! Your wholesale account is active.', 'wc-wholesale-offers' ),
			'registered'       => __( 'Your account has been created. Welcome!', 'wc-wholesale-offers' ),
			'loggedout'        => __( 'You have been logged out.', 'wc-wholesale-offers' ),
		);

		$map = ( 'notice' === $type ) ? $notices : $errors;
		return isset( $map[ $code ] ) ? $map[ $code ] : '';
	}
}
