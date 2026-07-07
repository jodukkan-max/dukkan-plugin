# Dynamic Pricing REST API — Quick Reference

## Namespace
`dukkan-dynamic-pricing/v1`

## Authentication
**Public** — no authentication required.

## Storage
Rules stored in **`rp_wcdpd_settings`** → visible in **WooCommerce > PricePep** dashboard.

---

## Endpoints

| Method | Route | Description |
|--------|-------|-------------|
| `GET` | `/rules?method=simple|bulk` | List rules, optional method filter |
| `GET` | `/rules/{uid}` | Get single rule by UID |
| `POST` | `/rules` | Create a new rule (simple or bulk) |
| `PUT` | `/rules/{uid}` | Update a rule (partial) |
| `DELETE` | `/rules/{uid}` | Delete a rule |
| `GET` | `/products/search` | Search WooCommerce products |

---

## Rule Types (`method`)

| Key | Description |
|-----|-------------|
| `simple` (default) | Simple adjustment — one pricing method for all matched products |
| `bulk` | Bulk pricing — quantity ranges with different pricing per range |

---

## Simple Adjustment: Pricing Methods

| Key | Description |
|-----|-------------|
| `discount__amount` | Fixed discount (e.g. $10 off) |
| `discount__percentage` | Percentage discount (e.g. 15% off) |
| `fee__amount` | Fixed fee (e.g. $5 added) |
| `fee__percentage` | Percentage fee (e.g. 10% added) |
| `fixed__price` | Fixed price (e.g. set to $49) |

## Bulk Pricing: Range Pricing Methods

| Key | Description |
|-----|-------------|
| `discount__amount` | Fixed discount |
| `discount__percentage` | Percentage discount |
| `fixed__price` | Fixed price per unit |
| `fixed__price_per_range` | Total price for entire range |

## Bulk: Quantity Grouping (`quantities_based_on`)

| Key | Meaning |
|-----|---------|
| `individual__product` | Each individual product counted separately |
| `individual__variation` | Each individual variation |
| `individual__configuration` | Each individual cart line item |
| `cumulative__all` | All matched products added up together |
| `cumulative__categories` | Quantities added up per category |

---

## Product/Category Methods

| Key | Description |
|-----|-------------|
| `in_list` | Rule applies to items in the list |
| `not_in_list` | Rule applies to items NOT in the list |

---

## Cart Conditions

| `type` | `method_option` Options |
|--------|--------------------------|
| `cart_subtotal` | `at_least`, `more_than`, `not_more_than`, `less_than` |
| `cart_quantity` | `at_least`, `more_than`, `not_more_than`, `less_than` |
| `cart_count` | `at_least`, `more_than`, `not_more_than`, `less_than` |
| `cart_weight` | `at_least`, `more_than`, `not_more_than`, `less_than` |

---

## Example: Create Bulk Pricing Rule

```json
POST /dukkan-dynamic-pricing/v1/rules

{
  "method": "bulk",
  "note": "Bulk discount",
  "quantities_based_on": "individual__product",
  "product_uids": [40289],
  "product_method": "in_list",
  "quantity_ranges": [
    { "from": 1,  "to": 5,  "pricing_method": "discount__amount",     "pricing_value": 0 },
    { "from": 6,  "to": 10, "pricing_method": "discount__percentage", "pricing_value": 10 },
    { "from": 11, "pricing_method": "fixed__price", "pricing_value": 15 }
  ]
}
```

> Use `"to": null` or omit `to` for unbounded ranges (e.g. `"from": 11` with no `to` means 11+).

## Bulk Response Format

```json
{
  "uid": "rp_wcdpd_xxx",
  "method": "bulk",
  "note": "Bulk discount",
  "public_note": "",
  "product_uids": [40289],
  "product_method": "in_list",
  "product_category_uids": [],
  "product_category_method": "in_list",
  "conditions": [],
  "quantities_based_on": "individual__product",
  "quantity_ranges": [
    { "from": 1,  "to": 5,  "pricing_method": "discount__amount",     "pricing_value": 0 },
    { "from": 6,  "to": 10, "pricing_method": "discount__percentage", "pricing_value": 10 },
    { "from": 11, "to": null, "pricing_method": "fixed__price", "pricing_value": 15 }
  ]
}
```
