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
		// Dynamic Pricing & Discounts — Inline Rule Cards
		// ================================================================

		var dpI18n = wpldp_ajax.dp_i18n || {};

		var dp = {
			$list: $('#dukkan-dp-list'),
			$template: $('#dukkan-dp-rule-template'),

			/**
			 * Safely read the rule-id as a string. jQuery .data() can auto-parse
			 * numeric-like values, so we always force to string.
			 */
			ruleId: function ($rule) {
				return String($rule.data('rule-id') || $rule.attr('data-rule-id') || '');
			},

			/**
			 * Collect data from a rule card into an object.
			 */
			collectRuleData: function ($rule) {
				return {
					id: dp.ruleId($rule),
					method: $rule.find('[data-method]').val() || 'simple_adjustment',
					note: $rule.find('[data-note]').val() || '',
					description: $rule.find('[data-description]').val() || '',
					adjustment_type: $rule.find('[data-adjustment-type]').val() || 'fixed_discount',
					adjustment_amount: $rule.find('[data-adjustment-amount]').val() || '0.00',
					apply_with: $rule.find('[data-apply-with]').val() || 'apply_with_others',
					products: dp.collectProductIds($rule),
					conditions: dp.collectConditionData($rule)
				};
			},

			/**
			 * Collect product IDs from tags.
			 */
			collectProductIds: function ($rule) {
				var ids = [];
				$rule.find('[data-product-id]').each(function () {
					ids.push($(this).data('product-id'));
				});
				return ids;
			},

			/**
			 * Collect condition data from tags.
			 */
			collectConditionData: function ($rule) {
				var conditions = [];
				$rule.find('[data-condition-id]').each(function () {
					conditions.push({
						id: $(this).data('condition-id'),
						type: $(this).data('condition-type') || '',
						label: $(this).find('span').first().text() || ''
					});
				});
				return conditions;
			},

			/**
			 * Update method label in header when method select changes.
			 */
			updateMethodLabel: function ($rule) {
				var method = $rule.find('[data-method]').val();
				var $label = $rule.find('[data-method-label]');
				var labels = {
					simple_adjustment:                   dpI18n.simple_adjustment || 'Simple adjustment',
					bulk_pricing:                        dpI18n.bulk_pricing || 'Bulk pricing',
					tiered_pricing:                      dpI18n.tiered_pricing || 'Tiered pricing',
					group_of_products:                   dpI18n.group_of_products || 'Group of products',
					group_of_products_repeating:         dpI18n.group_of_products_repeating || 'Group of products - Repeating',
					buy_x_get_x:                         dpI18n.buy_x_get_x || 'Buy x get x',
					buy_x_get_x_repeating:               dpI18n.buy_x_get_x_repeating || 'Buy x get x - Repeating',
					buy_x_get_y:                         dpI18n.buy_x_get_y_label || 'Buy x get y',
					buy_x_get_y_repeating:               dpI18n.buy_x_get_y_repeating || 'Buy x get y - Repeating',
					exclude_products_from_all_rules:     dpI18n.exclude_products_from_all_rules || 'Exclude products from all rules',
					restrict_purchase_of_matched_products: dpI18n.restrict_purchase_of_matched_products || 'Restrict purchase of matched products'
				};
				$label.text(labels[method] || labels.simple_adjustment);
			},

			/**
			 * Debounced save of a single rule via AJAX.
			 */
			debouncedSave: (function () {
				var timers = {};
				return function (ruleId, ruleData) {
					if (timers[ruleId]) clearTimeout(timers[ruleId]);
					timers[ruleId] = setTimeout(function () {
						dp.ajaxPost('dukkan_dp_update', {
							rule_id: ruleId,
							rule: ruleData
						});
					}, 600);
				};
			})(),

			/**
			 * Make AJAX POST request.
			 */
			ajaxPost: function (action, data, onSuccess, onError) {
				$.post(wpldp_ajax.url, $.extend({
					action: action,
					nonce: wpldp_ajax.nonce
				}, data))
				.done(function (response) {
					if (response.success) {
						if (onSuccess) onSuccess(response.data);
					} else {
						showToast(response.data.message || 'An error occurred.', 'error');
						if (onError) onError(response.data);
					}
				})
				.fail(function () {
					showToast('Request failed. Please try again.', 'error');
				});
			},

			/**
			 * Add a new blank rule card.
			 */
			addRule: function () {
				// Create with temporary ID, then save to server to get real ID.
				var tempId = 'temp_' + Date.now();
				var html = dp.$template.html().replace(/\{\{RULE_ID\}\}/g, tempId);
				var $rule = $(html);

				dp.$list.append($rule);

				// Immediately save to server.
				var ruleData = dp.collectRuleData($rule);
				dp.ajaxPost('dukkan_dp_add', { rule: ruleData }, function (data) {
					// Update temp ID with real ID from server.
					$rule.attr('data-rule-id', data.id);
					$rule.find('[data-rule-id]').attr('data-rule-id', data.id);
					if (dp.$list.hasClass('ui-sortable')) {
						dp.$list.sortable('refresh');
					} else {
						dp.initSortable();
					}
				}, function () {
					// On failure, remove the card.
					$rule.remove();
				});

				if (dp.$list.hasClass('ui-sortable')) {
					dp.$list.sortable('refresh');
				} else {
					dp.initSortable();
				}

				// Scroll to the new rule.
				$('html, body').animate({ scrollTop: $rule.offset().top - 100 }, 300);
			},

			/**
			 * Remove a rule card.
			 */
			removeRule: function ($rule) {
				var ruleId = dp.ruleId($rule);
				if (!ruleId || ruleId.indexOf('temp_') === 0) {
					$rule.slideUp(200, function () { $(this).remove(); });
					return;
				}
				dp.ajaxPost('dukkan_dp_delete', { rule_id: ruleId }, function () {
					showToast(dpI18n.deleted || 'Pricing rule deleted.', 'success');
					$rule.slideUp(200, function () { $(this).remove(); });
				});
			},

			/**
			 * Duplicate a rule card.
			 */
			duplicateRule: function ($rule) {
				var ruleId = dp.ruleId($rule);

				// For temp rules, just clone client-side.
				if (ruleId && ruleId.indexOf('temp_') === 0) {
					var $clone = $rule.clone();
					var newTempId = 'temp_' + Date.now();
					$clone.attr('data-rule-id', newTempId);
					$clone.find('[data-rule-id]').attr('data-rule-id', newTempId);
					$clone.insertAfter($rule);
					if (dp.$list.hasClass('ui-sortable')) {
						dp.$list.sortable('refresh');
					}
					return;
				}

				dp.ajaxPost('dukkan_dp_duplicate', { rule_id: ruleId }, function (data) {
					var $clone = $rule.clone();
					$clone.attr('data-rule-id', data.id);
					$clone.find('[data-method]').val(data.method || 'simple_adjustment');
					$clone.find('[data-apply-with]').val(data.apply_with || 'apply_with_others');
					$clone.find('[data-adjustment-type]').val(data.adjustment_type || 'fixed_discount');
					$clone.find('[data-adjustment-amount]').val(data.adjustment_amount || '0.00');
					$clone.find('[data-note]').val(data.note || '');
					$clone.find('[data-description]').val(data.description || '');
					$clone.hide().insertAfter($rule).slideDown(200);
					dp.updateMethodLabel($clone);
					showToast(dpI18n.duplicated || 'Pricing rule duplicated.', 'success');
					if (dp.$list.hasClass('ui-sortable')) {
						dp.$list.sortable('refresh');
					}
				});
			},

			/**
			 * Initialize jQuery UI Sortable for drag-and-drop reordering.
			 */
			initSortable: function () {
				if (dp.$list.hasClass('ui-sortable')) {
					dp.$list.sortable('destroy');
				}
				dp.$list.sortable({
					handle: '.dukkan-dp__rule-drag',
					axis: 'y',
					placeholder: 'dukkan-dp__rule ui-sortable-placeholder',
					forcePlaceholderSize: true,
					opacity: 0.85,
					tolerance: 'pointer',
					update: function () {
						// Collect all rules in current order and save.
						var allRules = [];
						dp.$list.find('.dukkan-dp__rule').each(function () {
							allRules.push(dp.collectRuleData($(this)));
						});
						dp.ajaxPost('dukkan_dp_save_all', { rules: allRules }, function (data) {
							showToast(dpI18n.order_saved || 'Order saved.', 'success');
						});
					}
				});
			}
		};

		// ---- Add Rule button ----
		$('#dukkan-dp-add-rule').on('click', function () {
			dp.addRule();
		});

		// ---- Global setting select change ----
		$('.dukkan-dp__global-select[data-global-setting]').on('change', function () {
			var $select = $(this);
			var key = $select.data('global-setting');
			var value = $select.val();

			dp.ajaxPost('dukkan_dp_save_global', {
				setting_key: key,
				setting_value: value
			}, function () {
				showToast(dpI18n.setting_saved || 'Setting saved.', 'success');
			});

			// Toggle discount-limit value input visibility.
			if (key === 'discount_limit') {
				var $input = $('#dukkan-dp-global-limit-value');
				$input.toggleClass('dukkan-dp__global-limit-input--hidden', value === 'none');
				if (value !== 'none') {
					$input.focus();
				}
			}
		});

		// ---- Discount limit value input — debounced save ----
		var dpLimitValueTimer;
		$('#dukkan-dp-global-limit-value').on('input', function () {
			var $input = $(this);
			clearTimeout(dpLimitValueTimer);
			dpLimitValueTimer = setTimeout(function () {
				var val = parseFloat($input.val());
				if (isNaN(val)) val = 0;
				dp.ajaxPost('dukkan_dp_save_global', {
					setting_key: 'discount_limit_value',
					setting_value: val
				});
			}, 500);
		});

		// ---- Remove rule (delegated) ----
		dp.$list.on('click', '[data-remove]', function () {
			var $rule = $(this).closest('.dukkan-dp__rule');
			dp.removeRule($rule);
		});

		// ---- Duplicate rule (delegated) ----
		dp.$list.on('click', '[data-duplicate]', function () {
			var $rule = $(this).closest('.dukkan-dp__rule');
			dp.duplicateRule($rule);
		});

		// ---- Method select change (delegated) ----
		dp.$list.on('change', '[data-method]', function () {
			var $rule = $(this).closest('.dukkan-dp__rule');
			dp.updateMethodLabel($rule);
			var ruleId = dp.ruleId($rule);
			if (ruleId && ruleId.indexOf('temp_') !== 0) {
				dp.debouncedSave(ruleId, dp.collectRuleData($rule));
			}
		});

		// ---- Apply-with select change (delegated) — save immediately ----
		dp.$list.on('change', '[data-apply-with]', function () {
			var $rule = $(this).closest('.dukkan-dp__rule');
			var ruleId = dp.ruleId($rule);
			if (ruleId && ruleId.indexOf('temp_') !== 0) {
				var ruleData = dp.collectRuleData($rule);
				dp.ajaxPost('dukkan_dp_update', {
					rule_id: ruleId,
					rule: ruleData
				});
			}
		});

		// ---- Any other field change triggers debounced save for persisted rules ----
		dp.$list.on('change input', '[data-adjustment-type], [data-adjustment-amount], [data-note], [data-description]', function () {
			var $rule = $(this).closest('.dukkan-dp__rule');
			var ruleId = dp.ruleId($rule);
			if (ruleId && ruleId.indexOf('temp_') !== 0) {
				dp.debouncedSave(ruleId, dp.collectRuleData($rule));
			}
		});

		// ---- Add Product button (placeholder) ----
		dp.$list.on('click', '[data-add-product]', function () {
			var $rule = $(this).closest('.dukkan-dp__rule');
			var $productsBody = $rule.find('.dukkan-dp__products-body');
			showToast(dpI18n.product_placeholder || 'Product selector coming soon.', 'warning');
		});

		// ---- Remove product tag (delegated) ----
		dp.$list.on('click', '[data-remove-product]', function () {
			var $tag = $(this).closest('.dukkan-dp__product-tag');
			var $rule = $(this).closest('.dukkan-dp__rule');
			$tag.remove();
			// Show empty state if no more product tags.
			var $boxBody = $rule.find('.dukkan-dp__products-body');
			var $list = $boxBody.find('[data-products-list]');
			if ($list.length && !$list.children().length) {
				$list.remove();
				$boxBody.find('[data-add-product]').remove();
				var $empty = $(
					'<div class="dukkan-dp__box-empty" data-products-empty>' +
						'<span class="dukkan-dp__box-empty-text">' + (dpI18n.applies_all || 'Applies to all products.') + '</span>' +
						'<button type="button" class="dukkan-dp__box-action-btn" data-add-product>' + (dpI18n.add_product || 'Add Product') + '</button>' +
					'</div>'
				);
				$boxBody.empty().append($empty);
			}
			// Debounce save.
			var ruleId = dp.ruleId($rule);
			if (ruleId && ruleId.indexOf('temp_') !== 0) {
				dp.debouncedSave(ruleId, dp.collectRuleData($rule));
			}
		});

		// ---- Add Condition button (placeholder) ----
		dp.$list.on('click', '[data-add-condition]', function () {
			var $rule = $(this).closest('.dukkan-dp__rule');
			showToast(dpI18n.condition_placeholder || 'Condition builder coming soon.', 'warning');
		});

		// ---- Remove condition tag (delegated) ----
		dp.$list.on('click', '[data-remove-condition]', function () {
			var $tag = $(this).closest('.dukkan-dp__condition-tag');
			var $rule = $(this).closest('.dukkan-dp__rule');
			$tag.remove();
			// Show empty state if no more condition tags.
			var $boxBody = $rule.find('.dukkan-dp__conditions-body');
			var $list = $boxBody.find('[data-conditions-list]');
			if ($list.length && !$list.children().length) {
				$list.remove();
				$boxBody.find('[data-add-condition]').remove();
				var $empty = $(
					'<div class="dukkan-dp__box-empty" data-conditions-empty>' +
						'<span class="dukkan-dp__box-empty-text">' + (dpI18n.applies_all_cases || 'Applies in all cases.') + '</span>' +
						'<button type="button" class="dukkan-dp__box-action-btn" data-add-condition>' + (dpI18n.add_condition || 'Add Condition') + '</button>' +
					'</div>'
				);
				$boxBody.empty().append($empty);
			}
			// Debounce save.
			var ruleId = dp.ruleId($rule);
			if (ruleId && ruleId.indexOf('temp_') !== 0) {
				dp.debouncedSave(ruleId, dp.collectRuleData($rule));
			}
		});

		// ---- Collapse toggle (delegated) ----
		dp.$list.on('click', '[data-toggle-collapse]', function (e) {
			// Don't toggle when clicking on selects, inputs, or action buttons
			// — except the collapse toggle button itself.
			var $target = $(e.target);
			if ($target.is('select, input') ||
			    $target.closest('select, [data-duplicate], [data-remove]').length) {
				return;
			}
			// Allow clicks on the toggle button or its icon to pass.
			if ($target.is('button') && !$target.closest('.dukkan-dp__rule-toggle-btn').length) {
				return;
			}

			var $rule = $(this).closest('.dukkan-dp__rule');
			var $body = $rule.find('.dukkan-dp__rule-body');
			var $icon = $rule.find('.dukkan-dp__rule-toggle-icon');
			$body.slideToggle(200, function () {
				$body.toggleClass('dukkan-dp__rule-body--collapsed');
			});
			$icon.toggleClass('dukkan-dp__rule-toggle-icon--open');
		});

		// ---- Save All Rules button ----
		$('#dukkan-dp-save-all').on('click', function () {
			var $btn = $(this);
			var allRules = [];
			dp.$list.find('.dukkan-dp__rule').each(function () {
				allRules.push(dp.collectRuleData($(this)));
			});

			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> ' + (dpI18n.saving || 'Saving…'));

			dp.ajaxPost('dukkan_dp_save_all', { rules: allRules }, function (data) {
				showToast(dpI18n.saved_all || 'All pricing rules saved.', 'success');
			}, function () {
				// Error handled by ajaxPost.
			});

			setTimeout(function () {
				$btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> ' + (dpI18n.save_all || 'Save All Rules'));
			}, 2000);
		});

		// ---- Initialize sortable if rules exist ----
		if (dp.$list.children().length) {
			dp.initSortable();
		}

	});

})( jQuery );
