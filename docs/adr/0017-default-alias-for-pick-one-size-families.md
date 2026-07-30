---
status: accepted
---

# Pick-one size families get a default alias; scale families stay positional

Size families split by how an element consumes them. A **pick-one family** — borders, radius, shadows — supplies exactly one value per element property (one border width, one corner radius, one shadow), so it gets a designated default and a bare alias that always exists: `--border`, `--radius`, `--shadow` point at the default option's value. Named options are freeform project vocabulary (`--border-hairline` is as legal as `--border-s`), emitted per enabled option; the names are editor-facing data keys, not a CSS contract. Component CSS binds to the bare alias for "the project's X"; a named reference is progressive enhancement and must chain-fall back to the family alias — `var(--border-s, var(--border))`, never a hardcoded value like `var(--border-s, 1px)`. This extends ADR 0015's sparse-emission + mandatory-fallback contract from palette slots to sizes, with the refinement that the fallback target is the alias, not a literal.

A **scale family** — spacing, type sizes — is consumed several steps at once (gap, padding, and section spacing are all deliberately different "spacing"), so no single default answers "which one?". Its names are structural, not vocabulary: spacing keys carry `position` values computed from base × ratio, and renaming them would break the math and the ramp UI. Scale families are excluded entirely — no alias, no freeform names, no chained fallbacks; their guarantee mechanism is that the ramp is always fully emitted. `--radius-full` (9999px pill/circle) is a shape choice, not a size, and stays a fixed guaranteed name outside the options set (already `fixed: true` in the schema).

The editor follows the option count with no CSS branching: one enabled option renders a single field labeled by family ("Border"); multiple render the named options with the default marked.

## Considered Options
- **Uniform `s`/`m`/`l` always (status quo)** — single-value projects awkwardly expose a size picker for one value, and today's hardcoded `px` fallbacks rot silently: only `m` is enabled in defaults, so every `--border-s` ref in component styles resolves to its literal fallback, unseen.
- **Bare name when single, sized names when multiple** — the CSS surface changes shape with option count, so every style must know how many options a project defines; cross-project styles break.
- **Extend the alias model to spacing too** — no meaningful "the spacing" exists, and freeform names collide with position math.
- **Keep hardcoded fallbacks with the new alias** — preserves the silent-rot failure mode the alias exists to eliminate.

## Consequences
`generate.php` reads the (currently unread) `defaultSize` and emits the bare alias; radius and shadows gain equivalent default designations. Component styles get a fallback sweep: every `var(--border-*/--radius-*/--shadow-*, <literal>)` retargets to the family alias — low-risk but visually observable once token values diverge from the old literals, so it warrants an explorer pass. The verify fallback check extends from palette slots to size references. Shared styles referencing project-specific option names degrade to the default elsewhere — the correct degradation, by contract.

## Amendment (2026-07-29): radius `full` is an ordinary option

[ADR 0021](0021-open-curated-size-sets.md) drops the fixed-name carve-out: `full` is an editable, renamable, deletable member of the radius options set like any other — a guaranteed-but-invisible constant reads in the editor as a size that doesn't exist, and users would recreate it. `fixed: true` leaves the schema; references follow the standard chained fallback (`var(--radius-full, var(--radius))`). Everything else here stands, including the single-field editor collapse.
