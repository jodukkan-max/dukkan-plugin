# Dukkan Plugin — Work Log & Structure

> Last updated: v1.0.25 — September 2, 2026

---

## Recent Changes

### v1.0.25 — Loyalty Points + add-on price display + update-check cache

- **Loyalty Points** (new feature): earn points per amount spent, redeem at checkout ("pay with points"), My Account balance display, admin-configurable points value & earn rate, earning on net amount, discount applied before tax, product/category exclusions, coupon-stacking rule, and a master enable switch.
  - Admin: `admin/class-dukkan-plugin-loyalty-admin.php`, `admin/partials/dukkan-loyalty-settings.php`, `admin/css/dp-loyalty.css`, `admin/js/dp-loyalty.js`
  - Public: `public/class-dukkan-plugin-loyalty-public.php`, `public/js/dukkan-plugin-loyalty.js`, `public/css/dukkan-plugin-loyalty.css`
  - Core/API: `includes/class-dukkan-plugin-loyalty.php`, `api/class-dukkan-plugin-loyalty-api.php`, `api/loyalty-points-api-reference.json`, `LOYALTY-POINTS-API-CURL.md`
- **Product Add-Ons — live price display**: removed the "Addons Total / Total" summary bar; the product's own price (regular or sale) now updates live to include the selected add-on value. `public/class-product-addon.php`, `public/js/dukkan-plugin-product-addon.js`, `public/css/dukkan-plugin-product-addon.css`
- **Update checker caching**: `version.json` is now cached in a transient (4-hour expiry) with an in-request guard, so the update check no longer fires repeated live GitHub requests on every admin page load. `includes/class-dukkan-plugin-updater.php`
- **Product Add-Ons combo fix**: scoped the products/categories search combo to its own `data-combo` types so the Badge and Loyalty scripts no longer overwrite the category dropdown with product names. `admin/js/dp-product-addon.js`, `admin/js/dp-badge.js`, `admin/js/dp-loyalty.js`

### v1.0.22 — Manual updates (auto-update removed)

- Removed the daily WP-Cron self-update and the background `dukkan/v1/update` REST endpoint.
- Removed the 12-hour `version.json` cache; the update UI now reads `version.json` live.
- Updates are applied only via the WordPress "update now" button (or WP auto-updates if enabled).

### v1.0.5 — Slim SEO API Bridge

Two write-only REST endpoints under `dukkan-seo/v1` that let the mobile app set Slim SEO meta titles and descriptions on posts (including products).

| Method | Route | Purpose |
|--------|-------|---------|
| PUT | `/posts/{id}/title` | Set the Slim SEO meta title |
| PUT | `/posts/{id}/description` | Set the Slim SEO meta description |

- Works on any post type (posts, pages, products, custom).
- Each endpoint modifies only the targeted field. All other Slim SEO meta (OG/Twitter images, canonical URL, noindex) is preserved.
- The API class (`api/class-dukkan-plugin-slim-seo-api.php`) only loads when Slim SEO is active — guarded by `class_exists('SlimSEO\Container')`.
- API reference for app developers: `api/slim-seo-api-reference.json`

### v1.0.4 — Async Background Self-Updater

A daily WP-Cron event (4 AM Amman time / 1 AM UTC) checks `version.json` on GitHub. If a newer version exists, it fires a non-blocking async REST request (`blocking=false`) to a protected internal endpoint (`dukkan/v1/update`) that performs the download and install in a separate PHP process. Zero visitor delay.

- Cron schedule: `dukkan_plugin_daily_update_check` hook, daily at 1 AM UTC.
- Background endpoint authenticated with an auto-generated bearer token.
- Also hooks into WordPress native update UI as a bonus.
- API class: `includes/class-dukkan-plugin-updater.php`

### v1.0.1–1.0.2 — Performance Optimizations

Six performance fixes targeting unnecessary queries and asset loads on every page:

| # | Issue | Fix | File |
|---|-------|-----|------|
| 1 | `dukkan_custom_order_statuses` option not autoloaded | Changed `add_option()` autoload param to `yes` | `includes/class-dukkan-plugin-activator.php` |
| 2 | Repeated `get_option()` calls for user statuses | Added static cache (`self::$cached_statuses`) + `get_user_statuses()` helper | `admin/class-dukkan-plugin-woocommerce.php` |
| 3 | Public CSS loaded on every page | Guard `enqueue_styles()` with `is_product()` | `public/class-dukkan-plugin-public.php` |
| 4 | Public JS loaded on every page | Guard `enqueue_scripts()` with `is_product()` | `public/class-dukkan-plugin-public.php` |
| 5 | Product-addon CSS loaded on every page | Guard `enqueue_styles()` with `is_product()` | `public/class-product-addon.php` |
| 6 | Product-addon JS loaded on every page | Guard `enqueue_scripts()` with `is_product()` | `public/class-product-addon.php` |

Also: TranslatePress API class now conditionally loaded only when `TRP_Translate_Press` class exists (`includes/class-dukkan-plugin.php`).

---

## Plugin Update Mechanism (manual)

No wordpress.org hosting required. The plugin reads `version.json` from the repo root and injects the latest release into WordPress's native update UI:

```json
{
  "version": "1.0.22",
  "package": "https://github.com/jodukkan-max/dukkan-plugin/releases/download/v1.0.22/dukkan-plugin.zip",
  "requires": "5.0",
  "tested": "6.6"
}
```

**Release workflow:**

1. Build the ZIP: `zip -r dukkan-plugin.zip dukkan-plugin -x "dukkan-plugin/.git/*" "*.DS_Store" "*.backup"`
2. Bump version in `dukkan-plugin.php` (header comment + `DUKKAN_PLUGIN_VERSION` constant)
3. Bump version and package URL in `version.json`
4. Commit and push
5. Create a GitHub Release with the same tag (`vX.Y.Z`) and attach the ZIP

Updates are applied manually by the admin via the "update now" button on the Plugins screen (or WordPress auto-updates if enabled). There is no scheduled cron and no automatic background install.

---

## Project Structure

```
dukkan-plugin/
├── dukkan-plugin.php              # Bootstrap — constants, activation, updater init
├── version.json                   # Version manifest read by the update UI
├── index.php                      # Silence is golden
├── uninstall.php                  # Cleanup on uninstall
├── README.txt                     # Plugin readme
├── LICENSE.txt                    # GPL v2
├── ARCHITECTURE.md                # Living architecture document
├── dev-notes.md                   # Order status feature dev notes
├── notes.md                       # This file
│
├── includes/                      # Core plugin infrastructure
│   ├── class-dukkan-plugin.php            # Main plugin class — loads deps, defines hooks
│   ├── class-dukkan-plugin-activator.php  # Activation routine, seeds defaults
│   ├── class-dukkan-plugin-deactivator.php
│   ├── class-dukkan-plugin-i18n.php       # Internationalization
│   ├── class-dukkan-plugin-loader.php     # Hook orchestrator
│   └── class-dukkan-plugin-updater.php    # Update notification (native WP update UI)
│
├── admin/                         # WordPress admin area
│   ├── class-dukkan-plugin-admin.php
│   ├── class-dukkan-plugin-woocommerce.php  # Custom order statuses + WC integration
│   ├── class-dukkan-plugin-order-status.php # Order status CRUD (AJAX)
│   ├── class-dukkan-plugin-product-addon.php
│   ├── css/
│   ├── js/
│   ├── images/
│   └── partials/                  # Admin page templates
│
├── public/                        # Front-end facing
│   ├── class-dukkan-plugin-public.php
│   ├── class-product-addon.php
│   ├── css/
│   ├── js/
│   └── partials/
│
├── api/                           # REST API endpoints
│   ├── class-dukkan-plugin-general.php              # General utilities
│   ├── class-dukkan-plugin-order-status-api.php     # Order status CRUD
│   ├── class-dukkan-plugin-product-addon-api.php    # Product addons
│   ├── class-dukkan-plugin-translatepress.php       # TranslatePress integration
│   ├── class-dukkan-plugin-dynamic-pricing-api.php  # WCDPD bridge (6 endpoints)
│   ├── class-dukkan-plugin-slim-seo-api.php         # Slim SEO bridge (2 endpoints, v1.0.5)
│   ├── dynamic-pricing-api-reference.json           # App dev reference — dynamic pricing
│   ├── slim-seo-api-reference.json                  # App dev reference — Slim SEO
│   ├── dynamic-pricing-notes.md
│   ├── dynamic-pricing-api-reference.json
│   ├── woo-extended/
│   │   └── class-dukkan-woo-extended-api.php
│   └── webhook/woo/
│       └── class-dukkan-woo-webhook.php
│
└── languages/                     # Translation files
    └── dukkan-plugin.pot
```

---

## Existing REST API Endpoints (All)

| Namespace | Route | Methods |
|-----------|-------|---------|
| `dukkan-order-status/v1` | `/statuses`, `/statuses/{id}` | GET, POST, PUT, DELETE |
| `dukkan-dynamic-pricing/v1` | `/rules`, `/rules/{uid}` | GET, POST, PUT, DELETE |
| `dukkan-dynamic-pricing/v1` | `/products/search` | GET |
| `dukkan-seo/v1` | `/posts/{id}/title` | PUT |
| `dukkan-seo/v1` | `/posts/{id}/description` | PUT |

All endpoints are publicly accessible (`__return_true` permission callback).
