# Themeisle Tester Design System

This document is the canonical reference for what "uniform" means in the
Themeisle Tester dashboard. Every UI change should be readable against it.
Decisions that aren't captured here aren't part of the system yet — when you
add a new pattern, write it back into this file.

The decision behind the visual identity lives in
[ADR-0007](adr/0007-control-panel-visual-identity.md). The non-negotiable
architecture (server-render, array schemas, `ttp_` prefix, etc.) lives in
[AGENTS.md](../AGENTS.md) and the rest of `docs/adr/`. This document does not
re-derive those constraints — it lives inside them.

---

## 1. Positioning

> **Internal QA cockpit for controlled product states.**

Every card is an instrument in a cockpit. The operator scans state at a
glance, flips a switch, watches the response. Calm chrome, vivid semantic
accents, disciplined spacing, no decoration. The plugin is internal-only
(see [AGENTS.md](../AGENTS.md)), so the design optimises for *operator
clarity* over marketing polish.

## 2. Identity principles

1. **State is legible at a glance.** Every card answers "what is this?" and
   "what is it currently doing?" in its first 24px of header.
2. **Calm chrome, vivid semantics.** Neutrals carry layout; color carries
   meaning. No decorative color.
3. **Mono earns its place.** A monospaced glyph promises "this is a literal —
   copy it, read it exactly." Never decorative.
4. **Borders, not shadows.** Hairline 1px borders structure the panel.
   Shadows appear only briefly on focus or hover to signal lift.
5. **One spacing scale, no exceptions.** Every margin/padding/gap resolves
   to a token. Ad-hoc 2px/6px patches are bugs.
6. **Danger is marked, not feared.** Operators deal with danger utilities
   daily. Mark them so they're never confused with safe items — but don't
   shout.
7. **Icons are affordance, not garnish.** If removing an icon would lose a
   hint the user can't recover from copy, keep it. Otherwise, drop it.
8. **Color is never the only signal.** Status dot *and* text label.
   Type badge *and* card border treatment for active state. Error border
   *and* error helper text.

## 3. Palette

All values exposed as `--ttp-*` custom properties scoped under
`.ttp-dashboard`. Never reference a raw `#hex` in a rule — always reference
a token.

### Surface

| Token                   | Value     | Role                                              |
|-------------------------|-----------|---------------------------------------------------|
| `--ttp-bg`              | `#F8FAFC` | Page background                                   |
| `--ttp-bg-muted`        | `#F1F5F9` | Nested surface, hovered row                       |
| `--ttp-bg-subtle`       | `#FFFFFF` | Card surface, input background                    |
| `--ttp-border-hair`     | `#E2E8F0` | 1px hairlines                                     |
| `--ttp-border`          | `#CBD5E1` | Stronger dividers, input borders                  |
| `--ttp-border-strong`   | `#94A3B8` | Inputs focused, emphasised dividers               |
| `--ttp-border-subtle`   | `rgba(15,23,42,.04)` | Inner separators inside cards          |

### Ink

| Token                  | Value     | Role                                |
|------------------------|-----------|-------------------------------------|
| `--ttp-text`           | `#0F172A` | Primary                             |
| `--ttp-text-muted`     | `#475569` | Meta, helper, secondary             |
| `--ttp-text-subtle`    | `#64748B` | Labels, captions, disabled          |

### Semantic accents (parametric oklch)

The four accent families share the same lightness and chroma; only **hue**
varies. Every "base" reads at one perceptual weight, every "strong" at one
weight, every "bg" at one weight — true consistency across the spectrum.
Adding a new family later means picking a hue and nothing else.

```
--ttp-l-base:     0.62      base accent
--ttp-l-strong:   0.50      active / pressed
--ttp-l-bg:       0.965     surface tint
--ttp-c-bold:     0.14      foreground chroma
--ttp-c-soft:     0.02      background chroma

--ttp-h-accent:   258       blue       utility / interactive
--ttp-h-success:  180       teal       scenario / ok
--ttp-h-danger:    64       amber      danger marker
--ttp-h-error:     27       red        fault / failed action
```

Each family composes from the parametric tokens:

```
--ttp-accent:        oklch(var(--ttp-l-base)   var(--ttp-c-bold) var(--ttp-h-accent));
--ttp-accent-strong: oklch(var(--ttp-l-strong) var(--ttp-c-bold) var(--ttp-h-accent));
--ttp-accent-bg:     oklch(var(--ttp-l-bg)     var(--ttp-c-soft) var(--ttp-h-accent));
```

…and the same shape for `--ttp-success-*`, `--ttp-danger-*`, `--ttp-error-*`.

> **Danger is amber, not red.** Red is reserved for *errors* (something
> failed), not for marking destructive controls (a tester applies these
> daily, on purpose).

> **Why oklch.** Tailwind's `600`-shade hexes are *not* perceptually
> uniform: amber reads brighter than blue at the same nominal weight, and
> red is far more saturated than teal. Expressing the accents in `oklch()`
> with shared L and C lets us pick "warmth" (hue) without paying for
> inconsistent brightness. Browser support is universal in evergreen
> browsers since 2022–2023; the plugin is internal, so no fallback is
> needed.

### Elevation

| Token                | Value                                            | Role                          |
|----------------------|--------------------------------------------------|-------------------------------|
| `--ttp-shadow-sm`    | `0 1px 2px rgba(15,23,42,.04)`                   | Card hover                    |
| `--ttp-shadow`       | `0 4px 12px rgba(15,23,42,.06)`                  | Tooltip, raised popover       |
| `--ttp-focus-ring`   | `0 0 0 3px rgba(37,99,235,.18)`                  | Keyboard focus halo           |

## 4. Typography

System stacks. Zero font load. The identity carries via layout, color, and
density — not through a webfont signature.

### Stacks

```
--ttp-font-sans  -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
                 Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue",
                 sans-serif
--ttp-font-mono  ui-monospace, SFMono-Regular, "SF Mono", Menlo,
                 Consolas, "Liberation Mono", monospace
```

### Scale

| Token              | Size / line-height | Role                                       |
|--------------------|--------------------|--------------------------------------------|
| `--ttp-text-xs`    | `11px / 1.4`       | Tracked uppercase labels, captions         |
| `--ttp-text-sm`    | `12px / 1.45`      | Meta, helper text, table cells             |
| `--ttp-text-base`  | `13px / 1.5`       | Body                                       |
| `--ttp-text-md`    | `14px / 1.5`       | Card titles                                |
| `--ttp-text-lg`    | `16px / 1.4`       | Section / group headings                   |
| `--ttp-text-xl`    | `20px / 1.3`       | Page title                                 |

### Tracking & weight

| Token                     | Value     | Role                                       |
|---------------------------|-----------|--------------------------------------------|
| `--ttp-tracking-wider`    | `0.06em`  | `.ttp-label` uppercase labels              |
| `--ttp-weight-medium`     | `500`     | Buttons, tab labels                        |
| `--ttp-weight-semibold`   | `600`     | Titles, active tab, primary buttons        |

### Mono usage rules

Use `<code class="ttp-mono">` (or the `.ttp-mono` utility on any element)
**only** for:

- Option keys (`license_data`, `themeisle_sdk_install_*`)
- License keys
- ISO 8601 timestamps (`2026-05-18T13:00:00Z`)
- Item IDs (`scenario_force_404`)
- File paths (`/wp-content/plugins/...`)

Never for headings, labels, body copy, or "to look technical."

## 5. Geometry & spacing

### Scale (unchanged from v1)

```
--ttp-space-1   4px
--ttp-space-2   8px
--ttp-space-3   12px
--ttp-space-4   16px
--ttp-space-5   24px
--ttp-space-6   32px
--ttp-space-7   48px
```

### Radii

| Token             | Value | Role                                                |
|-------------------|-------|-----------------------------------------------------|
| `--ttp-radius-sm` | `6px` | Inputs, small buttons, badges                       |
| `--ttp-radius`    | `8px` | Cards (modules), flash, note                        |
| `--ttp-radius-lg` | `10px`| Panel-level containers (rare)                       |

## 6. States (universal)

Every interactive primitive — button, card, input, tab, toggle, table row —
shares this state model.

| State    | Visual                                                                       |
|----------|------------------------------------------------------------------------------|
| Default  | Hairline border, base ink, no shadow                                         |
| Hover    | Border darkens to `--ttp-border-strong`; cards add `--ttp-shadow-sm`         |
| Focus    | `--ttp-focus-ring` (3px rgba accent halo) — never removed                    |
| Active   | Background `--ttp-bg-muted`, ink unchanged                                   |
| Selected | Card border shifts to `--ttp-success`; badge gains active dot + label        |
| Disabled | `opacity: .55`, `cursor: not-allowed`, all hover effects suppressed          |
| Busy     | `aria-busy="true"` on the card during Datastar fetch; `.ttp-card--busy` dims the card (`opacity: .72`, no pointer events) |
| Error    | `--ttp-error` border on field; helper line replaced by error message         |

## 7. Motion

```
--ttp-duration-fast   120ms    micro: hover, button press
--ttp-duration        180ms    standard: toggle, tab switch, focus ring
--ttp-duration-slow   320ms    panel expand, modal open (future)
--ttp-ease            cubic-bezier(.4, 0, .2, 1)   default
--ttp-ease-out        cubic-bezier(0, 0, .2, 1)    enter
--ttp-ease-in         cubic-bezier(.4, 0, 1, 1)    exit
```

Rules:

- Animate `transform`, `opacity`, `box-shadow`, `border-color`.
- Never `width` / `height` / `top` directly.
- `prefers-reduced-motion: reduce` zeros all durations.
- No idle / decorative animations.

## 8. Iconography

- **Library**: WordPress Dashicons (already loaded by WP admin; zero cost).
  No external icon font, no SVG sprites.
- **When**: affordance only — info "i" on labels needing explanation, "+"
  on add buttons, "×" on remove buttons, chevrons inside selects. Never
  decorative.
- **Sizes**: 16px default, 20px in page header.
- **Color**: inherit `currentColor`.
- **Spacing**: 8px gap from adjacent text.

## 9. Z-index scale

```
--ttp-z-base       1
--ttp-z-sticky     10     sticky tab strip (future)
--ttp-z-dropdown   100
--ttp-z-tooltip    1000
--ttp-z-modal      2000
--ttp-z-flash      3000   action-result flash sits above everything
```

## 10. Page anatomy

```
┌─ wp-admin chrome ───────────────────────────────────────────┐
│                                                             │
│  Header strip       Page title + lede                       │
│                     ──────────────────────────────────────  │
│                     "Themeisle Tester"                      │
│                     "Create, inspect, and reset…"           │
│                                                             │
│  Tabs (underline)   Scenarios   Utilities   Danger          │
│                     ─────────                               │
│                                                             │
│  Panel              GROUP HEADING (tracked uppercase 11px)  │
│                     ┌─Card─┐ ┌─Card─┐ ┌─Card─┐              │
│                     └──────┘ └──────┘ └──────┘              │
│                                                             │
│                     GROUP HEADING                           │
│                     ┌─Card─┐ ┌─Card─┐                       │
│                     └──────┘ └──────┘                       │
│                                                             │
│  Flash              Pinned top, dismissable, semantic rail  │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Spacing

| Between                                | Token             | Value |
|----------------------------------------|-------------------|-------|
| Header → tabs                          | `--ttp-space-4`   | 16px  |
| Tabs → first group heading             | `--ttp-space-5`   | 24px  |
| Group heading → card grid              | `--ttp-space-3`   | 12px  |
| Between groups                         | `--ttp-space-6`   | 32px  |
| Card grid gap                          | `--ttp-space-4`   | 16px  |

### Grid

`grid-template-columns: repeat(auto-fill, minmax(320px, 1fr))`. Collapses to
single column below 782px (WP admin's mobile threshold).

The grid uses **`grid-auto-flow: row dense`** so cards of different widths
pack tightly: when a `wide` or `full` card can't fit at the cursor and
would normally leave a gap, the browser's dense-packing algorithm looks
back through the grid and fits later, smaller cards into earlier holes.
The result: no empty cells between cards in a group, even when widths are
mixed.

> **Consequence — visual order may deviate from registration order.** A
> `normal` card declared *after* a `wide` card can render *before* it if
> the wide card created an earlier hole. Items inside a group are still
> emitted in registration order in the DOM (assistive tech reads them in
> order); only the visual placement is rearranged. If a specific visual
> ordering matters for a feature, split it into its own `group` so the
> ordering is bounded by a group heading.

### Grid sizing — per-item `width`

Cards default to **one column** (320px floor, auto-flow). An item can opt
into more horizontal room by declaring `width` in its schema:

```php
$registry->register( array(
    'id'    => 'install_from_zip',
    'type'  => 'utility',
    'width' => 'wide',   // 'normal' (default) | 'wide' | 'full'
    /* ... */
) );
```

| Value     | Grid effect                  | When to reach for it                                                        |
|-----------|------------------------------|-----------------------------------------------------------------------------|
| `normal`  | `1` column (320px floor)     | Default. Almost everything.                                                 |
| `wide`    | `span 2` columns             | Form with multiple side-by-side fields, or a list field with several rows.  |
| `full`    | `1 / -1` (entire row)        | Inspect tables, license editors, anything that paints a data table.         |

Implementation:

- The schema is normalised in `TTP_Item_Registry`: invalid values fall back
  to the type default. `danger_utility` items default to `full` for
  backward compatibility; everything else defaults to `normal`.
- The admin renderer adds `ttp-card--width-{value}` to the card; CSS
  applies `grid-column` accordingly.
- The single-column responsive breakpoint at 782px overrides everything
  back to one column, regardless of `width`.

> **Default to `normal`.** Wide and full cards consume a row in ways
> narrower cards don't; overusing them turns the panel back into a stack.
> Reach for `wide` only when the card's content can't usefully sit at
> 320px, and `full` only for data tables.

## 11. Component anatomy

### Card

```
┌──────────────────────────────────────────────────────────────┐
│  Title (14px semibold sentence-case)    [ SCENARIO ] product │
│                                                              │
│  Body — fields, toggle, table, or inspect output             │
│                                                              │
│  ─── separator ─────────────────────────────────             │
│  [ Primary action ]   [ Secondary ]   [ Ghost ]              │
└──────────────────────────────────────────────────────────────┘
```

- Radius: `--ttp-radius` (8px).
- Border: 1px hairline `--ttp-border-hair`.
- Padding: `--ttp-space-4` (16px) on all sides.
- Hover: border → `--ttp-border`, `--ttp-shadow-sm` lift.
- Active scenario: border shifts to `--ttp-success`; a subtle teal-tinted
  gradient sits behind the first ~30% of the card surface. The "Active"
  badge with status dot in the header carries the same signal in text.
- Type signaling lives in the badge (`SCENARIO` / `UTILITY` /
  `DANGER UTILITY`) — no card-level color treatment for type. We tried a
  colored left rail and removed it: stacking many cards with rails looked
  noisy, and the badge already communicates type unambiguously.

### Badge

```
┌─ 6px radius ────┐
│ SCENARIO        │   tracked uppercase 11px small caps
└─────────────────┘
```

Variants: `--scenario` (success), `--utility` (accent), `--danger_utility`
(danger), `--active` (success + status dot).

### Tabs (underline)

```
Scenarios    Utilities    Danger
─────────                       
────────────────────────────────  ← hairline divider
active = accent text + 2px accent underline
```

- Container: flex row, no background, no border except a hairline
  `--ttp-border-hair` bottom divider; tabs overlap it via `margin-bottom: -1px`.
- Inactive tab: text-only, `--ttp-text-muted`, weight-medium.
- Hover: ink → `--ttp-text`, faint `--ttp-border` underline preview.
- Active tab: `--ttp-accent-strong` text + 2px `--ttp-accent` underline,
  weight-semibold.
- Per-tab counts have been removed; an active-scenario dot
  (`.ttp-tab__indicator`, `--ttp-success` on `--ttp-success-bg`) appears
  next to the label when a category has any active scenarios.
- Focus ring: `--ttp-focus-ring` with `--ttp-radius-sm` corners.
- Keyboard contract preserved from `admin/js/dashboard.js`
  (`role=tab`, `aria-selected`, arrow keys, Home/End).

### Buttons

| Variant     | Surface              | Border               | Text                  | Use                       |
|-------------|----------------------|----------------------|-----------------------|---------------------------|
| Primary     | `--ttp-accent`       | `--ttp-accent`       | white                 | Apply, Save, Run          |
| Secondary   | `--ttp-bg-subtle`    | `--ttp-border`       | `--ttp-text`          | Reset, Cancel             |
| Danger      | transparent          | `--ttp-danger`       | `--ttp-danger`        | Apply (in danger context) |
| Ghost       | transparent          | none                 | `--ttp-text-muted`    | Reset, dismiss            |

Heights: 28px (sm, default), 36px (md). Radius: `--ttp-radius-sm` (6px).

### Form fields

Anatomy:

```
LABEL  (.ttp-label, 11px tracked uppercase, muted)
[ input or select control ]
helper text (12px muted) — or error text (12px error-strong) on error
```

- Input height: 32px (default), 36px (medium).
- Radius: `--ttp-radius-sm`.
- Border: 1px hairline.
- Focus: `--ttp-focus-ring` + border → `--ttp-accent`.
- Error: border → `--ttp-error`; helper line replaced by error message.

### Toggle

- Track: pill, `--ttp-border-strong` off, `--ttp-success` on.
- Knob: `--ttp-bg-subtle`, drop shadow.
- Focus: `--ttp-focus-ring`.

### List field (url_list)

- Repeatable rows, `--ttp-space-2` (8px) gap.
- Remove button: ghost variant with "×" Dashicon.
- Add button: ghost variant with "+" Dashicon; dashed border on rest,
  solid on hover.

### Group heading

- 11px tracked uppercase in `--ttp-text-muted`.
- 12px bottom margin.
- Trailing horizontal hairline for visual rhythm.

### Page header strip

- Full width inside `.wrap`.
- Title: 20px sentence case.
- Stat chips on the right, separated by `--ttp-space-2`.
- Hairline border-bottom (`--ttp-border-hair`).
- Padding-bottom: `--ttp-space-4`.

### Stat chip

```
┌─ 999px pill ──────────────────┐
│ 12  ITEMS                     │   mono count + tracked uppercase label
└───────────────────────────────┘
```

- Surface: `--ttp-bg-subtle`.
- Border: 1px hairline.
- Count: mono, `--ttp-text`, `--ttp-text-base`.
- Label: tracked uppercase 11px, `--ttp-text-muted`.
- `--active` variant: count → `--ttp-success-strong`.

### Flash

```
┌─ toast ─────────────────────┐
│ Message line (semibold)   × │
└─────────────────────────────┘
```

- Hairline border-radius `--ttp-radius`.
- Fixed bottom-right toast region above the Dashboard chrome.
- Success toasts auto-hide; errors stay until dismissed.
- Keep flash copy short. Detailed change context belongs in Recent activity.
- No full-bleed background.

### Note

Same pattern as flash, used inline inside a card for unavailable reasons
and warnings. Variants: default (warning amber), `--error` (red).

### Data table

- Hairline rows; no zebra stripes.
- Header cells: `--ttp-bg-muted`, 10px tracked uppercase, `--ttp-text-muted`.
- Body cells: 12px.
- `option` / `key` / `timestamp` columns: mono.
- Row hover: background `--ttp-bg-muted`.

### Recent activity

- Rendered immediately after `.ttp-panels`.
- Shows the last 10 Dashboard actions stored in `ttp_activity_log`.
- Columns: Time, Testing Item, Action, Result, Details.
- Result uses text badges (`Success` / `Error`) with semantic color, never color
  alone.
- Details are compact mono tokens for target IDs and changed field values.
- On small screens, the table scrolls horizontally instead of collapsing into
  cards.

### Tooltip

- Surface: `--ttp-text` (dark slate) — tokenised replacement for the
  previous `#1d2327` hardcode.
- Text: `--ttp-bg-subtle`.
- 8px radius, hairline shadow.
- Arrow inherits the surface token.

### Empty state

- Centered, `--ttp-text-muted`, single line.
- No illustration.
- Copy: "No scenarios registered." not "Nothing here yet!"

### Danger confirmation

Currently `window.confirm()`. The microcopy rule below says danger prompts
should name the target literal in mono; `confirm()` can't render `<code>`.
Replacing it with a styled modal is recorded as future work (§17).

## 12. Microcopy & writing rules

- **Buttons are imperative verbs**: Apply, Run, Reset, Restore, Save, Cancel.
  Never "OK" / "Submit" / "Go".
- **Sentence case for titles and labels**: "Force 404 on pricing", not
  "Force 404 On Pricing".
- **Mono-wrap any literal a tester could copy**: option key, license key,
  ISO timestamp, file path, item ID.
- **Danger confirmations** state what changes, name the target literal, end
  with the destructive verb on its own line:

  > This will overwrite the option `license_data`. Apply?

- **Timestamps**: ISO 8601 in mono. Relative form ("3 days ago") only as a
  supplement, never the sole representation.
- **Errors are factual, not apologetic**: "Backup not found for
  `license_data`." not "Oops! Something went wrong…"
- **No emoji, no exclamation marks** in operator-facing copy.
- **Empty states** describe what would be there: "No scenarios registered.",
  not "Nothing here yet!"

## 13. Accessibility targets

- **Contrast**: every text/background pair in the palette meets WCAG AA
  (4.5:1 for body, 3:1 for large text and UI components). Verified against
  the slate values.
- **Focus**: `--ttp-focus-ring` always visible on keyboard focus. Never set
  `outline: none` without a replacement that's at least as visible.
- **Keyboard**: tabs navigable with arrows + Home/End (see
  `admin/js/dashboard.js`). Add/remove list-field buttons reachable in tab
  order.
- **Screen readers**: every status dot is decorative (`aria-hidden="true"`);
  the paired text label is the truth.
- **Reduced motion**: `prefers-reduced-motion: reduce` zeros durations.
- **Color-not-only**: danger items carry the amber `DANGER UTILITY` type
  badge text alongside the amber chip color; active scenarios carry a teal
  border + dot *and* the "Active" badge text.

## 14. Anti-patterns

- ❌ Inline `style="…"` attributes in PHP. Class-based only.
- ❌ Raw `#hex` color values in CSS rules. Must reference a `--ttp-*` token.
- ❌ Magic spacing (2px, 6px, 10px). Must resolve to the scale.
- ❌ Decorative icons.
- ❌ Mono for non-literal text (headings, labels, body copy).
- ❌ Amber background fill on danger cards (marks; doesn't scream).
- ❌ Removing the focus ring without a louder replacement.
- ❌ `!important` to override the system. Fix the cascade instead.
- ❌ Adding a new top-level `--ttp-*` token without first checking whether
  an existing one fits.
- ❌ A new component class outside the BEM convention
  (`block__element--modifier`).
- ❌ Emoji or exclamation marks in operator-facing copy.

## 15. Contribution checklist

Before merging any UI change:

- [ ] Every color references a `--ttp-*` token (grep for `#` and `rgb` in
      the diff).
- [ ] Every spacing references a scale token (grep for raw `px` in
      margin/padding/gap in the diff).
- [ ] Focus ring visible on keyboard navigation through every new
      interactive element.
- [ ] Disabled state styled, not just `disabled` attribute.
- [ ] `prefers-reduced-motion: reduce` respected.
- [ ] Button labels are imperative verbs.
- [ ] Technical literals wrapped in `<code class="ttp-mono">`.
- [ ] No decorative icons.
- [ ] Linters pass (`composer run lint`, `composer run phpstan`,
      `npm run lint`).
- [ ] Page renders cleanly at 782px and below.

## 16. Token policy

- Token names are part of the public contract within the plugin.
  **Don't rename — extend.**
- **Adding** a new token: justify why an existing one doesn't fit; document
  it here.
- **Changing** a token value: a non-breaking visual change — proceed.
- **Removing** a token: requires a deprecation pass — grep usages, migrate,
  then delete.

## 17. Future work

Recorded so they can be picked up later without re-deciding direction:

1. **Live style guide admin sub-page** at
   `?page=ttp-tester&section=style-guide`, visible when `WP_DEBUG` is on.
   Renders every primitive in every state.
2. **Dark mode** via `@media (prefers-color-scheme: dark)` override of the
   surface and ink token blocks. Semantic accents stay.
3. **Styled confirmation modal** replacing `window.confirm()` so danger
   prompts can render `<code>` literals.
4. **PHP render helpers** (`ttp_render_card`, `ttp_render_badge`, …) — the
   *structural* uniformity pass that complements this visual one. Wait for
   demonstrated repetition (see AGENTS.md "Modest internal code").
