<?php
/**
 * Plugin settings, including the live color customizer.
 *
 * Colors are emitted as CSS custom properties on the front end, admin, and the
 * login screen, so nothing is hard-coded in the stylesheets.
 *
 * @package WC_Wholesale_Offers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings registration, sanitisation, and dynamic CSS output.
 */
class WWO_Settings {

	const OPTION_GROUP = 'wwo_settings_group';

	/**
	 * Color option keys mapped to their default values and CSS variable names.
	 *
	 * @return array
	 */
	public static function color_map() {
		return array(
			'wwo_color_primary'   => array( 'default' => '#332A28', 'var' => '--wwo-primary' ),
			'wwo_color_secondary' => array( 'default' => '#F0D1AD', 'var' => '--wwo-secondary' ),
			'wwo_color_light'     => array( 'default' => '#FFFFFF', 'var' => '--wwo-light' ),
			'wwo_color_accent'    => array( 'default' => '#050909', 'var' => '--wwo-accent' ),
		);
	}

	/**
	 * Hook registration + CSS output.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Emit CSS variables everywhere the UI appears.
		add_action( 'wp_head', array( $this, 'output_css_variables' ), 5 );
		add_action( 'admin_head', array( $this, 'output_css_variables' ), 5 );
		add_action( 'login_head', array( $this, 'output_css_variables' ), 5 );
	}

	/**
	 * Register all settings with sanitisation callbacks.
	 */
	public function register_settings() {
		// Colors.
		foreach ( array_keys( self::color_map() ) as $key ) {
			register_setting(
				self::OPTION_GROUP,
				$key,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_hex_color',
				)
			);
		}

		// Behaviour.
		register_setting( self::OPTION_GROUP, 'wwo_max_rounds', array( 'sanitize_callback' => 'absint' ) );
		register_setting( self::OPTION_GROUP, 'wwo_accept_expiry_hrs', array( 'sanitize_callback' => 'absint' ) );
		register_setting( self::OPTION_GROUP, 'wwo_rate_limit_count', array( 'sanitize_callback' => 'absint' ) );
		register_setting( self::OPTION_GROUP, 'wwo_rate_limit_window', array( 'sanitize_callback' => 'absint' ) );
		register_setting( self::OPTION_GROUP, 'wwo_notify_admin_email', array( 'sanitize_callback' => 'sanitize_email' ) );
		register_setting( self::OPTION_GROUP, 'wwo_login_page_id', array( 'sanitize_callback' => 'absint' ) );
		register_setting(
			self::OPTION_GROUP,
			'wwo_auto_approve',
			array(
				'sanitize_callback' => function ( $v ) {
					return 'yes' === $v ? 'yes' : 'no';
				},
			)
		);
	}

	/**
	 * Resolve a sanitised color value with fallback to default.
	 *
	 * @param string $key Option key.
	 * @return string
	 */
	public static function get_color( $key ) {
		$map     = self::color_map();
		$default = isset( $map[ $key ] ) ? $map[ $key ]['default'] : '#000000';
		$value   = sanitize_hex_color( get_option( $key, $default ) );
		return $value ? $value : $default;
	}

	/**
	 * Print the :root CSS custom properties block.
	 */
	public function output_css_variables() {
		$lines = array();
		foreach ( self::color_map() as $key => $meta ) {
			$lines[] = sprintf( '%s: %s;', $meta['var'], esc_attr( self::get_color( $key ) ) );
		}
		// A couple of derived helpers for convenience.
		printf(
			'<style id="wwo-colors">:root{%s}</style>',
			implode( '', $lines ) // values are escaped above.
		);
	}

	/**
	 * Render the settings admin page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_wwo_offers' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wc-wholesale-offers' ) );
		}
		?>
		<div class="wrap wwo-admin-wrap">
			<div class="wwo-page-head">
				<div class="wwo-page-head__title">
					<span class="dashicons dashicons-admin-settings"></span>
					<div>
						<h1><?php esc_html_e( 'Settings', 'wc-wholesale-offers' ); ?></h1>
						<p class="wwo-page-head__sub"><?php esc_html_e( 'Configure brand colors, negotiation behaviour, and wholesale accounts.', 'wc-wholesale-offers' ); ?></p>
					</div>
				</div>
			</div>

			<form method="post" action="options.php" class="wwo-settings-form">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<div class="wwo-panel">
				<h2 class="title"><span class="dashicons dashicons-art"></span> <?php esc_html_e( 'Brand colors', 'wc-wholesale-offers' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Change the plugin colors. The preview updates live; click Save to apply across the site.', 'wc-wholesale-offers' ); ?></p>
				<table class="form-table" role="presentation">
					<?php
					$labels = array(
						'wwo_color_primary'   => __( 'Primary', 'wc-wholesale-offers' ),
						'wwo_color_secondary' => __( 'Secondary', 'wc-wholesale-offers' ),
						'wwo_color_light'     => __( 'Light / background', 'wc-wholesale-offers' ),
						'wwo_color_accent'    => __( 'Accent', 'wc-wholesale-offers' ),
					);
					foreach ( self::color_map() as $key => $meta ) :
						?>
						<tr>
							<th scope="row"><?php echo esc_html( $labels[ $key ] ); ?></th>
							<td>
								<input
									type="text"
									name="<?php echo esc_attr( $key ); ?>"
									value="<?php echo esc_attr( self::get_color( $key ) ); ?>"
									data-cssvar="<?php echo esc_attr( $meta['var'] ); ?>"
									class="wwo-color-field"
								/>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				</div>

				<div class="wwo-panel">
				<h2 class="title"><span class="dashicons dashicons-randomize"></span> <?php esc_html_e( 'Negotiation behaviour', 'wc-wholesale-offers' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Maximum negotiation rounds', 'wc-wholesale-offers' ); ?></th>
						<td><input type="number" min="1" max="20" name="wwo_max_rounds" value="<?php echo esc_attr( get_option( 'wwo_max_rounds', 3 ) ); ?>" class="small-text" />
						<p class="description"><?php esc_html_e( 'Total proposals allowed before only accept/reject remain.', 'wc-wholesale-offers' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Accepted price expiry (hours)', 'wc-wholesale-offers' ); ?></th>
						<td><input type="number" min="0" name="wwo_accept_expiry_hrs" value="<?php echo esc_attr( get_option( 'wwo_accept_expiry_hrs', 48 ) ); ?>" class="small-text" />
						<p class="description"><?php esc_html_e( '0 = never expires. Default 48 hours.', 'wc-wholesale-offers' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Offer rate limit', 'wc-wholesale-offers' ); ?></th>
						<td>
							<input type="number" min="0" name="wwo_rate_limit_count" value="<?php echo esc_attr( get_option( 'wwo_rate_limit_count', 5 ) ); ?>" class="small-text" />
							<?php esc_html_e( 'offers per', 'wc-wholesale-offers' ); ?>
							<input type="number" min="60" name="wwo_rate_limit_window" value="<?php echo esc_attr( get_option( 'wwo_rate_limit_window', 3600 ) ); ?>" class="small-text" />
							<?php esc_html_e( 'seconds', 'wc-wholesale-offers' ); ?>
							<p class="description"><?php esc_html_e( '0 offers disables the limit.', 'wc-wholesale-offers' ); ?></p>
						</td>
					</tr>
				</table>
				</div>

				<div class="wwo-panel">
				<h2 class="title"><span class="dashicons dashicons-groups"></span> <?php esc_html_e( 'Wholesale accounts', 'wc-wholesale-offers' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Auto-approve wholesale', 'wc-wholesale-offers' ); ?></th>
						<td><label><input type="checkbox" name="wwo_auto_approve" value="yes" <?php checked( 'yes', get_option( 'wwo_auto_approve', 'no' ) ); ?> /> <?php esc_html_e( 'Approve new wholesale accounts automatically (skip manual review).', 'wc-wholesale-offers' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Notifications email', 'wc-wholesale-offers' ); ?></th>
						<td><input type="email" name="wwo_notify_admin_email" value="<?php echo esc_attr( get_option( 'wwo_notify_admin_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Where new-offer and approval emails are sent.', 'wc-wholesale-offers' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Login / Account page', 'wc-wholesale-offers' ); ?></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => 'wwo_login_page_id',
									'selected'          => (int) get_option( 'wwo_login_page_id' ),
									'show_option_none'  => esc_html__( '— Select a page —', 'wc-wholesale-offers' ),
									'option_none_value' => 0,
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'The page that contains your [wwo_login_register] form (e.g. your Elementor login page). Used for redirects when a logged-in user opens the login page and as a fallback for messages. Set this to your own page so you can safely delete the auto-created “Account Access” page.', 'wc-wholesale-offers' ); ?></p>
						</td>
					</tr>
				</table>

				</div>

				<?php submit_button( __( 'Save settings', 'wc-wholesale-offers' ), 'primary wwo-save-btn' ); ?>
			</form>

			<div class="wwo-panel wwo-settings-help">
				<h2 class="title"><span class="dashicons dashicons-info-outline"></span> <?php esc_html_e( 'Setup', 'wc-wholesale-offers' ); ?></h2>
				<p><?php esc_html_e( 'Add this shortcode to any page to display the styled login/registration form:', 'wc-wholesale-offers' ); ?></p>
				<code>[wwo_login_register]</code>
				<p><?php esc_html_e( 'Set a wholesale price per product on the product edit screen, under Product data → General (and per variation for variable products).', 'wc-wholesale-offers' ); ?></p>
			</div>
		</div>
		<?php
	}
}
