# Dukkan — Product Badges REST API (cURL Guide)

Base URL: `https://{site}/wp-json/wc/v3`
Auth: WooCommerce REST API (Consumer Key / Consumer Secret)

> Searching for products, categories or tags is **not** part of this API. Use the
> native WooCommerce endpoints instead:
> - `GET /wc/v3/products`
> - `GET /wc/v3/products/categories`
> - `GET /wc/v3/products/tags`

---

## Endpoint Index

| # | Method | Endpoint | Purpose |
|---|--------|----------|---------|
| 1 | `GET` | `/product-badges` | List all badges |
| 2 | `GET` | `/product-badges/{id}` | Get one badge |
| 3 | `POST` | `/product-badges` | Create a badge |
| 4 | `PUT` | `/product-badges/{id}` | Update a badge (partial) |
| 5 | `DELETE` | `/product-badges/{id}` | Delete a badge |
| 6 | `POST` | `/product-badges/{id}/duplicate` | Duplicate a badge |
| 7 | `POST` | `/product-badges/{id}/toggle` | Enable / disable a badge |

---

## Badge object shape

```json
{
  "id": "sale-1710000000",
  "text": "SALE<br>50% OFF",
  "shape": "rectangular",
  "background_color": "#e53935",
  "text_color": "#ffffff",
  "position": "top-left",
  "applied_to": "all",
  "products": [],
  "categories": [],
  "tags": [],
  "status": 1
}
```

| Field | Type | Values / Notes |
|-------|------|----------------|
| `id` | string | Read-only. `slug-timestamp`. |
| `text` | string | **Required**. May contain `<br>` for line breaks. |
| `shape` | string | `rectangular` \| `circle` |
| `background_color` | string | Hex, e.g. `#e53935` |
| `text_color` | string | Hex, e.g. `#ffffff` |
| `position` | string | `top-left` \| `top-right` \| `bottom-left` \| `bottom-right` |
| `applied_to` | string | `all` \| `specific_products` \| `specific_categories` \| `specific_tags` |
| `products` | array | Product IDs (write) → `{id, name}` objects (read) |
| `categories` | array | Category term IDs (write) → `{id, name}` objects (read) |
| `tags` | array | Product tag term IDs (write) → `{id, name}` objects (read) |
| `status` | integer | `1` enabled, `0` disabled |

> On **read**, `products`, `categories` and `tags` are hydrated to `{ id, name }`.
> On **write**, send plain integer IDs.

---

## cURL Examples

Set your credentials once:

```bash
SITE="https://example.com"
KEY="ck_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
SECRET="cs_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
```

### 1. List all badges

```bash
curl "$SITE/wp-json/wc/v3/product-badges" -u "$KEY:$SECRET"
```

### 2. Get one badge

```bash
curl "$SITE/wp-json/wc/v3/product-badges/sale-1710000000" -u "$KEY:$SECRET"
```

### 3. Create a badge (rectangular, top-right, all products)

```bash
curl -X POST "$SITE/wp-json/wc/v3/product-badges" \
  -u "$KEY:$SECRET" \
  -H 'Content-Type: application/json' \
  -d '{
    "text": "SALE<br>50% OFF",
    "shape": "rectangular",
    "background_color": "#e53935",
    "text_color": "#ffffff",
    "position": "top-right",
    "applied_to": "all",
    "status": 1
  }'
```

### 4. Create a circular badge for specific categories

```bash
curl -X POST "$SITE/wp-json/wc/v3/product-badges" \
  -u "$KEY:$SECRET" \
  -H 'Content-Type: application/json' \
  -d '{
    "text": "NEW",
    "shape": "circle",
    "background_color": "#16a34a",
    "text_color": "#ffffff",
    "position": "bottom-left",
    "applied_to": "specific_categories",
    "categories": [12, 34]
  }'
```

### 5. Create a badge for specific product tags

```bash
curl -X POST "$SITE/wp-json/wc/v3/product-badges" \
  -u "$KEY:$SECRET" \
  -H 'Content-Type: application/json' \
  -d '{
    "text": "HOT",
    "shape": "circle",
    "background_color": "#e11d48",
    "text_color": "#ffffff",
    "position": "top-left",
    "applied_to": "specific_tags",
    "tags": [7, 9]
  }'
```

### 6. Update a badge (partial — only sent keys change)

```bash
curl -X PUT "$SITE/wp-json/wc/v3/product-badges/sale-1710000000" \
  -u "$KEY:$SECRET" \
  -H 'Content-Type: application/json' \
  -d '{
    "text": "BLACK FRIDAY",
    "status": 0
  }'
```

### 7. Toggle a badge off / on

```bash
# Disable
curl -X POST "$SITE/wp-json/wc/v3/product-badges/sale-1710000000/toggle" \
  -u "$KEY:$SECRET" \
  -H 'Content-Type: application/json' \
  -d '{"status": 0}'

# Enable
curl -X POST "$SITE/wp-json/wc/v3/product-badges/sale-1710000000/toggle" \
  -u "$KEY:$SECRET" \
  -H 'Content-Type: application/json' \
  -d '{"status": 1}'
```

### 8. Duplicate a badge

```bash
curl -X POST "$SITE/wp-json/wc/v3/product-badges/sale-1710000000/duplicate" \
  -u "$KEY:$SECRET"
```

### 9. Delete a badge

```bash
curl -X DELETE "$SITE/wp-json/wc/v3/product-badges/sale-1710000000" \
  -u "$KEY:$SECRET"
```

---

## Response shapes

| Endpoint | Response |
|----------|----------|
| List | JSON array of badge objects |
| Get | Single badge object |
| Create | `201` + badge object |
| Duplicate | `201` + badge object |
| Update | Updated badge object |
| Toggle | `{ "id": "...", "status": 0 }` |
| Delete | `{ "deleted": true, "id": "..." }` |

---

## Errors

| Status | Code | When |
|--------|------|------|
| `400` | `missing_text` | `text` is missing or empty |
| `400` | `no_badge` | Badge `{id}` not provided |
| `404` | `not_found` | Badge does not exist |
| `401` | `woocommerce_rest_cannot_view` | Missing / invalid API auth |
| `403` | `woocommerce_rest_cannot_edit` | Read-only key used on a write endpoint |
