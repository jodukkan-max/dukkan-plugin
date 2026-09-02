/* Dukkan Loyalty Points — storefront logic. */
(function ( $ ) {
	'use strict';

	function reload() {
		window.location.reload();
	}

	// Keep at most one loyalty box in the DOM. Some themes (e.g. Rey) clone the
	// order-review block for sticky/mobile summaries, which can duplicate the box.
	function dedupeLoyaltyBoxes() {
		var $boxes = $( '.dukkan-loyalty-box' );
		if ( $boxes.length > 1 ) {
			$boxes.slice( 1 ).remove();
		}
	}

	$( document ).ready( function () {
		dedupeLoyaltyBoxes();

		// WooCommerce checkout fragments replace the review block; re-check after.
		$( document.body ).on( 'updated_checkout update_order_review checkout_error', dedupeLoyaltyBoxes );

		// Catch theme-driven cloning that happens outside standard WC events.
		if ( 'MutationObserver' in window ) {
			var observer = new MutationObserver( function ( mutations ) {
				mutations.forEach( function ( mutation ) {
					mutation.addedNodes.forEach( function ( node ) {
						if ( node.nodeType === 1 && ( node.matches && node.matches( '.dukkan-loyalty-box' ) ) || ( node.querySelectorAll && node.querySelectorAll( '.dukkan-loyalty-box' ).length ) ) {
							dedupeLoyaltyBoxes();
						}
					} );
				} );
			} );
			observer.observe( document.body, { childList: true, subtree: true } );
		}
	} );

	$( document ).on( 'click', '.dukkan-loyalty-apply', function () {
		var $box     = $( this ).closest( '.dukkan-loyalty-box' );
		var points   = parseInt( $box.find( '.dukkan-loyalty-input' ).val(), 10 );
		var max      = parseInt( $box.data( 'max' ), 10 );

		if ( ! points || points < 1 || points > max ) {
			alert( 'Please enter a valid number of points.' );
			return;
		}

		$.post( dukkan_loyalty.ajax_url, {
			action: 'dukkan_loyalty_apply',
			nonce: dukkan_loyalty.nonce,
			points: points
		}, function ( res ) {
			if ( res && res.success ) {
				reload();
			} else {
				alert( res && res.data && res.data.message ? res.data.message : 'Something went wrong.' );
			}
		} );
	} );

	$( document ).on( 'click', '.dukkan-loyalty-remove', function () {
		$.post( dukkan_loyalty.ajax_url, {
			action: 'dukkan_loyalty_remove',
			nonce: dukkan_loyalty.nonce
		}, function () {
			reload();
		} );
	} );
} )( jQuery );
