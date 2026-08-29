(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	$(document).ready(function(){
        function addGroupToSidebar(group){

			// remove empty message if exists
			$('.wpldp-empty').remove();

			let html = `
				<div class="wpldp-group" data-id="${group.id}">
					<div class="wpldp-group-top">
						<span>${group.group_name}</span>
						<div class="wpldp-group-top-controls">
							<button type="button" class="wpldp-trash-btn" data-id="${group.id}">
								<span class="dashicons dashicons-trash"></span>
							</button>
							<label class="wpldp-switch">
								<input type="checkbox" class="wpldp-toggle-product-addon-status" data-id="${group.id}" checked>
								<span class="wpldp-slider"></span>
							</label>
						</div>
					</div>
				</div>
			`;

			$('.wpldp-group-list').append(html);
		}

        /* ================= COMBO (products / categories picker) ================= */

		var categoriesCache = null;

		function escHtml( s ) {
			return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
			} );
		}

		function loadCategoriesData( cb ) {
			if ( categoriesCache ) {
				cb( categoriesCache );
				return;
			}
			$.post( wpldp_ajax.url, {
				action: 'wpldp_get_categories',
				nonce: wpldp_ajax.nonce
			}, function ( res ) {
				var items = [];
				var $container = $( '<div>' ).html( res );
				$container.find( '.wpldp-cat-item' ).each( function () {
					var id   = $( this ).find( '.cat-checkbox' ).first().val();
					var name = $( this ).find( '.cat-name' ).first().text().trim();
					var depth = $( this ).parents( '.wpldp-sub-cat' ).length;
					if ( id ) {
						items.push( { id: id, name: name, label: ( depth ? '— ' : '' ) + name } );
					}
				} );
				categoriesCache = items;
				cb( items );
			} );
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

			var filtered = items.filter( function ( it ) {
				return selectedIds.indexOf( String( it.id ) ) === -1;
			} );

			if ( ! filtered.length ) {
				$menu.append( '<div class="wpldp-combo-empty">' + escHtml( 'No results found' ) + '</div>' );
			} else {
				filtered.forEach( function ( it ) {
					var label = it.label || it.text || it.name || '';
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
			$.get( wpldp_ajax.url, {
				action: 'wpldp_search_products',
				nonce: wpldp_ajax.nonce,
				q: q
			}, function ( data ) {
				var items = ( data || [] ).map( function ( p ) {
					return { id: p.id, label: p.text };
				} );
				comboRenderMenu( $combo, items );
			} );
		}

		function searchCategories( $combo, q ) {
			loadCategoriesData( function ( items ) {
				var needle = ( q || '' ).toLowerCase();
				var filtered = items.filter( function ( it ) {
					return ! needle || ( it.name && it.name.toLowerCase().indexOf( needle ) !== -1 );
				} );
				comboRenderMenu( $combo, filtered );
			} );
		}

		function comboOpen( $combo ) {
			var type = $combo.data( 'combo' );
			var q    = $combo.find( '.wpldp-combo-input' ).val() || '';
			if ( type === 'categories' ) {
				searchCategories( $combo, q );
			} else if ( q.length >= 2 ) {
				searchProducts( $combo, q );
			} else {
				$combo.find( '.wpldp-combo-menu' )
					.html( '<div class="wpldp-combo-empty">' + escHtml( 'Type at least 2 characters to search' ) + '</div>' )
					.show();
			}
		}

		function comboReset( $combo ) {
			comboTags( $combo ).empty();
			$combo.find( '.wpldp-combo-input' ).val( '' );
			$combo.find( '.wpldp-combo-menu' ).hide();
		}

        function loadGroupData(groupId){
			showLoader();
			resetEditPanel();
			$.post(wpldp_ajax.url, {
				action: 'wpldp_get_group',
				nonce: wpldp_ajax.nonce,
				group_id: groupId
			}, function(res){

				if(res.success){
					renderEditPanel(res.data);
				}
				hideLoader();
			});

		}

		function resetEditPanel(){
			$('.wpldp-addon-group-details').html('');
			$('.wpldp-product-addon-fields').html('');
			$('#wpldp-addon-group-form-global').hide();
			$('#wpldp-create-group-panel').hide();
		}

		function openCreatePanel(){
			$('.wpldp-group').removeClass('active');
			resetEditPanel();
			$('#wpldp-create-group-panel').show();
		}

		function resetCreateForm(){
			$('#wpldp-group-form')[0].reset();
			$('#wpldp-group-form .wpldp-combo').each( function () {
				comboReset( $( this ) );
			} );
			$('#wpldp-applied-to').val('all');
			$('#wpldp-products-box').hide();
			$('#wpldp-categories-box').hide();
			$('#wpldp-group-form .wpldp-search-column').hide();
			$('#wpldp-group-form .wpldp-combo-tags').removeClass('is-visible');
		}

		function closeCreatePanel(){
			resetCreateForm();
			$('#wpldp-create-group-panel').hide();
		}

        function renderEditPanel(group){
			var editApplied = group.applied_to || 'all';
			var editSubtype = 'all';
			if(editApplied === 'specific_products'){
				editSubtype = 'products';
			} else if(editApplied === 'specific_categories'){
				editSubtype = 'categories';
			} else if(editApplied === 'specific'){
				if(group.categories && group.categories.length && !(group.products && group.products.length)){
					editSubtype = 'categories';
				} else {
					editSubtype = 'products';
				}
			}

			let html = `
			<form id="wpldp-group-form-edit">
				<div class="wpldp-edit-box" data-id="${group.id}">

					<div class="wpldp-field-row">
						<div class="wpldp-field">
							<label>Group Name</label>
							<input type="text" name="product_addon[group_name]" value="${group.group_name}">
						</div>

						<div class="wpldp-field">
							<label>Applied To</label>
							<select name="product_addon[applied_to]" id="wpldp-edit-applied-to">
								<option value="all" ${editSubtype==='all'?'selected':''}>All products</option>
								<option value="specific_products" ${editSubtype==='products'?'selected':''}>Specific Products</option>
								<option value="specific_categories" ${editSubtype==='categories'?'selected':''}>Specific Categories</option>
							</select>
						</div>

						<div class="wpldp-field wpldp-search-column" style="${editSubtype==='all'?'display:none;':''}">
							<label>Search</label>
							<div id="wpldp-edit-products-box" class="wpldp-target-box" style="${editSubtype==='products'?'display:block;':'display:none;'}">
								<div class="wpldp-combo" data-combo="products" data-name="product_addon[products][]">
									<div class="wpldp-combo-control">
										<input type="text" class="wpldp-combo-input" placeholder="Search for products…" autocomplete="off">
										<span class="dashicons dashicons-arrow-down-alt2 wpldp-combo-caret"></span>
									</div>
									<div class="wpldp-combo-menu"></div>
								</div>
							</div>

							<div id="wpldp-edit-categories-box" class="wpldp-target-box" style="${editSubtype==='categories'?'display:block;':'display:none;'}">
								<div class="wpldp-combo" data-combo="categories" data-name="product_addon[categories][]">
									<div class="wpldp-combo-control">
										<input type="text" class="wpldp-combo-input" placeholder="Search categories..." autocomplete="off">
										<span class="dashicons dashicons-arrow-down-alt2 wpldp-combo-caret"></span>
									</div>
									<div class="wpldp-combo-menu"></div>
								</div>
							</div>
						</div>
					</div>

					<!-- Selected labels (full width, under the fields) -->
					<div class="wpldp-combo-tags${editSubtype==='products'?' is-visible':''}" data-tags-for="products"></div>
					<div class="wpldp-combo-tags${editSubtype==='categories'?' is-visible':''}" data-tags-for="categories"></div>
			`;

			html += `</div></form>`;

			$('.wpldp-addon-group-details').html(html);

			let has_fields = group.fields && Object.keys(group.fields).length > 0;

			let fields_html = `<div class="wpldp-fields-empty" style="${has_fields ? 'display:none;' : 'display:block;'}">

					<div class="wpldp-empty-icon">
						<i class="fa-solid fa-plus"></i>
					</div>

					<h4>No fields yet</h4>

					<p>Add your first custom field to this group</p>

					<button data-id="${group.id}" type="button" class="wpldp-add-field-btn large">
						<i class="fa-solid fa-plus"></i> Add Field
					</button>

				</div>`;

            fields_html += `<form id="wpldp-addon-all-fields-data-form" data-groupid="${group.id}"><div class="wpldp-product-addon-fields-list">
                <!-- Fields will be rendered here -->
				
            </div></form>`;

			fields_html += `<div class="wpldp-add-another-field-wrap" style="${has_fields ? '' : 'display:none;'}">
				<button data-id="${group.id}" type="button" class="wpldp-add-field-btn">
					<i class="fa-solid fa-plus"></i> Add Another Field
				</button>
			</div>`;

			$('.wpldp-product-addon-fields').html(fields_html);
			$('#wpldp-addon-group-form-global').show();

			// Prefill selected products as labels.
			var $productCombo = $('#wpldp-group-form-edit .wpldp-combo[data-combo="products"]');
			if ( group.products && group.products.length ) {
				group.products.forEach( function ( product ) {
					comboAddTag( $productCombo, product.id, product.name );
				} );
			}

			// Prefill selected categories as labels.
			var $categoryCombo = $('#wpldp-group-form-edit .wpldp-combo[data-combo="categories"]');
			if ( group.categories && group.categories.length ) {
				loadCategoriesData( function ( items ) {
					var map = {};
					items.forEach( function ( it ) { map[ it.id ] = it.name; } );
					group.categories.forEach( function ( id ) {
						comboAddTag( $categoryCombo, id, map[ id ] || ( '#' + id ) );
					} );
				} );
			}

			if(has_fields){
				$('.wpldp-fields-empty').hide();
				for(let field_id in group.fields){
					appendFieldBuilder(group.id, field_id, group.fields[field_id]);
				}
				initFieldSortable();
			}else{
				$('.wpldp-fields-empty').show();
			}
		}
        
        // OPEN CREATE PANEL
		$('.wpldp-new-group').on('click', function(){
			openCreatePanel();
		});

        $('.wpldp-panel-close, .wpldp-cancel').on('click', function(){
			closeCreatePanel();
		});

        /* TRIGGER ON SELECT */
		$('#wpldp-applied-to').on('change', function(){

			var val = $(this).val();
			var $searchColumn = $('#wpldp-group-form .wpldp-search-column');
			var $productTags = $('#wpldp-group-form .wpldp-combo-tags[data-tags-for="products"]');
			var $categoryTags = $('#wpldp-group-form .wpldp-combo-tags[data-tags-for="categories"]');

			$('#wpldp-products-box').slideUp(150);
			$('#wpldp-categories-box').slideUp(150);
			$productTags.removeClass('is-visible');
			$categoryTags.removeClass('is-visible');

			if(val === 'specific_products'){
				$searchColumn.show();
				$('#wpldp-products-box').slideDown(150);
				$productTags.addClass('is-visible');
			} else if(val === 'specific_categories'){
				$searchColumn.show();
				$('#wpldp-categories-box').slideDown(150);
				$categoryTags.addClass('is-visible');
			} else {
				$searchColumn.hide();
			}

		});

		$(document).on('change', '#wpldp-edit-applied-to', function(){

			var val = $(this).val();
			var $form = $(this).closest('form');
			var $searchColumn = $form.find('.wpldp-field-row .wpldp-search-column');
			var $productTags = $form.find('.wpldp-combo-tags[data-tags-for="products"]');
			var $categoryTags = $form.find('.wpldp-combo-tags[data-tags-for="categories"]');

			$form.find('#wpldp-edit-products-box').slideUp(150);
			$form.find('#wpldp-edit-categories-box').slideUp(150);
			$productTags.removeClass('is-visible');
			$categoryTags.removeClass('is-visible');

			if(val === 'specific_products'){
				$searchColumn.show();
				$form.find('#wpldp-edit-products-box').slideDown(150);
				$productTags.addClass('is-visible');
			} else if(val === 'specific_categories'){
				$searchColumn.show();
				$form.find('#wpldp-edit-categories-box').slideDown(150);
				$categoryTags.addClass('is-visible');
			} else {
				$searchColumn.hide();
			}

		});

        /* ================= COMBO EVENT HANDLERS ================= */

		// Open dropdown on focus.
		$(document).on('focus', '.wpldp-combo-input', function(){
			comboOpen( $( this ).closest( '.wpldp-combo' ) );
		});

		// Search as the user types.
		$(document).on('keyup', '.wpldp-combo-input', function(){
			var $combo = $( this ).closest( '.wpldp-combo' );
			var q      = $( this ).val() || '';
			var type   = $combo.data( 'combo' );

			clearTimeout( $combo.data( 'timer' ) );

			if ( type === 'categories' ) {
				searchCategories( $combo, q );
			} else {
				$combo.data( 'timer', setTimeout( function () {
					if ( q.length >= 2 ) {
						searchProducts( $combo, q );
					} else {
						$combo.find( '.wpldp-combo-menu' )
							.html( '<div class="wpldp-combo-empty">' + escHtml( 'Type at least 2 characters to search' ) + '</div>' )
							.show();
					}
				}, 250 ) );
			}
		});

		// Pick an option → add as a label under the field.
		$(document).on('click', '.wpldp-combo-option', function(){
			var $combo = $( this ).closest( '.wpldp-combo' );
			comboAddTag( $combo, $( this ).data( 'id' ), $( this ).data( 'label' ) );
			$combo.find( '.wpldp-combo-menu' ).hide();
			$combo.find( '.wpldp-combo-input' ).val( '' ).focus();
		});

		// Remove a label.
		$(document).on('click', '.wpldp-combo-tag-remove', function(){
			var $tags = $( this ).closest( '.wpldp-combo-tags' );
			var type  = $tags.data( 'tags-for' );
			var $combo = $tags.closest( 'form' ).find( '.wpldp-combo[data-combo="' + type + '"]' );
			comboRemoveTag( $combo, $( this ).data( 'id' ) );
		});

		// Close menus when clicking outside the combo.
		$(document).on('click', function(e){
			if ( ! $( e.target ).closest( '.wpldp-combo' ).length ) {
				$( '.wpldp-combo-menu' ).hide();
			}
		});

        /* FORM SUBMIT -- adding product addon group */
		$('#wpldp-group-form').on('submit', function(e){

			e.preventDefault();

			let form = $(this)[0];
			let formData = new FormData(form);

			// Map the 3-option UI back to the backend's all/specific values.
			let appliedTo = formData.get('product_addon[applied_to]');
			if(appliedTo === 'specific_products'){
				formData.set('product_addon[applied_to]', 'specific');
				formData.delete('product_addon[categories][]');
			} else if(appliedTo === 'specific_categories'){
				formData.set('product_addon[applied_to]', 'specific');
				formData.delete('product_addon[products][]');
			} else {
				formData.delete('product_addon[products][]');
				formData.delete('product_addon[categories][]');
			}

			// manually append action + nonce
			formData.append('action', 'wpldp_save_group');
			formData.append('nonce', wpldp_ajax.nonce);

			showLoader();

			$.ajax({
				url: wpldp_ajax.url,
				method: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(res){

					if(res.success){

						showToast('Group created successfully');

						addGroupToSidebar(res.data);

						resetCreateForm();
						$('.wpldp-group').removeClass('active');
						$('.wpldp-group[data-id="' + res.data.id + '"]').addClass('active');
						loadGroupData(res.data.id); // manages its own loader

					} else {
						showToast(res.data?.message || 'Error saving', 'error');
						hideLoader();
					}
				}
			});

		});

        /* DELETE (shared confirmation modal) */
		var pendingDelete = null;

		function showConfirmModal(title){
			$('#wpldp-confirm-title').text(title);
			$('#wpldp-confirm-overlay').show();
		}

		function closeConfirmModal(){
			$('#wpldp-confirm-overlay').hide();
			pendingDelete = null;
		}

		// Group trash button → open the confirmation modal.
		$(document).on('click', '.wpldp-trash-btn', function(e){
			e.stopPropagation();
			pendingDelete = { kind: 'group', groupId: $(this).closest('.wpldp-group').data('id') };
			showConfirmModal('Are you sure you want to delete this group?');
		});

		// Field trash button → open the same confirmation modal.
		$(document).on('click', '.wpldp-delete-addon-field', function(e){
			e.stopPropagation();
			pendingDelete = {
				kind: 'field',
				groupId: $(this).data('groupid'),
				fieldId: $(this).data('fieldid'),
				fieldEl: $(this).closest('.wpldp-field-box')
			};
			showConfirmModal('Are you sure you want to delete this field?');
		});

		// Cancel / close the confirmation modal.
		$(document).on('click', '.wpldp-confirm-cancel', closeConfirmModal);

		$(document).on('click', '.wpldp-confirm-overlay', function(e){
			if($(e.target).is(this)){
				closeConfirmModal();
			}
		});

		// Confirm deletion.
		$(document).on('click', '.wpldp-confirm-delete', function(){

			var target = pendingDelete;
			closeConfirmModal();

			if(!target){
				return;
			}

			if(target.kind === 'group'){
				deleteGroup(target.groupId);
			} else if(target.kind === 'field'){
				deleteField(target.groupId, target.fieldId, target.fieldEl);
			}

		});

		function deleteGroup(groupId){

			let groupEl = $('.wpldp-group[data-id="' + groupId + '"]');

			if(!groupId || !groupEl.length){
				return;
			}

			showLoader();

			$.post(wpldp_ajax.url, {
				action: 'wpldp_delete_group',
				nonce: wpldp_ajax.nonce,
				group_id: groupId
			}, function(res){

				if(res.success){

					// remove from UI
					groupEl.fadeOut(200, function(){
						$(this).remove();

						// show empty message if no groups left
						if($('.wpldp-group').length === 0){
							$('.wpldp-group-list').html(`
								<p class="wpldp-empty">
									No groups created yet. Click "New Group" to get started!
								</p>
							`);

							$('.wpldp-addon-group-details').html('');
							$('.wpldp-product-addon-fields').html('');
							$('#wpldp-addon-group-form-global').hide();
						}
					});

					if(groupEl.hasClass('active')){
						resetEditPanel();
					}

					showToast('Group deleted successfully');

				} else {
					showToast(res.data?.message || 'Delete failed', 'error');
				}
				hideLoader();
			});
		}

		function deleteField(groupId, fieldId, fieldEl){

			if(!groupId || !fieldId || !fieldEl || !fieldEl.length){
				return;
			}

			showLoader();

			$.post(wpldp_ajax.url, {
				action: 'wpldp_delete_group_addon_field',
				nonce: wpldp_ajax.nonce,
				field_id: fieldId,
				group_id: groupId
			}, function(res){

				if(res.success){

					// remove from UI
					fieldEl.fadeOut(200, function(){
						$(this).remove();

						// show empty message if no fields left
						if($('.wpldp-product-addon-fields-list .wpldp-field-box').length === 0){
							$('.wpldp-fields-empty').show();
							$('.wpldp-add-another-field-wrap').hide();
						}
					});

					showToast('Field deleted successfully');

				} else {
					showToast(res.data?.message || 'Delete failed', 'error');
				}
				hideLoader();
			});
		}

        /* DUPLICATE GROUP */
		$(document).on('click', '.wpldp-duplicate-product-addon-group', function(){

			let groupEl = $(this).closest('.wpldp-group');
			let groupId = groupEl.data('id');
			showLoader();
			$.post(wpldp_ajax.url, {
				action: 'wpldp_duplicate_group',
				nonce: wpldp_ajax.nonce,
				group_id: groupId
			}, function(res){

				if(res.success){

					// add duplicated group to UI
					addGroupToSidebar(res.data);

					showToast('Group duplicated successfully');

				} else {
					showToast(res.data?.message || 'Duplicate failed', 'error');
				}
				hideLoader();
			});

		});

        /* TOGGLE STATUS */
		$(document).on('change', '.wpldp-toggle-product-addon-status', function(){

			let checkbox = $(this);
			let groupId = checkbox.data('id');
			let status = checkbox.is(':checked') ? 1 : 0;
			checkbox.prop('disabled', true);

			showLoader();

			$.post(wpldp_ajax.url, {
				action: 'wpldp_toggle_group_status',
				nonce: wpldp_ajax.nonce,
				group_id: groupId,
				status: status
			}, function(res){
				checkbox.prop('disabled', false);

				if(res.success){

					showToast(
						status ? 'Group enabled' : 'Group disabled'
					);

				} else {

					// revert UI if failed
					checkbox.prop('checked', !status);

					showToast(res.data?.message || 'Update failed', 'error');
				}

				hideLoader();

			});

		});

        /* CLICK GROUP → LOAD EDIT PANEL */
		$(document).on('click', '.wpldp-group', function(e){

			if ($(e.target).closest('.wpldp-group-actions').length) {
				return; // ignore clicks inside actions
			}
			if ($(e.target).closest('.wpldp-switch').length) {
				return; // ignore clicks inside actions
			}
			if ($(e.target).closest('.wpldp-trash-btn').length) {
				return; // ignore clicks on the delete button
			}

			let groupId = $(this).data('id');

			$('.wpldp-group').removeClass('active');
			$(this).addClass('active');

			loadGroupData(groupId);

		});

		$(document).on('click', '.wpldp-save-addon-group-changes', function(e){
			$('#wpldp-group-form-edit').submit();
		});

		$(document).on('submit', '#wpldp-group-form-edit', function(e){

			e.preventDefault();

			let form = $(this)[0];
			let formData = new FormData(form);

			let box = $(this).find('.wpldp-edit-box');

			let groupId = box.data('id');

			// Map the 3-option UI back to the backend's all/specific values.
			let appliedTo = formData.get('product_addon[applied_to]');
			if(appliedTo === 'specific_products'){
				formData.set('product_addon[applied_to]', 'specific');
				formData.delete('product_addon[categories][]');
			} else if(appliedTo === 'specific_categories'){
				formData.set('product_addon[applied_to]', 'specific');
				formData.delete('product_addon[products][]');
			} else {
				formData.delete('product_addon[products][]');
				formData.delete('product_addon[categories][]');
			}

			// manually append action + nonce
			formData.append('action', 'wpldp_update_group');
			formData.append('nonce', wpldp_ajax.nonce);
			formData.append('group_id', groupId);

			showLoader();

			$.ajax({
				url: wpldp_ajax.url,
				method: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(res){

					if(res.success){
						$(".wpldp-group-list .wpldp-group[data-id='"+res.data.id+"'] .wpldp-group-top>span").text(res.data.group_name);
						// showToast('Group updated successfully');
						$('#wpldp-addon-all-fields-data-form').submit(); // submit all fields data together (better than multiple ajax calls on each field change)
					} else {
						showToast(res.data?.message || 'Error saving', 'error');
					}
					hideLoader();
				}
			});

		});

		// $(document).on('change', '.wpldp-edit-box input, .wpldp-edit-box select', function(){
		// 	// trigger form submit on any change for better UX (auto save)
		// 	$('#wpldp-group-form-edit').submit();
		// });

		// Add new field builder
		function getFieldTypeOptions(){
			return `
				<div class="option" data-type="text"><i class="fa-solid fa-t"></i> Text Input</div>
				<div class="option" data-type="textarea"><i class="fa-solid fa-align-left"></i> Text Area</div>
				<div class="option" data-type="number"><i class="fa-solid fa-hashtag"></i> Number</div>
				<div class="option" data-type="select"><i class="fa-solid fa-chevron-down"></i> Dropdown</div>
				<div class="option" data-type="radio"><i class="fa-regular fa-circle"></i> Radio Buttons</div>
				<div class="option" data-type="checkbox"><i class="fa-regular fa-square-check"></i> Checkboxes</div>
				<div class="option" data-type="date"><i class="fa-regular fa-calendar"></i> Date Picker</div>
				<div class="option" data-type="file"><i class="fa-solid fa-upload"></i> File Upload</div>
				<div class="option" data-type="image"><i class="fa-regular fa-image"></i> Image Selection</div>
				<div class="option" data-type="color"><i class="fa-solid fa-palette"></i> Color Swatches</div>
			`;
		}

		function getFieldTypeSelectedOption(field_type){
			switch(field_type){
				case 'text':
					return `<i class="fa-solid fa-t"></i> Text Input`;
				case 'textarea':
					return `<i class="fa-solid fa-align-left"></i> Text Area`;
				case 'number':
					return `<i class="fa-solid fa-hashtag"></i> Number`;
				case 'select':
					return `<i class="fa-solid fa-chevron-down"></i> Dropdown`;
				case 'radio':
					return `<i class="fa-regular fa-circle"></i> Radio Buttons`;
				case 'checkbox':
					return `<i class="fa-regular fa-square-check"></i> Checkboxes`;
				case 'date':
					return `<i class="fa-regular fa-calendar"></i> Date Picker`;
				case 'file':
					return `<i class="fa-solid fa-upload"></i> File Upload`;
				case 'image':
					return `<i class="fa-regular fa-image"></i> Image Selection`;
				case 'color':
					return `<i class="fa-solid fa-palette"></i> Color Swatches`;
			}
		}

		function appendFieldBuilder(groupId, field_id = null, field_data = null){

			let fieldId = groupId + "_" + Date.now();

			if(field_id){
				fieldId = field_id;
			}

			let html = `
			<div class="wpldp-field-box ${(field_id)?'collapsed':''}" data-groupid="${groupId}" data-fieldid="${fieldId}">
				<div class="wpldp-addon-field-data-form" data-groupid="${groupId}" data-fieldid="${fieldId}">

					<div class="wpldp-field-header">
						<span class="dashicons dashicons-menu wpldp-drag"></span>

						<strong class="wpldp-field-type-label">${getFieldTypeSelectedOption(field_data ? field_data.type : 'text')}</strong>
						<span class="wpldp-field-title-label">${field_data && field_data.title ? field_data.title : ''}</span>

						<div class="wpldp-field-actions">
							<span class="dashicons dashicons-trash wpldp-delete-addon-field" data-groupid="${groupId}" data-fieldid="${fieldId}"></span>
							<span class="dashicons dashicons-arrow-up-alt2 toggle"></span>
						</div>
					</div>

					<div class="wpldp-field-body">

						<!-- FIELD TYPE + TITLE + WIDTH -->
						<div class="wpldp-field-row">
							<div class="wpldp-field">
								<label>Field Type</label>

								<div class="wpldp-custom-select">
									<div class="wpldp-selected">
										<span class="wpldp-selected-label">${getFieldTypeSelectedOption(field_data ? field_data.type : 'text')}</span>
										<span class="dashicons dashicons-arrow-down-alt2 wpldp-select-caret"></span>
									</div>

									<div class="wpldp-options" data-fieldid="${fieldId}">
										${getFieldTypeOptions()}
									</div>

									<input type="hidden" class="wpldp-field-type" name="fields[${fieldId}][type]" value="${field_data ? field_data.type : 'text'}">
								</div>
							</div>

							<div class="wpldp-field">
								<label>Field Title <span class="wpldp-required-star">*</span></label>
								<input type="text" name="fields[${fieldId}][title]" class="wpldp-title-input" value="${field_data && field_data.title ? field_data.title : ''}" required>
							</div>

							<div class="wpldp-field">
								<label>Field Width</label>
								<div class="wpldp-select-wrap">
									<select name="fields[${fieldId}][width]">
										<option ${(field_data && (field_data.width == '100%')) ? 'selected' : ''} value="100%">100%</option>
										<option ${(field_data && (field_data.width == '75%')) ? 'selected' : ''} value="75%">75%</option>
										<option ${(field_data && (field_data.width == '50%')) ? 'selected' : ''} value="50%">50%</option>
										<option ${(field_data && (field_data.width == '25%')) ? 'selected' : ''} value="25%">25%</option>
									</select>
									<span class="dashicons dashicons-arrow-down-alt2 wpldp-select-caret"></span>
								</div>
							</div>
						</div>

						<!-- REQUIRED -->
						<div class="wpldp-field">
							<label>
								<input type="checkbox" value="1" name="fields[${fieldId}][required]" ${field_data && field_data.required ? 'checked' : ''} class="wpldp-required"> Required field
							</label>
						</div>

						<!-- DYNAMIC OPTIONS -->
						<div class="wpldp-field-dynamic">
							${getFieldTypeHTML(field_data ? field_data.type : 'text', fieldId, field_data)}
						</div>

					</div>
				</div>
			</div>
			`;

			$('.wpldp-product-addon-fields-list').append(html);
		}

		function initFieldSortable(){
			var $list = $('.wpldp-product-addon-fields-list');
			if ( ! $list.length || typeof $list.sortable !== 'function' ) {
				return;
			}
			if ( $list.data('ui-sortable') ) {
				$list.sortable('destroy');
			}
			$list.sortable({
				handle: '.wpldp-drag',
				items: '.wpldp-field-box',
				axis: 'y',
				containment: 'parent',
				tolerance: 'pointer'
			});
		}

		function getFieldTypeHTML(type, fieldId, field_data = null){

			if(type === 'text' || type === 'textarea' || type === 'number' || type === 'date' || type === 'file'){
				return `
					<div class="wpldp-field">
						<label>Price</label>
						<input type="number" name="fields[${fieldId}][price]" value="${field_data ? field_data.price : 0}">
					</div>
				`;
			}

			if(type === 'select' || type === 'radio' || type === 'checkbox'){
				let options_html = '';
				if(field_data && field_data.options){
					
					for(let options_index in field_data.options){
						options_html += optionRow(fieldId, options_index, field_data.options[options_index]);
					}
				} else {
					options_html = optionRow(fieldId);
				}
				return `
					<div class="wpldp-field">
						<label>Options</label>
						<div class="wpldp-options-list">	
							${options_html}
						</div>
						<button type="button" class="add-option" data-fieldid="${fieldId}">+ Add Option</button>
					</div>
				`;
			}

			if(type === 'image'){
				let options_html = '';
				if(field_data && field_data.options){
					
					for(let options_index in field_data.options){
						options_html += imageOptionRow(fieldId, options_index, field_data.options[options_index]);
					}
				} else {
					options_html = imageOptionRow(fieldId);
				}
				return `
					<div class="wpldp-field">
						<label>Options</label>

						<div class="wpldp-image-table">

							<div class="wpldp-image-head">
								<span></span>
								<span>Title</span>
								<span>Image</span>
								<span>Price</span>
								<span></span>
							</div>

							<div class="wpldp-image-body">
								${options_html}
							</div>

						</div>

						<button type="button" data-fieldid="${fieldId}" class="wpldp-add-image-option">
							<i class="fa-solid fa-plus"></i> Add New Option
						</button>

					</div>
				`;
			}

			if(type === 'color'){
				let options_html = '';
				if(field_data && field_data.options){
					
					for(let options_index in field_data.options){
						options_html += colorOptionRow(fieldId, options_index, field_data.options[options_index]);
					}
				} else {
					options_html = colorOptionRow(fieldId);
				}
				return `
					<div class="wpldp-field">
						<label>Options</label>

						<div class="wpldp-color-table">

							<div class="wpldp-color-head">
								<span></span>
								<span>Title</span>
								<span>Color</span>
								<span>Color Code</span>
								<span>Price</span>
								<span></span>
							</div>

							<div class="wpldp-color-body">
								${options_html}
							</div>

						</div>

						<button type="button" data-fieldid="${fieldId}" class="wpldp-add-color-option">
							<i class="fa-solid fa-plus"></i> Add New Option
						</button>

					</div>
				`;
			}

			// if(type === 'file'){
			// 	return `<div class="wpldp-field"><label>Allowed File Types</label><input type="text"></div>`;
			// }

			// if(type === 'color'){
			// 	return `<div class="wpldp-field"><label>Color Options</label>${optionRow()}</div>`;
			// }

			return '';
		}

		function colorOptionRow(fieldId, options_index = null, option_data = null){
			let index = fieldId + "_option_" + Date.now();
			if(options_index){
				index = options_index;
			}
			return `
				<div class="wpldp-color-row">

					<span class="drag">⋮⋮</span>

					<input type="text" name="fields[${fieldId}][options][${index}][label]" placeholder="Title" value="${option_data ? option_data.label : ''}">

					<input type="color" class="color-picker" name="fields[${fieldId}][options][${index}][color]" value="${option_data ? option_data.color : '#000000'}">

					<input type="text" class="color-code" name="fields[${fieldId}][options][${index}][color_code]" value="${option_data ? option_data.color_code : '#000000'}">

					<input type="number" name="fields[${fieldId}][options][${index}][price]" value="${option_data ? option_data.price : 0}">

					<span class="dashicons dashicons-trash remove-option"></span>

				</div>
			`;
		}

		function imageOptionRow(fieldId, options_index = null, option_data = null){
			let index = fieldId + "_option_" + Date.now();
			if(options_index){
				index = options_index;
			}

			let imagePreview = '';
			if(option_data && option_data.image_id && option_data.image_url){
				imagePreview = `<div class="wpldp-image-preview"><img src="${option_data.image_url}" alt=""></div>`;
			}
			return `
				<div class="wpldp-image-row">

					<span class="drag">⋮⋮</span>

					<input type="text" name="fields[${fieldId}][options][${index}][label]" value="${option_data ? option_data.label : ''}" placeholder="Title">
					${imagePreview}
					<div class="wpldp-image-upload">
						
						<button type="button" class="upload-btn">
							<span class="dashicons dashicons-upload"></span>
						</button>
						<input type="hidden" name="fields[${fieldId}][options][${index}][image_id]" value="${option_data ? option_data.image_id : ''}" class="image-id">
					</div>

					<input type="number" name="fields[${fieldId}][options][${index}][price]" value="${option_data ? option_data.price : 0}">

					<span class="dashicons dashicons-trash remove-option"></span>

				</div>
			`;
		}

		function optionRow(fieldId, options_index = null, option_data = null){
			let index = fieldId + "_option_" + Date.now();
			if(options_index){
				index = options_index;
			}
			return `
				<div class="wpldp-option-row">
					<input type="text" name="fields[${fieldId}][options][${index}][label]" placeholder="Option label" value="${option_data ? option_data.label : ''}">
					<input type="number" name="fields[${fieldId}][options][${index}][price]" placeholder="Price" value="${option_data ? option_data.price : 0}">
					<span class="dashicons dashicons-trash remove-option"></span>
				</div>
			`;
		}

		$(document).on('click', '.wpldp-add-field-btn', function(){
			var groupId = $(this).data('id');
			appendFieldBuilder(groupId);
			initFieldSortable();
			$('.wpldp-fields-empty').hide();
			$('.wpldp-add-another-field-wrap').show();
		});

		// Live-update the header title label as the user types the field title.
		$(document).on('input', '.wpldp-title-input', function(){
			var $box = $(this).closest('.wpldp-field-box');
			$box.find('.wpldp-field-title-label').text($(this).val());
		});

		$(document).on('click', '.toggle', function(e){

			e.stopPropagation();

			let box = $(this).closest('.wpldp-field-box');
			let body = box.find('.wpldp-field-body');

			if(box.hasClass('collapsed')){

				// EXPAND
				body.show();

				let height = body.prop('scrollHeight');

				body.css({
					height: 0
				});

				setTimeout(function(){
					body.css('height', height);
				}, 10);

				setTimeout(function(){
					body.css('height', 'auto');
				}, 250);

				box.removeClass('collapsed');

			} else {

				// COLLAPSE
				let height = body.prop('scrollHeight');

				body.css('height', height);

				setTimeout(function(){
					body.css('height', 0);
				}, 10);

				setTimeout(function(){
					body.hide();
				}, 250);

				box.addClass('collapsed');
			}

		});

		$(document).on('click', '.wpldp-field-header', function(e){

			if($(e.target).closest('.wpldp-field-actions').length) return;

			$(this).find('.toggle').trigger('click');

		});

		$(document).on('click', '.wpldp-selected', function(){
			$('.wpldp-custom-select').not($(this).parent()).removeClass('active');
			$(this).parent().toggleClass('active');
		});

		$(document).on('click', function(e){
			if(!$(e.target).closest('.wpldp-custom-select').length){
				$('.wpldp-custom-select').removeClass('active');
			}
		});

		$(document).on('click', '.wpldp-options .option', function(){

			let option = $(this);
			let type = option.data('type');
			let text = option.text();
			let icon = option.find('i').prop('outerHTML');

			let hiddenInput = option.closest('.wpldp-options').siblings('input.wpldp-field-type');

			hiddenInput.val(type).change(); // trigger change for dynamic fields

			let fieldId = option.closest('.wpldp-options').data('fieldid');

			let select = option.closest('.wpldp-custom-select');
			let box = option.closest('.wpldp-field-box');

			// Update selected UI
			select.find('.wpldp-selected .wpldp-selected-label').html(icon + ' ' + text);
			box.find('.wpldp-field-type-label').text(text);

			// Update dynamic fields
			box.find('.wpldp-field-dynamic').html(getFieldTypeHTML(type, fieldId));

			// CLOSE DROPDOWN (IMPORTANT)
			select.removeClass('active');

		});

		$(document).on('click', '.add-option', function(){
			var fieldId = $(this).data('fieldid');
			var field_index = $(this).siblings('.wpldp-options-list').find('.wpldp-option-row').length;
			$(this).siblings('.wpldp-options-list').append(optionRow(fieldId));
		});

		$(document).on('click', '.remove-option', function(){
			$(this).parent().remove();
		});

		$(document).on('click', '.wpldp-add-image-option', function(){
			var fieldId = $(this).data('fieldid');
			var field_index = $(this).siblings('.wpldp-image-table').find('.wpldp-image-body .wpldp-image-row').length;

			let body = $(this).siblings('.wpldp-image-table').find('.wpldp-image-body');

			body.append(imageOptionRow(fieldId));

		});
		$(document).on('click', '.wpldp-image-row .remove-option', function(){
			$(this).closest('.wpldp-image-row').remove();
		});

		$(document).on('click', '.upload-btn', function(e){

			e.preventDefault();

			let button = $(this);
			let input = button.siblings('.image-id');

			let frame = wp.media({
				title: 'Select Image',
				button: { text: 'Use this image' },
				multiple: false
			});

			frame.on('select', function(){

				let attachment = frame.state().get('selection').first().toJSON();

				input.val(attachment.id);

				// show preview
				button.html(`<img src="${attachment.url}" style="height:40px;">`);

			});

			frame.open();
		});

		$(document).on('click', '.wpldp-add-color-option', function(){
			var fieldId = $(this).data('fieldid');
			var field_index = $(this).siblings('.wpldp-color-table').find('.wpldp-color-body .wpldp-color-row').length;

			let body = $(this).siblings('.wpldp-color-table').find('.wpldp-color-body');

			body.append(colorOptionRow(fieldId));

		});

		$(document).on('click', '.wpldp-color-row .remove-option', function(){
			$(this).closest('.wpldp-color-row').remove();
		});

		// picker → input
		$(document).on('input', '.color-picker', function(){
			let val = $(this).val();
			$(this).closest('.wpldp-color-row').find('.color-code').val(val);
		});

		// input → picker
		$(document).on('input', '.color-code', function(){

			let val = $(this).val();

			if(/^#([0-9A-F]{3}){1,2}$/i.test(val)){
				$(this).closest('.wpldp-color-row').find('.color-picker').val(val);
			}

		});

		$(document).on('submit', '#wpldp-addon-all-fields-data-form', function(e){
			e.preventDefault();
			let form = $(this)[0];
			let formData = new FormData(form);

			let groupId = $(this).data('groupid');

			// manually append action + nonce
			formData.append('action', 'wpldp_update_group_all_fields');
			formData.append('nonce', wpldp_ajax.nonce);
			formData.append('group_id', groupId);

			// current visual order of fields (for drag-to-reorder persistence)
			let fieldOrder = $('.wpldp-product-addon-fields-list .wpldp-field-box').map(function(){
				return $(this).data('fieldid');
			}).get();
			formData.append('field_order', JSON.stringify(fieldOrder));

			showLoader();
			$.ajax({
				url: wpldp_ajax.url,
				method: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(res){

					if(res.success){
						showToast('Group updated successfully');

					} else {
						showToast(res.data?.message || 'Error saving', 'error');
					}
					hideLoader();
				}
			});
		});

		$(document).on('submit', '.wpldp-addon-field-data-form', function(e){
			e.preventDefault();
			let form = $(this)[0];
			let formData = new FormData(form);

			let groupId = $(this).data('groupid');
			let fieldId = $(this).data('fieldid');

			// manually append action + nonce
			// manually append action + nonce
			formData.append('action', 'wpldp_update_group_field_data');
			formData.append('nonce', wpldp_ajax.nonce);
			formData.append('group_id', groupId);
			formData.append('field_id', fieldId);

			showLoader();
			$.ajax({
				url: wpldp_ajax.url,
				method: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(res){

					if(res.success){
						showToast('Field updated successfully');

					} else {
						showToast(res.data?.message || 'Error saving', 'error');
					}
					hideLoader();
				}
			});

		});

		// $(document).on('change', '.wpldp-addon-field-data-form input, .wpldp-addon-field-data-form select', function(){
		// 	// trigger form submit on any change for better UX (auto save)
		// 	$(this).closest('form.wpldp-addon-field-data-form').submit();
		// });

		/* DUPLICATE GROUP field */
		$(document).on('click', '.wpldp-copy-addon-field', function(){

			let groupId = $(this).data('groupid');
			let fieldId = $(this).data('fieldid');

			showLoader();

			$.post(wpldp_ajax.url, {
				action: 'wpldp_duplicate_group_addon_field',
				nonce: wpldp_ajax.nonce,
				group_id: groupId,
				field_id: fieldId
			}, function(res){

				if(res.success){
					appendFieldBuilder(groupId, res.data.field_id, res.data.field_data);
					initFieldSortable();
					showToast('Field duplicated successfully');

				} else {
					showToast(res.data?.message || 'Duplicate failed', 'error');
				}
				hideLoader();
			});

		});

    });
    
})( jQuery );