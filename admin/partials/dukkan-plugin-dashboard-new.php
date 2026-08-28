<?php
/**
 * Dukkan Mobile — feature cards dashboard.
 *
 * Styles for `.dukkan-main-d-*` classes live in the enqueued stylesheet
 * (admin/css/dukkan-plugin-admin.css), not inline.
 */
$dukkan_feature_cards = [
    [ 'label' => 'Products Management',      'icon' => DUKKAN_PLUGIN_URL . 'admin/images/products.jpeg' ],
    [ 'label' => 'Orders Management',        'icon' => DUKKAN_PLUGIN_URL . 'admin/images/order.jpeg' ],
    [ 'label' => 'Real Time Analytics',      'icon' => DUKKAN_PLUGIN_URL . 'admin/images/analytics.jpeg' ],
    [ 'label' => 'Coupons & Discounts',      'icon' => DUKKAN_PLUGIN_URL . 'admin/images/coupons.jpeg' ],
    [ 'label' => 'Ai Translation',           'icon' => DUKKAN_PLUGIN_URL . 'admin/images/translation.jpeg' ],
    [ 'label' => 'Bulk Product Edit',        'icon' => DUKKAN_PLUGIN_URL . 'admin/images/bulk-products-edits.jpeg' ],
    [ 'label' => 'Drag & Drop Categories',   'icon' => DUKKAN_PLUGIN_URL . 'admin/images/categories.jpeg' ],
    [ 'label' => 'Stock Management',         'icon' => DUKKAN_PLUGIN_URL . 'admin/images/inventory-management-barcode.jpeg' ],
    [ 'label' => 'Tags',                     'icon' => DUKKAN_PLUGIN_URL . 'admin/images/tags.jpeg' ],
    [ 'label' => 'Attributes',               'icon' => DUKKAN_PLUGIN_URL . 'admin/images/attributes.jpeg' ],
    [ 'label' => 'Product Addons',           'icon' => DUKKAN_PLUGIN_URL . 'admin/images/product-addons.jpeg' ],
    [ 'label' => 'Push Notifications',       'icon' => DUKKAN_PLUGIN_URL . 'admin/images/notifications.jpeg' ],
];
?>
<div class="dukkan-dashboard">

    <!-- Top Banner -->
    <div class="dukkan-top-banner">
        <?php esc_html_e( 'Manage your WooCommerce store through', 'dukkan-plugin' ); ?>
        <strong><?php esc_html_e( 'Dukkan Admin App', 'dukkan-plugin' ); ?></strong>
    </div>

    <div class="dukkan-dashboard-layout">

        <div class="dukkan-main-d-wrapper">

            <!-- HERO -->
            <section class="dukkan-main-d-hero">
                <h1 class="dukkan-main-d-hero__title">
                    <?php esc_html_e( 'Manage your WooCommerce store from your mobile', 'dukkan-plugin' ); ?>
                </h1>
                <p class="dukkan-main-d-hero__subtitle">
                    <?php esc_html_e( 'Dukkan WooCommerce Admin App makes it easy — manage orders, products (using Ai), track real-time sales, translate products, product addons faster than ever before.', 'dukkan-plugin' ); ?>
                </p>
                <a href="#" class="dukkan-main-d-btn"><?php esc_html_e( 'Download The App', 'dukkan-plugin' ); ?></a>
                <a href="#" class="dukkan-main-d-btn-invert"><?php esc_html_e( 'Product addons', 'dukkan-plugin' ); ?></a>
            </section>

            <!-- FEATURE CARDS -->
            <div class="dukkan-main-d-features">
                <?php foreach ( $dukkan_feature_cards as $card ) : ?>
                <div class="dukkan-main-d-card">
                    <div class="dukkan-main-d-card__icon">
                        <img
                            src="<?php echo esc_attr( $card['icon'] ); ?>"
                            alt="<?php echo esc_attr( $card['label'] ); ?> icon"
                            loading="lazy"
                        />
                    </div>
                    <span class="dukkan-main-d-card__label">
                        <?php echo esc_html( $card['label'] ); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>

        </div>

    </div>

</div>
