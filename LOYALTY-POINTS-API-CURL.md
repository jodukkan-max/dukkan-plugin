# Dukkan — Loyalty Points REST API

Base URL: `https://{site}/wp-json/wc/v3`
Auth: WooCommerce REST API (Consumer Key / Consumer Secret)

> Points are **awarded automatically** when an order reaches `Completed` and
> **reversed** on cancellation/refund. This API is for reading configuration,
> checking balances and transaction history, and making manual adjustments.

---

## All endpoints (one table)

| Method | Endpoint | Params | Purpose | Success response |
|--------|----------|--------|---------|------------------|
| `GET` | `/loyalty-points/settings` | — | Read loyalty configuration | Settings object (all keys) |
| `PUT` | `/loyalty-points/settings` | JSON body (partial) | Update configuration | Updated settings object |
| `GET` | `/loyalty-points/balance` | `customer_id` **or** `email` | Read a customer's points balance | `{ "customer_id": 7, "balance": 1250 }` |
| `GET` | `/loyalty-points/ledger` | `customer_id` **or** `email`, `per_page` (optional) | Read a customer's transaction history | `{ "customer_id": 7, "balance": 1250, "entries": [...] }` |
| `POST` | `/loyalty-points/adjust` | JSON body: `customer_id`, `points` (signed), `note` (optional) | Manually add / deduct points | `{ "customer_id": 7, "balance": 1300, "adjusted": 100 }` |

---

## Settings object shape

```json
{
  "enabled": 1,
  "points_per_amount": 1,
  "amount": 1,
  "value_per_point": 0.01,
  "max_redeem_percent": 100,
  "excluded_product_ids": [],
  "excluded_category_ids": [],
  "redeem_with_coupons": 1
}
```

| Field | Type | Notes |
|-------|------|-------|
| `enabled` | integer | `1` enabled, `0` disabled |
| `points_per_amount` | integer | Points earned per `amount` |
| `amount` | number | Monetary amount that earns `points_per_amount` |
| `value_per_point` | number | Monetary value of 1 point at redemption |
| `max_redeem_percent` | integer | Max % of cart subtotal points can cover (`0-100`) |
| `excluded_product_ids` | array | Product IDs excluded from earning |
| `excluded_category_ids` | array | Category IDs excluded from earning |
| `redeem_with_coupons` | integer | `1` allows points + coupon stacking |

---

## cURL (all endpoints)

Set your credentials once:

```bash
SITE="https://example.com"
KEY="ck_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
SECRET="cs_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
```

```bash
# 1. Read settings
curl "$SITE/wp-json/wc/v3/loyalty-points/settings" -u "$KEY:$SECRET"

# 2. Update settings (partial — only sent keys change)
curl -X PUT "$SITE/wp-json/wc/v3/loyalty-points/settings" \
  -u "$KEY:$SECRET" \
  -H 'Content-Type: application/json' \
  -d '{"enabled":1,"points_per_amount":2,"amount":10,"value_per_point":0.05,"max_redeem_percent":50}'

# 3. Read balance (by user ID)
curl "$SITE/wp-json/wc/v3/loyalty-points/balance?customer_id=7" -u "$KEY:$SECRET"

# 4. Read balance (by email)
curl "$SITE/wp-json/wc/v3/loyalty-points/balance?email=customer@example.com" -u "$KEY:$SECRET"

# 5. Read ledger (transaction history)
curl "$SITE/wp-json/wc/v3/loyalty-points/ledger?customer_id=7&per_page=50" -u "$KEY:$SECRET"

# 6. Manually add points
curl -X POST "$SITE/wp-json/wc/v3/loyalty-points/adjust" \
  -u "$KEY:$SECRET" \
  -H 'Content-Type: application/json' \
  -d '{"customer_id":7,"points":100,"note":"Welcome bonus"}'

# 7. Manually deduct points
curl -X POST "$SITE/wp-json/wc/v3/loyalty-points/adjust" \
  -u "$KEY:$SECRET" \
  -H 'Content-Type: application/json' \
  -d '{"customer_id":7,"points":-50,"note":"Refund adjustment"}'
```

---

## Errors

| Status | Code | When |
|--------|------|------|
| `400` | `invalid_points` | `points` is zero or missing on `adjust` |
| `404` | `customer_not_found` | `customer_id` / `email` does not resolve |
| `401` | `woocommerce_rest_cannot_view` | Missing / invalid API auth |
| `403` | `woocommerce_rest_cannot_edit` | Read-only key used on a write endpoint |
