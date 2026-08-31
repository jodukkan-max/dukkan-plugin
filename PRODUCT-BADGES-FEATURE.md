# Dukkan — Product Badges (Feature Overview)

> Version: Dukkan plugin v1.0.24+ · Applies to the WooCommerce storefront.

---

## What is a Product Badge?

A **badge** is a small, colored text label (e.g. `SALE`, `NEW`, `-50%`) that is
overlaid on top of a product image, both in the **shop grid** (product loop) and
on the **single product page**.

The store admin creates badges in the Dukkan admin area. The mobile app can
create and manage the exact same badges through the REST API.

---

## Where badges appear

| Surface | Hook |
|---------|------|
| Shop / category grid | `woocommerce_before_shop_loop_item_title` |
| Single product page | `woocommerce_before_single_product_summary` |

Badges are wrapped in a container `<div class="dukkan-badges">` and each badge
is a `<span>` element, e.g.:

```html
<span class="dukkan-badge dukkan-badge--top-left dukkan-badge--rectangular"
      style="background:#e53935;color:#ffffff;">SALE</span>
```

---

## Badge object

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

### Field reference

| Field | Type | Required | Description |
|-------|------|:--------:|-------------|
| `id` | string | — | Unique identifier (`slug-timestamp`). Read-only, generated server-side. |
| `text` | string | ✅ | The label text. May contain `<br>` for a line break (multi-line badges). |
| `shape` | string | — | Badge outline: `rectangular` or `circle`. Default `rectangular`. |
| `background_color` | string | — | Hex background color, e.g. `#e53935`. Default `#e53935`. |
| `text_color` | string | — | Hex text color, e.g. `#ffffff`. Default `#ffffff`. |
| `position` | string | — | Where the badge sits on the image (see below). Default `top-left`. |
| `applied_to` | string | ✅ | Targeting rule (see below). Default `all`. |
| `products` | int[] | — | Product IDs — used when `applied_to = specific_products`. |
| `categories` | int[] | — | Product category term IDs — used when `applied_to = specific_categories`. |
| `tags` | int[] | — | Product tag term IDs — used when `applied_to = specific_tags`. |
| `status` | int | — | `1` = enabled (shown), `0` = disabled (hidden). Default `1`. |

---

## Options explained

### 1. Badge Text (`text`)

The label shown on the badge. Free text.

- Supports `<br>` to break the label onto a new line, e.g. `SALE<br>50% OFF`.
- Required — a badge cannot be saved without text.
- Sanitized to allow only `<br>` (all other HTML is stripped).
- When TranslatePress is active, the text is run through `trp_translate()`,
  so each language can have its own translated badge text.

### 2. Shape (`shape`)

Controls the visual outline of the badge.

| Value | Look |
|-------|------|
| `rectangular` | Classic rounded rectangle (default). |
| `circle` | Circular badge (best for short labels like `NEW`, `-20%`). |

### 3. Colors

| Field | Meaning |
|-------|---------|
| `background_color` | Fill color of the badge. Any hex color. |
| `text_color` | Color of the badge text. Any hex color. |

### 4. Position (`position`)

Where the badge is anchored relative to the product image.

| Value | Corner |
|-------|--------|
| `top-left` | Top left (default) |
| `top-right` | Top right |
| `bottom-left` | Bottom left |
| `bottom-right` | Bottom right |

### 5. Applied To (`applied_to`)

Determines **which products** display the badge.

| Value | Meaning | Uses field |
|-------|---------|-----------|
| `all` | Every product | — |
| `specific_products` | Only the selected products | `products[]` |
| `specific_categories` | All products in the selected categories | `categories[]` |
| `specific_tags` | All products with the selected product tags | `tags[]` |

**Matching rules:**
- `all` → always applies.
- `specific_products` → applies if the product ID is in `products`.
- `specific_categories` → applies if the product belongs to any of the listed
  category term IDs (`has_term`).
- `specific_tags` → applies if the product has any of the listed product tag
  term IDs (`has_term`).

If `applied_to` is one of the "specific_*" values but its ID list is empty,
the badge will **not** show anywhere.

### 6. Status (`status`)

A simple on/off switch.

- `1` — badge is active and rendered.
- `0` — badge is disabled; it stays saved but is not shown on the storefront.

Disabled badges are filtered out before rendering.

---

## Multiple badges on one product

A product can match several badges at once. All matching, enabled badges are
rendered together inside one `.dukkan-badges` container (each in its own corner,
so they do not overlap).

---

## Translation (TranslatePress)

If TranslatePress is installed:

- Badge text is translated per language via `trp_translate()`.
- Each text line (split on `<br>`) is pre-registered into the TranslatePress
  dictionary when a badge is saved, so the strings appear in
  **TranslatePress → String Translation** without needing a frontend visit.

No action is needed from the app developer — this is handled automatically.

---

## Storage

All badges are stored in a single WordPress option:

```
dukkan_product_badges
```

Stored as an associative array keyed by `id`. The REST API reads/writes this
option directly.

---

## REST API

Badges are fully manageable over REST (WooCommerce-authenticated, `wc/v3`).
See `PRODUCT-BADGES-API-CURL.md` for the full endpoint list and cURL examples.

| Method | Endpoint |
|--------|----------|
| `GET` | `/product-badges` |
| `GET` | `/product-badges/{id}` |
| `POST` | `/product-badges` |
| `PUT` | `/product-badges/{id}` |
| `DELETE` | `/product-badges/{id}` |
| `POST` | `/product-badges/{id}/duplicate` |
| `POST` | `/product-badges/{id}/toggle` |
