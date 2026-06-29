<?php
/**
 * Pending wholesale approvals list table.
 *
 * @package WC_Wholesale_Offers
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists wholesale customers and their approval status.
 */
class WWO_List_Table_Approvals extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'wwo_approval',
				'plural'   => 'wwo_approvals',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'name'       => __( 'Name', 'wc-wholesale-offers' ),
			'email'      => __( 'Email', 'wc-wholesale-offers' ),
			'registered' => __( 'Registered', 'wc-wholesale-offers' ),
			'status'     => __( 'Status', 'wc-wholesale-offers' ),
			'actions'    => __( 'Actions', 'wc-wholesale-offers' ),
		);
	}

	/**
	 * Load the wholesale users, pending first.
	 *
	 * We fetch by role only and filter by status in PHP. A wholesale user with
	 * no status meta is treated as "pending" so freshly registered accounts are
	 * never hidden by a missing/edge-case meta value.
	 */
	public function prepare_items() {
		$status_filter = isset( $_GET['wstatus'] ) ? sanitize_key( wp_unslash( $_GET['wstatus'] ) ) : 'pending'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $status_filter, array( 'pending', 'approved', 'rejected' ), true ) ) {
			$status_filter = 'pending';
		}

		$users = get_users(
			array(
				'role'    => WWO_Roles::ROLE,
				'orderby' => 'registered',
				'order'   => 'DESC',
				'number'  => 500,
			)
		);

		$this->items = array_values(
			array_filter(
				$users,
				static function ( $user ) use ( $status_filter ) {
					$status = WWO_Roles::get_status( $user->ID );
					$status = $status ? $status : WWO_Roles::STATUS_PENDING;
					return $status === $status_filter;
				}
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), array() );
	}

	/**
	 * Filter links by status.
	 *
	 * @return array
	 */
	protected function get_views() {
		$current = isset( $_GET['wstatus'] ) ? sanitize_key( wp_unslash( $_GET['wstatus'] ) ) : 'pending'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$views   = array();
		$map     = array(
			'pending'  => __( 'Pending', 'wc-wholesale-offers' ),
			'approved' => __( 'Approved', 'wc-wholesale-offers' ),
			'rejected' => __( 'Rejected', 'wc-wholesale-offers' ),
		);
		foreach ( $map as $key => $label ) {
			$url   = add_query_arg( array( 'page' => 'wwo-approvals', 'wstatus' => $key ), admin_url( 'admin.php' ) );
			$class = ( $current === $key ) ? ' class="current"' : '';
			$views[ $key ] = sprintf( '<a href="%1$s"%2$s>%3$s</a>', esc_url( $url ), $class, esc_html( $label ) );
		}
		return $views;
	}

	public function column_name( $user ) {
		return sprintf( '<a href="%s">%s</a>', esc_url( get_edit_user_link( $user->ID ) ), esc_html( $user->display_name ) );
	}

	public function column_email( $user ) {
		return sprintf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $user->user_email ) );
	}

	public function column_registered( $user ) {
		return esc_html( date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered ) ) );
	}

	public function column_status( $user ) {
		$status = WWO_Roles::get_status( $user->ID );
		$status = $status ? $status : 'pending';
		return sprintf( '<span class="wwo-badge wwo-badge--%1$s">%2$s</span>', esc_attr( $status ), esc_html( ucfirst( $status ) ) );
	}

	public function column_actions( $user ) {
		$status = WWO_Roles::get_status( $user->ID );
		ob_start();
		echo '<div class="wwo-row-actions" data-user="' . esc_attr( $user->ID ) . '">';
		if ( WWO_Roles::STATUS_APPROVED !== $status ) {
			echo '<button type="button" class="button button-primary wwo-approve" data-action="approve">' . esc_html__( 'Approve', 'wc-wholesale-offers' ) . '</button> ';
		}
		if ( WWO_Roles::STATUS_REJECTED !== $status ) {
			echo '<button type="button" class="button wwo-approve wwo-act--danger" data-action="reject">' . esc_html__( 'Reject', 'wc-wholesale-offers' ) . '</button>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	public function display_views() {
		$views = $this->get_views();
		echo '<ul class="subsubsub">';
		$last = array_key_last( $views );
		foreach ( $views as $key => $view ) {
			$sep = ( $key === $last ) ? '' : ' | ';
			echo '<li>' . wp_kses_post( $view ) . $sep . '</li>'; // phpcs:ignore
		}
		echo '</ul>';
	}

	public function no_items() {
		esc_html_e( 'No wholesale accounts found for this status.', 'wc-wholesale-offers' );
	}

	/**
	 * Render the views above the table.
	 */
	public function display() {
		$this->display_views();
		parent::display();
	}
}
