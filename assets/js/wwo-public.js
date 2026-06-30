/**
 * Front-end behaviour for the Make an Offer workflow.
 *
 * @package WC_Wholesale_Offers
 */
( function ( $ ) {
	'use strict';

	// Bump this when editing this file so you can confirm in the browser console
	// that the latest version is actually loaded (not a cached/combined copy).
	var WWO_JS_VERSION = '1.2.0';
	if ( window.console && window.console.info ) {
		window.console.info( '[WWO] wwo-public.js loaded, v' + WWO_JS_VERSION );
	}

	var cfg = window.WWO_Public || {};

	/**
	 * Low-level POST to admin-ajax with an explicit nonce.
	 */
	function rawPost( action, data, nonce ) {
		return $.ajax( {
			url: cfg.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: $.extend( { action: action, nonce: nonce }, data )
		} );
	}

	/**
	 * Fetch a fresh nonce for the current user and store it. Resolves with the
	 * new nonce; REJECTS if admin-ajax considers us logged out (which is the
	 * real cause when a fresh nonce still fails the security check).
	 */
	function refreshNonce() {
		var dfd = $.Deferred();
		rawPost( 'wwo_refresh_nonce', {}, '' )
			.done( function ( res ) {
				if ( res && res.success && res.data && res.data.nonce ) {
					cfg.nonce = res.data.nonce;
					dfd.resolve( cfg.nonce );
				} else {
					dfd.reject( { _wwoLoggedOut: true } );
				}
			} )
			.fail( function ( jqXHR ) {
				// 403 here means admin-ajax sees the request as logged out.
				dfd.reject( { _wwoLoggedOut: ( jqXHR && jqXHR.status === 403 ), status: jqXHR ? jqXHR.status : 0 } );
			} );
		return dfd.promise();
	}

	/**
	 * POST that transparently refreshes the nonce and retries once on a 403
	 * ("security check failed"), then resolves/rejects like a normal request.
	 */
	/**
	 * Is this failure specifically a nonce/security failure (vs. a real business
	 * error like "not approved")? Only those should trigger a nonce refresh.
	 */
	function isNonceFailure( jqXHR ) {
		if ( ! jqXHR || jqXHR.status !== 403 ) {
			return false;
		}
		var rj = jqXHR.responseJSON;
		// Our server tags genuine nonce failures with code 'bad_nonce'.
		if ( rj && rj.data && rj.data.code === 'bad_nonce' ) {
			return true;
		}
		// A bare "-1"/empty body (legacy check_ajax_referer) is also a nonce fail.
		if ( ! rj && ( jqXHR.responseText === '-1' || jqXHR.responseText === '0' || jqXHR.responseText === '' ) ) {
			return true;
		}
		return false;
	}

	function post( action, data ) {
		var dfd = $.Deferred();

		rawPost( action, data, cfg.nonce )
			.done( function ( res ) { dfd.resolve( res ); } )
			.fail( function ( jqXHR ) {
				if ( isNonceFailure( jqXHR ) ) {
					// Stale/cached nonce — refresh once and retry.
					refreshNonce()
						.done( function () {
							rawPost( action, data, cfg.nonce )
								.done( function ( res ) { dfd.resolve( res ); } )
								.fail( function ( jq2 ) { dfd.reject( jq2 ); } );
						} )
						.fail( function ( info ) {
							// Could not get a valid nonce → genuinely logged out at admin-ajax.
							dfd.reject( $.extend( { status: 403 }, info ) );
						} );
				} else {
					// Real error (e.g. not approved): surface the server message as-is.
					dfd.reject( jqXHR );
				}
			} );

		return dfd.promise();
	}

	/**
	 * Build a human-readable reason from a failed request so the customer (and
	 * we) can see WHY it failed instead of a generic message.
	 */
	function failReason( jqXHR ) {
		if ( ! cfg.ajaxUrl || ! cfg.nonce ) {
			return 'Offer script is not configured (WWO_Public missing). Clear any page cache and reload.';
		}
		if ( jqXHR && jqXHR._wwoLoggedOut ) {
			return ( cfg.i18n && cfg.i18n.loggedOut ) ? cfg.i18n.loggedOut
				: 'You appear to be logged out on this connection. Please reload and sign in again.';
		}
		// Prefer an explicit message from the server (e.g. "not approved",
		// "offer too high"), even on a 403 — only fall back to generic text.
		if ( jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message ) {
			return jqXHR.responseJSON.data.message;
		}
		if ( jqXHR && jqXHR.status === 403 ) {
			return 'Your session expired (security check failed). Please reload the page and try again.';
		}
		if ( jqXHR && jqXHR.status === 0 ) {
			return 'Could not reach the server. Check your connection and reload.';
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

	// Show / hide password toggle (eye icon).
	$( document ).on( 'click', '.wwo-eye', function () {
		var $btn   = $( this );
		var $input = $btn.closest( '.wwo-field' ).find( 'input' ).first();
		var reveal = $input.attr( 'type' ) === 'password';

		$input.attr( 'type', reveal ? 'text' : 'password' );
		$btn.attr( 'aria-label', reveal ? 'Hide password' : 'Show password' );

		var show = $btn.data( 'show' );
		var hide = $btn.data( 'hide' );
		if ( show && hide ) {
			$btn.html( reveal ? hide : show );
		}
	} );

	$( function () {
		// Initialise the auth tabs to their default.
		$( '.wwo-auth' ).each( function () {
			activateTab( $( this ), $( this ).data( 'defaultTab' ) || 'login' );
		} );

		// If an offer UI is on the page, proactively refresh the nonce so the
		// first action works even when the page was served from cache.
		if ( cfg.ajaxUrl && ( $( '.wwo-offer-box' ).length || $( '.wwo-offer-actions' ).length ) ) {
			refreshNonce();
		}

		if ( cfg.pollInterval && cfg.ajaxUrl ) {
			window.setInterval( poll, cfg.pollInterval );
			poll();
		}
	} );

} )( jQuery );
