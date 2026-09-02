/* Product Badges — admin logic.
 *
 * Self-contained combo multi-select + badge editor. Mirrors the Product
 * Add-Ons behavior and reuses its .wpldp-* markup.
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

	// Escape HTML but re-allow explicit <br> line breaks the user typed.
	function escBr( s ) {
		return escHtml( s ).replace( /&lt;br\s*\/?&gt;/gi, '<br>' );
	}

	// Tags for a combo live in a full-width container scoped to the same form.
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
	}

	function searchProducts( $combo, q ) {
		$.get( wpldp_badge_ajax.url, {
			action: 'wpldp_badge_search_products',
			nonce: wpldp_badge_ajax.nonce,
			q: q
		}, function ( res ) {
			var items = ( ( res && res.success ) ? res.data : [] ).map( function ( p ) {
				return { id: p.id, label: p.name };
			} );
			comboRenderMenu( $combo, items );
		} );
	}

	function searchCategories( $combo, q ) {
		$.get( wpldp_badge_ajax.url, {
			action: 'wpldp_badge_search_categories',
			nonce: wpldp_badge_ajax.nonce,
			q: q
		}, function ( res ) {
			var items = ( ( res && res.success ) ? res.data : [] ).map( function ( c ) {
				return { id: c.id, label: c.name };
			} );
			comboRenderMenu( $combo, items );
		} );
	}

	function searchTags( $combo, q ) {
		$.get( wpldp_badge_ajax.url, {
			action: 'wpldp_badge_search_tags',
			nonce: wpldp_badge_ajax.nonce,
			q: q
		}, function ( res ) {
			var items = ( ( res && res.success ) ? res.data : [] ).map( function ( t ) {
				return { id: t.id, label: t.name };
			} );
			comboRenderMenu( $combo, items );
		} );
	}

	function comboOpen( $combo ) {
		var type = $combo.data( 'combo' );
		if ( type !== 'badge_products' && type !== 'badge_categories' && type !== 'badge_tags' ) {
			return;
		}
		var q    = $combo.find( '.wpldp-combo-input' ).val() || '';
		if ( type === 'badge_categories' ) {
			searchCategories( $combo, q );
		} else if ( type === 'badge_tags' ) {
			searchTags( $combo, q );
		} else if ( q.length >= 2 ) {
			searchProducts( $combo, q );
		} else {
			$combo.find( '.wpldp-combo-menu' )
				.html( '<div class="wpldp-combo-empty">' + escHtml( 'Type at least 2 characters to search' ) + '</div>' )
				.show();
		}
	}

	function comboReset( $combo ) {
		comboTags( $combo ).empty().removeClass( 'is-visible' );
		$combo.find( '.wpldp-combo-input' ).val( '' );
		$combo.find( '.wpldp-combo-menu' ).hide();
	}

	function setColor( selector, value ) {
		var $input = $( selector );
		$input.val( value );
		var $picker = $input.closest( '.wp-picker-container' ).find( '.wp-color-picker' );
		if ( $picker.length ) {
			$picker.iris( 'color', value );
		}
	}

	function resetForm() {
		$( '#wpldp-badge-form' )[0].reset();
		$( '#wpldp-badge-id' ).val( '' );
		setColor( '#wpldp-badge-background-color', '#e53935' );
		setColor( '#wpldp-badge-text-color', '#ffffff' );
		$( 'input[name="shape"][value="rectangular"]' ).prop( 'checked', true );
		$( 'input[name="position"][value="top-left"]' ).prop( 'checked', true );
		$( '#wpldp-badge-form .wpldp-combo' ).each( function () {
			comboReset( $( this ) );
		} );
		$( '#wpldp-badge-applied-to' ).val( 'all' );
		$( '#wpldp-badge-products-box' ).hide();
		$( '#wpldp-badge-categories-box' ).hide();
		$( '#wpldp-badge-tags-box' ).hide();
		$( '#wpldp-badge-form .wpldp-search-column' ).hide();
		$( '#wpldp-badge-form .wpldp-combo-tags' ).removeClass( 'is-visible' );
		updatePreview();
	}

	function updatePreview() {
		var text = $( '#wpldp-badge-text' ).val() || '';
		var bg   = $( '#wpldp-badge-background-color' ).val() || '#e53935';
		var fg   = $( '#wpldp-badge-text-color' ).val() || '#ffffff';
		var shape = $( 'input[name="shape"]:checked' ).val() || 'rectangular';
		var position = $( 'input[name="position"]:checked' ).val() || 'top-left';
		var $preview = $( '#wpldp-badge-preview' );
		$preview.html( escBr( text ) || 'Badge' );
		$preview.css( { background: bg, color: fg } );
		$preview.attr( 'class', 'wpldp-badge-preview wpldp-preview-shape-' + shape + ' wpldp-preview-pos-' + position );
	}

	function updateAppliedToVisibility() {
		var appliedTo = $( '#wpldp-badge-applied-to' ).val();
		var $searchCol = $( '#wpldp-badge-form .wpldp-search-column' );
		var $productTags = $( '.wpldp-combo-tags[data-tags-for="badge_products"]' );
		var $categoryTags = $( '.wpldp-combo-tags[data-tags-for="badge_categories"]' );
		var $tagTags = $( '.wpldp-combo-tags[data-tags-for="badge_tags"]' );

		$( '#wpldp-badge-products-box' ).hide();
		$( '#wpldp-badge-categories-box' ).hide();
		$( '#wpldp-badge-tags-box' ).hide();
		$productTags.removeClass( 'is-visible' );
		$categoryTags.removeClass( 'is-visible' );
		$tagTags.removeClass( 'is-visible' );

		if ( appliedTo === 'all' ) {
			$searchCol.hide();
		} else if ( appliedTo === 'specific_products' ) {
			$searchCol.show();
			$( '#wpldp-badge-products-box' ).show();
			$productTags.addClass( 'is-visible' );
		} else if ( appliedTo === 'specific_categories' ) {
			$searchCol.show();
			$( '#wpldp-badge-categories-box' ).show();
			$categoryTags.addClass( 'is-visible' );
		} else if ( appliedTo === 'specific_tags' ) {
			$searchCol.show();
			$( '#wpldp-badge-tags-box' ).show();
			$tagTags.addClass( 'is-visible' );
		}
	}

	function badgeListItemHtml( badge ) {
		var bg = badge.background_color || '#e53935';
		var fg = badge.text_color || '#ffffff';
		var shape = badge.shape || 'rectangular';
		var checked = badge.status ? 'checked' : '';
		return '<div class="wpldp-group wpldp-badge-item" data-id="' + escHtml( badge.id ) + '">' +
			'<div class="wpldp-group-top">' +
				'<span><span class="wpldp-badge-swatch' + ( shape === 'circle' ? ' wpldp-badge-swatch--circle' : '' ) + '" style="background:' + escHtml( bg ) + ';color:' + escHtml( fg ) + ';">' + escHtml( badge.text ).replace( /&lt;br\s*\/?&gt;/gi, ' ' ) + '</span></span>' +
				'<div class="wpldp-group-top-controls">' +
					'<button type="button" class="wpldp-trash-btn wpldp-badge-delete" data-id="' + escHtml( badge.id ) + '" title="Delete badge"><span class="dashicons dashicons-trash"></span></button>' +
					'<label class="wpldp-switch"><input type="checkbox" class="wpldp-toggle-badge-status" data-id="' + escHtml( badge.id ) + '" ' + checked + '><span class="wpldp-slider"></span></label>' +
				'</div>' +
			'</div>' +
		'</div>';
	}

	function fillEditor( badge ) {
		$( '#wpldp-badge-id' ).val( badge.id || '' );
		$( '#wpldp-badge-text' ).val( badge.text || '' );
		$( '#wpldp-badge-editor-title' ).text( 'Edit Badge' );
		$( '#wpldp-badge-editor-subtitle' ).text( 'Customize this badge\'s appearance and placement.' );
		$( 'input[name="shape"][value="' + ( badge.shape || 'rectangular' ) + '"]' ).prop( 'checked', true );
		$( 'input[name="position"][value="' + ( badge.position || 'top-left' ) + '"]' ).prop( 'checked', true );
		$( '#wpldp-badge-applied-to' ).val( badge.applied_to || 'all' );

		setColor( '#wpldp-badge-background-color', badge.background_color || '#e53935' );
		setColor( '#wpldp-badge-text-color', badge.text_color || '#ffffff' );

		// Reset combos, then re-add tags.
		$( '#wpldp-badge-form .wpldp-combo' ).each( function () {
			comboReset( $( this ) );
		} );

		var $productsCombo = $( '#wpldp-badge-products-box .wpldp-combo' );
		var $categoriesCombo = $( '#wpldp-badge-categories-box .wpldp-combo' );
		var $tagsCombo = $( '#wpldp-badge-tags-box .wpldp-combo' );
		( badge.products || [] ).forEach( function ( p ) {
			if ( typeof p === 'object' ) {
				comboAddTag( $productsCombo, p.id, p.name );
			} else {
				comboAddTag( $productsCombo, p, p );
			}
		} );
		( badge.categories || [] ).forEach( function ( c ) {
			if ( typeof c === 'object' ) {
				comboAddTag( $categoriesCombo, c.id, c.name );
			} else {
				comboAddTag( $categoriesCombo, c, c );
			}
		} );
		( badge.tags || [] ).forEach( function ( t ) {
			if ( typeof t === 'object' ) {
				comboAddTag( $tagsCombo, t.id, t.name );
			} else {
				comboAddTag( $tagsCombo, t, t );
			}
		} );

		updateAppliedToVisibility();
		updatePreview();
	}

	function showEditor() {
		$( '#wpldp-badge-no-selection' ).hide();
		$( '#wpldp-badge-editor' ).show();
	}

	// -----------------------------------------------------------------
	// Init
	// -----------------------------------------------------------------
	$( document ).ready( function () {

		// Color pickers.
		$( '.wpldp-color-field' ).wpColorPicker( {
			change: function () { updatePreview(); },
			clear: function () { updatePreview(); }
		} );

		// Live preview on text change.
		$( '#wpldp-badge-text' ).on( 'input', updatePreview );

		// Live preview on shape/position change.
		$( 'input[name="shape"]' ).on( 'change', updatePreview );
		$( 'input[name="position"]' ).on( 'change', updatePreview );

		// Applied-to visibility.
		$( '#wpldp-badge-applied-to' ).on( 'change', updateAppliedToVisibility );

		// New Badge button.
		$( document ).on( 'click', '.wpldp-new-badge', function () {
			$( '.wpldp-group' ).removeClass( 'active' );
			resetForm();
			$( '#wpldp-badge-editor-title' ).text( 'New Badge' );
			$( '#wpldp-badge-editor-subtitle' ).text( 'Create a badge and customize its appearance.' );
			showEditor();
		} );

		// Select an existing badge.
		$( document ).on( 'click', '.wpldp-badge-item', function ( e ) {
			if ( $( e.target ).closest( '.wpldp-trash-btn, .wpldp-switch' ).length ) {
				return;
			}
			var badgeId = $( this ).data( 'id' );
			$( '.wpldp-group' ).removeClass( 'active' );
			$( this ).addClass( 'active' );

			$.post( wpldp_badge_ajax.url, {
				action: 'wpldp_badge_get',
				nonce: wpldp_badge_ajax.nonce,
				badge_id: badgeId
			}, function ( res ) {
				if ( res && res.success ) {
					fillEditor( res.data );
					showEditor();
				}
			} );
		} );

		// Toggle status.
		$( document ).on( 'change', '.wpldp-toggle-badge-status', function () {
			var $toggle = $( this );
			var badgeId = $toggle.data( 'id' );
			var status  = $toggle.is( ':checked' ) ? 1 : 0;
			$.post( wpldp_badge_ajax.url, {
				action: 'wpldp_badge_toggle',
				nonce: wpldp_badge_ajax.nonce,
				badge_id: badgeId,
				status: status
			}, function ( res ) {
				if ( ! res.success ) {
					$toggle.prop( 'checked', ! $toggle.is( ':checked' ) );
				}
			} );
		} );

		// Delete badge — confirmation modal.
		var pendingDeleteBadgeId = '';
		$( document ).on( 'click', '.wpldp-badge-delete', function ( e ) {
			e.stopPropagation();
			pendingDeleteBadgeId = $( this ).data( 'id' );
			$( '#wpldp-badge-confirm-overlay' ).show();
		} );
		$( '.wpldp-badge-confirm-cancel' ).on( 'click', function () {
			pendingDeleteBadgeId = '';
			$( '#wpldp-badge-confirm-overlay' ).hide();
		} );
		$( '.wpldp-badge-confirm-delete' ).on( 'click', function () {
			if ( ! pendingDeleteBadgeId ) { return; }
			var badgeId = pendingDeleteBadgeId;
			$.post( wpldp_badge_ajax.url, {
				action: 'wpldp_badge_delete',
				nonce: wpldp_badge_ajax.nonce,
				badge_id: badgeId
			}, function ( res ) {
				pendingDeleteBadgeId = '';
				$( '#wpldp-badge-confirm-overlay' ).hide();
				if ( res.success ) {
					$( '.wpldp-badge-item[data-id="' + badgeId + '"]' ).remove();
					if ( $( '#wpldp-badge-id' ).val() === badgeId ) {
						$( '#wpldp-badge-editor' ).hide();
						$( '#wpldp-badge-no-selection' ).show();
					}
					if ( ! $( '.wpldp-badge-item' ).length ) {
						$( '.wpldp-badge-list' ).html( '<p class="wpldp-empty">No badges created yet. Click "New Badge" to get started!</p>' );
					}
				}
			} );
		} );

		// Cancel editing.
		$( document ).on( 'click', '.wpldp-badge-cancel', function () {
			$( '.wpldp-group' ).removeClass( 'active' );
			$( '#wpldp-badge-editor' ).hide();
			$( '#wpldp-badge-no-selection' ).show();
		} );

		// Combo interactions.
		$( document ).on( 'focus click', '.wpldp-combo-input', function () {
			var $combo = $( this ).closest( '.wpldp-combo' );
			comboOpen( $combo );
		} );
		$( document ).on( 'keyup', '.wpldp-combo-input', function () {
			var $combo = $( this ).closest( '.wpldp-combo' );
			comboOpen( $combo );
		} );
		$( document ).on( 'click', '.wpldp-combo-option', function () {
			var $option = $( this );
			var $combo  = $option.closest( '.wpldp-combo' );
			var type    = $combo.data( 'combo' );
			if ( type !== 'badge_products' && type !== 'badge_categories' && type !== 'badge_tags' ) {
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
			if ( type !== 'badge_products' && type !== 'badge_categories' && type !== 'badge_tags' ) {
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

		// Save badge (create or update).
		$( '#wpldp-badge-form' ).on( 'submit', function ( e ) {
			e.preventDefault();

			var text = $.trim( $( '#wpldp-badge-text' ).val() );
			if ( ! text ) {
				alert( 'Badge text is required.' );
				return;
			}

			var data = $( this ).serialize();

			$.post( wpldp_badge_ajax.url, data + '&action=wpldp_badge_save&nonce=' + encodeURIComponent( wpldp_badge_ajax.nonce ), function ( res ) {
				if ( ! res.success ) {
					alert( res.data && res.data.message ? res.data.message : 'Save failed.' );
					return;
				}
				var badge = res.data;
				var $existing = $( '.wpldp-badge-item[data-id="' + badge.id + '"]' );
				var html = badgeListItemHtml( badge );
				if ( $existing.length ) {
					$existing.replaceWith( html );
				} else {
					$( '.wpldp-badge-list .wpldp-empty' ).remove();
					$( '.wpldp-badge-list' ).prepend( html );
				}
				$( '.wpldp-group' ).removeClass( 'active' );
				$( '.wpldp-badge-item[data-id="' + badge.id + '"]' ).addClass( 'active' );
				$( '#wpldp-badge-id' ).val( badge.id );
			} );
		} );
	} );
} )( jQuery );
