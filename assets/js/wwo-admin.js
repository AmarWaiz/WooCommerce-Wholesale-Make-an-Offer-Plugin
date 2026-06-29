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
	} );

} )( jQuery );
