<?php
/**
 * AJAX endpoints for offers, admin actions, approvals, and polling.
 *
 * Every handler verifies a nonce and the relevant capability before acting.
 *
 * @package WC_Wholesale_Offers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and handles all AJAX actions.
 */
class WWO_Ajax {

	/**
	 * Register AJAX hooks. All require a logged-in user (no nopriv handlers).
	 */
	public function __construct() {
		// Customer actions.
		add_action( 'wp_ajax_wwo_submit_offer', array( $this, 'submit_offer' ) );
		add_action( 'wp_ajax_wwo_customer_respond', array( $this, 'customer_respond' ) );
		add_action( 'wp_ajax_wwo_poll', array( $this, 'poll' ) );
		add_action( 'wp_ajax_wwo_clear_unread', array( $this, 'clear_unread' ) );

		// Admin actions.
		add_action( 'wp_ajax_wwo_admin_offer_action', array( $this, 'admin_offer_action' ) );
		add_action( 'wp_ajax_wwo_admin_approval', array( $this, 'admin_approval' ) );

		// Issues a fresh nonce to the current user (survives page caching).
		add_action( 'wp_ajax_wwo_refresh_nonce', array( $this, 'refresh_nonce' ) );

		// Start an output buffer before each of our handlers so any stray PHP
		// notice/warning is captured (and later discarded) instead of corrupting
		// the JSON response. Runs at priority 0, before the handlers (priority 10).
		$actions = array(
			'wwo_submit_offer',
			'wwo_customer_respond',
			'wwo_poll',
			'wwo_clear_unread',
			'wwo_admin_offer_action',
			'wwo_admin_approval',
			'wwo_refresh_nonce',
		);
		foreach ( $actions as $a ) {
			add_action( 'wp_ajax_' . $a, array( $this, 'start_buffer' ), 0 );
		}
	}

	/**
	 * Begin output buffering for an AJAX request.
	 */
	public function start_buffer() {
		ob_start();
	}

	/**
	 * Return a fresh 'wwo_public' nonce for the current logged-in user.
	 *
	 * No nonce is required to call this: it only ever returns a token bound to
	 * the already-authenticated current user, which is useless to anyone else.
	 * This lets the front end recover when a page-cached nonce has gone stale.
	 */
	public function refresh_nonce() {
		if ( ! is_user_logged_in() ) {
			$this->err( array( 'message' => __( 'Please log in.', 'wc-wholesale-offers' ), 'logged_in' => 0 ), 403 );
		}
		$this->ok( array( 'nonce' => wp_create_nonce( 'wwo_public' ) ) );
	}

	/**
	 * Verify the public nonce and return a self-explanatory JSON error on
	 * failure. Unlike check_ajax_referer() (which dies with a bare "-1"), this
	 * tells us WHETHER the request is authenticated — so the Network tab alone
	 * reveals whether the cause is a stale nonce (logged_in:1) or a missing auth
	 * cookie at admin-ajax (logged_in:0).
	 */
	private function verify_public_request() {
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( wp_verify_nonce( $nonce, 'wwo_public' ) ) {
			return; // Valid.
		}

		$logged_in = is_user_logged_in();
		$this->err(
			array(
				'code'      => 'bad_nonce',
				'logged_in' => $logged_in ? 1 : 0,
				'message'   => $logged_in
					? __( 'Security check failed. Please reload the page and try again.', 'wc-wholesale-offers' )
					: __( 'You appear to be logged out on this connection. Please reload the page and sign in again.', 'wc-wholesale-offers' ),
			),
			403
		);
	}

	/**
	 * Discard any buffered output (stray PHP notices/warnings from this or other
	 * plugins/themes) so it cannot corrupt the JSON response, then send success.
	 *
	 * @param array $data Payload.
	 */
	private function ok( $data = array() ) {
		$this->discard_output();
		wp_send_json_success( $data );
	}

	/**
	 * As ok(), but for an error response.
	 *
	 * @param array    $data Payload.
	 * @param int|null $code Optional HTTP status code.
	 */
	private function err( $data, $code = null ) {
		$this->discard_output();
		wp_send_json_error( $data, $code );
	}

	/**
	 * Clean any open output buffers so the response body is pure JSON.
	 */
	private function discard_output() {
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
	}

	/* ---------------------------------------------------------------------
	 * Customer endpoints.
	 * ------------------------------------------------------------------- */

	/**
	 * Submit a new offer.
	 */
	public function submit_offer() {
		$this->verify_public_request();

		$user_id = get_current_user_id();
		// Gate on approved-wholesale status — the same rule that shows the
		// "Make an Offer" box — so the button and the action never disagree.
		if ( ! $user_id || ! WWO_Roles::is_approved_wholesale( $user_id ) ) {
			$this->err(
				array(
					'message'   => __( 'Only approved wholesale customers can make offers.', 'wc-wholesale-offers' ),
					'logged_in' => $user_id ? 1 : 0,
				),
				403
			);
		}

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$price        = isset( $_POST['price'] ) ? wp_unslash( $_POST['price'] ) : ''; // Sanitised in offers layer.

		$result = WWO_Offers::create_offer( $user_id, $product_id, $variation_id, $price );

		if ( is_wp_error( $result ) ) {
			$this->err( array( 'message' => $result->get_error_message() ) );
		}

		$this->ok(
			array(
				'message'  => __( 'Your offer has been submitted. We will respond shortly.', 'wc-wholesale-offers' ),
				'offer_id' => $result,
			)
		);
	}

	/**
	 * Customer responds to an offer (accept/reject/counter).
	 */
	public function customer_respond() {
		$this->verify_public_request();

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			$this->err( array( 'message' => __( 'Please log in.', 'wc-wholesale-offers' ) ), 403 );
		}

		$offer_id = isset( $_POST['offer_id'] ) ? absint( $_POST['offer_id'] ) : 0;
		$action   = isset( $_POST['offer_action'] ) ? sanitize_key( wp_unslash( $_POST['offer_action'] ) ) : '';

		switch ( $action ) {
			case 'accept':
				$result = WWO_Offers::customer_accept( $offer_id, $user_id );
				break;
			case 'reject':
				$result = WWO_Offers::customer_reject( $offer_id, $user_id );
				break;
			case 'counter':
				$price  = isset( $_POST['price'] ) ? wp_unslash( $_POST['price'] ) : '';
				$result = WWO_Offers::customer_counter( $offer_id, $user_id, $price );
				break;
			default:
				$result = new WP_Error( 'wwo_bad_action', __( 'Unknown action.', 'wc-wholesale-offers' ) );
		}

		if ( is_wp_error( $result ) ) {
			$this->err( array( 'message' => $result->get_error_message() ) );
		}

		$this->ok( array( 'message' => __( 'Done.', 'wc-wholesale-offers' ) ) );
	}

	/**
	 * Polling endpoint: returns the user's offers + unread badge.
	 */
	public function poll() {
		$this->verify_public_request();

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			$this->err( array(), 403 );
		}

		$offers = WWO_DB::get_user_offers( $user_id );
		$out    = array();
		$labels = WWO_Offers::statuses();

		foreach ( $offers as $offer ) {
			$out[] = array(
				'id'            => (int) $offer->id,
				'product_id'    => (int) $offer->product_id,
				'status'        => $offer->status,
				'status_label'  => isset( $labels[ $offer->status ] ) ? $labels[ $offer->status ] : $offer->status,
				'turn'          => $offer->turn,
				'current_price' => wp_strip_all_tags( wc_price( (float) $offer->current_price ) ),
				'agreed_price'  => $offer->agreed_price ? wp_strip_all_tags( wc_price( (float) $offer->agreed_price ) ) : null,
				'can_respond'   => ( 'countered' === $offer->status && 'customer' === $offer->turn ),
				'can_counter'   => ( 'countered' === $offer->status && 'customer' === $offer->turn && WWO_Offers::can_counter( $offer ) ),
			);
		}

		$this->ok(
			array(
				'offers' => $out,
				'unread' => (int) get_user_meta( $user_id, 'wwo_unread', true ),
			)
		);
	}

	/**
	 * Reset the customer's unread badge.
	 */
	public function clear_unread() {
		$this->verify_public_request();
		$user_id = get_current_user_id();
		if ( $user_id ) {
			update_user_meta( $user_id, 'wwo_unread', 0 );
		}
		$this->ok();
	}

	/* ---------------------------------------------------------------------
	 * Admin endpoints.
	 * ------------------------------------------------------------------- */

	/**
	 * Admin acts on an offer (accept/counter/reject).
	 */
	public function admin_offer_action() {
		check_ajax_referer( 'wwo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_wwo_offers' ) ) {
			$this->err( array( 'message' => __( 'Permission denied.', 'wc-wholesale-offers' ) ), 403 );
		}

		$offer_id = isset( $_POST['offer_id'] ) ? absint( $_POST['offer_id'] ) : 0;
		$action   = isset( $_POST['offer_action'] ) ? sanitize_key( wp_unslash( $_POST['offer_action'] ) ) : '';

		switch ( $action ) {
			case 'accept':
				$result = WWO_Offers::admin_accept( $offer_id );
				break;
			case 'reject':
				$result = WWO_Offers::admin_reject( $offer_id );
				break;
			case 'counter':
				$price  = isset( $_POST['price'] ) ? wp_unslash( $_POST['price'] ) : '';
				$result = WWO_Offers::admin_counter( $offer_id, $price );
				break;
			default:
				$result = new WP_Error( 'wwo_bad_action', __( 'Unknown action.', 'wc-wholesale-offers' ) );
		}

		if ( is_wp_error( $result ) ) {
			$this->err( array( 'message' => $result->get_error_message() ) );
		}

		$offer = WWO_DB::get_offer( $offer_id );
		$labels = WWO_Offers::statuses();
		$this->ok(
			array(
				'message'      => __( 'Offer updated.', 'wc-wholesale-offers' ),
				'status'       => $offer->status,
				'status_label' => isset( $labels[ $offer->status ] ) ? $labels[ $offer->status ] : $offer->status,
			)
		);
	}

	/**
	 * Admin approves/rejects a wholesale account.
	 */
	public function admin_approval() {
		check_ajax_referer( 'wwo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_wwo_offers' ) ) {
			$this->err( array( 'message' => __( 'Permission denied.', 'wc-wholesale-offers' ) ), 403 );
		}

		$target_user = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$action      = isset( $_POST['approval_action'] ) ? sanitize_key( wp_unslash( $_POST['approval_action'] ) ) : '';

		$user = get_userdata( $target_user );
		if ( ! $user || ! in_array( WWO_Roles::ROLE, (array) $user->roles, true ) ) {
			$this->err( array( 'message' => __( 'Invalid wholesale account.', 'wc-wholesale-offers' ) ) );
		}

		if ( 'approve' === $action ) {
			WWO_Roles::set_status( $target_user, WWO_Roles::STATUS_APPROVED );
			do_action( 'wwo_wholesale_approved', $target_user );
			$label = __( 'Approved', 'wc-wholesale-offers' );
		} elseif ( 'reject' === $action ) {
			WWO_Roles::set_status( $target_user, WWO_Roles::STATUS_REJECTED );
			do_action( 'wwo_wholesale_rejected', $target_user );
			$label = __( 'Rejected', 'wc-wholesale-offers' );
		} else {
			$this->err( array( 'message' => __( 'Unknown action.', 'wc-wholesale-offers' ) ) );
		}

		$this->ok(
			array(
				'message' => __( 'Account updated.', 'wc-wholesale-offers' ),
				'status'  => $label,
			)
		);
	}
}
