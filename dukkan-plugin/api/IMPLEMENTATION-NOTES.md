# Dukkan Dynamic Pricing REST API — Implementation Notes

## Session Date
July 4–5, 2026

---

## What Was Built

A public REST API (6 endpoints) that creates/reads/updates/deletes **simple adjustment rules** in the WCDPD (WooCommerce Dynamic Pricing & Discounts / PricePep) plugin. The API is embedded inside the Dukkan plugin and acts as a bridge between the mobile app and WCDPD's rule storage.

---

## Files Created / Modified

| File | Action | Purpose |
|------|--------|---------|
| `api/class-dukkan-plugin-dynamic-pricing-api.php` | **Created** | Main API class — 6 REST endpoints, all public |
| `api/dynamic-pricing-notes.md` | **Created** | Quick-reference markdown for the API |
| `api/dynamic-pricing-api-reference.json` | **Created** | Machine-readable spec for mobile devs |
| `includes/class-dukkan-plugin.php` | **Modified** | 3 insertions to load the new API class |

### Orchestrator Insertions (class-dukkan-plugin.php)

1. **Line 85** — Constructor: `$this->define_dynamic_pricing_api_hooks();`
2. **Line 152** — `require_once ... 'api/class-dukkan-plugin-dynamic-pricing-api.php';`
3. **Line 272** — Private method `define_dynamic_pricing_api_hooks()` that instantiates the class

---

## Architecture

```
Mobile App ──REST (public)──▶ Dukkan Plugin API ──read/write──▶ rp_wcdpd_settings['1']['product_pricing']
                                                                          │
                                                                WCDPD Plugin reads it
                                                                → WooCommerce > PricePep dashboard
```

The API class follows the **self-registering pattern** used by all Dukkan API classes (TranslatePress, General, Woo Webhook, etc.) — it registers its own routes in the constructor via `add_action('rest_api_init', ...)`. No authentication (`permission_callback => '__return_true'`), matching the TranslatePress API pattern.

---

## Bugs Encountered & Fixed

### Bug 1: Wrong WCDPD storage path (CRITICAL)
- **Symptom:** Rules created via API didn't appear in WooCommerce > PricePep dashboard
- **Cause:** API was writing to `rp_wcdpd_settings['product_pricing_1']` (flattened key)
- **Fix:** Changed to `rp_wcdpd_settings['1']['product_pricing']` — WCDPD uses version `'1'` as the outer wrapper key, not `product_pricing_1`

### Bug 2: WCDPD internal format mismatch (earlier iteration)
- **Symptom:** Rules invisible to WCDPD plugin
- **Cause:** Incorrect condition type (`product_product` vs `product__product`), wrong method_option (`at_least_one` vs `in_list`), wrong field name (`product` singular vs `products` plural)
- **Fix:** Matched all field names exactly to WCDPD's internal structure

### Bug 3: Wrong data store target (Dukkan vs WCDPD)
- **Symptom:** Rules appeared in Dukkan dashboard but not PricePep
- **Cause:** Briefly rewrote to target `dukkan_dynamic_pricing_rules` (Dukkan's own option)
- **Fix:** Reverted to `rp_wcdpd_settings` — the Dukkan plugin is a bridge, not a replacement

### Bug 4: Route param mismatch
- **Symptom:** Single-rule endpoints failed
- **Cause:** Route regex used `{id}` but callbacks read `->get_param('uid')`
- **Fix:** Unified to `{uid}` everywhere

---

## WCDPD Internal Format (for reference)

```php
// Rules stored at: rp_wcdpd_settings['1']['product_pricing']
array(
    'uid'            => 'rp_wcdpd_xxx',
    'exclusivity'    => 'all',
    'method'         => 'simple',
    'note'           => '',
    'public_note'    => '',
    'pricing_method' => 'discount__percentage',  // double-underscore keys
    'pricing_value'  => 40.0,
    'conditions'     => array(
        array(
            'uid'           => 'rp_wcdpd_yyy',
            'type'          => 'product__product',       // double-underscore
            'method_option' => 'in_list',
            'products'      => array('41987'),            // strings, not ints
        ),
    ),
);
```

Key WCDPD facts:
- Version key is hardcoded `$version = '1'`
- Product condition type is `product__product` (not `product_product`)
- Category condition type is `product__category`, field is `product_categories`
- Cart condition values stored as strings in type-specific fields (`decimal` or `number`)
- `pricing_method` uses combined keys like `discount__amount`, `fee__percentage`

---

## API Design Decisions

1. **Public access** — user explicitly rejected Application Passwords and WooCommerce API keys
2. **UID-based routing** — rules identified by `rp_wcdpd_*` hash, matching WCDPD's convention
3. **Partial updates** — PUT only changes fields you send; unset fields are preserved
4. **Product/category exclusivity** — at least one of `product_uids` or `product_category_uids` required
5. **No separate plugin** — integrated into existing Dukkan plugin per user request

---

## Live Test Results

All 6 endpoints verified on `https://fashion.dukkanjo.com`:

| Endpoint | Status | Test |
|----------|--------|------|
| GET /rules | 200 | Listed 3 rules |
| POST /rules | 201 | Created rules by product, by category, with conditions |
| GET /rules/{uid} | 200 | Fetched single rule |
| PUT /rules/{uid} | 200 | Updated pricing_value from 30 to 40 |
| DELETE /rules/{uid} | 200 | Deleted test rule, returned `{"deleted":true}` |
| GET /products/search | 200 | Returned real products from store |

---

## ZIP Location

`/Users/indiana/Cursor/dukkan-plugin-with-api.zip` (972K)
