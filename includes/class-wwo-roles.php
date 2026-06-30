<?php
/**
 * Role & capability management plus user-status helpers.
 *
 * @package WC_Wholesale_Offers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the wholesale role and exposes status helpers used across the plugin.
 */
class WWO_Roles {

	const ROLE            = 'wholesale_customer';
	const STATUS_META     = 'wwo_wholesale_status';
	const STATUS_PENDING  = 'pending';
	const STATUS_APPROVED = 'approved';
	const STATUS_REJECTED = 'rejected';

	/**
	 * Constructor: make sure the role and capabilities always exist.
	 */
	public function __construct() {
		// Self-heal the role on every load (cheap; only writes when missing).
		add_action( 'init', array( $this, 'ensure_role_exists' ), 1 );
		add_action( 'admin_init', array( $this, 'maybe_grant_admin_cap' ) );
	}

	/**
	 * Guarantee the wholesale role AND its capabilities exist, even if the role
	 * was created by an earlier version (add_role() does not update the caps of
	 * an already-existing role, so we repair them explicitly here).
	 */
	public function ensure_role_exists() {
		$role = get_role( self::ROLE );

		if ( ! $role ) {
			self::add_roles();
			$role = get_role( self::ROLE );
		}

		// Repair the offer capability on the wholesale role if missing.
		if ( $role && ! $role->has_cap( 'wwo_make_offer' ) ) {
			$role->add_cap( 'wwo_make_offer' );
		}

		// Repair administrator capabilities.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			if ( ! $admin->has_cap( 'manage_wwo_offers' ) ) {
				$admin->add_cap( 'manage_wwo_offers' );
			}
			if ( ! $admin->has_cap( 'wwo_make_offer' ) ) {
				$admin->add_cap( 'wwo_make_offer' );
			}
		}
	}

	/**
	 * Create the wholesale role and grant the admin management capability.
	 * Called on activation.
	 */
	public static function add_roles() {
		// Wholesale customers behave like customers but can make offers.
		add_role(
			self::ROLE,
			__( 'Wholesale Customer', 'wc-wholesale-offers' ),
			array(
				'read'         => true,
				'wwo_make_offer' => true,
			)
		);

		// Give administrators the capability to manage offers/approvals.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'manage_wwo_offers' );
			$admin->add_cap( 'wwo_make_offer' );
		}

		// Shop managers, if present, also manage offers.
		$shop_manager = get_role( 'shop_manager' );
		if ( $shop_manager ) {
			$shop_manager->add_cap( 'manage_wwo_offers' );
		}
	}

	/**
	 * Self-heal the admin capability in case the role was created before this
	 * plugin was installed.
	 */
	public function maybe_grant_admin_cap() {
		if ( current_user_can( 'administrator' ) && ! current_user_can( 'manage_wwo_offers' ) ) {
			$admin = get_role( 'administrator' );
			if ( $admin ) {
				$admin->add_cap( 'manage_wwo_offers' );
			}
		}
	}

	/* ---------------------------------------------------------------------
	 * Static helpers (used throughout the plugin).
	 * ------------------------------------------------------------------- */

	/**
	 * Whether the user holds the wholesale role (regardless of approval).
	 *
	 * @param int|null $user_id User ID, defaults to current user.
	 * @return bool
	 */
	public static function is_wholesale( $user_id = null ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		$user = get_userdata( $user_id );
		return $user && in_array( self::ROLE, (array) $user->roles, true );
	}

	/**
	 * Get the wholesale approval status for a user.
	 *
	 * @param int $user_id User ID.
	 * @return string One of pending|approved|rejected, or empty string.
	 */
	public static function get_status( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return '';
		}
		return (string) get_user_meta( $user_id, self::STATUS_META, true );
	}

	/**
	 * Whether the user is an APPROVED wholesale customer.
	 *
	 * @param int|null $user_id User ID, defaults to current user.
	 * @return bool
	 */
	public static function is_approved_wholesale( $user_id = null ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id || ! self::is_wholesale( $user_id ) ) {
			return false;
		}
		return self::STATUS_APPROVED === self::get_status( $user_id );
	}

	/**
	 * Set a user's wholesale status.
	 *
	 * @param int    $user_id User ID.
	 * @param string $status  Status slug.
	 */
	public static function set_status( $user_id, $status ) {
		$allowed = array( self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED );
		if ( in_array( $status, $allowed, true ) ) {
			update_user_meta( absint( $user_id ), self::STATUS_META, $status );
		}
	}
}

/**
 * Convenience wrapper used by templates and other code.
 *
 * @param int|null $user_id User ID.
 * @return bool
 */
function wwo_is_approved_wholesale( $user_id = null ) {
	return WWO_Roles::is_approved_wholesale( $user_id );
}
