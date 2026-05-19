# CLAUDE.md

This file is auto-loaded by Claude Code when working in this repository. The full agent guide is **[AGENTS.md](AGENTS.md)** — read it before changing anything non-trivial. This file is a quick reference so the critical rules stay in front of you even if AGENTS.md isn't loaded.

## What this project is

Themeisle Tester is an internal WordPress admin plugin for QA/dev humans to create controlled testing conditions across Themeisle products and the shared SDK. **It is not a public plugin.** Domain language is in `CONTEXT.md` (Scenario / Utility / Danger Utility / Category / Group / Product). Architecture is summarised in `docs/architecture.md` and locked by the ADRs in `docs/adr/`.

## The non-negotiables (architecture invariants)

Don't break these without writing a new ADR. The full list with rationale is in AGENTS.md.

1. **The Dashboard is server-rendered (ADR-0006).** No React, no `@wordpress/build`, no bundler. Client code: `admin/js/libs/datastar.min.js` (partial updates, ADR-0008) + `admin/js/dashboard.js` (tabs, list fields).
2. **Array schemas only (ADR-0004).** Products extend by passing arrays to `$registry->register()` on the `ttp_register_items` action. No class contracts.
3. **`ttp_` prefix everywhere (ADR-0002).** Hooks, option keys, REST namespace, CSS classes, data attributes. `window.ttpTester` is reserved but unpopulated.
4. **First-mutation backups for Danger Utilities (ADR-0005).** Backup before the first mutation of each target; never overwrite later. Restore reads the backup and deletes it.
5. **Three Testing Item types, fixed.** `scenario` / `utility` / `danger_utility`.
6. **Runtime safety gates always apply.** Every Scenario apply and Danger Utility mutation respects `TTP_DISABLED` and `apply_filters( 'ttp_is_runtime_enabled', $enabled )`.
7. **Modest internal code.** Registry / stores / applicator / sanitizer / REST / admin presentation (`TTP_Admin_Page` + small renderers under `admin/`). Don't add factories or value objects without demonstrated repetition.
8. **Scenarios don't mutate state.** A Scenario's `apply` callback attaches hooks only. Anything that writes data is a Danger Utility, and needs the backup-before-mutate logic.
9. **PHP enforces every domain and safety rule.** Nonces + `manage_options` + `TTP_Schema_Sanitizer` on every input path. Never trust the client.
10. **Internal addons first (ADR-0009).** First-party Testing Items live in `includes/addons/` and register via `TTP_Addon_Loader`; product plugins use `ttp_register_items` only for edge cases. Do not depend on addon classes from outside the plugin.

## Linters that must pass before you commit

```
composer run lint     # PHPCS — WordPress coding standards, PHP 7.4+, prefix=ttp
composer run phpstan  # PHPStan level 9 + szepeviktor/phpstan-wordpress
npm run lint          # Biome (JS + CSS)
```

PHPStan is strict at level 9. The codebase carries shape information via `@phpstan-type NormalizedItem` on `TTP_Item_Registry` — use `@phpstan-import-type` / `@phpstan-param` annotations rather than runtime guards when you can.

## Don't

- Don't add React, Vue, Svelte, or any bundler.
- Don't introduce class-based item contracts (interfaces, abstracts, base classes).
- Don't widen the schema sanitizer to passthrough — it's the security boundary.
- Don't rename `ttp_scenario_state` or `ttp_danger_backups` option keys.
- Don't write to `$_POST` / `$_GET` without nonce verification and sanitization.
- Don't silently hide unavailable items — render the card disabled with `unavailable_reason`.
- Don't suppress PHPStan errors with `@phpstan-ignore` to make red disappear. Fix the type.

## Where to go next

- `AGENTS.md` — the complete guide (extension contract, field types, file layout, gotchas, when-to-ADR rules).
- `CONTEXT.md` — domain language definitions.
- `docs/PROJECT.md` — scope, V1 items, full `ttp_*` hook contract.
- `docs/ENGINEERING.md` — engineering rules.
- `docs/architecture.md` — C4 diagrams + gap analysis.
- `docs/design-system.md` — Internal QA Cockpit visual identity: palette (parametric oklch), type scale, states, motion, anti-patterns, contribution checklist. Read before any UI change.
- `docs/adr/0009-internal-addon-structure.md` — internal addons layout and SDK sub-module split.
- `docs/adr/` — every architectural decision (including supersedes notes).
