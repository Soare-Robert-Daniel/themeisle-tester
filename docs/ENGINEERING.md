# Themeisle Tester Engineering Guidelines

## Public API

- Keep product-facing APIs boring: plain arrays, callbacks, and WordPress hooks.
- Product plugins must not depend on internal platform classes or on `includes/addons/` (`TTP_Addon`, `TTP_Addon_Loader`, `TTP_Addon_*` feature classes). Those paths are for first-party code inside Themeisle Tester only; external plugins extend through `ttp_register_items` and documented `ttp_*` hooks.
- Treat REST routes as Dashboard UI endpoints, not as the product extension API.
- Use stable Testing Item IDs; once shipped, IDs should not change because state, backups, REST requests, and UI keys depend on them.
- Document every public `do_action()` and `apply_filters()` call at the call site with who should use it and when it fires.

## Item Definitions

- Normalize raw item arrays once after registration.
- After normalization, avoid checking loose array keys throughout the codebase.
- Validate definitions loudly: invalid items should produce useful admin/debug notices with the item ID and missing or invalid field.
- Prefer explicit schema over clever inference.
- Require core fields such as `id`, `type`, `categories`, `product`, `label`, and the callbacks required for that item type.
- Keep callback requirements tied to item type:
  - Scenarios use `apply`.
  - Utilities use `inspect` or `run`.
  - Danger Utilities use `inspect`, `mutate`, and `restore`.

## Internal Structure

- Keep internal code modest until real duplication appears.
- Start with a small registry, store, hook applicator, admin page, and REST layer.
- Add factories, commands, serializers, or extra value objects only when they remove demonstrated repetition or clarify a real boundary.
- Keep one class focused on one WordPress boundary:
  - registry validates item definitions;
  - store handles options;
  - hook applicator attaches runtime hooks;
  - REST controller maps requests to application behavior;
  - PHP views render the Dashboard shell.
- Do not create classes that render UI, mutate options, call product callbacks, and parse requests all at once.

## Runtime Behavior

- Keep callbacks as pure as practical.
- Scenario `apply` callbacks should attach filters/actions or provide runtime transformations.
- Scenario callbacks should not render UI, save state, or read request globals.
- Enabled Scenarios apply only after item registration has closed.
- Keep inline boot data small: include item definitions and saved state, but fetch expensive scanner data on demand.
- Show active Scenarios in an admin notice outside the Dashboard so runtime overrides are visible.

## Safety

- PHP enforces domain and safety rules; React only presents controls and feedback.
- Never rely on React validation alone.
- Centralize capability and nonce checks.
- Do not use raw `$_POST`, `$_GET`, or unsanitized REST payloads directly.
- Route all input through request parsing, validation, and sanitization.
- Keep Scenario state and Danger Utility backups in predictable Tester-owned options:
  - `ttp_scenario_state`
  - `ttp_danger_backups`
- Treat Danger Utilities as a separate lane.
- If an item mutates Product or site data, it is a Danger Utility and must support warning, backup, and restore behavior.
- Do not let normal Scenarios gain destructive behavior.
- Make reset behavior visible for every card: reset Tester state, restore backup, or no reset available.

## Testing

- Run contract tests with `composer run test` (PHPUnit). Requires the WordPress test library: set `WP_TESTS_DIR` or install via `bin/install-wp-tests.sh` from the [WordPress develop repo](https://github.com/WordPress/wordpress-develop).
- Static analysis (`composer run phpstan`) and linters remain required before merge.
- Cover at least these cases:
  - a fake Product registers a valid Testing Item;
  - an invalid item is rejected with a useful reason;
  - duplicate IDs are rejected;
  - an enabled Scenario applies after registration closes;
  - an unavailable item is shown disabled with a reason;
  - a Danger Utility backs up before mutation;
  - a Danger Utility restore uses the original first-change backup;
  - runtime application is blocked by `TTP_DISABLED`;
  - capability and nonce checks protect REST operations.

## Principle

Themeisle Tester should be easy for Product teams to extend through hooks and hard to misuse accidentally. Prefer a simple public contract and boring internal code over clever abstractions.
