# Palette-model implementation sequence

The commit-by-commit route that hands off the locked palette + size decisions
(ADRs 0015–0028) for implementation. Resolves wayfinder ticket
[#38](https://github.com/alexmansfield/anticustom/issues/38) — the destination
artifact of the Palette-model map ([#26](https://github.com/alexmansfield/anticustom/issues/26)).

> **This is a rework toward a better system, not a migration that preserves
> current pixels.** Nothing consumes these tokens in production yet, so where the
> old model forced something awkward, the route improves it rather than copying
> it faithfully. The current `defaults.json` is a *seed* for the base spec, not a
> preservation target.

## Decisions banked before sequencing

**Process / ordering (chosen for this route):**

- **Foundational-first.** The generator's emission half is effectively a rewrite,
  so milestone 1 rebuilds the *emission model* (key-identity `--{key}`, open
  ordered sets, death of `enabled`/`baseSize`, `{default, sizes}` shape) as a
  green skeleton; feature semantics layer on after.
- **Base spec baked early; spec *lifecycle* is the route tail.** The base spec's
  guaranteed-key set is needed in M1 (verify + emission lean on it). The
  draft→publish→evolve machinery (ADR 0023) is a tail milestone, still in the
  hand-off, after the substance is green.
- **The two color-math ports are isolated single-purpose commits.** OKLCH swap
  (#29) and the WCAG contrast matrix (#31) each land alone with a
  match-the-research verify checkpoint — the two spots a silent numeric
  regression can hide.
- **The palette break stages dense-first.** Rename + contrast remap + component
  sweep land emitting a *dense* palette (green, no fallbacks needed); *then*
  fallback sweep → sparse emission → permanent verify guard.
- **The 63-file colorway sweep is a big-bang scripted commit** driven by a
  mapping table, not a dual-emission scaffold (the 5-token mapping is ~92%
  uniform; no per-component semantic variation to stage).

**Micro-decisions (resolved with Alex):**

1. **Base spec = migrated current defaults** (a seed to improve on, not preserve).
2. **`enabled` → keep-all-as-members.** Dropping now *reroutes* refs to the family
   alias (no literals survive the sweep), so keep every sized token. Designated
   defaults: `border → m` (2px), `radius → m` (8px, not `full`),
   `shadow → none` (bare alias emits `none`; `xs–xl` stay opt-in members).
3. **Keep the wider vocabulary, classify it.** Spec tokens: spacing `xs–xl`, text
   `s/m/l`. Custom tokens riding the same ramp: spacing `xxs`/`xxl`, text
   `xs`/`xl`. Nothing dropped.
4. **Contrast scale** (ADR 0015 fixed the step names + the middle remap):
   `palette-`-prefixed keys (`--palette-surface`, `--palette-{step}`). Default
   palette wiring off today's neutral ramp — `surface→90`,
   `ultra-soft-contrast→65` *(new)*, `soft-contrast→35`, `hard-contrast→20`
   (absorbs old `contrast`), `ultra-hard-contrast→10` *(new)*, `accent→primary`.
5. **Intents: all four** (`info`/`success`/`warning`/`danger`) + `accent` as a
   peer slot. Generator emits one `[data-intent="x"]{--intent;--intent-on}`
   binding rule per intent; badge references `--intent`/`--intent-on`.
6. **State-tier color-mix v1:** pole by resolved lightness (≤50 → `black`, >50 →
   `white`), `hover 12%` / `active 20%`, `in srgb`. Chroma-floor reversal
   **deferred** to the state-derivation effort (first task there).
7. **Heading scale mode** per ADR 0022: `leading 8px`, letter-spacing
   `calc(-0.022em + 0.35px)`, single `weight 600`; per-level blocks migrate into
   the (inactive) custom store. Default heading look shifts slightly from today —
   accepted as an intended improvement.
8. **Retire the `.anti-interface` apparatus** (not the broad `--anti-*` theming
   layer). Delete the generator block + `anti_interface_css()` + the
   `.anti-interface` class + `__interface` prop plumbing; convert card/intro to
   draw padding/border via **direct** properties. #37's instance-picking
   *replacement* stays deferred.

**Naming calls made under this route** (base-spec authoring, ADR 0027
key-identity — flagged for review, easily changed):

- Scale families emit their namespace *in the key*: `--space-{k}`, `--text-{k}`.
- Headings drop rank-emission (ADR 0027 amends 0024): keys `h1…h6` emit
  `--h1…--h6`, `position` orders the scale math, replacing `--heading-{level}`.
- Ramp stops keep today's named stops: `--{color}-{stopname}`
  (e.g. `--primary-ultra-light`), plus white/black as L100/L0 data stops.

---

## Milestone 1 — Foundational emission skeleton (non-color families + base spec)

Rebuilds the emission model for everything **except** color (color's shape change
is inseparable from the palette remap, so it lives in M3). Ends with a green
system emitting the new *shape* with old-ish *values*.

- **1.0 — Retire the `.anti-interface` apparatus.** Delete the generator block
  (`generate.php:446–452`) + `anti_interface_css()`; remove the `.anti-interface`
  class + `__interface` plumbing (`render.php:253`, `card.php`, `intro.php`,
  `playground.js`); convert card/intro skin CSS to draw padding (and aristotle's
  border) via direct properties; drop the `--anti-border-width/radius/shadow`
  sets. *(Independent prelude; also makes the later `--colorway-soft-contrast`
  ref moot.)*
- **1.1 — Open-set model for scale families.** Schema + `generate.php`:
  spacing/text/headings become `{ default (anchor step), ratio, sizes{…, position} }`;
  drop `baseSize` (the anchor step *is* the origin); key-identity emission
  `--{key}`; headings go symmetric (`--h{n}`, position-ordered).
- **1.2 — Open-set model for pick-one families.** border/radius/shadow become
  `{ default, sizes{} }`; drop `enabled`; the designated default backs an
  always-emitted bare alias (`--border`/`--radius`/`--shadow`).
- **1.3 — Migrate `defaults.json`.** Keep-all members; spec/custom classification;
  anchor + ratio per scale family; `radius→m`, `shadow→none`, `border→m` defaults.
- **1.4 — Bake the base spec + verify guarantee.** The guaranteed-key set
  (spacing `xs–xl`, text `s/m/l`, six headings, pick-one aliases, palette surface)
  as a constant; `verify` asserts spec-token presence.
- **1.5 — Emit bare aliases** for pick-one families **and** `--space`/`--text`
  scale aliases (ADR 0024, chained-fallback targets).
- **1.6 — Size fallback sweep.** Retarget component
  `var(--border-*/radius-*/shadow-*, <literal>)` (and space/text refs) to
  `var(--{family}-{name}, var(--{alias}))`. *Must follow 1.5* or the chained
  fallbacks resolve to nothing.
- **1.7 — Extend the verify fallback check** to size refs (born here; extended to
  palette slots in 3.7).
- **1.8 — Explorer parity pass.** `panel.js` builds the panel from the new schema;
  all components render.

**Verify checkpoint:** `php styles/generate.php` emits valid CSS; `php
components/verify.php` green; explorer renders every component with the new
`--{key}` names and bare-alias fallbacks.

## Milestone 2 — Mode system + derived heading typography

Extends M1's scale families with `mode: scale | custom` (ADR 0018 + the 0012
asymmetry amendment) and lands derived heading styles (ADR 0022). Kept contiguous
with M1 so the whole size/type side is complete before color.

- **2.1 — Schema:** `mode: scale|custom` per axis (spacing/text/headings);
  per-device (mobile/desktop) anchors; global `viewport { mobile, desktop }` block.
- **2.2 — `generate.php`:** clamp emission for px-length size tokens
  (line-height/letter-spacing/weight stay single-valued); scale-vs-custom store
  selection with the inactive store invisible; incomplete-custom-store =
  generate error.
- **2.3 — `verify`:** store-completeness guard + distinct-anchor guard
  (equal anchors emit a static; distinct anchors emit fluid).
- **2.4 — Derived heading typography (ADR 0022).** Emit
  `--{h}-line-height: calc(1em + 8px)`, `--{h}-letter-spacing: calc(-0.022em + 0.35px)`,
  single `weight 600`; per-level blocks seed the custom store; knobs
  `{ leading, letterSpacingSlope, letterSpacingConstant, weight }`.
- **2.5 — Migrate defaults:** fold `text.m` into the scale; seed per-device
  anchors; headings ship scale-mode with per-level values parked in the custom
  store.
- **2.6 — Explorer:** mode switch UI, per-device inputs, derived-knobs typography
  tab (leading as the headline field, *not* labeled as inter-line gap).

**Verify checkpoint:** generate + verify green at **both** device anchors; the
scale↔custom switch round-trips nondestructively in the explorer.

## Milestone 3 — Color: ramp, palette break, state tier

The big color milestone. Ramp shape → OKLCH math → dense palette break → state
tier → sparsify → guard → contrast warnings. Dense-first staging keeps four green
checkpoints.

- **3.1 — Ramp-tier shape (ADR 0025 + 0019).** Flat open **source colors × stop
  scale**, presence-is-membership, key-identity `--{color}-{stop}`, **no** state
  vars at the ramp tier (ADR 0026 drops `generate.php:358–371`); white/black as
  L100/L0 data stops; sparse per-color `pins { stop: hex }` schema. Migrate
  `color.sections`/`hues` → ramp data (enabled source colors only + named stops).
  *(Still HSL math.)*
- **3.2 — OKLCH swap (isolated, #29).** Replace HSL stop generation with OKLCH
  conversion (Ottosson matrices) + CSS Color 4 chroma-bisection gamut mapping;
  output stays 6-digit hex. **Verify: ramp values match `docs/research/oklch-generation.md`
  worked examples, gamut-safe.**
- **3.3 — Palette break, dense (ADRs 0015/0016/0020/0027).** Rename+remap
  `colorway→palette` (`base→surface`, `contrast→hard-contrast`, per ADR 0015);
  contrast scale (surface + 4 steps) + intents + authored `-on` + `accent`; delete
  the auto-generated semantic colorways; data-driven slot enumeration (iterate
  palette keys, `-on/-hover/-active` suffix partition); badge → `data-intent` +
  `--intent`/`--intent-on` binding rules. **Big-bang scripted component sweep**
  (mapping table for the 4 uniform tokens; `data-palette` vs `data-intent` role
  split). Emit **dense** (every step/intent present) — no fallbacks needed yet.
- **3.4 — State tier (ADR 0026).** Palette-tier `color-mix(in srgb, var(--palette-x), {pole} {n}%)`
  for `-hover`/`-active`; pole by resolved lightness (≤50→black, >50→white),
  `12%`/`20%`; retire `colorway_derive_state` + `color_interaction_shifts` band
  math. Chroma-floor reversal deferred.
- **3.5 — Palette fallback sweep.** Retarget the ~21 bare palette `var()` refs to
  chained fallbacks. *Must precede 3.6.*
- **3.6 — Sparse emission.** Flip palette emission to present-keys-only.
- **3.7 — Permanent verify guard.** Fallback-presence check on palette slots
  (extends the 1.7 mechanism).
- **3.8 — WCAG contrast matrix (isolated, #31/ADR 0020).** Port the matrix as a
  `verify` utility behind an `anti_contrast_ratio()` seam; advisory legibility
  warnings only (`surface` vs text-bearing steps; intents/`accent` vs resolved
  `-on` at rest/hover/active). **Verify: matrix outputs match
  `docs/research/php-contrast-matrix.md` / WebAIM values.**
- **3.9 — Explorer parity:** palette editor (contrast steps, intents, `-on`), ramp
  editor (source colors, stops, pin indicators), OKLCH-aware picking.

**Verify checkpoints:** after 3.2 (ramp values vs research); after 3.3 (dense
render green, all components); after 3.6 (sparse + fallback green); after 3.7
(guard rejects a missing fallback).

## Milestone 4 — Semantic heading level (ADR 0028)

Small, orthogonal (element identity, not tokens).

- **4.1 — Schema:** `level` field (`h1`–`h6`, `p`) on `intro`/`card`/`hero`;
  per-component defaults (intro/hero `h2`, card `h3`).
- **4.2 — Templates:** set the post-promotion heading tag; compose with
  eyebrow-promotion; `p` = styled-as-heading, out of the outline.
- **4.3 — Dependency note (not built here):** the layout-tier `heading-order`
  failure state (one `h1`, first-is-`h1`, no descending skip) is **booked for the
  failure-states effort (#2–#4)** — #38 only declares the `level` field it checks.

**Verify checkpoint:** intro/card/hero render the configured tag; defaults produce
a sane outline.

## Milestone 5 — Spec lifecycle (route tail)

The deferred-within-route piece (decision B). Substance (M1–M4) ships first.

- **5.1 — Lifecycle:** draft → publish → evolve; immutable versioning; mechanical
  `extends` stamps.
- **5.2 — Editor:** non-deletable spec-token rows (free labels), custom-token
  rows, lean-site aliasing UI.
- **5.3 — `verify`:** extends-stamp / spec-conformance checks; inbound specs
  checked, never bound.

**Verify checkpoint:** a draft variant publishes, stamps `extends`, and a lean
site aliasing one spec token to another degrades correctly.

---

## Cross-cutting invariants (hold at every checkpoint)

- **Explorer is a same-repo schema consumer** — every milestone keeps
  `explorer/index.php` + `panel.js` rendering.
- **Fallback-before-sparse, always** — both the size (1.5→1.6) and palette
  (3.5→3.6) sweeps land the fallback contract *before* sparse emission, or tokens
  fail silently.
- **Bare-alias-before-fallback-sweep** — 1.5 before 1.6; the aliases must exist
  before refs chain to them.
- **The two math ports (3.2, 3.8) verify against their research docs** before
  anything builds on them.
