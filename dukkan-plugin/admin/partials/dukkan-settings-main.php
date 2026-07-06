<?php

$tabs = apply_filters('dukkan_settings_tabs', array(

    'dukkan_main' => array(
        'title' => 'Dukkan Mobile',
        'icon'  => 'fa-solid fa-mobile-screen',
    ),

    'store_app_connection' => array(
        'title' => 'Store OTP',
        'icon'  => 'fa-solid fa-key',
    ),

    // 'addons' => array(
    //     'title' => 'Product Add-Ons',
    //     'icon'  => 'fa-solid fa-dollar-sign',
    // ),

    // 'discounts' => array(
    //     'title' => 'Dynamic Pricing & Discounts',
    //     'icon'  => 'fa-solid fa-percent',
    // )

));

$active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';

if (!$active_tab || !isset($tabs[$active_tab])) {
    $active_tab = key($tabs);
}

?>
<div class="wpldp-wrapper">

    <div class="wpldp-tabs">

        <?php 
        foreach ($tabs as $tab_id => $tab) : 
            $is_active = $active_tab === $tab_id;
        ?>

            <div class="tab <?php echo $is_active ? 'active' : ''; ?>" data-tab="<?php echo esc_attr($tab_id); ?>">

                <i class="<?php echo esc_attr($tab['icon']); ?>"></i>
                <?php echo esc_html($tab['title']); ?>

            </div>

        <?php 
        endforeach; 
        ?>

    </div>


    <div class="tab-content">

        <?php

        foreach ($tabs as $tab_id => $tab) :
            $is_active = $active_tab === $tab_id;

        ?>

            <div class="wpldp-tab-panel <?php echo $is_active ? 'active' : ''; ?>" id="<?php echo esc_attr($tab_id); ?>">

                <?php
                do_action('dukkan_settings_tab_content_' . $tab_id);
                ?>

            </div>

        <?php

        endforeach;

        ?>

    </div>
</div>
