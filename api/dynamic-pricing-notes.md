# Dukkan — Complete Developer Reference

> Auto-generated from 5-agent deep analysis.  
> Version: 1.0.1 | Last updated: 2026-07-07

---

## 1. Overview

The Dukkan plugin is a WooCommerce companion plugin that bridges a mobile app to a WordPress store. It provides REST APIs, an admin settings dashboard, and public-facing product add-ons.

**Files:** 24 PHP classes (~260 KB), 2 JS files, 3 CSS files  
**REST endpoints:** 29 across 6 namespaces  
**AJAX handlers:** 18  
**Option keys:** 6  
**Admin tabs:** 4  

---

## 2. Plugin Bootstrapping

### Entry Point (`dukkan-plugin.php`)

```php
define( 'DUKKAN_PLUGIN_VERSION', '1.0.1' );
define( 'DUKKAN_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'DUKKAN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DUKKAN_WOO_EXTENDED_STATIC_API_KEY', 'yuwqeq436473h4h3rh557448384' );
```

- `register_activation_hook` → seeds 3 default order statuses (`with-carrier`, `out-for-delivery`, `hold`)
- `register_deactivation_hook` → empty (no cleanup)
- `run_dukkan_plugin()` → creates `Dukkan_Plugin` → calls `$plugin->run()`

### Core Orchestrator (`includes/class-dukkan-plugin.php`)

Loads 14 files + instantiates 15 classes in this order:

```
dependencies → locale → webhook → woo-extended → general-api → translatepress-api →
product-addon-api → order-status-api → dynamic-pricing-api → admin-hooks → public-hooks
```

**Hook registration pattern:** API classes self-register via `add_action('rest_api_init', ...)` in their constructors.  
Admin/public hooks use the `Dukkan_Plugin_Loader` deferred system (9 hooks).

### Deferred Loader (`includes/class-dukkan-plugin-loader.php`)

Collects hook definitions with `$loader->add_action()` / `$loader->add_filter()`, then calls `$loader->run()` to register them all. Only 9 hooks use this system.

---

## 3. Admin Dashboard (4 tabs)

| Tab ID | Title | Powered By | What It Does |
|---|---|---|---|
| `dukkan_main` | Dukkan Mobile | `Dukkan_Plugin_Admin` | Hero banner + 12 feature cards with images |
| `store_app_connection` | Store OTP | `Dukkan_Plugin_Admin` | Displays the one-time auth code |
| `order_status` | Order Status | `Dukkan_Plugin_Order_Status` | Drag-and-drop modal, 5 AJAX handlers, auto-slug generation |
| `addons` | Product Add-Ons | `Dukkan_Plugin_Product_Addon` | 11 AJAX handlers, 10 field types (text, textarea, number, date, file, select, radio, checkbox, image, color) |

### Admin Assets (all gated to `toplevel_page_dukkan-settings`)

| Asset | Type | Deps |
|---|---|---|
| `select2` | CSS | — |
| `dukkan-plugin-admin.css` | CSS | — |
| `dp-product-addon.css` | CSS | — |
| `selectWoo` | JS | — |
| `dukkan-plugin-admin.js` | JS | jquery, jquery-ui-sortable, selectWoo |
| `dp-product-addon.js` | JS | jquery, selectWoo, dukkan-plugin |

JavaScript object: `wpldp_ajax = { url, nonce, os_i18n: { 18 strings } }`

### WooCommerce Integration (`Dukkan_Plugin_WooCommerce`)

Registers custom order statuses as `wc-{slug}` on `init`, adds them to the admin dropdown via `wc_order_statuses` filter. All statuses stored in `dukkan_custom_order_statuses`.

### Order Status AJAX Handlers (require `manage_options` + nonce)

| Action | Purpose |
|---|---|
| `dukkan_os_list` | List all statuses |
| `dukkan_os_add` | Create (name + slug) |
| `dukkan_os_update` | Update by old_slug |
| `dukkan_os_delete` | Delete by slug |
| `dukkan_os_reorder` | Persist drag-drop order |

### Product Addon AJAX Handlers (nonce only — no capability check! Many nopriv)

| Action | Purpose |
|---|---|
| `wpldp_get_categories` | Get category tree HTML |
| `wpldp_search_products` | Select2 product search |
| `wpldp_save_group` | Create group |
| `wpldp_delete_group` | Delete group |
| `wpldp_duplicate_group` | Duplicate group |
| `wpldp_toggle_group_status` | Enable/disable |
| `wpldp_get_group` | Get single (resolved product names) |
| `wpldp_update_group` | Update metadata |
| `wpldp_update_group_all_fields` | Batch update |
| `wpldp_duplicate_group_addon_field` | Duplicate a field |
| `wpldp_delete_group_addon_field` | Delete a field |

### Product Addon Group Data Model

```json
{
  "group_id": "group-name-1234567890",
  "group_name": "My Addon Group",
  "description": "",
  "applied_to": "all|products|categories",
  "categories": [],
  "products": [],
  "status": "enabled|disabled",
  "fields": [
    {
      "id": "field_1234567890",
      "type": "text|textarea|number|date|file|select|radio|checkbox|image|color",
      "label": "Field Name",
      "required": "yes|no",
      "price": 0,
      "options": [
        { "id": "opt_1234567890", "label": "Option", "price": 0 }
      ]
    }
  ]
}
```

---

## 4. REST API — All 29 Endpoints

### 4.1 Dynamic Pricing API — `dukkan-dynamic-pricing/v1` (PUBLIC)

**Writes to WCDPD's `rp_wcdpd_settings['1']['product_pricing']`** — rules appear in PricePep dashboard.

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/rules?page=&per_page=&method=&search=` | List rules (paginated, filterable by simple/bulk) |
| `POST` | `/rules` | Create simple or bulk rule |
| `GET` | `/rules/{uid}` | Get single rule |
| `PUT` | `/rules/{uid}` | Partial update |
| `DELETE` | `/rules/{uid}` | Delete |
| `GET` | `/products/search?search=&per_page=` | Search products |

**Rule types:** `simple` (top-level pricing_method + pricing_value), `bulk` (quantities_based_on + quantity_ranges)

**Simple pricing methods:** `discount__amount`, `discount__percentage`, `fee__amount`, `fee__percentage`, `fixed__price`  
**Bulk pricing methods:** `discount__amount`, `discount__percentage`, `fixed__price`, `fixed__price_per_range`  
**Quantity modes:** `individual__product`, `individual__variation`, `individual__configuration`, `cumulative__all`, `cumulative__categories`  
**Cart conditions:** `cart_subtotal`, `cart_quantity`, `cart_count`, `cart_weight` with operators `at_least`, `more_than`, `not_more_than`, `less_than`

### 4.2 Order Status API — `wc/v3` (WC Manager Auth)

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/statuses` | List all custom statuses |
| `POST` | `/statuses` | Create (name + slug) |
| `GET` | `/statuses/{slug}` | Get single |
| `PUT` | `/statuses/{slug}` | Update (name + new_slug) |
| `DELETE` | `/statuses/{slug}` | Delete |

**Validation:** name required, slug required (max 20 chars), unique slug, regex `[a-zA-Z0-9_-]+`

### 4.3 Product Addon API — `wc/v3` (WC Manager Auth)

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/get_groups/` | List groups (resolved product names) |
| `GET` | `/get_group/?group_id=X` | Get single |
| `POST` | `/create_group/` | Create with fields |
| `POST` | `/duplicate_group/` | Duplicate with "(Copy)" suffix |
| `POST/PUT/PATCH` | `/update_group/` | Partial update (supports all 3 methods) |
| `DELETE` | `/delete_group/?group_id=X` | Delete (returns deleted data) |

### 4.4 TranslatePress API — `dukkan-translation-translatepress/v1` (PUBLIC)

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/languages-list` | Available TP languages |
| `POST` | `/translate` | Save dictionary translations |
| `GET` | `/get-translations?original=&source_lang=` | Get translations for string |
| `GET` | `/translatepress-settings` | Read TP settings |
| `POST` | `/translatepress-save-settings` | Save TP settings |
| `GET` | `/translatepress-get-text-domains` | List gettext domains |
| `GET` | `/translatepress-gettext-translations?original=&domain=` | Get gettext translations |
| `POST` | `/translatepress-gettext-translate` | Save gettext translations |
| `GET` | `/translatepress-gettext-original-strings?domain=&page=&per_page=` | Paginated originals |

**Uses direct `$wpdb`** on `trp_gettext_*`, `trp_dictionary_*`, `trp_original_strings` tables.  
**Gates:** `class_exists('TRP_Translate_Press')` returns `tp_missing` if TP not active.

### 4.5 WooCommerce Webhook — `dukkan-woo-webhook/v1` (PUBLIC)

| Method | Route | Purpose |
|---|---|---|
| `POST` | `/shipping-status` | Receive shipping update → map to WC order status |

**Status mapping (filterable via `dukkan_plugin_woo_order_status_map`):**

| Ship Status | WC Status |
|---|---|
| `SCANNED_BY_HANDLER_AND_UNLOADED` | `with-carrier` |
| `SCANNED_BY_DRIVER_AND_IN_CAR` | `out-for-delivery` |
| `DELIVERED_TO_RECIPIENT` | `completed` |
| `CANCELLED` | `cancelled` |

**Payload:** `{ barcode, newStatus (required), invoiceNumber }` — Order lookup via `wc_get_order(invoiceNumber)`.

### 4.6 WooCommerce Extended API — `dukkan-woo-extended/v1` (Two-step auth)

| Method | Route | Auth | Purpose |
|---|---|---|---|
| `POST` | `/request-store-connection-auth-code` | Static key | Generate 4-char OTP |
| `POST` | `/rest-api-keys` | One-time code | Generate WC consumer key + secret |

**Static key:** `x-dukkan-api-key` header (hardcoded in `DUKKAN_WOO_EXTENDED_STATIC_API_KEY` constant)  
**Auth code:** stored in `dukkan_plugin_store_connection_auth_code`, deleted after one use  
**Results:** inserts directly into `woocommerce_api_keys` table

**Additional hook:** `before_delete_post` — when product deleted with `?delete_images=true`, deletes orphaned featured/gallery images (queries `postmeta` to verify no other product uses them).

### 4.7 General API — `dukkan-general-api/v1` (PUBLIC)

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/plugin-status?plugin={slug}` | Check if plugin installed + active |

Response: `{ success, slug, plugin, installed, active }`

---

## 5. Public-Facing Classes

### 5.1 Dukkan_Plugin_Public

Enqueues `dukkan-plugin-public.css` + `dukkan-plugin-public.js` (depends on jQuery).  
The old TranslatePress REST routes (`twb/v1/translate`) are **commented out** — never registered.

### 5.2 Dukkan_Product_Addon — Cart/Checkout Flow

This handles the entire product addon lifecycle:

```
woocommerce_before_add_to_cart_button → render_addons (10 field types)
    ↓
woocommerce_add_to_cart_validation → validate required fields
    ↓
woocommerce_add_cart_item_data → attach addon data + price to cart item
    ↓
woocommerce_get_cart_item_from_session → restore from session
    ↓
woocommerce_before_calculate_totals → add addon price to cart item price
    ↓
woocommerce_get_item_data → display addon details in cart
    ↓
woocommerce_checkout_create_order_line_item → save addon meta to order
    ↓
woocommerce_order_item_get_formatted_meta_data → hide internal keys
```

**File upload handler:** `wp_ajax[_nopriv]_wpldp_upload_file` — async upload for file-type fields.

**Supported field types:** text, textarea, number, date, file (with AJAX upload), select, radio, checkbox, image (radio with image previews), color (radio with color swatches). Each field can have a price. JS calculates addon totals + grand total.

---

## 6. Database Options — Complete Inventory

| Option Key | Stored As | Autoload | Purpose | Reads | Writes |
|---|---|---|---|---|---|
| `dukkan_custom_order_statuses` | Indexed array of `{name, slug}` | `no` | Custom WC order statuses | 5 calls | 2 calls |
| `wpldp_product_addon_groups` | Associative array of group objects | default | Product addon groups + fields | 17 calls | 12 calls |
| `dukkan_plugin_store_connection_auth_code` | String (4-char) | default | Store connection OTP | 2 calls | 1 write + 1 delete |
| `rp_wcdpd_settings` | `['1']['product_pricing'][]` | default | WCDPD/PricePep pricing rules | 2 calls | 1 call |
| `trp_settings` | Array | default | TranslatePress settings | 2 calls | 1 call |
| `woocommerce_currency_pos` | String | — | Read-only currency position | 1 call | 0 |

**Total:** 36 `get_option`, 19 `update_option`, 1 `add_option` (activation seed), 1 `delete_option`

### Direct `$wpdb` Usage

| File | Tables | Operations |
|---|---|---|
| `api/class-dukkan-plugin-translatepress.php` | `trp_gettext_*`, `trp_dictionary_*`, `trp_original_strings` | SELECT, INSERT, UPDATE, SHOW TABLES LIKE |
| `api/woo-extended/class-dukkan-woo-extended-api.php` | `woocommerce_api_keys`, `postmeta` | INSERT, SELECT |
| `public/class-dukkan-plugin-public.php` | `trp_dictionary_*` | Commented out — not active |

---

## 7. External Dependencies & Guards

| Class/Function | Files That Check | Purpose |
|---|---|---|
| `WooCommerce` | `class-dukkan-plugin-woocommerce.php`, `class-dukkan-woo-extended-api.php` | Gate WC-dependent features |
| `TRP_Translate_Press` | `class-dukkan-plugin-translatepress.php` (5 checks) | Gate TranslatePress features |
| `RP_WCDPD_Settings` | `class-dukkan-plugin-dynamic-pricing-api.php` | Cache bust after rule writes |
| `wc_rand_hash` / `wc_api_hash` | `class-dukkan-woo-extended-api.php` | API key generation |
| `is_plugin_active` | `class-dukkan-plugin-general.php` | Plugin status check |
| `wc_get_product` | 4 files | Product validation + enrichment |

---

## 8. Hook Registration Summary

| Registration Pattern | Count | Used By |
|---|---|---|
| Constructor `add_action` / `add_filter` | 46 hooks | All API classes, admin classes, public classes |
| Loader-deferred | 9 hooks | Admin enqueue (4), public enqueue (4), i18n (1) |
| Activation/Deactivation | 2 hooks | Plugin bootstrap |
| `apply_filters` | 1 filter | `dukkan_plugin_woo_order_status_map` |

---

## 9. Security Observations

| Observation | Severity | Details |
|---|---|---|
| Product addon AJAX handlers use `nopriv` | Medium | 11 AJAX handlers are exposed to unauthenticated users. Nonce-only protection — no `current_user_can()` check. |
| 16 of 29 REST endpoints are fully public | Info | TranslatePress (9), Dynamic Pricing (6), General (1) = no auth required |
| Static API key hardcoded in source | Low | `DUKKAN_WOO_EXTENDED_STATIC_API_KEY` is plaintext in `dukkan-plugin.php` |
| Dynamic Pricing API writes to live WCDPD rules | Info | Intentional — this is the bridge. Rules appear in PricePep dashboard |
| Shipping webhook has commented-out secret validation | Low | `dukkan_plugin_woo_order_status_map` had `x-webhook-secret` header check commented out |
| No dangerous functions | — | 0 occurrences of `eval`, `exec`, `system`, `shell_exec` |

---

## 10. Orphaned/Dead Files

| File | Status |
|---|---|
| `admin/partials/dukkan-plugin-dashboard.php` | Old dashboard — `-new.php` version is used instead |
| `admin/partials/dukkan-plugin-admin-display.php` | Never referenced by any `require` |
| `public/partials/dukkan-plugin-public-display.php` | Empty placeholder, never required |
| `admin/index.php`, `includes/index.php`, `public/index.php`, `index.php` | Directory silencers (standard WP pattern) |

---

## 11. Full Class Instantiation Order

```
1.  Dukkan_Plugin_Loader          (deferred hook system)
2.  Dukkan_Plugin_i18n            (text domain)
3.  Dukkan_Plugin_Woo_Webhook     (shipping webhook → 1 REST route)
4.  Dukkan_Plugin_Woo_Extended_API (API key generation + image cleanup → 2 REST routes)
5.  Dukkan_Plugin_API_General     (plugin status → 1 REST route)
6.  Dukkan_Plugin_Translatepress  (translation API → 9 REST routes)
7.  Dukkan_Plugin_Product_Addon_API (addon CRUD → 6 REST routes)
8.  Dukkan_Plugin_Order_Status_API  (status CRUD → 5 REST routes)
9.  Dukkan_Plugin_Dynamic_Pricing_API (WCDPD bridge → 6 REST routes)
10. Dukkan_Plugin_Admin           (menu + dashboard tabs)
11. Dukkan_Plugin_WooCommerce     (WC status registration)
12. Dukkan_Plugin_Order_Status    (status AJAX + admin tab)
13. Dukkan_Plugin_Product_Addon   (addon AJAX + admin tab)
14. Dukkan_Plugin_Public          (public enqueue)
15. Dukkan_Product_Addon          (frontend product addons)
```

---

## 12. Future Development Notes

### To Remove (safe cleanup)
- `admin/partials/dukkan-plugin-dashboard.php` — old unused dashboard
- `admin/partials/dukkan-plugin-admin-display.php` — dead partial
- `public/partials/dukkan-plugin-public-display.php` — empty placeholder
- `public/class-dukkan-plugin-public.php` lines 54-230 — commented-out TranslatePress REST code

### To Add
- Capability checks on product addon AJAX handlers (currently nonce-only + nopriv)
- Webhook secret validation on shipping status endpoint
- Dynamic pricing API — consider tiered pricing support (already has constants ready)
- Store OTP: add expiry timestamp + rate limiting

### Testing Checklist
- [ ] Product addons render on frontend (all 10 field types)
- [ ] File upload works (frontend + admin)
- [ ] Order status CRUD (admin + REST)
- [ ] Dynamic pricing rules (simple + bulk) via REST API
- [ ] Rules appear in PricePep dashboard
- [ ] TranslatePress endpoints (dictionary + gettext)
- [ ] Shipping webhook (status mapping)
- [ ] API key generation flow (static key → OTP → WC keys)
- [ ] Product deletion with image cleanup
