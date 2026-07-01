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

	window.showToast = function (message, type = 'success') {
		let icon = '✔';

		if(type === 'error') icon = '✖';
		if(type === 'warning') icon = '!';

		$('#wpldp-toast').remove();

		let toast = $(`
			<div id="wpldp-toast" class="wpldp-toast wpldp-${type}">
				<span class="wpldp-toast-icon">${icon}</span>
				<span class="wpldp-toast-text">${message}</span>
			</div>
		`);

		$('body').append(toast);

		setTimeout(()=>toast.addClass('show'),10);

		setTimeout(()=>{
			toast.removeClass('show');
			setTimeout(()=>toast.remove(),300);
		},2500);
	};

	window.showLoader = function (message = 'Processing...') {
		// $('#wpldp-loader').remove();
		// let loader = $(`
		// 	<div id="wpldp-loader">
		// 		<div class="wpldp-loader-spinner"></div>
		// 		<span class="wpldp-loader-text">${message}</span>
		// 	</div>
		// `);

		// $('body .wpldp-wrapper').append(loader);
	};

	window.hideLoader = function () {
		//$('#wpldp-loader').remove();
	};

	$(document).ready(function(){

		$(".tab").click(function(){

			var tab = $(this).data("tab");

			$(".tab").removeClass("active");
			$(this).addClass("active");

			$(".wpldp-tab-panel").removeClass("active");

			$("#"+tab).addClass("active");

		});

		$('.dukkan-menu-item').on('click',function(){

			let tab = $(this).data('tab');

			$('.dukkan-menu-item').removeClass('active');
			$(this).addClass('active');

			$('.dukkan-tab').removeClass('active');
			$('#dukkan-tab-'+tab).addClass('active');

		});

		// ================================================================
		// Order Status Management
		// ================================================================

		var i18n = wpldp_ajax.os_i18n || {};

		var os = {
			$list: $('#dukkan-os-list'),
			$empty: $('#dukkan-os-empty'),

			// Modal elements
			$modal: $('#dukkan-os-modal'),
			$modalOverlay: $('#dukkan-os-modal-overlay'),
			$modalTitle: $('#dukkan-os-modal-title'),
			$modalOldSlug: $('#dukkan-os-modal-old-slug'),
			$modalName: $('#dukkan-os-modal-name'),
			$modalSlug: $('#dukkan-os-modal-slug'),
			$modalError: $('#dukkan-os-modal-error'),
			$modalSave: $('#dukkan-os-modal-save'),

			// Delete modal
			$deleteModal: $('#dukkan-os-delete-modal'),
			$deleteOverlay: $('#dukkan-os-delete-overlay'),
			$deleteConfirm: $('#dukkan-os-delete-confirm'),

			deleteSlug: '',

			/**
			 * Open the add/edit modal.
			 */
			openModal: function (title, oldSlug, name, slug) {
				os.$modalTitle.text(title);
				os.$modalOldSlug.val(oldSlug || '');
				os.$modalName.val(name || '');
				os.$modalSlug.val(slug || '');
				os.$modalError.removeClass('active').text('');
				os.$modalSave.prop('disabled', false).html(i18n.save_btn || 'Save Status');
				os.$modal.addClass('active');
				os.$modalOverlay.addClass('active');
				setTimeout(function () { os.$modalName.focus(); }, 100);
			},

			/**
			 * Close the add/edit modal.
			 */
			closeModal: function () {
				os.$modal.removeClass('active');
				os.$modalOverlay.removeClass('active');
			},

			/**
			 * Open the delete confirmation modal.
			 */
			openDeleteModal: function (slug) {
				os.deleteSlug = slug;
				os.$deleteModal.addClass('active');
				os.$deleteOverlay.addClass('active');
			},

			/**
			 * Close the delete modal.
			 */
			closeDeleteModal: function () {
				os.deleteSlug = '';
				os.$deleteModal.removeClass('active');
				os.$deleteOverlay.removeClass('active');
			},

			/**
			 * Make AJAX POST request.
			 */
			ajaxPost: function (action, data, onSuccess) {
				$.post(wpldp_ajax.url, $.extend({
					action: action,
					nonce: wpldp_ajax.nonce
				}, data))
				.done(function (response) {
					if (response.success) {
						if (onSuccess) onSuccess(response.data);
					} else {
						showToast(response.data.message || 'An error occurred.', 'error');
					}
				})
				.fail(function () {
					showToast('Request failed. Please try again.', 'error');
				});
			},

			/**
			 * Refresh the list from server.
			 */
			refreshList: function () {
				os.ajaxPost('dukkan_os_list', {}, function (statuses) {
					os.$list.empty();
					os.$empty.hide();

					if (!statuses || !statuses.length) {
						os.$empty.show();
						return;
					}

					$.each(statuses, function (i, status) {
						var $item = $(`
							<div class="dukkan-os__item" data-slug="${os.escAttr(status.slug)}">
								<div class="dukkan-os__item-drag">
									<i class="fa-solid fa-grip-vertical"></i>
								</div>
								<div class="dukkan-os__item-content">
									<div class="dukkan-os__item-name">${os.escHtml(status.name)}</div>
									<div class="dukkan-os__item-slug">
										<code>wc-${os.escHtml(status.slug)}</code>
									</div>
								</div>
								<div class="dukkan-os__item-actions">
									<button type="button"
											class="dukkan-os__item-btn dukkan-os__item-btn--edit"
											data-slug="${os.escAttr(status.slug)}"
											data-name="${os.escAttr(status.name)}"
											title="${os.escAttr(i18n.edit || 'Edit')}">
										<i class="fa-solid fa-pen-to-square"></i>
									</button>
									<button type="button"
											class="dukkan-os__item-btn dukkan-os__item-btn--delete"
											data-slug="${os.escAttr(status.slug)}"
											title="${os.escAttr(i18n.delete || 'Delete')}">
										<i class="fa-solid fa-trash-can"></i>
									</button>
								</div>
							</div>
						`);
						os.$list.append($item);
					});

					os.initSortable();
				});
			},

			/**
			 * Escape HTML.
			 */
			escHtml: function (str) {
				var div = document.createElement('div');
				div.appendChild(document.createTextNode(str));
				return div.innerHTML;
			},

			/**
			 * Escape attribute.
			 */
			escAttr: function (str) {
				return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#039;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
			},

			/**
			 * Initialize jQuery UI Sortable for drag-and-drop reordering.
			 */
			initSortable: function () {
				if (os.$list.hasClass('ui-sortable')) {
					os.$list.sortable('destroy');
				}

				os.$list.sortable({
					handle: '.dukkan-os__item-drag',
					axis: 'y',
					placeholder: 'dukkan-os__item ui-sortable-placeholder',
					forcePlaceholderSize: true,
					opacity: 0.85,
					tolerance: 'pointer',
					update: function () {
						var order = os.$list.find('.dukkan-os__item').map(function () {
							return $(this).data('slug');
						}).get();

						os.ajaxPost('dukkan_os_reorder', { order: order }, function () {
							showToast(i18n.order_saved || 'Order saved.', 'success');
						});
					}
				});
			}
		};

		// ---- Add button ----
		$('#dukkan-os-add-btn').on('click', function () {
			os.openModal(i18n.add_title || 'Add New Order Status', '', '', '');
		});

		// ---- Edit button (delegated) ----
		os.$list.on('click', '.dukkan-os__item-btn--edit', function () {
			var $btn = $(this);
			os.openModal(
				i18n.edit_title || 'Edit Order Status',
				$btn.data('slug'),
				$btn.data('name'),
				$btn.data('slug')
			);
		});

		// ---- Delete button (delegated) ----
		os.$list.on('click', '.dukkan-os__item-btn--delete', function () {
			os.openDeleteModal($(this).data('slug'));
		});

		// ---- Modal close ----
		$('#dukkan-os-modal-close, #dukkan-os-modal-cancel, #dukkan-os-modal-overlay').on('click', function () {
			os.closeModal();
		});

		// ---- Modal save ----
		$('#dukkan-os-modal-save').on('click', function () {
			var $btn     = $(this);
			var oldSlug  = os.$modalOldSlug.val().trim();
			var name     = os.$modalName.val().trim();
			var slug     = os.$modalSlug.val().trim();
			var isEdit   = oldSlug !== '';

			os.$modalError.removeClass('active').text('');

			if (!name) {
				os.$modalError.addClass('active').text(i18n.name_required || 'Status name is required.');
				return;
			}
			if (!slug) {
				os.$modalError.addClass('active').text(i18n.slug_required || 'Status slug is required.');
				return;
			}
			if (slug.length > 20) {
				os.$modalError.addClass('active').text(i18n.slug_max || 'Status slug must be 20 characters or fewer.');
				return;
			}

			$btn.prop('disabled', true).html('<span class="dukkan-os__spinner"></span>' + (i18n.saving || 'Saving…'));

			var data = { name: name, slug: slug };
			if (isEdit) {
				data.old_slug = oldSlug;
			}

			os.ajaxPost(isEdit ? 'dukkan_os_update' : 'dukkan_os_add', data, function () {
				os.closeModal();
				showToast(isEdit ? (i18n.updated || 'Order status updated.') : (i18n.added || 'Order status added.'), 'success');
				os.refreshList();
			});

			setTimeout(function () {
				$btn.prop('disabled', false).text(i18n.save_btn || 'Save Status');
			}, 3000);
		});

		// ---- Enter key in modal fields ----
		$('#dukkan-os-modal-name, #dukkan-os-modal-slug').on('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				$('#dukkan-os-modal-save').trigger('click');
			}
		});

		// ---- Delete modal ----
		$('#dukkan-os-delete-close, #dukkan-os-delete-cancel, #dukkan-os-delete-overlay').on('click', function () {
			os.closeDeleteModal();
		});

		$('#dukkan-os-delete-confirm').on('click', function () {
			if (!os.deleteSlug) return;

			var $btn = $(this);
			$btn.prop('disabled', true).text(i18n.deleting || 'Deleting…');

			os.ajaxPost('dukkan_os_delete', { slug: os.deleteSlug }, function () {
				os.closeDeleteModal();
				showToast(i18n.deleted || 'Order status deleted.', 'success');
				os.refreshList();
			});

			setTimeout(function () {
				$btn.prop('disabled', false).text(i18n.delete || 'Delete');
			}, 3000);
		});

		// ---- Initialize sortable if list exists ----
		if (os.$list.length && os.$list.children().length) {
			os.initSortable();
		}

		// ---- Auto-generate slug from name ----
		var slugTimeout;
		$('#dukkan-os-modal-name').on('input', function () {
			// Only auto-fill slug when adding new (not editing).
			if (os.$modalOldSlug.val()) return;

			clearTimeout(slugTimeout);
			var $slugField = $('#dukkan-os-modal-slug');
			slugTimeout = setTimeout(function () {
				var name = os.$modalName.val().trim();
				if (name) {
					var generated = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').substring(0, 20);
					if (!$slugField.val()) {
						$slugField.val(generated);
					}
				}
			}, 300);
		});

		// ================================================================
		// Dynamic Pricing & Discounts Management
		// ================================================================

		var dpI18n = wpldp_ajax.dp_i18n || {};

		var dp = {
			$list: $('#dukkan-dp-list'),
			$empty: $('#dukkan-dp-empty'),

			// Modal elements
			$modal: $('#dukkan-dp-modal'),
			$modalOverlay: $('#dukkan-dp-modal-overlay'),
			$modalTitle: $('#dukkan-dp-modal-title'),
			$modalRuleId: $('#dukkan-dp-modal-rule-id'),
			$modalName: $('#dukkan-dp-rule-name'),
			$modalDesc: $('#dukkan-dp-rule-desc'),
			$modalDiscType: $('#dukkan-dp-discount-type'),
			$modalDiscValue: $('#dukkan-dp-discount-value'),
			$modalAppliesTo: $('#dukkan-dp-applies-to'),
			$modalCategories: $('#dukkan-dp-categories'),
			$modalProducts: $('#dukkan-dp-products'),
			$modalMinQty: $('#dukkan-dp-min-qty'),
			$modalMinAmount: $('#dukkan-dp-min-amount'),
			$modalStartDate: $('#dukkan-dp-start-date'),
			$modalEndDate: $('#dukkan-dp-end-date'),
			$modalStatus: $('#dukkan-dp-status'),
			$modalError: $('#dukkan-dp-modal-error'),
			$modalSave: $('#dukkan-dp-modal-save'),
			$valueHint: $('#dukkan-dp-value-hint'),

			// Delete modal
			$deleteModal: $('#dukkan-dp-delete-modal'),
			$deleteOverlay: $('#dukkan-dp-delete-overlay'),
			$deleteConfirm: $('#dukkan-dp-delete-confirm'),

			deleteRuleId: '',

			/**
			 * Open the add/edit modal.
			 */
			openModal: function (title, rule) {
				rule = rule || {};
				dp.$modalTitle.text(title);
				dp.$modalRuleId.val(rule.id || '');
				dp.$modalName.val(rule.name || '');
				dp.$modalDesc.val(rule.description || '');
				dp.$modalDiscType.val(rule.discount_type || 'percentage');
				dp.$modalDiscValue.val(rule.discount_value !== undefined ? rule.discount_value : '');
				dp.$modalAppliesTo.val(rule.applies_to || 'all');
				dp.$modalMinQty.val(rule.min_quantity || '');
				dp.$modalMinAmount.val(rule.min_amount || '');
				dp.$modalStartDate.val(rule.start_date || '');
				dp.$modalEndDate.val(rule.end_date || '');
				dp.$modalStatus.prop('checked', rule.status !== undefined ? !!rule.status : true);
				dp.$modalError.removeClass('active').text('');
				dp.$modalSave.prop('disabled', false).text(dpI18n.save_btn || 'Save Rule');

				dp.updateDiscountHint();
				dp.toggleConditionalFields();

				// Reset and repopulate select2 fields
				if (rule.categories && rule.categories.length) {
					dp.populateCategories(rule.categories);
				} else {
					dp.$modalCategories.val(null).trigger('change');
				}

				if (rule.products && rule.products.length) {
					dp.populateProducts(rule.products);
				} else {
					dp.$modalProducts.val(null).trigger('change');
				}

				dp.$modal.addClass('active');
				dp.$modalOverlay.addClass('active');
				setTimeout(function () { dp.$modalName.focus(); }, 100);
			},

			/**
			 * Close the add/edit modal.
			 */
			closeModal: function () {
				dp.$modal.removeClass('active');
				dp.$modalOverlay.removeClass('active');
			},

			/**
			 * Open the delete confirmation modal.
			 */
			openDeleteModal: function (ruleId) {
				dp.deleteRuleId = ruleId;
				dp.$deleteModal.addClass('active');
				dp.$deleteOverlay.addClass('active');
			},

			/**
			 * Close the delete modal.
			 */
			closeDeleteModal: function () {
				dp.deleteRuleId = '';
				dp.$deleteModal.removeClass('active');
				dp.$deleteOverlay.removeClass('active');
			},

			/**
			 * Update discount value hint based on type.
			 */
			updateDiscountHint: function () {
				var type = dp.$modalDiscType.val();
				if (type === 'percentage') {
					dp.$valueHint.text(dpI18n.value_hint_percent || 'Enter the discount percentage (e.g. 20 for 20% off).');
				} else if (type === 'fixed') {
					dp.$valueHint.text(dpI18n.value_hint_fixed || 'Enter the fixed discount amount.');
				} else {
					dp.$valueHint.text(dpI18n.value_hint_buy || 'Enter the discount amount for Buy X Get Y offers.');
				}
			},

			/**
			 * Show/hide conditional fields based on applies-to selection.
			 */
			toggleConditionalFields: function () {
				var appliesTo = dp.$modalAppliesTo.val();
				$('#dukkan-dp-categories-field').toggle(appliesTo === 'categories');
				$('#dukkan-dp-products-field').toggle(appliesTo === 'products');
			},

			/**
			 * Populate categories select2 with selected values.
			 */
			populateCategories: function (categories) {
				var data = [];
				$.each(categories, function (i, catId) {
					$.ajax({
						url: wpldp_ajax.url,
						data: {
							action: 'wpldp_get_categories',
							nonce: wpldp_ajax.nonce
						},
						async: false,
						success: function (html) {
							var $temp = $('<div>').html(html);
							var $option = $temp.find('input[value="' + catId + '"]');
							if ($option.length) {
								data.push({
									id: catId,
									text: $option.siblings('.cat-name').text()
								});
							}
						}
					});
				});

				// Since loading categories asynchronously is complex with Select2,
				// we load all categories and pre-select.
				$.ajax({
					url: wpldp_ajax.url,
					data: {
						action: 'wpldp_get_categories',
						nonce: wpldp_ajax.nonce
					},
					success: function (html) {
						var $temp = $('<div>').html(html);
						var options = [];
						$temp.find('.cat-checkbox').each(function () {
							options.push({
								id: $(this).val(),
								text: $(this).siblings('.cat-name').text()
							});
						});
						dp.rebuildSelect2(dp.$modalCategories, options, categories);
					}
				});
			},

			/**
			 * Populate products select2 with selected values.
			 */
			populateProducts: function (products) {
				var selected = [];
				$.each(products, function (i, product) {
					var id = typeof product === 'object' ? product.id : product;
					var name = typeof product === 'object' ? product.name : String(id);
					selected.push({ id: id, text: name });
				});

				// Set initial selections
				dp.rebuildSelect2(dp.$modalProducts, selected, []);
			},

			/**
			 * Rebuild a Select2 instance with options and selections.
			 */
			rebuildSelect2: function ($select, allOptions, selectedIds) {
				$select.empty();
				$.each(allOptions, function (i, opt) {
					var $option = new Option(opt.text, opt.id, false, selectedIds.indexOf(parseInt(opt.id)) !== -1);
					$select.append($option);
				});
				$select.trigger('change');
			},

			/**
			 * Make AJAX POST request.
			 */
			ajaxPost: function (action, data, onSuccess) {
				$.post(wpldp_ajax.url, $.extend({
					action: action,
					nonce: wpldp_ajax.nonce
				}, data))
				.done(function (response) {
					if (response.success) {
						if (onSuccess) onSuccess(response.data);
					} else {
						showToast(response.data.message || 'An error occurred.', 'error');
					}
				})
				.fail(function () {
					showToast('Request failed. Please try again.', 'error');
				});
			},

			/**
			 * Collect form data into a rule object.
			 */
			collectFormData: function () {
				return {
					name: dp.$modalName.val().trim(),
					description: dp.$modalDesc.val().trim(),
					discount_type: dp.$modalDiscType.val(),
					discount_value: dp.$modalDiscValue.val(),
					applies_to: dp.$modalAppliesTo.val(),
					categories: dp.$modalCategories.val() || [],
					products: dp.$modalProducts.val() || [],
					min_quantity: dp.$modalMinQty.val(),
					min_amount: dp.$modalMinAmount.val(),
					start_date: dp.$modalStartDate.val(),
					end_date: dp.$modalEndDate.val(),
					status: dp.$modalStatus.is(':checked') ? 1 : 0
				};
			},

			/**
			 * Refresh the list from server.
			 */
			refreshList: function () {
				dp.ajaxPost('dukkan_dp_list', {}, function (rules) {
					dp.$list.empty();
					dp.$empty.hide();

					if ($.isEmptyObject(rules)) {
						dp.$empty.show();
						return;
					}

					$.each(rules, function (ruleId, rule) {
						var badgeText = '';
						if (rule.discount_type === 'percentage') {
							badgeText = rule.discount_value + '% ' + (dpI18n.off || 'off');
						} else if (rule.discount_type === 'fixed') {
							badgeText = (dpI18n.off || 'off') + ' ' + rule.discount_value;
						} else {
							badgeText = dpI18n.buy_x_get_y || 'Buy X Get Y';
						}

						var scopeText = '';
						if (rule.applies_to === 'all') {
							scopeText = dpI18n.all_products || 'All Products';
						} else if (rule.applies_to === 'categories') {
							scopeText = (rule.categories ? rule.categories.length : 0) + ' ' + (dpI18n.categories || 'Categories');
						} else {
							scopeText = (rule.products ? rule.products.length : 0) + ' ' + (dpI18n.products_label || 'Products');
						}

						var $item = $(
							'<div class="dukkan-dp__item" data-rule-id="' + dp.escAttr(ruleId) + '">' +
								'<div class="dukkan-dp__item-main">' +
									'<div class="dukkan-dp__item-info">' +
										'<div class="dukkan-dp__item-name">' + dp.escHtml(rule.name) + '</div>' +
										'<div class="dukkan-dp__item-meta">' +
											'<span class="dukkan-dp__item-badge">' + dp.escHtml(badgeText) + '</span>' +
											'<span class="dukkan-dp__item-scope">' + dp.escHtml(scopeText) + '</span>' +
										'</div>' +
									'</div>' +
									'<label class="dukkan-dp__toggle wpldp-switch">' +
										'<input type="checkbox" class="dukkan-dp__toggle-input" data-rule-id="' + dp.escAttr(ruleId) + '"' + (rule.status ? ' checked' : '') + '>' +
										'<span class="wpldp-slider"></span>' +
									'</label>' +
								'</div>' +
								'<div class="dukkan-dp__item-actions">' +
									'<button type="button" class="dukkan-dp__item-btn dukkan-dp__item-btn--edit" data-rule-id="' + dp.escAttr(ruleId) + '" title="' + dp.escAttr(dpI18n.edit || 'Edit') + '">' +
										'<i class="fa-solid fa-pen-to-square"></i>' +
									'</button>' +
									'<button type="button" class="dukkan-dp__item-btn dukkan-dp__item-btn--copy" data-rule-id="' + dp.escAttr(ruleId) + '" title="' + dp.escAttr(dpI18n.duplicate || 'Duplicate') + '">' +
										'<i class="fa-solid fa-copy"></i>' +
									'</button>' +
									'<button type="button" class="dukkan-dp__item-btn dukkan-dp__item-btn--delete" data-rule-id="' + dp.escAttr(ruleId) + '" title="' + dp.escAttr(dpI18n.delete || 'Delete') + '">' +
										'<i class="fa-solid fa-trash-can"></i>' +
									'</button>' +
								'</div>' +
							'</div>'
						);
						dp.$list.append($item);
					});
				});
			},

			/**
			 * Escape HTML.
			 */
			escHtml: function (str) {
				var div = document.createElement('div');
				div.appendChild(document.createTextNode(str));
				return div.innerHTML;
			},

			/**
			 * Escape attribute.
			 */
			escAttr: function (str) {
				return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#039;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
			}
		};

		// ---- Add button ----
		$('#dukkan-dp-add-btn').on('click', function () {
			dp.openModal(dpI18n.add_title || 'Add Pricing Rule');
		});

		// ---- Edit button (delegated) ----
		dp.$list.on('click', '.dukkan-dp__item-btn--edit', function () {
			var ruleId = $(this).data('rule-id');
			// Fetch full rule data and open modal.
			$.post(wpldp_ajax.url, {
				action: 'dukkan_dp_get',
				rule_id: ruleId,
				nonce: wpldp_ajax.nonce
			}, function (response) {
				if (response.success) {
					dp.openModal(dpI18n.edit_title || 'Edit Pricing Rule', response.data);
				} else {
					showToast(response.data.message || 'Failed to load rule.', 'error');
				}
			}).fail(function () {
				showToast('Request failed. Please try again.', 'error');
			});
		});

		// ---- Duplicate button (delegated) ----
		dp.$list.on('click', '.dukkan-dp__item-btn--copy', function () {
			var ruleId = $(this).data('rule-id');
			dp.ajaxPost('dukkan_dp_duplicate', { rule_id: ruleId }, function (data) {
				showToast(dpI18n.duplicated || 'Pricing rule duplicated.', 'success');
				dp.refreshList();
			});
		});

		// ---- Delete button (delegated) ----
		dp.$list.on('click', '.dukkan-dp__item-btn--delete', function () {
			dp.openDeleteModal($(this).data('rule-id'));
		});

		// ---- Toggle switch (delegated) ----
		dp.$list.on('change', '.dukkan-dp__toggle-input', function () {
			var ruleId = $(this).data('rule-id');
			var status = $(this).is(':checked') ? 1 : 0;
			dp.ajaxPost('dukkan_dp_toggle', { rule_id: ruleId, status: status }, function () {
				showToast(status ? (dpI18n.enabled || 'Rule enabled.') : (dpI18n.disabled || 'Rule disabled.'), 'success');
			});
		});

		// ---- Modal close ----
		$('#dukkan-dp-modal-close, #dukkan-dp-modal-cancel, #dukkan-dp-modal-overlay').on('click', function () {
			dp.closeModal();
		});

		// ---- Discount type change ----
		dp.$modalDiscType.on('change', function () {
			dp.updateDiscountHint();
		});

		// ---- Applies-to change ----
		dp.$modalAppliesTo.on('change', function () {
			dp.toggleConditionalFields();
		});

		// ---- Modal save ----
		$('#dukkan-dp-modal-save').on('click', function () {
			var $btn = $(this);
			var ruleId = dp.$modalRuleId.val().trim();
			var ruleData = dp.collectFormData();
			var isEdit = ruleId !== '';

			dp.$modalError.removeClass('active').text('');

			if (!ruleData.name) {
				dp.$modalError.addClass('active').text(dpI18n.name_required || 'Rule name is required.');
				return;
			}
			if (!ruleData.discount_value || parseFloat(ruleData.discount_value) <= 0) {
				dp.$modalError.addClass('active').text(dpI18n.value_required || 'Discount value must be greater than zero.');
				return;
			}
			if (ruleData.applies_to === 'categories' && (!ruleData.categories || !ruleData.categories.length)) {
				dp.$modalError.addClass('active').text(dpI18n.categories_required || 'Please select at least one category.');
				return;
			}
			if (ruleData.applies_to === 'products' && (!ruleData.products || !ruleData.products.length)) {
				dp.$modalError.addClass('active').text(dpI18n.products_required || 'Please select at least one product.');
				return;
			}

			$btn.prop('disabled', true).text(dpI18n.saving || 'Saving…');

			var ajaxAction = isEdit ? 'dukkan_dp_update' : 'dukkan_dp_add';
			var ajaxData = { rule: ruleData };
			if (isEdit) {
				ajaxData.rule_id = ruleId;
			}

			dp.ajaxPost(ajaxAction, ajaxData, function () {
				dp.closeModal();
				showToast(isEdit ? (dpI18n.updated || 'Pricing rule updated.') : (dpI18n.added || 'Pricing rule added.'), 'success');
				dp.refreshList();
			});

			setTimeout(function () {
				$btn.prop('disabled', false).text(dpI18n.save_btn || 'Save Rule');
			}, 3000);
		});

		// ---- Enter key in modal fields ----
		$('#dukkan-dp-modal').on('keydown', 'input', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				$('#dukkan-dp-modal-save').trigger('click');
			}
		});

		// ---- Delete modal ----
		$('#dukkan-dp-delete-close, #dukkan-dp-delete-cancel, #dukkan-dp-delete-overlay').on('click', function () {
			dp.closeDeleteModal();
		});

		$('#dukkan-dp-delete-confirm').on('click', function () {
			if (!dp.deleteRuleId) return;

			var $btn = $(this);
			$btn.prop('disabled', true).text(dpI18n.deleting || 'Deleting…');

			dp.ajaxPost('dukkan_dp_delete', { rule_id: dp.deleteRuleId }, function () {
				dp.closeDeleteModal();
				showToast(dpI18n.deleted || 'Pricing rule deleted.', 'success');
				dp.refreshList();
			});

			setTimeout(function () {
				$btn.prop('disabled', false).text(dpI18n.delete_btn || 'Delete');
			}, 3000);
		});

		// ---- Initialize Select2 for categories and products in modal ----
		if ($.fn.select2) {
			dp.$modalCategories.select2({
				placeholder: dpI18n.select_categories || 'Search categories…',
				allowClear: true,
				width: '100%'
			});

			dp.$modalProducts.select2({
				placeholder: dpI18n.search_products || 'Search products…',
				allowClear: true,
				width: '100%',
				ajax: {
					url: wpldp_ajax.url,
					dataType: 'json',
					delay: 250,
					data: function (params) {
						return {
							action: 'wpldp_search_products',
							q: params.term,
							nonce: wpldp_ajax.nonce
						};
					},
					processResults: function (data) {
						return {
							results: $.map(data, function (item) {
								return { id: item.id, text: item.text };
							})
						};
					},
					cache: true
				},
				minimumInputLength: 1
			});

			// Load categories into the modal select2
			$.ajax({
				url: wpldp_ajax.url,
				data: {
					action: 'wpldp_get_categories',
					nonce: wpldp_ajax.nonce
				},
				success: function (html) {
					var $temp = $('<div>').html(html);
					$temp.find('.cat-checkbox').each(function () {
						var $option = new Option($(this).siblings('.cat-name').text(), $(this).val(), false, false);
						dp.$modalCategories.append($option);
					});
					dp.$modalCategories.trigger('change');
				}
			});
		}

	});

})( jQuery );
