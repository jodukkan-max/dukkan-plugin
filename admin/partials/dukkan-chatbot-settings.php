<?php
/**
 * AI Chatbot settings — native WordPress form-table layout.
 *
 * @since 1.0.27
 *
 * @var array $settings Chatbot settings merged with defaults.
 * @var array $index    Index status (count, last_build).
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
				<p><?php esc_html_e( 'A DeepSeek-powered store assistant that helps customers find products, track orders, and check their loyalty points.', 'dukkan-plugin' ); ?></p>
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
				<h2><?php esc_html_e( 'Connection', 'dukkan-plugin' ); ?></h2>
				<p><?php esc_html_e( 'API keys are stored on your server and never exposed to visitors.', 'dukkan-plugin' ); ?></p>
			</div>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'DeepSeek API key', 'dukkan-plugin' ); ?></th>
						<td>
							<input type="password" name="dukkan_chatbot[deepseek_api_key]" value="<?php echo esc_attr( $settings['deepseek_api_key'] ); ?>" class="regular-text" autocomplete="off">
							<p class="description"><?php esc_html_e( 'Used for chat generation.', 'dukkan-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'DeepSeek model', 'dukkan-plugin' ); ?></th>
						<td>
							<select name="dukkan_chatbot[deepseek_model]">
								<option value="deepseek-chat" <?php selected( $settings['deepseek_model'], 'deepseek-chat' ); ?>><?php esc_html_e( 'deepseek-chat (fast)', 'dukkan-plugin' ); ?></option>
								<option value="deepseek-reasoner" <?php selected( $settings['deepseek_model'], 'deepseek-reasoner' ); ?>><?php esc_html_e( 'deepseek-reasoner (deeper reasoning)', 'dukkan-plugin' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'OpenAI API key', 'dukkan-plugin' ); ?></th>
						<td>
							<input type="password" name="dukkan_chatbot[openai_api_key]" value="<?php echo esc_attr( $settings['openai_api_key'] ); ?>" class="regular-text" autocomplete="off">
							<p class="description"><?php esc_html_e( 'Used for semantic product search (embeddings).', 'dukkan-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Test connection', 'dukkan-plugin' ); ?></th>
						<td>
							<button type="button" class="button" id="dukkan-chatbot-test"><?php esc_html_e( 'Test connection', 'dukkan-plugin' ); ?></button>
							<span id="dukkan-chatbot-test-result"></span>
						</td>
					</tr>
				</tbody>
			</table>
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
				<p><?php esc_html_e( 'The assistant searches your products using semantic (meaning-based) search.', 'dukkan-plugin' ); ?></p>
			</div>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Index status', 'dukkan-plugin' ); ?></th>
						<td>
							<p>
								<strong id="dukkan-chatbot-index-count"><?php echo esc_html( $index['count'] ); ?></strong>
								<?php esc_html_e( 'products indexed', 'dukkan-plugin' ); ?>
								<?php if ( $index['last_build'] ) : ?>
									— <?php esc_html_e( 'last build', 'dukkan-plugin' ); ?> <?php echo esc_html( $index['last_build'] ); ?>
								<?php endif; ?>
							</p>
							<button type="button" class="button" id="dukkan-chatbot-rebuild"><?php esc_html_e( 'Rebuild index now', 'dukkan-plugin' ); ?></button>
							<span id="dukkan-chatbot-rebuild-result"></span>
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
						<th scope="row"><?php esc_html_e( 'Add to cart', 'dukkan-plugin' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="dukkan_chatbot[enable_add_to_cart]" value="1" <?php checked( ! empty( $settings['enable_add_to_cart'] ), 1 ); ?>>
								<?php esc_html_e( 'Allow the assistant to add products to the cart', 'dukkan-plugin' ); ?>
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
					<tr>
						<th scope="row"><?php esc_html_e( 'Rate limit', 'dukkan-plugin' ); ?></th>
						<td>
							<div class="dukkan-loyalty-inline">
								<input type="number" min="0" step="1" name="dukkan_chatbot[rate_limit]" value="<?php echo esc_attr( $settings['rate_limit'] ); ?>" class="small-text">
								<span><?php esc_html_e( 'messages per minute per visitor (0 = unlimited)', 'dukkan-plugin' ); ?></span>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Memory', 'dukkan-plugin' ); ?></th>
						<td>
							<select name="dukkan_chatbot[memory_mode]">
								<option value="session" <?php selected( $settings['memory_mode'], 'session' ); ?>><?php esc_html_e( 'Session only (in-browser)', 'dukkan-plugin' ); ?></option>
								<option value="persistent" <?php selected( $settings['memory_mode'], 'persistent' ); ?>><?php esc_html_e( 'Persistent (remember logged-in customers across visits)', 'dukkan-plugin' ); ?></option>
							</select>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'dukkan-plugin' ); ?></button>
		</p>
	</form>

	<div class="dukkan-loyalty-card">
		<div class="dukkan-loyalty-card__head">
			<h2><?php esc_html_e( 'Conversation log', 'dukkan-plugin' ); ?></h2>
			<p><?php esc_html_e( 'Recent conversations handled by the assistant.', 'dukkan-plugin' ); ?></p>
		</div>
		<div id="dukkan-chatbot-logs"></div>
	</div>

</div>
