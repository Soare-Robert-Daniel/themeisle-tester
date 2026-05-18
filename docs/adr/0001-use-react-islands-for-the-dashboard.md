# Use React Islands For The Dashboard

Date: 2026-05-18

> **Status:** Superseded by [ADR-0006](0006-server-render-the-dashboard.md) on 2026-05-18. Themeisle Tester v1 ships with a fully PHP-rendered Dashboard and vanilla-JS tab controller; React islands are no longer planned.

Themeisle Tester will render its Dashboard shell with PHP and mount independent React islands for each Scenario or Utility card. We chose this instead of a full React SPA because the plugin is primarily a WordPress extension surface: PHP hooks register testing items, WordPress owns permissions and initial page rendering, and a broken custom Control should not take down the whole Dashboard. React remains responsible for interactive fields and card behavior, while PHP remains the source of truth for registration, boot data, and access control.

## Considered Options

- Full React SPA: simpler frontend ownership, but weaker WordPress extensibility and harder product-plugin UI injection.
- PHP-only UI: easiest WordPress integration, but too limited for rich controls, scanners, conditional fields, and responsive card interactions.
- React islands: preserves WordPress-native extension points while allowing modern interactive controls where they are useful.
