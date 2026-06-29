/**
 * Front-end behaviour for the Make an Offer workflow.
 *
 * @package WC_Wholesale_Offers
 */
( function ( $ ) {
	'use strict';

	var cfg = window.WWO_Public || {};

	/**
	 * Small helper for POSTing to admin-ajax.
	 */
	function post( action, data ) {
		return $.ajax( {
			url: cfg.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: $.extend( { action: action, nonce: cfg.nonce }, data )
		} );
	}

	/**
	 * Build a human-readable reason from a failed jqXHR so the customer (and we)
	 * can see WHY a request failed instead of a generic message.
	 */
	function failReason( jqXHR ) {
		if ( ! cfg.ajaxUrl || ! cfg.nonce ) {
			return 'Offer script is not configured (WWO_Public missing). Clear any page cache and reload.';
		}
		if ( jqXHR && jqXHR.status === 403 ) {
			return 'Your session expired (security check failed). Please reload the page and try again.';
		}
		if ( jqXHR && jqXHR.status === 0 ) {
			return 'Could not reach the server. Check your connection and reload.';
		}
		if ( jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message ) {
			return jqXHR.responseJSON.data.message;
		}
		if ( jqXHR && jqXHR.status ) {
			return ( cfg.i18n.error || 'Something went wrong.' ) + ' (HTTP ' + jqXHR.status + ')';
		}
		return cfg.i18n.error;
	}

	/**
	 * Tabs / form toggling on the offer box.
	 */
	$( document ).on( 'click', '.wwo-open-offer', function () {
		$( this ).closest( '.wwo-offer-box' ).find( '.wwo-offer-form' ).prop( 'hidden', false );
		$( this ).prop( 'hidden', true );
	} );

	$( document ).on( 'click', '.wwo-cancel-offer', function () {
		var box = $( this ).closest( '.wwo-offer-box' );
		box.find( '.wwo-offer-form' ).prop( 'hidden', true );
		box.find( '.wwo-open-offer' ).prop( 'hidden', false );
	} );

	/**
	 * Submit a new offer.
	 */
	$( document ).on( 'click', '.wwo-submit-offer', function () {
		var box     = $( this ).closest( '.wwo-offer-box' );
		var product = box.data( 'product' );
		var price   = box.find( '.wwo-offer-price' ).val();
		var msg     = box.find( '.wwo-offer-message' );
		var btn     = $( this );
		var label   = btn.text();

		if ( ! price || parseFloat( price ) <= 0 ) {
			msg.attr( 'class', 'wwo-offer-message wwo-offer-message--error' ).text( cfg.i18n.error );
			return;
		}

		btn.prop( 'disabled', true ).text( cfg.i18n.submitting );

		post( 'wwo_submit_offer', { product_id: product, variation_id: 0, price: price } )
			.done( function ( res ) {
				if ( res && res.success ) {
					msg.attr( 'class', 'wwo-offer-message wwo-offer-message--success' ).text( res.data.message );
					setTimeout( function () { window.location.reload(); }, 1200 );
				} else {
					msg.attr( 'class', 'wwo-offer-message wwo-offer-message--error' ).text( res.data && res.data.message ? res.data.message : cfg.i18n.error );
					btn.prop( 'disabled', false ).text( label );
				}
			} )
			.fail( function ( jqXHR ) {
				if ( window.console ) {
					window.console.error( 'WWO submit_offer failed', jqXHR.status, jqXHR.responseText );
				}
				msg.attr( 'class', 'wwo-offer-message wwo-offer-message--error' ).text( failReason( jqXHR ) );
				btn.prop( 'disabled', false ).text( label );
			} );
	} );

	/**
	 * Respond to a counter (accept / counter / reject) — used on product page and My Account.
	 */
	$( document ).on( 'click', '.wwo-respond', function () {
		var wrap   = $( this ).closest( '.wwo-offer-actions' );
		var offer  = wrap.data( 'offer' );
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

		wrap.find( 'button' ).prop( 'disabled', true );

		post( 'wwo_customer_respond', { offer_id: offer, offer_action: action, price: price } )
			.done( function ( res ) {
				if ( res && res.success ) {
					window.location.reload();
				} else {
					window.alert( res.data && res.data.message ? res.data.message : cfg.i18n.error );
					wrap.find( 'button' ).prop( 'disabled', false );
				}
			} )
			.fail( function () {
				window.alert( cfg.i18n.error );
				wrap.find( 'button' ).prop( 'disabled', false );
			} );
	} );

	/**
	 * Lightweight polling: refresh statuses + update the account-menu badge.
	 */
	function poll() {
		post( 'wwo_poll', {} ).done( function ( res ) {
			if ( ! res || ! res.success ) {
				return;
			}
			updateBadge( res.data.unread );
		} );
	}

	function updateBadge( count ) {
		var $menu = $( '.woocommerce-MyAccount-navigation-link--wwo-offers a' );
		$menu.find( '.wwo-badge-count' ).remove();
		if ( count > 0 ) {
			$menu.append( ' <span class="wwo-badge-count">' + count + '</span>' );
		}
	}

	/**
	 * Login / register tab switching.
	 */
	function activateTab( root, tab ) {
		root.find( '.wwo-tab' ).removeClass( 'is-active' );
		root.find( '.wwo-tab[data-tab="' + tab + '"]' ).addClass( 'is-active' );
		root.find( '.wwo-form' ).removeClass( 'is-active' );
		root.find( '.wwo-form[data-panel="' + tab + '"]' ).addClass( 'is-active' );
	}

	$( document ).on( 'click', '.wwo-tab, [data-tab-link]', function ( e ) {
		e.preventDefault();
		var root = $( this ).closest( '.wwo-auth' );
		var tab  = $( this ).data( 'tab' ) || $( this ).data( 'tabLink' );
		activateTab( root, tab );
	} );

	// Show the wholesale approval hint when the wholesale role is selected.
	$( document ).on( 'change', 'select[name="account_role"]', function () {
		var isWholesale = $( this ).val() && $( this ).val().indexOf( 'wholesale' ) !== -1;
		$( this ).closest( '.wwo-field' ).find( '.wwo-wholesale-hint' ).prop( 'hidden', ! isWholesale );
	} );

	$( function () {
		// Initialise the auth tabs to their default.
		$( '.wwo-auth' ).each( function () {
			activateTab( $( this ), $( this ).data( 'defaultTab' ) || 'login' );
		} );

		if ( cfg.pollInterval && cfg.ajaxUrl ) {
			window.setInterval( poll, cfg.pollInterval );
			poll();
		}
	} );

} )( jQuery );
