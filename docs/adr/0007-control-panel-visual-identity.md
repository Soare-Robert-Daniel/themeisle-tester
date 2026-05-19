# Internal QA Cockpit Visual Identity

Date: 2026-05-18

Themeisle Tester adopts an **"Internal QA Cockpit"** visual identity for the
Dashboard: cool blue-slate chrome, system sans + system mono fonts, 8px
module radii, hairline borders over shadows, segmented-control tabs,
parametric **oklch** for the semantic accent families, monospace reserved
for technical literals, and amber (not red) for danger utilities. Every
card represents a controllable lever in a cockpit-style instrument panel;
the design optimises for *operator clarity* over marketing polish, since
the plugin is internal and never customer-facing.

The plugin v1 (per [ADR-0006](0006-server-render-the-dashboard.md)) shipped
with a partial design system: 25 `--ttp-*` tokens, BEM-like class names,
scoped CSS under `.ttp-dashboard`, no inline styles. What was missing was
a *named* identity to align future work to, a typography scale, motion /
z-index / state tokens, and a written reference (`docs/design-system.md`).
This ADR records the identity decision; the spec doc carries the full
detail.

The identity is grounded in three product constraints. First, the Dashboard
lives inside WP admin chrome, so it harmonises with the platform but
deliberately reads as "you are in the testing surface" via slate tokens
(distinct from WP admin's gray). Second, the three Testing Item types
(Scenario, Utility, Danger Utility per
[ADR-0004](0004-use-array-schemas-for-testing-items.md)) map onto the
semantic color system — teal=scenario/ok, blue=utility/info, amber=danger,
red=error. Third, [ADR-0006](0006-server-render-the-dashboard.md) locks in
server-render + vanilla JS, so the identity has to be expressible purely
in CSS and plain HTML — no framework, no bundler, no webfont.

## Considered Options

- **Refined WP-native** (stay close to WordPress admin conventions, just
  polish what's there): lowest risk, highest familiarity. But the plugin
  is internal and benefits from a distinct visual surface signalling "you
  are in the testing zone" — pure WP-native gives no identity to align to,
  so "uniformity" stays aspirational.
- **Themeisle warm** (warm cream + ink, brand teal, serif headings,
  rounder modules): connects to the parent brand. Rejected because the
  plugin is never customer-facing, so full brand expression is wasted, and
  warm chrome competes with WP admin's cool grays.
- **Dark studio** (dark chrome by default, neon semantic accents): bold;
  signals "different surface" most aggressively. Rejected for v1 because
  every component would need to be validated on dark and the break from WP
  admin's light chrome is jarring when navigating in and out of the page.
  Recorded as future work — palette is structured to allow a
  `prefers-color-scheme: dark` override later.
- **Internal QA Cockpit** (chosen): cool blue-slate chrome, system fonts,
  8px modules, hairline borders, segmented tabs, amber for danger.
  Distinct from generic WP admin without abandoning the platform; semantic
  palette maps cleanly to the three Testing Item types; zero font load;
  expressible in CSS + plain HTML per ADR-0006.

## Consequences

- `admin/css/dashboard.css` carries the full identity. Token names
  introduced by v1 are preserved (no renames); palette values are updated
  to the slate family (neutrals in sRGB hex), and the four semantic accent
  families (accent / success / danger / error) are expressed parametrically
  via `oklch()`: shared `--ttp-l-base / -strong / -bg` and `--ttp-c-bold /
  -soft`, with only `--ttp-h-{accent,success,danger,error}` differing.
  This gives perceptual parity across the spectrum — every "base" reads at
  one weight, every "strong" at one weight, every "bg" at one weight. New
  tokens are added for typography (`--ttp-text-xs/sm/base/md/lg/xl` +
  tracking + weights), motion (`--ttp-duration-*`, `--ttp-ease-*`),
  z-index (`--ttp-z-*`), and the previously hardcoded focus-ring rgba /
  tooltip color / warning colors / knob whites. Browser support for
  `oklch()` is universal in evergreen browsers since 2022–2023; the plugin
  is internal so no sRGB fallback is provided.
- Three new utility classes are introduced: `.ttp-label` (tracked
  uppercase), `.ttp-mono` (monospaced literal), `.ttp-dot` (status dot —
  generalised from the existing `.ttp-badge--active::before`).
- `admin/class-ttp-admin-page.php` gains minimal markup changes:
  technical literals are wrapped in `<code class="ttp-mono">`. No new
  helper methods, no structural refactor — that remains "modest internal
  code" per [AGENTS.md](../../AGENTS.md). When the codebase shows
  demonstrated repetition of card / badge / button markup, a follow-up
  ADR may justify PHP render helpers.
- `docs/design-system.md` becomes the canonical reference. Every UI change
  is read against it; new patterns are written back into it. The pointer
  lines in `CLAUDE.md` and `AGENTS.md` make it discoverable.
- The JS contract for tabs (`role=tab`, `aria-selected`, `aria-controls`,
  arrow / Home / End keyboard nav per `admin/js/dashboard.js`) is preserved
  unchanged. The shift from underlined tabs to a segmented control is
  pure CSS.
- Danger Utilities are marked by the amber `DANGER UTILITY` type badge in
  the card header; the card body stays neutral. We tried adding a 3px
  amber/teal/blue left rail per item type and removed it after visual
  review — stacking many cards together made the rails read as noise, and
  the type badge already communicates the item type unambiguously. The
  active-scenario state retains its border-color shift to
  `--ttp-success` + subtle teal-tinted background gradient, paired with the
  "Active" badge + status dot in the header for color-not-only signaling.
- Future work captured in the spec doc (§17): live style guide sub-page,
  dark mode override, styled confirmation modal, PHP render helpers.
