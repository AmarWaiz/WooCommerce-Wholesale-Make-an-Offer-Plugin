/**
 * Admin behaviour: color picker live preview + offer/approval actions.
 *
 * @package WC_Wholesale_Offers
 */
( function ( $ ) {
	'use strict';

	var cfg = window.WWO_Admin || {};

	function post( action, data ) {
		return $.post( cfg.ajaxUrl, $.extend( { action: action, nonce: cfg.nonce }, data ) );
	}

	$( function () {
		/* -----------------------------------------------------------------
		 * Color picker with live CSS-variable preview.
		 * --------------------------------------------------------------- */
		if ( $.fn.wpColorPicker ) {
			$( '.wwo-color-field' ).each( function () {
				var $input  = $( this );
				var cssVar  = $input.data( 'cssvar' );
				$input.wpColorPicker( {
					change: function ( event, ui ) {
						if ( cssVar && ui && ui.color ) {
							document.documentElement.style.setProperty( cssVar, ui.color.toString() );
						}
					},
					clear: function () {
						// no-op; default restored on save.
					}
				} );
			} );
		}

		/* -----------------------------------------------------------------
		 * Offer actions (accept / counter / reject).
		 * --------------------------------------------------------------- */
		$( document ).on( 'click', '.wwo-act', function () {
			var $wrap  = $( this ).closest( '.wwo-row-actions' );
			var offer  = $wrap.data( 'offer' );
			var action = $( this ).data( 'action' );
			var price  = null;

			if ( 'counter' === action ) {
				price = window.prompt( cfg.i18n.counterPrompt );
				if ( null === price || '' === price ) {
					return;
				}
			}
			if ( 'reject' === action && ! window.confirm( cfg.i18n.confirmReject ) ) {
				return;
			}

			$wrap.find( 'button' ).prop( 'disabled', true );

			post( 'wwo_admin_offer_action', { offer_id: offer, offer_action: action, price: price } )
				.done( function ( res ) {
					if ( res && res.success ) {
						window.location.reload();
					} else {
						window.alert( res.data && res.data.message ? res.data.message : cfg.i18n.error );
						$wrap.find( 'button' ).prop( 'disabled', false );
					}
				} )
				.fail( function () {
					window.alert( cfg.i18n.error );
					$wrap.find( 'button' ).prop( 'disabled', false );
				} );
		} );

		/* -----------------------------------------------------------------
		 * Approval actions (approve / reject).
		 * --------------------------------------------------------------- */
		$( document ).on( 'click', '.wwo-approve', function () {
			var $wrap  = $( this ).closest( '.wwo-row-actions' );
			var user   = $wrap.data( 'user' );
			var action = $( this ).data( 'action' );
			var confirmMsg = ( 'approve' === action ) ? cfg.i18n.confirmApprove : cfg.i18n.confirmDeny;

			if ( ! window.confirm( confirmMsg ) ) {
				return;
			}

			$wrap.find( 'button' ).prop( 'disabled', true );

			post( 'wwo_admin_approval', { user_id: user, approval_action: action } )
				.done( function ( res ) {
					if ( res && res.success ) {
						window.location.reload();
					} else {
						window.alert( res.data && res.data.message ? res.data.message : cfg.i18n.error );
						$wrap.find( 'button' ).prop( 'disabled', false );
					}
				} )
				.fail( function () {
					window.alert( cfg.i18n.error );
					$wrap.find( 'button' ).prop( 'disabled', false );
				} );
		} );

		/* -----------------------------------------------------------------
		 * Bulk selection state.
		 * --------------------------------------------------------------- */
		function sprintfCount( tpl, n ) {
			return ( tpl || '%d selected' ).replace( '%d', n );
		}

		// Reflect current selection into the bulk bar (count + enabled state)
		// and the "select all" header checkbox.
		function refreshSelection() {
			var $rows  = $( '.wwo-cb' );
			var $sel   = $rows.filter( ':checked' );
			var n      = $sel.length;

			$( '.wwo-bulk-delete' ).prop( 'disabled', n === 0 );
			$( '.wwo-bulk-count' ).text( n > 0 ? sprintfCount( cfg.i18n.selectedLabel, n ) : '' );

			// Highlight selected rows.
			$rows.each( function () {
				$( this ).closest( 'tr' ).toggleClass( 'wwo-row-selected', this.checked );
			} );

			// Keep the header/footer "select all" boxes in sync.
			$( 'thead .check-column input[type="checkbox"], tfoot .check-column input[type="checkbox"]' )
				.prop( 'checked', $rows.length > 0 && n === $rows.length );
		}

		// "Select all" header/footer checkbox toggles every row checkbox.
		$( document ).on( 'change', 'thead .check-column input[type="checkbox"], tfoot .check-column input[type="checkbox"]', function () {
			$( '.wwo-cb' ).prop( 'checked', this.checked );
			refreshSelection();
		} );

		$( document ).on( 'change', '.wwo-cb', refreshSelection );

		// Gather selected IDs.
		function selectedIds() {
			return $( '.wwo-cb:checked' ).map( function () {
				return $( this ).val();
			} ).get();
		}

		/* -----------------------------------------------------------------
		 * Individual delete (offer / user).
		 * --------------------------------------------------------------- */
		$( document ).on( 'click', '.wwo-delete-offer, .wwo-delete-user', function () {
			var $btn    = $( this );
			var $wrap   = $btn.closest( '.wwo-row-actions' );
			var isOffer = $btn.hasClass( 'wwo-delete-offer' );
			var id      = isOffer ? $wrap.data( 'offer' ) : $wrap.data( 'user' );
			var confirmMsg = isOffer ? cfg.i18n.confirmDeleteOffer : cfg.i18n.confirmDeleteUser;
			var endpoint   = isOffer ? 'wwo_admin_delete_offers' : 'wwo_admin_delete_users';

			if ( ! id || ! window.confirm( confirmMsg ) ) {
				return;
			}

			$wrap.find( 'button' ).prop( 'disabled', true );

			post( endpoint, { ids: [ id ] } )
				.done( function ( res ) {
					if ( res && res.success ) {
						window.location.reload();
					} else {
						window.alert( res.data && res.data.message ? res.data.message : cfg.i18n.error );
						$wrap.find( 'button' ).prop( 'disabled', false );
					}
				} )
				.fail( function () {
					window.alert( cfg.i18n.error );
					$wrap.find( 'button' ).prop( 'disabled', false );
				} );
		} );

		/* -----------------------------------------------------------------
		 * Bulk delete.
		 * --------------------------------------------------------------- */
		$( document ).on( 'click', '.wwo-bulk-delete', function () {
			var $btn  = $( this );
			var type  = $btn.closest( '.wwo-bulk-bar' ).data( 'type' );
			var ids   = selectedIds();

			if ( ! ids.length ) {
				return;
			}

			var isOffer    = ( 'offer' === type );
			var confirmMsg = isOffer ? cfg.i18n.confirmBulkOffers : cfg.i18n.confirmBulkUsers;
			var endpoint   = isOffer ? 'wwo_admin_delete_offers' : 'wwo_admin_delete_users';

			if ( ! window.confirm( confirmMsg ) ) {
				return;
			}

			$btn.prop( 'disabled', true );

			post( endpoint, { ids: ids } )
				.done( function ( res ) {
					if ( res && res.success ) {
						window.location.reload();
					} else {
						window.alert( res.data && res.data.message ? res.data.message : cfg.i18n.error );
						$btn.prop( 'disabled', false );
					}
				} )
				.fail( function () {
					window.alert( cfg.i18n.error );
					$btn.prop( 'disabled', false );
				} );
		} );
	} );

} )( jQuery );
