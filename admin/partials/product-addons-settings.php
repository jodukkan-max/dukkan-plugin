<?php $product_addon_groups = get_option('wpldp_product_addon_groups', []); 
?>
<div class="wpldp-content">

    <div class="wpldp-sidebar">
        <div class="dukkan-brand">
            <img 
                src="<?php echo DUKKAN_PLUGIN_URL . 'admin/images/dukkan-logo.png'; ?>" 
                alt="Dukkan"
                class="dukkan-brand-logo"
            >
        </div>

        <h3><?php esc_html_e('Add-On Groups', 'dukkan-plugin'); ?></h3>

        <button type="button" class="wpldp-new-group">
            <span class="dashicons dashicons-plus-alt2"></span>
            <?php esc_html_e('New Group', 'dukkan-plugin'); ?>
        </button>

        <div class="wpldp-group-list">
            <?php if(empty($product_addon_groups)): ?>
                <p class="wpldp-empty"><?php esc_html_e('No groups created yet. Click "New Group" to get started!', 'dukkan-plugin'); ?></p>
            <?php else: ?>
                <?php foreach ($product_addon_groups as $group_key => $group): ?>
                    <div class="wpldp-group" data-id="<?php echo esc_attr($group_key); ?>">
                        <div class="wpldp-group-top">
                            <span><?php echo esc_html($group['group_name']); ?></span>
                            <div class="wpldp-group-top-controls">
                                <button type="button" class="wpldp-trash-btn" data-id="<?php echo esc_attr($group_key); ?>" title="<?php esc_attr_e('Delete group', 'dukkan-plugin'); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                                <label class="wpldp-switch">
                                    <input type="checkbox" class="wpldp-toggle-product-addon-status" data-id="<?php echo esc_attr($group_key); ?>" <?php checked($group['status'], 1); ?>>
                                    <span class="wpldp-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>


    <div class="wpldp-main" id="wpldpAddonFieldsContainer">

        <div id="wpldp-create-group-panel" class="wpldp-create-panel" style="display:none;">

            <form id="wpldp-group-form">
                <div class="wpldp-field-row">
                    <div class="wpldp-field">
                        <label><?php esc_html_e('Group Name', 'dukkan-plugin'); ?> <span class="wpldp-required-star">*</span></label>
                        <input type="text" name="product_addon[group_name]" placeholder="<?php esc_attr_e('e.g., Gift Options', 'dukkan-plugin'); ?>">
                    </div>

                    <div class="wpldp-field">
                        <label><?php esc_html_e('Applied to', 'dukkan-plugin'); ?> <span class="wpldp-required-star">*</span></label>
                        <div class="wpldp-select-wrap">
                            <select name="product_addon[applied_to]" id="wpldp-applied-to">
                                <option value="all"><?php esc_html_e('All products', 'dukkan-plugin'); ?></option>
                                <option value="specific_products"><?php esc_html_e('Specific Products', 'dukkan-plugin'); ?></option>
                                <option value="specific_categories"><?php esc_html_e('Specific Categories', 'dukkan-plugin'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="wpldp-field wpldp-search-column" style="display:none;">
                        <label><?php esc_html_e('Search', 'dukkan-plugin'); ?></label>
                        <!-- SELECT PRODUCTS -->
                        <div id="wpldp-products-box" class="wpldp-target-box" style="display:none;">
                            <div class="wpldp-combo" data-combo="products" data-name="product_addon[products][]">
                                <div class="wpldp-combo-control">
                                    <input type="text" class="wpldp-combo-input" placeholder="<?php esc_attr_e( 'Search for products…', 'dukkan-plugin' ); ?>" autocomplete="off">
                                    <span class="dashicons dashicons-arrow-down-alt2 wpldp-combo-caret"></span>
                                </div>
                                <div class="wpldp-combo-menu"></div>
                            </div>
                        </div>

                        <!-- SELECT CATEGORIES -->
                        <div id="wpldp-categories-box" class="wpldp-target-box" style="display:none;">
                            <div class="wpldp-combo" data-combo="categories" data-name="product_addon[categories][]">
                                <div class="wpldp-combo-control">
                                    <input type="text" class="wpldp-combo-input" placeholder="<?php esc_attr_e('Search categories...', 'dukkan-plugin'); ?>" autocomplete="off">
                                    <span class="dashicons dashicons-arrow-down-alt2 wpldp-combo-caret"></span>
                                </div>
                                <div class="wpldp-combo-menu"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Selected labels (full width, under the fields) -->
                <div class="wpldp-combo-tags" data-tags-for="products"></div>
                <div class="wpldp-combo-tags" data-tags-for="categories"></div>

                <div class="wpldp-panel-footer">
                    <button type="button" class="button wpldp-cancel"><?php esc_html_e('Cancel', 'dukkan-plugin'); ?></button>
                    <button type="submit" class="button button-primary wpldp-create"><?php esc_html_e('Create Group', 'dukkan-plugin'); ?></button>
                </div>
            </form>
        </div>

        <div id="wpldp-addon-group-form-global" style="display:none;">
            
            <div class="wpldp-addon-group-details">
                
            </div>

            <div class="wpldp-product-addon-fields">

                
                
            </div>
            <div class="wpldp-addon-group-footer">
                <button type="button" class="wpldp-save-addon-group-changes"><?php esc_html_e('Save Changes', 'dukkan-plugin'); ?></button>
            </div>
        </div>

    </div>

</div>

<!-- Delete group confirmation modal -->
<div class="wpldp-confirm-overlay" id="wpldp-confirm-overlay" style="display:none;">
    <div class="wpldp-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="wpldp-confirm-title">
        <div class="wpldp-confirm-icon">
            <span class="dashicons dashicons-trash"></span>
        </div>
        <h3 id="wpldp-confirm-title"><?php esc_html_e('Are you sure you want to delete this group?', 'dukkan-plugin'); ?></h3>
        <p><?php esc_html_e('This action cannot be undone.', 'dukkan-plugin'); ?></p>
        <div class="wpldp-confirm-actions">
            <button type="button" class="button wpldp-confirm-cancel"><?php esc_html_e('Cancel', 'dukkan-plugin'); ?></button>
            <button type="button" class="button button-primary wpldp-confirm-delete"><?php esc_html_e('Delete', 'dukkan-plugin'); ?></button>
        </div>
    </div>
</div>
