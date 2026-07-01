<?php
/**
 * Offer negotiation business logic.
 *
 * Every public method re-validates server-side. Pricing is never trusted from
 * the client: the original/wholesale price is recomputed here.
 *
 * @package WC_Wholesale_Offers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates the full "Make an Offer" lifecycle.
 */
class WWO_Offers {

	const STATUS_PENDING   = 'pending';
	const STATUS_COUNTERED = 'countered';
	const STATUS_ACCEPTED  = 'accepted';
	const STATUS_REJECTED  = 'rejected';
	const STATUS_EXPIRED   = 'expired';

	/**
	 * Maximum negotiation rounds (configurable).
	 *
	 * @return int
	 */
	public static function max_rounds() {
		return max( 1, (int) get_option( 'wwo_max_rounds', 3 ) );
	}

	/**
	 * Human-readable status labels.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			self::STATUS_PENDING   => __( 'Pending', 'wc-wholesale-offers' ),
			self::STATUS_COUNTERED => __( 'Countered', 'wc-wholesale-offers' ),
			self::STATUS_ACCEPTED  => __( 'Accepted', 'wc-wholesale-offers' ),
			self::STATUS_REJECTED  => __( 'Rejected', 'wc-wholesale-offers' ),
			self::STATUS_EXPIRED   => __( 'Expired', 'wc-wholesale-offers' ),
		);
	}

	/* =====================================================================
	 * Customer-initiated actions.
	 * =================================================================== */

	/**
	 * Create a new offer from a wholesale customer.
	 *
	 * @param int   $user_id      User ID.
	 * @param int   $product_id   Product ID.
	 * @param int   $variation_id Variation ID (0 if simple product).
	 * @param float $offered      Proposed price.
	 * @return int|WP_Error Offer ID or error.
	 */
	public static function create_offer( $user_id, $product_id, $variation_id, $offered ) {
		$user_id      = absint( $user_id );
		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );
		$offered      = self::sanitize_price( $offered );

		// Capability / approval check.
		if ( ! WWO_Roles::is_approved_wholesale( $user_id ) ) {
			return new WP_Error( 'wwo_not_allowed', __( 'Only approved wholesale customers can make offers.', 'wc-wholesale-offers' ) );
		}

		// Product must exist and be purchasable.
		$product = wc_get_product( $variation_id ? $variation_id : $product_id );
		if ( ! $product ) {
			return new WP_Error( 'wwo_no_product', __( 'Invalid product.', 'wc-wholesale-offers' ) );
		}

		// Rate limiting.
		$limit  = (int) get_option( 'wwo_rate_limit_count', 5 );
		$window = (int) get_option( 'wwo_rate_limit_window', 3600 );
		if ( $limit > 0 && WWO_DB::count_recent_offers( $user_id, $window ) >= $limit ) {
			return new WP_Error(
				'wwo_rate_limited',
				__( 'You have submitted too many offers recently. Please try again later.', 'wc-wholesale-offers' )
			);
		}

		// One active negotiation per product at a time.
		$existing = WWO_DB::get_active_offer( $user_id, $product_id, $variation_id );
		if ( $existing ) {
			return new WP_Error( 'wwo_existing', __( 'You already have an active offer for this product.', 'wc-wholesale-offers' ) );
		}

		// The original price is the wholesale price (server-computed, never trusted from client).
		$original = WWO_Pricing::get_base_wholesale_price( $product );
		if ( $original <= 0 ) {
			return new WP_Error( 'wwo_no_price', __( 'This product is not available for offers.', 'wc-wholesale-offers' ) );
		}

		// Validate the offered price.
		$validation = self::validate_proposed_price( $offered, $original );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$offer_id = WWO_DB::insert_offer(
			array(
				'product_id'     => $product_id,
				'variation_id'   => $variation_id,
				'user_id'        => $user_id,
				'original_price' => $original,
				'current_price'  => $offered,
				'status'         => self::STATUS_PENDING,
				'turn'           => 'admin', // admin must respond next.
				'round_count'    => 1,
			)
		);

		if ( ! $offer_id ) {
			return new WP_Error( 'wwo_db_error', __( 'Could not save your offer. Please try again.', 'wc-wholesale-offers' ) );
		}

		WWO_DB::add_history( $offer_id, 'customer', 'offer', $offered );

		$offer = WWO_DB::get_offer( $offer_id );
		do_action( 'wwo_offer_created', $offer );

		return $offer_id;
	}

	/**
	 * Customer counters the admin's counter-offer.
	 *
	 * @param int   $offer_id Offer ID.
	 * @param int   $user_id  Acting user ID.
	 * @param float $price    Counter price.
	 * @return true|WP_Error
	 */
	public static function customer_counter( $offer_id, $user_id, $price ) {
		$offer = self::get_owned_offer( $offer_id, $user_id );
		if ( is_wp_error( $offer ) ) {
			return $offer;
		}

		// It must be the customer's turn and the offer still open.
		if ( self::STATUS_COUNTERED !== $offer->status || 'customer' !== $offer->turn ) {
			return new WP_Error( 'wwo_bad_state', __( 'This offer cannot be countered right now.', 'wc-wholesale-offers' ) );
		}

		// Round limit.
		if ( (int) $offer->round_count >= self::max_rounds() ) {
			return new WP_Error( 'wwo_max_rounds', __( 'The negotiation limit has been reached. You can only accept or reject.', 'wc-wholesale-offers' ) );
		}

		$price      = self::sanitize_price( $price );
		$validation = self::validate_proposed_price( $price, (float) $offer->original_price );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		WWO_DB::update_offer(
			$offer_id,
			array(
				'current_price' => $price,
				'status'        => self::STATUS_COUNTERED,
				'turn'          => 'admin',
				'round_count'   => (int) $offer->round_count + 1,
			)
		);
		WWO_DB::add_history( $offer_id, 'customer', 'counter', $price );

		$offer = WWO_DB::get_offer( $offer_id );
		do_action( 'wwo_offer_countered', $offer, 'customer' );

		return true;
	}

	/**
	 * Customer accepts the admin's current counter price.
	 *
	 * @param int $offer_id Offer ID.
	 * @param int $user_id  Acting user ID.
	 * @return true|WP_Error
	 */
	public static function customer_accept( $offer_id, $user_id ) {
		$offer = self::get_owned_offer( $offer_id, $user_id );
		if ( is_wp_error( $offer ) ) {
			return $offer;
		}
		if ( self::STATUS_COUNTERED !== $offer->status || 'customer' !== $offer->turn ) {
			return new WP_Error( 'wwo_bad_state', __( 'There is nothing to accept on this offer.', 'wc-wholesale-offers' ) );
		}

		self::finalize_accept( $offer, (float) $offer->current_price, 'customer' );
		return true;
	}

	/**
	 * Customer rejects/withdraws the negotiation.
	 *
	 * @param int $offer_id Offer ID.
	 * @param int $user_id  Acting user ID.
	 * @return true|WP_Error
	 */
	public static function customer_reject( $offer_id, $user_id ) {
		$offer = self::get_owned_offer( $offer_id, $user_id );
		if ( is_wp_error( $offer ) ) {
			return $offer;
		}
		if ( in_array( $offer->status, array( self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_EXPIRED ), true ) ) {
			return new WP_Error( 'wwo_bad_state', __( 'This offer is already closed.', 'wc-wholesale-offers' ) );
		}

		WWO_DB::update_offer( $offer_id, array( 'status' => self::STATUS_REJECTED, 'turn' => '' ) );
		WWO_DB::add_history( $offer_id, 'customer', 'reject' );

		$offer = WWO_DB::get_offer( $offer_id );
		do_action( 'wwo_offer_rejected', $offer, 'customer' );

		return true;
	}

	/* =====================================================================
	 * Admin-initiated actions.
	 * =================================================================== */

	/**
	 * Admin counters the customer.
	 *
	 * @param int   $offer_id Offer ID.
	 * @param float $price    Counter price.
	 * @return true|WP_Error
	 */
	public static function admin_counter( $offer_id, $price ) {
		$offer = WWO_DB::get_offer( $offer_id );
		if ( ! $offer ) {
			return new WP_Error( 'wwo_not_found', __( 'Offer not found.', 'wc-wholesale-offers' ) );
		}
		if ( ! in_array( $offer->status, array( self::STATUS_PENDING, self::STATUS_COUNTERED ), true ) || 'admin' !== $offer->turn ) {
			return new WP_Error( 'wwo_bad_state', __( 'This offer cannot be countered right now.', 'wc-wholesale-offers' ) );
		}
		if ( (int) $offer->round_count >= self::max_rounds() ) {
			return new WP_Error( 'wwo_max_rounds', __( 'The negotiation limit has been reached. You can only accept or reject.', 'wc-wholesale-offers' ) );
		}

		$price      = self::sanitize_price( $price );
		$validation = self::validate_proposed_price( $price, (float) $offer->original_price );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		WWO_DB::update_offer(
			$offer_id,
			array(
				'current_price' => $price,
				'status'        => self::STATUS_COUNTERED,
				'turn'          => 'customer',
				'round_count'   => (int) $offer->round_count + 1,
			)
		);
		WWO_DB::add_history( $offer_id, 'admin', 'counter', $price );

		$offer = WWO_DB::get_offer( $offer_id );
		do_action( 'wwo_offer_countered', $offer, 'admin' );

		return true;
	}

	/**
	 * Admin accepts the customer's current price.
	 *
	 * @param int $offer_id Offer ID.
	 * @return true|WP_Error
	 */
	public static function admin_accept( $offer_id ) {
		$offer = WWO_DB::get_offer( $offer_id );
		if ( ! $offer ) {
			return new WP_Error( 'wwo_not_found', __( 'Offer not found.', 'wc-wholesale-offers' ) );
		}
		if ( ! in_array( $offer->status, array( self::STATUS_PENDING, self::STATUS_COUNTERED ), true ) ) {
			return new WP_Error( 'wwo_bad_state', __( 'This offer is already closed.', 'wc-wholesale-offers' ) );
		}

		self::finalize_accept( $offer, (float) $offer->current_price, 'admin' );
		return true;
	}

	/**
	 * Admin rejects the offer.
	 *
	 * @param int $offer_id Offer ID.
	 * @return true|WP_Error
	 */
	public static function admin_reject( $offer_id ) {
		$offer = WWO_DB::get_offer( $offer_id );
		if ( ! $offer ) {
			return new WP_Error( 'wwo_not_found', __( 'Offer not found.', 'wc-wholesale-offers' ) );
		}
		if ( in_array( $offer->status, array( self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_EXPIRED ), true ) ) {
			return new WP_Error( 'wwo_bad_state', __( 'This offer is already closed.', 'wc-wholesale-offers' ) );
		}

		WWO_DB::update_offer( $offer_id, array( 'status' => self::STATUS_REJECTED, 'turn' => '' ) );
		WWO_DB::add_history( $offer_id, 'admin', 'reject' );

		$offer = WWO_DB::get_offer( $offer_id );
		do_action( 'wwo_offer_rejected', $offer, 'admin' );

		return true;
	}

	/* =====================================================================
	 * Shared helpers.
	 * =================================================================== */

	/**
	 * Finalise an accepted offer: store agreed price + expiry.
	 *
	 * @param object $offer   Offer row.
	 * @param float  $price   Agreed price.
	 * @param string $accepted_by admin|customer.
	 */
	private static function finalize_accept( $offer, $price, $accepted_by ) {
		$expiry_hrs = (int) get_option( 'wwo_accept_expiry_hrs', 48 );
		$expires_at = $expiry_hrs > 0
			? gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + ( $expiry_hrs * HOUR_IN_SECONDS ) )
			: null;

		WWO_DB::update_offer(
			$offer->id,
			array(
				'status'       => self::STATUS_ACCEPTED,
				'agreed_price' => $price,
				'turn'         => '',
				'expires_at'   => $expires_at,
				'used'         => 0,
			)
		);
		WWO_DB::add_history( $offer->id, $accepted_by, 'accept', $price );

		$offer = WWO_DB::get_offer( $offer->id );
		do_action( 'wwo_offer_accepted', $offer, $accepted_by );
	}

	/**
	 * Mark an accepted offer as used after checkout.
	 *
	 * @param int $offer_id Offer ID.
	 * @param int $order_id Order ID.
	 */
	public static function mark_used( $offer_id, $order_id ) {
		WWO_DB::update_offer(
			$offer_id,
			array(
				'used'     => 1,
				'order_id' => absint( $order_id ),
			)
		);
		WWO_DB::add_history( $offer_id, 'system', 'used', null, sprintf( 'Order #%d', absint( $order_id ) ) );
	}

	/**
	 * Cron callback: expire accepted-but-unused offers past their window.
	 */
	public static function expire_due_offers() {
		$offers = WWO_DB::get_expirable_offers();
		foreach ( $offers as $offer ) {
			WWO_DB::update_offer( $offer->id, array( 'status' => self::STATUS_EXPIRED, 'turn' => '' ) );
			WWO_DB::add_history( $offer->id, 'system', 'expire' );

			$fresh = WWO_DB::get_offer( $offer->id );
			do_action( 'wwo_offer_expired', $fresh );
		}
	}

	/**
	 * Whether the offer (for the current customer's turn) can still be countered.
	 *
	 * @param object $offer Offer row.
	 * @return bool
	 */
	public static function can_counter( $offer ) {
		return (int) $offer->round_count < self::max_rounds();
	}

	/**
	 * Fetch an offer and assert ownership by the given user.
	 *
	 * @param int $offer_id Offer ID.
	 * @param int $user_id  User ID.
	 * @return object|WP_Error
	 */
	private static function get_owned_offer( $offer_id, $user_id ) {
		$offer = WWO_DB::get_offer( $offer_id );
		if ( ! $offer ) {
			return new WP_Error( 'wwo_not_found', __( 'Offer not found.', 'wc-wholesale-offers' ) );
		}
		if ( (int) $offer->user_id !== absint( $user_id ) ) {
			return new WP_Error( 'wwo_forbidden', __( 'You are not allowed to act on this offer.', 'wc-wholesale-offers' ) );
		}
		return $offer;
	}

	/**
	 * Normalise a price coming from a request.
	 *
	 * @param mixed $price Raw price.
	 * @return float
	 */
	public static function sanitize_price( $price ) {
		// wc_format_decimal handles localised separators and strips currency symbols.
		return (float) wc_format_decimal( wc_clean( wp_unslash( $price ) ), wc_get_price_decimals() );
	}

	/**
	 * Validate a proposed price against the product's base price.
	 *
	 * @param float $price    Proposed price.
	 * @param float $original Base/original price.
	 * @return true|WP_Error
	 */
	private static function validate_proposed_price( $price, $original ) {
		if ( $price <= 0 ) {
			return new WP_Error( 'wwo_invalid_price', __( 'Please enter a valid price greater than zero.', 'wc-wholesale-offers' ) );
		}
		// The offer must be strictly LESS than the list price — an offer equal to
		// (or above) the list price defeats the purpose of negotiating a discount.
		if ( $price >= $original ) {
			return new WP_Error(
				'wwo_too_high',
				/* translators: %s: formatted list price */
				sprintf( __( 'The offer amount must be less than the list price (%s).', 'wc-wholesale-offers' ), wp_strip_all_tags( wc_price( $original ) ) )
			);
		}
		return true;
	}
}
