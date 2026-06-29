<?php
/**
 * Offers admin list table (sortable, filterable).
 *
 * @package WC_Wholesale_Offers
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Displays offers in a standard WordPress list table.
 */
class WWO_List_Table_Offers extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'wwo_offer',
				'plural'   => 'wwo_offers',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Column definitions.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'id'             => __( 'ID', 'wc-wholesale-offers' ),
			'product'        => __( 'Product', 'wc-wholesale-offers' ),
			'customer'       => __( 'Customer', 'wc-wholesale-offers' ),
			'original_price' => __( 'List / wholesale', 'wc-wholesale-offers' ),
			'current_price'  => __( 'Latest offer', 'wc-wholesale-offers' ),
			'status'         => __( 'Status', 'wc-wholesale-offers' ),
			'updated_at'     => __( 'Updated', 'wc-wholesale-offers' ),
			'actions'        => __( 'Actions', 'wc-wholesale-offers' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'id'            => array( 'id', true ),
			'current_price' => array( 'current_price', false ),
			'status'        => array( 'status', false ),
			'updated_at'    => array( 'updated_at', false ),
		);
	}

	/**
	 * Status filter links.
	 *
	 * @return array
	 */
	protected function get_views() {
		$statuses = array_merge( array( '' => __( 'All', 'wc-wholesale-offers' ) ), WWO_Offers::statuses() );
		$current  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$views    = array();

		foreach ( $statuses as $key => $label ) {
			$count = WWO_DB::query_offers( array( 'status' => $key, 'per_page' => 1 ) )['total'];
			$url   = add_query_arg( array( 'page' => 'wwo-offers', 'status' => $key ), admin_url( 'admin.php' ) );
			$class = ( $current === $key ) ? ' class="current"' : '';
			$views[ $key ? $key : 'all' ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
				esc_url( $url ),
				$class,
				esc_html( $label ),
				esc_html( number_format_i18n( $count ) )
			);
		}
		return $views;
	}

	/**
	 * Load items.
	 */
	public function prepare_items() {
		$per_page = 20;
		$paged    = $this->get_pagenum();

		$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'id'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$result = WWO_DB::query_offers(
			array(
				'status'   => $status,
				'orderby'  => $orderby,
				'order'    => $order,
				'per_page' => $per_page,
				'paged'    => $paged,
			)
		);

		$this->items = $result['items'];

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $result['total'] / $per_page ),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	/* ---------------------------------------------------------------------
	 * Column renderers.
	 * ------------------------------------------------------------------- */

	public function column_id( $item ) {
		return '#' . absint( $item->id );
	}

	public function column_product( $item ) {
		$id      = $item->variation_id ? $item->variation_id : $item->product_id;
		$product = wc_get_product( $id );
		if ( ! $product ) {
			return esc_html__( '(deleted product)', 'wc-wholesale-offers' );
		}
		return sprintf(
			'<a href="%s" target="_blank">%s</a>',
			esc_url( get_edit_post_link( $item->product_id ) ),
			esc_html( $product->get_name() )
		);
	}

	public function column_customer( $item ) {
		$user = get_userdata( $item->user_id );
		if ( ! $user ) {
			return esc_html__( '(deleted user)', 'wc-wholesale-offers' );
		}
		return sprintf(
			'<a href="%s">%s</a><br><span class="description">%s</span>',
			esc_url( get_edit_user_link( $item->user_id ) ),
			esc_html( $user->display_name ),
			esc_html( $user->user_email )
		);
	}

	public function column_original_price( $item ) {
		return wp_kses_post( wc_price( (float) $item->original_price ) );
	}

	public function column_current_price( $item ) {
		$html = wp_kses_post( wc_price( (float) $item->current_price ) );
		if ( 'accepted' === $item->status && $item->agreed_price ) {
			$html .= '<br><strong>' . esc_html__( 'Agreed:', 'wc-wholesale-offers' ) . ' ' . wp_kses_post( wc_price( (float) $item->agreed_price ) ) . '</strong>';
		}
		return $html;
	}

	public function column_status( $item ) {
		$labels = WWO_Offers::statuses();
		$label  = isset( $labels[ $item->status ] ) ? $labels[ $item->status ] : $item->status;
		$turn   = $item->turn ? sprintf( '<br><span class="description">%s</span>', esc_html( sprintf( /* translators: %s: party */ __( 'Waiting on: %s', 'wc-wholesale-offers' ), ucfirst( $item->turn ) ) ) ) : '';
		return sprintf( '<span class="wwo-badge wwo-badge--%1$s">%2$s</span>%3$s', esc_attr( $item->status ), esc_html( $label ), $turn );
	}

	public function column_updated_at( $item ) {
		return esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item->updated_at ) ) );
	}

	/**
	 * Action buttons. Real work happens via AJAX in wwo-admin.js.
	 */
	public function column_actions( $item ) {
		$can_act    = in_array( $item->status, array( 'pending', 'countered' ), true ) && 'admin' === $item->turn;
		$can_counter = $can_act && WWO_Offers::can_counter( $item );

		ob_start();
		echo '<div class="wwo-row-actions" data-offer="' . esc_attr( $item->id ) . '">';

		if ( $can_act ) {
			echo '<button type="button" class="button button-primary wwo-act" data-action="accept">' . esc_html__( 'Accept', 'wc-wholesale-offers' ) . '</button> ';
			if ( $can_counter ) {
				echo '<button type="button" class="button wwo-act" data-action="counter">' . esc_html__( 'Counter', 'wc-wholesale-offers' ) . '</button> ';
			}
			echo '<button type="button" class="button wwo-act wwo-act--danger" data-action="reject">' . esc_html__( 'Reject', 'wc-wholesale-offers' ) . '</button>';
		} else {
			echo '<span class="description">' . esc_html__( '—', 'wc-wholesale-offers' ) . '</span>';
		}

		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Fallback renderer.
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
	}

	/**
	 * Empty-state message.
	 */
	public function no_items() {
		esc_html_e( 'No offers found.', 'wc-wholesale-offers' );
	}
}
