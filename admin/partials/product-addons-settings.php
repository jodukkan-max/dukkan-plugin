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

        <p class="wpldp-subtitle">
            <?php esc_html_e('Manage product customizations', 'dukkan-plugin'); ?>
        </p>

        <button type="button" class="wpldp-new-group">
            <i class="fa-solid fa-plus"></i>
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
                            <label class="wpldp-switch">
                                <input type="checkbox" class="wpldp-toggle-product-addon-status" data-id="<?php echo esc_attr($group_key); ?>" <?php checked($group['status'], 1); ?>>
                                <span class="wpldp-slider"></span>
                            </label>
                        </div>
                        <div class="wpldp-group-actions">
                            <i class="fa-regular fa-copy wpldp-duplicate-product-addon-group"></i>
                            <i class="fa-solid fa-trash wpldp-delete-product-addon-group"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>


    <div class="wpldp-main" id="wpldpAddonFieldsContainer">

        <div class="empty-box wpldp-no-selection-box">

            <div class="wpldp-icon">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>

            <h2>
                <?php esc_html_e('Select a Group', 'dukkan-plugin'); ?>
            </h2>

            <p>
                <?php esc_html_e('Choose an add-on group from the sidebar to view and manage its fields', 'dukkan-plugin'); ?>
            </p>

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

<div class="wpldp-modal-overlay" id="wpldpModal">

    <div class="wpldp-modal">

        <div class="wpldp-modal-header">
            <h2><?php esc_html_e('Create New Group', 'dukkan-plugin'); ?></h2>
            <button class="wpldp-close">&times;</button>
        </div>

        <p class="wpldp-modal-subtitle">
            <?php esc_html_e('Add a new add-on group to organize your product customizations', 'dukkan-plugin'); ?>
        </p>

        <div class="wpldp-divider"></div>

        <form id="wpldp-group-form">
            <div class="wpldp-field">
                <label><?php esc_html_e('Group Name', 'dukkan-plugin'); ?> <span class="wpldp-required-star">*</span></label>
                <input type="text" name="product_addon[group_name]" placeholder="<?php esc_attr_e('e.g., Gift Options', 'dukkan-plugin'); ?>">
            </div>

            <div class="wpldp-field">
                <label><?php esc_html_e('Description', 'dukkan-plugin'); ?></label>
                <textarea name="product_addon[description]" placeholder="<?php esc_attr_e('Brief description of this group', 'dukkan-plugin'); ?>"></textarea>
            </div>

            <div class="wpldp-field">
                <label><?php esc_html_e('Applied to', 'dukkan-plugin'); ?> <span class="wpldp-required-star">*</span></label>
                <div class="wpldp-select-wrap">
                    <select name="product_addon[applied_to]" id="wpldp-applied-to">
                        <option value="all"><?php esc_html_e('All products', 'dukkan-plugin'); ?></option>
                        <option value="specific"><?php esc_html_e('Specific products or categories', 'dukkan-plugin'); ?></option>
                    </select>
                </div>
            </div>

            <div id="wpldp-conditional-box" style="display:none;">
                <!-- SELECT PRODUCTS -->
                <div class="wpldp-sub-section product-select-wrapper">
                    <h4><?php esc_html_e('Select Products', 'dukkan-plugin'); ?></h4>
                    <!-- Tags render here, ABOVE the input -->
                    <div class="selected-products-tags" style="display:none;"></div>
                    <div class="wpldp-search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <select name="product_addon[products][]" class="wc-product-search" id="wpldp-product-search" data-action="wpldp_search_products" data-dropdownparent="#wpldpModal" data-placeholder="<?php esc_attr_e( 'Search for products…', 'dukkan-plugin' ); ?>" multiple style="width:100%" data-allow_clear="true"></select>
                    </div>
                </div>

                <!-- SELECT CATEGORIES -->
                <div class="wpldp-sub-section">
                    <h4><?php esc_html_e('Select Categories', 'dukkan-plugin'); ?></h4>

                    <div class="wpldp-search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="wpldp-category-search" placeholder="<?php esc_attr_e('Search categories...', 'dukkan-plugin'); ?>">
                    </div>

                    <div class="wpldp-category-list">

                        <label><input type="checkbox"> Food</label>

                        <div class="wpldp-sub-cat">
                            <label><input type="checkbox"> Pizza</label>
                            <label><input type="checkbox"> Pasta</label>
                        </div>

                        <label><input type="checkbox"> Beverages</label>

                        <div class="wpldp-sub-cat">
                            <label><input type="checkbox"> Soft Drinks</label>
                            <label><input type="checkbox"> Alcoholic</label>
                        </div>

                    </div>
                </div>

            </div>

            <div class="wpldp-divider"></div>

            <div class="wpldp-modal-footer">
                <button type="button" class="wpldp-cancel"><?php esc_html_e('Cancel', 'dukkan-plugin'); ?></button>
                <button type="submit" class="wpldp-create"><?php esc_html_e('Create Group', 'dukkan-plugin'); ?></button>
            </div>
        </form>
    </div>

</div>