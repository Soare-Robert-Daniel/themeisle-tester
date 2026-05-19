# Internal addons

First-party Testing Items live here. Product plugins can still register items via the public `ttp_register_items` hook; addons are the default path for Themeisle-owned items.

## Layout

```
addons/
  manifest.php          # require_once list — add new addon files here
  sdk/                  # Shared SDK scenarios + Danger Utilities
  wordpress/            # WordPress admin utilities
```

## Adding an addon

1. Create `includes/addons/{name}/class-ttp-addon-{name}.php` implementing `TTP_Addon`.
2. Register items in `register_hooks()` on `ttp_register_items` (call `$registry->register( array( ... ) )`).
3. Add the file to `manifest.php`.
4. Document the addon in `docs/adr/` if it introduces a new pattern.

## SDK hook priorities

`TTP_Addon_SDK` registers modules in this order: Black Friday (10), licensing (20), surveys (30), install (40).

## External products

Neve, Otter, and other product-specific addons can follow the same folder pattern when needed. Keep shared backup/mutation logic in Danger Utilities per ADR-0005.
