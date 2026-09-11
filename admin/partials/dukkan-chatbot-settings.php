<?php
/**
 * AI Chatbot settings — native WordPress form-table layout.
 *
 * @since 1.0.27
 *
 * @var array $settings    Chatbot settings merged with defaults.
 * @var array $index       Products index status (count, last_build).
 * @var array $cat_index   Categories index status (count, last_build).
 * @var array $page_index  Pages index status (count, last_build).
 * @var array $order_index Orders index status (count, last_build).
 * @var string $whatsapp_webhook_url  The WhatsApp webhook URL.
 * @var string $whatsapp_verify_token The auto-generated verify token.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$language_options = array(
	'auto'  => __( 'Auto — match the customer', 'dukkan-plugin' ),
	'fixed' => __( 'Fixed language', 'dukkan-plugin' ),
	'site'  => __( 'Site default', 'dukkan-plugin' ),
);

$tone_options = array(
	'friendly' => __( 'Friendly', 'dukkan-plugin' ),
	'official' => __( 'Official', 'dukkan-plugin' ),
	'casual'   => __( 'Casual', 'dukkan-plugin' ),
	'fun'      => __( 'Fun', 'dukkan-plugin' ),
);
?>
<div class="wrap dukkan-chatbot-settings">

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'AI Chatbot settings saved.', 'dukkan-plugin' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dukkan-chatbot-form">
		<input type="hidden" name="action" value="dukkan_chatbot_save_settings">
		<?php wp_nonce_field( 'dukkan_chatbot_settings', 'dukkan_chatbot_settings_nonce' ); ?>

		<div class="dukkan-loyalty-master">
			<div class="dukkan-loyalty-master__text">
				<h2><?php esc_html_e( 'AI Chatbot', 'dukkan-plugin' ); ?></h2>
				<p><?php esc_html_e( 'A Google Gemma store assistant that helps customers find products, track orders, and check their loyalty points.', 'dukkan-plugin' ); ?></p>
			</div>
			<div class="dukkan-loyalty-master__control">
				<span class="dukkan-loyalty-master__status<?php echo ! empty( $settings['enabled'] ) ? ' is-active' : ''; ?>" data-status-text>
					<?php echo ! empty( $settings['enabled'] ) ? esc_html__( 'Active', 'dukkan-plugin' ) : esc_html__( 'Inactive', 'dukkan-plugin' ); ?>
				</span>
				<label class="dukkan-loyalty-master__switch">
					<input type="checkbox" name="dukkan_chatbot[enabled]" value="1" data-master-toggle <?php checked( ! empty( $settings['enabled'] ), 1 ); ?>>
					<span class="dukkan-loyalty-master__slider"></span>
				</label>
			</div>
		</div>

		<div class="dukkan-loyalty-card">
			<div class="dukkan-loyalty-card__head">
				<h2><?php esc_html_e( 'Personality & language', 'dukkan-plugin' ); ?></h2>
				<p><?php esc_html_e( 'Control how the assistant speaks and which language it uses.', 'dukkan-plugin' ); ?></p>
			</div>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Language', 'dukkan-plugin' ); ?></th>
						<td>
							<select name="dukkan_chatbot[language]" id="dukkan-chatbot-language">
								<?php foreach ( $language_options as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['language'], $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<input type="text" name="dukkan_chatbot[fixed_language]" id="dukkan-chatbot-fixed-language" value="<?php echo esc_attr( $settings['fixed_language'] ); ?>" class="small-text" placeholder="en / ar / fr" style="<?php echo 'fixed' === $settings['language'] ? '' : 'display:none;'; ?>">
							<p class="description"><?php esc_html_e( '"Auto" replies in the customer\'s own language — recommended for bilingual stores.', 'dukkan-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Tone', 'dukkan-plugin' ); ?></th>
						<td>
							<select name="dukkan_chatbot[tone]">
								<?php foreach ( $tone_options as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['tone'], $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'System prompt', 'dukkan-plugin' ); ?></th>
						<td>
							<textarea name="dukkan_chatbot[system_prompt]" rows="4" class="large-text" placeholder="<?php esc_attr_e( 'Store identity, policies and extra rules…', 'dukkan-plugin' ); ?>"><?php echo esc_textarea( $settings['system_prompt'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Bot name', 'dukkan-plugin' ); ?></th>
						<td>
							<input type="text" name="dukkan_chatbot[bot_name]" value="<?php echo esc_attr( $settings['bot_name'] ); ?>" class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Bot avatar', 'dukkan-plugin' ); ?></th>
						<td>
							<input type="url" name="dukkan_chatbot[bot_avatar]" value="<?php echo esc_attr( $settings['bot_avatar'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'https://…', 'dukkan-plugin' ); ?>">
							<p class="description"><?php esc_html_e( 'Optional image URL shown in the chat header. Leave empty to use the default illustrated avatar.', 'dukkan-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Greeting message', 'dukkan-plugin' ); ?></th>
						<td>
							<textarea name="dukkan_chatbot[greeting]" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Hi! Ask me about products, orders, or anything else.', 'dukkan-plugin' ); ?>"><?php echo esc_textarea( $settings['greeting'] ); ?></textarea>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="dukkan-loyalty-card">
			<div class="dukkan-loyalty-card__head">
				<h2><?php esc_html_e( 'Appearance', 'dukkan-plugin' ); ?></h2>
			</div>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Accent color', 'dukkan-plugin' ); ?></th>
						<td>
							<input type="text" name="dukkan_chatbot[accent_color]" value="<?php echo esc_attr( $settings['accent_color'] ); ?>" class="dukkan-chatbot-color" data-default-color="#1d4f5f">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Widget position', 'dukkan-plugin' ); ?></th>
						<td>
							<select name="dukkan_chatbot[position]">
								<option value="bottom-right" <?php selected( $settings['position'], 'bottom-right' ); ?>><?php esc_html_e( 'Bottom right', 'dukkan-plugin' ); ?></option>
								<option value="bottom-left" <?php selected( $settings['position'], 'bottom-left' ); ?>><?php esc_html_e( 'Bottom left', 'dukkan-plugin' ); ?></option>
							</select>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="dukkan-loyalty-card">
			<div class="dukkan-loyalty-card__head">
				<h2><?php esc_html_e( 'Catalog & retrieval', 'dukkan-plugin' ); ?></h2>
				<p><?php esc_html_e( 'The assistant searches your catalog using semantic (meaning-based) search. Rebuild each index after importing products or categories.', 'dukkan-plugin' ); ?></p>
			</div>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Products index', 'dukkan-plugin' ); ?></th>
						<td>
							<p>
								<strong id="dukkan-chatbot-index-count"><?php echo esc_html( $index['count'] ); ?></strong>
								<?php esc_html_e( 'products indexed', 'dukkan-plugin' ); ?>
								<?php if ( $index['last_build'] ) : ?>
									— <?php esc_html_e( 'last build', 'dukkan-plugin' ); ?> <?php echo esc_html( $index['last_build'] ); ?>
								<?php endif; ?>
							</p>
							<button type="button" class="button" id="dukkan-chatbot-rebuild"><?php esc_html_e( 'Rebuild products index', 'dukkan-plugin' ); ?></button>
							<span id="dukkan-chatbot-rebuild-result"></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Categories index', 'dukkan-plugin' ); ?></th>
						<td>
							<p>
								<strong id="dukkan-chatbot-cat-count"><?php echo esc_html( $cat_index['count'] ); ?></strong>
								<?php esc_html_e( 'categories indexed', 'dukkan-plugin' ); ?>
								<?php if ( $cat_index['last_build'] ) : ?>
									— <?php esc_html_e( 'last build', 'dukkan-plugin' ); ?> <?php echo esc_html( $cat_index['last_build'] ); ?>
								<?php endif; ?>
							</p>
							<button type="button" class="button" id="dukkan-chatbot-rebuild-categories"><?php esc_html_e( 'Rebuild categories index', 'dukkan-plugin' ); ?></button>
							<span id="dukkan-chatbot-rebuild-categories-result"></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Pages index', 'dukkan-plugin' ); ?></th>
						<td>
							<p>
								<strong id="dukkan-chatbot-page-count"><?php echo esc_html( $page_index['count'] ); ?></strong>
								<?php esc_html_e( 'pages indexed', 'dukkan-plugin' ); ?>
								<?php if ( $page_index['last_build'] ) : ?>
									— <?php esc_html_e( 'last build', 'dukkan-plugin' ); ?> <?php echo esc_html( $page_index['last_build'] ); ?>
								<?php endif; ?>
							</p>
							<button type="button" class="button" id="dukkan-chatbot-rebuild-pages"><?php esc_html_e( 'Rebuild pages index', 'dukkan-plugin' ); ?></button>
							<span id="dukkan-chatbot-rebuild-pages-result"></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Orders index', 'dukkan-plugin' ); ?></th>
						<td>
							<p>
								<strong id="dukkan-chatbot-order-count"><?php echo esc_html( $order_index['count'] ); ?></strong>
								<?php esc_html_e( 'orders indexed', 'dukkan-plugin' ); ?>
								<?php if ( $order_index['last_build'] ) : ?>
									— <?php esc_html_e( 'last build', 'dukkan-plugin' ); ?> <?php echo esc_html( $order_index['last_build'] ); ?>
								<?php endif; ?>
							</p>
							<button type="button" class="button" id="dukkan-chatbot-rebuild-orders"><?php esc_html_e( 'Rebuild orders index', 'dukkan-plugin' ); ?></button>
							<span id="dukkan-chatbot-rebuild-orders-result"></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Auto-index on save', 'dukkan-plugin' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="dukkan_chatbot[auto_index]" value="1" <?php checked( ! empty( $settings['auto_index'] ), 1 ); ?>>
								<?php esc_html_e( 'Re-index a product automatically when it is saved', 'dukkan-plugin' ); ?>
							</label>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="dukkan-loyalty-card">
			<div class="dukkan-loyalty-card__head">
				<h2><?php esc_html_e( 'Capabilities', 'dukkan-plugin' ); ?></h2>
			</div>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Order & points lookup', 'dukkan-plugin' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="dukkan_chatbot[enable_lookup]" value="1" <?php checked( ! empty( $settings['enable_lookup'] ), 1 ); ?>>
								<?php esc_html_e( 'Let logged-in customers ask about their orders and loyalty points', 'dukkan-plugin' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Human handoff', 'dukkan-plugin' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="dukkan_chatbot[enable_handoff]" value="1" <?php checked( ! empty( $settings['enable_handoff'] ), 1 ); ?>>
								<?php esc_html_e( 'Email support when a customer asks for a human', 'dukkan-plugin' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Support email', 'dukkan-plugin' ); ?></th>
						<td>
							<input type="email" name="dukkan_chatbot[support_email]" value="<?php echo esc_attr( $settings['support_email'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Defaults to the admin email if left empty.', 'dukkan-plugin' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="dukkan-loyalty-card">
			<div class="dukkan-loyalty-card__head">
				<h2><?php esc_html_e( 'WhatsApp Business', 'dukkan-plugin' ); ?></h2>
				<p><?php esc_html_e( 'Answer customers on WhatsApp with the same AI assistant. Only two values to paste — we handle the rest.', 'dukkan-plugin' ); ?></p>
			</div>

			<div class="dukkan-whatsapp-steps">
				<div class="dukkan-whatsapp-steps__item">
					<span class="dukkan-whatsapp-steps__num">1</span>
					<div>
						<strong><?php esc_html_e( 'Get your two Meta values', 'dukkan-plugin' ); ?></strong>
						<p><?php esc_html_e( 'Open your Meta app (or create one) and your system-user token — the buttons below take you straight there.', 'dukkan-plugin' ); ?></p>
						<p>
							<a class="button" target="_blank" rel="noopener" href="https://developers.facebook.com/apps"><?php esc_html_e( 'Create Meta App + Phone Number', 'dukkan-plugin' ); ?></a>
							<a class="button" target="_blank" rel="noopener" href="https://business.facebook.com/settings/system-users"><?php esc_html_e( 'Get Access Token', 'dukkan-plugin' ); ?></a>
						</p>
					</div>
				</div>
				<div class="dukkan-whatsapp-steps__item">
					<span class="dukkan-whatsapp-steps__num">2</span>
					<div>
						<strong><?php esc_html_e( 'Paste the two values below and save', 'dukkan-plugin' ); ?></strong>
						<p><?php esc_html_e( 'Phone Number ID and Access token. Your verify token is generated for you.', 'dukkan-plugin' ); ?></p>
					</div>
				</div>
				<div class="dukkan-whatsapp-steps__item">
					<span class="dukkan-whatsapp-steps__num">3</span>
					<div>
						<strong><?php esc_html_e( 'Subscribe the webhook in Meta', 'dukkan-plugin' ); ?></strong>
						<p><?php esc_html_e( 'In Meta → WhatsApp → Configuration → Webhook, paste the Callback URL and Verify token below, then click Verify and save.', 'dukkan-plugin' ); ?></p>
					</div>
				</div>
			</div>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable WhatsApp', 'dukkan-plugin' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="dukkan_chatbot[whatsapp_enabled]" value="1" <?php checked( ! empty( $settings['whatsapp_enabled'] ), 1 ); ?>>
								<?php esc_html_e( 'Reply to WhatsApp messages with the AI assistant', 'dukkan-plugin' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Phone Number ID', 'dukkan-plugin' ); ?></th>
						<td>
							<input type="text" name="dukkan_chatbot[whatsapp_phone_number_id]" value="<?php echo esc_attr( $settings['whatsapp_phone_number_id'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. 1353064267882401', 'dukkan-plugin' ); ?>">
							<p class="description"><?php esc_html_e( 'Meta App → WhatsApp → API Setup → under "From".', 'dukkan-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Access token', 'dukkan-plugin' ); ?></th>
						<td>
							<input type="password" name="dukkan_chatbot[whatsapp_access_token]" value="<?php echo esc_attr( $settings['whatsapp_access_token'] ); ?>" class="large-text" autocomplete="off">
							<p class="description"><?php esc_html_e( 'System-user token (expiry = Never) with whatsapp_business_messaging + whatsapp_business_management permissions.', 'dukkan-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Webhook URL', 'dukkan-plugin' ); ?></th>
						<td>
							<input type="text" readonly value="<?php echo esc_url( $whatsapp_webhook_url ); ?>" class="large-text dukkan-whatsapp-copy" onclick="this.select()">
							<p class="description"><?php esc_html_e( 'Paste into Meta → WhatsApp → Configuration → Webhook → Callback URL.', 'dukkan-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Verify token', 'dukkan-plugin' ); ?></th>
						<td>
							<input type="text" readonly value="<?php echo esc_attr( $whatsapp_verify_token ); ?>" class="regular-text dukkan-whatsapp-copy" onclick="this.select()">
							<input type="hidden" name="dukkan_chatbot[whatsapp_verify_token]" value="<?php echo esc_attr( $whatsapp_verify_token ); ?>">
							<p class="description"><?php esc_html_e( 'Auto-generated for you — paste the same value into Meta\'s "Verify token" field.', 'dukkan-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'App secret', 'dukkan-plugin' ); ?></th>
						<td>
							<input type="password" name="dukkan_chatbot[whatsapp_app_secret]" value="<?php echo esc_attr( $settings['whatsapp_app_secret'] ); ?>" class="large-text" autocomplete="off" placeholder="<?php esc_attr_e( 'Optional', 'dukkan-plugin' ); ?>">
							<p class="description"><?php esc_html_e( 'Optional — only needed to verify webhook signatures. Meta App → App Settings → Basic → App secret.', 'dukkan-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Session memory', 'dukkan-plugin' ); ?></th>
						<td>
							<div class="dukkan-loyalty-inline">
								<input type="number" min="5" step="1" name="dukkan_chatbot[whatsapp_session_ttl]" value="<?php echo esc_attr( $settings['whatsapp_session_ttl'] ); ?>" class="small-text">
								<span><?php esc_html_e( 'minutes to remember each phone number\'s conversation', 'dukkan-plugin' ); ?></span>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Handoff number', 'dukkan-plugin' ); ?></th>
						<td>
							<input type="text" name="dukkan_chatbot[whatsapp_handoff_number]" value="<?php echo esc_attr( $settings['whatsapp_handoff_number'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( '+962 7X XXX XXXX', 'dukkan-plugin' ); ?>">
							<p class="description"><?php esc_html_e( 'Shown to customers when they ask for a human.', 'dukkan-plugin' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'dukkan-plugin' ); ?></button>
		</p>
	</form>

</div>
