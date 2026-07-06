# Dynamic Pricing REST API — Quick Reference

## Namespace
`dukkan-dynamic-pricing/v1`

## Authentication
**Public** — no authentication required.

## Storage
Rules are stored in **`rp_wcdpd_settings`** (WCDPD plugin option) and appear in **WooCommerce > PricePep** dashboard.

---

## Endpoints

| Method | Route | Description |
|--------|-------|-------------|
| `GET` | `/dukkan-dynamic-pricing/v1/rules` | List all simple rules (paginated, searchable) |
| `GET` | `/dukkan-dynamic-pricing/v1/rules/{uid}` | Get single rule by UID |
| `POST` | `/dukkan-dynamic-pricing/v1/rules` | Create a new simple adjustment rule |
| `PUT` | `/dukkan-dynamic-pricing/v1/rules/{uid}` | Update an existing rule (partial) |
| `DELETE` | `/dukkan-dynamic-pricing/v1/rules/{uid}` | Delete a rule |
| `GET` | `/dukkan-dynamic-pricing/v1/products/search` | Search WooCommerce products |

---

## Pricing Methods (`pricing_method`)

| Key | WCDPD Equivalent | Description |
|-----|-----------------|-------------|
| `discount__amount` | Fixed discount | $X off |
| `discount__percentage` | Percentage discount | X% off |
| `fee__amount` | Fixed fee | $X added |
| `fee__percentage` | Percentage fee | X% added |
| `fixed__price` | Fixed price | Set price to $X |

---

## Product/Category Methods

| Key | Description |
|-----|-------------|
| `in_list` | Rule applies to items in the list |
| `not_in_list` | Rule applies to items NOT in the list |

---

## Cart Conditions

| `type` | Description | `method_option` Options |
|--------|-------------|--------------------------|
| `cart_subtotal` | Cart subtotal | `at_least`, `more_than`, `not_more_than`, `less_than` |
| `cart_quantity` | Total item quantity | `at_least`, `more_than`, `not_more_than`, `less_than` |
| `cart_count` | Distinct cart items | `at_least`, `more_than`, `not_more_than`, `less_than` |
| `cart_weight` | Total cart weight | `at_least`, `more_than`, `not_more_than`, `less_than` |

---

## Example: Create a fixed $5 fee for products 40289, 40372

```json
POST /dukkan-dynamic-pricing/v1/rules

{
  "note": "Fixed $5 fee",
  "pricing_method": "fee__amount",
  "pricing_value": 5,
  "product_uids": [40289, 40372],
  "product_method": "in_list"
}
```

## Response Format

```json
{
  "uid": "rp_wcdpd_abc123",
  "note": "Fixed $5 fee",
  "public_note": "",
  "pricing_method": "fee__amount",
  "pricing_value": 5.0,
  "product_uids": [40289, 40372],
  "product_method": "in_list",
  "product_category_uids": [],
  "product_category_method": "in_list",
  "conditions": []
}
```
