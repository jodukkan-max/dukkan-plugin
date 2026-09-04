/* Dukkan AI Chatbot — admin logic. */
(function ( $ ) {
	'use strict';

	function escHtml( s ) {
		return String( s == null ? '' : s )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

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
			var ok = true;
			var parts = [];
			parts.push( 'DeepSeek: ' + ( d.deepseek === true ? 'OK' : 'FAIL' ) );
			parts.push( 'OpenAI: ' + ( d.openai === true ? 'OK' : 'FAIL' ) );
			ok = d.deepseek === true && d.openai === true;

			$result.text( parts.join( '  ·  ' ) ).toggleClass( 'is-ok', ok ).toggleClass( 'is-error', ! ok );
		} );
	}

	function rebuildIndex() {
		var $result = $( '#dukkan-chatbot-rebuild-result' );
		$result.text( 'Rebuilding…' );

		$.post( dukkan_chatbot_admin.url, {
			action: 'dukkan_chatbot_rebuild_index',
			nonce: dukkan_chatbot_admin.nonce
		}, function ( res ) {
			if ( ! res || ! res.success ) {
				$result.text( 'Failed' ).addClass( 'is-error' );
				return;
			}
			$result.text( 'Done — ' + res.data.indexed + ' indexed, ' + res.data.failed + ' failed' );
			$( '#dukkan-chatbot-index-count' ).text( res.data.indexed );
		} );
	}

	function loadLogs() {
		var $logs = $( '#dukkan-chatbot-logs' );
		$.get( dukkan_chatbot_admin.url, {
			action: 'dukkan_chatbot_logs',
			nonce: dukkan_chatbot_admin.nonce
		}, function ( res ) {
			if ( ! res || ! res.success || ! res.data.length ) {
				$logs.html( '<p>No conversations yet.</p>' );
				return;
			}

			var html = '<table class="widefat striped">';
			html += '<thead><tr><th>Date</th><th>User</th><th>Message</th><th>Reply</th><th></th></tr></thead><tbody>';
			res.data.forEach( function ( row ) {
				var who = row.user_id ? '#' + row.user_id : 'guest';
				html += '<tr>';
				html += '<td>' + escHtml( row.created_at ) + '</td>';
				html += '<td>' + escHtml( who ) + '</td>';
				html += '<td class="dukkan-chatbot-log-msg">' + escHtml( row.message ) + '</td>';
				html += '<td class="dukkan-chatbot-log-reply">' + escHtml( row.reply ) + '</td>';
				html += '<td>' + ( row.handoff == 1 ? '<span class="dukkan-chatbot-log-handoff">handoff</span>' : '' ) + '</td>';
				html += '</tr>';
			} );
			html += '</tbody></table>';

			$logs.html( html );
		} );
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

		// Load logs when the chatbot tab is active.
		if ( $( '.dukkan-chatbot-settings' ).length ) {
			loadLogs();
		}
	} );
} )( jQuery );
