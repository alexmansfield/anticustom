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

**Pick-one family** vs **Scale family**:
Both are open curated size-token families sharing ADR 0021's `{ default, sizes }` envelope — presence in data is membership (no `enabled` flags), order is an integer `position`, a `default` naming a missing key is invalid data, and freeform names chain-fall back to a bare alias (never a literal) — split now by how values are produced, not by open-vs-structural naming. A **pick-one family** (borders, radius, shadows) supplies one authored value per element property, validated as a single-token CSS length (unitless normalized to `px` at save; no calc/var/negatives), picked by vocabulary name that falls back to `--border` / `--radius` / `--shadow`; radius `full` is an ordinary option. A **scale family** (spacing, text, headings) computes its values along a ramp — one anchor value × `scale^ordinal` in scale mode, per-device authored in custom mode (ADR 0018) — and likewise gains a bare alias (`--space`, `--text`) and chained fallback (ADR 0024 withdrew ADR 0017's scale-family exclusion). Spacing and text emit vocabulary-named variables; **headings** emit rank-numbered variables (`--heading-1` …, biggest first) with free labels, because skins bind them positionally, and their count is a spec choice, not fixed at six. Spec status is orthogonal to membership: a custom token can ride the scale and get a computed value (factory `xxs`/`xxl`). See ADRs 0017, 0021, 0024.
_Avoid_: structural size name, position-math name (as a reason scale names can't be freeform), fixed size name, fixed-six headings, "alias model doesn't apply to scale families"

**Scale mode** vs **Custom mode**:
The two authoring modes of a scale-family axis (`mode: scale | custom`) — systematic (base + scale per device) or hand-authored (per-size mobile/desktop value pairs), never mixed; per-size pins do not exist for sizes (see the ADR 0012 amendment). Both stores persist in data; `mode` points at the one the generator reads; the inactive store is invisible in both directions — never surfaced, never a fallback. Switching to custom seeds missing keys from the current scale computation; an incomplete custom store under `mode: custom` is invalid data, not a fallback case. See ADR 0018.
_Avoid_: override toggle (for sizes), pinned size, mixed/hybrid mode, customized flag

**Anchor step**:
The single `default`-pointed step of a scale family. In scale mode it carries the sole authored value and is at once the exponent origin (`value = anchorValue × scale^ordinal`), the ratio pivot, and the bare-alias fallback target (`--space` = the anchor's value = the project's base spacing unit) — one step, not a separate base and default, because a geometric sequence has no privileged origin. Moving it is value-preserving (the new step seeds from its current computed value; the ramp holds) and only retargets the alias. There is no standalone `baseSize` field. In custom mode the origin and pivot roles go dormant and it is purely the fallback target. See ADR 0024.
_Avoid_: base size (as a separate field), base pointer vs default pointer (as two things), pivot (as a user-facing separate control)

**Pin**:
A per-shade absolute override inside a generated ramp: the stop key's presence in a color's `pins` map (stop name → bare hex) *is* the pin; clearing is deleting the key, falling back to the generated shade. Pins survive source-color changes and regeneration; states (`-hover`/`-active`) derive from the pinned value through the normal band logic. Colors-only — sizes deliberately have no pins (see the ADR 0012 amendment and ADR 0019).
_Avoid_: override toggle (for shades), frozen/custom shade, pinned size

**Intent**:
A hued, meaning-bearing palette token — `accent` plus the statuses (`success`, `warning`, `danger`, `info`) — separate from the contrast scale because contrast alone can't express hue meaning. An intent is a color plus its `-on` foreground — an ordinary authored palette key (the legibility contract collapsed to one step, for elements that bring their own background), never auto-derived: the default palette ships it, other palettes inherit via the cascade, defining the key is the override, and verify warns below 4.5:1 (ADR 0020) — not a sub-palette; more slots (e.g. `success-soft`) are data-edit extensions. Statuses inherit from the default palette via the cascade unless a palette redefines them.
_Avoid_: semantic colorway, status colorway, variant color, fill (as a token name), auto-derived/computed `-on`

**Spec**:
A named, versioned, **immutable** artifact defining the guaranteed token set — token names and their emitted CSS variables, never values — existing independently of any skin. Skins *follow* a spec; a site is **built to spec** when every spec-defined token resolves, **out of spec** otherwise (degrades via chained fallbacks, never breaks). Projects edit a **draft** (seeded blank, from the shipped base spec, or by *starting from* any imported spec) and **publish** to freeze a version; published specs are never edited — evolution drafts from a published version and publishes the next, and retaining every token of the seed earns an **extends** stamp. A project follows at most one spec outbound; installed skins bring inbound specs that are checked, never bound. See ADR 0023.
_Avoid_: contract, standard, mint/minting, guarantee/slot/role (for spec items), schema (for this artifact)

**Spec token** vs **Custom token**:
The two kinds of token a site holds. A **spec token** is defined by the followed spec: its name and variable are the spec's; its value and display **label** are the site's (labels are free and default to the spec name — relabeling touches nothing, ever). It exists in the editor as a non-deletable row while the spec is followed — deleting means going out of spec — and is **missing** when its value doesn't resolve. A lean site satisfies a wide spec by **aliasing** one spec token to another (`xxl` uses `xl`'s value). A **custom token** is project vocabulary beyond the spec: freely created, renamed (its slug is its variable), deleted; skins reference it only via chained fallback. Palette keys and intents (ADRs 0015/0016) are this system's original spec tokens.
_Avoid_: slot, mapped/unmapped token, backing, required token (as a canonical name — "defined by the spec" in copy)

**Leading**:
The authored px increment in scale mode's derived heading line-height, `calc(1em + <leading>)`: the text's own size plus a fixed amount, so small headings stay loose and display sizes tighten automatically — including under a retuned type scale or ADR 0018's fluid anchors. It is a baseline-rhythm offset, **not** the optical gap between lines (ink height is glyph- and font-dependent), and must not be labeled as a gap. Letter-spacing derives through the same affine-in-size shape (`calc(<slope>em + <constant>px)`); weight is a single authored value in scale mode. Per-level style blocks exist only in the custom store. See ADR 0022.
_Avoid_: line gap, smart line height (the ACSS name), per-level line-height (in scale mode), ex-based derivation
