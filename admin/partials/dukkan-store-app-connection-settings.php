<div class="dukkan-store-settings">

    <div class="dukkan-store-settings__header">
        <h2><?php esc_html_e( 'Store App Connection', 'dukkan-plugin' ); ?></h2>
        <p><?php esc_html_e( 'Use this one-time code to connect your Dukkan mobile app to this store.', 'dukkan-plugin' ); ?></p>
    </div>

    <?php $generated_auth_code = get_option( 'dukkan_plugin_store_connection_auth_code' ); ?>

    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><?php esc_html_e( 'Auth OTP', 'dukkan-plugin' ); ?></th>
            <td>
                <?php if ( $generated_auth_code ) : ?>
                    <span class="wpldp-store-connection-auth-code">
                        <strong><?php echo esc_html( $generated_auth_code ); ?></strong>
                    </span>
                <?php else : ?>
                    <span><?php esc_html_e( 'No auth code generated yet.', 'dukkan-plugin' ); ?></span>
                <?php endif; ?>
                <p class="description">
                    <?php esc_html_e( 'The code is generated when your app requests a store connection.', 'dukkan-plugin' ); ?>
                </p>
            </td>
        </tr>
    </table>

    <p>
        <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=dukkan-settings&tab=store_app_connection' ) ); ?>">
            <?php esc_html_e( 'Refresh', 'dukkan-plugin' ); ?>
        </a>
    </p>

</div>
