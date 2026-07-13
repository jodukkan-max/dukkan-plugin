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

	function initProductSelect( $el ) {
        $el.selectWoo( {
            ajax: {
                url:      wpldp_ajax.url,
                dataType: 'json',
                delay:    250,
                data: function ( params ) {
                    return {
						action: $el.data( 'action' ),
						nonce: wpldp_ajax.nonce,
						q: params.term
                    };
                },
                processResults: function ( data ) {
                    return { results: data };
                },
                cache: true,
            },
            minimumInputLength: 3,
            allowClear:         false, // we handle remove on the external tags
            placeholder:        $el.data( 'placeholder' ),
            width:              '100%',
			dropdownParent:        $($el.data( 'dropdownparent' ) || null )
        } ).on( 'select2:select select2:unselect', function () {
            renderExternalTags( $el );
        } );
    }

	/**
     * Render selected values as pills OUTSIDE the SelectWoo input box,
     * into a dedicated tag container above it.
     */
    function renderExternalTags( $el ) {
        var $wrapper    = $el.closest( '.product-select-wrapper' );
        var $tagWrapper = $wrapper.find( '.selected-products-tags' );
        $tagWrapper.empty();

        var selected = $el.select2( 'data' );
        if ( ! selected || ! selected.length ) {
            $tagWrapper.hide();
            return;
        }

        $tagWrapper.show();
        $.each( selected, function ( i, item ) {
            var $tag = $(
                '<span class="product-tag">' +
                    '<span class="product-tag__text">' + $( '<div>' ).text( item.text ).html() + '</span>' +
                    '<button type="button" class="product-tag__remove" data-id="' + item.id + '" aria-label="Remove">&times;</button>' +
                '</span>'
            );
            $tagWrapper.append( $tag );
        } );

        // Remove tag on click
        $tagWrapper.find( '.product-tag__remove' ).on( 'click', function () {
            var id      = $( this ).data( 'id' ).toString();
            var current = $el.val() || [];
            var updated = current.filter( function ( v ) { return v !== id; } );
            $el.val( updated ).trigger( 'change' );
            renderExternalTags( $el );
        } );
    }

	$(document).ready(function(){
        function addGroupToSidebar(group){

			// remove empty message if exists
			$('.wpldp-empty').remove();

			let html = `
				<div class="wpldp-group" data-id="${group.id}">
					<div class="wpldp-group-top">
						<span>${group.group_name}</span>
						<label class="wpldp-switch">
							<input type="checkbox" class="wpldp-toggle-product-addon-status" data-id="${group.id}" checked>
							<span class="wpldp-slider"></span>
						</label>
					</div>
					<div class="wpldp-group-actions">
						<i class="fa-regular fa-copy wpldp-duplicate-product-addon-group"></i>
                        <i class="fa-solid fa-trash wpldp-delete-product-addon-group"></i>
					</div>
				</div>
			`;

			$('.wpldp-group-list').append(html);
		}

        /* LOAD CATEGORIES */
		function loadCategories(){
			$.post(wpldp_ajax.url, {
				action: 'wpldp_get_categories',
				nonce: wpldp_ajax.nonce
			}, function(res){
				$('.wpldp-category-list').html(res);
			});
		}

        function loadCategoriesForEdit(selected){

			$.post(wpldp_ajax.url, {
				action: 'wpldp_get_categories',
				nonce: wpldp_ajax.nonce
			}, function(res){

				$('.wpldp-category-list').html(res);

				// preselect
				selected.forEach(function(id){
					$('.cat-checkbox[value="'+id+'"]').prop('checked', true);
				});

			});
		}

        function initEditSelect(group){

			$('#wpldp-edit-products').selectWoo({
				placeholder: "Type to search products...",
				width: '100%',
				//dropdownParent: $('.wpldp-main'),
				minimumInputLength: 3, // allow initial load
				allowClear: true,
				ajax: {
					url: wpldp_ajax.url,
					dataType: 'json',
					delay: 250,
					data: function(params){
						return {
							action: 'wpldp_search_products',
							nonce: wpldp_ajax.nonce,
							q: params.term || ''
						};
					},
					processResults: function(data){
						return { results: data };
					}
				}
			});
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
			$('.wpldp-no-selection-box').show();
			$('#wpldp-addon-group-form-global').hide();
		}

        function renderEditPanel(group){
			let selected_product_options = '';
			if(group.products && group.products.length){

				group.products.forEach(function(product){
					selected_product_options += ` <option value="${product.id}" selected>${product.name}</option>`;
					
				});
			}

			let html = `
			<form id="wpldp-group-form-edit">
				<div class="wpldp-edit-box" data-id="${group.id}">

					<div class="wpldp-field">
						<label>Group Name</label>
						<input type="text" name="product_addon[group_name]" value="${group.group_name}">
					</div>

					<div class="wpldp-field">
						<label>Description</label>
						<textarea name="product_addon[description]" placeholder="Brief description of this group">${group.description}</textarea>
					</div>

					<div class="wpldp-field">
						<label>Applied To</label>
						<select name="product_addon[applied_to]" id="wpldp-edit-applied-to">
							<option value="all" ${group.applied_to=='all'?'selected':''}>All products</option>
							<option value="specific" ${group.applied_to=='specific'?'selected':''}>Specific products or categories</option>
						</select>
					</div>

					<div id="wpldp-conditional-edit-box" style="${group.applied_to=='specific'?'display:block;':'display:none;'}">
						<div class="wpldp-field product-select-wrapper">
							<label>Show in products:</label>
							<div class="selected-products-tags" style="display:none;"></div>
							<select name="product_addon[products][]" data-dropdownparent="#wpldp-group-form-edit" id="wpldp-edit-products" class="wc-product-search" data-allow_clear="true" data-placeholder="Search for products…"  data-action="wpldp_search_products" multiple style="width:100%">${selected_product_options}</select>
						</div>
						<div class="wpldp-field">
							<label>Show in categories:</label>
							<input type="text" id="wpldp-category-search-edit" placeholder="Type to search categories...">
							<div class="wpldp-category-list"></div>
						</div>
					</div>
			`;

			html += `</div></form>`;

			$('.wpldp-no-selection-box').hide();

			$('.wpldp-addon-group-details').html(html);

			let fields_html = `<div class="wpldp-fields-header">
                <h3>Fields</h3>
                <button data-id="${group.id}" type="button" class="wpldp-add-field-btn">
                    <i class="fa-solid fa-plus"></i> Add Field
                </button>
            </div>`;

			fields_html += `<div class="wpldp-fields-empty" style="${(group.fields && Object.keys(group.fields).length > 0) ? 'display:none;' : 'display:block;'}">

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

			$('.wpldp-product-addon-fields').html(fields_html);
			$('#wpldp-addon-group-form-global').show();

			// init selectWoo
			//initEditSelect(group);
			
			var $productSelectEdit = $('#wpldp-edit-products');
			initProductSelect( $productSelectEdit );
			$productSelectEdit.trigger( 'change' );
            renderExternalTags( $productSelectEdit );

			// load categories
			// if(group.applied_to === 'specific'){
				loadCategoriesForEdit(group.categories);
			//}

			if(group.fields && Object.keys(group.fields).length > 0){
				$('.wpldp-fields-empty').hide();
				for(let field_id in group.fields){
					appendFieldBuilder(group.id, field_id, group.fields[field_id]);
				}
			}else{
				$('.wpldp-fields-empty').show();
			}
		}
        
        // OPEN MODAL
		$('.wpldp-new-group').on('click', function(){

			$('#wpldpModal')
				.css({
					display: 'flex',
					alignItems: 'center',
					justifyContent: 'center'
				})
				.hide()
				.fadeIn(120);

		});

        $('.wpldp-close, .wpldp-cancel').on('click', function(){
			$('#wpldpModal').fadeOut(120);
		});

        $('#wpldpModal').on('click', function(e){
			if($(e.target).is('#wpldpModal')){
				$(this).fadeOut(120);
			}
		});

        /* TRIGGER ON SELECT */
		$('#wpldp-applied-to').on('change', function(){

			if($(this).val() === 'specific'){
				$('#wpldp-conditional-box').slideDown(150);
				loadCategories();
			} else {
				$('#wpldp-conditional-box').slideUp(150);
			}

		});

		$(document).on('change', '#wpldp-edit-applied-to', function(){

			if($(this).val() === 'specific'){
				$('#wpldp-conditional-edit-box').slideDown(150);
				//loadCategoriesForEdit();
			} else {
				$('#wpldp-conditional-edit-box').slideUp(150);
			}

		});

        /* CATEGORY SEARCH (FRONTEND ONLY) */
		$(document).on('keyup', '#wpldp-category-search', function(){

			let value = $(this).val().toLowerCase();

			$('.wpldp-cat-item').each(function(){

				let text = $(this).find('.cat-name').text().toLowerCase();

				if(text.includes(value)){
					$(this).show();
				} else {
					$(this).hide();
				}

			});

			// hide empty sub categories
			// $('.wpldp-sub-cat').each(function(){

			// 	let visibleChildren = $(this).find('.wpldp-cat-item:visible').length;

			// 	if(visibleChildren > 0){
			// 		$(this).show();
			// 	} else {
			// 		$(this).hide();
			// 	}

			// });

		});

        /* CHECKBOX TREE LOGIC (UPDATED) */
		$(document).on('change', '.cat-checkbox', function(){

			let isChecked = $(this).is(':checked');

			let currentLabel = $(this).closest('.wpldp-cat-item');

			// 1. PARENT → CHILD
			let subCat = currentLabel.next('.wpldp-sub-cat');

			if(subCat.length){
				subCat.find('.cat-checkbox').prop('checked', isChecked);
			}

			// 2. CHILD → PARENT
			let parentSubCat = currentLabel.closest('.wpldp-sub-cat');

			if(parentSubCat.length){

				let parentLabel = parentSubCat.prev('.wpldp-cat-item');

				let allChecked = parentSubCat.find('.cat-checkbox').length === parentSubCat.find('.cat-checkbox:checked').length;

				let anyChecked = parentSubCat.find('.cat-checkbox:checked').length > 0;

				if(allChecked){
					parentLabel.find('.cat-checkbox').prop('checked', true);
				} else if(!anyChecked){
					parentLabel.find('.cat-checkbox').prop('checked', false);
				} else {
					// optional: partial state (not native checkbox UI but useful)
					parentLabel.find('.cat-checkbox').prop('checked', false);
				}
			}

		});

        /* SELECT2 PRODUCT SEARCH */
		var $productSelect = $( '#wpldp-product-search' );
		initProductSelect( $productSelect );
		// $('#wpldp-product-search').selectWoo({
		// 	placeholder: "Type to search products...",
		// 	width: '100%',
		// 	dropdownParent: $('#wpldpModal'), // THIS FIXES IT
		// 	minimumInputLength: 3,
        // 	allowClear: true,
		// 	ajax: {
		// 		url: wpldp_ajax.url,
		// 		dataType: 'json',
		// 		delay: 250,
		// 		data: function (params) {
		// 			return {
		// 				action: 'wpldp_search_products',
		// 				nonce: wpldp_ajax.nonce,
		// 				q: params.term
		// 			};
		// 		},
		// 		processResults: function (data) {
		// 			return { results: data };
		// 		}
		// 	}
		// });

        /* FORM SUBMIT -- adding product addon group */
		$('#wpldp-group-form').on('submit', function(e){

			e.preventDefault();

			let form = $(this)[0];
			let formData = new FormData(form);

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

						$('#wpldpModal').fadeOut(150);

						// reset form
						$('#wpldp-group-form')[0].reset();
						$('.cat-checkbox').prop('checked', false);
						$('#wpldp-product-search').val(null).trigger('change');

					} else {
						showToast(res.data?.message || 'Error saving', 'error');
					}
					hideLoader();
				}
			});

		});

        /* DELETE GROUP */
		$(document).on('click', '.wpldp-delete-product-addon-group', function(){

			if(!confirm('Are you sure you want to delete this group?')) return;

			let groupEl = $(this).closest('.wpldp-group');
			let groupId = groupEl.data('id');

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

							$('.wpldp-no-selection-box').show();
							$('.wpldp-addon-group-details').html('');
							$('.wpldp-product-addon-fields').html('');
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

		});

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
						<span class="wpldp-drag">⋮⋮</span>

						<strong class="wpldp-field-type-label">${getFieldTypeSelectedOption(field_data ? field_data.type : 'text')}</strong>
						<span class="wpldp-field-title-label">${field_data ? field_data.title : 'New Add-On Field'}</span>

						<div class="wpldp-field-actions">
							<i class="fa-regular fa-copy wpldp-copy-addon-field" data-groupid="${groupId}" data-fieldid="${fieldId}"></i>
							<i class="fa-solid fa-trash wpldp-delete-addon-field" data-groupid="${groupId}" data-fieldid="${fieldId}"></i>
							<i class="fa-solid fa-angle-up toggle"></i>
						</div>
					</div>

					<div class="wpldp-field-body">

						<!-- FIELD TYPE -->
						<div class="wpldp-field">
							<label>Field Type</label>

							<div class="wpldp-custom-select">
								<div class="wpldp-selected">
									${getFieldTypeSelectedOption(field_data ? field_data.type : 'text')}
								</div>

								<div class="wpldp-options" data-fieldid="${fieldId}">
									${getFieldTypeOptions()}
								</div>

								<input type="hidden" class="wpldp-field-type" name="fields[${fieldId}][type]" value="${field_data ? field_data.type : 'text'}">
							</div>
						</div>

						<!-- FIELD TITLE -->
						<div class="wpldp-field">
							<label>Field Title</label>
							<input type="text" name="fields[${fieldId}][title]" class="wpldp-input-title" value="${field_data ? field_data.title : 'New Add-On Field'}">
						</div>

						<!-- FIELD WIDTH -->
						<div class="wpldp-field">
							<label>Field Width</label>
							<select class="wpldp-input-title" name="fields[${fieldId}][width]">
								<option ${(field_data && (field_data.width == '100%')) ? 'selected' : ''} value="100%">100%</option>
								<option ${(field_data && (field_data.width == '75%')) ? 'selected' : ''} value="75%">75%</option>
								<option ${(field_data && (field_data.width == '50%')) ? 'selected' : ''} value="50%">50%</option>
								<option ${(field_data && (field_data.width == '25%')) ? 'selected' : ''} value="25%">25%</option>
							</select>
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

					<i class="fa-solid fa-trash remove-option"></i>

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
							<i class="fa-solid fa-upload"></i>
						</button>
						<input type="hidden" name="fields[${fieldId}][options][${index}][image_id]" value="${option_data ? option_data.image_id : ''}" class="image-id">
					</div>

					<input type="number" name="fields[${fieldId}][options][${index}][price]" value="${option_data ? option_data.price : 0}">

					<i class="fa-solid fa-trash remove-option"></i>

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
					<i class="fa-solid fa-trash remove-option"></i>
				</div>
			`;
		}

		$(document).on('click', '.wpldp-add-field-btn', function(){
			var groupId = $(this).data('id');
			appendFieldBuilder(groupId);
			$('.wpldp-fields-empty').hide();
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
			select.find('.wpldp-selected').html(icon + ' ' + text);
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
					showToast('Field duplicated successfully');

				} else {
					showToast(res.data?.message || 'Duplicate failed', 'error');
				}
				hideLoader();
			});

		});

		/* DELETE GROUP field */
		$(document).on('click', '.wpldp-delete-addon-field', function(){

			if(!confirm('Are you sure you want to delete this field?')) return;

			let groupId = $(this).data('groupid');
			let fieldId = $(this).data('fieldid');
			let fieldEl = $(this).closest('.wpldp-field-box');

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

						// show empty message if no groups left
						if($('.wpldp-product-addon-fields-list .wpldp-field-box').length === 0){
							$('.wpldp-fields-empty').show();
						}
					});

					showToast('Field deleted successfully');

				} else {
					showToast(res.data?.message || 'Delete failed', 'error');
				}
				hideLoader();
			});

		});

    });
    
})( jQuery );