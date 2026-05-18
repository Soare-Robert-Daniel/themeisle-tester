# Server-Render The Dashboard

Date: 2026-05-18

Themeisle Tester v1 ships with a fully PHP-rendered Dashboard. The cards, forms, tables, tabs, and badges are all emitted by `TTP_Admin_Page::render()` and styled by `admin/css/dashboard.css`. The only client-side code is a small vanilla-JS controller (`admin/js/dashboard.js`) that switches the visible tab panel, handles keyboard navigation, and syncs the active tab to the URL hash. This replaces the earlier plan in [ADR-0001](0001-use-react-islands-for-the-dashboard.md) to mount per-card React islands.

The PHP primitives turned out to be enough for the v1 surface area: Scenario forms (toggle + a handful of fields + Save/Reset), Utility cards (description + Run / inline inspector), and Danger Utility row tables (one mutate form per row + Restore). Server-side rendering keeps the page boot fast, removes a bundler from the critical path, makes Product plugins' extension story simpler (PHP schema only — no JavaScript Control registry needed for v1), and avoids carrying `@wordpress/element` + `@wordpress/components` for what amounts to a configuration page.

If the Dashboard later grows controls that genuinely need client-side state — virtualised long lists, conditional fields, live inspector polling, validation that can't be done on submit — React can be reintroduced as targeted islands without re-architecting: the markup already exposes `data-ttp-item-id` / `data-ttp-item-type` anchors, and the REST surface (`ttp/v1`) is in place.

## Considered Options

- Continue with React islands as planned in ADR-0001: more flexible UI, but adds a bundler, runtime React payload, and a parallel rendering path for very little v1 benefit.
- Drop client-rendering entirely: PHP-only forms, no JS — would lose the tab navigation UX and any future interactive controls.
- **Server-render with a minimal vanilla-JS enhancement layer:** chosen. The dashboard is PHP-first; JS only progressively enhances tab switching and keyboard navigation.

## Consequences

- The `ttp_enqueue_controls` action still fires on the Dashboard page so Product plugins can register their own scripts later, but v1 doesn't ship a JavaScript Control registry. The corresponding section of `docs/PROJECT.md` ("Optional custom Controls are registered in Dashboard-only scripts") describes a forward-compatible extension point, not a v1 dependency.
- The `@wordpress/build` route plan in ADR-0003 currently has no consumer. The package can stay in `devDependencies` for future use, but the v1 build does not produce a Dashboard bundle. If we never reintroduce client rendering, the ADR-0003 setup should also be retired in a later ADR.
- The PHP renderer is the canonical layout. Any visual or interaction change to the Dashboard happens in PHP + CSS, not in a parallel React tree.
- The `window.ttpTester.registerControl()` JS API is not exposed in v1. ADR-0002's mention of `window.ttpTester` remains valid as a reserved namespace; we just don't populate it yet.
