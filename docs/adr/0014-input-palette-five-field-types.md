---
status: accepted
thread: 01kym2refkh58mwvaak3tfv4c5
amends: 0013
---

# The input palette is five discrete field types

Component (and future form) fields draw from a five-type input palette: `text`, `textarea`, `leantext`, `richtext`, `html`. The plain pair never carries markup — always fully escaped, safe in any attribute or URL context. The formatted trio is markup-bearing, sanitized on output against per-type allowlists. Richness is carried by the *type*; graduation lives *inside* each type as its options, never across types. This amends ADR 0013: what it called `richtext` is renamed **leantext**, freeing `richtext` for a genuinely block-capable WYSIWYG.

## Decisions

- **Five discrete types, three real axes.** Line-count (`text` vs `textarea`), markup-richness (plain → inline → blocks), and authoring modality (WYSIWYG vs raw source) are distinct axes; the five types are their sensible corners, not points on one dial. A smooth dial fails at three places: text↔textarea is not a richness step; plain↔leantext is a binary security boundary; richtext↔html inverts modality (most powerful, least assisted).
- **The leantext/richtext boundary sits on the flat↔tree editor seam.** Leantext is the flat segment engine (inline marks, named styles, `<br>`); richtext requires a document-tree editor (blocks, Enter-splits). The split quarantines the unresolved third-party block-editor dependency to richtext, keeping leantext buildless and first-party.
- **Leantext schema shape: the type implies marks.** `"type": "leantext"` alone means bold/italic, single line. Options object `leantext: { marks?, styles?, multiline? }` refines it: `marks` omitted → both (present → exactly that list); `styles` omitted → none (curated per-field opt-in from the registry, per ADR 0013); `multiline` omitted → false.
- **Per-key project defaults in `fields/defaults.json`.** Resolution: field options → project defaults → built-in floor, each key independently (the CSS/tsconfig convention; arrays replace, never union — explicit empty narrows). The file is optional; resolution lives solely in the PHP features function and the explorer consumes resolved features.
- **`richtext/` folds into `fields/`, the palette system's home.** Mirroring `styles/` as the token system's home: `fields/` holds `defaults.json`, `sanitize.php`, `styles.json`, `leantext.js` (was engine.js), `toolbar.js`, `editor.css`, `verify.php`. Renames: `anti_rt_*` → `anti_field_*`, `.anti-rt-*` → `.anti-style-*`, `anti_rich()` → `anti_field_html()`, `window.AntiRT` → `window.AntiLeantext`. Hard rename, no aliases — "richtext" means exactly one thing: the future block-capable type.
- **Migration is behavior-preserving.** `intro.eyebrow` (empty options = plain) becomes `text`; the other four richtext fields become `leantext`. Stored explorer values are keyed by field name, not type, and survive; old `.anti-rt-*` spans degrade gracefully (class stripped, span unwrapped) in dev-only localStorage.
- **`richtext` and `html` are reserved names.** Their allowlists and option shapes are deferred until the editor decision (#22) lands.

## Considered Options

- **Leantext as a capability on text/textarea (four types)** — rejected; plain fields feed hrefs/alt/aria/slugs where markup is a footgun. The type boundary is the security boundary.
- **One WYSIWYG type with richness × line-count dials** — rejected; only the heavy block editor spans the dial, and it fuses the vocabulary decision with the editor choice.
- **Whole-object option override** — rejected for per-key: per-key matches the near-universal layered-config convention (CSS cascade, VS Code settings, tsconfig extends) and whole-replacement still needs an inheritance rule for omitted keys.
- **Keep `richtext/` + `anti_rt_*` as a neutral shared namespace** — rejected: "richtext" would mean two things (machinery namespace and field type).

## Consequences

Map #18's four-type headline becomes five. ADR 0013's per-decision machinery (style registry, sanitize-on-output, dual-view sidebar, selection bubble, segment engine) survives under new names; its central single-graduated-type framing is amended. Accepted costs: "mostly lean + occasional list" fields must adopt the heavier richtext type; the sanitizer allowlist now resolves from two inputs (field ⊕ project defaults), audited by verify.php assertions. Wayfinder reconciliation: map #6 closes as destination-reached-under-a-new-name; #14 closes as obviated (blocks → the richtext type via #22; "links as a leantext capability" becomes fog on map #18); #15 migrates to map #18 in palette vocabulary.

---
*Deliberation: [grill thread →](https://decisionrecords.localhost:8453/s/01kym2mtedpkwqjhrw4wtqz0hj)*
