<?php
/**
 * Database access layer for offers and offer history.
 *
 * All queries use $wpdb->prepare() with placeholders. Table names are built
 * from the trusted $wpdb->prefix and never from user input.
 *
 * @package WC_Wholesale_Offers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Thin, well-documented gateway over the two custom tables.
 */
class WWO_DB {

	/**
	 * Fully-qualified offers table name.
	 *
	 * @return string
	 */
	public static function offers_table() {
		global $wpdb;
		return $wpdb->prefix . 'wwo_offers';
	}

	/**
	 * Fully-qualified history table name.
	 *
	 * @return string
	 */
	public static function history_table() {
		global $wpdb;
		return $wpdb->prefix . 'wwo_offer_history';
	}

	/**
	 * Insert a new offer row.
	 *
	 * @param array $data Column => value pairs (already sanitised by caller).
	 * @return int|false Insert ID or false on failure.
	 */
	public static function insert_offer( array $data ) {
		global $wpdb;

		$now      = current_time( 'mysql' );
		$defaults = array(
			'variation_id'   => 0,
			'agreed_price'   => null,
			'status'         => 'pending',
			'turn'           => 'admin',
			'round_count'    => 1,
			'expires_at'     => null,
			'used'           => 0,
			'order_id'       => 0,
			'created_at'     => $now,
			'updated_at'     => $now,
		);
		$data = wp_parse_args( $data, $defaults );

		$ok = $wpdb->insert(
			self::offers_table(),
			array(
				'product_id'     => absint( $data['product_id'] ),
				'variation_id'   => absint( $data['variation_id'] ),
				'user_id'        => absint( $data['user_id'] ),
				'original_price' => (float) $data['original_price'],
				'current_price'  => (float) $data['current_price'],
				'agreed_price'   => is_null( $data['agreed_price'] ) ? null : (float) $data['agreed_price'],
				'status'         => sanitize_key( $data['status'] ),
				'turn'           => sanitize_key( $data['turn'] ),
				'round_count'    => absint( $data['round_count'] ),
				'expires_at'     => $data['expires_at'],
				'used'           => absint( $data['used'] ),
				'order_id'       => absint( $data['order_id'] ),
				'created_at'     => $data['created_at'],
				'updated_at'     => $data['updated_at'],
			),
			array( '%d', '%d', '%d', '%f', '%f', '%f', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update an offer by ID.
	 *
	 * @param int   $offer_id Offer ID.
	 * @param array $fields   Column => value pairs.
	 * @return bool
	 */
	public static function update_offer( $offer_id, array $fields ) {
		global $wpdb;

		$fields['updated_at'] = current_time( 'mysql' );

		// Build a format map matching the supplied fields.
		$formats   = array();
		$float_map = array( 'original_price', 'current_price', 'agreed_price' );
		$int_map   = array( 'product_id', 'variation_id', 'user_id', 'round_count', 'used', 'order_id' );

		foreach ( $fields as $key => $value ) {
			if ( in_array( $key, $float_map, true ) ) {
				$formats[] = '%f';
			} elseif ( in_array( $key, $int_map, true ) ) {
				$formats[] = '%d';
			} else {
				$formats[] = '%s';
			}
		}

		return false !== $wpdb->update(
			self::offers_table(),
			$fields,
			array( 'id' => absint( $offer_id ) ),
			$formats,
			array( '%d' )
		);
	}

	/**
	 * Fetch a single offer by ID.
	 *
	 * @param int $offer_id Offer ID.
	 * @return object|null
	 */
	public static function get_offer( $offer_id ) {
		global $wpdb;
		$table = self::offers_table();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $offer_id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Fetch an active (non-final) offer for a user + product, if any.
	 *
	 * @param int $user_id      User ID.
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return object|null
	 */
	public static function get_active_offer( $user_id, $product_id, $variation_id = 0 ) {
		global $wpdb;
		$table = self::offers_table();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE user_id = %d AND product_id = %d AND variation_id = %d
				   AND status IN ('pending','countered','accepted')
				 ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $user_id ),
				absint( $product_id ),
				absint( $variation_id )
			)
		);
	}

	/**
	 * Find a usable accepted offer for a user + product to apply at checkout.
	 *
	 * @param int $user_id      User ID.
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return object|null
	 */
	public static function get_redeemable_offer( $user_id, $product_id, $variation_id = 0 ) {
		global $wpdb;
		$table = self::offers_table();
		$now   = current_time( 'mysql' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE user_id = %d AND product_id = %d AND variation_id = %d
				   AND status = 'accepted' AND used = 0
				   AND ( expires_at IS NULL OR expires_at > %s )
				 ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $user_id ),
				absint( $product_id ),
				absint( $variation_id ),
				$now
			)
		);
	}

	/**
	 * Count offers created by a user within the last $window seconds.
	 * Used for rate limiting.
	 *
	 * @param int $user_id User ID.
	 * @param int $window  Window in seconds.
	 * @return int
	 */
	public static function count_recent_offers( $user_id, $window ) {
		global $wpdb;
		$table  = self::offers_table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - absint( $window ) );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND created_at > %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $user_id ),
				$cutoff
			)
		);
	}

	/**
	 * Query offers for the admin list table.
	 *
	 * @param array $args status, search, orderby, order, per_page, paged.
	 * @return array{items: object[], total: int}
	 */
	public static function query_offers( array $args = array() ) {
		global $wpdb;
		$table = self::offers_table();

		$defaults = array(
			'status'   => '',
			'user_id'  => 0,
			'orderby'  => 'id',
			'order'    => 'DESC',
			'per_page' => 20,
			'paged'    => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		// Whitelist orderby/order to avoid SQL injection via column names.
		$allowed_orderby = array( 'id', 'product_id', 'user_id', 'current_price', 'status', 'created_at', 'updated_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'id';
		$order           = ( 'ASC' === strtoupper( $args['order'] ) ) ? 'ASC' : 'DESC';

		$where  = '1=1';
		$params = array();

		if ( $args['status'] ) {
			$where   .= ' AND status = %s';
			$params[] = sanitize_key( $args['status'] );
		}
		if ( $args['user_id'] ) {
			$where   .= ' AND user_id = %d';
			$params[] = absint( $args['user_id'] );
		}

		// Total count.
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		$total     = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) // phpcs:ignore WordPress.DB.PreparedSQL
			: $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		// Page results.
		$per_page = max( 1, absint( $args['per_page'] ) );
		$offset   = ( max( 1, absint( $args['paged'] ) ) - 1 ) * $per_page;

		$sql           = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$query_params  = array_merge( $params, array( $per_page, $offset ) );
		$items         = $wpdb->get_results( $wpdb->prepare( $sql, $query_params ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Get offers belonging to a specific user (for My Account).
	 *
	 * @param int $user_id User ID.
	 * @return object[]
	 */
	public static function get_user_offers( $user_id ) {
		global $wpdb;
		$table = self::offers_table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY updated_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $user_id )
			)
		);
	}

	/**
	 * Offers that have expired and need their status flipped.
	 *
	 * @return object[]
	 */
	public static function get_expirable_offers() {
		global $wpdb;
		$table = self::offers_table();
		$now   = current_time( 'mysql' );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE status = 'accepted' AND used = 0
				   AND expires_at IS NOT NULL AND expires_at <= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$now
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * History.
	 * ------------------------------------------------------------------- */

	/**
	 * Add a history entry for an offer.
	 *
	 * @param int    $offer_id Offer ID.
	 * @param string $actor    customer|admin|system.
	 * @param string $action   offer|counter|accept|reject|expire.
	 * @param float  $price    Optional price.
	 * @param string $note     Optional note.
	 * @return int|false
	 */
	public static function add_history( $offer_id, $actor, $action, $price = null, $note = '' ) {
		global $wpdb;

		$ok = $wpdb->insert(
			self::history_table(),
			array(
				'offer_id'   => absint( $offer_id ),
				'actor'      => sanitize_key( $actor ),
				'action'     => sanitize_key( $action ),
				'price'      => is_null( $price ) ? null : (float) $price,
				'note'       => sanitize_textarea_field( $note ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%f', '%s', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get the full history for an offer (oldest first).
	 *
	 * @param int $offer_id Offer ID.
	 * @return object[]
	 */
	public static function get_history( $offer_id ) {
		global $wpdb;
		$table = self::history_table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE offer_id = %d ORDER BY id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $offer_id )
			)
		);
	}
}
