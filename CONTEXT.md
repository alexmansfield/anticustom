# Anticustom

Anticustom is a token + component system where every design module is a testable contract: it declares its requirements and the ways it can break, so novice editors are warned and AI agents provably cannot ship a broken layout.

## Language

**Failure state**:
A named, machine-checkable predicate a component can fail at edit or publish time given particular content, viewport, or data (e.g. `no-upscale`, `min-contrast`). Declared per component via the `failure_states` array (mirroring `tokens_used`) and defined once in the shared failure-states registry. Distinct from an **input invariant**: a failure state may be a render- or layout-level emergent property that no single field constrains.
_Avoid_: test, rule, assertion, antipattern

**Invariant layers**:
The cost-ordered spectrum a failure state is evaluated at — **input** (cheap, deterministic, over the JSON props), **render** (cheap-ish, over the HTML string), and **layout** (expensive, viewport-dependent, over real browser geometry). Checks run cheapest-first and fail fast.
_Avoid_: validation level, check type

**Advisory** vs **Enforced**:
The two severity tiers of a failure state that must never blur. An **advisory** finding (the `warning` threshold) is dismissible and tuned for human editors; it informs. An **enforced** finding (the `gated` threshold) is blocking, stricter, and tuned for AI agents and the publish gate; it prevents shipping.
_Avoid_: soft/hard error, warning/blocker (as the canonical names)

**Input palette**:
The five field types component/form creators offer end users: `text`, `textarea` (strictly plain, always escaped), `leantext` (inline WYSIWYG: marks + named styles + `<br>`), `richtext` (block-capable WYSIWYG, editor TBD), `html` (raw source, sanitized on output). Richness rides on the type; graduation lives inside each type (see ADR 0014).
_Avoid_: four-type palette, input types (as the canonical name)

**Leantext**:
The inline-only formatted field type: bold/italic marks (implied by the type), named `.anti-style-*` styles (per-field opt-in), optional `<br>` multiline. Flat segment editor (`fields/leantext.js`), buildless and first-party; options resolve per-key against `fields/defaults.json`, then a built-in floor. What ADR 0013 called "richtext".
_Avoid_: richtext (for the inline system), WYSIWYG field, tier numbers in schemas

**Named style**:
A developer-defined inline text treatment registered globally in `fields/styles.json` and opted into per field by name. Compiles to exactly one token-backed class (`.anti-style-<name>`); editors see its label ("Highlight"), never the class.
_Avoid_: span class, format, custom style (per component)

**Mount** (toolbar):
A presentation host for the field toolbar implementing `{show, hide, destroy}`. The toolbar core builds buttons from an editor's capabilities; the mount decides where they appear — today, a bubble anchored to the active sidebar field, shown while a selection exists; attached-row and inline-preview mounts are possible later.
_Avoid_: toolbar variant, chrome position

**Contrast scale**:
The surface-anchored, open set of neutral-emphasis steps a palette wires, named by contrast (soft↔hard) not lightness so component CSS survives a light→dark palette flip. Default steps: `ultra-soft-contrast`, `soft-contrast`, `hard-contrast`, `ultra-hard-contrast`; no default middle step; all step names are editable data keys.
_Avoid_: role list, light/dark steps, fixed slots

**Intent**:
A hued, meaning-bearing palette token — `accent` plus the statuses (`success`, `warning`, `danger`, `info`) — separate from the contrast scale because contrast alone can't express hue meaning. An intent is a color plus its auto-derived, overridable `-on` foreground (the legibility contract collapsed to one step, for elements that bring their own background) — not a sub-palette; more slots (e.g. `success-soft`) are data-edit extensions. Statuses inherit from the default palette via the cascade unless a palette redefines them.
_Avoid_: semantic colorway, status colorway, variant color, fill (as a token name)
