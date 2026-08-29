# Dukkan Product Add-Ons API — cURL Guide

Full `curl` reference for the Product Add-Ons REST API (namespace `wc/v3`).

**Base URL:** `https://{site}/wp-json/wc/v3`
**Auth:** WooCommerce REST API — Basic auth with `consumer_key:consumer_secret` (the `-u ck:cs` part).

> Replace `{site}` with your store domain and `ck:cs` with your real Consumer Key / Consumer Secret.

---

## Endpoint index

| Goal | Method | Route |
|------|--------|-------|
| List groups | GET | `/product-addons` |
| Get one group | GET | `/product-addons/{group_id}` |
| Create group | POST | `/product-addons` |
| Update group (rename / status / applied_to) | PATCH | `/product-addons/{group_id}` |
| Update fields (add / edit) | PUT | `/product-addons/{group_id}` |
| Delete group | DELETE | `/product-addons/{group_id}` |
| Duplicate group | POST | `/product-addons/{group_id}/duplicate` |
| Toggle (enable/disable) | POST | `/product-addons/{group_id}/toggle` |
| Reorder fields | POST | `/product-addons/{group_id}/reorder-fields` |
| Delete one field | DELETE | `/product-addons/{group_id}/fields/{field_id}` |

---

## Migration: Before (legacy) vs After (new)

### Routes

| Action | Before (legacy) | After (new) | Change |
|--------|-----------------|-------------|--------|
| List groups | `GET /wc/v3/get_groups/` | `GET /wc/v3/product-addons` | Route + response |
| Get one group | `GET /wc/v3/get_group/` | `GET /wc/v3/product-addons/{id}` | Route + id in path |
| Create group | `POST /wc/v3/create_group/` | `POST /wc/v3/product-addons` | Route |
| Update group | `PUT/PATCH /wc/v3/update_group/` | `PUT/PATCH /wc/v3/product-addons/{id}` | Route + id in path + merge semantics |
| Delete group | `DELETE /wc/v3/delete_group/` | `DELETE /wc/v3/product-addons/{id}` | Route + id in path |
| Duplicate group | `POST /wc/v3/duplicate_group/` | `POST /wc/v3/product-addons/{id}/duplicate` | Route + id in path |
| Toggle status | — (not available) | `POST /wc/v3/product-addons/{id}/toggle` | NEW |
| Reorder fields | — (not available) | `POST /wc/v3/product-addons/{id}/reorder-fields` | NEW |
| Delete one field | — (not available) | `DELETE /wc/v3/product-addons/{id}/fields/{field_id}` | NEW |

### How the id is passed

| Before (legacy) | After (new) |
|-----------------|-------------|
| `group_id` sent in the request body/query param (e.g. `{"group_id":"..."}`) | `id` in the URL path (e.g. `/product-addons/{id}`) |

### Response shapes

| Action | Before (legacy) | After (new) |
|--------|-----------------|-------------|
| List groups | Keyed object `{ "slug-123": {...}, ... }` | JSON array `[ {...}, {...} ]` |
| Get one group | Raw group object | Group object (with `id`, products as `{id, name}`) |
| Create | `{ "success": true, "message": "...", "data": {...} }` | `201` + group object |
| Update | `{ "success": true, "message": "...", "data": {...} }` | Group object |
| Delete | `{ "success": true, "message": "...", "group_id": "...", "deleted": {...} }` | `{ "deleted": true, "id": "..." }` |
| Duplicate | `{ "success": true, "message": "...", "data": { "id": "...", "group": {...} } }` | `201` + group object |
| Toggle | — | `{ "id": "...", "status": 1 }` |
| Reorder fields | — | Group object |
| Delete one field | — | `{ "deleted": true, "group_id": "...", "field_id": "..." }` |

### Behavior changes (important)

| Topic | Before (legacy) | After (new) |
|-------|-----------------|-------------|
| Partial update | Sending only some keys wiped `description`, `categories`, `products`, `status`, and deleted un-sent fields | Merge semantics — omitted keys keep their value; `fields` only touched when the `fields` key is sent |
| No-change save | Returned a false `500` error | Returns success (no more false failure) |
| `required` missing | PHP notice / stored raw | Normalized to `0`/`1` |
| Duplicate ids | Copy shared the original's field/option ids | Copy gets brand-new ids everywhere (fully independent) |
| Sanitization | Minimal | `price` cast to float, `color` validated hex, `image_url` resolved server-side |

---

## 1. List all groups

```bash
curl -X GET "https://{site}/wp-json/wc/v3/product-addons" \
  -u ck:cs
```

Response: a JSON array of group objects.

---

## 2. Get a single group

```bash
curl -X GET "https://{site}/wp-json/wc/v3/product-addons/{group_id}" \
  -u ck:cs
```

---

## 3. Create a group (with fields)

```bash
curl -X POST "https://{site}/wp-json/wc/v3/product-addons" \
  -u ck:cs \
  -H 'Content-Type: application/json' \
  -d '{
    "group_name": "Gift Options",
    "description": "Optional extras",
    "applied_to": "specific",
    "categories": [12, 34],
    "products": [101, 102],
    "status": 1,
    "fields": [
      { "type": "text", "title": "Gift note", "width": "100%", "required": 1, "price": 0 },
      {
        "type": "select",
        "title": "Wrap style",
        "width": "50%",
        "required": 0,
        "price": 0,
        "options": [
          { "label": "Standard", "price": 0 },
          { "label": "Premium", "price": 2.5 }
        ]
      }
    ]
  }'
```

Returns `201` with the created group (including server-generated ids).

---

## 4. Update group — rename / status / applied_to (fields untouched)

```bash
curl -X PATCH "https://{site}/wp-json/wc/v3/product-addons/{group_id}" \
  -u ck:cs \
  -H 'Content-Type: application/json' \
  -d '{
    "group_name": "New Name",
    "status": 0
  }'
```

> Only the keys you send are changed. Omitting `fields` leaves all fields exactly as they are.

---

## 5. Update group — add / edit fields (send the full fields list)

`fields` is replaced **only when you send the `fields` key**. Send every existing field (with its current `id` so it is preserved) plus the new/edited field.

```bash
curl -X PUT "https://{site}/wp-json/wc/v3/product-addons/{group_id}" \
  -u ck:cs \
  -H 'Content-Type: application/json' \
  -d '{
    "fields": [
      { "id": "field_1", "type": "select", "title": "Wrap style", "width": "50%", "required": 0, "price": 0,
        "options": [ { "id": "opt_1", "label": "Standard", "price": 0 } ] },
      { "type": "text", "title": "Gift note", "width": "100%", "required": 0, "price": 0 }
    ]
  }'
```

Rules:

- A field with a known `id` is updated in place.
- A field with no `id` (or an unknown `id`) is created new with a server-generated id.
- `fields` must be a JSON **array** — the order is preserved.

---

## 6. Delete a group

```bash
curl -X DELETE "https://{site}/wp-json/wc/v3/product-addons/{group_id}" \
  -u ck:cs
```

---

## 7. Duplicate a group

```bash
curl -X POST "https://{site}/wp-json/wc/v3/product-addons/{group_id}/duplicate" \
  -u ck:cs
```

Returns `201` with the new group. New ids are generated for the group, all fields, and all options — the copy is fully independent.

---

## 8. Toggle a group (enable / disable)

```bash
curl -X POST "https://{site}/wp-json/wc/v3/product-addons/{group_id}/toggle" \
  -u ck:cs \
  -H 'Content-Type: application/json' \
  -d '{"status": 0}'
```

`status`: `1` = enabled, `0` = disabled.

---

## 9. Reorder fields

```bash
curl -X POST "https://{site}/wp-json/wc/v3/product-addons/{group_id}/reorder-fields" \
  -u ck:cs \
  -H 'Content-Type: application/json' \
  -d '{"field_order": ["field_2", "field_1", "field_3"]}'
```

Any field not listed is appended to the end. Returns the updated group.

---

## 10. Delete a single field

```bash
curl -X DELETE "https://{site}/wp-json/wc/v3/product-addons/{group_id}/fields/{field_id}" \
  -u ck:cs
```

---

# Field reference

### Supported field types

`text`, `textarea`, `number`, `date`, `file`, `select`, `radio`, `checkbox`, `image`, `color`

### Common field shape

```json
{
  "id": "string (omit on create)",
  "type": "text | textarea | number | date | file | select | radio | checkbox | image | color",
  "title": "string",
  "width": "100% | 75% | 50% | 25%",
  "required": 1,
  "price": 0,
  "options": []
}
```

### Options per type

- **select / radio / checkbox** — each option has `label` and `price`.
- **color** — each option has `label`, `color` (hex), `color_code` (hex), `price`.
- **image** — each option has `label`, `image_id` (attachment id), `price`. The `image_url` is resolved server-side and returned in responses.
- **text / textarea / number / date / file** — no options.

---

# Full example — create a group with every field type

```bash
curl -X POST "https://{site}/wp-json/wc/v3/product-addons" \
  -u ck:cs \
  -H 'Content-Type: application/json' \
  -d '{
    "group_name": "Customization",
    "description": "All field types demo",
    "applied_to": "all",
    "status": 1,
    "fields": [
      { "type": "text",     "title": "Name",        "width": "100%", "required": 1, "price": 0 },
      { "type": "textarea", "title": "Message",     "width": "100%", "required": 0, "price": 0 },
      { "type": "number",   "title": "Quantity",    "width": "50%",  "required": 0, "price": 0 },
      { "type": "date",     "title": "Delivery",    "width": "50%",  "required": 0, "price": 0 },
      { "type": "file",     "title": "Upload",      "width": "100%", "required": 0, "price": 0 },
      {
        "type": "select",
        "title": "Size",
        "width": "50%",
        "required": 0,
        "price": 0,
        "options": [
          { "label": "Small", "price": 0 },
          { "label": "Large", "price": 3 }
        ]
      },
      {
        "type": "radio",
        "title": "Priority",
        "width": "50%",
        "required": 0,
        "price": 0,
        "options": [
          { "label": "Standard", "price": 0 },
          { "label": "Express",  "price": 5 }
        ]
      },
      {
        "type": "checkbox",
        "title": "Extras",
        "width": "50%",
        "required": 0,
        "price": 0,
        "options": [
          { "label": "Gift wrap", "price": 2 },
          { "label": "Card",      "price": 1 }
        ]
      },
      {
        "type": "color",
        "title": "Ribbon color",
        "width": "50%",
        "required": 0,
        "price": 0,
        "options": [
          { "label": "Red",   "color": "#d63638", "color_code": "#d63638", "price": 0 },
          { "label": "Blue",  "color": "#2271b1", "color_code": "#2271b1", "price": 0 }
        ]
      },
      {
        "type": "image",
        "title": "Pattern",
        "width": "50%",
        "required": 0,
        "price": 0,
        "options": [
          { "label": "Floral", "image_id": 42736, "price": 1 },
          { "label": "Plain",  "image_id": 42737, "price": 0 }
        ]
      }
    ]
  }'
```

---

# Error responses

| Status | Code | Meaning |
|--------|------|---------|
| 400 | `missing_group_name` | `group_name` missing/empty on create |
| 400 | `missing_group_id` | group id missing |
| 400 | `missing_field_order` | `field_order` missing/empty on reorder |
| 404 | `not_found` | group does not exist |
| 404 | `field_not_found` | field does not exist |
| 401 | `woocommerce_rest_cannot_view` | missing/invalid WooCommerce auth |
