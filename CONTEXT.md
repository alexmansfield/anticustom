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

**Richtext field**:
A schema field of type `richtext` whose options (`marks`, `styles`, `multiline`, `blocks`, `links`) declare its editing *capabilities*; the tier (plain → marks → styled spans → blocks/links) is derived from those options, never named in the schema. The same options drive the editor, the toolbar, and the output sanitizer (see ADR 0013).
_Avoid_: WYSIWYG field, HTML field, tier numbers in schemas

**Named style**:
A developer-defined inline text treatment registered globally in `richtext/styles.json` and opted into per field by name. Compiles to exactly one token-backed class (`.anti-rt-<name>`); editors see its label ("Highlight"), never the class.
_Avoid_: span class, format, custom style (per component)

**Mount** (toolbar):
A presentation host for the rich text toolbar implementing `{show, hide, destroy}`. The toolbar core builds buttons from an editor's capabilities; the mount decides where they appear — today, a bubble anchored to the active sidebar field, shown while a selection exists; attached-row and inline-preview mounts are possible later.
_Avoid_: toolbar variant, chrome position
