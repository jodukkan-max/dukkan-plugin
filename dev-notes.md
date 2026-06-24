# Dev Notes — Custom WooCommerce Order Status Management

> Feature branch context. Update as work progresses.

---

## Completed Milestones

### Milestone 1: Core architecture

- [x] Created `admin/class-dukkan-plugin-order-status.php` — admin class with tab registration, data access, validation, and CRUD
- [x] Created `admin/partials/dukkan-order-status-settings.php` — tab content partial (initial two-column layout)
- [x] Created `api/class-dukkan-plugin-order-status-api.php` — REST API for order status CRUD
- [x] Updated `admin/class-dukkan-plugin-woocommerce.php` — dynamic registration of user-managed statuses alongside built-in ones
- [x] Registered all classes in `includes/class-dukkan-plugin.php` — `require_once` + instantiation
- [x] Added CSS to `admin/css/dukkan-plugin-admin.css` for order status UI

### Milestone 2: Modern UI redesign

- [x] Rewrote `admin/class-dukkan-plugin-order-status.php` — replaced `admin-post.php` handlers with AJAX handlers (`list`, `add`, `update`, `delete`, `reorder`)
- [x] Rewrote `admin/partials/dukkan-order-status-settings.php` — header with "Add Status" button, sortable list, modal popup for add/edit, delete confirmation modal
- [x] Replaced old CSS with modern card/modal/drag-drop styles matching plugin design
- [x] Added JS to `admin/js/dukkan-plugin-admin.js` — modal interactions, AJAX CRUD, jQuery UI Sortable drag-drop, delete confirmation, auto-generate slug, Enter key support
- [x] Added `jquery-ui-sortable` as script dependency in `admin/class-dukkan-plugin-admin.php`
- [x] Added `os_i18n` localized strings for all JS-facing text

### Milestone 3: Documentation & Conventions

- [x] Created `.cursorrules` — strict PHP 7.4+ OOP conventions for AI-assisted development
- [x] Created `ARCHITECTURE.md` — living blueprint covering all features, hooks, routes, options, and data flows
- [x] Created `dev-notes.md` — this file

---

## Current To-Do

### Immediate

- [ ] **Test in a WordPress environment** — verify tab appears, modals open, AJAX works, drag-drop persists order, statuses appear in WC admin
- [ ] **Test REST API endpoints** — verify all 5 routes with authentication
- [ ] **Test edge cases** — empty slug, duplicate slug, 20-char limit, slug update with same name, delete last status, concurrent operations
- [ ] **Test WooCommerce integration** — verify statuses appear in order status dropdown, status counts work, status transitions allowed
- [ ] **Test backward compatibility** — verify existing built-in statuses still work when toggle is enabled

### Future Enhancements

- [ ] Add bulk-delete functionality for multiple statuses
- [ ] Add status color/icon customization per status
- [ ] Add email notification triggers per custom status
- [ ] Add "order actions" (WooCommerce order actions metabox) for custom status transitions
- [ ] Add status history tracking (which admin changed which order to which status and when)
- [ ] Add import/export for custom statuses
- [ ] Add visual order status workflow diagram in admin
- [ ] Internationalize all remaining hardcoded strings (verify `.pot` file coverage)

---

## Notes

- Storage uses WordPress Options API (`dukkan_custom_order_statuses`) — no custom DB tables
- Statuses stored as indexed array of `{name, slug}` to preserve user-defined ordering
- Slug max length enforced at 20 characters (WooCommerce standard via `register_post_status`)
- `wc-` prefix auto-added during registration; stored slugs do NOT include the prefix
- Both admin UI and REST API share the same validation logic (but implemented separately due to different return types — array vs WP_Error)
- jQuery UI Sortable handles reordering; persisted to server on `update` event
- Delete uses a separate confirmation modal to prevent accidental data loss
- Auto-slug generation debounced at 300ms, only for new statuses (not edits)
- Toast notifications use existing `showToast()` utility
- All AJAX operations verify `wpldp_nonce` nonce and `manage_options` capability
