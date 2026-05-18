# Use `ttp` For Public APIs

Date: 2026-05-18

Themeisle Tester will use the `ttp` prefix for public PHP APIs, WordPress hooks, REST namespace, CSS classes, data attributes, and generated asset handles. This matches the existing PHPCS global-prefix rule and avoids mixing `themeisle_tester_*`, `tipt_*`, and `ttp_*` across the codebase. The REST namespace uses `ttp/v1` for consistency with the hook/API surface even though `themeisle-tester/v1` would be more self-documenting. The JavaScript extension global is the readable exception: `window.ttpTester`, which still keeps the `ttp` prefix while being clearer in browser tooling.

## Considered Options

- `themeisle_tester_*`: more descriptive, but inconsistent with the configured PHPCS prefix and longer for extension hooks.
- `ttp_*`: shorter, consistent with standards, and stable for product plugins to depend on.
- Supporting both: more forgiving, but creates unnecessary public API surface before v1 has shipped.
