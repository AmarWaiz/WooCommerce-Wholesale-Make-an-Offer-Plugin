<?php
/**
 * Email & dashboard notifications for offers and approvals.
 *
 * @package WC_Wholesale_Offers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sends transactional emails on every offer/approval status change and keeps a
 * lightweight unread counter for the dashboard polling badge.
 */
class WWO_Notifications {

	/**
	 * Hook into offer/approval lifecycle actions.
	 */
	public function __construct() {
		add_action( 'wwo_offer_created', array( $this, 'on_created' ), 10, 1 );
		add_action( 'wwo_offer_countered', array( $this, 'on_countered' ), 10, 2 );
		add_action( 'wwo_offer_accepted', array( $this, 'on_accepted' ), 10, 2 );
		add_action( 'wwo_offer_rejected', array( $this, 'on_rejected' ), 10, 2 );
		add_action( 'wwo_offer_expired', array( $this, 'on_expired' ), 10, 1 );

		add_action( 'wwo_wholesale_registered', array( $this, 'on_wholesale_registered' ), 10, 2 );
		add_action( 'wwo_wholesale_approved', array( $this, 'on_wholesale_approved' ), 10, 1 );
		add_action( 'wwo_wholesale_rejected', array( $this, 'on_wholesale_rejected' ), 10, 1 );

		add_action( 'wwo_password_reset_requested', array( $this, 'on_password_reset_requested' ), 10, 2 );

		// Stop WordPress from emailing the site admin a "Password changed for user
		// X" notice every time a customer/wholesale user resets their password.
		// These are personal, per-user notifications and should not reach the admin
		// inbox. Runs before core's own handler (priority 10) so it can unhook it.
		add_action( 'after_password_reset', array( $this, 'suppress_admin_password_notification' ), 1, 1 );
	}

	/**
	 * Remove the core admin "password changed" email for non-admin accounts.
	 *
	 * WordPress hooks wp_password_change_notification() onto after_password_reset
	 * to email the site admin whenever any user resets their password. For ordinary
	 * customers and wholesale accounts that is unwanted noise, so we unhook it here
	 * for those users. Administrators still get their own change notification.
	 *
	 * @param WP_User $user The user whose password was reset.
	 */
	public function suppress_admin_password_notification( $user ) {
		if ( $user instanceof WP_User && ! user_can( $user, 'manage_options' ) ) {
			remove_action( 'after_password_reset', 'wp_password_change_notification' );
		}
	}

	/* ---------------------------------------------------------------------
	 * Offer events.
	 * ------------------------------------------------------------------- */

	/**
	 * New offer submitted → notify admin.
	 *
	 * @param object $offer Offer.
	 */
	public function on_created( $offer ) {
		$this->bump_admin_unread();
		$subject = __( 'New price offer received', 'wc-wholesale-offers' );
		$body    = sprintf(
			/* translators: 1: customer, 2: product, 3: offered price */
			__( '%1$s has made an offer of %3$s on "%2$s".', 'wc-wholesale-offers' ),
			$this->customer_name( $offer->user_id ),
			$this->product_name( $offer ),
			$this->price( $offer->current_price )
		);
		$this->mail_admin( $subject, $body, $this->admin_offer_url( $offer->id ), __( 'Review offer', 'wc-wholesale-offers' ) );
	}

	/**
	 * Counter sent → notify the other party.
	 *
	 * @param object $offer Offer.
	 * @param string $actor admin|customer.
	 */
	public function on_countered( $offer, $actor ) {
		if ( 'admin' === $actor ) {
			// Notify customer.
			$this->bump_customer_unread( $offer->user_id );
			$subject = __( 'You have a counter-offer', 'wc-wholesale-offers' );
			$body    = sprintf(
				/* translators: 1: product, 2: counter price */
				__( 'We have countered your offer on "%1$s" with %2$s. You can accept, reject, or counter from your account.', 'wc-wholesale-offers' ),
				$this->product_name( $offer ),
				$this->price( $offer->current_price )
			);
			$this->mail_customer( $offer->user_id, $subject, $body, $this->account_url(), __( 'View offer', 'wc-wholesale-offers' ) );
		} else {
			// Notify admin.
			$this->bump_admin_unread();
			$subject = __( 'Customer sent a counter-offer', 'wc-wholesale-offers' );
			$body    = sprintf(
				/* translators: 1: customer, 2: product, 3: price */
				__( '%1$s countered with %3$s on "%2$s".', 'wc-wholesale-offers' ),
				$this->customer_name( $offer->user_id ),
				$this->product_name( $offer ),
				$this->price( $offer->current_price )
			);
			$this->mail_admin( $subject, $body, $this->admin_offer_url( $offer->id ), __( 'Review offer', 'wc-wholesale-offers' ) );
		}
	}

	/**
	 * Offer accepted → notify both parties.
	 *
	 * @param object $offer       Offer.
	 * @param string $accepted_by admin|customer.
	 */
	public function on_accepted( $offer, $accepted_by ) {
		$expiry = $offer->expires_at ? $this->date( $offer->expires_at ) : __( 'no expiry', 'wc-wholesale-offers' );

		// Customer message.
		$this->bump_customer_unread( $offer->user_id );
		$cust_subject = __( 'Your offer was accepted 🎉', 'wc-wholesale-offers' );
		$cust_body    = sprintf(
			/* translators: 1: product, 2: agreed price, 3: expiry */
			__( 'Great news! The price for "%1$s" is now %2$s. Check out before %3$s to use this price.', 'wc-wholesale-offers' ),
			$this->product_name( $offer ),
			$this->price( $offer->agreed_price ),
			$expiry
		);
		$this->mail_customer( $offer->user_id, $cust_subject, $cust_body, $this->product_url( $offer ), __( 'Buy now', 'wc-wholesale-offers' ) );

		// Note: we intentionally do NOT email the admin here. An accepted offer is
		// a customer-facing, account-specific notification; the admin can review
		// accepted offers any time from the Offers dashboard. This keeps the admin
		// inbox free of per-customer status emails.
	}

	/**
	 * Offer rejected → notify the other party.
	 *
	 * @param object $offer Offer.
	 * @param string $actor admin|customer.
	 */
	public function on_rejected( $offer, $actor ) {
		if ( 'admin' === $actor ) {
			$this->bump_customer_unread( $offer->user_id );
			$this->mail_customer(
				$offer->user_id,
				__( 'Update on your offer', 'wc-wholesale-offers' ),
				sprintf(
					/* translators: %s: product */
					__( 'Unfortunately your offer on "%s" was not accepted this time. You are welcome to try again.', 'wc-wholesale-offers' ),
					$this->product_name( $offer )
				),
				$this->product_url( $offer ),
				__( 'View product', 'wc-wholesale-offers' )
			);
		} else {
			$this->bump_admin_unread();
			$this->mail_admin(
				__( 'A customer withdrew an offer', 'wc-wholesale-offers' ),
				sprintf(
					/* translators: 1: customer, 2: product */
					__( '%1$s withdrew their offer on "%2$s".', 'wc-wholesale-offers' ),
					$this->customer_name( $offer->user_id ),
					$this->product_name( $offer )
				),
				$this->admin_offer_url( $offer->id ),
				__( 'View offer', 'wc-wholesale-offers' )
			);
		}
	}

	/**
	 * Accepted price expired → notify customer.
	 *
	 * @param object $offer Offer.
	 */
	public function on_expired( $offer ) {
		$this->bump_customer_unread( $offer->user_id );
		$this->mail_customer(
			$offer->user_id,
			__( 'Your agreed price has expired', 'wc-wholesale-offers' ),
			sprintf(
				/* translators: %s: product */
				__( 'The agreed price for "%s" has expired. You can make a new offer at any time.', 'wc-wholesale-offers' ),
				$this->product_name( $offer )
			),
			$this->product_url( $offer ),
			__( 'Make a new offer', 'wc-wholesale-offers' )
		);
	}

	/* ---------------------------------------------------------------------
	 * Approval events.
	 * ------------------------------------------------------------------- */

	/**
	 * New wholesale registration → notify admin (unless auto-approved).
	 *
	 * @param int  $user_id User ID.
	 * @param bool $auto    Whether it was auto-approved.
	 */
	public function on_wholesale_registered( $user_id, $auto ) {
		if ( $auto ) {
			return;
		}
		$this->bump_admin_unread();
		$this->mail_admin(
			__( 'New wholesale account awaiting approval', 'wc-wholesale-offers' ),
			sprintf(
				/* translators: %s: customer */
				__( '%s has registered for a wholesale account and is awaiting approval.', 'wc-wholesale-offers' ),
				$this->customer_name( $user_id )
			),
			admin_url( 'admin.php?page=wwo-approvals' ),
			__( 'Review approvals', 'wc-wholesale-offers' )
		);
	}

	/**
	 * Wholesale account approved → notify customer.
	 *
	 * @param int $user_id User ID.
	 */
	public function on_wholesale_approved( $user_id ) {
		$this->mail_customer(
			$user_id,
			__( 'Your wholesale account is active', 'wc-wholesale-offers' ),
			__( 'Good news — your wholesale account has been approved. You now have access to wholesale pricing and can make offers.', 'wc-wholesale-offers' ),
			$this->account_url(),
			__( 'Start shopping', 'wc-wholesale-offers' )
		);
	}

	/**
	 * Wholesale account rejected → notify customer.
	 *
	 * @param int $user_id User ID.
	 */
	public function on_wholesale_rejected( $user_id ) {
		$this->mail_customer(
			$user_id,
			__( 'Update on your wholesale application', 'wc-wholesale-offers' ),
			__( 'Your wholesaler account request has not been approved. If you believe this is an error or need more information, please contact the administrator.', 'wc-wholesale-offers' ),
			home_url(),
			__( 'Visit store', 'wc-wholesale-offers' )
		);
	}

	/**
	 * Password reset requested → email the customer a branded reset link.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $reset_url Fully-formed reset URL back to the login page.
	 */
	public function on_password_reset_requested( $user_id, $reset_url ) {
		$this->mail_customer(
			$user_id,
			__( 'Reset your password', 'wc-wholesale-offers' ),
			__( 'We received a request to reset the password for your account. Click the button below to choose a new password. This link will expire for your security. If you did not request this, you can safely ignore this email — your password will not change.', 'wc-wholesale-offers' ),
			$reset_url,
			__( 'Reset my password', 'wc-wholesale-offers' )
		);
	}

	/* ---------------------------------------------------------------------
	 * Unread counters (dashboard polling badge).
	 * ------------------------------------------------------------------- */

	/**
	 * Increment the per-customer unread notification counter.
	 *
	 * @param int $user_id User ID.
	 */
	private function bump_customer_unread( $user_id ) {
		$count = (int) get_user_meta( $user_id, 'wwo_unread', true );
		update_user_meta( $user_id, 'wwo_unread', $count + 1 );
	}

	/**
	 * Increment the site-wide admin unread counter (transient).
	 */
	private function bump_admin_unread() {
		$count = (int) get_transient( 'wwo_admin_unread' );
		set_transient( 'wwo_admin_unread', $count + 1, WEEK_IN_SECONDS );
	}

	/* ---------------------------------------------------------------------
	 * Mail helpers.
	 * ------------------------------------------------------------------- */

	/**
	 * Send an email to the configured admin address.
	 */
	private function mail_admin( $subject, $body, $cta_url = '', $cta_text = '' ) {
		$to = sanitize_email( get_option( 'wwo_notify_admin_email', get_option( 'admin_email' ) ) );
		if ( $to ) {
			$this->send( $to, $subject, $body, $cta_url, $cta_text );
		}
	}

	/**
	 * Send an email to a customer by user ID.
	 */
	private function mail_customer( $user_id, $subject, $body, $cta_url = '', $cta_text = '' ) {
		$user = get_userdata( $user_id );
		if ( $user && is_email( $user->user_email ) ) {
			$this->send( $user->user_email, $subject, $body, $cta_url, $cta_text );
		}
	}

	/**
	 * Build and dispatch a branded HTML email.
	 */
	private function send( $to, $subject, $body, $cta_url, $cta_text ) {
		$primary = sanitize_hex_color( get_option( 'wwo_color_primary', '#332A28' ) );
		$accent  = sanitize_hex_color( get_option( 'wwo_color_secondary', '#F0D1AD' ) );
		$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		$cta = '';
		if ( $cta_url && $cta_text ) {
			$cta = sprintf(
				'<p style="margin:24px 0;"><a href="%1$s" style="background:%2$s;color:#ffffff;padding:12px 22px;border-radius:6px;text-decoration:none;display:inline-block;font-weight:600;">%3$s</a></p>',
				esc_url( $cta_url ),
				esc_attr( $primary ),
				esc_html( $cta_text )
			);
		}

		$html = sprintf(
			'<style>
				a[x-apple-data-detectors]{color:inherit !important;text-decoration:none !important;}
				.wwo-email-head a,.wwo-email-head a:link,.wwo-email-head a:visited{color:#ffffff !important;text-decoration:none !important;}
			</style>
			<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;border:1px solid #eee;border-radius:10px;overflow:hidden;">
				<div class="wwo-email-head" style="background:%1$s;color:#fff;padding:20px 28px;font-size:18px;font-weight:700;"><span style="color:#ffffff;text-decoration:none;">%2$s</span></div>
				<div style="padding:28px;color:#332A28;font-size:15px;line-height:1.6;">
					<p>%3$s</p>%4$s
					<p style="color:#888;font-size:12px;margin-top:28px;">%5$s</p>
				</div>
			</div>',
			esc_attr( $primary ),
			esc_html( $site ),
			esc_html( $body ),
			$cta, // already escaped above.
			esc_html__( 'This is an automated message regarding your wholesale account.', 'wc-wholesale-offers' )
		);

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		/**
		 * Allow customisation of any outgoing notification.
		 */
		$html = apply_filters( 'wwo_email_html', $html, $subject, $to );

		/*
		 * Sending mail must never break the request that triggered it. On servers
		 * without a configured mailer, wp_mail()/PHP mail() can emit warnings that
		 * would otherwise be printed into an AJAX response and corrupt its JSON.
		 * We buffer to swallow any stray output and catch any thrown error.
		 */
		ob_start();
		try {
			wp_mail( $to, $subject, $html, $headers );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WWO mail error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
		ob_end_clean();
	}

	/* ---------------------------------------------------------------------
	 * Small value helpers.
	 * ------------------------------------------------------------------- */

	private function product_name( $offer ) {
		$id      = $offer->variation_id ? $offer->variation_id : $offer->product_id;
		$product = wc_get_product( $id );
		return $product ? $product->get_name() : __( 'a product', 'wc-wholesale-offers' );
	}

	private function product_url( $offer ) {
		$product = wc_get_product( $offer->product_id );
		return $product ? get_permalink( $product->get_id() ) : home_url();
	}

	private function customer_name( $user_id ) {
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : __( 'A customer', 'wc-wholesale-offers' );
	}

	private function price( $amount ) {
		return wp_strip_all_tags( wc_price( (float) $amount ) );
	}

	private function date( $mysql ) {
		return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $mysql ) );
	}

	private function account_url() {
		return function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'wwo-offers' ) : home_url();
	}

	private function admin_offer_url( $offer_id ) {
		return admin_url( 'admin.php?page=wwo-offers&offer=' . absint( $offer_id ) );
	}
}
