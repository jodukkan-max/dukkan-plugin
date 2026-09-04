/* Dukkan Loyalty Points — admin logic.
 *
 * Self-contained combo multi-select for the exclusion pickers plus the
 * customer-balance lookup. Reuses the .wpldp-combo* styles from
 * dp-product-addon.css.
 */
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

	function comboTags( $combo ) {
		var $form = $combo.closest( 'form' );
		return $form.find( '.wpldp-combo-tags[data-tags-for="' + $combo.data( 'combo' ) + '"]' );
	}

	function comboRenderMenu( $combo, items ) {
		var $menu = $combo.find( '.wpldp-combo-menu' );
		$menu.empty();

		var selectedIds = comboTags( $combo ).find( '.wpldp-combo-hidden' ).map( function () {
			return String( $( this ).val() );
		} ).get();

		var filtered = ( items || [] ).filter( function ( it ) {
			return selectedIds.indexOf( String( it.id ) ) === -1;
		} );

		if ( ! filtered.length ) {
			$menu.append( '<div class="wpldp-combo-empty">' + escHtml( 'No results found' ) + '</div>' );
		} else {
			filtered.forEach( function ( it ) {
				var label = it.label || it.name || '';
				$menu.append(
					'<div class="wpldp-combo-option" data-id="' + escHtml( it.id ) + '" data-label="' + escHtml( label ) + '">' + escHtml( label ) + '</div>'
				);
			} );
		}
		$menu.show();
	}

	function comboAddTag( $combo, id, label ) {
		var idStr = String( id );
		var $tags = comboTags( $combo );
		if ( $tags.find( '.wpldp-combo-hidden[value="' + idStr + '"]' ).length ) {
			return;
		}

		var $tag = $( '<span class="wpldp-combo-tag"></span>' );
		$( '<span class="wpldp-combo-tag-text"></span>' ).text( label ).appendTo( $tag );
		$( '<button type="button" class="wpldp-combo-tag-remove" aria-label="Remove">&times;</button>' )
			.attr( 'data-id', idStr )
			.appendTo( $tag );
		$tags.append( $tag );

		$( '<input>' ).attr( {
			type: 'hidden',
			'class': 'wpldp-combo-hidden',
			name: $combo.data( 'name' ),
			value: idStr
		} ).appendTo( $tags );

		$tags.addClass( 'is-visible' );
	}

	function comboRemoveTag( $combo, id ) {
		var idStr = String( id );
		var $tags = comboTags( $combo );
		$tags.find( '.wpldp-combo-tag' ).each( function () {
			if ( String( $( this ).find( '.wpldp-combo-tag-remove' ).data( 'id' ) ) === idStr ) {
				$( this ).remove();
			}
		} );
		$tags.find( '.wpldp-combo-hidden' ).each( function () {
			if ( String( $( this ).val() ) === idStr ) {
				$( this ).remove();
			}
		} );
		if ( ! $tags.find( '.wpldp-combo-tag' ).length ) {
			$tags.removeClass( 'is-visible' );
		}
	}

	function searchProducts( $combo, q ) {
		$.get( dukkan_loyalty_admin.url, {
			action: 'dukkan_loyalty_search_products',
			nonce: dukkan_loyalty_admin.nonce,
			q: q
		}, function ( res ) {
			var items = ( ( res && res.success ) ? res.data : [] ).map( function ( p ) {
				return { id: p.id, label: p.name };
			} );
			comboRenderMenu( $combo, items );
		} );
	}

	function searchCategories( $combo, q ) {
		$.get( dukkan_loyalty_admin.url, {
			action: 'dukkan_loyalty_search_categories',
			nonce: dukkan_loyalty_admin.nonce,
			q: q
		}, function ( res ) {
			var items = ( ( res && res.success ) ? res.data : [] ).map( function ( c ) {
				return { id: c.id, label: c.name };
			} );
			comboRenderMenu( $combo, items );
		} );
	}

	function searchCustomers( $combo, q ) {
		$.get( dukkan_loyalty_admin.url, {
			action: 'dukkan_loyalty_search_customers',
			nonce: dukkan_loyalty_admin.nonce,
			q: q
		}, function ( res ) {
			var $menu = $combo.find( '.wpldp-combo-menu' );
			var items = ( res && res.success ) ? res.data : [];
			$menu.empty();

			if ( ! items.length ) {
				$menu.append( '<div class="wpldp-combo-empty">' + escHtml( 'No customers found' ) + '</div>' );
			} else {
				items.forEach( function ( u ) {
					var $option = $( '<div class="wpldp-combo-option dukkan-loyalty-customer-option"></div>' );
					$option.attr( 'data-id', escHtml( u.id ) );
					$option.attr( 'data-label', escHtml( u.display_name ) );
					$option.append(
						'<span class="dukkan-loyalty-customer-name">' + escHtml( u.display_name ) + '</span>' +
						'<span class="dukkan-loyalty-customer-email">' + escHtml( u.email ) + '</span>'
					);
					$menu.append( $option );
				} );
			}
			$menu.show();
		} );
	}

	function comboOpen( $combo ) {
		var type = $combo.data( 'combo' );
		if ( type !== 'loyalty_products' && type !== 'loyalty_categories' && type !== 'loyalty_customers' ) {
			return;
		}
		var q    = $combo.find( '.wpldp-combo-input' ).val() || '';
		if ( type === 'loyalty_categories' ) {
			searchCategories( $combo, q );
		} else if ( type === 'loyalty_customers' ) {
			if ( q.length >= 2 ) {
				searchCustomers( $combo, q );
			} else {
				$combo.find( '.wpldp-combo-menu' )
					.html( '<div class="wpldp-combo-empty">' + escHtml( 'Type at least 2 characters to search' ) + '</div>' )
					.show();
			}
		} else if ( q.length >= 2 ) {
			searchProducts( $combo, q );
		} else {
			$combo.find( '.wpldp-combo-menu' )
				.html( '<div class="wpldp-combo-empty">' + escHtml( 'Type at least 2 characters to search' ) + '</div>' )
				.show();
		}
	}

	var selectedCustomerId = null;

	function renderCustomerResult( d ) {
		var $result = $( '#dukkan-loyalty-lookup-result' );
		var html = '<table class="widefat striped" style="max-width:560px;margin-top:10px;">';
		html += '<tbody>';
		html += '<tr><th style="width:180px;">Customer</th><td>' + escHtml( d.display_name ) + ' (' + escHtml( d.email ) + ')</td></tr>';
		html += '<tr><th>Balance</th><td><strong>' + escHtml( d.balance ) + '</strong> points</td></tr>';
		html += '</tbody></table>';

		if ( d.ledger && d.ledger.length ) {
			html += '<h3 style="margin:16px 0 8px;">Recent activity</h3>';
			html += '<table class="widefat striped" style="max-width:560px;">';
			html += '<thead><tr><th>Type</th><th>Points</th><th>Note</th><th>Date</th></tr></thead><tbody>';
			d.ledger.forEach( function ( row ) {
				html += '<tr>';
				html += '<td>' + escHtml( row.type ) + '</td>';
				html += '<td>' + escHtml( row.points ) + '</td>';
				html += '<td>' + escHtml( row.note ) + '</td>';
				html += '<td>' + escHtml( row.created_at ) + '</td>';
				html += '</tr>';
			} );
			html += '</tbody></table>';
		}

		$result.html( html );
		$( '#dukkan-loyalty-adjust' ).show();
	}

	function loadCustomerBalance( userId ) {
		var $result = $( '#dukkan-loyalty-lookup-result' );
		$result.html( '<p>' + escHtml( 'Loading…' ) + '</p>' );

		$.post( dukkan_loyalty_admin.url, {
			action: 'dukkan_loyalty_balance_lookup',
			nonce: dukkan_loyalty_admin.nonce,
			user_id: userId
		}, function ( res ) {
			if ( ! res || ! res.success ) {
				$result.html( '<p class="dukkan-loyalty-lookup-error">' + escHtml( res && res.data && res.data.message ? res.data.message : 'Customer not found.' ) + '</p>' );
				selectedCustomerId = null;
				$( '#dukkan-loyalty-adjust' ).hide();
				return;
			}

			selectedCustomerId = res.data.user_id;
			renderCustomerResult( res.data );
		} );
	}

	function adjustPoints( sign ) {
		var points = parseInt( $( '#dukkan-loyalty-adjust-points' ).val(), 10 );
		var note   = $.trim( $( '#dukkan-loyalty-adjust-note' ).val() );

		if ( ! selectedCustomerId ) {
			alert( 'Please select a customer first.' );
			return;
		}
		if ( ! points || points <= 0 ) {
			alert( 'Please enter a valid points amount.' );
			return;
		}

		var delta = sign === 'add' ? points : -points;

		$.post( dukkan_loyalty_admin.url, {
			action: 'dukkan_loyalty_adjust_points',
			nonce: dukkan_loyalty_admin.nonce,
			user_id: selectedCustomerId,
			points: delta,
			note: note
		}, function ( res ) {
			if ( ! res || ! res.success ) {
				alert( res && res.data && res.data.message ? res.data.message : 'Adjustment failed.' );
				return;
			}

			$( '#dukkan-loyalty-adjust-points' ).val( '' );
			$( '#dukkan-loyalty-adjust-note' ).val( '' );
			loadCustomerBalance( selectedCustomerId );
		} );
	}

	$( document ).ready( function () {
		// Master enable switch — update the live status label.
		$( document ).on( 'change', '[data-master-toggle]', function () {
			var $status = $( '[data-status-text]' );
			if ( $( this ).is( ':checked' ) ) {
				$status.text( 'Active' ).addClass( 'is-active' );
			} else {
				$status.text( 'Inactive' ).removeClass( 'is-active' );
			}
		} );

		// Combo interactions.
		$( document ).on( 'focus click', '.wpldp-combo-input', function () {
			comboOpen( $( this ).closest( '.wpldp-combo' ) );
		} );
		$( document ).on( 'keyup', '.wpldp-combo-input', function () {
			comboOpen( $( this ).closest( '.wpldp-combo' ) );
		} );
		$( document ).on( 'click', '.wpldp-combo-option', function () {
			var $option = $( this );
			var $combo  = $option.closest( '.wpldp-combo' );
			var type    = $combo.data( 'combo' );
			if ( type !== 'loyalty_products' && type !== 'loyalty_categories' && type !== 'loyalty_customers' ) {
				return;
			}
			if ( type === 'loyalty_customers' ) {
				$combo.find( '.wpldp-combo-input' ).val( $option.data( 'label' ) );
				$combo.find( '.wpldp-combo-menu' ).hide();
				loadCustomerBalance( $option.data( 'id' ) );
				return;
			}
			comboAddTag( $combo, $option.data( 'id' ), $option.data( 'label' ) );
			$combo.find( '.wpldp-combo-input' ).val( '' );
			$combo.find( '.wpldp-combo-menu' ).hide();
		} );
		$( document ).on( 'click', '.wpldp-combo-tag-remove', function () {
			var $remove = $( this );
			var $tags   = $remove.closest( '.wpldp-combo-tags' );
			var type    = $tags.data( 'tags-for' );
			if ( type !== 'loyalty_products' && type !== 'loyalty_categories' ) {
				return;
			}
			var $combo  = $tags.length
				? $( '.wpldp-combo[data-combo="' + type + '"]' )
				: null;
			if ( $combo && $combo.length ) {
				comboRemoveTag( $combo, $remove.data( 'id' ) );
			}
		} );
		$( document ).on( 'click', function ( e ) {
			if ( ! $( e.target ).closest( '.wpldp-combo' ).length ) {
				$( '.wpldp-combo-menu' ).hide();
			}
		} );

		// Customer selection from the combo already triggers loadCustomerBalance.
		// Adjust points (add / deduct).
		$( '#dukkan-loyalty-adjust-add' ).on( 'click', function () {
			adjustPoints( 'add' );
		} );
		$( '#dukkan-loyalty-adjust-deduct' ).on( 'click', function () {
			adjustPoints( 'deduct' );
		} );
	} );
} )( jQuery );
