<?php
/**
 * Role-based pricing and agreed-offer enforcement.
 *
 * Wholesale prices are stored as product/variation meta `_wwo_wholesale_price`.
 * Accepted-offer prices are applied at cart calculation time and re-validated
 * against the database — the client never dictates the price.
 *
 * @package WC_Wholesale_Offers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pricing integration with WooCommerce.
 */
class WWO_Pricing {

	const META_KEY = '_wwo_wholesale_price';

	/**
	 * Hook into WooCommerce price + cart pipelines.
	 */
	public function __construct() {
		// Show wholesale prices to approved wholesale customers.
		add_filter( 'woocommerce_product_get_price', array( $this, 'filter_price' ), 20, 2 );
		add_filter( 'woocommerce_product_get_regular_price', array( $this, 'filter_price' ), 20, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( $this, 'filter_price' ), 20, 2 );
		add_filter( 'woocommerce_product_variation_get_regular_price', array( $this, 'filter_price' ), 20, 2 );
		add_filter( 'woocommerce_variation_prices_price', array( $this, 'filter_variation_prices' ), 20, 3 );
		add_filter( 'woocommerce_variation_prices_regular_price', array( $this, 'filter_variation_prices' ), 20, 3 );

		// Bust the variation price cache so wholesale/retail don't bleed across roles.
		add_filter( 'woocommerce_get_variation_prices_hash', array( $this, 'variation_prices_hash' ), 10, 1 );

		// Apply accepted-offer prices in the cart (highest authority, runs late).
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_agreed_prices' ), 1000 );

		// Persist the redeemed offer to the order line and mark it used.
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_offer_to_line_item' ), 10, 4 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'mark_offers_used' ), 10, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'mark_offers_used_from_order' ), 10, 1 );
	}

	/* ---------------------------------------------------------------------
	 * Wholesale price helpers.
	 * ------------------------------------------------------------------- */

	/**
	 * Read the raw wholesale price meta for a product.
	 *
	 * @param WC_Product $product Product.
	 * @return float 0 if not set.
	 */
	public static function get_wholesale_meta( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return 0;
		}
		$value = $product->get_meta( self::META_KEY, true );
		return '' === $value || null === $value ? 0 : (float) $value;
	}

	/**
	 * The base price used as the starting point for offers: wholesale price if
	 * set, otherwise the product's regular price.
	 *
	 * @param WC_Product $product Product.
	 * @return float
	 */
	public static function get_base_wholesale_price( $product ) {
		$wholesale = self::get_wholesale_meta( $product );
		if ( $wholesale > 0 ) {
			return $wholesale;
		}
		// Fall back to the unfiltered regular price.
		return (float) $product->get_regular_price( 'edit' );
	}

	/**
	 * Filter the displayed/effective price for approved wholesale customers.
	 *
	 * @param string     $price   Current price.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function filter_price( $price, $product ) {
		if ( ! WWO_Roles::is_approved_wholesale() ) {
			return $price;
		}
		$wholesale = self::get_wholesale_meta( $product );
		return $wholesale > 0 ? $wholesale : $price;
	}

	/**
	 * Filter cached variation prices for approved wholesale customers.
	 *
	 * @param string               $price     Price.
	 * @param WC_Product_Variation $variation Variation.
	 * @param WC_Product_Variable  $product   Parent.
	 * @return string
	 */
	public function filter_variation_prices( $price, $variation, $product ) {
		if ( ! WWO_Roles::is_approved_wholesale() ) {
			return $price;
		}
		$wholesale = self::get_wholesale_meta( $variation );
		return $wholesale > 0 ? $wholesale : $price;
	}

	/**
	 * Make the variation price cache role-aware.
	 *
	 * @param array $hash Hash parts.
	 * @return array
	 */
	public function variation_prices_hash( $hash ) {
		$hash[] = WWO_Roles::is_approved_wholesale() ? 'wwo_wholesale' : 'wwo_retail';
		return $hash;
	}

	/* ---------------------------------------------------------------------
	 * Accepted-offer enforcement.
	 * ------------------------------------------------------------------- */

	/**
	 * Apply agreed offer prices to matching cart items.
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public function apply_agreed_prices( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id || ! WWO_Roles::is_approved_wholesale( $user_id ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			$product_id   = (int) $cart_item['product_id'];
			$variation_id = (int) ( $cart_item['variation_id'] ?? 0 );

			// Re-validate against the DB on every recalculation.
			$offer = WWO_DB::get_redeemable_offer( $user_id, $product_id, $variation_id );
			if ( $offer && (float) $offer->agreed_price > 0 ) {
				$cart_item['data']->set_price( (float) $offer->agreed_price );

				// Tag the runtime item so we can persist it to the order line.
				$cart_item['data']->update_meta_data( '_wwo_runtime_offer_id', (int) $offer->id );
			}
		}
	}

	/**
	 * Save the redeemed offer ID on the order line item.
	 *
	 * @param WC_Order_Item_Product $item          Line item.
	 * @param string                $cart_item_key Cart key.
	 * @param array                 $values        Cart item values.
	 * @param WC_Order              $order         Order.
	 */
	public function add_offer_to_line_item( $item, $cart_item_key, $values, $order ) {
		$user_id      = $order->get_customer_id();
		$product_id   = (int) $values['product_id'];
		$variation_id = (int) ( $values['variation_id'] ?? 0 );

		if ( ! $user_id ) {
			return;
		}

		$offer = WWO_DB::get_redeemable_offer( $user_id, $product_id, $variation_id );
		if ( $offer ) {
			$item->add_meta_data( '_wwo_offer_id', (int) $offer->id, true );
			$item->add_meta_data(
				__( 'Negotiated price', 'wc-wholesale-offers' ),
				wp_strip_all_tags( wc_price( (float) $offer->agreed_price ) ),
				true
			);
		}
	}

	/**
	 * After an order is placed, mark redeemed offers as used.
	 *
	 * @param int $order_id Order ID.
	 */
	public function mark_offers_used( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$this->mark_offers_used_from_order( $order );
		}
	}

	/**
	 * Mark used from an order object (covers classic + Store API checkout).
	 *
	 * @param WC_Order $order Order.
	 */
	public function mark_offers_used_from_order( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			$offer_id = (int) $item->get_meta( '_wwo_offer_id', true );
			if ( $offer_id ) {
				$offer = WWO_DB::get_offer( $offer_id );
				// Only mark if still redeemable and belongs to this customer.
				if ( $offer && 0 === (int) $offer->used && (int) $offer->user_id === $order->get_customer_id() ) {
					WWO_Offers::mark_used( $offer_id, $order->get_id() );
				}
			}
		}
	}
}
