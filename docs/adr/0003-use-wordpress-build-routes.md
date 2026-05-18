# Use `@wordpress/build` Routes For The Dashboard

Date: 2026-05-18

> **Status:** Superseded by [ADR-0006](0006-server-render-the-dashboard.md) on 2026-05-18. v1 ships no Dashboard JavaScript bundle; the only client-side code is a single vanilla-JS file (`admin/js/dashboard.js`) enqueued directly. The `@wordpress/build` package may stay in `devDependencies` for future use, but no v1 route consumes it.

Themeisle Tester will use the new `@wordpress/build` route convention for the Dashboard bundle. We chose this over a hand-rolled Vite or `@wordpress/scripts` setup because the Dashboard is a WordPress admin route, the plugin depends on WordPress packages such as `@wordpress/element` and `@wordpress/components`, and the route convention keeps generated assets aligned with WordPress plugin build tooling.

## Considered Options

- `@wordpress/build` routes: best fit for a WordPress admin Dashboard and future-facing WordPress tooling.
- `@wordpress/scripts`: stable and familiar, but less aligned with the route-based structure we want for this plugin.
- Vite: fast and flexible, but requires more custom WordPress externals and asset registration handling.

## Risks

`@wordpress/build` is newer and lower-adoption than `@wordpress/scripts`, so examples and edge-case documentation may be thinner. If the route convention blocks a normal WordPress admin workflow, introduces unstable generated assets, or slows implementation more than it helps, the Dashboard bundle should fall back to `@wordpress/scripts` with a single entry point.
