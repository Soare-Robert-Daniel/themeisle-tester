# Themeisle Tester — Agent Guide

This file tells AI coding agents (Claude, Cursor, Copilot, etc.) how to work in this codebase without destroying its architecture. Read it before you change anything.

Themeisle Tester is an internal WordPress admin plugin for QA/dev humans to create controlled testing conditions across Themeisle products and the shared SDK. It is **not** a public plugin.

## Read first

In this order, before changing code:

1. `CONTEXT.md` — the domain language. The atomic unit is **Testing Item**, with three subtypes: **Scenario**, **Utility**, **Danger Utility**. Categories and Groups are how cards are organised in the Dashboard. Do not coin new terms.
2. `docs/PROJECT.md` — scope, V1 Testing Items, the full `ttp_*` hook contract.
3. `docs/ENGINEERING.md` — engineering rules (public API discipline, internal modesty, safety, testing).
4. `docs/architecture.md` — synthesis with C4-style diagrams and a gap analysis against the original plan.
5. `docs/design-system.md` — Internal QA Cockpit visual identity (ADR-0007). Token reference, component anatomy, motion, states, microcopy, anti-patterns. Read before any UI change.
6. `docs/adr/*.md` — every architectural decision, including supersedes notes. ADRs are append-only; don't edit historical content, write a new ADR if you need to change a decision.

## Non-negotiables

These are architecture invariants. Do not change them without writing a new ADR.

1. **The Dashboard is server-rendered (ADR-0006).** PHP renders the full UI. There is no React, no `@wordpress/build`, no bundler. Client code is `admin/js/libs/datastar.min.js` (partial page updates via HTML morph) plus `admin/js/dashboard.js` (tabs and `data-ttp-list` rows). See ADR-0008. If you think you need a heavier JS framework, write an ADR first.
2. **Array schemas only (ADR-0004).** Products register Testing Items by passing a plain array to `$registry->register()` from a callback hooked to the `ttp_register_items` action. No class-based contracts. No interfaces to implement. The validator runs once at finalize time; after that, the registry is closed.
3. **`ttp_` prefix everywhere (ADR-0002).** Public PHP hooks, option keys, REST namespace (`ttp/v1`), CSS classes (`.ttp-*`), data attributes (`data-ttp-*`). `window.ttpTester` is reserved as a JS namespace but is not populated in v1.
4. **First-mutation backups for Danger Utilities (ADR-0005).** Every Danger Utility writes the original target value to `ttp_danger_backups` *before the first mutation* of that target. Backups are keyed by `{utility_id, target_id}` and are never overwritten by subsequent mutations. `restore` reads the backup and deletes it.
5. **Three Testing Item types, fixed.** `scenario` / `utility` / `danger_utility`. Adding a new type changes the public contract — write an ADR first.
6. **Runtime safety gates always apply.** Every code path that applies a Scenario or runs a Danger Utility mutation respects both:
   - the `TTP_DISABLED` constant (kill switch), and
   - the `apply_filters( 'ttp_is_runtime_enabled', $enabled )` filter (environment override).
7. **Modest internal code** (ENGINEERING.md §"Internal Structure"). The PHP layer is: registry, two stores (scenario + danger backups), hook applicator, schema sanitizer, REST controller, admin page (renderer + form handler), admin notices. Add factories / commands / value objects only when they remove demonstrated repetition.
8. **Scenarios do not mutate state.** A Scenario's `apply` callback may attach `add_filter` / `add_action` and provide runtime transformations. It must not render UI, save state, or read request globals. Anything that writes to options or product data is a Danger Utility.
9. **PHP enforces every domain and safety rule.** Never rely on client-side validation. All form posts go through `check_admin_referer` + `current_user_can('manage_options')` + `TTP_Schema_Sanitizer`. All REST routes go through nonce + `manage_options` + `TTP_Schema_Sanitizer`.

## Extension contract

**Primary path — internal addons (ADR-0009).** Most Testing Items ship inside Themeisle Tester under `includes/addons/{name}/`. Each addon is a class implementing `TTP_Addon` with a `register( TTP_Item_Registry $registry )` method that calls `$registry->register()` with array schemas (SDK uses `TTP_Addon_SDK::register_hooks()` with prioritized module callbacks instead). `TTP_Addon_Loader::load_addon_files()` reads [`includes/addons/manifest.php`](includes/addons/manifest.php); the loader hooks addons to `ttp_register_items` at boot. To add a first-party integration: add paths to the manifest, implement `TTP_Addon`, append the class (and priority) to `TTP_Addon_Loader::$addons`.

**Optional path — product plugins.** A Themeisle product plugin may register additional items the same way when no internal addon covers the case: hook `ttp_register_items` and pass arrays to `$registry->register()`. Do not reach into platform classes (`TTP_*` in `includes/class-ttp-*.php`) or addon classes under `includes/addons/`.

```php
// Product plugin (edge cases) — internal addons use the same register() call inside TTP_Addon::register().
add_action( 'ttp_register_items', function ( $registry ) {
    $registry->register( array(

        // === required ===
        'id'         => 'unique_id',           // sanitize_key'd; lowercase a-z0-9_-
        'type'       => 'scenario',            // 'scenario' | 'utility' | 'danger_utility'
        'categories' => array( 'My Tab' ),     // ≥1 non-empty strings; alphabetic sort decides tab order
        'product'    => 'My Product Name',
        'label'      => 'Card title',

        // === optional ===
        'description'        => 'Short blurb.',
        'group'              => 'Optional Sub-group',
        'width'              => 'normal',        // 'normal' | 'wide' | 'full' — grid span; default 'normal' (or 'full' for danger_utility). See docs/design-system.md §Page anatomy.
        'fields'             => array( /* see Field types below */ ),
        'requires'           => array( /* external APIs — see below */ ),
        'is_available'       => 'callable',     // optional extra gate; ANDed with `requires`
        'unavailable_reason' => 'callable',     // optional; used when unavailable and `requires` did not set a reason

        // === behavior callbacks, required by type ===
        // scenario        → 'apply'
        // utility         → at least one of 'inspect', 'run'
        // danger_utility  → 'inspect', 'mutate', 'restore' (all three)
        'apply'   => 'callable',  // ($item, $state) => void — attach hooks only
        'inspect' => 'callable',  // ($item, $payload) => array|WP_Error
        'run'     => 'callable',  // ($item, $payload) => array|WP_Error
        'mutate'  => 'callable',  // ($item, $target, $payload) => array|WP_Error
        'restore' => 'callable',  // ($item, $target, $backup) => array|WP_Error
    ) );
} );
```

**`requires`** — declare external classes, functions, and capabilities before callbacks run. Keys: `classes`, `functions`, `capabilities` (each a map of symbol => unavailable message). The registry checks them at finalize/render time; REST, form POST, and Dashboard actions also block when unmet. Reuse presets from `TTP_Integration_Checks::require_*()` for WooCommerce/PPOM addons.

**Presentation (optional)** — keep Dashboard markup out of shared views:

- `render_inspect` — `callable( $item, $inspect_result, TTP_Admin_Page $page ): void` for custom inspect layout.
- `render_run` — `callable( $item, TTP_Admin_Page $page ): void` when the default run chrome is not enough.
- `inspect_on_load` — `bool`, default `true`. Set `false` for heavy inspect utilities; the card shows a **Load** button that POSTs to `ttp/v1/.../inspect` and morphs `#ttp-card-inspect-{id}`.
- `inspect_refresh` — `bool`, default `false`. Set `true` when the inspect surface shows live state that drifts (option values, log entries, cached files, license/install timestamps). The card renders a **Refresh** button above the inspect body. Leave off for static or selection-only inspect panels — `install_plugin_from_zip` is the canonical "don't opt in" example.
- `run_ui` — `array( 'transport' => 'datastar' | 'progressive' | 'zip_batch' )` for platform run forms when `render_run` is omitted.

Per-item filters (no class needed):

- `apply_filters( "ttp_item_definition_{$id}", $item )` — adjust a single normalized item.
- `apply_filters( 'ttp_registered_items', $items )` — adjust the whole map.
- `apply_filters( 'ttp_item_available', $bool, $item )` — gate visibility.
- `apply_filters( 'ttp_item_unavailable_reason', $string, $item )` — override the disabled message.
- `apply_filters( "ttp_scenario_state_{$id}", $row, $id )` — read-only state adaptation.

## Field types

| `type` | UI | Server sanitization |
|---|---|---|
| `text` (default) | `<input type="text">` | `sanitize_text_field` |
| `date` | `<input type="date">` | YYYY-MM-DD regex + `sanitize_text_field` |
| `url` | `<input type="url">` | `esc_url_raw` |
| `url_list` | repeatable `<input type="url">` rows with [×] / + Add (`admin/js/dashboard.js` clones a `<template>`) | submits as `ttp_params[id][]`; sanitizer accepts array or newline-string, `esc_url_raw` per entry, returns `string[]` |
| `email` / `number` | native HTML5 | falls back to `sanitize_text_field` (tighten if you need stricter rules) |
| `select` | `<select>` with `options` array | enum-validates against `options` |
| `toggle` / `boolean` | custom switch | `(bool)` |
| `integer` | text input | numeric check + `(int)` cast |
| `array` / `json` | (no inline renderer yet) | `map_deep` + `sanitize_text_field` |

`get_post_payload()` uses `sanitize_textarea_field` (not `sanitize_text_field`) so multi-line fields keep their newlines through the form pipeline. Single-line inputs are unaffected because browsers won't post newlines through them.

Adding a new field type means editing **both**:

- `TTP_Schema_Sanitizer::sanitize_value` (new case branch)
- `TTP_Admin_Page::render_fields` (new render branch)

…and re-running PHPCS + PHPStan + Biome.

## File layout

```
themeisle-tester.php                    bootstrap: defines TTP_* constants; loads files; boots TTP_Plugin on plugins_loaded p20
includes/
  class-ttp-plugin.php                  service container; wires every other class
  class-ttp-item-registry.php           register / normalize / validate items; @phpstan-type NormalizedItem
  class-ttp-scenario-store.php          ttp_scenario_state option I/O (autoload=false)
  class-ttp-danger-backup-store.php     ttp_danger_backups option I/O (autoload=false)
  class-ttp-hook-applicator.php         apply enabled Scenarios after registration closes
  class-ttp-schema-sanitizer.php        server-side field sanitization (the security boundary)
  class-ttp-activity-store.php          Dashboard action log (autoload=false)
  class-ttp-dashboard-actions.php       shared apply/inspect/run/mutate/restore handlers
  class-ttp-dashboard-renderer.php      Datastar morph HTML fragments
  class-ttp-rest-html.php               REST HTML negotiation for Datastar
  class-ttp-rest-controller.php         ttp/v1 REST routes for external tooling
  interface-ttp-addon.php               TTP_Addon contract for addon classes
  class-ttp-addon-loader.php            manifest loader + wires addons to ttp_register_items
  addons/
    manifest.php                        require list for addon PHP files
    sdk/
      class-ttp-addon-sdk.php           SDK hook priorities (BF 10, license 20, surveys 30, install 40)
      class-ttp-sdk-black-friday.php    BF scenarios + utilities
      class-ttp-sdk-surveys.php         survey override scenario
      class-ttp-sdk-licensing.php       license/install Danger Utilities
    wordpress/
      class-ttp-addon-wordpress.php     WordPress admin utilities (install from ZIP)
admin/
  class-ttp-admin-page.php              menu, Dashboard shell; delegates to renderers below
  class-ttp-view-loader.php             admin/views partial loader
  class-ttp-dashboard-layout-renderer.php tabs, panels, group grids
  class-ttp-admin-form-handler.php      classic form POST fallback (ADR-0008)
  class-ttp-admin-assets.php            Dashboard CSS/JS enqueue
  class-ttp-datastar.php                Datastar REST/form attributes
  class-ttp-field-renderer.php          card field markup
  class-ttp-flash-renderer.php          flash notices
  class-ttp-danger-table-renderer.php   Danger Utility tables + mutate/restore forms
  class-ttp-activity-renderer.php       recent activity section
  class-ttp-inspect-result-renderer.php utility/danger inspect result tables
  class-ttp-scenario-summary-renderer.php scenario "Saved" param summary
  class-ttp-admin-notices.php           cross-admin active-Scenario notice + registry errors
  css/dashboard.css                     scoped tokens + components (.ttp-dashboard root)
  js/libs/datastar.min.js               hypermedia morph (ADR-0008)
  js/dashboard.js                       vanilla-JS tab + list field controller
docs/
  PROJECT.md                            scope + hook contract
  ENGINEERING.md                        engineering rules
  architecture.md                       C4 synthesis + gap analysis
  adr/                                  numbered architectural decisions
CONTEXT.md                              domain language
tests/
  phpstan/constants.php                 declares TTP_* constants for static analysis only
.phpcs.xml.dist                         WordPress coding standards, prefix=ttp, PHP 7.4+
phpstan.neon                            level 9 + WordPress extension + constants bootstrap
biome.json                              Biome formatter + linter config (JS + CSS)
```

## Linters that must pass

```
composer run lint     # PHPCS — WordPress coding standards, PHP 7.4+, ttp prefix
composer run phpstan  # PHPStan level 9 + szepeviktor/phpstan-wordpress
npm run lint          # Biome (JS + CSS)
```

Run the relevant linter for the files you touch. PHP changes → PHPCS + PHPStan. CSS / JS changes → Biome. Don't ship anything red. PHPStan especially is non-trivial to please at level 9 — read the existing `@phpstan-type` / `@phpstan-param` annotations on the registry to understand how the codebase carries shape information.

## Common gotchas

- **PHPStan can't see `TTP_*` constants** without the `tests/phpstan/constants.php` bootstrap. If you add a new `TTP_*` constant, declare it there too.
- **SDK hooks use dashes** (`themeisle-sdk/survey/{slug}`). PHPCS flags this via `ValidHookName.UseUnderscores`. Keep the inline `// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Targets the SDK-defined hook name.` at every such call site.
- **REST + form posts use different nonces.** REST routes accept the WP REST nonce via `X-WP-Nonce`. Forms use `check_admin_referer( 'ttp_admin_action', 'ttp_nonce' )`.
- **Tabs sort alphabetically** by category name (`ksort`). You cannot pin a tab order without renaming.
- **Item order within a tab is registration order in the DOM.** The grid uses `grid-auto-flow: row dense` to fill gaps caused by mixed card widths, so *visual* placement may rearrange — a `normal` card declared after a `wide` card can render before it if doing so fills an earlier hole. Group order within a tab is "first encountered wins" — also registration order. If you need a strict visual ordering, split the items into their own `group`.
- **Group sub-headings render only when ≥2 distinct non-empty `group` values exist** in the panel. A single group or none → flat grid, no heading.
- **Toggle defaults.** Unchecked checkboxes don't appear in `$_POST` at all; the sanitizer converts missing values to `(bool) false`. There is no "default-true" toggle without server-side logic.
- **Active Scenarios are highlighted twice on purpose**: a green dot on the tab badge + an "Active" pill + a green left border on the card. Don't dedupe these — testers need the redundancy.

## When you change PHP

- Run `composer run lint && composer run phpstan` before finishing.
- New public hook (`do_action` / `apply_filters`)? Document it at the call site with a docblock explaining who should use it and when it fires (per ENGINEERING.md §"Public API").
- Mutating Product or site data? It goes in a **Danger Utility**, not a Scenario. Add backup before mutation.
- Reading request input? Always nonce + sanitize. Don't pass `$_POST` values into the registry without going through `TTP_Schema_Sanitizer`.

## When you change the UI

- Server-render it. Datastar handles in-place card actions; `admin/js/dashboard.js` handles tabs and list fields.
- New CSS goes in `admin/css/dashboard.css`, scoped under `.ttp-dashboard`.
- Read `docs/design-system.md` first. It is the canonical reference for the Internal QA Cockpit identity (ADR-0007): palette (parametric oklch), type scale, states, motion, anti-patterns, contribution checklist. New patterns get written back into it.
- Use the existing tokens (`var(--ttp-space-*)`, `var(--ttp-text)`, `var(--ttp-accent)`, etc.). Don't introduce one-off values that bypass the scale.
- Semantic colors are `oklch()` parametrics — adding a new accent family means picking a hue (`--ttp-h-*`), not new lightness/chroma.
- Biome enforces double quotes, no inner-paren spacing, and forbids descending-specificity CSS selectors. Run `npm run lint` after edits.

## When you change docs

- Update `docs/PROJECT.md` and `docs/architecture.md` together if a contract changes.
- New architectural decision → write a numbered ADR in `docs/adr/`. Use the next sequence number.
- Superseding an ADR → add `> **Status:** Superseded by [ADR-####](####.md) on YYYY-MM-DD.` at the top of the old one. Don't delete it; the history is part of the record.
- `MEMORY.md`-style auto-memory lives outside this repo; don't write personal-note files here.

## Don't

- Don't add React, Vue, Svelte, or any bundler. Don't introduce `@wordpress/build`, `@wordpress/scripts`, Vite, esbuild, or webpack.
- Don't introduce class-based item contracts (interfaces, abstracts, base classes). Array schemas only.
- Don't widen the schema sanitizer to pass values through unchanged — it's the security boundary.
- Don't rename `ttp_scenario_state` or `ttp_danger_backups` option keys. Existing installs depend on them.
- Don't write to `$_POST` / `$_GET` without nonce verification and sanitization.
- Don't bypass `is_callable()` guards on item callbacks — the registry guarantees only that *required* callbacks (per type) are callable; optional ones are nullable and must be checked at the call site.
- Don't silently hide unavailable items. Render the card disabled with the `unavailable_reason` string (the renderer already does this — preserve it).
- Don't run a Danger Utility mutation without writing the first-mutation backup first.
- Don't suppress PHPStan errors with `@phpstan-ignore` to make red disappear. Fix the type or restructure the code; the existing `@phpstan-type NormalizedItem` shape exists so you don't have to.

## When in doubt

- Read the ADR that governs the area you're touching. If no ADR exists, the area is open territory but adding a precedent quietly is bad form — write an ADR.
- Prefer adding to the existing seam (a new item in the right `includes/addons/*` addon, a new field type) over inventing a new seam (a new registry, a new lifecycle hook).
- If your change moves the architecture, mention which ADR it touches in your commit message and update or supersede that ADR in the same change.
