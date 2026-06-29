<?php
/**
 * "Make an Offer" block shown on single product pages.
 *
 * @var WC_Product  $product    Current product.
 * @var float       $base_price Base/wholesale price.
 * @var object|null $active     Active offer for this user+product (or null).
 * @var object|null $redeemable Redeemable accepted offer (or null).
 *
 * @package WC_Wholesale_Offers
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wwo-offer-box" data-product="<?php echo esc_attr( $product->get_id() ); ?>">

	<?php if ( $redeemable ) : ?>

		<div class="wwo-offer-status wwo-offer-status--accepted">
			<span class="wwo-badge wwo-badge--accepted"><?php esc_html_e( 'Price agreed', 'wc-wholesale-offers' ); ?></span>
			<p>
				<?php
				printf(
					/* translators: %s: agreed price */
					esc_html__( 'Your agreed price is %s. Add to cart to check out at this price.', 'wc-wholesale-offers' ),
					wp_kses_post( wc_price( (float) $redeemable->agreed_price ) )
				);
				?>
			</p>
			<?php if ( $redeemable->expires_at ) : ?>
				<p class="wwo-offer-expiry">
					<?php
					printf(
						/* translators: %s: expiry date */
						esc_html__( 'Valid until %s.', 'wc-wholesale-offers' ),
						esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $redeemable->expires_at ) ) )
					);
					?>
				</p>
			<?php endif; ?>
		</div>

	<?php elseif ( $active ) : ?>

		<div class="wwo-offer-status" data-offer="<?php echo esc_attr( $active->id ); ?>">
			<?php
			$labels = WWO_Offers::statuses();
			$label  = isset( $labels[ $active->status ] ) ? $labels[ $active->status ] : $active->status;
			?>
			<span class="wwo-badge wwo-badge--<?php echo esc_attr( $active->status ); ?>"><?php echo esc_html( $label ); ?></span>

			<?php if ( 'pending' === $active->status ) : ?>
				<p>
					<?php
					printf(
						/* translators: %s: offered price */
						esc_html__( 'You offered %s. Waiting for a response.', 'wc-wholesale-offers' ),
						wp_kses_post( wc_price( (float) $active->current_price ) )
					);
					?>
				</p>
			<?php elseif ( 'countered' === $active->status && 'customer' === $active->turn ) : ?>
				<p>
					<?php
					printf(
						/* translators: %s: counter price */
						esc_html__( 'We countered with %s.', 'wc-wholesale-offers' ),
						wp_kses_post( wc_price( (float) $active->current_price ) )
					);
					?>
				</p>
				<div class="wwo-offer-actions" data-offer="<?php echo esc_attr( $active->id ); ?>">
					<button type="button" class="wwo-btn wwo-btn--primary wwo-respond" data-action="accept"><?php esc_html_e( 'Accept', 'wc-wholesale-offers' ); ?></button>
					<?php if ( WWO_Offers::can_counter( $active ) ) : ?>
						<button type="button" class="wwo-btn wwo-btn--ghost wwo-respond" data-action="counter"><?php esc_html_e( 'Counter', 'wc-wholesale-offers' ); ?></button>
					<?php endif; ?>
					<button type="button" class="wwo-btn wwo-btn--text wwo-respond" data-action="reject"><?php esc_html_e( 'Decline', 'wc-wholesale-offers' ); ?></button>
				</div>
			<?php elseif ( 'countered' === $active->status ) : ?>
				<p>
					<?php
					printf(
						/* translators: %s: counter price */
						esc_html__( 'You countered with %s. Waiting for a response.', 'wc-wholesale-offers' ),
						wp_kses_post( wc_price( (float) $active->current_price ) )
					);
					?>
				</p>
			<?php endif; ?>
		</div>

	<?php else : ?>

		<button type="button" class="wwo-btn wwo-btn--secondary wwo-open-offer">
			<?php esc_html_e( 'Make an Offer', 'wc-wholesale-offers' ); ?>
		</button>

		<div class="wwo-offer-form" hidden>
			<p class="wwo-offer-form__intro">
				<?php
				printf(
					/* translators: %s: list price */
					esc_html__( 'List price: %s. Enter your proposed price below.', 'wc-wholesale-offers' ),
					wp_kses_post( wc_price( $base_price ) )
				);
				?>
			</p>
			<div class="wwo-offer-input">
				<span class="wwo-offer-input__symbol"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
				<input type="number" step="0.01" min="0" max="<?php echo esc_attr( $base_price ); ?>" class="wwo-offer-price" placeholder="0.00" />
			</div>
			<div class="wwo-offer-form__buttons">
				<button type="button" class="wwo-btn wwo-btn--primary wwo-submit-offer"><?php esc_html_e( 'Submit offer', 'wc-wholesale-offers' ); ?></button>
				<button type="button" class="wwo-btn wwo-btn--text wwo-cancel-offer"><?php esc_html_e( 'Cancel', 'wc-wholesale-offers' ); ?></button>
			</div>
			<div class="wwo-offer-message" role="status" aria-live="polite"></div>
		</div>

	<?php endif; ?>
</div>
