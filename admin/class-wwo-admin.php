<?php
/**
 * Admin area: menus, asset loading, and page controllers.
 *
 * @package WC_Wholesale_Offers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the admin UI for approvals, offers, and settings.
 */
class WWO_Admin {

	/**
	 * Hook menus and assets.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the top-level menu and subpages.
	 */
	public function register_menu() {
		$cap = 'manage_wwo_offers';

		$unread = (int) get_transient( 'wwo_admin_unread' );
		$bubble = $unread > 0 ? ' <span class="awaiting-mod">' . esc_html( $unread ) . '</span>' : '';

		add_menu_page(
			__( 'Wholesale & Offers', 'wc-wholesale-offers' ),
			__( 'Wholesale & Offers', 'wc-wholesale-offers' ) . $bubble,
			$cap,
			'wwo-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-tag',
			56
		);

		add_submenu_page( 'wwo-dashboard', __( 'Dashboard', 'wc-wholesale-offers' ), __( 'Dashboard', 'wc-wholesale-offers' ), $cap, 'wwo-dashboard', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'wwo-dashboard', __( 'Offers', 'wc-wholesale-offers' ), __( 'Offers', 'wc-wholesale-offers' ), $cap, 'wwo-offers', array( $this, 'render_offers' ) );
		add_submenu_page( 'wwo-dashboard', __( 'Wholesale Approvals', 'wc-wholesale-offers' ), __( 'Approvals', 'wc-wholesale-offers' ), $cap, 'wwo-approvals', array( $this, 'render_approvals' ) );
		add_submenu_page( 'wwo-dashboard', __( 'Settings', 'wc-wholesale-offers' ), __( 'Settings', 'wc-wholesale-offers' ), $cap, 'wwo-settings', array( $this, 'render_settings' ) );
	}

	/**
	 * Enqueue admin CSS/JS only on plugin screens (and product edit for the price field).
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		$is_plugin_screen  = false !== strpos( $hook, 'wwo-' );
		$is_product_screen = in_array( $hook, array( 'post.php', 'post-new.php' ), true );

		if ( ! $is_plugin_screen && ! $is_product_screen ) {
			return;
		}

		wp_enqueue_style( 'wwo-admin', WWO_PLUGIN_URL . 'assets/css/wwo-admin.css', array(), wwo_asset_ver( 'assets/css/wwo-admin.css' ) );

		if ( $is_plugin_screen ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script(
				'wwo-admin',
				WWO_PLUGIN_URL . 'assets/js/wwo-admin.js',
				array( 'jquery', 'wp-color-picker' ),
				wwo_asset_ver( 'assets/js/wwo-admin.js' ),
				true
			);
			wp_localize_script(
				'wwo-admin',
				'WWO_Admin',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'wwo_admin' ),
					'i18n'    => array(
						'counterPrompt' => __( 'Enter your counter price:', 'wc-wholesale-offers' ),
						'confirmReject' => __( 'Reject this offer?', 'wc-wholesale-offers' ),
						'confirmApprove'=> __( 'Approve this wholesale account?', 'wc-wholesale-offers' ),
						'confirmDeny'   => __( 'Reject this wholesale account?', 'wc-wholesale-offers' ),
						'error'         => __( 'Something went wrong. Please try again.', 'wc-wholesale-offers' ),
					),
				)
			);
		}
	}

	/* ---------------------------------------------------------------------
	 * Page controllers.
	 * ------------------------------------------------------------------- */

	/**
	 * Dashboard overview with quick counts.
	 */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_wwo_offers' ) ) {
			wp_die( esc_html__( 'Access denied.', 'wc-wholesale-offers' ) );
		}
		// Clear the unread badge when the admin visits.
		delete_transient( 'wwo_admin_unread' );

		$pending_offers = WWO_DB::query_offers( array( 'status' => 'pending', 'per_page' => 1 ) );
		$countered      = WWO_DB::query_offers( array( 'status' => 'countered', 'per_page' => 1 ) );
		$accepted       = WWO_DB::query_offers( array( 'status' => 'accepted', 'per_page' => 1 ) );
		$pending_users  = count( $this->get_pending_wholesale_users() );
		?>
		<div class="wrap wwo-admin-wrap">
			<h1><?php esc_html_e( 'Wholesale & Offers', 'wc-wholesale-offers' ); ?></h1>
			<div class="wwo-cards">
				<?php
				$this->stat_card( __( 'Pending offers', 'wc-wholesale-offers' ), $pending_offers['total'], 'wwo-offers&status=pending' );
				$this->stat_card( __( 'In negotiation', 'wc-wholesale-offers' ), $countered['total'], 'wwo-offers&status=countered' );
				$this->stat_card( __( 'Accepted', 'wc-wholesale-offers' ), $accepted['total'], 'wwo-offers&status=accepted' );
				$this->stat_card( __( 'Wholesale approvals', 'wc-wholesale-offers' ), $pending_users, 'wwo-approvals' );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Output a single stat card.
	 */
	private function stat_card( $label, $value, $page ) {
		printf(
			'<a class="wwo-card-stat" href="%1$s"><span class="wwo-card-stat__num">%2$s</span><span class="wwo-card-stat__label">%3$s</span></a>',
			esc_url( admin_url( 'admin.php?page=' . $page ) ),
			esc_html( number_format_i18n( $value ) ),
			esc_html( $label )
		);
	}

	/**
	 * Offers list-table page.
	 */
	public function render_offers() {
		if ( ! current_user_can( 'manage_wwo_offers' ) ) {
			wp_die( esc_html__( 'Access denied.', 'wc-wholesale-offers' ) );
		}
		require_once WWO_PLUGIN_DIR . 'admin/class-wwo-list-table-offers.php';

		$table = new WWO_List_Table_Offers();
		$table->prepare_items();
		?>
		<div class="wrap wwo-admin-wrap">
			<h1><?php esc_html_e( 'Offers', 'wc-wholesale-offers' ); ?></h1>
			<form method="get">
				<input type="hidden" name="page" value="wwo-offers" />
				<?php
				$table->views();
				$table->search_box( __( 'Search', 'wc-wholesale-offers' ), 'wwo-offer' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Wholesale approvals page.
	 */
	public function render_approvals() {
		if ( ! current_user_can( 'manage_wwo_offers' ) ) {
			wp_die( esc_html__( 'Access denied.', 'wc-wholesale-offers' ) );
		}
		require_once WWO_PLUGIN_DIR . 'admin/class-wwo-list-table-approvals.php';

		$table = new WWO_List_Table_Approvals();
		$table->prepare_items();
		?>
		<div class="wrap wwo-admin-wrap">
			<h1><?php esc_html_e( 'Wholesale Approvals', 'wc-wholesale-offers' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Approve or reject pending wholesale customer accounts.', 'wc-wholesale-offers' ); ?></p>
			<form method="get">
				<input type="hidden" name="page" value="wwo-approvals" />
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Settings page (delegates to WWO_Settings).
	 */
	public function render_settings() {
		$settings = WWO_Plugin::instance()->get( 'settings' );
		if ( $settings ) {
			$settings->render_page();
		}
	}

	/**
	 * Get wholesale users awaiting approval (missing status counts as pending).
	 *
	 * @return WP_User[]
	 */
	public function get_pending_wholesale_users() {
		$users = get_users( array( 'role' => WWO_Roles::ROLE, 'number' => 500 ) );

		return array_values(
			array_filter(
				$users,
				static function ( $user ) {
					$status = WWO_Roles::get_status( $user->ID );
					return ( ! $status || WWO_Roles::STATUS_PENDING === $status );
				}
			)
		);
	}
}
