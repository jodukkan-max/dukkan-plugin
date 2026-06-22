(function ($) {
    'use strict';

    if (typeof WPLDP_GROUPS === 'undefined') return;

    // Build field-key → definition map
    var fieldMap = {};
    WPLDP_GROUPS.forEach(function (group) {
        if (!group.fields) return;
        Object.keys(group.fields).forEach(function (fk) {
            fieldMap[fk] = group.fields[fk];
        });
    });
    

    // Format price number as string
    function formatPrice(amount) {
        var decimals  = parseInt(WPLDP.decimals, 10);
        var formatted = parseFloat(amount).toFixed(decimals);

        // Apply thousand separator
        if (WPLDP.thousand_sep) {
            var parts = formatted.split('.');
            parts[0]  = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, WPLDP.thousand_sep);
            formatted = parts.join(WPLDP.decimal_sep);
        } else {
            formatted = formatted.replace('.', WPLDP.decimal_sep);
        }

        // Apply currency symbol based on WooCommerce currency position setting
        var symbol = WPLDP.currency_symbol;
        switch (WPLDP.currency_pos) {
            case 'left':
                formatted = symbol + formatted;
                break;
            case 'right':
                formatted = formatted + symbol;
                break;
            case 'left_space':
                formatted = symbol + ' ' + formatted;
                break;
            case 'right_space':
                formatted = formatted + ' ' + symbol;
                break;
            default:
                formatted = symbol + formatted;
        }

        return formatted;
    }

    // Calculate total addon price from all selected fields
    function calculateTotal() {
        var total = 0;

        Object.keys(fieldMap).forEach(function (fk) {
            var field  = fieldMap[fk];
            var type   = field.type;
            var $field = $('#wpa-addons-wrapper').find('[data-field-key="' + fk + '"]');
            if (!$field.length) return;

            switch (type) {
                case 'text':
                case 'textarea':
                case 'number':
                case 'date':
                case 'file':
                    var $inp = $field.find('input, textarea');
                    if ($inp.val() && $inp.val().trim() !== '') {
                        total += parseFloat(field.price || 0);
                    }
                    break;

                case 'select':
                    var $opted = $field.find('option:selected');
                    total += parseFloat($opted.data('price') || 0);
                    break;

                case 'radio':
                case 'image':
                case 'color':
                    var $checked = $field.find('input[type="radio"]:checked');
                    if ($checked.length) {
                        total += parseFloat($checked.data('price') || 0);
                    }
                    break;

                case 'checkbox':
                    $field.find('input[type="checkbox"]:checked').each(function () {
                        total += parseFloat($(this).data('price') || 0);
                    });
                    break;
            }
        });

        return total;
    }

    // Update the price summary bar
    // function updateDisplay() {
    //     var total    = calculateTotal();
    //     var $summary = $('#wpa-price-summary');

    //     // formatPrice now returns full string e.g. "₹1,500.00"
    //     $summary.find('.wpa-price-value').text('+' + formatPrice(total));

    //     if (total > 0) {
    //         $summary.show();
    //     } else {
    //         $summary.hide();
    //     }
    // }

    // Tracks current base price — updated by variation events or set from data attribute
    var currentBasePrice = 0;

    function updateDisplay() {
        var addonTotal = calculateTotal();
        var $summary   = $('#wpa-price-summary');

        if (addonTotal <= 0) {
            $summary.hide();
            return;
        }

        var grandTotal = currentBasePrice + addonTotal;

        $summary.find('#wpa-addons-total').text('+' + formatPrice(addonTotal));
        $summary.find('#wpa-grand-total').text(formatPrice(grandTotal));

        $summary.show();
    }

    $(document).ready(function () {
        var $wrapper = $('#wpa-addons-wrapper');
        if (!$wrapper.length) return;

        var productType = $wrapper.data('product-type');
        // For simple products, read base price from data attribute directly
        if (productType === 'simple') {
            currentBasePrice = parseFloat($wrapper.data('base-price')) || 0;
        }

        $('#wpa-price-summary .wpa-symbol').text(WPLDP.currency_symbol);

        // Recalculate on any field change
        $wrapper.on('change input', '.wpa-price-trigger', function () {
            updateDisplay();
        });

        $wrapper.on('click', '.wpa-image-label, .wpa-color-label', function () {
            updateDisplay();
        });

        // ── Variable product: read price from variation data ──────
        // WooCommerce fires this with the full variation object which has
        // display_price = active price (sale or regular), already calculated
        $(document).on('found_variation', 'form.variations_form', function (e, variation) {
            // display_price is the final price WooCommerce shows (respects sale, tax settings)
            currentBasePrice = parseFloat(variation.display_price) || 0;
            updateDisplay();
        });

        // Reset when variation is unselected
        $(document).on('reset_data', 'form.variations_form', function () {
            currentBasePrice = 0;
            updateDisplay();
        });


        // ── File upload via AJAX ──────────────────────────────────────

        // Icon map for non-image file types
        var FILE_ICONS = {
            'application/pdf'    : '📄',
            'text/plain'         : '📝',
            'application/msword' : '📝',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : '📝',
            'application/vnd.ms-excel' : '📊',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'       : '📊',
            'application/zip'    : '🗜️',
        };

        var IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        $wrapper.on('change', '.wpa-file', function () {
            var $input     = $(this);
            var file       = this.files[0];
            var fieldKey   = $input.data('field-key');
            var $field     = $input.closest('.wpa-field');
            var $status    = $field.find('.wpa-file-status');
            var $preview   = $field.find('.wpa-file-preview');
            var $urlInput  = $field.find('.wpa-file-url-input');

            if (!file) return;

            // Reset state
            $urlInput.val('');
            $preview.hide().empty();
            $status.text('Uploading...').css('color', '#6b7280');

            var formData = new FormData();
            formData.append('action',    'wpldp_upload_file');
            formData.append('nonce',     WPLDP.nonce);
            formData.append('field_key', fieldKey);
            formData.append('file',      file);

            $.ajax({
                url         : WPLDP.ajax_url,
                type        : 'POST',
                data        : formData,
                processData : false,
                contentType : false,
                success: function (response) {
                    if (!response.success) {
                        $status.text('Error: ' + response.data.message).css('color', '#e94560');
                        return;
                    }

                    var url      = response.data.url;
                    var mime     = response.data.mime_type;
                    var filename = response.data.filename;

                    // Store URL in hidden field
                    $urlInput.val(url);

                    // Trigger price recalculation
                    $input.trigger('change.wpa');
                    updateDisplay();

                    // Show status
                    $status.text('✓ ' + filename).css('color', '#10b981');

                    // Show preview
                    $preview.empty().show();

                    if (IMAGE_TYPES.indexOf(mime) !== -1) {
                        // Image preview
                        $preview.html(
                            '<div class="wpa-preview-image-wrap">' +
                                '<img src="' + url + '" alt="' + filename + '" class="wpa-preview-img" />' +
                                '<button type="button" class="wpa-preview-remove" data-field-key="' + fieldKey + '">✕ Remove</button>' +
                            '</div>'
                        );
                    } else {
                        // Icon preview for other file types
                        var icon = FILE_ICONS[mime] || '📎';
                        $preview.html(
                            '<div class="wpa-preview-file-wrap">' +
                                '<span class="wpa-preview-icon">' + icon + '</span>' +
                                '<span class="wpa-preview-filename">' + filename + '</span>' +
                                '<a href="' + url + '" target="_blank" class="wpa-preview-open">Open</a>' +
                                '<button type="button" class="wpa-preview-remove" data-field-key="' + fieldKey + '">✕ Remove</button>' +
                            '</div>'
                        );
                    }
                },
                error: function () {
                    $status.text('Upload failed. Please try again.').css('color', '#e94560');
                }
            });
        });

        // ── Remove uploaded file ──────────────────────────────────────

        $wrapper.on('click', '.wpa-preview-remove', function () {
            var $btn      = $(this);
            var $field    = $btn.closest('.wpa-field');
            var $preview  = $field.find('.wpa-file-preview');
            var $status   = $field.find('.wpa-file-status');
            var $urlInput = $field.find('.wpa-file-url-input');
            var $fileInp  = $field.find('.wpa-file');

            $urlInput.val('');
            $fileInp.val('');
            $preview.hide().empty();
            $status.text('');
            updateDisplay();
        });
    });

    // Client-side required field validation on Add To Cart
    $(document).on('submit', 'form.cart', function (e) {
        var valid = true;

        Object.keys(fieldMap).forEach(function (fk) {
            var field = fieldMap[fk];
            if (!field.required || field.required === '0') return;

            var $field = $('#wpa-addons-wrapper').find('[data-field-key="' + fk + '"]');
            var type   = field.type;
            $field.removeClass('wpa-has-error');

            switch (type) {
                case 'text':
                case 'textarea':
                case 'number':
                case 'date':
                    if (!$field.find('input, textarea').val().trim()) {
                        $field.addClass('wpa-has-error'); valid = false;
                    }
                    break;
                case 'select':
                    if (!$field.find('select').val()) {
                        $field.addClass('wpa-has-error'); valid = false;
                    }
                    break;
                case 'radio':
                case 'image':
                case 'color':
                    if (!$field.find('input[type="radio"]:checked').length) {
                        $field.addClass('wpa-has-error'); valid = false;
                    }
                    break;
                case 'checkbox':
                    if (!$field.find('input[type="checkbox"]:checked').length) {
                        $field.addClass('wpa-has-error'); valid = false;
                    }
                    break;
                case 'file':
                    if (!$field.find('input[type="file"]').val()) {
                        $field.addClass('wpa-has-error'); valid = false;
                    }
                    break;
            }
        });

        if (!valid) {
            var $first = $('#wpa-addons-wrapper .wpa-has-error').first();
            if ($first.length) {
                $('html, body').animate({ scrollTop: $first.offset().top - 100 }, 400);
            }
            e.preventDefault();
            return false;
        }
    });

}(jQuery));