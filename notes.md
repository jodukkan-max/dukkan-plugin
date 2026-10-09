# Dukkan Plugin — Work Log & Structure

> Last updated: v1.0.51 — October 9, 2026 — WooCommerce API key revoke endpoint

---

## Recent Changes

### v1.0.51 — Revoke WooCommerce API keys

- New `DELETE /dukkan-woo-extended/v1/rest-api-keys` route (static-key auth). Deletes a WooCommerce REST API key by matching the plaintext `consumer_key` against WooCommerce's `truncated_key` column (last 7 chars). Lets the app revoke a store's keys when the merchant deletes a website.

### v1.0.50 — Elementor widget settings API (Heading + Text Editor)

- **Why**: Elementor has no REST API for widget settings. The app's mobile website builder needs to read/write them, so the plugin now exposes them. **New API class** `Dukkan_Plugin_Elementor_API` (`api/class-dukkan-plugin-elementor-api.php`), namespace `dukkan-elementor/v1`, WooCommerce-key authenticated (same `wc_rest_check_manager_permissions('settings', …)` as the other APIs; writes also need `edit_post` on the page). Routes are only registered when Elementor is active. Registered in `includes/class-dukkan-plugin.php` (`define_elementor_api_hooks()`).

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/pages` | Pages built with Elementor (publish/draft/private/future), front page first: `{id, title, status, link, is_front_page, modified}` |
| GET | `/pages/{id}` | Same fields + `elements`: the page's full raw `_elementor_data` tree (read-only; containers, legacy section/column, every widget type). Lets the app render the real page |
| GET | `/widgets/heading/controls[?tab=content\|style]` | Content + Style control schema straight from Elementor (`key`, `type`, `label`, `tab`, `section`, `default`, `options`, `condition`, `size_units`, `range`, `return_value`, `device` for responsive variants, `group`/`group_toggle` for popover groups, `ui_only` for headings/notices) + `sections` list |
| GET | `/pages/{id}/headings` | Every heading widget on an Elementor page: `{id, widget_type, settings, globals}` |
| GET | `/pages/{id}/headings/{element_id}` | One heading's Content + Style settings |
| PATCH (also PUT/POST) | `/pages/{id}/headings/{element_id}` | Body `{"settings": {...}}`. Merges; `null` removes a key (falls back to the Elementor default). All-or-nothing: any unknown key, Advanced-tab key or invalid value → `400 dukkan_elementor_invalid_settings` with a per-key `errors` map and nothing saved |
| GET/PATCH | `/pages/{id}/text-editors[/{element_id}]` (and `/widgets/text-editor/controls`) | Same four routes for `text-editor`. On ankur5: 8 Content + 66 Style controls across 3 sections (Text Editor / Text Editor / Drop Cap); includes the Rey skin (`_skin`, `rey_dynamic_source`), drop cap, columns, typography, text shadow, links, toggle-text controls |
| GET/PATCH | `/pages/{id}/images[/{element_id}]` (and `/widgets/image/controls`) | Same four routes for `image`. Content: skin/dynamic image, image (media), resolution, caption, link/lightbox. Style: alignment, width/max-width/height, object-fit/position, opacity, hover animation, border, radius, caption typography/spacing. Added `hover_animation` validation (same as `animation`) |
| GET/PATCH | `/pages/{id}/buttons[/{element_id}]` (and `/widgets/button/controls`) | Same four routes for the Elementor `button` widget, which Rey extends (`rey-core/inc/elementor/custom/button.php`): `button_type` gained REY styles (link/primary/secondary/outline/underline/dashed…), icon (`icons` type — added validation `{value,library}`), icon position/effect/size, block/stretch button (`rey_btn_block`), plus standard typography, solid/gradient background, box shadow, hover, border, padding |
| GET/PATCH | `/pages/{id}/rey-buttons[/{element_id}]` (and `/widgets/reycore-button-skew/controls`) | Same four routes for Rey's `reycore-button-skew` widget (`button-skew/button-skew.php`): skew button style (filled/outline × left/right), text, link, align, typography, colors, border, padding |
| GET/PATCH | `/pages/{id}/containers[/{element_id}]` (and `/widgets/container/controls`) | Same four routes for Elementor **Containers** (`elType: container`). Content lives in the `layout` tab (container_type flex/grid, content_width, min_height, flex_direction/justify/align_items/gap/wrap/align_content, overflow, html_tag); Style = background (classic/gradient/image), border, box shadow. Added `gaps` validation (`{column,row,isLinked,unit}`) and `'layout'` to `TABS`; `get_widget_controls`/`collect_widgets`/`apply_settings`/`format_widget` now handle `elType: container` |
| GET/PATCH | `/pages/{id}/rey-sliders[/{element_id}]` (and `/widgets/reycore-basic-slider/controls`) | Same four routes for Rey's `reycore-basic-slider`. Content: source (images/custom), slides repeater, height, autoplay/duration/pause, infinite, transition/direction/speed, caption animation, arrows/dots. Style: content width, position/align, title+subtitle typography+color, button style/typography/colors, arrows, dots. Added `repeater` (validates rows against `fields`, structural fields like `_id`/`tabs`/`tab`/`rey-query` pass through leniently) and `gallery` (array of `{id,url}`) validation |
| GET/PATCH | `/pages/{id}/rey-grids[/{element_id}]` (and `/widgets/reycore-product-grid/controls`) | Same four routes for Rey's `reycore-product-grid` (grid + carousel skins). Content: skin, per_row, limit, query_type, orderby/order, image_size, hide_out_of_stock, component toggles (category/price/ratings/ATC). Style: alignment, colors, grid gaps, product box (bg/border/radius/padding), title color/typography |
| GET/PATCH | `/pages/{id}/rey-carousels[/{element_id}]` (and `/widgets/reycore-carousel/controls`) | Same four routes for Rey's `reycore-carousel` (generic media/custom carousel). Content: source (images/custom/posts/categories/product_cat/reviews/attributes), `carousel_items` repeater (image/video/captions/title/subtitle/label/button), items_to_show, gap, direction, infinite, autoplay, arrows, dots. Style: card layout, radius, media fit/radius, title/subtitle/button styles, arrows, dots |
| GET | `/catalog[?type=categories|tags|attributes|attribute_terms|loop_skins][&attribute=pa_x][&page][&per_page]` | Read-only WooCommerce taxonomy data for the app's `rey-query`/`rey-ajax-list` pickers: product categories/tags (term id+label), product attributes (`id,label,slug` where slug is `pa_*`), attribute terms (via `attribute=pa_x` slug or `attribute_id`), and Rey loop skins (`woocommerce_loop->get_skins_list()`). Added `select2`/`rey-query`/`rey-ajax-list` validation (array of ids, or single id) |
| PUT/POST | `/pages/{id}/elements` | Body `{"elements": [...]}` — replaces the page's whole `_elementor_data` tree (structure edits: add/remove/move/duplicate sections & widgets). Structurally validated (elType, unique ids, widgetType, settings shape); any error → `400 dukkan_elementor_invalid_elements` with per-element `errors` and nothing saved. Empty array = clear page |

- **Schema is live, not hardcoded**: built from `Widget_Base::get_controls()`, so it includes theme injections (Rey "Special Styles": text outline, vertical text, parent-hover, word styles) and responsive keys (`align_tablet`, `align_mobile`, …) for the site's active breakpoints. On ankur5 (Elementor 4.1.1 + Rey): 9 Content + 147 Style controls in 3 sections.
- **Gotcha — optimized control loading**: on the frontend Elementor strips labels/options and drops all Style controls (`Core\Frontend\Performance::should_optimize_controls()`); it doesn't during REST requests (`REST_REQUEST` defined), which is why this must stay a REST endpoint.
- **Validation per control type**: text/textarea (`wp_kses_post`, so `<mark>`/`<br>` survive, `<script>` doesn't), number (min/max), select/choose (must be an option key; `''` is accepted as "reset"), switcher/popover_toggle (`''` or `return_value`), color (#hex 3/4/6/8, rgb(a), hsl(a)), slider `{unit∈size_units, size}`, dimensions `{unit, top…left as strings, isLinked}`, url, media, text_shadow/box_shadow, font (any well-formed family name — not checked against `Elementor\Fonts`, which only lists 7 system fonts when Elementor's Google Fonts are off, as on Rey sites), animation (animate.css slug or `''`).
- **Tabs**: `TABS = ['content','style','advanced']` — the Advanced tab (Layout / Motion Effects / Background / Responsive) is read+written for every widget.
- **Globals**: writing a value removes its `__globals__` link (and the group's link, e.g. `typography_typography` for any `typography_*` field), otherwise Elementor keeps rendering the kit's global color/typography. Popover groups only apply when their toggle is saved too (`typography_typography: "custom"`, `text_stroke_text_stroke_type: "yes"`, `text_shadow_text_shadow_type: "yes"`) — the API doesn't set these implicitly; the app sends them.
- **Edits apply to all devices** (product decision, 2026-10-04): writing or `null`-resetting a base key (`align`, `typography_font_size`, …) also removes its per-device overrides and their globals (`align_tablet`, `align_mobile`, `typography_font_size_widescreen`, … for the site's active breakpoints), otherwise Elementor would keep showing the old value on those devices. A variant sent explicitly in the same request is kept. Note Elementor 4 heading `align` options are `start/center/end/justify` (not `left/right`).
- **Saving** goes through `$document->save(['elements' => …])`, so Elementor stores a revision and regenerates the page CSS. Pages are still served from the nginx fastcgi cache (30 min) until it expires/purges.
- **Extending**: add `'<route-slug>' => '<widget type>'` to `Dukkan_Plugin_Elementor_API::WIDGETS` to expose another widget with the same routes, add a `sanitize_value()` case for any control type it uses that isn't supported yet, and add the matching `'<widget type>': '<route-slug>'` entry to the app's `ElementorApi.widgetRoutes`. Full step-by-step guide (both sides, deploy + test recipes): app repo `notes.md` §21 "How To: Website Builder ↔ Elementor wiring".
- **Verified** on ankur5 via `wp eval-file` with `rest_do_request` against a temporary draft page (deleted afterwards): schema, list, patch + stored `_elementor_data`, globals removal, all-or-nothing rejection, null reset, 404 (bad element / non-Elementor post), 401 (anonymous read and write).
- **Deployed to ankur5 only (2026-10-04)**, hot-patched on top of the installed v1.0.49 (WP still reports 1.0.49): copied `api/class-dukkan-plugin-elementor-api.php`, `includes/class-dukkan-plugin.php`, `notes.md` (www-data, 644); previous `class-dukkan-plugin.php` backed up to `/root/class-dukkan-plugin.php.bak-20261004`. Verified over real HTTPS: anonymous GET/PATCH → 401; authenticated GET schema → 156 labelled controls (9 content + 147 style); `pages/192/headings` (front page) → 13 headings. Other sites get it only after the formal v1.0.50 release (version bump + zip + tag + GitHub release).
- **Also deployed to thecoach-jo.com (2026-10-04)** — docroot `/var/www/soccer.dukkan.pro`, which runs the Elementor Dukkan fork **v1.0.2** (ankur5 has v1.0.3). Full plugin synced from local (dukkan-plugin v1.0.48 → v1.0.49 + the Elementor API file); whole folder backed up to `/root/dukkan-plugin-soccer-backup-20261004-131035.tar.gz`. Verified over real HTTPS: anonymous routes → 401; `/pages` → 6 pages (front 192); front page → 18 headings + 1 text-editor; heading controls schema → 156 controls / 3 sections (fork v1.0.2 is API-compatible).
- **App connection (2026-10-04)**: the app's website builder opens the store's real Elementor page (`/pages` → front page → `/pages/{id}`), lets the merchant edit **headings, text-editors, images, buttons and Rey skew-buttons** (Content + Style + Advanced), Publish PATCHes only the changed keys per widget, and now also **saves structure edits** (add/remove/move/duplicate sections & widgets) via `/pages/{id}/elements` full-tree replace when the tree changed. The app's `BuilderNode` round-trips `_elementor_data` losslessly (preserves `isInner`, `isLocked`, etc.). Text-editor `editor` is shown as plain text in the app and re-wrapped to `<p>`/`<br>` on save. Other widget types are still view-only (settings), though they can be moved/deleted. Verified end-to-end on ankur5 + thecoach-jo.com against temporary draft pages (then deleted).
- **Gotcha — testing via `wp eval`**: outside a real REST request `REST_REQUEST` isn't defined, so Elementor's optimized loading kicks in and the schema comes back with ~218 unlabelled controls including Advanced sections. Always verify the schema over HTTP.

### v1.0.49 — Return auth code from store-connection handshake

- `dukkan-woo-extended/v1/request-store-connection-auth-code` now returns `auth_code` in its response (was `{success: true}` only). This lets the automated "Build New Website" flow complete the key-generation handshake without a human reading the code from WP admin. The code remains single-use (`dukkan_plugin_auth_code_permission_callback` deletes it on first successful use). `api/woo-extended/class-dukkan-woo-extended-api.php`

### v1.0.48 — Fix custom order-status registration (critical)

- **Critical bug**: custom order statuses (`ready-delivery`, `out-for-delivery`, `with-carrier`, etc.) were **never registered** with WooCommerce. `Dukkan_Plugin_WooCommerce::__construct` added a `plugins_loaded` hook, but the class is instantiated inside `run_dukkan_plugin()` (which itself runs on `plugins_loaded`), so the nested hook never fired. The webhook then called `update_status('out-for-delivery')` with an unregistered status → WooCommerce silently fell back to `pending`, and the "cancel unpaid orders" cron cancelled real Completed orders.
- **Fix**: hook `register_custom_order_statuses()` directly on `init` (and the `wc_order_statuses` filter directly), removing the dead `plugins_loaded` re-hook. `admin/class-dukkan-plugin-woocommerce.php`
- **Impact**: this caused the iamkbeauty.shop incident where 6 Completed orders were wrongly cancelled. Those orders were restored manually.

### v1.0.47 — SCANNED_BY_DRIVER_AND_IN_CAR → Ready For Delivery

- **Status-map tweak**: `SCANNED_BY_DRIVER_AND_IN_CAR` ("Picked") now maps to `ready-delivery` (Ready For Delivery) instead of `out-for-delivery`. `api/webhook/woo/class-dukkan-woo-webhook.php`

### v1.0.46 — New LogesTechs-aligned order statuses (At Sorting Center / Partially Delivered / Delivered To Sender)

- **3 new custom statuses** added to the default seed: `at-sorting-center` ("At Sorting Center"), `partially-delivered` ("Partially Delivered"), `delivered-to-sender` ("Delivered To Sender").
- **Idempotent migration** `Dukkan_Plugin_Activator::maybe_migrate_statuses()` appends any missing default statuses (matched by slug) to existing installs without removing user-created statuses. Hooked on `plugins_loaded`. `includes/class-dukkan-plugin-activator.php`, `dukkan-plugin.php`
- **Status map re-aligned**: `SCANNED_BY_HANDLER_AND_UNLOADED` + `MOVED_TO_SHELF_AND_OUT_OF_HANDLER_CUSTODY` → `at-sorting-center`; `PARTIALLY_DELIVERED` → `partially-delivered`; `DELIVERED_TO_SENDER` → `delivered-to-sender`. `api/webhook/woo/class-dukkan-woo-webhook.php`

### v1.0.45 — Shipping status webhook: full LogesTechs status map + fixes

- **Full status map**: `dukkan_plugin_get_woo_order_status_map()` now maps the complete LogesTechs status vocabulary (PENDING_CUSTOMER_CARE_APPROVAL, APPROVED_BY_CUSTOMER_CARE_AND_WAITING_FOR_DISPATCHER, ASSIGNED_TO_DRIVER_AND_PENDING_APPROVAL, ACCEPTED_BY_DRIVER_AND_PENDING_PICKUP, MOVED_TO_SHELF_AND_OUT_OF_HANDLER_CUSTODY, OUT_FOR_DELIVERY, POSTPONED_DELIVERY, COMPLETED, PARTIALLY_DELIVERED, RETURNED_BY_RECIPIENT, DELIVERED_TO_SENDER, FAILED, LOST, DAMAGED, REJECTED_BY_DRIVER_AND_PENDING_MANGEMENT, OPENED_ISSUE_AND_WAITING_FOR_MANAGEMENT, TRANSFERRED_OUT, EXPORTED_TO_THIRD_PARTY, SWAPPED, BROUGHT) to the correct WooCommerce/Dukkan statuses — previously only 4 were handled and `OUT_FOR_DELIVERY` was dropped.
- **Order lookup hardened**: falls back to matching by `_logestechs_barcode` order meta when the invoice number isn't a WooCommerce order ID.
- **Notes/postponed date**: the platform's `notes` + `postponedDate` are now appended to the WooCommerce order note (so failed/postponed reasons are visible), and `packageId` is properly captured + logged (was undefined).
- **Barcode persisted** to `_logestechs_barcode` order meta for future lookups. `api/webhook/woo/class-dukkan-woo-webhook.php`

### v1.0.44 — AI Translation: gettext search + Elementor templates

- **Gettext search**: `translatepress-gettext-original-strings` now accepts an optional `search` param that filters by `original LIKE %query%` (safe `$wpdb->esc_like()`), so the mobile app's Strings tab can do full-domain server-side search (not just the loaded page). `api/class-dukkan-plugin-translatepress.php`
- **Elementor templates**: new endpoint `GET /dukkan-translation-translatepress/v1/elementor-templates` lists every `elementor_library` post (headers, footers, sections, containers, page/single/archive templates, popups, loop items) with `id`, `title`, `type`, `subtype`, `status` and rendered `content` (via `get_builder_content_for_display`), so the app's new "Elementor Blocks" tab can translate them. Returns an empty list when Elementor is inactive.

### v1.0.43 — Secure the shipping-status webhook (optional shared secret)

- **Security**: `dukkan-woo-webhook/v1/shipping-status` was fully public (`permission_callback => __return_true`, auth block commented out). It now checks an optional shared secret via the `dukkan_shipping_webhook_secret` filter — when set, requests must send a matching `X-Webhook-Secret` header (constant-time `hash_equals`), otherwise they get a `401`. When no secret is configured the endpoint stays open for backwards compatibility and logs an `AUTH_WARNING`. `api/webhook/woo/class-dukkan-woo-webhook.php`
- **To lock it down**: add `add_filter( 'dukkan_shipping_webhook_secret', fn() => 'YOUR_LONG_RANDOM_SECRET' );` (or set it in `wp-config.php`) and configure the same secret on the shipping platform.

### v1.0.42 — Dynamic pricing plugin status endpoint

- **New endpoint** `GET /dukkan-dynamic-pricing/v1/status` returning `{ "wcdpd_active": bool }` — checks `class_exists('RP_WCDPD_Settings')` so the mobile app can detect whether the WooCommerce Dynamic Pricing & Discounts (WCDPD) plugin is installed and active. `api/class-dukkan-plugin-dynamic-pricing-api.php`

### v1.0.41 — Media upload endpoint (drop ImgBB from product flow)

- **New API class** `Dukkan_Plugin_Media_API` (`api/class-dukkan-plugin-media-api.php`) exposing `POST /dukkan-media/v1/upload` (WooCommerce-key authenticated, multipart `file` field) that stores the image directly into the WordPress media library via `wp_handle_upload` + `wp_insert_attachment` and returns `{ id, source_url }`.
- **Why**: the app previously uploaded product/variation images to ImgBB (with a hardcoded shared key) and then had WooCommerce sideload the ImgBB URL. This replaces that double hop with a single, direct upload using the store's existing WooCommerce key.
- **Registered** in `includes/class-dukkan-plugin.php` (`define_media_api_hooks()`).

### v1.0.40 — Attribute swatch settings endpoints

- Added attribute-level Rey swatch settings read/write to `dukkan-attributes/v1`:
  - `GET /attributes/{id}/settings` — reads the attribute's entry from the `rey_swatches_data` option.
  - `PUT /attributes/{id}/settings` — merges arbitrary swatch settings keys (whitelisted: `swatch_tooltip`, `swatch_tooltip_image`, `use_variation_img`, `label_display`, `swatch_width`, `swatch_height`, `swatch_radius`, `swatch_font_size`, `swatch_padding`, `swatch_spacing`, `swatch_per_row`, `swatch_align`, `swatch_show_desc`, `swatch_direction`, `swatch_fallback`), preserving the authoritative `attribute_id`/`attribute_type`/`attribute_label` from the taxonomy table. `api/class-dukkan-plugin-attributes-api.php`

### v1.0.39 — Rey attribute swatch API

- **New API class** `Dukkan_Plugin_Attributes_API` (`api/class-dukkan-plugin-attributes-api.php`) exposing Rey theme variation-swatch settings to the mobile app under the `dukkan-attributes/v1` namespace (WooCommerce-authenticated):
  - `GET /types` — list available attribute types (core `select` + Rey swatches `rey_color`, `rey_image`, `rey_button`, `rey_large_button`, `rey_radio`) via `wc_get_attribute_types()`.
  - `PUT /attributes/{id}/type` — set an attribute's type (validated against `wc_get_attribute_types()`).
  - `GET /terms/{id}/swatch` — read a term's swatch meta (`rey_attribute_color`, `rey_attribute_color_secondary`, `rey_attribute_image`).
  - `PUT /terms/{id}/swatch` — write a term's swatch meta; accepts `color`, `color_secondary`, and `image_url` (sideloads the remote URL into the media library via `media_handle_sideload()` and stores the attachment ID as `rey_attribute_image`).
- **Registered** in `includes/class-dukkan-plugin.php` (`define_attributes_api_hooks()`).
- **Note**: the standard WooCommerce `wc/v3/products/attributes` API already returns/accepts `type`, but the per-term swatch meta is not exposed there — hence the dedicated endpoints.

### v1.0.38 — TranslatePress canonicalization: full HTML entity coverage (incl. `&`)

- **Bug**: strings containing `&` (or the double-encoded `&amp;` in imported content) never translated, same root cause as the apostrophe bug. `wptexturize()` re-encodes a bare `&` as `&#038;`, and imported source data stored literal `&amp;` in term/title names.
- **Fix**: `dukkan_plugin_canonicalize_original()` now decodes with `ENT_QUOTES | ENT_HTML5` (was `ENT_QUOTES` only) so **all** named + numeric entities (`&amp;`, `&lt;`, `&gt;`, `&quot;`, `&apos;`, `&nbsp;`, `&#038;`, `&#8217;`, `&#8211;`, etc.) normalize to raw UTF-8. `api/class-dukkan-plugin-translatepress.php`
- **Data repair** (one-off, `thecoach-jo.com`): decoded 19 double-encoded plain-text rows — `wp_terms.name` (`Dun &amp; Burst` → `Dun & Burst`) and `wp_posts.post_title` (`Terms &amp; Conditions` page + 17 `LZEL … iPhone 18 Pro &amp; 18 Pro Max` products).

### v1.0.37 — TranslatePress punctuation canonicalization fix (decode, not encode)

- **Bug**: `dukkan_plugin_canonicalize_original()` re-encoded curly punctuation to HTML numeric entities (`you’re` → `you&#8217;re`). TranslatePress stores + looks up dictionary keys in decoded raw UTF-8, so any string with an apostrophe/quote/dash was saved under a key the front-end never matched — the title translated but the long description did not.
- **Fix**: `dukkan_plugin_canonicalize_original()` now decodes to raw UTF-8 (entity-decode → `wptexturize()` → final decode) instead of re-encoding. `api/class-dukkan-plugin-translatepress.php`
- **Data repair** (one-off, applied to `thecoach-jo.com`): decoded the `original` keys of the polluted `wp_trp_dictionary_en_us_ar` + `wp_trp_original_strings` rows.
- **Also**: syncs the v1.0.36 `translate-block` endpoint into git (it previously lived only in the locally-built zip, never committed).

### v1.0.36 — TranslatePress merge-block endpoint

- **New endpoint** `POST /dukkan-translation-translatepress/v1/translate-block`: merges multi-element content (e.g. a description split across page-builder spans/divs) into a single TranslatePress translation block (`block_type = 1`). Saves per-language translations, and marks the child strings `block_type = 2` (deprecated, non-destructively) so the front-end renders the whole block as one translation. Punctuation is canonicalized server-side. `api/class-dukkan-plugin-translatepress.php`
- **Docs**: added §5 to `TRANSLATION-API-CURL.md` with a cURL example + index row.

### Unreleased (WIP) — Central API gateway (Cloudflare Worker)

- **Goal**: keep a single Google key for all client sites without the key ever living in the plugin (a public repo). The key moves to a Cloudflare Worker the developer owns; every site calls the gateway, which injects the key and forwards to Google.
- **Plugin**: added a `gemini_target()` helper that routes the 4 Gemini-family call sites (`embed_text`, `embed_texts_batch`, `call_gemini`, `stream_generate`) through the gateway when `gateway_url` is set, and falls back to direct Google (per-site `google_api_key`) otherwise. Added `gateway_url()` / `gateway_token()` accessors (both overridable via `dukkan_chatbot_gateway_url` / `dukkan_chatbot_gateway_token` filters), a `DUKKAN_GATEWAY_URL` constant (empty by default — a URL is not a secret), `gateway_url` / `gateway_token` settings + defaults + sanitizer + activator seed. Streaming (`stream_generate`) still uses raw cURL and now builds its header list from `gemini_target()`. `includes/class-dukkan-plugin-chatbot.php`, `admin/class-dukkan-plugin-chatbot-admin.php`, `admin/partials/dukkan-chatbot-settings.php`, `includes/class-dukkan-plugin-activator.php`
- **Admin UI**: the **Connection** card now has **Gateway URL**, **Gateway token**, and the direct **Google API key (fallback)** fields, with descriptions explaining when each applies. `admin/partials/dukkan-chatbot-settings.php`
- **Gateway**: new `gateway/` folder (NOT shipped in the plugin zip) with `worker.js` (Cloudflare Worker proxying `/chat`, `/chat-stream`, `/embed`, `/embed-batch`), `wrangler.toml`, and `GATEWAY-DEPLOY.md` (setup, verification cURLs, cost, key rotation).
- **Deployed**: the gateway is live at `https://dukkan-gateway.dukkanjo.workers.dev` (Cloudflare account `jodukkan@gmail.com`), with `GOOGLE_API_KEY` stored as a Cloudflare secret. The plugin's `DUKKAN_GATEWAY_URL` constant now points at this URL, so every site is zero-config out of the box. Chat + embed endpoints verified working. No `GATEWAY_TOKEN` is set, so the gateway currently accepts anonymous traffic — set one and add the matching token per site if you want to reject strangers.

### Unreleased (WIP) — Google API key moved to admin setting (leak fix)

- **Why**: the hardcoded `GOOGLE_API_KEY` was committed to the public repo; Google's secret scanner flagged it and auto-revoked the key, so every chat + embedding call returned **HTTP 403 "Your API key was reported as leaked"**. A live key can never live in a public repo.
- **Fix**: the hardcoded constant is now empty (documented as a placeholder) and `get_google_api_key()` reads the key purely from the `google_api_key` setting. Added a **Connection → Google API key** field to the AI Chatbot settings form so each store pastes its own key (stored in the options table, never in code). `includes/class-dukkan-plugin-chatbot.php`, `admin/partials/dukkan-chatbot-settings.php`
- **Action required**: generate a new key at [Google AI Studio](https://aistudio.google.com/app/apikey) and paste it into Dukkan → AI Chatbot → Connection (or deploy the gateway above and use that instead).

### v1.0.34 — Chatbot: Gemma-only (drop the Gemini model)
- **Single model**: the chat engine is now hardcoded to **`gemma-4-26b-a4b-it` (Gemma 4)**. Removed the admin Model selector, the `chat_model` setting/default/sanitizer, the `GEMINI_MODEL` and `PRICE_CHAT_INPUT`/`PRICE_CHAT_OUTPUT` constants, and the `is_gemma()` helper. `model_name()` always returns `GEMMA_MODEL`; `thinking_config()` always sends no `thinkingConfig` (Gemma rejects it); `sampling_config()` is fixed at Gemma's recommended `temperature=1.0 / topP=0.95 / topK=64`; the cost meter is pinned to `$0` (`PRICE_GEMMA_*`). `includes/class-dukkan-plugin-chatbot.php`, `admin/class-dukkan-plugin-chatbot-admin.php`, `admin/partials/dukkan-chatbot-settings.php`
- **Why Gemma-only**: Gemma is free (no paid tier) and served through the *same* Gemini API gateway (`generativelanguage.googleapis.com`), so it uses the identical key, protocol, and function-calling — the only change is the model string. Semantic search still uses `gemini-embedding-001` on that same key, so the Google API stays (Gemma has no separate API).
- **⚠️ Arabic caveat (still needs live QA)**: Gemma 4 is a smaller open model and its Arabic/Jordanian-dialect quality is not yet verified against real customer phrasing. If Arabic answers degrade, the escape hatch is to restore the model selector or point `model_name()` back at a Gemini Flash model. `includes/class-dukkan-plugin-chatbot.php`

### v1.0.34 — Chatbot: agent loop, image/voice search, citations, coupons
- **Multi-step agent loop**: the tool-turn guard is raised from 3 → 6 in both `process_message()` and `process_message_stream()`, so the assistant can now chain longer plans (search → filter → compare → recommend → add-to-cart → apply-coupon) instead of cutting off after 3 rounds. `includes/class-dukkan-plugin-chatbot.php`
- **Image search**: customers can attach a photo and the bot finds similar products. New `image_inline_part()` converts a `data:` URL into a Gemini `inlineData` part (validated MIME + 3.5MB cap), `build_contents()` attaches it to the current turn, and `process_message()` / `process_message_stream()` accept an optional `$image` param. `ajax_send` / `ajax_send_stream` read `$_POST['image']` and default the caption when empty. The widget gains an attach (paperclip) button, a pending-image preview with a remove control, and `sendMessage()` passes the image through both streaming and fallback paths. Verified live: Gemini and Gemma both accept image `inlineData`. `includes/class-dukkan-plugin-chatbot.php`, `public/class-dukkan-plugin-chatbot-public.php`, `public/js/dukkan-plugin-chatbot.js`, `public/css/dukkan-plugin-chatbot.css`
- **Voice search**: a microphone button transcribes speech in-browser via the Web Speech API (`SpeechRecognition`/`webkitSpeechRecognition`, browser language, no server transcription cost) and drops the transcript into the input for the same pipeline. Graceful fallback for unsupported browsers. `public/class-dukkan-plugin-chatbot-public.php`, `public/js/dukkan-plugin-chatbot.js`, `public/css/dukkan-plugin-chatbot.css`
- **Grounded answers with citations**: a new system-prompt instruction requires the model to back every factual answer with a markdown citation (e.g. `[returns policy](https://…)`) and to admit uncertainty instead of guessing. `includes/class-dukkan-plugin-chatbot.php`
- **Better greeting**: a time-of-day aware default greeting (morning/afternoon/evening/night, using the bot name) replaces the flat "Hi!" fallback. `public/js/dukkan-plugin-chatbot.js`
- **Coupons**: two new tools — `list_coupons` (lists active WooCommerce coupons + their discount, skipping invalid/expired ones) and `apply_coupon` (applies a code via `WC()->cart->apply_coupon()`). Both wired into `get_tools()` / `execute_tool()` and the system prompt so the bot can offer deals and redeem codes. `includes/class-dukkan-plugin-chatbot.php`

### v1.0.34 — Model selector: Gemini ↔ Gemma 4

- **Additive model choice**: a new **Model** select in the AI Chatbot settings lets the store choose between `gemini-2.5-flash-lite` (default) and **`gemma-4-26b-a4b-it`** (Gemma 4 26B A4B). Gemma is served through the *same* Gemini API gateway (`generativelanguage.googleapis.com`), so it uses the identical `functionCall`/`functionResponse` protocol, the same `x-goog-api-key`, and needs **no tool-layer rewrite**.
- **Free tier**: Gemma 4 has **no paid tier on the Gemini API** (free of charge, rate-limited), so selecting it drops chat API cost to $0. New `PRICE_GEMMA_INPUT` / `PRICE_GEMMA_OUTPUT` constants are `0.0` and `get_estimated_cost()` / `chat_input_price()` / `chat_output_price()` now price the meter by the active model.
- **Thinking config adapter**: new `thinking_config()` maps Gemini's `thinkingBudget`; Gemma sends **no** `thinkingConfig` (it rejects both `thinkingLevel` and `thinkingBudget` with HTTP 400). `call_gemini()` and `stream_generate()` now resolve the endpoint + thinking config through `model_name()` / `thinking_config()`.
- **Gemma thought filtering (fix)**: Gemma 4 thinks by default and emits `thought: true` parts ahead of the real answer. `gemini_extract_text()` and the streaming `$consume` callback now skip `thought: true` parts so chain-of-thought never leaks into the customer reply; the parts are still kept verbatim in `$all_parts` for faithful tool round-trip reconstruction (the `thoughtSignature` on Gemma is a *sibling* of `functionCall`, preserved as-is).
- **⚠️ Test note**: Gemma's Arabic/dialect quality needs live verification before going to production. The plugin now runs **Gemma-only** (see the "Gemma-only" change above). `includes/class-dukkan-plugin-chatbot.php`, `admin/class-dukkan-plugin-chatbot-admin.php`, `admin/partials/dukkan-chatbot-settings.php`
- **Model-aware sampling (fix)**: new `sampling_config()` — Gemini stays at `temperature=0.4` (deterministic), while Gemma uses Google's recommended `temperature=1.0`, `topP=0.95`, `topK=64` (a low temperature makes open models repetitive and stiff, hurting natural function calling). Applied in both `call_gemini()` and `stream_generate()`.

### v1.0.34 — Chatbot intelligence upgrade (Tier 1 + 2)

- **Reasoning for all languages**: `thinking_budget_for()` now gives English queries a real thinking budget (768, or 1536 for complex) and raises Arabic (2048, 3072 complex), with complexity auto-detected (comparisons, filters, recommendations) in both languages. Previously English got `0` thinking and jumped to conclusions.
- **Structured product filtering**: `search_products()` and `keyword_search()` accept filters (`price_min`, `price_max`, `in_stock`, `category`, `attribute`) via a new `product_matches_filters()` helper using effective (sale) price. The `search_products` tool now exposes these as parameters, so "under $50 / in stock / size M" is executed as a filter instead of approximated.
- **Hybrid search**: a new `keyword_boost()` nudges exact lexical matches (name/SKU/category/attribute) above mere semantic neighbours, improving brand/SKU recall.
- **New tools — `get_product_details`, `get_cart`, `recommend_products`**: full single-product detail lookup (variants/stock/price/link), cart contents + subtotal read, and embedding-based "similar products" (category fallback). All wired into `get_tools()` + `execute_tool()` and the system prompt.
- **Longer memory with rolling summary**: `build_contents()` keeps 16 recent turns verbatim and condenses older turns into a compact "earlier the customer said…" note (`summarize_older_turns()`) so long conversations retain context without extra API calls.
- **Smarter handoff**: `finalize_reply()` now takes history and escalates via `customer_is_frustrated()` (repeated negations or angry language), not just the "human/agent" keyword.
- **Prompt instructions** updated for filtering, product details, recommendations and cart. `includes/class-dukkan-plugin-chatbot.php`

### v1.0.34 — WhatsApp Business integration

- **WhatsApp Business (Meta Cloud API) — Phase 1**: the chatbot can now answer customers on WhatsApp by reusing the exact same Gemini engine. New `includes/class-dukkan-plugin-whatsapp.php` registers a `dukkan-whatsapp/v1/webhook` REST route (GET verify handshake + POST message reception with `X-Hub-Signature-256` verification), resolves a sender's phone to a WooCommerce customer via `billing_phone` (so `lookup_orders` / `lookup_points` work), keeps per-phone conversation memory in a transient, applies the shared rate limit, and sends replies through the Cloud API (`POST graph.facebook.com/v21.0/{phone_number_id}/messages`). Admin gains a "WhatsApp Business" card (enable toggle, webhook URL, phone number ID, access token, verify token, app secret, session TTL, handoff number) saved into the chatbot settings with new `whatsapp_*` defaults. `includes/class-dukkan-plugin-whatsapp.php`, `includes/class-dukkan-plugin.php`, `includes/class-dukkan-plugin-chatbot.php`, `admin/class-dukkan-plugin-chatbot-admin.php`, `admin/partials/dukkan-chatbot-settings.php`

### v1.0.34 — Chatbot: Flash-Lite swap, orders index, cost meter

- **Model swap to Gemini 2.5 Flash-Lite (historical)**: `GEMINI_MODEL` was `gemini-2.5-flash-lite` (~75% cheaper than 2.5 Flash). Superseded — the plugin now runs **Gemma-only** (the `GEMINI_MODEL` constant has been removed). `includes/class-dukkan-plugin-chatbot.php`
- **Orders index (new)**: orders are now a first-class indexed entity like products/pages — new `{prefix}dukkan_chatbot_orders` table, `index_order()`, `build_order_index_batch()`, `get_order_index_status()`, `search_orders()` (semantic ranking + live fallback), an "Orders index" row + "Rebuild orders index" button in admin, `dukkan_chatbot_rebuild_orders_index` AJAX handler, and orders rebuild in the daily cron. `DB_VERSION` → 1.3.0. `tool_lookup_orders()` now reads from the index. `includes/class-dukkan-plugin-chatbot.php`, `includes/class-dukkan-plugin-activator.php`, `admin/class-dukkan-plugin-chatbot-admin.php`, `admin/partials/dukkan-chatbot-settings.php`, `admin/js/dp-chatbot.js`
- **Cost meter (new)**: an "Usage & cost" card on the AI Chatbot tab tracks chat input/output tokens (from Gemini `usageMetadata`, incl. thinking tokens billed as output) and embedding tokens (estimated), estimates USD cost, and has a "Reset meter" button. `USAGE_KEY`, `record_chat_usage()`, `record_embed_usage()`, `get_estimated_cost()`, `ajax_reset_usage`. `includes/class-dukkan-plugin-chatbot.php`, `admin/class-dukkan-plugin-chatbot-admin.php`, `admin/partials/dukkan-chatbot-settings.php`, `admin/js/dp-chatbot.js`, `admin/css/dp-chatbot.css`
- **Order lookup "could not generate" fix**: the streaming tool loop dropped Gemini's `thoughtSignature` when echoing the function call back with the function result, which broke follow-up replies under thinking mode. `stream_generate()` now keeps the whole part and `process_message_stream()` preserves `thoughtSignature` (with admin-visible error surfacing on tool-loop failure). `includes/class-dukkan-plugin-chatbot.php`
- **Categories/announcement "could not generate" fix (complete)**: the earlier `thoughtSignature` fix was incomplete — Gemini attaches the signature to the *text* part that precedes the function call (in thinking mode that text is the model "announcing" it will search), not to the `functionCall` part. So a text + tool-call turn still dropped the signature (breaking the follow-up → "Sorry, I could not generate a response.") and streamed the announcement text to the customer as a fake "I'm searching" message. Fixed by (1) `stream_generate()` now capturing every part verbatim (`parts`), (2) `process_message_stream()` reconstructing the model turn from the full part sequence and *buffering* the first turn's text (streaming only the final answer), and (3) a new system-prompt instruction forcing the model to call the tool in the same turn without greeting/announcing first. `includes/class-dukkan-plugin-chatbot.php`
- **No-argument tool 400 fix (root cause)**: asking for categories/shipping/points returned "Sorry, I could not generate a response. [Chat request failed. HTTP 400]". Root cause is a PHP `json_decode`/`json_encode` round-trip bug, not the signature: Gemini returns `functionCall.args: {}` for no-parameter tools (`list_categories`, `get_shipping`, `lookup_points`), but `json_decode(..., true)` turns `{}` into an empty PHP array, which `json_encode` re-encodes as `[]` — and Gemini rejects `args: []` with HTTP 400 ("Proto field is not repeating, cannot start list"). Product search was unaffected because it always has a `query` arg. Added `normalize_parts()` (converts empty `args` back to an empty object) and applied it to both `call_gemini()` and `stream_generate()` return values. `includes/class-dukkan-plugin-chatbot.php`
- **Modern chat launcher + mobile fix**: the launcher uses a crisp inline SVG (replacing the 💬 emoji) with a gradient, springy hover and a pulsing "online" dot; the panel is now `position: fixed` (was `absolute`, which pushed it off-screen on mobile) and uses `100dvh` + safe-area padding on small screens. `public/class-dukkan-plugin-chatbot-public.php`, `public/css/dukkan-plugin-chatbot.css`
- **Chat header redesign**: the widget header now has a vibrant blue gradient background with a curved, wavy bottom edge (an inline SVG wave that fills with the messages background), a circular illustrated avatar of a woman wearing a hat (inline SVG, replaced by a real image when a `bot_avatar` URL is set), the name rendered as "Chat with {bot_name}", and a "We are online!" status with a pulsing green dot. Default `bot_name` changed to "Jessica Smith" and a new `bot_avatar` setting + admin field were added. `public/class-dukkan-plugin-chatbot-public.php`, `public/css/dukkan-plugin-chatbot.css`, `includes/class-dukkan-plugin-chatbot.php`, `admin/class-dukkan-plugin-chatbot-admin.php`, `admin/partials/dukkan-chatbot-settings.php`
- **Chat launcher icon redesign**: the launcher is now a prominent, perfect circle with a blue gradient background (`linear-gradient(135deg, #1d4ed8 → #2563eb → #7c3aed)`), a flat white speech-bubble glyph centered inside, and a soft blurred blue drop shadow for a floating, elevated appearance. Selectors are scoped under `.dukkan-chatbot` (two/three-class specificity) so theme button resets (`button`, `.woocommerce button`) and global `svg { fill }` rules can't override the circle or the white glyph — the icon fill is set via CSS rather than relying on the SVG presentation attribute. `public/class-dukkan-plugin-chatbot-public.php`, `public/css/dukkan-plugin-chatbot.css`
- **"Chat with us" launcher label**: the launcher is now a white pill (full rounded radius) containing the gradient blue circle icon on the left and a "Chat with us" label beside it; the green online pulse dot moved onto the circle's corner. `public/class-dukkan-plugin-chatbot-public.php`, `public/css/dukkan-plugin-chatbot.css`
- **Header cleanup**: removed the "clear chat" (reset) button from the header and its JS handler; fixed the close (×) button rendering black by scoping it under `.dukkan-chatbot` and forcing `color: #fff` so theme button resets can't override it on the dark header. `public/class-dukkan-plugin-chatbot-public.php`, `public/css/dukkan-plugin-chatbot.css`, `public/js/dukkan-plugin-chatbot.js`

### v1.0.34 — Chatbot: pages + orders indexing

- **Pages index (new)**: store pages (About, policies, FAQ, shipping/returns, contact) are now a first-class indexed entity so the assistant can answer informational questions by meaning. New `{prefix}dukkan_chatbot_pages` table, `pages_table()`, `page_embed_text()`, `index_page()`, `build_page_index_batch()`, `get_page_index_status()`, `search_pages()` + `keyword_search_pages()` fallback, and a new `search_pages` tool (`tool_search_pages()`) that returns title + excerpt + link. Added a "Pages index" row with its own "Rebuild pages index" button in the admin, the `dukkan_chatbot_rebuild_pages_index` AJAX handler, auto-index on `save_post_page`, and pages rebuild in the daily cron. `DB_VERSION` → 1.2.0. `includes/class-dukkan-plugin-chatbot.php`, `includes/class-dukkan-plugin-activator.php`, `admin/class-dukkan-plugin-chatbot-admin.php`, `admin/partials/dukkan-chatbot-settings.php`, `admin/js/dp-chatbot.js`
- **Orders lookup enhanced**: `tool_lookup_orders()` now returns up to 15 recent orders including their line-item names, so the customer can ask "where is my mascara order?" and the model can identify the right order. It also accepts an optional `query` argument and, when present, semantically ranks the order summaries against it (batched embedding + cosine similarity) so the most relevant order floats to the top. `includes/class-dukkan-plugin-chatbot.php`
- **System prompt**: added a `search_pages` instruction (answer from returned page content + share the link). `includes/class-dukkan-plugin-chatbot.php`

### v1.0.34 — Chatbot v2: streaming, shipping, categories, attributes

- **Streaming replies (SSE)**: the assistant now streams its answer word-by-word like ChatGPT. Added `stream_generate()` (raw cURL to Gemini `:streamGenerateContent?alt=sse`, because `wp_remote_post` buffers) and `process_message_stream()`, plus a new `dukkan_chatbot_send_stream` AJAX endpoint that emits `data: {"t":"…"}` token events and a final `data: {"d":{…}}` payload. The client streams via `fetch` + `ReadableStream` and falls back to the classic non-streaming `dukkan_chatbot_send` endpoint on unsupported browsers or mid-stream network errors. `includes/class-dukkan-plugin-chatbot.php`, `public/class-dukkan-plugin-chatbot-public.php`, `public/js/dukkan-plugin-chatbot.js`
- **Shipping method + cost tool**: new `get_shipping` tool reads enabled WooCommerce shipping zones/methods (flat rate, free-shipping threshold, local pickup, etc.) and returns their titles + costs, plus a cart-aware "add $X more for free shipping" hint. Added `tool_get_shipping()`. `includes/class-dukkan-plugin-chatbot.php`
- **Categories tool**: new `list_categories` tool returns the product category tree (top-level + one level of children) with product counts. Added `tool_list_categories()`. `includes/class-dukkan-plugin-chatbot.php`
- **Attribute-aware reasoning**: products now index their visible attributes + values (e.g. "Color: Red, Blue | Size: S, M, L") into a new `attributes` column and into the embedding text, so the model can reason about specific variants ("blue in size M") and keyword search can match attribute values. `DB_VERSION` → 1.1.0 and `EMBEDDING_VERSION` → 3 force a table check + re-embed. Added `product_attributes_text()`. `includes/class-dukkan-plugin-chatbot.php`, `includes/class-dukkan-plugin-activator.php`
- **Chat polish**: markdown rendering in bot bubbles (bold/italic/code/links/bullets), a blinking caret while streaming, quick-reply chips after each answer, and a "clear chat" button in the header. `public/js/dukkan-plugin-chatbot.js`, `public/css/dukkan-plugin-chatbot.css`, `public/class-dukkan-plugin-chatbot-public.php`
- **Rebuild index required**: attribute embeddings require a fresh index — click "Rebuild index now" on the AI Chatbot tab after deploying.
- **Jordanian Arabic dialect**: the assistant now replies in natural Amman/Jordanian dialect when the customer writes in Arabic — "بدي" instead of "أريد", "كمان شوي" for time, "مش" for negation — and freely uses elegant, common Jordanian expressions and light humor on its own (no fixed list). Added `arabic_dialect_instruction()` to the system prompt. `includes/class-dukkan-plugin-chatbot.php`
- **Arabic reasoning boost**: Arabic customer messages now get Gemini "thinking" time (`thinkingBudget: 1024`) so the model reasons harder before searching, fixing cases where an Arabic product request ("بدي فاونديشن") was answered with "not found". Added `is_arabic_text()`, `thinking_budget_for()`, and threaded a `thinking_budget` param through `call_gemini()` / `stream_generate()` / `process_message()` / `process_message_stream()`. English queries stay fast (thinking off). `includes/class-dukkan-plugin-chatbot.php`
- **Fixed conversation context loss (critical)**: the chat forgot prior turns ("بدي مسكارة" → "بدي من ماركة ميبلين" returned unrelated Maybelline products). Root cause: the streaming endpoint posts `history` as a JSON string but the server cast it with `(array)`, turning it into a single-element array of one string that `sanitize_history()` then dropped — so every turn reached Gemini with empty history. Added `request_history()` which accepts both the jQuery nested-array format (fallback path) and the JSON-string format (streaming path). Applied to both `ajax_send()` and `ajax_send_stream()`. `public/class-dukkan-plugin-chatbot-public.php`
- **Chat reply quality (prompt)**: replies now greet only on the first message, address the customer gender-neutrally (no "تعرفي/جاهزة/لقيتلك"), carry forward context on short follow-ups, never print bullet lists of products (cards render automatically), and end with at most one short question. `includes/class-dukkan-plugin-chatbot.php`
- **Fast batched index rebuild**: product/category indexing now embeds an entire chunk in one `batchEmbedContents` call (up to 100 texts) instead of one HTTP request per product. Added `embed_texts_batch()`, made `index_product()` / `index_category()` accept an optional precomputed vector, and rewired `build_full_index_batch()`, `build_category_index_batch()` and `cron_reindex()` to use it. Batch size raised to 50 (admin JS + AJAX default). This collapses N round-trips into N/100. `includes/class-dukkan-plugin-chatbot.php`, `admin/class-dukkan-plugin-chatbot-admin.php`, `admin/js/dp-chatbot.js`
- **Batched "Rebuild index now"**: the rebuild previously indexed every product in a single synchronous AJAX request (one embedding API call per product), which hung the button on larger catalogs. It now processes 5 products per request via a transient-backed queue (`build_full_index_batch()`), with the admin JS looping batches and showing live progress ("Indexing… 30/120"). `includes/class-dukkan-plugin-chatbot.php`, `admin/class-dukkan-plugin-chatbot-admin.php`, `admin/js/dp-chatbot.js`
- **Separate indexes + per-entity rebuild buttons**: the admin "Catalog & retrieval" card now has independent "Rebuild products index" and "Rebuild categories index" buttons, each with its own count + progress. Categories are now a first-class indexed entity: a new `{prefix}dukkan_chatbot_categories` table stores embedded category names/descriptions (with parent chain so "Shirts" also matches "Men"), built via `build_category_index_batch()` and searched semantically with a new `search_categories` tool (`search_categories()`, `tool_search_categories()`). The daily cron now rebuilds both indexes. `includes/class-dukkan-plugin-chatbot.php`, `includes/class-dukkan-plugin-activator.php`, `admin/class-dukkan-plugin-chatbot-admin.php`, `admin/partials/dukkan-chatbot-settings.php`, `admin/js/dp-chatbot.js`

### v1.0.34 — AI Chatbot migrated to Google Gemini

- **Chat engine swap**: `includes/class-dukkan-plugin-chatbot.php` now uses Google Gemini (`gemini-2.5-flash`) instead of DeepSeek. `call_deepseek()` → `call_gemini()` (Gemini `generateContent` endpoint, system prompt moved to `systemInstruction`, history mapped to user/model `contents`), and function-calling rewritten from OpenAI `tool_calls` to Gemini `functionDeclarations`/`functionCall`/`functionResponse` parts (added `gemini_extract_text()` + `gemini_extract_calls()` helpers).
- **Embeddings swap**: `embed_text()` now calls Google `gemini-embedding-001` (`:embedContent`) instead of OpenAI `text-embedding-3-small`.
- **Retrieval task type**: product embeddings use `RETRIEVAL_DOCUMENT` and queries use `RETRIEVAL_QUERY` (via `taskType`), the correct asymmetric setup for search. Previously products were embedded with the default task type, which degraded retrieval and let unrelated products match. Stale vectors are invalidated automatically via `dukkan_chatbot_embedding_version`. `includes/class-dukkan-plugin-chatbot.php`
- **Single key**: hardcoded `GOOGLE_API_KEY` constant powers both chat and embeddings (overridable via the `google_api_key` setting). DeepSeek key constant, `deepseek_model` / `deepseek_api_key` / `openai_api_key` settings, and the admin "DeepSeek model" + "OpenAI API key" fields are all removed.
- **IPv4 enforced**: Gemini calls force IPv4 (`CURLOPT_IPRESOLVE_V4` via `http_api_curl`) to avoid Google's "User location is not supported" IPv6 geo-misclassification. `includes/class-dukkan-plugin-chatbot.php`
- **Empty-properties fix**: `lookup_orders` / `lookup_points` tool schemas use `new stdClass()` for `properties` so they encode as `{}` (map) rather than `[]` (list), which Gemini rejects. `includes/class-dukkan-plugin-chatbot.php`
- **Daily reindex**: cron now runs **daily at 4am (site timezone)** instead of every two days; migrated via `dukkan_chatbot_cron_v2` option flag. `includes/class-dukkan-plugin-chatbot.php`
- **Smarter retrieval**: `search_products()` now drops weak/unrelated results using an absolute floor (0.35) plus a relative floor (0.55× best match), so a single unrelated product no longer leaks into recommendations. `includes/class-dukkan-plugin-chatbot.php`
- **Model-controlled search (major refactor)**: product search is now a `search_products` tool the model calls itself, instead of being injected into every prompt. The model only receives (and the widget only renders) products it explicitly searched for — fixing unrelated-card spam, duplicated text+card replies, and lost follow-up context. Added `tool_search_products()`, `last_products` capture, `build_system_prompt()`, and a strengthened prompt (keep replies short, don't restate card details). `includes/class-dukkan-plugin-chatbot.php`
- **Cross-lingual search**: Arabic (or other non-Latin) queries are now translated to English via a lightweight Gemini call before searching, so an Arabic query like "فاونديشن" correctly matches the English product "Foundation". Added `translate_query_to_english()`. `includes/class-dukkan-plugin-chatbot.php`
- **Conversation correctness**: the current message was previously appended *twice* (the client pre-pushes it and the server re-added it), producing consecutive `user` turns. `process_message()` now deduplicates the current message and merges consecutive same-role turns via `append_turn()` so Gemini's strict user/model alternation always holds. Tool loop now supports chained calls with a guard. `includes/class-dukkan-plugin-chatbot.php`
- **Thinking disabled**: `gemini-2.5-flash` now sends `thinkingConfig.thinkingBudget = 0` for direct, fast, predictable replies instead of verbose reasoning. `includes/class-dukkan-plugin-chatbot.php`
- **Persistent memory fixed**: `ajax_send()` now *reads* the server-side memory transient in persistent mode (it was previously only written, never loaded), so logged-in customers are remembered across visits. `public/class-dukkan-plugin-chatbot-public.php`
- **Persistent chat**: the storefront widget now saves the conversation to `localStorage` (`dukkan_chatbot_history`) and restores it on page load, so navigating pages keeps the chat. `public/js/dukkan-plugin-chatbot.js`
- **3-up products + "View more"**: the widget now shows only the first 3 product cards and a "View more" button; clicking it loads the remaining cards via a new `dukkan_chatbot_more_products` AJAX endpoint (deferred cards are stashed in a per-visitor transient for 10 minutes). `includes/class-dukkan-plugin-chatbot.php`, `public/class-dukkan-plugin-chatbot-public.php`, `public/js/dukkan-plugin-chatbot.js`, `public/css/dukkan-plugin-chatbot.css`
- **IMPORTANT — model lifecycle**: the plugin now runs **Gemma 4 (`gemma-4-26b-a4b-it`) only** — the Gemini chat model and its `GEMINI_MODEL` constant were removed. If Gemma is ever retired, replace the single `GEMMA_MODEL` constant in `includes/class-dukkan-plugin-chatbot.php` with the newest Gemma (or restore a Gemini Flash model).
- **Rebuild index required**: embedding dimensions changed (OpenAI 1536 → Google 3072); after deploying, click "Rebuild index now" on the AI Chatbot tab.

### v1.0.34 — Product Add-Ons storefront matching hardening

- **Parent/child category matching**: `group_applies_to_product()` now matches a product that sits in a *child* category of a selected (parent) category. Previously `has_term()` only checked direct assignment, so a "Specific Categories" group targeting "Men" never matched products filed under "Men → Shirts" — a common fashion-store setup. Added `product_in_categories()` helper. `public/class-product-addon.php`
- **Robust product resolution**: `wpldp_render_addons_new()` now falls back to `wc_get_product()` when `global $product` isn't a `WC_Product` (some Elementor/Rey rendering paths). `public/class-product-addon.php`
- **JSON safety + diagnostics**: `wp_json_encode()` failure falls back to `[]` (avoids a JS syntax error on non-UTF8 data), and `?wpldp_addon_debug=1` (or `WP_DEBUG`) logs a per-group match breakdown to the error log and shows it on-page for admins. `public/class-product-addon.php`

### v1.0.28 — Product Add-Ons: support `specific_products` / `specific_categories`

- **Storefront matching fix**: `group_applies_to_product()` in `public/class-product-addon.php` now understands `specific_products` and `specific_categories` in addition to the legacy `all` / `specific` values. Groups created via the REST API (or mobile app, following the badges convention) previously never matched on the storefront because the public code only recognized `'specific'`. `public/class-product-addon.php`
- **Docs**: REST API `applied_to` description + `api/product-addon-api-reference.json` updated to document the accepted values.

### v1.0.27 — AI Chatbot (DeepSeek)

- **Core engine**: `includes/class-dukkan-plugin-chatbot.php` — settings, Gemini chat client (`gemini-2.5-flash`), Google `gemini-embedding-001` embeddings, product vector index (`{prefix}dukkan_chatbot_products`) with cosine-similarity retrieval, function-calling tools (`lookup_orders`, `lookup_points`, `add_to_cart`), human-handoff email, and conversation log (`{prefix}dukkan_chatbot_log`). (Originally shipped on DeepSeek + OpenAI embeddings in v1.0.27; migrated to Gemini in a later unreleased change.)
- **Admin**: `admin/class-dukkan-plugin-chatbot-admin.php` + `admin/partials/dukkan-chatbot-settings.php` + `admin/css/dp-chatbot.css` + `admin/js/dp-chatbot.js` — a new "AI Chatbot" settings tab with a master switch, connection keys + "Test connection", personality (language/tone/system prompt/bot name/greeting), appearance (accent color + position), catalog & retrieval (index status + "Rebuild index now" + auto-index toggle), capabilities (lookup / add-to-cart / handoff / rate limit / memory), and a conversation-log viewer.
- **Public widget**: `public/class-dukkan-plugin-chatbot-public.php` + `public/js/dukkan-plugin-chatbot.js` + `public/css/dukkan-plugin-chatbot.css` — a floating chat launcher/panel with greeting + suggestion chips, product cards with "Add to cart", human-handoff form, and AJAX endpoints (`dukkan_chatbot_send`, `dukkan_chatbot_handoff`) for both logged-in and guest visitors.
- **Index freshness**: products re-embed on `save_post_product` (debounced) plus a `dukkan_every_two_days` WP-Cron full reindex; both are registered in `includes/class-dukkan-plugin.php` (`define_chatbot_hooks`).
- **Tables/settings**: seeded on activation (`includes/class-dukkan-plugin-activator.php`); cron cleared on deactivation (`includes/class-dukkan-plugin-deactivator.php`).

### v1.0.26 — Loyalty Points admin: customer search + adjust points

- **Customer search combo**: replaced the "email or user ID + Look up" flow in the admin "Customer balance" card with a live autocomplete that searches customers by name or email (`ajax_search_customers`). `admin/class-dukkan-plugin-loyalty-admin.php`, `admin/partials/dukkan-loyalty-settings.php`, `admin/js/dp-loyalty.js`, `admin/css/dp-loyalty.css`
- **Manual adjust points**: added an "Adjust points" form that lets the admin add or deduct points from a customer with an optional reason (`ajax_adjust_points`), routed through the existing `add_points()` / `deduct_points()` engine and written to the ledger as `type=adjust`. Same files as above.

### v1.0.25 — Loyalty Points + add-on price display + update-check cache

- **Loyalty Points** (new feature): earn points per amount spent, redeem at checkout ("pay with points"), My Account balance display, admin-configurable points value & earn rate, earning on net amount, discount applied before tax, product/category exclusions, coupon-stacking rule, and a master enable switch.
  - Admin: `admin/class-dukkan-plugin-loyalty-admin.php`, `admin/partials/dukkan-loyalty-settings.php`, `admin/css/dp-loyalty.css`, `admin/js/dp-loyalty.js`
  - Public: `public/class-dukkan-plugin-loyalty-public.php`, `public/js/dukkan-plugin-loyalty.js`, `public/css/dukkan-plugin-loyalty.css`
  - Core/API: `includes/class-dukkan-plugin-loyalty.php`, `api/class-dukkan-plugin-loyalty-api.php`, `api/loyalty-points-api-reference.json`, `LOYALTY-POINTS-API-CURL.md`
- **Product Add-Ons — live price display**: removed the "Addons Total / Total" summary bar; the product's own price (regular or sale) now updates live to include the selected add-on value. `public/class-product-addon.php`, `public/js/dukkan-plugin-product-addon.js`, `public/css/dukkan-plugin-product-addon.css`
- **Update checker caching**: `version.json` is now cached in a transient (4-hour expiry) with an in-request guard, so the update check no longer fires repeated live GitHub requests on every admin page load. `includes/class-dukkan-plugin-updater.php`
- **Product Add-Ons combo fix**: scoped the products/categories search combo to its own `data-combo` types so the Badge and Loyalty scripts no longer overwrite the category dropdown with product names. `admin/js/dp-product-addon.js`, `admin/js/dp-badge.js`, `admin/js/dp-loyalty.js`

### v1.0.22 — Manual updates (auto-update removed)

- Removed the daily WP-Cron self-update and the background `dukkan/v1/update` REST endpoint.
- Removed the 12-hour `version.json` cache; the update UI now reads `version.json` live.
- Updates are applied only via the WordPress "update now" button (or WP auto-updates if enabled).

### v1.0.5 — Slim SEO API Bridge

Two write-only REST endpoints under `dukkan-seo/v1` that let the mobile app set Slim SEO meta titles and descriptions on posts (including products).

| Method | Route | Purpose |
|--------|-------|---------|
| PUT | `/posts/{id}/title` | Set the Slim SEO meta title |
| PUT | `/posts/{id}/description` | Set the Slim SEO meta description |

- Works on any post type (posts, pages, products, custom).
- Each endpoint modifies only the targeted field. All other Slim SEO meta (OG/Twitter images, canonical URL, noindex) is preserved.
- The API class (`api/class-dukkan-plugin-slim-seo-api.php`) only loads when Slim SEO is active — guarded by `class_exists('SlimSEO\Container')`.
- API reference for app developers: `api/slim-seo-api-reference.json`

### v1.0.4 — Async Background Self-Updater

A daily WP-Cron event (4 AM Amman time / 1 AM UTC) checks `version.json` on GitHub. If a newer version exists, it fires a non-blocking async REST request (`blocking=false`) to a protected internal endpoint (`dukkan/v1/update`) that performs the download and install in a separate PHP process. Zero visitor delay.

- Cron schedule: `dukkan_plugin_daily_update_check` hook, daily at 1 AM UTC.
- Background endpoint authenticated with an auto-generated bearer token.
- Also hooks into WordPress native update UI as a bonus.
- API class: `includes/class-dukkan-plugin-updater.php`

### v1.0.1–1.0.2 — Performance Optimizations

Six performance fixes targeting unnecessary queries and asset loads on every page:

| # | Issue | Fix | File |
|---|-------|-----|------|
| 1 | `dukkan_custom_order_statuses` option not autoloaded | Changed `add_option()` autoload param to `yes` | `includes/class-dukkan-plugin-activator.php` |
| 2 | Repeated `get_option()` calls for user statuses | Added static cache (`self::$cached_statuses`) + `get_user_statuses()` helper | `admin/class-dukkan-plugin-woocommerce.php` |
| 3 | Public CSS loaded on every page | Guard `enqueue_styles()` with `is_product()` | `public/class-dukkan-plugin-public.php` |
| 4 | Public JS loaded on every page | Guard `enqueue_scripts()` with `is_product()` | `public/class-dukkan-plugin-public.php` |
| 5 | Product-addon CSS loaded on every page | Guard `enqueue_styles()` with `is_product()` | `public/class-product-addon.php` |
| 6 | Product-addon JS loaded on every page | Guard `enqueue_scripts()` with `is_product()` | `public/class-product-addon.php` |

Also: TranslatePress API class now conditionally loaded only when `TRP_Translate_Press` class exists (`includes/class-dukkan-plugin.php`).

---

## Plugin Update Mechanism (manual)

No wordpress.org hosting required. The plugin reads `version.json` from the repo root and injects the latest release into WordPress's native update UI:

```json
{
  "version": "1.0.22",
  "package": "https://github.com/jodukkan-max/dukkan-plugin/releases/download/v1.0.22/dukkan-plugin.zip",
  "requires": "5.0",
  "tested": "6.6"
}
```

**Release workflow:**

1. Edit source in `dukkan-plugin/`.
2. Bump version in `dukkan-plugin.php` (header + `DUKKAN_PLUGIN_VERSION`) and `version.json`.
3. Rebuild the archive:
   `zip -r dukkan-plugin.zip dukkan-plugin -x "*.git*" "*.DS_Store" "*__MACOSX*" "dukkan-plugin/dukkan-plugin.zip"`
   > ⚠️ The extra `"dukkan-plugin/dukkan-plugin.zip"` exclude is required now that the git
   > repo tracks `dukkan-plugin.zip` inside `dukkan-plugin/` — otherwise the built zip nests
   > the previous release zip inside itself (doubles the size).
4. Commit, tag `vX.Y.Z`, push `main` + tag.
5. Create the GitHub release and attach `dukkan-plugin.zip`.

Updates are applied manually by the admin via the "update now" button on the Plugins screen (or WordPress auto-updates if enabled). There is no scheduled cron and no automatic background install.

---

## Notes / Gotchas

- **nginx fastcgi cache** at `/var/cache/nginx` (`keys_zone=WORDPRESS`, 30-min TTL).
  Cleared only via root/sudo. Cache bypasses on any query string or the
  `wordpress_no_cache=1` cookie (see `/etc/nginx/snippets/wordpress-cache.conf`).
- **`fm.php` (web file manager) sits in the public web root** — security exposure; remove it.
- **`query-monitor` plugin is active on production** — leaks request/DB info; disable in prod.
- **TranslatePress plugin** is at `wp-content/plugins/translatepress-multilingual/`
  (free version). Dictionary tables are `wp_trp_dictionary_*`, originals in
  `wp_trp_original_strings` / `wp_trp_gettext_original_strings`.
- **Translation is client-side AI**: the Flutter app calls Gemini directly (`GEMINI_API_KEY`
  in the app's `.env`), then POSTs results to the plugin's
  `dukkan-translation-translatepress/v1/*` endpoints. The plugin only stores into
  TranslatePress (no server-side AI for translation).
- **Plugin detection endpoint:** `GET /wp-json/dukkan-general-api/v1/plugin-status?plugin=<slug>`.
- **Gemini model note:** app translation was using `gemini-2.5-flash-image` (image model)
  for text translation — corrected to `gemini-2.5-flash` in the app repo (2026-09-28).

---

## Project Structure

```
dukkan-plugin/
├── dukkan-plugin.php              # Bootstrap — constants, activation, updater init
├── version.json                   # Version manifest read by the update UI
├── index.php                      # Silence is golden
├── uninstall.php                  # Cleanup on uninstall
├── README.txt                     # Plugin readme
├── LICENSE.txt                    # GPL v2
├── ARCHITECTURE.md                # Living architecture document
├── dev-notes.md                   # Order status feature dev notes
├── notes.md                       # This file
│
├── includes/                      # Core plugin infrastructure
│   ├── class-dukkan-plugin.php            # Main plugin class — loads deps, defines hooks
│   ├── class-dukkan-plugin-activator.php  # Activation routine, seeds defaults
│   ├── class-dukkan-plugin-deactivator.php
│   ├── class-dukkan-plugin-i18n.php       # Internationalization
│   ├── class-dukkan-plugin-loader.php     # Hook orchestrator
│   └── class-dukkan-plugin-updater.php    # Update notification (native WP update UI)
│
├── admin/                         # WordPress admin area
│   ├── class-dukkan-plugin-admin.php
│   ├── class-dukkan-plugin-woocommerce.php  # Custom order statuses + WC integration
│   ├── class-dukkan-plugin-order-status.php # Order status CRUD (AJAX)
│   ├── class-dukkan-plugin-product-addon.php
│   ├── css/
│   ├── js/
│   ├── images/
│   └── partials/                  # Admin page templates
│
├── public/                        # Front-end facing
│   ├── class-dukkan-plugin-public.php
│   ├── class-product-addon.php
│   ├── css/
│   ├── js/
│   └── partials/
│
├── api/                           # REST API endpoints
│   ├── class-dukkan-plugin-general.php              # General utilities
│   ├── class-dukkan-plugin-order-status-api.php     # Order status CRUD
│   ├── class-dukkan-plugin-product-addon-api.php    # Product addons
│   ├── class-dukkan-plugin-translatepress.php       # TranslatePress integration
│   ├── class-dukkan-plugin-dynamic-pricing-api.php  # WCDPD bridge (6 endpoints)
│   ├── class-dukkan-plugin-slim-seo-api.php         # Slim SEO bridge (2 endpoints, v1.0.5)
│   ├── dynamic-pricing-api-reference.json           # App dev reference — dynamic pricing
│   ├── slim-seo-api-reference.json                  # App dev reference — Slim SEO
│   ├── dynamic-pricing-notes.md
│   ├── dynamic-pricing-api-reference.json
│   ├── woo-extended/
│   │   └── class-dukkan-woo-extended-api.php
│   └── webhook/woo/
│       └── class-dukkan-woo-webhook.php
│
└── languages/                     # Translation files
    └── dukkan-plugin.pot
```

---

## Existing REST API Endpoints (All)

| Namespace | Route | Methods |
|-----------|-------|---------|
| `dukkan-order-status/v1` | `/statuses`, `/statuses/{id}` | GET, POST, PUT, DELETE |
| `dukkan-dynamic-pricing/v1` | `/rules`, `/rules/{uid}` | GET, POST, PUT, DELETE |
| `dukkan-dynamic-pricing/v1` | `/products/search` | GET |
| `dukkan-seo/v1` | `/posts/{id}/title` | PUT |
| `dukkan-seo/v1` | `/posts/{id}/description` | PUT |
| `dukkan-elementor/v1` | `/widgets/heading/controls` | GET |
| `dukkan-elementor/v1` | `/pages/{id}/headings`, `/pages/{id}/headings/{element_id}` | GET, PATCH |

All endpoints are publicly accessible (`__return_true` permission callback).
