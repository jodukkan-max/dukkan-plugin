/**
 * Dynamic Pricing & Discounts — Inline Rule Cards
 *
 * @package    Dukkan_Plugin
 * @subpackage Admin/JS
 * @author     Modwings
 * @since      1.0.1
 */

(function( $ ) {
	'use strict';

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
				products: dp.collectProductFilters($rule),
				conditions: dp.collectConditionData($rule)
			};
		},

		/**
		 * Collect product filter data from rows.
		 */
		collectProductFilters: function ($rule) {
			var filters = [];
			$rule.find('[data-product-filter]').each(function () {
				var $row = $(this);
				var type = $row.find('[data-filter-type]').val() || 'product';
				var value;
				if (type === 'product_is_on_sale') {
					value = $row.find('[data-filter-value-on-sale]').val() || '';
				} else if (type === 'product') {
					// Multi-select via Select2 — collect as array.
					var $select2 = $row.find('[data-filter-value-select2]');
					if ($select2.length && $select2.data('select2')) {
						value = $select2.val() || [];
					} else {
						value = $select2.val() || [];
					}
					// Ensure value is always an array of strings.
					if (!Array.isArray(value)) {
						value = value ? [value] : [];
					}
				} else {
					value = $row.find('[data-filter-value-input]').val() || '';
				}
				filters.push({
					type: type,
					operator: $row.find('[data-filter-operator]').val() || 'in_list',
					value: value
				});
			});
			return filters;
		},

		/**
		 * Generate a product filter row HTML template (for JS cloning).
		 */
		productFilterRowTemplate: function () {
			var typeOptions = '';
			var typeGroups = {
				'Product': [
					{ val: 'product', label: dpI18n.pf_product || 'Product' },
					{ val: 'product_variation', label: dpI18n.pf_product_variation || 'Product variation' },
					{ val: 'product_category', label: dpI18n.pf_product_category || 'Product category' },
					{ val: 'product_attributes', label: dpI18n.pf_product_attributes || 'Product attributes' },
					{ val: 'product_tags', label: dpI18n.pf_product_tags || 'Product tags' }
				],
				'Product Property': [
					{ val: 'product_regular_price', label: dpI18n.pf_product_regular_price || 'Product regular price' },
					{ val: 'product_is_on_sale', label: dpI18n.pf_product_is_on_sale || 'Product is on sale' },
					{ val: 'product_stock_quantity', label: dpI18n.pf_product_stock_quantity || 'Product stock quantity' },
					{ val: 'product_shipping_class', label: dpI18n.pf_product_shipping_class || 'Product shipping class' },
					{ val: 'product_metadata', label: dpI18n.pf_product_metadata || 'Product metadata' }
				],
				'Other': [
					{ val: 'cart_item_data', label: dpI18n.pf_cart_item_data || 'Cart item data' },
					{ val: 'coupons_applied', label: dpI18n.pf_coupons_applied || 'Coupons applied' }
				]
			};
			for (var group in typeGroups) {
				typeOptions += '<optgroup label="' + group + '">';
				typeGroups[group].forEach(function (o) {
					typeOptions += '<option value="' + o.val + '">' + o.label + '</option>';
				});
				typeOptions += '</optgroup>';
			}

			var operatorOptions = '';
			var operators = ['in_list','not_in_list','equals','not_equals','greater_than','less_than','greater_than_or_equal','less_than_or_equal','contains','does_not_contain'];
			var operatorLabels = {
				in_list: dpI18n.op_in_list || 'in list',
				not_in_list: dpI18n.op_not_in_list || 'not in list',
				equals: dpI18n.op_equals || 'equals',
				not_equals: dpI18n.op_not_equals || 'not equals',
				greater_than: dpI18n.op_greater_than || 'greater than',
				less_than: dpI18n.op_less_than || 'less than',
				greater_than_or_equal: dpI18n.op_greater_than_or_equal || 'greater than or equal',
				less_than_or_equal: dpI18n.op_less_than_or_equal || 'less than or equal',
				contains: dpI18n.op_contains || 'contains',
				does_not_contain: dpI18n.op_does_not_contain || 'does not contain'
			};
			operators.forEach(function (op) {
				operatorOptions += '<option value="' + op + '">' + (operatorLabels[op] || op) + '</option>';
			});

			return '<div class="dukkan-dp__product-filter-row" data-product-filter>' +
				'<div class="dukkan-dp__product-filter-drag"><i class="fa-solid fa-grip-vertical"></i></div>' +
				'<select class="dukkan-dp__product-filter-type" data-filter-type>' + typeOptions + '</select>' +
				'<select class="dukkan-dp__product-filter-operator" data-filter-operator>' + operatorOptions + '</select>' +
				'<div class="dukkan-dp__product-filter-value" data-filter-value-wrap>' +
					'<select class="dukkan-dp__product-filter-value-on-sale" data-filter-value-on-sale style="display:none;">' +
						'<option value="yes">' + (dpI18n.pf_yes || 'Yes') + '</option>' +
						'<option value="no">' + (dpI18n.pf_no || 'No') + '</option>' +
					'</select>' +
					'<select class="dukkan-dp__product-filter-value-select2" data-filter-value-select2 multiple ' +
						'style="display:none;width:100%;" ' +
						'data-placeholder="' + (dpI18n.pf_search_placeholder || 'Search products…') + '">' +
					'</select>' +
					'<input type="text" class="dukkan-dp__product-filter-value-input" data-filter-value-input placeholder="' + (dpI18n.pf_search_placeholder || 'Search or enter value…') + '">' +
				'</div>' +
				'<button type="button" class="dukkan-dp__product-filter-remove" data-remove-product-filter title="' + (dpI18n.pf_remove || 'Remove filter') + '">' +
					'<i class="fa-solid fa-xmark"></i>' +
				'</button>' +
			'</div>';
		},

		/**
		 * Operator labels shared between template generation and rebuild logic.
		 */
		operatorLabels: {
			in_list: dpI18n.op_in_list || 'in list',
			not_in_list: dpI18n.op_not_in_list || 'not in list',
			equals: dpI18n.op_equals || 'equals',
			not_equals: dpI18n.op_not_equals || 'not equals',
			greater_than: dpI18n.op_greater_than || 'greater than',
			less_than: dpI18n.op_less_than || 'less than',
			greater_than_or_equal: dpI18n.op_greater_than_or_equal || 'greater than or equal',
			less_than_or_equal: dpI18n.op_less_than_or_equal || 'less than or equal',
			contains: dpI18n.op_contains || 'contains',
			does_not_contain: dpI18n.op_does_not_contain || 'does not contain'
		},

		/**
		 * Per-type operator sets and value field configuration.
		 * Add new types here as they are implemented.
		 */
		filterTypeConfig: {
			'product': {
				operators: ['in_list', 'not_in_list'],
				defaultOperator: 'in_list',
				valueType: 'select2'
			},
			'default': {
				operators: ['in_list','not_in_list','equals','not_equals','greater_than','less_than','greater_than_or_equal','less_than_or_equal','contains','does_not_contain'],
				defaultOperator: 'in_list',
				valueType: 'text'
			}
		},

		/**
		 * Rebuild operator dropdown options and value field type based on the
		 * selected product filter type. This is the central routing function —
		 * every Product Type will later define its own operator set and value
		 * field type through the filterTypeConfig map.
		 *
		 * @param {jQuery} $row The product filter row element.
		 */
		rebuildFilterControls: function ($row) {
			var type = $row.find('[data-filter-type]').val() || 'product';
			var config = dp.filterTypeConfig[type] || dp.filterTypeConfig['default'];

			// 1. Rebuild operator dropdown.
			var $operator = $row.find('[data-filter-operator]');
			var currentOp = $operator.val();
			var operatorOptions = '';
			config.operators.forEach(function (op) {
				var label = dp.operatorLabels[op] || op;
				var selected = (currentOp === op) ? ' selected' : '';
				operatorOptions += '<option value="' + op + '"' + selected + '>' + label + '</option>';
			});
			$operator.html(operatorOptions);

			// Reset operator to default if the previously selected one is no
			// longer in the allowed set for the new type.
			if (currentOp && config.operators.indexOf(currentOp) === -1) {
				$operator.val(config.defaultOperator);
			}

			// 2. Rebuild value field.
			var $valueWrap = $row.find('[data-filter-value-wrap]');
			var $input    = $row.find('[data-filter-value-input]');
			var $onSale   = $row.find('[data-filter-value-on-sale]');
			var $select2  = $valueWrap.find('[data-filter-value-select2]');

			// Destroy existing Select2 instance before hiding.
			if ($select2.length && $select2.data('select2')) {
				$select2.select2('destroy');
			}

			// Hide everything first.
			$input.hide();
			$onSale.hide();
			$select2.hide();

			// Show the correct value widget.
			if (type === 'product_is_on_sale') {
				$onSale.show();
			} else if (config.valueType === 'select2') {
				$select2.show();
				dp.initProductSelect2($select2);
			} else {
				$input.show();
			}
		},

		/**
		 * Initialize SelectWoo (AJAX multi-select) on a <select multiple>
		 * for product search.
		 *
		 * @param {jQuery} $el The <select> element to enhance.
		 */
		initProductSelect2: function ($el) {
			// Don't re-initialise if already a Select2.
			if ($el.data('select2')) {
				return;
			}

			$el.selectWoo({
				ajax: {
					url:      wpldp_ajax.url,
					dataType: 'json',
					delay:    250,
					data: function (params) {
						return {
							action: 'dukkan_dp_product_search',
							nonce:  wpldp_ajax.nonce,
							term:   params.term
						};
					},
					processResults: function (data) {
						return { results: data.data || [] };
					},
					cache: true
				},
				minimumInputLength: 2,
				allowClear:         true,
				placeholder:        $el.data('placeholder') || (dpI18n.pf_search_placeholder || 'Search products…'),
				width:              '100%',
				dropdownParent:     $el.closest('.dukkan-dp__rule')
			}).on('select2:select select2:unselect', function () {
				var $rule = $(this).closest('.dukkan-dp__rule');
				dp.saveRuleProductFilters($rule);
			});
		},

		/**
		 * Backward-compatible wrapper — now delegates to rebuildFilterControls.
		 *
		 * @deprecated Use rebuildFilterControls directly.
		 * @param {jQuery} $row The product filter row element.
		 */
		updateProductFilterValueFields: function ($row) {
			dp.rebuildFilterControls($row);
		},

		/**
		 * Initialize sortable for product filter rows within a list.
		 */
		initProductFiltersSortable: function ($list) {
			if ($list.hasClass('ui-sortable')) {
				$list.sortable('destroy');
			}
			$list.sortable({
				handle: '.dukkan-dp__product-filter-drag',
				axis: 'y',
				placeholder: 'dukkan-dp__product-filter-row dukkan-dp__product-filter-row--placeholder',
				forcePlaceholderSize: true,
				opacity: 0.85,
				tolerance: 'pointer',
				update: function () {
					var $rule = $list.closest('.dukkan-dp__rule');
					dp.saveRuleProductFilters($rule);
				}
			});
		},

		/**
		 * Save product filter changes for a rule.
		 */
		saveRuleProductFilters: function ($rule) {
			var ruleId = dp.ruleId($rule);
			if (ruleId && ruleId.indexOf('temp_') !== 0) {
				dp.debouncedSave(ruleId, dp.collectRuleData($rule));
			}
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

	// ---- Add Product button (delegated) ----
	dp.$list.on('click', '[data-add-product]', function () {
		var $rule = $(this).closest('.dukkan-dp__rule');
		var $productsBody = $rule.find('.dukkan-dp__products-body');
		var $empty = $productsBody.find('[data-products-empty]');
		var $list = $productsBody.find('[data-products-list]');
		var $addBtn = $(this);

		// If button is inside empty state, move it out.
		if ($addBtn.parent().is('[data-products-empty]')) {
			$empty.hide();
			$addBtn.appendTo($productsBody).show();
		}

		// Create list if first filter.
		if (!$list.length) {
			$list = $('<div class="dukkan-dp__products-list" data-products-list></div>');
			$addBtn.before($list);
			dp.initProductFiltersSortable($list);
		}

		// Append a new filter row from template.
			var $row = $(dp.productFilterRowTemplate());
			$list.append($row);

			// Rebuild controls for the default type ('product') — this
			// trims operators and initialises Select2.
			dp.rebuildFilterControls($row);

			// Re-init sortable on the list.
		if ($list.hasClass('ui-sortable')) {
			$list.sortable('refresh');
		} else {
			dp.initProductFiltersSortable($list);
		}

		// Save rule.
		dp.saveRuleProductFilters($rule);
	});

	// ---- Remove product filter row (delegated) ----
	dp.$list.on('click', '[data-remove-product-filter]', function () {
		var $row = $(this).closest('[data-product-filter]');
		var $rule = $(this).closest('.dukkan-dp__rule');
		var $productsBody = $rule.find('.dukkan-dp__products-body');
		var $list = $productsBody.find('[data-products-list]');
		$row.remove();

		// If no rows left, restore empty state.
		if (!$list.find('[data-product-filter]').length) {
			$list.remove();
			var $empty = $productsBody.find('[data-products-empty]');
			var $addBtn = $productsBody.find('[data-add-product]');
			$addBtn.prependTo($empty);
			$empty.show();
		}
		dp.saveRuleProductFilters($rule);
	});

	// ---- Product filter type change (delegated) ----
	dp.$list.on('change', '[data-filter-type]', function () {
		var $row = $(this).closest('[data-product-filter]');
		dp.rebuildFilterControls($row);
		var $rule = $(this).closest('.dukkan-dp__rule');
		dp.saveRuleProductFilters($rule);
	});

	// ---- Product filter operator / value change (delegated) ----
	dp.$list.on('change input', '[data-filter-operator], [data-filter-value-input], [data-filter-value-on-sale]', function () {
		var $rule = $(this).closest('.dukkan-dp__rule');
		dp.saveRuleProductFilters($rule);
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

	// ---- Initialize Select2 on existing server-rendered product filter rows ----
	dp.$list.find('[data-product-filter]').each(function () {
		var type = $(this).find('[data-filter-type]').val();
		if (type === 'product') {
			dp.rebuildFilterControls($(this));
		}
	});

})( jQuery );
