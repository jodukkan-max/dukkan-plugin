<?php
/**
 * Product Badges settings — two-column layout matching Product Add-Ons.
 *
 * Left sidebar lists badges; the right panel edits the selected badge.
 * Rendered inside the Dukkan settings tab for "Product Badges".
 */

$badges = isset( $badges ) ? $badges : array();
?>
<div class="wpldp-content wpldp-badges-content">

    <div class="wpldp-sidebar">
        <div class="dukkan-brand">
            <img
                src="<?php echo esc_url( DUKKAN_PLUGIN_URL . 'admin/images/dukkan-logo.png' ); ?>"
                alt="Dukkan"
                class="dukkan-brand-logo"
            >
        </div>

        <h3><?php esc_html_e( 'Badges', 'dukkan-plugin' ); ?></h3>

        <button type="button" class="wpldp-new-group wpldp-new-badge">
            <span class="dashicons dashicons-plus-alt2"></span>
            <?php esc_html_e( 'New Badge', 'dukkan-plugin' ); ?>
        </button>

        <div class="wpldp-group-list wpldp-badge-list">
            <?php if ( empty( $badges ) ) : ?>
                <p class="wpldp-empty"><?php esc_html_e( 'No badges created yet. Click "New Badge" to get started!', 'dukkan-plugin' ); ?></p>
            <?php else : ?>
                <?php foreach ( $badges as $badge_key => $badge ) : ?>
                    <div class="wpldp-group wpldp-badge-item" data-id="<?php echo esc_attr( $badge_key ); ?>">
                        <div class="wpldp-group-top">
                            <span>
                                <span class="wpldp-badge-swatch<?php echo ( $badge['shape'] ?? 'rectangular' ) === 'circle' ? ' wpldp-badge-swatch--circle' : ''; ?>" style="background:<?php echo esc_attr( $badge['background_color'] ?? '#e53935' ); ?>;color:<?php echo esc_attr( $badge['text_color'] ?? '#ffffff' ); ?>;"><?php echo esc_html( preg_replace( '/<br\s*\/?>/i', ' ', $badge['text'] ?? '' ) ); ?></span>
                            </span>
                            <div class="wpldp-group-top-controls">
                                <button type="button" class="wpldp-trash-btn wpldp-badge-delete" data-id="<?php echo esc_attr( $badge_key ); ?>" title="<?php esc_attr_e( 'Delete badge', 'dukkan-plugin' ); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                                <label class="wpldp-switch">
                                    <input type="checkbox" class="wpldp-toggle-badge-status" data-id="<?php echo esc_attr( $badge_key ); ?>" <?php checked( ! empty( $badge['status'] ), 1 ); ?>>
                                    <span class="wpldp-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="wpldp-main" id="wpldpBadgeEditorContainer">

        <div id="wpldp-badge-editor" class="wpldp-create-panel wpldp-badge-editor" style="display:none;">

            <header class="wpldp-badge-editor__header">
                <div class="wpldp-badge-editor__heading">
                    <h2 id="wpldp-badge-editor-title"><?php esc_html_e( 'New Badge', 'dukkan-plugin' ); ?></h2>
                    <p id="wpldp-badge-editor-subtitle"><?php esc_html_e( 'Create a badge and customize its appearance.', 'dukkan-plugin' ); ?></p>
                </div>
                <button type="button" class="wpldp-badge-editor__close wpldp-badge-cancel" title="<?php esc_attr_e( 'Close', 'dukkan-plugin' ); ?>">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </header>

            <form id="wpldp-badge-form" autocomplete="off">

                <div class="wpldp-badge-editor__layout">

                    <div class="wpldp-badge-editor__main">

                        <section class="wpldp-badge-section">
                            <h3 class="wpldp-badge-section__title"><?php esc_html_e( 'Appearance', 'dukkan-plugin' ); ?></h3>
                            <div class="wpldp-badge-section__body">

                                <div class="wpldp-field">
                                    <label for="wpldp-badge-text"><?php esc_html_e( 'Badge Text', 'dukkan-plugin' ); ?> <span class="wpldp-required-star">*</span></label>
                                    <input type="text" name="text" id="wpldp-badge-text" placeholder="<?php esc_attr_e( 'e.g., SALE', 'dukkan-plugin' ); ?>">
                                    <p class="wpldp-field-hint"><?php esc_html_e( 'Tip: use <br> to break text onto a new line.', 'dukkan-plugin' ); ?></p>
                                </div>

                                <div class="wpldp-field-row">
                                    <div class="wpldp-field">
                                        <label><?php esc_html_e( 'Shape', 'dukkan-plugin' ); ?></label>
                                        <div class="wpldp-seg" role="radiogroup" aria-label="<?php esc_attr_e( 'Badge Shape', 'dukkan-plugin' ); ?>">
                                            <label class="wpldp-seg__item">
                                                <input type="radio" name="shape" value="rectangular" checked>
                                                <span class="wpldp-seg__visual">
                                                    <span class="wpldp-seg__chip wpldp-seg__chip--rect"></span>
                                                    <span><?php esc_html_e( 'Rectangular', 'dukkan-plugin' ); ?></span>
                                                </span>
                                            </label>
                                            <label class="wpldp-seg__item">
                                                <input type="radio" name="shape" value="circle">
                                                <span class="wpldp-seg__visual">
                                                    <span class="wpldp-seg__chip wpldp-seg__chip--circle"></span>
                                                    <span><?php esc_html_e( 'Circle', 'dukkan-plugin' ); ?></span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="wpldp-field">
                                        <label><?php esc_html_e( 'Position', 'dukkan-plugin' ); ?></label>
                                        <div class="wpldp-pos" role="radiogroup" aria-label="<?php esc_attr_e( 'Badge Position', 'dukkan-plugin' ); ?>">
                                            <label class="wpldp-pos__cell wpldp-pos__cell--tl" title="<?php esc_attr_e( 'Top Left', 'dukkan-plugin' ); ?>">
                                                <input type="radio" name="position" value="top-left" checked>
                                                <span class="wpldp-pos__dot"></span>
                                            </label>
                                            <label class="wpldp-pos__cell wpldp-pos__cell--tr" title="<?php esc_attr_e( 'Top Right', 'dukkan-plugin' ); ?>">
                                                <input type="radio" name="position" value="top-right">
                                                <span class="wpldp-pos__dot"></span>
                                            </label>
                                            <label class="wpldp-pos__cell wpldp-pos__cell--bl" title="<?php esc_attr_e( 'Bottom Left', 'dukkan-plugin' ); ?>">
                                                <input type="radio" name="position" value="bottom-left">
                                                <span class="wpldp-pos__dot"></span>
                                            </label>
                                            <label class="wpldp-pos__cell wpldp-pos__cell--br" title="<?php esc_attr_e( 'Bottom Right', 'dukkan-plugin' ); ?>">
                                                <input type="radio" name="position" value="bottom-right">
                                                <span class="wpldp-pos__dot"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </section>

                        <section class="wpldp-badge-section">
                            <h3 class="wpldp-badge-section__title"><?php esc_html_e( 'Colors', 'dukkan-plugin' ); ?></h3>
                            <div class="wpldp-badge-section__body">
                                <div class="wpldp-field-row">
                                    <div class="wpldp-field">
                                        <label for="wpldp-badge-background-color"><?php esc_html_e( 'Background', 'dukkan-plugin' ); ?></label>
                                        <input type="text" name="background_color" id="wpldp-badge-background-color" class="wpldp-color-field" value="#e53935">
                                    </div>
                                    <div class="wpldp-field">
                                        <label for="wpldp-badge-text-color"><?php esc_html_e( 'Text', 'dukkan-plugin' ); ?></label>
                                        <input type="text" name="text_color" id="wpldp-badge-text-color" class="wpldp-color-field" value="#ffffff">
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="wpldp-badge-section">
                            <h3 class="wpldp-badge-section__title"><?php esc_html_e( 'Visibility', 'dukkan-plugin' ); ?></h3>
                            <div class="wpldp-badge-section__body">

                                <div class="wpldp-field">
                                    <label for="wpldp-badge-applied-to"><?php esc_html_e( 'Applied to', 'dukkan-plugin' ); ?> <span class="wpldp-required-star">*</span></label>
                                    <div class="wpldp-select-wrap">
                                        <select name="applied_to" id="wpldp-badge-applied-to">
                                            <option value="all"><?php esc_html_e( 'All products', 'dukkan-plugin' ); ?></option>
                                            <option value="specific_products"><?php esc_html_e( 'Specific Products', 'dukkan-plugin' ); ?></option>
                                            <option value="specific_categories"><?php esc_html_e( 'Specific Categories', 'dukkan-plugin' ); ?></option>
                                            <option value="specific_tags"><?php esc_html_e( 'Specific Product Tags', 'dukkan-plugin' ); ?></option>
                                        </select>
                                        <span class="dashicons dashicons-arrow-down-alt2 wpldp-select-caret"></span>
                                    </div>
                                </div>

                                <div class="wpldp-field wpldp-search-column" style="display:none;">
                                    <label><?php esc_html_e( 'Search', 'dukkan-plugin' ); ?></label>

                                    <div id="wpldp-badge-products-box" class="wpldp-target-box" style="display:none;">
                                        <div class="wpldp-combo" data-combo="badge_products" data-name="products[]">
                                            <div class="wpldp-combo-control">
                                                <input type="text" class="wpldp-combo-input" placeholder="<?php esc_attr_e( 'Search for products…', 'dukkan-plugin' ); ?>" autocomplete="off">
                                                <span class="dashicons dashicons-arrow-down-alt2 wpldp-combo-caret"></span>
                                            </div>
                                            <div class="wpldp-combo-menu"></div>
                                        </div>
                                    </div>

                                    <div id="wpldp-badge-categories-box" class="wpldp-target-box" style="display:none;">
                                        <div class="wpldp-combo" data-combo="badge_categories" data-name="categories[]">
                                            <div class="wpldp-combo-control">
                                                <input type="text" class="wpldp-combo-input" placeholder="<?php esc_attr_e( 'Search categories…', 'dukkan-plugin' ); ?>" autocomplete="off">
                                                <span class="dashicons dashicons-arrow-down-alt2 wpldp-combo-caret"></span>
                                            </div>
                                            <div class="wpldp-combo-menu"></div>
                                        </div>
                                    </div>

                                    <div id="wpldp-badge-tags-box" class="wpldp-target-box" style="display:none;">
                                        <div class="wpldp-combo" data-combo="badge_tags" data-name="tags[]">
                                            <div class="wpldp-combo-control">
                                                <input type="text" class="wpldp-combo-input" placeholder="<?php esc_attr_e( 'Search product tags…', 'dukkan-plugin' ); ?>" autocomplete="off">
                                                <span class="dashicons dashicons-arrow-down-alt2 wpldp-combo-caret"></span>
                                            </div>
                                            <div class="wpldp-combo-menu"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="wpldp-combo-tags" data-tags-for="badge_products"></div>
                                <div class="wpldp-combo-tags" data-tags-for="badge_categories"></div>
                                <div class="wpldp-combo-tags" data-tags-for="badge_tags"></div>

                            </div>
                        </section>

                    </div>

                    <aside class="wpldp-badge-editor__aside">
                        <div class="wpldp-badge-preview-card">
                            <div class="wpldp-badge-preview-card__head"><?php esc_html_e( 'Live Preview', 'dukkan-plugin' ); ?></div>
                            <div class="wpldp-badge-preview-stage">
                                <div class="wpldp-preview-product">
                                    <div class="wpldp-preview-image">
                                        <span class="dashicons dashicons-format-image wpldp-preview-image-placeholder"></span>
                                        <span id="wpldp-badge-preview" class="wpldp-badge-preview"></span>
                                    </div>
                                    <div class="wpldp-preview-info">
                                        <span class="wpldp-preview-name"><?php esc_html_e( 'Product Name', 'dukkan-plugin' ); ?></span>
                                        <span class="wpldp-preview-price">$ 0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </aside>

                </div>

                <div class="wpldp-panel-footer">
                    <button type="button" class="button wpldp-badge-cancel"><?php esc_html_e( 'Cancel', 'dukkan-plugin' ); ?></button>
                    <button type="submit" class="button button-primary wpldp-badge-save"><?php esc_html_e( 'Save Badge', 'dukkan-plugin' ); ?></button>
                </div>

                <input type="hidden" name="badge_id" id="wpldp-badge-id" value="">
            </form>
        </div>

        <div id="wpldp-badge-no-selection" class="wpldp-badge-no-selection" style="display:none;">
            <p><?php esc_html_e( 'Select a badge from the left or create a new one.', 'dukkan-plugin' ); ?></p>
        </div>
    </div>
</div>

<!-- Delete badge confirmation modal -->
<div class="wpldp-confirm-overlay" id="wpldp-badge-confirm-overlay" style="display:none;">
    <div class="wpldp-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="wpldp-badge-confirm-title">
        <div class="wpldp-confirm-icon">
            <span class="dashicons dashicons-trash"></span>
        </div>
        <h3 id="wpldp-badge-confirm-title"><?php esc_html_e( 'Are you sure you want to delete this badge?', 'dukkan-plugin' ); ?></h3>
        <p><?php esc_html_e( 'This action cannot be undone.', 'dukkan-plugin' ); ?></p>
        <div class="wpldp-confirm-actions">
            <button type="button" class="button wpldp-badge-confirm-cancel"><?php esc_html_e( 'Cancel', 'dukkan-plugin' ); ?></button>
            <button type="button" class="button button-primary wpldp-badge-confirm-delete"><?php esc_html_e( 'Delete', 'dukkan-plugin' ); ?></button>
        </div>
    </div>
</div>
