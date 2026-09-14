<?php
/**
 * Dukkan Mobile — promotional dashboard.
 *
 * PhotoRoom-inspired landing: bold hero with a phone mockup, a social-proof
 * stats row, a feature grid with real descriptions, a three-step "how it
 * works" band, and a closing CTA. Styles live in
 * admin/css/dukkan-plugin-admin.css under the `.dukkan-mobile-*` namespace.
 *
 * Replace the store links below with the live App Store / Google Play URLs.
 */
$dukkan_store_links = array(
	'app_store'  => 'https://dukkanjo.com',
	'google_play' => 'https://dukkanjo.com',
	'cta'         => 'https://dukkanjo.com',
);

$dukkan_features = array(
	array(
		'icon'  => 'products.jpeg',
		'title' => __( 'Products Management', 'dukkan-plugin' ),
		'desc'  => __( 'Create, edit and publish products with AI — right from your phone.', 'dukkan-plugin' ),
	),
	array(
		'icon'  => 'order.jpeg',
		'title' => __( 'Orders Management', 'dukkan-plugin' ),
		'desc'  => __( 'Process, fulfil and track orders on the go, wherever you are.', 'dukkan-plugin' ),
	),
	array(
		'icon'  => 'analytics.jpeg',
		'title' => __( 'Real-time Analytics', 'dukkan-plugin' ),
		'desc'  => __( 'Live sales dashboards and reports so you always know how you’re doing.', 'dukkan-plugin' ),
	),
	array(
		'icon'  => 'coupons.jpeg',
		'title' => __( 'Coupons & Discounts', 'dukkan-plugin' ),
		'desc'  => __( 'Create and manage discount codes that convert, in a few taps.', 'dukkan-plugin' ),
	),
	array(
		'icon'  => 'translation.jpeg',
		'title' => __( 'AI Translation', 'dukkan-plugin' ),
		'desc'  => __( 'Translate your whole catalog into any language instantly.', 'dukkan-plugin' ),
	),
	array(
		'icon'  => 'bulk-products-edits.jpeg',
		'title' => __( 'Bulk Product Edit', 'dukkan-plugin' ),
		'desc'  => __( 'Update prices, stock and details across hundreds of products at once.', 'dukkan-plugin' ),
	),
	array(
		'icon'  => 'categories.jpeg',
		'title' => __( 'Drag & Drop Categories', 'dukkan-plugin' ),
		'desc'  => __( 'Organize your catalog visually with simple drag-and-drop.', 'dukkan-plugin' ),
	),
	array(
		'icon'  => 'inventory-management-barcode.jpeg',
		'title' => __( 'Stock Management', 'dukkan-plugin' ),
		'desc'  => __( 'Track inventory levels and get low-stock alerts before you sell out.', 'dukkan-plugin' ),
	),
	array(
		'icon'  => 'tags.jpeg',
		'title' => __( 'Tags', 'dukkan-plugin' ),
		'desc'  => __( 'Keep products organized and discoverable with tags.', 'dukkan-plugin' ),
	),
	array(
		'icon'  => 'attributes.jpeg',
		'title' => __( 'Attributes', 'dukkan-plugin' ),
		'desc'  => __( 'Manage sizes, colors and variants with full control.', 'dukkan-plugin' ),
	),
	array(
		'icon'  => 'product-addons.jpeg',
		'title' => __( 'Product Add-ons', 'dukkan-plugin' ),
		'desc'  => __( 'Offer extra options that boost every order’s value.', 'dukkan-plugin' ),
	),
	array(
		'icon'  => 'notifications.jpeg',
		'title' => __( 'Push Notifications', 'dukkan-plugin' ),
		'desc'  => __( 'Get instant alerts for new orders, reviews and low stock.', 'dukkan-plugin' ),
	),
);
?>
<div class="dukkan-mobile">

	<!-- ===================== HERO ===================== -->
	<section class="dukkan-mobile__hero">
		<div class="dukkan-mobile__hero-grid">
			<div class="dukkan-mobile__hero-copy">
				<span class="dukkan-mobile__badge">
					<img src="<?php echo esc_url( DUKKAN_PLUGIN_URL . 'admin/images/dukkan-logo.png' ); ?>" alt="" class="dukkan-mobile__badge-logo">
					<?php esc_html_e( 'Dukkan Admin App', 'dukkan-plugin' ); ?>
				</span>

				<h1 class="dukkan-mobile__title">
					<?php esc_html_e( 'Run your WooCommerce store', 'dukkan-plugin' ); ?>
					<span class="dukkan-mobile__title-accent"><?php esc_html_e( 'from your pocket', 'dukkan-plugin' ); ?></span>
				</h1>

				<p class="dukkan-mobile__subtitle">
					<?php esc_html_e( 'Dukkan turns your phone into a full store command center — manage orders, publish products with AI, translate your catalog and watch sales in real time, wherever you are.', 'dukkan-plugin' ); ?>
				</p>

				<div class="dukkan-mobile__stores">
					<a href="<?php echo esc_url( $dukkan_store_links['app_store'] ); ?>" class="dukkan-mobile__store" target="_blank" rel="noopener">
						<span class="dukkan-mobile__store-icon" aria-hidden="true"></span>
						<span class="dukkan-mobile__store-text">
							<small><?php esc_html_e( 'Download on the', 'dukkan-plugin' ); ?></small>
							<strong><?php esc_html_e( 'App Store', 'dukkan-plugin' ); ?></strong>
						</span>
					</a>
					<a href="<?php echo esc_url( $dukkan_store_links['google_play'] ); ?>" class="dukkan-mobile__store" target="_blank" rel="noopener">
						<span class="dukkan-mobile__store-icon dukkan-mobile__store-icon--play" aria-hidden="true"></span>
						<span class="dukkan-mobile__store-text">
							<small><?php esc_html_e( 'Get it on', 'dukkan-plugin' ); ?></small>
							<strong><?php esc_html_e( 'Google Play', 'dukkan-plugin' ); ?></strong>
						</span>
					</a>
				</div>

				<ul class="dukkan-mobile__proof">
					<li><?php esc_html_e( 'AI-powered', 'dukkan-plugin' ); ?></li>
					<li><?php esc_html_e( 'Real-time', 'dukkan-plugin' ); ?></li>
					<li><?php esc_html_e( 'Made for WooCommerce', 'dukkan-plugin' ); ?></li>
				</ul>
			</div>

			<div class="dukkan-mobile__hero-media">
				<div class="dukkan-mobile__phone">
					<div class="dukkan-mobile__phone-notch"></div>
					<div class="dukkan-mobile__phone-screen">
						<div class="dukkan-mobile__phone-bar">
							<span class="dukkan-mobile__phone-logo"><?php esc_html_e( 'Dukkan', 'dukkan-plugin' ); ?></span>
							<span class="dukkan-mobile__phone-avatar"></span>
						</div>
						<div class="dukkan-mobile__phone-hello">
							<strong><?php esc_html_e( 'Good morning', 'dukkan-plugin' ); ?></strong>
							<span><?php esc_html_e( 'Here’s your store today', 'dukkan-plugin' ); ?></span>
						</div>
						<div class="dukkan-mobile__phone-stats">
							<div class="dukkan-mobile__phone-stat">
								<strong>1,284</strong>
								<span><?php esc_html_e( 'Products', 'dukkan-plugin' ); ?></span>
							</div>
							<div class="dukkan-mobile__phone-stat">
								<strong>96</strong>
								<span><?php esc_html_e( 'Orders', 'dukkan-plugin' ); ?></span>
							</div>
						</div>
						<div class="dukkan-mobile__phone-row">
							<span class="dukkan-mobile__phone-dot is-green"></span>
							<div>
								<strong><?php esc_html_e( 'New order', 'dukkan-plugin' ); ?></strong>
								<small>$124.99</small>
							</div>
						</div>
						<div class="dukkan-mobile__phone-row">
							<span class="dukkan-mobile__phone-dot is-amber"></span>
							<div>
								<strong><?php esc_html_e( 'Low stock', 'dukkan-plugin' ); ?></strong>
								<small>3 <?php esc_html_e( 'items left', 'dukkan-plugin' ); ?></small>
							</div>
						</div>
					</div>
				</div>
				<div class="dukkan-mobile__glow" aria-hidden="true"></div>
			</div>
		</div>
	</section>

	<!-- ===================== FEATURES ===================== -->
	<section class="dukkan-mobile__features">
		<div class="dukkan-mobile__section-head">
			<h2><?php esc_html_e( 'Everything you need to run your store', 'dukkan-plugin' ); ?></h2>
			<p><?php esc_html_e( 'Dukkan brings the power of your WooCommerce dashboard into a fast, beautiful mobile app.', 'dukkan-plugin' ); ?></p>
		</div>

		<div class="dukkan-mobile__grid">
			<?php foreach ( $dukkan_features as $feature ) : ?>
				<article class="dukkan-mobile__card">
					<div class="dukkan-mobile__card-icon">
						<img src="<?php echo esc_url( DUKKAN_PLUGIN_URL . 'admin/images/' . $feature['icon'] ); ?>" alt="<?php echo esc_attr( $feature['title'] ); ?>" loading="lazy">
					</div>
					<h3 class="dukkan-mobile__card-title"><?php echo esc_html( $feature['title'] ); ?></h3>
					<p class="dukkan-mobile__card-desc"><?php echo esc_html( $feature['desc'] ); ?></p>
				</article>
			<?php endforeach; ?>
		</div>
	</section>

	<!-- ===================== HOW IT WORKS ===================== -->
	<section class="dukkan-mobile__how">
		<div class="dukkan-mobile__section-head">
			<h2><?php esc_html_e( 'Get started in three steps', 'dukkan-plugin' ); ?></h2>
		</div>
		<div class="dukkan-mobile__steps">
			<div class="dukkan-mobile__step">
				<span class="dukkan-mobile__step-num">1</span>
				<h3><?php esc_html_e( 'Download the app', 'dukkan-plugin' ); ?></h3>
				<p><?php esc_html_e( 'Install Dukkan on iOS or Android — free to start.', 'dukkan-plugin' ); ?></p>
			</div>
			<div class="dukkan-mobile__step">
				<span class="dukkan-mobile__step-num">2</span>
				<h3><?php esc_html_e( 'Connect your store', 'dukkan-plugin' ); ?></h3>
				<p><?php esc_html_e( 'Use the one-time code from the Store OTP tab to link this store.', 'dukkan-plugin' ); ?></p>
			</div>
			<div class="dukkan-mobile__step">
				<span class="dukkan-mobile__step-num">3</span>
				<h3><?php esc_html_e( 'Manage on the go', 'dukkan-plugin' ); ?></h3>
				<p><?php esc_html_e( 'Publish, fulfil and grow — all from the palm of your hand.', 'dukkan-plugin' ); ?></p>
			</div>
		</div>
	</section>

	<!-- ===================== CTA ===================== -->
	<section class="dukkan-mobile__cta">
		<div class="dukkan-mobile__cta-inner">
			<h2><?php esc_html_e( 'Ready to manage your store from anywhere?', 'dukkan-plugin' ); ?></h2>
			<p><?php esc_html_e( 'Join Dukkan and keep your business moving — even when you’re away from your desk.', 'dukkan-plugin' ); ?></p>
			<a href="<?php echo esc_url( $dukkan_store_links['cta'] ); ?>" class="dukkan-mobile__cta-btn" target="_blank" rel="noopener">
				<?php esc_html_e( 'Download the Dukkan App', 'dukkan-plugin' ); ?>
			</a>
		</div>
	</section>

</div>
