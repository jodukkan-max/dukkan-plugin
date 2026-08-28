<?php
/**
 * Dukkan settings page — native WordPress tabs.
 *
 * Tabs are rendered with the WordPress `nav-tab-wrapper` component and each
 * tab is a full page-load link (`?tab=...`). Only the active tab's content is
 * rendered, so inactive tabs are never generated on page load.
 */

$tabs = apply_filters( 'dukkan_settings_tabs', array(

    'dukkan_main' => array(
        'title' => __( 'Dukkan Mobile', 'dukkan-plugin' ),
        'icon'  => 'dashicons-smartphone',
    ),

    'store_app_connection' => array(
        'title' => __( 'Store OTP', 'dukkan-plugin' ),
        'icon'  => 'dashicons-lock',
    ),

));

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

if ( ! $active_tab || ! isset( $tabs[ $active_tab ] ) ) {
    $active_tab = key( $tabs );
}

?>
<div class="wrap dukkan-settings-wrap">

    <h1><?php esc_html_e( 'Dukkan', 'dukkan-plugin' ); ?></h1>

    <nav class="nav-tab-wrapper">

        <?php foreach ( $tabs as $tab_id => $tab ) : ?>

            <?php
            $tab_url   = admin_url( 'admin.php?page=dukkan-settings&tab=' . $tab_id );
            $is_active = $active_tab === $tab_id;
            $icon      = ! empty( $tab['icon'] ) ? $tab['icon'] : 'dashicons-admin-generic';
            ?>

            <a href="<?php echo esc_url( $tab_url ); ?>" class="nav-tab <?php echo $is_active ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
                <span><?php echo esc_html( $tab['title'] ); ?></span>
            </a>

        <?php endforeach; ?>

    </nav>

    <div class="dukkan-tab-content">
        <?php do_action( 'dukkan_settings_tab_content_' . $active_tab ); ?>
    </div>

</div>
