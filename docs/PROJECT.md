# Themeisle Tester Project Brief

## Purpose

Themeisle Tester is an internal WordPress admin plugin for creating controlled testing conditions across Themeisle products and shared SDK behavior. It gives humans a single Dashboard where they can configure Scenarios, run Utilities, inspect product state, and reset test changes without editing product code manually or changing server infrastructure.

## Scope

Themeisle Tester provides the testing platform:

- a WordPress admin Dashboard rendered entirely by PHP;
- a Registry where Products declare Scenarios and Utilities;
- global per-site Scenario state;
- REST endpoints for saving, resetting, and on-demand inspection (JSON for tooling; the Dashboard uses the same routes with `Datastar-Request` / `Accept: text/html` for HTML morph responses);
- first-party Testing Items under `includes/addons/` (SDK, WordPress, and future product integrations).

Product plugins may provide additional product-specific behavior when needed:

- register Scenarios and Utilities through `ttp_register_items` for items not yet in an internal addon;
- they expose hooks/filters where non-destructive Scenarios can apply runtime changes;
- they may enqueue optional Dashboard-only scripts through `ttp_enqueue_controls` (a forward-compatibility hook; v1 does not ship a Control registry).

## Non-Goals

Themeisle Tester is not:

- a public wordpress.org plugin;
- a React SPA or single-page app of any kind;
- a replacement for automated tests;
- a generic WordPress site debugging suite;
- a place for product plugins to mount React applications;
- a normal settings page for permanent product configuration.

## Architecture

The Dashboard is fully PHP-rendered, with a small vanilla-JS enhancement for tab navigation (see [ADR-0006](adr/0006-server-render-the-dashboard.md)).

- PHP renders the Dashboard shell, Categories, and every Testing Item card.
- Forms post through Datastar to `ttp/v1` REST routes (HTML morph) or fall back to classic PHP POST (`TTP_Admin_Page::handle_post()` when JS is off). Both paths use `TTP_Dashboard_Actions` and `TTP_Schema_Sanitizer`.
- `admin/js/libs/datastar.min.js` patches cards, flash, and tab indicators in place; `admin/js/dashboard.js` handles tab panels and `data-ttp-list` rows.
- Active Scenarios are shown in a sticky admin notice outside the Dashboard so testers do not forget runtime behavior has been overridden.
- Internal code should stay modest: prefer a small registry, store, applicator, admin, and REST layer before introducing extra factories, commands, or serializers.
- Product plugins must extend Themeisle Tester through public hooks and callbacks, not by reaching into internal classes.
- Testing Items may declare optional presentation metadata (`render_inspect`, `render_run`, `inspect_on_load`, `run_ui`) so addons own custom card markup without editing `admin/views/card-utility.php`. Heavy inspect utilities set `inspect_on_load` to `false` and load via REST + Datastar morph.

Public API naming follows the `ttp` prefix:

- hooks: `ttp_register_items`, `ttp_enqueue_controls`;
- REST namespace: `ttp/v1`;
- CSS and data attributes: `.ttp-*`, `data-ttp-*`;
- JavaScript global namespace: `window.ttpTester` (reserved; not populated in v1).

## V1 Testing Items

SDK addon (`includes/addons/sdk/`):

- Scenario: override SDK current date through `themeisle_sdk_current_date`.
- Scenario: swap Black Friday sale URL domains through `themeisle_sdk_blackfriday_data`.
- Utility: clear the current user's Black Friday dismissed notice.
- Utility: provide Black Friday quick date helpers for sale start, Black Friday, and sale end.
- Danger Utilities (ported from `../test-black-friday`): license data scanner/editor for `*_license_data` options; install timestamp scanner/editor for `*_install` options (includes embedded force license refresh: clears `ti_license_cache`, POSTs `ti/v1/license/refresh`).
- Utility (dashboard-hidden, embedded in install timestamp card): force license refresh.
- Utility (`includes/addons/sdk/logger/`): logger inspector — read-only view of each SDK product’s `{key}_logger_flag` consent, `{key}_logger_data` payload, scheduled `{key}_log_activity` cron, and JS telemetry queue (mirrors `ThemeisleSDK\Modules\Logger`). Inline **Send log now** / **Send all active logs** buttons POST the same payload shape as `Logger::send_log()` to `api.themeisle.com/tracking/log` for QA.

WordPress addon (`includes/addons/wordpress/`):

- Utility: install plugins — quick-install shortcuts for curated plugins (catalog in `ttp-popular-plugins-catalog.php`) plus custom ZIP URL installs.

PPOM addon (`includes/addons/ppom/`):

- Utility: inspect field groups — read-only list of all PPOM field group rows from `{prefix}nm_personalized`, including decoded field definitions and raw `the_meta` JSON.
- `requires`: `TTP_Integration_Checks::require_ppom()` (WooCommerce product classes + `ppom_meta_repository()`).

WooCommerce addon (`includes/addons/woocommerce/`):

- Utility: generate random products — bulk-create simple or variable WooCommerce products with randomized names, SKUs, and prices; optional attribute DSL for variations; products tagged with `_ttp_generated` meta.
- `requires`: `TTP_Integration_Checks::require_woocommerce_products()` (WooCommerce product classes + `edit_products`).
- Utility: delete generated products — permanently removes all products tagged by Themeisle Tester.

Danger Utilities must warn clearly before mutation and store a backup before the first change so reset can restore the original values.

## Extension Model

Products register Testing Items with an array schema:

```php
add_action(
	'ttp_register_items',
	function ( $registry ) {
		$registry->register(
			array(
				'id'         => 'example_scenario',
				'type'       => 'scenario',
				'categories' => array( 'Licensing' ),
				'product'    => 'Example Product',
				'label'      => 'Example scenario',
				'fields'     => array(),
				'apply'      => 'example_apply_scenario',
			)
		);
	}
);
```

Common item callbacks:

- `apply`: applies an enabled Scenario to WordPress or Product hooks.
- `inspect`: returns on-demand data for an inspector Utility.
- `run`: executes a stateless Utility action.
- `mutate`: performs a Danger Utility change after backup.
- `restore`: restores a Danger Utility backup.
- `is_available`: reports whether dependencies are present.
- `unavailable_reason`: explains why an item is disabled.

Product plugins may enqueue scripts on the Dashboard only via the `ttp_enqueue_controls` action. v1 ships no JavaScript Control registry — fields are rendered by PHP from the array schema. The hook exists as a forward-compatibility point if a future Dashboard surface needs client-side controls.

```php
add_action(
	'ttp_enqueue_controls',
	function () {
		wp_enqueue_script( 'example-ttp-controls' );
	}
);
```

### Hook Contract

Themeisle Tester is a hook-first platform. REST routes exist for the Dashboard UI; PHP hooks are the product extension API.

Registration lifecycle:

```php
do_action( 'ttp_register_items', $registry );
do_action( 'ttp_items_registered', $registry );
```

Product plugins register Scenarios and Utilities on `ttp_register_items`. Themeisle Tester applies enabled Scenarios only after registration has closed.

Item filtering:

```php
$items = apply_filters( 'ttp_registered_items', $items );
$item  = apply_filters( "ttp_item_definition_{$item_id}", $item );
```

Availability:

```php
$available = apply_filters( 'ttp_item_available', $available, $item );
$reason    = apply_filters( 'ttp_item_unavailable_reason', $reason, $item );
```

Scenario state:

```php
$state = apply_filters( 'ttp_scenario_state', $state );
$state = apply_filters( "ttp_scenario_state_{$scenario_id}", $state, $scenario_id );
```

Runtime Scenario application:

```php
do_action( 'ttp_before_apply_scenario', $item, $state );
do_action( 'ttp_after_apply_scenario', $item, $state );
```

Utility execution:

```php
do_action( 'ttp_before_run_utility', $item, $payload );
do_action( 'ttp_after_run_utility', $item, $payload, $result );
```

Danger Utility mutation and restore:

```php
do_action( 'ttp_before_mutate_danger_utility', $item, $target, $payload );
do_action( 'ttp_after_mutate_danger_utility', $item, $target, $payload, $result );
do_action( 'ttp_before_restore_danger_utility', $item, $target );
do_action( 'ttp_after_restore_danger_utility', $item, $target, $result );
```

Assets and safety:

```php
do_action( 'ttp_enqueue_controls' );

$enabled = apply_filters( 'ttp_is_runtime_enabled', $enabled );
```

The runtime safety filter allows Themeisle environments to define when Scenarios and Danger Utilities are allowed to run.

## State And Safety

- Scenario state is stored globally per site.
- Multisite uses normal per-site options; subsites are independent and Network Admin does not provide a unified v1 view.
- Utilities are stateless by default.
- Danger Utilities may mutate Product or site data only with backup/restore support.
- Danger Utility backups are stored in Tester-owned options, keyed by utility ID and target identifier, and are written only before the first mutation for that target.
- All REST reads and mutations require a valid nonce and `manage_options`.
- Unavailable Testing Items are shown disabled with a reason instead of being silently hidden.
- Runtime Scenario application must support a global kill switch, `TTP_DISABLED`, that prevents Scenarios from applying.
- Production environments must be guarded before runtime Scenarios or Danger Utilities run.

## Related Documentation

- `CONTEXT.md` defines domain language only.
- `docs/adr/` records architectural decisions and trade-offs.
- This file records the living project scope and intended system behavior.
