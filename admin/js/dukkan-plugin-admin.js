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

	});

})( jQuery );
