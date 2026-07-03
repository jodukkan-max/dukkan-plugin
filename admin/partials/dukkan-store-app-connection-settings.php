<div class="wpldp-content">

    <?php $generated_auth_code = get_option('dukkan_plugin_store_connection_auth_code'); ?>
    <?php if ($generated_auth_code): ?>
        <div class="wpldp-group">
            <div class="wpldp-group-top">
                <span>Generated Auth OTP: <?php echo $generated_auth_code; ?></span>
                <button class="wpldp-button" type="button" onclick="location.reload();">Refresh to see the latest generated auth OTP.</button>
            </div>
        </div>
    <?php else: ?>
        <div class="wpldp-group">
            <div class="wpldp-group-top">
                <span>No auth code generated yet. Click the button below.</span>
                <button class="wpldp-button" type="button" onclick="location.reload();">Refresh to see the generated auth OTP.</button>
            </div>
        </div>
    <?php endif; ?>

</div>