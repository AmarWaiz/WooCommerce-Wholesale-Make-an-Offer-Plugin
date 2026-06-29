<?php
/**
 * Adds the wholesale price field to products and variations.
 *
 * @package WC_Wholesale_Offers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wholesale price meta box fields.
 */
class WWO_Product_Fields {

	/**
	 * Hook into the product data panels.
	 */
	public function __construct() {
		// Simple products: General tab.
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'simple_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_simple' ) );

		// Variations.
		add_action( 'woocommerce_variation_options_pricing', array( $this, 'variation_field' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation' ), 10, 2 );
	}

	/**
	 * Wholesale price input on the General tab.
	 */
	public function simple_field() {
		woocommerce_wp_text_input(
			array(
				'id'          => WWO_Pricing::META_KEY,
				'label'       => sprintf(
					/* translators: %s: currency symbol */
					__( 'Wholesale price (%s)', 'wc-wholesale-offers' ),
					get_woocommerce_currency_symbol()
				),
				'data_type'   => 'price',
				'desc_tip'    => true,
				'description' => __( 'Price shown to approved wholesale customers. Leave blank to use the regular price.', 'wc-wholesale-offers' ),
			)
		);
	}

	/**
	 * Save the simple-product wholesale price.
	 *
	 * @param WC_Product $product Product object.
	 */
	public function save_simple( $product ) {
		// WooCommerce verifies its own nonce before this hook runs.
		if ( isset( $_POST[ WWO_Pricing::META_KEY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$raw = wc_clean( wp_unslash( $_POST[ WWO_Pricing::META_KEY ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$val = ( '' === $raw ) ? '' : wc_format_decimal( $raw );
			$product->update_meta_data( WWO_Pricing::META_KEY, $val );
		}
	}

	/**
	 * Wholesale price input per variation.
	 *
	 * @param int     $loop           Loop index.
	 * @param array   $variation_data Variation data.
	 * @param WP_Post $variation      Variation post.
	 */
	public function variation_field( $loop, $variation_data, $variation ) {
		$value = get_post_meta( $variation->ID, WWO_Pricing::META_KEY, true );
		woocommerce_wp_text_input(
			array(
				'id'            => WWO_Pricing::META_KEY . '_' . $loop,
				'name'          => WWO_Pricing::META_KEY . '[' . $loop . ']',
				'value'         => $value,
				'label'         => sprintf(
					/* translators: %s: currency symbol */
					__( 'Wholesale price (%s)', 'wc-wholesale-offers' ),
					get_woocommerce_currency_symbol()
				),
				'data_type'     => 'price',
				'wrapper_class' => 'form-row form-row-full',
				'desc_tip'      => true,
				'description'   => __( 'Wholesale price for this variation.', 'wc-wholesale-offers' ),
			)
		);
	}

	/**
	 * Save a variation's wholesale price.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $loop         Loop index.
	 */
	public function save_variation( $variation_id, $loop ) {
		// WooCommerce verifies its own nonce before this hook runs.
		if ( isset( $_POST[ WWO_Pricing::META_KEY ][ $loop ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$raw = wc_clean( wp_unslash( $_POST[ WWO_Pricing::META_KEY ][ $loop ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$val = ( '' === $raw ) ? '' : wc_format_decimal( $raw );
			update_post_meta( $variation_id, WWO_Pricing::META_KEY, $val );
		}
	}
}
