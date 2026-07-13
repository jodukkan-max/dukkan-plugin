<style>
   :root {
      --dukkan-main-d-bg:          #f0f0f5;
      --dukkan-main-d-white:       #ffffff;
      --dukkan-main-d-heading:     #0f1523;
      --dukkan-main-d-body:        #5a6072;
      --dukkan-main-d-accent:      #ff5a00;
      --dukkan-main-d-accent-glow: rgba(255, 90, 0, 0.35);
      --dukkan-main-d-card-bg:     #ffffff;
      --dukkan-main-d-card-shadow: 0 2px 16px rgba(0, 0, 0, 0.06);
      --dukkan-main-d-icon-color:  #9aa0b0;
      --dukkan-main-d-radius:      20px;
    }
 
    /* ── WRAPPER ── */
    .dukkan-main-d-wrapper {
        width: 100%;
        max-width: 1200px;
        padding: 40px 40px 60px;
    }
    .dukkan-main-d-card__icon img {
        width: 100%;
    }
    .dukkan-main-d-card:hover {
        border: 1px solid var(--dukkan-main-d-accent);
    }
    /* ── HERO SECTION ── */
    .dukkan-main-d-hero {
      text-align: center;
      margin-bottom: 60px;
    }
 
    .dukkan-main-d-hero__title {
        font-size: clamp(2rem, 5vw, 2.25rem) !important;
        font-weight: 800 !important;
        color: var(--dukkan-main-d-heading);
        line-height: 1.18 !important;
        letter-spacing: -0.02em;
        margin: 0 auto 22px !important;
    }
 
    .dukkan-main-d-hero__subtitle {
      font-size: clamp(0.95rem, 1.5vw, 1.1rem);
      font-weight: 400;
      color: var(--dukkan-main-d-body);
      line-height: 1.7;
      max-width: 700px;
      margin: 0 auto 40px;
    }
 
    /* ── CTA BUTTON ── */
    .dukkan-main-d-btn {
      display: inline-block;
      padding: 18px 52px;
      background: var(--dukkan-main-d-accent);
      color: #fff;
      font-size: 1rem;
      font-weight: 700;
      border: 1px solid var(--dukkan-main-d-accent);
      border-radius: 15px;
      cursor: pointer;
      text-decoration: none;
      transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
      position: relative;
    }
    .dukkan-main-d-btn-invert {
        display: inline-block;
        padding: 18px 52px;
        color: var(--dukkan-main-d-accent);
        font-size: 1rem;
        font-weight: 700;
        border: 1px solid var(--dukkan-main-d-accent);
        border-radius: 15px;
        cursor: pointer;
        text-decoration: none;
        transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
        position: relative;
    }
 
    /* soft orange glow beneath button */
    .dukkan-main-d-btn::after {
      content: '';
      position: absolute;
      bottom: -18px;
      left: 50%;
      transform: translateX(-50%);
      width: 70%;
      height: 24px;
      background: radial-gradient(ellipse at center, var(--dukkan-main-d-accent-glow) 0%, transparent 75%);
      pointer-events: none;
      border-radius: 50%;
      filter: blur(4px);
    }
 
    .dukkan-main-d-btn:hover {
      transform: translateY(-2px);
      background: var(--dukkan-main-d-accent);
      color: #fff;
    }

    .dukkan-main-d-btn-invert:hover {
      transform: translateY(-2px);
      color: var(--dukkan-main-d-accent);
    }
 
    .dukkan-main-d-btn:active {
      transform: translateY(0);
    }
 
    /* ── FEATURE CARDS STRIP ── */
    .dukkan-main-d-features {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 16px;
      margin-top: 72px;
    }
 
    .dukkan-main-d-card {
      background: var(--dukkan-main-d-card-bg);
      border-radius: var(--dukkan-main-d-radius);
      padding: 32px 16px 28px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 18px;
      box-shadow: var(--dukkan-main-d-card-shadow);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
 
    .dukkan-main-d-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 28px rgba(0, 0, 0, 0.10);
    }
 
    .dukkan-main-d-card__icon {
      width: 48px;
      height: 48px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--dukkan-main-d-icon-color);
    }
 
    .dukkan-main-d-card__icon svg {
      width: 28px;
      height: 28px;
      stroke: currentColor;
      fill: none;
      stroke-width: 1.6;
      stroke-linecap: round;
      stroke-linejoin: round;
    }
 
    .dukkan-main-d-card__label {
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--dukkan-main-d-heading);
      text-align: center;
      line-height: 1.4;
      letter-spacing: 0.01em;
    }
 
    /* ── RESPONSIVE ── */
    @media (max-width: 900px) {
      .dukkan-main-d-features {
        grid-template-columns: repeat(3, 1fr);
      }
    }
 
    @media (max-width: 540px) {
      .dukkan-main-d-wrapper { padding: 48px 20px 40px; }
      .dukkan-main-d-features {
        grid-template-columns: repeat(2, 1fr);
      }
    }
</style>
<?php
/**
 * ──────────────────────────────────────────────
 *  DUKKAN FEATURE CARDS – DATA ARRAY
 *  Replace the 'icon' values with your actual
 *  icon image URLs before deploying.
 * ──────────────────────────────────────────────
 */
$dukkan_feature_cards = [
    [
        'label' => 'Products Management',
        'icon'  => DUKKAN_PLUGIN_URL . 'admin/images/products.jpeg',
    ],
    [
        'label' => 'Orders Management',
        'icon'  => DUKKAN_PLUGIN_URL . 'admin/images/order.jpeg',
    ],
    [
        'label' => 'Real Time Analytics',
        'icon'  => DUKKAN_PLUGIN_URL . 'admin/images/analytics.jpeg',
    ],
    [
        'label' => 'Coupons & Discounts',
        'icon'  => DUKKAN_PLUGIN_URL . 'admin/images/coupons.jpeg',
    ],
    [
        'label' => 'Ai Translation',
        'icon'  => DUKKAN_PLUGIN_URL . 'admin/images/translation.jpeg',
    ],
    [
        'label' => 'Bulk Product Edit',
        'icon'  => DUKKAN_PLUGIN_URL . 'admin/images/bulk-products-edits.jpeg',
    ],
    [
        'label' => 'Drag & Drop Categories',
        'icon'  => DUKKAN_PLUGIN_URL . 'admin/images/categories.jpeg',
    ],
    [
        'label' => 'Stock Management',
        'icon'  => DUKKAN_PLUGIN_URL . 'admin/images/inventory-management-barcode.jpeg',
    ],
    [
        'label' => 'Tags',
        'icon'  => DUKKAN_PLUGIN_URL . 'admin/images/tags.jpeg',
    ],
    [
        'label' => 'Attributes',
        'icon'  => DUKKAN_PLUGIN_URL . 'admin/images/attributes.jpeg',
    ],
    [
        'label' => 'Product Addons',
        'icon'  => DUKKAN_PLUGIN_URL . 'admin/images/product-addons.jpeg',
    ],
    [
        'label' => 'Push Notifications',
        'icon'  => DUKKAN_PLUGIN_URL . 'admin/images/notifications.jpeg',
    ],
];
?>
<div class="dukkan-dashboard">

    <!-- Top Banner -->
    <div class="dukkan-top-banner">
        <?php esc_html_e( 'Manage your WooCommerce store through', 'dukkan-plugin' ); ?> 
        <strong><?php esc_html_e( 'Dukkan Admin App', 'dukkan-plugin' ); ?></strong> 🔥
    </div>


    <div class="dukkan-dashboard-layout">

        <div class="dukkan-main-d-wrapper">
 
            <!-- HERO -->
            <section class="dukkan-main-d-hero">
                <h1 class="dukkan-main-d-hero__title">
                Manage your Woocommerce store<br>from Your Mobile
                </h1>
                <p class="dukkan-main-d-hero__subtitle">
                Dukkan WooCommerce Admin App makes it easy—manage orders, products (using Ai),
                track real-time sales, translate products, product addons faster than ever before.
                </p>
                <a href="#" class="dukkan-main-d-btn">Download The App</a>
                <a href="#" class="dukkan-main-d-btn-invert">Product addons</a>
            </section>
 
            <!-- FEATURE CARDS -->
             <!-- FEATURE CARDS – rendered dynamically from $dukkan_feature_cards -->
            <div class="dukkan-main-d-features">
                <?php foreach ($dukkan_feature_cards as $card) : ?>
                <div class="dukkan-main-d-card">
                    <div class="dukkan-main-d-card__icon">
                    <img
                        src="<?php echo esc_attr($card['icon']); ?>"
                        alt="<?php echo esc_attr($card['label']); ?> icon"
                        loading="lazy"
                    />
                    </div>
                    <span class="dukkan-main-d-card__label">
                    <?php echo esc_html($card['label']); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        
        </div>

    </div>

</div>