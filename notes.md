# Dukkan Plugin — Work Log & Structure

> Last updated: v1.0.34 — September 12, 2026

---

## Recent Changes

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

1. Build the ZIP: `zip -r dukkan-plugin.zip dukkan-plugin -x "dukkan-plugin/.git/*" "*.DS_Store" "*.backup"`
2. Bump version in `dukkan-plugin.php` (header comment + `DUKKAN_PLUGIN_VERSION` constant)
3. Bump version and package URL in `version.json`
4. Commit and push
5. Create a GitHub Release with the same tag (`vX.Y.Z`) and attach the ZIP

Updates are applied manually by the admin via the "update now" button on the Plugins screen (or WordPress auto-updates if enabled). There is no scheduled cron and no automatic background install.

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

All endpoints are publicly accessible (`__return_true` permission callback).
