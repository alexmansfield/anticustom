---
status: accepted
---

# Scale families switch between scale and custom modes with per-device anchors

Spacing and typography (text and headings) drop per-size `override` toggles. Each axis instead carries `mode: scale | custom` and per-device (mobile/desktop) values: in scale mode, base + scale per device; in custom mode, per-size value pairs. There is no per-size variance in scale mode — the axis is either systematic or hand-authored, never mixed.

Both representations persist in the data simultaneously; `mode` is a pointer saying which one the generator reads. The inactive store is invisible in both directions — never surfaced in the editor, never used as a fallback. Switching is nondestructive both ways. Switching `scale → custom` seeds **per key**: any missing custom key is materialized from the current scale computation at switch time; present keys are kept untouched. Clearing a custom value is per-leaf revert — its target is the scale-computed value. An incomplete custom store while `mode: custom` is **invalid data**: `generate.php` errors and the verify layer asserts store completeness, protecting ADR 0017's always-full-emission guarantee for scale families. There is no silent cross-mode repair.

Each px-length size token compiles to a `clamp()` from its two anchors: the middle term is the line through (mobileViewport, mobileValue) and (desktopViewport, desktopValue), computed in PHP at generate time; outer bounds swap when mobile > desktop; equal value anchors emit a static value, not a degenerate clamp. The pair/clamp treatment is scoped to px lengths only — line-height (unitless), letter-spacing (em), and weight (integer) stay single-valued and non-responsive. Viewport anchors are user-editable data: a global `viewport: { mobile: 390, desktop: 1440 }` block in `defaults.json`, exposed in the schema outside the per-axis panels, guarded as distinct (equal anchors zero the slope denominator).

## Considered Options
- **Keep per-size pins inside scale mode (status quo, deepened)** — in the responsive model a pin is a per-size, per-device override on top of a mode switch (override × device × mode); pins are invisible drift ("why didn't `m` move?"), and the panel needs per-row toggle chrome twice over.
- **Reseed custom from scale on every switch** — keeps "no visual change on switch" always true, but silently destroys custom work on every round-trip.
- **Sparse custom (store only edited keys, live fall-through to scale math)** — reintroduces the mixed state through the back door: "custom with one edit" is exactly "scale with one pin".
- **Generator backfills missing custom keys from scale math** — a silent cross-mode read; breaks the symmetry that the inactive store is invisible, and hides data corruption instead of surfacing it.
- **Fixed viewport anchors (dossier item 11 as written)** — no technical need; ADR 0012 makes hand-setting first-class, and comps built at other reference widths (414/1920) legitimately want different anchors. The dossier's real concern — per-axis panel clutter — is solved by placement, not hiding.

## Consequences
A deliberate capability regression, stated as an amendment to ADR 0012: you can no longer ride the scale and pin one size; the seeded mode switch covers that workflow at the cost of live re-derivation after the fork. Today's `typography.text.m` pin in `defaults.json` becomes inexpressible — the defaults migrate by folding it into the scale or shipping the axis in custom mode. `generate.php` learns mode reading, clamp emission, and the incomplete-store error; verify learns store-completeness and distinct-anchor assertions; the schema gains mode fields, per-device values, and the viewport block; seeding-at-switch is an editor-side contract (the schema consumer, host or explorer, performs it). Ticket #34's global-vs-per-level heading-style question compares single plain values, not per-device pairs.
