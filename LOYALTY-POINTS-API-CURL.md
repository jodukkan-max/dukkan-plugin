# Dukkan — Loyalty Points REST API (cURL Guide)

Base URL: `https://{site}/wp-json/wc/v3`
Auth: WooCommerce REST API (Consumer Key / Consumer Secret)

> Customer balances are stored per user and every change is written to a ledger.
> Points are **awarded automatically** when an order reaches `Completed` and
> **reversed** on cancellation/refund — this API is for reading configuration,
> checking balances, and manual adjustments.

---

## Endpoint Index

| # | Method | Endpoint | Purpose |
|---|--------|----------|---------|
| 1 | `GET` | `/loyalty-points/settings` | Read loyalty configuration |
| 2 | `PUT` | `/loyalty-points/settings` | Update configuration (partial) |
| 3 | `GET` | `/loyalty-points/balance` | Read a customer's points balance |
| 4 | `GET` | `/loyalty-points/ledger` | Read a customer's transaction history |
| 5 | `POST` | `/loyalty-points/adjust` | Manually add / deduct points |

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

## cURL Examples

Set your credentials once:

```bash
SITE="https://example.com"
KEY="ck_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
SECRET="cs_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
```

### 1. Read settings

```bash
curl "$SITE/wp-json/wc/v3/loyalty-points/settings" -u "$KEY:$SECRET"
```

### 2. Update settings (partial)

```bash
curl -X PUT "$SITE/wp-json/wc/v3/loyalty-points/settings" \
  -u "$KEY:$SECRET" \
  -H 'Content-Type: application/json' \
  -d '{
    "enabled": 1,
    "points_per_amount": 2,
    "amount": 10,
    "value_per_point": 0.05,
    "max_redeem_percent": 50
  }'
```

> Only the keys you send are changed — omitted keys keep their current value.

### 3. Read a customer's balance (by user ID)

```bash
curl "$SITE/wp-json/wc/v3/loyalty-points/balance?customer_id=7" -u "$KEY:$SECRET"
```

```json
{ "customer_id": 7, "balance": 1250 }
```

### 4. Read a customer's balance (by email)

```bash
curl "$SITE/wp-json/wc/v3/loyalty-points/balance?email=customer@example.com" -u "$KEY:$SECRET"
```

### 5. Read a customer's ledger

```bash
curl "$SITE/wp-json/wc/v3/loyalty-points/ledger?customer_id=7&per_page=50" -u "$KEY:$SECRET"
```

```json
{
  "customer_id": 7,
  "balance": 1250,
  "entries": [
    { "id": 42, "order_id": 1188, "points": 120, "type": "earn", "note": "Points earned for order #1188", "created_at": "2026-09-01 10:00:00" },
    { "id": 41, "order_id": 0, "points": -50, "type": "spend", "note": "Points redeemed on order #1188", "created_at": "2026-09-01 09:58:00" }
  ]
}
```

### 6. Manually add points

```bash
curl -X POST "$SITE/wp-json/wc/v3/loyalty-points/adjust" \
  -u "$KEY:$SECRET" \
  -H 'Content-Type: application/json' \
  -d '{
    "customer_id": 7,
    "points": 100,
    "note": "Welcome bonus"
  }'
```

### 7. Manually deduct points

```bash
curl -X POST "$SITE/wp-json/wc/v3/loyalty-points/adjust" \
  -u "$KEY:$SECRET" \
  -H 'Content-Type: application/json' \
  -d '{
    "customer_id": 7,
    "points": -50,
    "note": "Refund adjustment"
  }'
```

---

## Response shapes

| Endpoint | Response |
|----------|----------|
| Get settings | Settings object (all keys) |
| Update settings | Updated settings object |
| Balance | `{ "customer_id": 7, "balance": 1250 }` |
| Ledger | `{ "customer_id": 7, "balance": 1250, "entries": [...] }` |
| Adjust | `{ "customer_id": 7, "balance": 1300, "adjusted": 100 }` |

---

## Errors

| Status | Code | When |
|--------|------|------|
| `400` | `invalid_points` | `points` is zero or missing on `adjust` |
| `404` | `customer_not_found` | `customer_id` / `email` does not resolve |
| `401` | `woocommerce_rest_cannot_view` | Missing / invalid API auth |
| `403` | `woocommerce_rest_cannot_edit` | Read-only key used on a write endpoint |
