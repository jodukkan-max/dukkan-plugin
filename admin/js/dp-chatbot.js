/* Dukkan AI Chatbot — admin logic. */
(function ( $ ) {
	'use strict';

	function testConnection() {
		var $result = $( '#dukkan-chatbot-test-result' );
		$result.text( 'Testing…' ).removeClass( 'is-ok is-error' );

		$.post( dukkan_chatbot_admin.url, {
			action: 'dukkan_chatbot_test_connection',
			nonce: dukkan_chatbot_admin.nonce
		}, function ( res ) {
			if ( ! res || ! res.success ) {
				$result.text( 'Failed' ).addClass( 'is-error' );
				return;
			}

			var d = res.data;

			function label( v ) {
				if ( v === true ) return 'OK';
				if ( typeof v === 'string' && v ) return 'FAIL — ' + v;
				return 'FAIL';
			}

			var parts = [];
			parts.push( 'Gemini: ' + label( d.chat ) );
			parts.push( 'Embeddings: ' + label( d.embed ) );
			var ok = d.chat === true && d.embed === true;

			$result.text( parts.join( '  ·  ' ) ).toggleClass( 'is-ok', ok ).toggleClass( 'is-error', ! ok );
		} );
	}

	// Generic batched index rebuild. Loops batches until the server reports
	// `done`, so a single request never hangs on large catalogs.
	function runIndexRebuild( action, $button, $result, countId ) {
		$result.removeClass( 'is-error' );
		$button.prop( 'disabled', true );

		function runBatch() {
			$result.text( 'Rebuilding…' );

			$.post( dukkan_chatbot_admin.url, {
				action: action,
				nonce: dukkan_chatbot_admin.nonce,
				batch: 50
			}, function ( res ) {
				if ( ! res || ! res.success ) {
					$result.text( 'Failed' ).addClass( 'is-error' );
					$button.prop( 'disabled', false );
					return;
				}

				var d = res.data;
				if ( d.done ) {
					$result.text( 'Done — ' + d.indexed + ' indexed, ' + d.failed + ' failed' );
					if ( countId ) {
						$( countId ).text( d.indexed );
					}
					$button.prop( 'disabled', false );
					return;
				}

				$result.text( 'Indexing… ' + d.indexed + ' / ' + d.total );
				runBatch();
			} ).fail( function () {
				$result.text( 'Failed' ).addClass( 'is-error' );
				$button.prop( 'disabled', false );
			} );
		}

		runBatch();
	}

	function rebuildIndex() {
		runIndexRebuild(
			'dukkan_chatbot_rebuild_index',
			$( '#dukkan-chatbot-rebuild' ),
			$( '#dukkan-chatbot-rebuild-result' ),
			'#dukkan-chatbot-index-count'
		);
	}

	function rebuildCategoriesIndex() {
		runIndexRebuild(
			'dukkan_chatbot_rebuild_categories_index',
			$( '#dukkan-chatbot-rebuild-categories' ),
			$( '#dukkan-chatbot-rebuild-categories-result' ),
			'#dukkan-chatbot-cat-count'
		);
	}

	function rebuildPagesIndex() {
		runIndexRebuild(
			'dukkan_chatbot_rebuild_pages_index',
			$( '#dukkan-chatbot-rebuild-pages' ),
			$( '#dukkan-chatbot-rebuild-pages-result' ),
			'#dukkan-chatbot-page-count'
		);
	}

	function rebuildOrdersIndex() {
		runIndexRebuild(
			'dukkan_chatbot_rebuild_orders_index',
			$( '#dukkan-chatbot-rebuild-orders' ),
			$( '#dukkan-chatbot-rebuild-orders-result' ),
			'#dukkan-chatbot-order-count'
		);
	}

	function formatNumber( n ) {
		return String( n ).replace( /\B(?=(\d{3})+(?!\d))/g, ',' );
	}

	$( document ).ready( function () {
		// Master enable switch.
		$( document ).on( 'change', '[data-master-toggle]', function () {
			var $status = $( '[data-status-text]' );
			if ( $( this ).is( ':checked' ) ) {
				$status.text( 'Active' ).addClass( 'is-active' );
			} else {
				$status.text( 'Inactive' ).removeClass( 'is-active' );
			}
		} );

		// Language mode toggle.
		$( '#dukkan-chatbot-language' ).on( 'change', function () {
			$( '#dukkan-chatbot-fixed-language' ).toggle( $( this ).val() === 'fixed' );
		} );

		// Color picker.
		if ( $.fn.wpColorPicker ) {
			$( '.dukkan-chatbot-color' ).wpColorPicker();
		}

		$( '#dukkan-chatbot-test' ).on( 'click', testConnection );
		$( '#dukkan-chatbot-rebuild' ).on( 'click', rebuildIndex );
		$( '#dukkan-chatbot-rebuild-categories' ).on( 'click', rebuildCategoriesIndex );
		$( '#dukkan-chatbot-rebuild-pages' ).on( 'click', rebuildPagesIndex );
		$( '#dukkan-chatbot-rebuild-orders' ).on( 'click', rebuildOrdersIndex );
	} );
} )( jQuery );
