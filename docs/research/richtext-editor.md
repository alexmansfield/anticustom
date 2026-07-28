# Research: A block-capable editor for the `richtext` field

**Ticket:** GitHub issue #20 — the `richtext` field needs block support (lists,
headings, paragraphs). The current engine (`richtext/engine.js`) is structurally
inline-only; ADR 0013 kept Tiptap/ProseMirror as a documented-but-unshipped swap
path. This reopens that with fresh facts.

**Status:** Research only. No recommendation. Facts and trade-offs, each claim
cited to a primary source (URL) or repo file:line.

**Grounding (repo facts):**
- The repo is build-free PHP; the explorer is a static `php -S` server
  (`CLAUDE.md`, project overview). No bundler, no npm build step exists.
- Storage is sanitized HTML strings, re-sanitized on every render by a
  DOM-walking allowlist derived from the field's schema
  (`richtext/sanitize.php:78-130`; ADR 0013 "Decisions").
- Named styles are a global registry compiled to one class each
  (`.anti-rt-<name>`), opted into per field (`richtext/styles.json`;
  `richtext/sanitize.php:287-308`).
- The engine's swap seam is `AntiRT.createEditor({root, features, ...}) →
  {exec, getHTML, setHTML, destroy}` (`richtext/engine.js:16-20`, 271-785).
- The sanitizer *already* supports tier-4 blocks (`ul`/`ol`/`li`) and links —
  only the engine and toolbar defer them (`richtext/sanitize.php:59,101-106`;
  ADR 0013 line 12). So block support is a client-editor problem, not a
  storage/security problem.

---

## Question 1 — Adopting Tiptap/ProseMirror in a build-free PHP repo

### 1a. Can it run from a CDN / ESM without a bundler? What breaks?

**Yes in principle, with a sharp caveat.** Tiptap's official docs present a
no-bundler path: load ES modules directly with `<script type="module">` from
esm.sh, e.g. `import { Editor } from 'https://esm.sh/@tiptap/core'` and
`import StarterKit from 'https://esm.sh/@tiptap/starter-kit'`. "Your `<script>`
tag must include `type="module"` for the import syntax to work in the browser."
The docs do not flag production caveats.
([Tiptap CDN docs](https://tiptap.dev/docs/editor/installation/cdn);
[Tiptap vanilla JS install](https://tiptap.dev/docs/editor/getting-started/install/vanilla-javascript))

**What breaks: the ProseMirror single-instance invariant.** ProseMirror requires
that exactly one copy of each `prosemirror-*` package (especially
`prosemirror-model`) is loaded. If two versions load, it throws
`RangeError: Can not convert <> to a Fragment (looks like multiple versions of
prosemirror-model were loaded)` on basic operations like inserting a line break.
This is a documented, real failure when importing Tiptap from **esm.sh**, which
resolved duplicate transitive versions (e.g. `prosemirror-view` 1.32.4 *and*
1.32.7, `prosemirror-model` 1.19.3 *and* 1.19.4).
([Tiptap issue #4873](https://github.com/ueberdosis/tiptap/issues/4873);
[ProseMirror issue #1070](https://github.com/ProseMirror/prosemirror/issues/1070))

- Root cause: bare specifiers can't be resolved by browsers — "Browsers can't
  resolve dependencies like `prosemirror-model`, only precise URLs" — so
  deduping must be done by the CDN or by an **import map** that pins one URL per
  package. ProseMirror's maintainer added `.mjs`/`module`-field ESM builds
  specifically to enable buildless use, but "version deduplication remains a
  consideration for buildless setups without proper dependency management."
  ([ProseMirror buildless ESM thread](https://discuss.prosemirror.net/t/prose-mirror-buildless-esm-support/2322))
- The community workaround for the esm.sh failure is **jsDelivr's `+esm`
  endpoint**, which resolves the peer graph correctly:
  `import { Editor } from 'https://cdn.jsdelivr.net/npm/@tiptap/core@2.2.2/+esm'`
  — pinning an exact version so the whole graph agrees.
  ([Tiptap issue #4873](https://github.com/ueberdosis/tiptap/issues/4873))
- Tiptap ships `@tiptap/pm` as the canonical single-copy ProseMirror bundle
  precisely to guarantee "the same version used by Tiptap" across extensions.
  Buildless, you must make the CDN honor that (exact-version pins + an import map
  routing every `prosemirror-*` and `@tiptap/pm/*` specifier to one URL).
  ([ngx-editor #500 / same-instance rationale](https://github.com/sibiraj-s/ngx-editor/issues/500))

**Secondary buildless costs:**
- Network waterfall: native ESM fans out to many small module requests; "with
  larger apps the client browser can make many requests, which is slow."
  esm.sh/jsDelivr mitigate with `?bundle`/`+esm` sub-module bundling, but this
  reintroduces the duplicate-shared-module risk that causes the invariant break.
  ([esm.sh README / scaling notes](https://github.com/esm-dev/esm.sh))
- Third-party runtime dependency: the editor would load from a public CDN at
  page load (offline explorer breaks; CDN outage breaks editing; no SRI by
  default on esm.sh — [SRI request #1206](https://github.com/esm-dev/esm.sh/issues/1206)).
  Vendoring the pinned `+esm` files locally is possible but means hand-managing a
  flattened dependency graph — i.e. doing a bundler's job by hand.

### 1b. Realistic bundle size (lists + headings + links + marks)

- ProseMirror **core alone** (model, view, state, transform — always required)
  is ~60 KB; with typical additions the core footprint is cited around ~73 KB
  gzipped before Tiptap's own layer.
  ([discuss.ProseMirror: reduce bundle size](https://discuss.prosemirror.net/t/reduce-size-of-bundle/1705))
- Tiptap `StarterKit` bundles ~27 extensions (paragraph, heading, lists,
  blockquote, code block, history, marks, …). It is tree-shakeable *with a
  bundler*; buildless from a CDN you generally pay for the whole entry unless you
  import individual extension packages by hand.
  ([ProseMirror StarterKit size guide](https://domternal.dev/v1/packages/);
  bundle-size search summary)
- Net realistic figure for a lists+headings+links+marks config: **low-to-mid
  hundreds of KB min, ~75-120 KB+ gzipped**, i.e. roughly 1-2 orders of magnitude
  larger than the current hand-rolled `engine.js` (~860 lines, no deps). Exact
  gzip depends heavily on whether you can tree-shake (needs a bundler) vs. load
  StarterKit whole (buildless). *Note: the precise gzip number for a buildless
  CDN StarterKit load was not pinned to a single primary source; treat the range
  as an estimate to confirm empirically.*

### 1c. Coexistence with sanitize-on-output and the named-style registry

- **Sanitizer boundary — compatible, and partly redundant.** Tiptap already
  parses input HTML through a strict schema and *discards anything the schema
  doesn't define*: "You can't use any HTML element or attribute that is not
  defined in your schema"; unknown elements/marks/attributes "are simply stripped
  rather than preserved." So Tiptap normalizes on input the way `sanitize.php`
  normalizes on output — the server allowlist (`sanitize.php:78-130`) still runs
  as the trust boundary (client code is never trusted), so the two stack cleanly.
  ([Tiptap schema docs](https://tiptap.dev/docs/editor/core-concepts/schema))
- **Named-style registry — needs a custom mark/attribute extension.** The
  `.anti-rt-<name>` classes on `<span>` (`sanitize.php:202-216`) are not a
  built-in Tiptap concept. To round-trip them you must author a custom mark (or a
  `class` attribute on an existing mark) with `parseHTML`/`renderHTML` matching
  `anti-rt-*`, mirroring what `parseNode` does today (`engine.js:63-66`). Without
  it, Tiptap's schema-strict input parse would **strip the styled spans on
  load** (per 1c above) — the same failure mode ADR 0013 flagged as "its document
  model sits between schemas and HTML" (ADR 0013 line 22). Feasible, but it means
  re-expressing the whole `features`/registry mapping as ProseMirror schema
  config, in addition to the buildless-graph work in 1a.

---

## Question 2 — Extending the flat segment model to blocks

The current model is one entry per UTF-16 code unit `{ch, bold, italic, style}`
plus `{br:true}` (`engine.js:33-44`, 40-52). Everything downstream assumes this
flat shape. Here is specifically where blocks fight it:

1. **`parseNode` flattens by design.** Unknown/block elements are "transparent
   and contribute their text"; only `STRONG/B`, `EM/I`, `SPAN.anti-rt-*`, and
   `BR` carry structure (`engine.js:52-67`). A `<ul><li>` would be flattened to
   its text with no list boundary retained — the model has no slot for "this
   character belongs to list item 2 of an unordered list." Adding blocks means a
   second, orthogonal axis (block container + nesting depth) that the per-char
   record doesn't have.
2. **`renderModel` emits inline-only canonical DOM.** Its nesting is fixed:
   `span.anti-rt-* > strong > em` over runs, with `<br>` as the only separator
   (`engine.js:103-163`). Lists/headings need block elements *wrapping* runs
   (`<ul><li>…</li></ul>`, `<h2>…</h2>`), i.e. a containment hierarchy, not run
   grouping. This function would have to be rewritten around block segmentation,
   not extended.
3. **Selection index mapping is a flat character count.** `toIndex`/`fromIndex`
   walk text length + 1 per `<br>` (`engine.js:189-267`), and `subtreeLength`
   counts `<br>` as length 1 (`engine.js:189-196`). Block boundaries have no
   length today. Lists/headings introduce positions that aren't a single code
   unit (the boundary between two `<li>`s, an empty list item, the caret at the
   start of an empty heading). ProseMirror uses a richer position model (each
   node open/close counts) precisely because a flat count can't address these;
   the flat model would need boundary tokens (like the existing `{br}`) for every
   block edge, and every index computation reworked to count them consistently.
4. **`insertBreak`/`insertParagraph` collapse to one `{br}`.** Enter currently
   splices a single `{br}` (`engine.js:579-590`, 633-637). Block semantics need
   Enter to mean "split the current block" (new `<li>`, new paragraph) and a
   second Enter in an empty item to mean "exit the list" — logic that has no home
   in a segment stream with no notion of "current block."
5. **`applyFeatures`/`normalizeDom` reason per-segment.** Feature gating and the
   canonical-DOM diff (`engine.js:77-96`, 364-380) both iterate flat segments;
   block nesting can't be validated by a per-character filter (e.g. "`<li>` only
   inside `<ul>/<ol>`", "no heading inside a list item") without structural
   rules.

**Assessment:** headings/paragraphs/lists are a *containment* model; the flat
segment array is a *paint* model (each char carries its own formatting). Marks
and one-per-range styles fit paint; blocks do not. Extending it means bolting a
parallel tree (block containers + nesting + block-boundary positions) onto a flat
array and reworking selection math, rendering, and Enter handling around it —
which is approximately re-deriving a document-tree model. This corroborates the
ticket's framing that the segment model is "structurally inline-only," and ADR
0013's note that the engine "defers" tier-4 rather than merely not-yet-shipping
it.

---

## Question 3 — Other lean editors

Target: "TinyMCE but not bloated/convoluted" — small, buildless-friendly, real
block support, and fits the sanitize-on-output + named-style-class model.

- **Squire** (fastmail/Squire) — purpose-built as an embeddable engine "for
  creating documents like emails and essays," no built-in toolbar/UI (you supply
  chrome, which matches the repo's mount-agnostic toolbar contract in ADR 0013).
  Supports lists and headings via block methods (e.g. change selected block to
  `ul`/`ol`). Combined JS+CSS cited around ~100 KB. It operates directly on
  contenteditable HTML (no separate document model), so its output is HTML that
  `sanitize.php` can re-sanitize — but it does not enforce a schema, so it will
  not by itself strip disallowed structure the way the flat model or ProseMirror
  do; the server allowlist stays load-bearing.
  ([Squire GitHub](https://github.com/fastmail/Squire))
- **Trix** (basecamp/trix) — ships a full editor *with* its own toolbar and an
  internal document model ("Trix converts input into an editing operation on its
  internal document model"). Built-in block attributes: `heading1`, `quote`,
  `code`, `bullet`, `number`; inline: bold, italic, href, strike. Distributed as
  a single self-contained UMD bundle (`dist/trix.umd.min.js`) — buildless-easy
  via one `<script>`. Costs: it owns its toolbar/chrome (conflicts with the
  field-anchored bubble in ADR 0013), it emits its own HTML shape and stores an
  internal-format document, and applying arbitrary `class` attributes to blocks
  is awkward (open issue), which is friction against the `.anti-rt-*` registry.
  ([Trix README](https://github.com/basecamp/trix/blob/main/README.md);
  [Trix class-on-block issue #1027](https://github.com/basecamp/trix/issues/1027))
  *Exact gzip size not pinned to a primary source; it is a single bundle
  materially larger than the current engine — confirm empirically.*
- **Lexical** (facebook/lexical) — modular; **core ~22 KB min+gzip**, pay only
  for plugins used; lists/headings/quotes/links/inline styles via markdown
  shortcuts and `@lexical/list`, `@lexical/rich-text` packages. Has a document
  model + schema-like node registry (so, like ProseMirror, it strips content that
  no registered node handles — named styles need a custom node/decorator).
  Buildless caveat: an open request notes the quick-start "requires
  typescript/nodejs/npm" and "a more minimalistic approach without a
  compiler/bundler is needed" — i.e. buildless is a community ask, not a
  first-class documented path, and the multi-package graph raises the same
  single-instance/import-map concerns as ProseMirror.
  ([Lexical intro/size](https://lexical.dev/docs/intro);
  [@lexical/list](https://lexical.dev/docs/packages/lexical-list);
  [buildless quick-start request #5840](https://github.com/facebook/lexical/issues/5840))
- **Extend the in-house engine to a block model** (the Question-2 path) — the
  most buildless-native option (zero deps, one `<script>`, already fits the
  swap seam and the sanitizer/registry), but per Question 2 it is effectively
  writing a small document-tree editor, and re-derives problems these libraries
  have already solved (selection across block boundaries, Enter-to-split,
  list exit).

---

## Trade-off summary

| Option | Buildless fit | Size (min+gzip, approx) | Block support | Sanitizer / `.anti-rt-*` fit |
|---|---|---|---|---|
| **Tiptap / ProseMirror** | Poor–moderate: works only if import maps pin one copy of every `prosemirror-*`; esm.sh breaks the single-instance rule, jsDelivr `+esm` at a pinned version works ([#4873](https://github.com/ueberdosis/tiptap/issues/4873)) | ~75–120 KB+ (core ~60–73 KB + StarterKit; tree-shake needs a bundler) | Full, first-class (lists/headings/paragraphs native) | Server allowlist stacks fine; named styles need a **custom mark extension** or input parse strips them ([schema docs](https://tiptap.dev/docs/editor/core-concepts/schema)) |
| **Extend flat segment model** | Excellent: zero deps, one `<script>`, already at the swap seam | ~ current (tiny) | Would be hand-built; fights the model (Q2: selection math, nesting, block boundaries, Enter) | Native — it already emits `.anti-rt-*` and matches `sanitize.php` exactly |
| **Squire** | Good: small, no build; ships no UI (matches mount-agnostic toolbar) | ~100 KB JS+CSS combined ([repo](https://github.com/fastmail/Squire)) | Lists + headings via block methods | Emits plain HTML (re-sanitizable); no schema enforcement — server allowlist stays essential; classes addable but manual |
| **Trix** | Excellent: single UMD `<script>`, no build | Single bundle, larger than current (exact gzip unconfirmed) | Built-in headings/quote/code/bullet/number | Owns its toolbar + internal doc format; per-block `class` is awkward ([#1027](https://github.com/basecamp/trix/issues/1027)) — friction vs. registry |
| **Lexical** | Poor–moderate: buildless is a community ask, not documented; multi-package graph like PM ([#5840](https://github.com/facebook/lexical/issues/5840)) | Core ~22 KB + per-plugin ([intro](https://lexical.dev/docs/intro)) | Full via plugins (`@lexical/list`, rich-text) | Schema-like nodes strip unknown content; named styles need a **custom node** |

---

## Open questions the decision still needs

1. **Is a public-CDN runtime dependency acceptable at all?** The explorer is a
   local `php -S` server; a CDN-loaded editor breaks offline and on CDN outage,
   and esm.sh offers no SRI by default. Is vendoring the pinned `+esm` files into
   the repo (hand-flattening the graph) acceptable, or does that reintroduce the
   "foreign package graph" ADR 0013 wanted to avoid?
2. **Empirical buildless bundle size + request count** for a real
   lists+headings+links+marks config (Tiptap via jsDelivr `+esm`, and Lexical),
   including the native-ESM request waterfall — the figures here are ranges, not
   measured.
3. **How much schema re-expression is acceptable?** Tiptap/Lexical both require
   re-authoring the `features` model and the `.anti-rt-*` registry as schema
   nodes/marks with `parseHTML`/`renderHTML`. Is that mapping a one-time cost or
   an ongoing coupling (registry changes now touch editor schema too)?
4. **Does the toolbar contract survive?** ADR 0013's field-anchored bubble is
   mount-agnostic. Tiptap/ProseMirror and Squire supply no UI (fits); Trix ships
   its own toolbar (conflicts). Which editors preserve the existing chrome
   contract vs. force a rewrite?
5. **What is the actual tier-4 scope?** The sanitizer allows only `ul`/`ol`/`li`
   blocks + links today (`sanitize.php:59,101-106`) — no headings/paragraphs yet.
   Does "block-capable" in #20 mean just lists (a smaller extend-the-engine job)
   or full paragraphs+headings (which tilts toward a document-model library)?
6. **Storage-format commitment.** Trix and Lexical prefer their own serialized
   formats; the repo's contract is *sanitized HTML strings with free `{field}`
   interpolation* (ADR 0013). Any editor whose native storage isn't HTML
   re-opens the "structured JSON storage" option ADR 0013 rejected for breaking
   interpolation.

---

## Sources

- Tiptap CDN install — https://tiptap.dev/docs/editor/installation/cdn
- Tiptap vanilla JS install — https://tiptap.dev/docs/editor/getting-started/install/vanilla-javascript
- Tiptap schema (strict parse, unknown stripped) — https://tiptap.dev/docs/editor/core-concepts/schema
- Tiptap esm.sh duplicate-ProseMirror failure + jsDelivr fix — https://github.com/ueberdosis/tiptap/issues/4873
- ProseMirror multiple-versions error — https://github.com/ProseMirror/prosemirror/issues/1070
- ProseMirror buildless ESM (import maps, maintainer stance) — https://discuss.prosemirror.net/t/prose-mirror-buildless-esm-support/2322
- ProseMirror bundle size discussion — https://discuss.prosemirror.net/t/reduce-size-of-bundle/1705
- StarterKit / packages size guide — https://domternal.dev/v1/packages/
- esm.sh (no-build CDN, bundling/waterfall) — https://github.com/esm-dev/esm.sh
- esm.sh SRI request — https://github.com/esm-dev/esm.sh/issues/1206
- ngx-editor single-instance rationale — https://github.com/sibiraj-s/ngx-editor/issues/500
- Squire — https://github.com/fastmail/Squire
- Trix README — https://github.com/basecamp/trix/blob/main/README.md
- Trix per-block class issue — https://github.com/basecamp/trix/issues/1027
- Lexical intro/size — https://lexical.dev/docs/intro
- @lexical/list — https://lexical.dev/docs/packages/lexical-list
- Lexical buildless quick-start request — https://github.com/facebook/lexical/issues/5840
- Repo grounding — richtext/engine.js, richtext/sanitize.php, richtext/styles.json, docs/adr/0013-graduated-richtext-fields.md, CLAUDE.md
