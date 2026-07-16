<div class="wpldp-content-store-connection">

    <?php $generated_auth_code = get_option('dukkan_plugin_store_connection_auth_code'); ?>
    <?php if ($generated_auth_code): ?>
        <div class="wpldp-group">
            <div class="wpldp-group-top">
                <span class="wpldp-store-connection-auth-code">Generated Auth OTP: <strong><?php echo $generated_auth_code; ?></strong></span>
                <button class="wpldp-button" type="button" onclick="location.replace('<?php echo admin_url('admin.php?page=dukkan-settings&tab=store_app_connection'); ?>');">Refresh to see the latest generated auth OTP.</button>
            </div>
        </div>
    <?php else: ?>
        <div class="wpldp-group">
            <div class="wpldp-group-top">
                <span>No auth code generated yet. Click the button below.</span>
                <button class="wpldp-button" type="button" onclick="location.replace('<?php echo admin_url('admin.php?page=dukkan-settings&tab=store_app_connection'); ?>');">Refresh to see the generated auth OTP.</button>
            </div>
        </div>
    <?php endif; ?>

</div>