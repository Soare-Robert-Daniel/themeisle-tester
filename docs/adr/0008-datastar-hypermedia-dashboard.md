# Hypermedia Dashboard Enhancements via Datastar

Date: 2026-05-19

The Dashboard remains fully PHP-rendered per [ADR-0006](0006-server-render-the-dashboard.md). Card actions (Scenario save/reset, Utility run, Danger Utility mutate/restore) now submit through Datastar `@post()` to existing `ttp/v1` REST routes. When the client sends `Datastar-Request: true` or `Accept: text/html`, the server returns morphable HTML fragments (`ttp-flash`, `ttp-card-{id}`, `ttp-tab-indicator-{slug}`) instead of reloading the admin page.

This supplements ADR-0006; it does not reintroduce React, a bundler, or a parallel UI tree. `admin/js/dashboard.js` still owns tab navigation and `data-ttp-list` row cloning; Datastar is loaded as a single vendored module (`admin/js/libs/datastar.min.js`).

## Considered Options

- Full-page POST only — simple, but poor UX on every action.
- React islands (ADR-0001) — rejected in ADR-0006.
- JSON REST + client DOM updates — duplicates PHP rendering.
- **Datastar + server HTML morph** — chosen. PHP stays canonical; REST gains an HTML representation alongside JSON.

## Consequences

- Writable REST routes support content negotiation: JSON for external tooling, HTML for the Dashboard.
- Action logic lives in `TTP_Dashboard_Actions`; HTML fragments in `TTP_Dashboard_Renderer`.
- Security unchanged: `manage_options`, `TTP_Schema_Sanitizer`, `X-WP-Nonce` on REST, `check_admin_referer` on classic POST fallback.
- Classic `method="post"` forms remain for no-JS fallback (full reload).
- SSE / live polling is out of scope for this ADR.
