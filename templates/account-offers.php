<?php
/**
 * "My Offers" My Account endpoint content.
 *
 * @var object[] $offers Offers belonging to the current user.
 *
 * @package WC_Wholesale_Offers
 */

defined( 'ABSPATH' ) || exit;

$wwo_labels = WWO_Offers::statuses();
?>
<div class="wwo-account-offers">

	<?php if ( empty( $offers ) ) : ?>
		<p><?php esc_html_e( 'You have not made any offers yet.', 'wc-wholesale-offers' ); ?></p>
	<?php else : ?>

		<table class="wwo-offers-table shop_table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Product', 'wc-wholesale-offers' ); ?></th>
					<th><?php esc_html_e( 'List price', 'wc-wholesale-offers' ); ?></th>
					<th><?php esc_html_e( 'Latest price', 'wc-wholesale-offers' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wc-wholesale-offers' ); ?></th>
					<th><?php esc_html_e( 'Updated', 'wc-wholesale-offers' ); ?></th>
					<th><?php esc_html_e( 'Action', 'wc-wholesale-offers' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $offers as $offer ) : ?>
					<?php
					$product = wc_get_product( $offer->variation_id ? $offer->variation_id : $offer->product_id );
					$name    = $product ? $product->get_name() : __( '(unavailable)', 'wc-wholesale-offers' );
					$link    = $product ? get_permalink( $offer->product_id ) : '';
					$label   = isset( $wwo_labels[ $offer->status ] ) ? $wwo_labels[ $offer->status ] : $offer->status;
					$price   = ( 'accepted' === $offer->status && $offer->agreed_price ) ? $offer->agreed_price : $offer->current_price;
					?>
					<tr data-offer="<?php echo esc_attr( $offer->id ); ?>">
						<td data-title="<?php esc_attr_e( 'Product', 'wc-wholesale-offers' ); ?>">
							<?php if ( $link ) : ?>
								<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $name ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $name ); ?>
							<?php endif; ?>
						</td>
						<td data-title="<?php esc_attr_e( 'List price', 'wc-wholesale-offers' ); ?>"><?php echo wp_kses_post( wc_price( (float) $offer->original_price ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Latest price', 'wc-wholesale-offers' ); ?>"><?php echo wp_kses_post( wc_price( (float) $price ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Status', 'wc-wholesale-offers' ); ?>">
							<span class="wwo-badge wwo-badge--<?php echo esc_attr( $offer->status ); ?>"><?php echo esc_html( $label ); ?></span>
							<?php if ( 'accepted' === $offer->status && $offer->expires_at && ! $offer->used ) : ?>
								<br><small><?php
								printf(
									/* translators: %s: expiry datetime */
									esc_html__( 'Use before %s', 'wc-wholesale-offers' ),
									esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $offer->expires_at ) ) )
								);
								?></small>
							<?php endif; ?>
						</td>
						<td data-title="<?php esc_attr_e( 'Updated', 'wc-wholesale-offers' ); ?>"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $offer->updated_at ) ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Action', 'wc-wholesale-offers' ); ?>" class="wwo-account-actions">
							<?php if ( 'countered' === $offer->status && 'customer' === $offer->turn ) : ?>
								<div class="wwo-offer-actions" data-offer="<?php echo esc_attr( $offer->id ); ?>">
									<button type="button" class="wwo-btn wwo-btn--primary wwo-btn--sm wwo-respond" data-action="accept"><?php esc_html_e( 'Accept', 'wc-wholesale-offers' ); ?></button>
									<?php if ( WWO_Offers::can_counter( $offer ) ) : ?>
										<button type="button" class="wwo-btn wwo-btn--ghost wwo-btn--sm wwo-respond" data-action="counter"><?php esc_html_e( 'Counter', 'wc-wholesale-offers' ); ?></button>
									<?php endif; ?>
									<button type="button" class="wwo-btn wwo-btn--text wwo-btn--sm wwo-respond" data-action="reject"><?php esc_html_e( 'Decline', 'wc-wholesale-offers' ); ?></button>
								</div>
							<?php elseif ( 'accepted' === $offer->status && ! $offer->used && $link ) : ?>
								<a class="wwo-btn wwo-btn--primary wwo-btn--sm" href="<?php echo esc_url( $link ); ?>"><?php esc_html_e( 'Buy now', 'wc-wholesale-offers' ); ?></a>
							<?php else : ?>
								<span class="description">—</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

	<?php endif; ?>
</div>
