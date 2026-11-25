/* Dukkan — "Generate with AI" on the product editor. @since 1.0.74 */
(function ($) {
	'use strict';

	var cfg = window.dpProductAI;
	if (!cfg) {
		return;
	}
	var t = cfg.i18n;

	/** Featured image id, else the first gallery image id, else 0. */
	function imageId() {
		var thumb = parseInt($('#_thumbnail_id').val(), 10);
		if (thumb > 0) {
			return thumb;
		}
		var gallery = String($('#product_image_gallery').val() || '').split(',');
		for (var i = 0; i < gallery.length; i++) {
			var id = parseInt(gallery[i], 10);
			if (id > 0) {
				return id;
			}
		}
		return 0;
	}

	function escapeHtml(s) {
		return String(s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	/** Plain text → paragraphs for the editor. */
	function toHtml(text) {
		return String(text)
			.split(/\n\s*\n/)
			.map(function (p) {
				return '<p>' + escapeHtml(p.trim()).replace(/\n/g, '<br>') + '</p>';
			})
			.join('\n');
	}

	function setEditor(id, text) {
		var html = toHtml(text);
		var ed = window.tinymce && window.tinymce.get(id);
		if (ed && !ed.isHidden()) {
			ed.setContent(html);
			ed.save();
			ed.fire('change');
		} else {
			$('#' + id).val(html).trigger('change');
		}
	}

	var apply = {
		title: function (text) {
			$('#title').val(text).trigger('input').trigger('change');
			$('#title-prompt-text').addClass('screen-reader-text');
		},
		description: function (text) {
			setEditor('content', text);
		},
		short: function (text) {
			setEditor('excerpt', text);
		}
	};

	function buildPanel(field) {
		var $panel = $(
			'<div class="dp-ai" data-field="' + field + '">' +
				'<button type="button" class="button dp-ai-btn" disabled>' +
					'<span class="dashicons dashicons-superhero-alt"></span> ' + escapeHtml(t.button) +
				'</button>' +
				'<span class="dp-ai-hint"></span>' +
				'<div class="dp-ai-results" hidden></div>' +
			'</div>'
		);
		$panel.find('.dp-ai-btn').on('click', function () {
			run(field, $panel);
		});
		return $panel;
	}

	function run(field, $panel) {
		var id = imageId();
		if (!id) {
			return;
		}
		var $btn = $panel.find('.dp-ai-btn');
		var $hint = $panel.find('.dp-ai-hint');
		var $res = $panel.find('.dp-ai-results');

		$panel.addClass('is-busy');
		$btn.prop('disabled', true);
		$hint.html('<span class="spinner is-active"></span>' + escapeHtml(t.working));
		$res.attr('hidden', true).empty();

		$.post(cfg.ajaxUrl, { action: cfg.action, nonce: cfg.nonce, field: field, image: id })
			.done(function (r) {
				if (!r || !r.success) {
					$hint.text((r && r.data && r.data.message) || t.failed).addClass('is-error');
					return;
				}
				var list = r.data.suggestions || [];
				if (!list.length) {
					$hint.text(t.noResults).addClass('is-error');
					return;
				}
				$hint.text(t.pick).removeClass('is-error');
				list.forEach(function (text) {
					var $item = $('<button type="button" class="dp-ai-item"></button>').text(text);
					$item.on('click', function () {
						apply[field](text);
						$res.find('.dp-ai-item').removeClass('is-used');
						$item.addClass('is-used');
					});
					$res.append($item);
				});
				$res.append(
					$('<button type="button" class="button-link dp-ai-close"></button>')
						.text(t.close)
						.on('click', function () {
							$res.attr('hidden', true).empty();
							$hint.text('');
						})
				);
				$res.removeAttr('hidden');
			})
			.fail(function (xhr) {
				var msg = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
				$hint.text(msg || t.failed).addClass('is-error');
			})
			.always(function () {
				$panel.removeClass('is-busy');
				refresh();
			});
	}

	/* ---------- Product photos ("Let AI do it") ---------- */

	var ratio = '3:4';

	function ratioShape(r) {
		var p = r.split(':');
		var w = 18, h = 18;
		if (+p[0] < +p[1]) {
			w = Math.round(18 * p[0] / p[1]);
		}
		return '<span class="dp-ai-shape" style="width:' + w + 'px;height:' + h + 'px"></span>';
	}

	function buildPhotoPanel() {
		var ratios = (cfg.ratios || []).map(function (r) {
			return '<button type="button" class="dp-ai-ratio' + (r === ratio ? ' is-on' : '') + '" data-ratio="' + r + '">' +
				ratioShape(r) + '<span>' + r + '</span></button>';
		}).join('');
		var $panel = $(
			'<div class="dp-ai dp-ai-photo" data-field="photo" data-ready="' + (cfg.photoReady ? 1 : 0) + '">' +
				'<button type="button" class="button dp-ai-btn" disabled>' +
					'<span class="dashicons dashicons-superhero-alt"></span> ' + escapeHtml(t.button) +
				'</button>' +
				'<span class="dp-ai-hint"></span>' +
				'<div class="dp-ai-options" hidden>' +
					'<p class="dp-ai-label">' + escapeHtml(t.ratio) + '</p>' +
					'<div class="dp-ai-ratios">' + ratios + '</div>' +
					'<p class="dp-ai-label">' + escapeHtml(t.howMany) + '</p>' +
					'<div class="dp-ai-counts">' +
						'<button type="button" class="dp-ai-count" data-count="1"><strong>1</strong>' + escapeHtml(t.onePhoto) + '</button>' +
						'<button type="button" class="dp-ai-count" data-count="2"><strong>2</strong>' + escapeHtml(t.twoPhotos) + '</button>' +
					'</div>' +
					'<p class="dp-ai-note">' + escapeHtml(t.aiDecides) + '</p>' +
				'</div>' +
				'<div class="dp-ai-photos" hidden></div>' +
			'</div>'
		);
		$panel.find('.dp-ai-btn').on('click', function () {
			var $o = $panel.find('.dp-ai-options');
			$o.prop('hidden', !$o.prop('hidden'));
		});
		$panel.on('click', '.dp-ai-ratio', function () {
			ratio = $(this).data('ratio');
			$panel.find('.dp-ai-ratio').removeClass('is-on');
			$(this).addClass('is-on');
		});
		$panel.on('click', '.dp-ai-count', function () {
			makePhotos($panel, parseInt($(this).data('count'), 10) || 1);
		});
		return $panel;
	}

	/** Seller hints, like the app: the name typed so far and ticked categories. */
	function hints() {
		var cats = [];
		$('#product_catchecklist input:checked').each(function () {
			cats.push($.trim($(this).parent().text()));
		});
		return { name: $.trim($('#title').val() || ''), categories: cats };
	}

	function addToGallery(photo) {
		var $input = $('#product_image_gallery');
		var ids = String($input.val() || '').split(',').filter(Boolean);
		ids.push(String(photo.id));
		$input.val(ids.join(',')).trigger('change');
		$('#product_images_container ul.product_images').append(
			'<li class="image" data-attachment_id="' + photo.id + '">' +
				'<img src="' + escapeHtml(photo.thumb) + '" alt="">' +
				'<ul class="actions"><li><a href="#" class="delete tips" data-tip="' + escapeHtml(t.deleteImg) + '">' + escapeHtml(t.delete) + '</a></li></ul>' +
			'</li>'
		);
	}

	function makePhotos($panel, count) {
		var id = imageId();
		if (!id || $panel.hasClass('is-busy')) {
			return;
		}
		var $btn = $panel.find('.dp-ai-btn');
		var $hint = $panel.find('.dp-ai-hint');
		var $grid = $panel.find('.dp-ai-photos');
		var h = hints();
		var left = count, made = 0, firstError = '';

		$panel.addClass('is-busy');
		$panel.find('.dp-ai-options').prop('hidden', true);
		$btn.prop('disabled', true);
		$hint.html('<span class="spinner is-active"></span>' + escapeHtml(t.making)).removeClass('is-error');
		$grid.prop('hidden', false);

		for (var i = 0; i < count; i++) {
			(function () {
				var $tile = $('<div class="dp-ai-tile is-loading" data-ratio="' + ratio + '"><span class="spinner is-active"></span></div>');
				$tile.css('aspect-ratio', ratio.replace(':', ' / '));
				$grid.append($tile);
				$.ajax({
					url: cfg.ajaxUrl,
					method: 'POST',
					timeout: 240000,
					data: {
						action: cfg.photoAction,
						nonce: cfg.nonce,
						image: id,
						post_id: cfg.postId,
						ratio: ratio,
						name: h.name,
						categories: h.categories
					}
				}).done(function (r) {
					if (r && r.success) {
						made++;
						$tile.removeClass('is-loading').empty().append(
							$('<a target="_blank" rel="noopener"></a>').attr('href', r.data.full).append($('<img alt="">').attr('src', r.data.preview || r.data.thumb))
						);
						addToGallery(r.data);
					} else {
						firstError = firstError || (r && r.data && r.data.message) || t.failed;
						$tile.remove();
					}
				}).fail(function (xhr) {
					firstError = firstError || (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || t.failed;
					$tile.remove();
				}).always(function () {
					left--;
					if (left > 0) {
						return;
					}
					$panel.removeClass('is-busy');
					if (!made) {
						$hint.text(firstError || t.failed).addClass('is-error');
					} else if (made < count) {
						$hint.text(t.added + ' ' + t.someFailed).addClass('is-error');
					} else {
						$hint.text(t.added).removeClass('is-error');
					}
					refresh();
				});
			})();
		}
	}

	/** Enable the buttons only while the product has an image. */
	function refresh() {
		var has = imageId() > 0;
		$('.dp-ai').each(function () {
			var $p = $(this);
			if ($p.hasClass('is-busy')) {
				return;
			}
			var $hint = $p.find('.dp-ai-hint');
			if (String($p.data('ready')) === '0') {
				$p.find('.dp-ai-btn').prop('disabled', true).attr('title', t.notSetUp);
				$hint.text(t.notSetUp);
				return;
			}
			$p.find('.dp-ai-btn')
				.prop('disabled', !has)
				.attr('title', has ? '' : t.needImage);
			if (!has) {
				$p.find('.dp-ai-options').prop('hidden', true);
			}
			if (!has && !$p.find('.dp-ai-results, .dp-ai-photos').children().length) {
				$hint.text(t.needImage).removeClass('is-error');
			} else if (has && $hint.text() === t.needImage) {
				$hint.text('');
			}
		});
	}

	$(function () {
		if ($('#titlewrap').length) {
			$('#titlewrap').after(buildPanel('title'));
		}
		if ($('#postdivrich').length) {
			$('#postdivrich').after(buildPanel('description'));
		}
		if ($('#postexcerpt .inside').length) {
			$('#postexcerpt .inside').append(buildPanel('short'));
		}
		// In the "Product gallery" box, under its "Add product gallery images"
		// link (WooCommerce only changes the image list in that box, not the box).
		var $gallery = $('#woocommerce-product-images .inside');
		if (cfg.photoAction && $gallery.length) {
			$gallery.append(buildPhotoPanel());
		}
		refresh();
		// The image meta boxes replace their markup when an image is set or
		// removed, so check the hidden ids regularly instead of binding events.
		setInterval(refresh, 800);
	});
})(jQuery);
