# Dukkan Plugin — Work Log & Structure

> Last updated: v1.0.5 — July 8, 2026

---

## Recent Changes

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

## Plugin Self-Update Mechanism

No wordpress.org hosting required. The updater file is `version.json` at the repo root:

```json
{
  "version": "1.0.5",
  "package": "https://github.com/jodukkan-max/dukkan-plugin/releases/download/v1.0.5/dukkan-plugin-v1.0.5.zip",
  "requires": "5.0",
  "tested": "6.6"
}
```

**Release workflow:**

1. Build the ZIP: `zip -r dukkan-plugin-vX.Y.Z.zip dukkan-plugin -x "dukkan-plugin/.git/*" ...`
2. Bump version in `dukkan-plugin.php` (header comment + `DUKKAN_PLUGIN_VERSION` constant)
3. Bump version and package URL in `version.json`
4. Commit and push
5. Create a GitHub Release with the same tag (`vX.Y.Z`) and attach the ZIP

Sites running v1.0.4+ will auto-update at 4 AM without any user action.

**To seed the updater on old sites:** Upload the latest ZIP (`dukkan-plugin-v1.0.5.zip`) manually once per site. From that point on, the site auto-updates.

---

## Project Structure

```
dukkan-plugin/
├── dukkan-plugin.php              # Bootstrap — constants, activation, updater init
├── version.json                   # Single source of truth for auto-updater
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
│   └── class-dukkan-plugin-updater.php    # Self-updater (cron + async background)
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
| `dukkan/v1` | `/update` | POST (internal, token-protected) |

All endpoints except `/update` are publicly accessible (`__return_true` permission callback).
