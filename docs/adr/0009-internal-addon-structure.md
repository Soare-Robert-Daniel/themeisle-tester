# Internal Addon Structure

Date: 2026-05-19

Most Testing Items ship inside Themeisle Tester rather than in separate product plugins. First-party integrations live under `includes/addons/{name}/` as **addons**: PHP classes that register array schemas on `ttp_register_items`, using the same public hook external products may use for edge-case items.

## Context

`TTP_Bundled_Items` held every shared Scenario, Utility, and Danger Utility in one ~1000-line class. As more product and service integrations are added internally, a monolithic bundled class would grow without a clear boundary between the **platform** (registry, stores, REST, Dashboard) and **integrations** (SDK hooks, WordPress install helpers, future Neve/Otter items).

## Decision

1. **Core platform** stays in `includes/class-ttp-*.php` and `admin/`.
2. **First-party integrations** live in `includes/addons/{integration}/class-ttp-addon-{integration}.php`.
3. **`TTP_Addon_Loader`** wires addons by hooking each addon's `register( TTP_Item_Registry )` to `ttp_register_items` at default priority. The loader holds an explicit class list (no glob autoload in v1).
4. **External product plugins** may still call `ttp_register_items`; that remains the public extension API for product-specific items not yet promoted to an internal addon.

### v1 addons

| Addon | Path | Responsibility |
|-------|------|----------------|
| SDK | `includes/addons/sdk/` (orchestrator + feature modules) | Black Friday, surveys, license/install timestamp Danger Utilities |
| WordPress | `includes/addons/wordpress/class-ttp-addon-wordpress.php` | Install plugins from ZIP URLs |

Item IDs, Dashboard categories, and runtime behavior are unchanged from the former bundled class split.

### SDK sub-modules (large integrations)

When an addon outgrows one file, split by feature area. **SDK** is the reference implementation of this pattern.

| Role | File | Responsibility |
|------|------|----------------|
| Orchestrator | `class-ttp-addon-sdk.php` | `register_hooks()` wires feature modules to `ttp_register_items` at priorities 10–40 |
| Black Friday | `class-ttp-sdk-black-friday.php` | `sdk_current_date`, `sdk_blackfriday_domain`, `clear_black_friday_dismissal`, `black_friday_dates` |
| Surveys | `class-ttp-sdk-surveys.php` | `survey_data_override`, `survey_data_inspect` |
| Licensing | `class-ttp-sdk-licensing.php` | `license_data_editor`, `install_timestamp_editor` |

Feature classes are plain PHP helpers (not `TTP_Addon` implementations): they own item array definitions and callbacks for their area. The orchestrator stays thin. Do not add a shared addon base class until a second large addon needs the same pattern.

## Consequences

- Adding a new internal integration: create `includes/addons/{name}/class-ttp-addon-{name}.php`, add its path to `includes/addons/manifest.php`, append the class (and `ttp_register_items` priority) to `TTP_Addon_Loader::$addons`. Large addons may add feature classes under the same folder (SDK sub-modules pattern above).
- Shared helpers between addons (e.g. option restore) stay duplicated until a second addon needs them; do not introduce a base class prematurely.
- Documentation and architecture diagrams distinguish **platform** from **addons**; product plugins are optional extenders, not the primary source of Testing Items.

## Considered Options

- Keep `TTP_Bundled_Items` and grow it: simple short-term, but poor navigability and merge conflict risk.
- Autoload/glob discovery of addon folders: convenient at scale; deferred until there are many addons (explicit list matches the existing bootstrap `require_once` style).
- Separate repositories per integration: out of scope for an internal QA plugin.
