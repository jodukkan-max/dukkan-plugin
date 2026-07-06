# Dukkan Plugin — Architecture

> Living blueprint. Update this file whenever structural changes are made (new classes,
> routes, hooks, options, or tabs).

---

## 1. High-Level Overview

**Plugin Name:** Dukkan Plugin  
**Version:** 1.0.0  
**Text Domain:** `dukkan-plugin`  
**Minimum PHP:** 7.4+  
**Author:** Atul Goyal  
**Entry Point:** `dukkan-plugin.php`

The plugin provides a unified admin settings dashboard (`Dukkan Settings`) under
WordPress Admin > Dukkan Settings. Features are organized as **tabs** within that page.
Each feature lives in its own class following the `Dukkan_Plugin_{Component}` naming
convention.

---

## 2. Entry Point & Constants

**File:** `dukkan-plugin.php`

```php
define( 'DUKKAN_PLUGIN_VERSION', '1.0.0' );
define( 'DUKKAN_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'DUKKAN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DUKKAN_WOO_EXTENDED_STATIC_API_KEY', 'yuwqeq436473h4h3rh557448384' );
```

| Hook | Callback |
|------|----------|
| `register_activation_hook` | `activate_dukkan_plugin()` → `Dukkan_Plugin_Activator::activate()` |
| `register_deactivation_hook` | `deactivate_dukkan_plugin()` → `Dukkan_Plugin_Deactivator::deactivate()` |

Activation/deactivation classes are currently empty stubs.

---

## 3. Directory Structure

```
dukkan-plugin/
├── dukkan-plugin.php              # Bootstrap
├── ARCHITECTURE.md                # This file
├── .cursorrules                   # Cursor AI conventions
├── index.php                      # Silence is golden
├── uninstall.php                  # Uninstall stub
│
├── includes/
│   ├── class-dukkan-plugin.php            # Core orchestrator
│   ├── class-dukkan-plugin-loader.php     # Hook registration system
│   ├── class-dukkan-plugin-i18n.php       # Text domain loading
│   ├── class-dukkan-plugin-activator.php  # Activation (empty)
│   ├── class-dukkan-plugin-deactivator.php# Deactivation (empty)
│   └── index.php
│
├── admin/                          # Admin-facing classes
│   ├── class-dukkan-plugin-admin.php           # Menu, enqueue, tab content actions
│   ├── class-dukkan-plugin-store-settings.php  # Store Settings tab
│   ├── class-dukkan-plugin-woocommerce.php      # WC order status registration
│   ├── class-dukkan-plugin-order-status.php     # Order Status tab + AJAX CRUD
│   ├── class-dukkan-plugin-product-addon.php    # Product Add-Ons tab + AJAX handlers
│   ├── css/dukkan-plugin-admin.css
│   ├── css/dp-product-addon.css
│   ├── js/dukkan-plugin-admin.js
│   ├── js/dp-product-addon.js
│   ├── images/                                 # Feature card images (12 JPEGs)
│   └── partials/
│       ├── dukkan-settings-main.php            # Tab navigation + panels
│       ├── dukkan-plugin-dashboard-new.php     # "Dukkan Main" tab content
│       ├── dukkan-plugin-dashboard.php         # Legacy dashboard
│       ├── dukkan-plugin-admin-display.php     # Simple admin display
│       ├── dukkan-discount-settings.php        # "Discounts" tab (static)
│       ├── dukkan-order-status-settings.php    # Order Status tab (modal + sortable)
│       └── product-addons-settings.php         # Product Add-Ons tab (JS-driven)
│
├── api/                            # REST API classes
│   ├── class-dukkan-plugin-general.php          # Plugin status checker
│   ├── class-dukkan-plugin-order-status-api.php # Order Status CRUD
│   ├── class-dukkan-plugin-product-addon-api.php# Product Addon CRUD (wc/v3)
│   ├── class-dukkan-plugin-translatepress.php   # TranslatePress integration
│   ├── woo-extended/class-dukkan-woo-extended-api.php # WC API key generation
│   └── webhook/woo/class-dukkan-woo-webhook.php # Shipping status webhook
│
├── public/                         # Frontend-facing classes
│   ├── class-dukkan-plugin-public.php         # Public enqueue
│   ├── class-product-addon.php                # Frontend product addon display
│   ├── css/dukkan-plugin-public.css
│   ├── css/dukkan-plugin-product-addon.css
│   ├── js/dukkan-plugin-public.js
│   ├── js/dukkan-plugin-product-addon.js
│   └── partials/dukkan-plugin-public-display.php
│
└── languages/
    └── dukkan-plugin.pot           # Translation template
```

---

## 4. Core Orchestrator — `Dukkan_Plugin`

**File:** `includes/class-dukkan-plugin.php`

The constructor calls `load_dependencies()` then a series of `define_*_hooks()` methods.
Each method instantiates the relevant class. Some components register their own hooks
in their constructors; others use `$this->loader` for deferred registration.

### Instantiation Order

| Method | Class Instantiated | Hook Registration |
|--------|-------------------|-------------------|
| `set_locale()` | `Dukkan_Plugin_i18n` | Loader: `plugins_loaded` |
| `define_woo_webhook_hooks()` | `Dukkan_Plugin_Woo_Webhook` | Self-registered |
| `define_woo_extended_hooks()` | `Dukkan_Plugin_Woo_Extended_API` | Self-registered |
| `define_general_api_hooks()` | `Dukkan_Plugin_API_General` | Self-registered (`rest_api_init`) |
| `define_translatepress_api_hooks()` | `Dukkan_Plugin_Translatepress` | Self-registered (`rest_api_init`) |
| `define_product_addon_api_hooks()` | `Dukkan_Plugin_Product_Addon_API` | Self-registered (`rest_api_init`) |
| `define_order_status_api_hooks()` | `Dukkan_Plugin_Order_Status_API` | Self-registered (`rest_api_init`) |
| `define_admin_hooks()` | `Dukkan_Plugin_Admin` | Loader: `admin_enqueue_scripts` (styles + scripts) |
| | `Dukkan_Plugin_Store_Settings` | Self-registered (tab filter + action + admin-post) |
| | `Dukkan_Plugin_WooCommerce` | Self-registered (`plugins_loaded`) |
| | `Dukkan_Plugin_Order_Status` | Self-registered (tab filter + action + AJAX) |
| | `Dukkan_Plugin_Product_Addon` | Loader: `admin_enqueue_scripts` (styles + scripts) |
| `define_public_hooks()` | `Dukkan_Plugin_Public` | Loader: `wp_enqueue_scripts` (styles + scripts) |
| | `Dukkan_Product_Addon` | Loader: `wp_enqueue_scripts` (styles + scripts) |

---

## 5. Admin Settings Page — Tab System

**Admin Menu Slug:** `dukkan-settings`  
**Menu Position:** 25  
**Icon:** `dashicons-store`  
**Capability:** `manage_options`

### How Tabs Work

1. Tabs are defined via `apply_filters( 'dukkan_settings_tabs', $defaults )` in
   `admin/partials/dukkan-settings-main.php`.
2. Content is rendered via `do_action( 'dukkan_settings_tab_content_' . $tab_id )`.
3. Active tab determined server-side by `?tab=` query param; client-side switching
   via jQuery click handler (no page reload).
4. CSS classes: `.wpldp-tab-panel` (hidden by default), `.wpldp-tab-panel.active` (visible).

### Registered Tabs

| Tab ID | Title | Icon | Registered By |
|--------|-------|------|---------------|
| `dukkan_main` | Dukkan Mobile | `fa-solid fa-mobile-screen` | Hardcoded default |
| `store_settings` | Dukkan Store Settings | `fa-solid fa-store` | `Dukkan_Plugin_Store_Settings` |
| `addons` | Product Add-Ons | `fa-solid fa-dollar-sign` | `Dukkan_Plugin_Product_Addon` |
| `order_status` | Order Status | `fa-solid fa-truck-fast` | `Dukkan_Plugin_Order_Status` |

---

## 6. Feature: Store Settings

**Class:** `Dukkan_Plugin_Store_Settings`  
**File:** `admin/class-dukkan-plugin-store-settings.php`

### Option Key

```
dukkan_store_settings
```

### Fields

No default fields. The `dukkan_store_settings_fields` filter allows other
components to register fields dynamically. The old `dukkan_woo_order_status`
toggle has been removed — all order statuses are now user-managed via the
Order Status tab.

### Form Handling

| Action | Route | Nonce |
|--------|-------|-------|
| `admin_post_dukkan_store_settings_save` | Form POST | `dukkan_store_settings_nonce` |

### Hooks

| Hook | Type | Purpose |
|------|------|---------|
| `dukkan_settings_tabs` | Filter | Add store_settings tab |
| `dukkan_settings_tab_content_store_settings` | Action | Render tab content |
| `admin_post_dukkan_store_settings_save` | Action | Save form |
| `dukkan_store_settings_fields` | Filter | Modify field definitions |
| `dukkan_store_settings_before_save` | Action | Before save |
| `dukkan_store_settings_field_value_before_save` | Filter | Per-field value before save |
| `dukkan_store_settings_values_before_save` | Filter | All values before save |
| `dukkan_store_settings_after_save` | Action | After save |

---

## 7. Feature: WooCommerce Order Status Registration

**Class:** `Dukkan_Plugin_WooCommerce`  
**File:** `admin/class-dukkan-plugin-woocommerce.php`

### Constant

| Constant | Value |
|----------|-------|
| `USER_STATUSES_OPTION` | `dukkan_custom_order_statuses` |

### Behaviour

All custom order statuses are read from the `dukkan_custom_order_statuses` option.
There are **no built-in statuses** — the three delivery statuses (Ready For Delivery,
Out For Delivery, With Carrier) are seeded as default entries in the option by
`Dukkan_Plugin_Activator::seed_default_statuses()` on first activation.

Each status is registered via `register_post_status()` on `init` with the `wc-`
prefix, and added to the WC admin dropdown via the `wc_order_statuses` filter.

### Hooks

| Hook | Type | Purpose |
|------|------|---------|
| `plugins_loaded` | Action | Gate: check `class_exists('WooCommerce')`, then register hooks |
| `init` | Action | `register_custom_order_statuses()` |
| `wc_order_statuses` | Filter | `add_custom_order_statuses()` |

---

## 8. Feature: Order Status Management (UI + AJAX)

**Class:** `Dukkan_Plugin_Order_Status`  
**File:** `admin/class-dukkan-plugin-order-status.php`  
**Partial:** `admin/partials/dukkan-order-status-settings.php`

### Option Key

```
dukkan_custom_order_statuses
```

Stored as an indexed array of `{name: string, slug: string}` objects. User-defined
ordering is preserved.

### Constants

| Constant | Value |
|----------|-------|
| `OPTION_KEY` | `dukkan_custom_order_statuses` |
| `SLUG_MAX_LENGTH` | `20` |

### AJAX Handlers

All require `manage_options` capability and verify `wpldp_nonce` nonce.

| Action | Method | Purpose |
|--------|--------|---------|
| `wp_ajax_dukkan_os_list` | `ajax_list()` | List all statuses |
| `wp_ajax_dukkan_os_add` | `ajax_add()` | Create new status |
| `wp_ajax_dukkan_os_update` | `ajax_update()` | Update existing status |
| `wp_ajax_dukkan_os_delete` | `ajax_delete()` | Delete a status |
| `wp_ajax_dukkan_os_reorder` | `ajax_reorder()` | Reorder via drag-drop |

### UI Element IDs (used by JS)

| ID | Element |
|----|---------|
| `dukkan-os-add-btn` | "Add Status" button |
| `dukkan-os-list` | Sortable list container |
| `dukkan-os-empty` | Empty state message |
| `dukkan-os-modal` | Add/Edit modal |
| `dukkan-os-modal-overlay` | Modal overlay |
| `dukkan-os-modal-title` | Modal title (H3) |
| `dukkan-os-modal-old-slug` | Hidden input for edit mode |
| `dukkan-os-modal-name` | Status name input |
| `dukkan-os-modal-slug` | Status slug input |
| `dukkan-os-modal-error` | Inline error message |
| `dukkan-os-modal-save` | Save button |
| `dukkan-os-modal-cancel` | Cancel button |
| `dukkan-os-modal-close` | X close button |
| `dukkan-os-delete-modal` | Delete confirmation modal |
| `dukkan-os-delete-overlay` | Delete modal overlay |
| `dukkan-os-delete-confirm` | Delete confirm button |
| `dukkan-os-delete-cancel` | Delete cancel button |
| `dukkan-os-delete-close` | Delete modal X button |
| `dukkan-os-delete-msg` | Delete confirmation message |

---

## 9. Feature: Product Add-Ons

**Class:** `Dukkan_Plugin_Product_Addon`  
**File:** `admin/class-dukkan-plugin-product-addon.php`

### Option Key

```
wpldp_product_addon_groups
```

### AJAX Handlers

All use `check_ajax_referer( 'wpldp_nonce', 'nonce' )`.

| Action | Purpose |
|--------|---------|
| `wp_ajax_wpldp_get_categories` | Get product categories |
| `wp_ajax_wpldp_search_products` | Search products |
| `wp_ajax_wpldp_save_group` | Save addon group |
| `wp_ajax_wpldp_delete_group` | Delete addon group |
| `wp_ajax_wpldp_duplicate_group` | Duplicate addon group |
| `wp_ajax_wpldp_toggle_group_status` | Toggle group active/inactive |
| `wp_ajax_wpldp_get_group` | Get single group |
| `wp_ajax_wpldp_update_group` | Update group |
| `wp_ajax_wpldp_save_field` | Save field to group |
| `wp_ajax_wpldp_delete_field` | Delete field from group |
| `wp_ajax_wpldp_update_field` | Update field |

(All also have `wp_ajax_nopriv_*` variants.)

---

## 10. REST API Endpoints

### 10.1 General API

**Class:** `Dukkan_Plugin_API_General`  
**Namespace:** `dukkan-general-api/v1`  
**Permission:** `__return_true` (public)

| Method | Route | Purpose |
|--------|-------|---------|
| `GET` | `/plugin-status?plugin={slug}` | Check if a plugin is installed/active |

### 10.2 Order Status API

**Class:** `Dukkan_Plugin_Order_Status_API`  
**Namespace:** `dukkan-order-status/v1`  
**Permission:** `current_user_can( 'manage_options' )`

| Method | Route | Purpose |
|--------|-------|---------|
| `GET` | `/statuses` | List all custom statuses |
| `POST` | `/statuses` | Create a new status |
| `GET` | `/statuses/{slug}` | Get a single status |
| `PUT` | `/statuses/{slug}` | Update a status |
| `DELETE` | `/statuses/{slug}` | Delete a status |

### 10.3 Product Addon API

**Class:** `Dukkan_Plugin_Product_Addon_API`  
**Namespace:** `wc/v3`  
**Permission:** `wc_rest_check_manager_permissions( 'settings', 'read' )`

| Method | Route | Purpose |
|--------|-------|---------|
| `GET` | `/get_groups/` | List all addon groups |
| `GET` | `/get_group/` | Get single group |
| `POST` | `/create_group/` | Create group |
| `POST/PUT/PATCH` | `/update_group/` | Update group |
| `DELETE` | `/delete_group/` | Delete group |
| `POST` | `/duplicate_group/` | Duplicate group |

### 10.4 TranslatePress API

**Class:** `Dukkan_Plugin_Translatepress`  
**Namespace:** `dukkan-translation-translatepress/v1`  
**Permission:** `__return_true` (public)

| Method | Route | Purpose |
|--------|-------|---------|
| `GET` | `/languages-list` | List available languages |
| `POST` | `/translate` | Translate text |
| `GET` | `/get-translations` | Get translations |
| `GET` | `/translatepress-settings` | Get TranslatePress settings |
| `POST` | `/translatepress-save-settings` | Save TranslatePress settings |
| `GET` | `/translatepress-get-text-domains` | Get text domains |
| `GET` | `/translatepress-gettext-translations` | Get gettext translations |
| `POST` | `/translatepress-gettext-translate` | Translate gettext strings |
| `GET` | `/translatepress-gettext-original-strings` | Get original strings |

### 10.5 WooCommerce Webhook

**Class:** `Dukkan_Plugin_Woo_Webhook`  
**Namespace:** `dukkan-woo-webhook/v1`  
**Permission:** `__return_true` (public webhook)

| Method | Route | Purpose |
|--------|-------|---------|
| `POST` | `/shipping-status` | Receive shipping platform status update |

**Filter:** `dukkan_plugin_woo_order_status_map` — maps shipping platform statuses to
WooCommerce statuses.

### 10.6 WooCommerce Extended API

**Class:** `Dukkan_Plugin_Woo_Extended_API`  
**Namespace:** `dukkan-woo-extended/v1`  
**Permission:** Static API key (checks `DUKKAN_WOO_EXTENDED_STATIC_API_KEY` constant or
`dukkan_plugin_woo_extended_static_api_key` filter)

| Method | Route | Purpose |
|--------|-------|---------|
| `POST` | `/rest-api-keys` | Generate WooCommerce REST API keys |

Also hooks into `before_delete_post` to delete product images on REST API product deletion.

---

## 11. Shared Dependencies

### Enqueued on Admin

| Asset | Handle | Dependencies |
|-------|--------|-------------|
| Select2 (CSS) | `select2` | — |
| Admin CSS | `dukkan-plugin` | — |
| Admin Product Addon CSS | `dp-product-addon` | — |
| SelectWoo (JS) | `selectWoo` | — |
| Admin JS | `dukkan-plugin` | `jquery`, `jquery-ui-sortable`, `selectWoo` |
| Admin Product Addon JS | `dp-product-addon` | `jquery`, `selectWoo` |
| Font Awesome 6 | CDN link | Inline `<link>` in settings page |

### Localized Data (via `wp_localize_script`)

**Handle:** `dukkan-plugin`  
**Object:** `wpldp_ajax`

```
{
    url: admin_url('admin-ajax.php'),
    nonce: wp_create_nonce('wpldp_nonce'),
    os_i18n: {
        add_title, edit_title, name_required, slug_required, slug_max,
        save_btn, saving, deleting, added, updated, deleted,
        order_saved, edit, delete, delete_confirm, delete_msg, cancel
    }
}
```

---

## 12. Option Keys Reference

| Option Key | Storage Format | Used By |
|------------|---------------|---------|
| `dukkan_store_settings` | Associative array | `Dukkan_Plugin_Store_Settings` |
| `dukkan_custom_order_statuses` | Indexed array of `{name, slug}` | `Dukkan_Plugin_Order_Status`, `Dukkan_Plugin_WooCommerce`, `Dukkan_Plugin_Order_Status_API` |
| `wpldp_product_addon_groups` | Array of group objects | `Dukkan_Plugin_Product_Addon`, `Dukkan_Plugin_Product_Addon_API` |

---

## 13. Nonce Reference

| Nonce Action | Used In | Method |
|-------------|---------|--------|
| `wpldp_nonce` | All AJAX handlers, localized JS | `check_ajax_referer( 'wpldp_nonce', 'nonce' )` |
| `dukkan_store_settings_save` | Store settings form | `check_admin_referer( 'dukkan_store_settings_save', 'dukkan_store_settings_nonce' )` |

---

## 14. Data Flow

### Admin Settings Page

```
Browser → ?page=dukkan-settings&tab=order_status
  → WordPress Admin
  → Dukkan_Plugin_Admin::dukkan_settings_page()
  → require partials/dukkan-settings-main.php
  → apply_filters( 'dukkan_settings_tabs', $defaults )     [build tabs]
  → do_action( 'dukkan_settings_tab_content_' . $tab_id )   [render content]
  → jQuery click handler switches .active classes            [client-side nav]
```

### Order Status CRUD (AJAX)

```
Browser → $.post(admin-ajax.php, { action: 'dukkan_os_add', nonce, name, slug })
  → Dukkan_Plugin_Order_Status::ajax_add()
  → verify_ajax() [capability + nonce]
  → validate() [name required, slug required, max 20 chars, no duplicates]
  → get_all_statuses() / save_statuses() [option read/write]
  → wp_send_json_success( data )
  → JS refreshes list, shows toast, closes modal
```

### Order Status CRUD (REST API)

```
Client → GET/POST/PUT/DELETE /wp-json/dukkan-order-status/v1/statuses[/{slug}]
  → Dukkan_Plugin_Order_Status_API::check_permission() [manage_options]
  → validate() [WP_Error on failure]
  → get_all_statuses() / save_statuses() [option read/write]
  → rest_ensure_response( data )
```

### WooCommerce Registration

```
WordPress init
  → Dukkan_Plugin_WooCommerce::register_custom_order_statuses()
  → get_option( 'dukkan_custom_order_statuses' )
  → foreach: register_post_status( 'wc-{slug}', { label, public, ...label_count } )
  → add_filter( 'wc_order_statuses' ) adds all to admin dropdown
```

### Activation (Default Status Seeding)

```
Plugin activation
  → Dukkan_Plugin_Activator::activate()
  → seed_default_statuses()
  → add_option( 'dukkan_custom_order_statuses', [
        { name: 'Ready For Delivery', slug: 'ready-delivery' },
        { name: 'Out For Delivery',  slug: 'out-for-delivery' },
        { name: 'With Carrier',      slug: 'with-carrier' },
    ], '', 'no' )
  → add_option() is a no-op if the option already exists

---

## 15. Adding a New Feature

Follow this checklist when adding a new feature to the Dukkan settings page:

1. **Create admin class** in `admin/class-dukkan-plugin-{feature}.php`
   - Constructor: add tab via `dukkan_settings_tabs` filter
   - Constructor: add content via `dukkan_settings_tab_content_{tab_id}` action
   - Constructor: add AJAX handlers via `wp_ajax_{action}` (if needed)
   - Define option key as class constant
   - Implement CRUD methods
   - Verify `wpldp_nonce` nonce and `manage_options` capability

2. **Create partial template** in `admin/partials/dukkan-{feature}-settings.php`
   - Use `dukkan-` prefixed CSS classes following BEM conventions
   - Use `esc_html__()`, `esc_attr_e()`, `esc_url()` for all output
   - Pass data via the calling class (not `global`)

3. **Create REST API class** in `api/class-dukkan-plugin-{feature}-api.php` (if needed)
   - Hook into `rest_api_init`
   - Namespace: `dukkan-{feature}/v1`
   - Non-public endpoints: `permission_callback` must check capability

4. **Update `includes/class-dukkan-plugin.php`**
   - Add `require_once` in `load_dependencies()`
   - Add `define_{feature}_api_hooks()` method
   - Instantiate admin class in `define_admin_hooks()`

5. **Update CSS** in `admin/css/dukkan-plugin-admin.css` — follow existing color
   palette and patterns

6. **Update JS** in `admin/js/dukkan-plugin-admin.js` — use `wpldp_ajax` for AJAX,
   `showToast()` for notifications, `wpldp_ajax.os_i18n` or new i18n key for strings

7. **Update this file** — add feature section documenting routes, AJAX actions, option
   keys, and element IDs
