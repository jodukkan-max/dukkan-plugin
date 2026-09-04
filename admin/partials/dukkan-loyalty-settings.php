<?php
/**
 * Loyalty Points settings — native WordPress form-table layout.
 *
 * @since 1.0.25
 *
 * @var array $settings Loyalty settings merged with defaults.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$currency_symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';

/**
 * Render pre-selected combo tags for the exclusion pickers.
 *
 * @param array  $ids  Term/post IDs.
 * @param string $type 'product' or 'category'.
 */
function dukkan_loyalty_render_tags( $ids, $type ) {
	foreach ( (array) $ids as $id ) {
		$id   = (int) $id;
		$name = '';

		if ( 'product' === $type ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
			if ( $product ) {
				$name = $product->get_name();
			}
		} else {
			$term = get_term( $id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$name = $term->name;
			}
		}

		if ( '' === $name ) {
			continue;
		}
		?>
		<span class="wpldp-combo-tag">
			<span class="wpldp-combo-tag-text"><?php echo esc_html( $name ); ?></span>
			<button type="button" class="wpldp-combo-tag-remove" data-id="<?php echo esc_attr( $id ); ?>">&times;</button>
		</span>
		<input type="hidden" class="wpldp-combo-hidden" name="dukkan_loyalty[excluded_<?php echo esc_attr( 'product' === $type ? 'product' : 'category' ); ?>_ids][]" value="<?php echo esc_attr( $id ); ?>">
		<?php
	}
}
?>
<div class="wrap dukkan-loyalty-settings">

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Loyalty Points settings saved.', 'dukkan-plugin' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dukkan-loyalty-form">
		<input type="hidden" name="action" value="dukkan_loyalty_save_settings">
		<?php wp_nonce_field( 'dukkan_loyalty_settings', 'dukkan_loyalty_settings_nonce' ); ?>

	<div class="dukkan-loyalty-master">
		<div class="dukkan-loyalty-master__text">
			<h2><?php esc_html_e( 'Loyalty Points', 'dukkan-plugin' ); ?></h2>
			<p><?php esc_html_e( 'Reward customers for the amount they spend and let them redeem points at checkout.', 'dukkan-plugin' ); ?></p>
		</div>
		<div class="dukkan-loyalty-master__control">
			<span class="dukkan-loyalty-master__status<?php echo ! empty( $settings['enabled'] ) ? ' is-active' : ''; ?>" data-status-text>
				<?php echo ! empty( $settings['enabled'] ) ? esc_html__( 'Active', 'dukkan-plugin' ) : esc_html__( 'Inactive', 'dukkan-plugin' ); ?>
			</span>
			<label class="dukkan-loyalty-master__switch">
				<input type="checkbox" name="dukkan_loyalty[enabled]" value="1" data-master-toggle <?php checked( ! empty( $settings['enabled'] ), 1 ); ?>>
				<span class="dukkan-loyalty-master__slider"></span>
			</label>
		</div>
	</div>

		<div class="dukkan-loyalty-card">
			<div class="dukkan-loyalty-card__head">
				<h2><?php esc_html_e( 'Earning & redemption', 'dukkan-plugin' ); ?></h2>
				<p><?php esc_html_e( 'Configure how points are earned and how much they are worth.', 'dukkan-plugin' ); ?></p>
			</div>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Earning rate', 'dukkan-plugin' ); ?></th>
						<td>
							<div class="dukkan-loyalty-inline">
								<span><?php esc_html_e( 'Earn', 'dukkan-plugin' ); ?></span>
								<input type="number" min="1" step="1" name="dukkan_loyalty[points_per_amount]" value="<?php echo esc_attr( $settings['points_per_amount'] ); ?>" class="small-text">
								<span><?php esc_html_e( 'points for every', 'dukkan-plugin' ); ?></span>
								<input type="number" min="0.01" step="0.01" name="dukkan_loyalty[amount]" value="<?php echo esc_attr( (string) $settings['amount'] ); ?>" class="small-text">
								<span><?php echo esc_html( $currency_symbol ); ?></span>
								<span><?php esc_html_e( 'spent', 'dukkan-plugin' ); ?></span>
							</div>
							<p class="description"><?php esc_html_e( 'Points are awarded when an order reaches "Completed". Shipping, tax, excluded items and the portion paid with points are not counted.', 'dukkan-plugin' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Redemption value', 'dukkan-plugin' ); ?></th>
						<td>
							<div class="dukkan-loyalty-inline">
								<span>1 <?php esc_html_e( 'point =', 'dukkan-plugin' ); ?></span>
								<input type="number" min="0.0001" step="0.0001" name="dukkan_loyalty[value_per_point]" value="<?php echo esc_attr( (string) $settings['value_per_point'] ); ?>" class="small-text">
								<span><?php echo esc_html( $currency_symbol ); ?></span>
							</div>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Maximum redemption', 'dukkan-plugin' ); ?></th>
						<td>
							<div class="dukkan-loyalty-inline">
								<input type="number" min="0" max="100" step="1" name="dukkan_loyalty[max_redeem_percent]" value="<?php echo esc_attr( $settings['max_redeem_percent'] ); ?>" class="small-text">
								<span>%</span>
								<span><?php esc_html_e( 'of the cart subtotal', 'dukkan-plugin' ); ?></span>
							</div>
							<p class="description"><?php esc_html_e( 'Caps how much of an order points can cover. Use 100 to allow full coverage.', 'dukkan-plugin' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Coupon stacking', 'dukkan-plugin' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="dukkan_loyalty[redeem_with_coupons]" value="1" <?php checked( ! empty( $settings['redeem_with_coupons'] ), 1 ); ?>>
								<?php esc_html_e( 'Allow points to be combined with a coupon code', 'dukkan-plugin' ); ?>
							</label>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="dukkan-loyalty-card">
			<div class="dukkan-loyalty-card__head">
				<h2><?php esc_html_e( 'Excluded products & categories', 'dukkan-plugin' ); ?></h2>
				<p><?php esc_html_e( 'Customers do not earn points on these products or categories.', 'dukkan-plugin' ); ?></p>
			</div>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Excluded products', 'dukkan-plugin' ); ?></th>
						<td>
							<div class="wpldp-combo" data-combo="loyalty_products" data-name="dukkan_loyalty[excluded_product_ids][]">
								<div class="wpldp-combo-control">
									<input type="text" class="wpldp-combo-input" placeholder="<?php esc_attr_e( 'Search for products…', 'dukkan-plugin' ); ?>" autocomplete="off">
									<span class="dashicons dashicons-arrow-down-alt2 wpldp-combo-caret"></span>
								</div>
								<div class="wpldp-combo-menu"></div>
							</div>
							<div class="wpldp-combo-tags<?php echo empty( $settings['excluded_product_ids'] ) ? '' : ' is-visible'; ?>" data-tags-for="loyalty_products">
								<?php dukkan_loyalty_render_tags( $settings['excluded_product_ids'], 'product' ); ?>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Excluded categories', 'dukkan-plugin' ); ?></th>
						<td>
							<div class="wpldp-combo" data-combo="loyalty_categories" data-name="dukkan_loyalty[excluded_category_ids][]">
								<div class="wpldp-combo-control">
									<input type="text" class="wpldp-combo-input" placeholder="<?php esc_attr_e( 'Search categories…', 'dukkan-plugin' ); ?>" autocomplete="off">
									<span class="dashicons dashicons-arrow-down-alt2 wpldp-combo-caret"></span>
								</div>
								<div class="wpldp-combo-menu"></div>
							</div>
							<div class="wpldp-combo-tags<?php echo empty( $settings['excluded_category_ids'] ) ? '' : ' is-visible'; ?>" data-tags-for="loyalty_categories">
								<?php dukkan_loyalty_render_tags( $settings['excluded_category_ids'], 'category' ); ?>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'dukkan-plugin' ); ?></button>
		</p>
	</form>

	<div class="dukkan-loyalty-card dukkan-loyalty-lookup">
		<div class="dukkan-loyalty-card__head">
			<h2><?php esc_html_e( 'Customer balance', 'dukkan-plugin' ); ?></h2>
			<p><?php esc_html_e( 'Search for a customer to view their points balance and adjust points.', 'dukkan-plugin' ); ?></p>
		</div>

		<div class="wpldp-combo dukkan-loyalty-customer-combo" data-combo="loyalty_customers">
			<div class="wpldp-combo-control">
				<input type="text" class="wpldp-combo-input" placeholder="<?php esc_attr_e( 'Search customers by name or email…', 'dukkan-plugin' ); ?>" autocomplete="off">
				<span class="dashicons dashicons-arrow-down-alt2 wpldp-combo-caret"></span>
			</div>
			<div class="wpldp-combo-menu"></div>
		</div>

		<div id="dukkan-loyalty-lookup-result"></div>

		<div class="dukkan-loyalty-adjust" id="dukkan-loyalty-adjust" style="display:none;">
			<div class="dukkan-loyalty-adjust__head">
				<h3><?php esc_html_e( 'Adjust points', 'dukkan-plugin' ); ?></h3>
			</div>
			<div class="dukkan-loyalty-adjust__row">
				<input type="number" min="1" step="1" id="dukkan-loyalty-adjust-points" class="small-text" placeholder="<?php esc_attr_e( 'Points', 'dukkan-plugin' ); ?>">
				<input type="text" id="dukkan-loyalty-adjust-note" class="regular-text" placeholder="<?php esc_attr_e( 'Reason (optional)', 'dukkan-plugin' ); ?>">
				<button type="button" class="button button-primary" id="dukkan-loyalty-adjust-add"><?php esc_html_e( 'Add points', 'dukkan-plugin' ); ?></button>
				<button type="button" class="button" id="dukkan-loyalty-adjust-deduct"><?php esc_html_e( 'Deduct points', 'dukkan-plugin' ); ?></button>
			</div>
		</div>
	</div>

</div>
