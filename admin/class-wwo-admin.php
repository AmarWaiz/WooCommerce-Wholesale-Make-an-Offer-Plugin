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
						'confirmDeleteOffer'  => __( 'Permanently delete this offer? This cannot be undone.', 'wc-wholesale-offers' ),
						'confirmDeleteUser'   => __( 'Permanently delete this wholesale account? This cannot be undone.', 'wc-wholesale-offers' ),
						'confirmBulkOffers'   => __( 'Permanently delete the selected offers? This cannot be undone.', 'wc-wholesale-offers' ),
						'confirmBulkUsers'    => __( 'Permanently delete the selected wholesale accounts? This cannot be undone.', 'wc-wholesale-offers' ),
						'selectedLabel'       => __( '%d selected', 'wc-wholesale-offers' ),
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
		$total_offers   = WWO_DB::query_offers( array( 'per_page' => 1 ) );
		$pending_users  = count( $this->get_pending_wholesale_users() );
		?>
		<div class="wrap wwo-admin-wrap">
			<div class="wwo-page-head">
				<div class="wwo-page-head__title">
					<span class="dashicons dashicons-tag"></span>
					<div>
						<h1><?php esc_html_e( 'Wholesale & Offers', 'wc-wholesale-offers' ); ?></h1>
						<p class="wwo-page-head__sub"><?php esc_html_e( 'Overview of price negotiations and wholesale account requests.', 'wc-wholesale-offers' ); ?></p>
					</div>
				</div>
			</div>

			<div class="wwo-cards">
				<?php
				$this->stat_card( __( 'Pending offers', 'wc-wholesale-offers' ), $pending_offers['total'], 'wwo-offers&status=pending', 'dashicons-clock', 'pending' );
				$this->stat_card( __( 'In negotiation', 'wc-wholesale-offers' ), $countered['total'], 'wwo-offers&status=countered', 'dashicons-randomize', 'countered' );
				$this->stat_card( __( 'Accepted', 'wc-wholesale-offers' ), $accepted['total'], 'wwo-offers&status=accepted', 'dashicons-yes-alt', 'accepted' );
				$this->stat_card( __( 'Wholesale approvals', 'wc-wholesale-offers' ), $pending_users, 'wwo-approvals', 'dashicons-groups', 'approvals' );
				?>
			</div>

			<div class="wwo-panel wwo-quick-links">
				<h2><?php esc_html_e( 'Quick actions', 'wc-wholesale-offers' ); ?></h2>
				<div class="wwo-quick-links__grid">
					<a class="wwo-quick-link" href="<?php echo esc_url( admin_url( 'admin.php?page=wwo-offers' ) ); ?>">
						<span class="dashicons dashicons-cart"></span>
						<span>
							<strong><?php esc_html_e( 'Manage offers', 'wc-wholesale-offers' ); ?></strong>
							<?php printf( esc_html__( '%s total in the system', 'wc-wholesale-offers' ), '<em>' . esc_html( number_format_i18n( $total_offers['total'] ) ) . '</em>' ); // phpcs:ignore ?>
						</span>
					</a>
					<a class="wwo-quick-link" href="<?php echo esc_url( admin_url( 'admin.php?page=wwo-approvals' ) ); ?>">
						<span class="dashicons dashicons-id"></span>
						<span>
							<strong><?php esc_html_e( 'Review approvals', 'wc-wholesale-offers' ); ?></strong>
							<?php esc_html_e( 'Approve or reject wholesale accounts', 'wc-wholesale-offers' ); ?>
						</span>
					</a>
					<a class="wwo-quick-link" href="<?php echo esc_url( admin_url( 'admin.php?page=wwo-settings' ) ); ?>">
						<span class="dashicons dashicons-admin-settings"></span>
						<span>
							<strong><?php esc_html_e( 'Settings', 'wc-wholesale-offers' ); ?></strong>
							<?php esc_html_e( 'Colors, negotiation rules & accounts', 'wc-wholesale-offers' ); ?>
						</span>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Output a single stat card.
	 *
	 * @param string $label Card label.
	 * @param int    $value Count to show.
	 * @param string $page  Target admin page (query fragment).
	 * @param string $icon  Dashicon class.
	 * @param string $tone  Tone modifier for coloring.
	 */
	private function stat_card( $label, $value, $page, $icon = 'dashicons-chart-bar', $tone = 'default' ) {
		printf(
			'<a class="wwo-card-stat wwo-card-stat--%5$s" href="%1$s"><span class="wwo-card-stat__icon"><span class="dashicons %4$s"></span></span><span class="wwo-card-stat__body"><span class="wwo-card-stat__num">%2$s</span><span class="wwo-card-stat__label">%3$s</span></span></a>',
			esc_url( admin_url( 'admin.php?page=' . $page ) ),
			esc_html( number_format_i18n( $value ) ),
			esc_html( $label ),
			esc_attr( $icon ),
			esc_attr( $tone )
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
			<div class="wwo-page-head">
				<div class="wwo-page-head__title">
					<span class="dashicons dashicons-cart"></span>
					<div>
						<h1><?php esc_html_e( 'Offers', 'wc-wholesale-offers' ); ?></h1>
						<p class="wwo-page-head__sub"><?php esc_html_e( 'Review, respond to, and manage customer price offers.', 'wc-wholesale-offers' ); ?></p>
					</div>
				</div>
			</div>

			<div class="wwo-panel wwo-table-panel">
				<form method="get">
					<input type="hidden" name="page" value="wwo-offers" />
					<?php $table->views(); ?>
					<div class="wwo-table-toolbar">
						<?php $this->bulk_bar( 'offer' ); ?>
						<?php $table->search_box( __( 'Search', 'wc-wholesale-offers' ), 'wwo-offer' ); ?>
					</div>
					<?php $table->display(); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the bulk-delete toolbar shared by the list tables.
	 *
	 * @param string $type 'offer' or 'user' — decides which delete endpoint JS calls.
	 */
	private function bulk_bar( $type ) {
		printf(
			'<div class="wwo-bulk-bar" data-type="%1$s">
				<button type="button" class="button wwo-bulk-delete" disabled>
					<span class="dashicons dashicons-trash"></span> %2$s
				</button>
				<span class="wwo-bulk-count" aria-live="polite"></span>
			</div>',
			esc_attr( $type ),
			esc_html__( 'Delete selected', 'wc-wholesale-offers' )
		);
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
			<div class="wwo-page-head">
				<div class="wwo-page-head__title">
					<span class="dashicons dashicons-groups"></span>
					<div>
						<h1><?php esc_html_e( 'Wholesale Approvals', 'wc-wholesale-offers' ); ?></h1>
						<p class="wwo-page-head__sub"><?php esc_html_e( 'Approve or reject pending wholesale customer accounts.', 'wc-wholesale-offers' ); ?></p>
					</div>
				</div>
			</div>

			<div class="wwo-panel wwo-table-panel">
				<form method="get">
					<input type="hidden" name="page" value="wwo-approvals" />
					<?php $table->display_views(); ?>
					<div class="wwo-table-toolbar">
						<?php $this->bulk_bar( 'user' ); ?>
					</div>
					<?php $table->display_table_only(); ?>
				</form>
			</div>
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
