# Dukkan Dynamic Pricing REST API

Public REST API for managing WCDPD (PricePep) simple adjustment pricing rules. Embedded in the Dukkan plugin as a bridge between the mobile app and the WCDPD plugin.

- **Base URL:** `https://fashion.dukkanjo.com/wp-json/dukkan-dynamic-pricing/v1`
- **Auth:** None (public)
- **Storage:** `rp_wcdpd_settings` WordPress option → visible in WooCommerce > PricePep dashboard

## Files

| File | Description |
|------|-------------|
| `api/class-dukkan-plugin-dynamic-pricing-api.php` | Main API class (6 endpoints) |
| `api/dynamic-pricing-notes.md` | Quick-reference markdown |
| `api/dynamic-pricing-api-reference.json` | Machine-readable spec (tables + examples) |
| `api/IMPLEMENTATION-NOTES.md` | Full session log — bugs, fixes, architecture decisions |
| `includes/class-dukkan-plugin.php` | Core orchestrator (3 insertions to load the API) |

## Endpoints

| Method | Route | Description |
|--------|-------|-------------|
| `GET` | `/rules` | List rules (`?page=&per_page=&search=`) |
| `POST` | `/rules` | Create rule |
| `GET` | `/rules/{uid}` | Get rule |
| `PUT` | `/rules/{uid}` | Update rule (partial) |
| `DELETE` | `/rules/{uid}` | Delete rule |
| `GET` | `/products/search` | Search products (`?search=&per_page=`) |

## Quick Example

```bash
curl -X POST https://fashion.dukkanjo.com/wp-json/dukkan-dynamic-pricing/v1/rules \
  -H 'Content-Type: application/json' \
  -d '{"pricing_method":"discount__percentage","pricing_value":20,"product_uids":[40289]}'
```

## Architecture

```
Mobile App → Dukkan REST API → rp_wcdpd_settings['1']['product_pricing'] → WCDPD/PricePep Dashboard
```

The API follows the same self-registering pattern as all Dukkan API classes (TranslatePress, General, etc.) — it hooks into `rest_api_init` in its constructor and all endpoints are public (`permission_callback => '__return_true'`).
