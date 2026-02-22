# Anticustom — Project Plan

## Vision

Three standalone tools for building high-quality web projects, designed for humans and machines alike. Each tool is JSON-schema-driven with a shared explorer workbench for interactive use.

**Philosophy:** Good and fast. Machines remove the human bottleneck; clear schemas remove the machine bottleneck.

## Repository Structure

One repo (`anticustom`) for the three tools and the explorer. The CMS will be a separate repo later.

```
anticustom/
├── styles/                  # Design token definitions
│   ├── tokens.schema.json   # Token schema (from panel.schema.json)
│   ├── defaults.json        # Default token values
│   ├── generate.php         # CSS variable generator
│   └── GUIDE.md             # LLM instruction file
│
├── components/              # Component building blocks
│   ├── button/
│   │   ├── button.schema.json
│   │   ├── styles/
│   │   └── templates/
│   ├── card/
│   ├── ...
│   └── GUIDE.md             # LLM instruction file
│
├── forms/                   # Form field & validation definitions
│   ├── fields.schema.json   # Field type definitions
│   ├── validation.schema.json  # JSON Schema-based validation
│   └── GUIDE.md             # LLM instruction file
│
├── explorer/                # PHP + Alpine.js workbench
│   ├── index.php            # Entry point
│   ├── panels/              # Settings panels (sidebar)
│   │   ├── styles.php       # Token editor controls
│   │   ├── components.php   # Component prop editor
│   │   └── forms.php        # Form field editor
│   ├── views/               # Main content area (built with components)
│   │   ├── styles.php       # Token preview + reference
│   │   ├── components.php   # Component preview + detail
│   │   └── forms.php        # Form preview + detail
│   ├── js/                  # Alpine.js + editor modules (from anticustom-editor)
│   ├── css/
│   └── shared/              # Shared PHP helpers (rendering, loading, etc.)
│
├── CLAUDE.md                # Project-level AI instructions
├── design.md                # Universal design principles
└── PLAN.md                  # This file
```

## Decisions Made

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Styles scope | Token definitions only | Editor UI lives in the explorer |
| Styles basis | Existing panel.schema.json | Already defines token categories well |
| Component templates | PHP only (for now) | Used by the explorer; add formats later |
| Component schema changes | Remove `ai_metadata` and `permission` | AI guidance → GUIDE.md; permissions → CMS concern |
| Component schema keeps | name, label, version, category, icon, tabs, fields, tokens_used, children | All still useful for standalone tool |
| Forms scope | Schema + validation only | Rendering uses components; processing is consumer's job |
| Forms validation | JSON Schema standard | Language-agnostic, huge ecosystem, fits JSON-first approach |
| Explorer type | PHP playground + Alpine.js | PHP reads tool files from disk; Alpine.js for interactivity |
| Explorer features | Full workbench with export | Edit tokens → export CSS; preview components; build forms |
| Explorer layout | Settings panel (sidebar) + main content area | Matches existing editor; panel controls settings, main area shows live results |
| Content HTML | All components | Everything between `<body>` and `</body>` is rendered through components. Panel sidebar is the temporary exception (already built). |
| LLM documentation | One CLAUDE.md-style file per tool | Dropped into AI context as instructions |
| AI metadata | Consolidated to GUIDE.md | Natural language better for LLMs than structured JSON |
| Distribution | TBD (leaning shadcn-style copy) | Decide after tools stabilize |
| CMS | Separate repo, built later | Focus on tools + explorer first |

## Explorer Layout

The explorer follows the existing anticustom-editor layout pattern:

```
┌─────────────────────────────────────────────────────────┐
│  Tool Navigation (Styles | Components | Forms)          │
├──────────────┬──────────────────────────────────────────┤
│              │                                          │
│   Settings   │   Main Content Area                     │
│   Panel      │                                         │
│   (sidebar)  │   ┌──────────────────────────────────┐  │
│              │   │  Live Preview                     │  │
│   Controls   │   │  (rendered with components +      │  │
│   for the    │   │   current token styles)           │  │
│   selected   │   └──────────────────────────────────┘  │
│   tool       │                                         │
│              │   ┌──────────────────────────────────┐  │
│   - Token    │   │  Detail Sections (scrollable)    │  │
│     editors  │   │  - Schema viewer                 │  │
│   - Prop     │   │  - CSS inspector                 │  │
│     editors  │   │  - Template output               │  │
│   - Form     │   │  - Token usage / reference       │  │
│     editors  │   │  - etc.                          │  │
│              │   └──────────────────────────────────┘  │
│              │                                          │
├──────────────┴──────────────────────────────────────────┤
│  Export actions (CSS file, JSON config, etc.)           │
└─────────────────────────────────────────────────────────┘
```

**Key principle:** The main content area is built entirely with anticustom components. The settings panel sidebar reuses the existing editor code (temporary exception — will be rebuilt from components later).

**Styles view:** Settings panel edits token values. Main area shows components rendered with those tokens (dogfooding) + a token reference table showing all CSS variables and their current values.

**Components view:** Settings panel edits component props (auto-generated from schema fields, organized by tabs). Main area shows the live component preview + scrollable detail sections (schema, CSS, template output, token usage).

**Forms view:** Settings panel edits form field definitions and validation rules. Main area shows the form rendered using components + schema viewer.

## Build Phases

Explorer-first development: we build the explorer and create tool functionality as the explorer demands it. Each phase ends with something testable.

Components are built incrementally — we create new ones as the explorer needs them rather than building everything upfront.

---

### Phase 1: Repository Setup & Migration

**Goal:** Clean foundation with existing work migrated into the new structure.

- [ ] Pull from `https://github.com/alexmansfield/anticustom.git`
- [ ] Create folder structure (`styles/`, `components/`, `forms/`, `explorer/`)
- [ ] Migrate existing component schemas into `components/`
  - Remove `ai_metadata` from all schemas
  - Remove `permission` from all field definitions
  - Keep everything else intact
- [ ] Extract token schema from `anticustom-editor/panel.schema.json` into `styles/`
  - Separate schema (structure) from defaults (values)
- [ ] Migrate component styles and PHP templates
- [ ] Write initial project-level `CLAUDE.md`
- [ ] Write initial `design.md` (migrate from current)

**Test:** Folder structure exists, schemas are valid JSON, no ai_metadata or permission fields remain.

---

### Phase 2: Styles Tool Core

**Goal:** Define tokens in JSON, generate a CSS variables file from them.

- [ ] Finalize `tokens.schema.json` — the format for defining design tokens
  - Typography (headings, text — base size + scale + overrides)
  - Colors (palette with hues, colorways)
  - Spacing (base size + scale + overrides)
  - Borders (widths, radius, shadows)
  - Effects (transitions)
- [ ] Create `defaults.json` — sensible default values (from panel.schema.json defaults)
- [ ] Build `generate.php` — reads token JSON, outputs a CSS `:root` block with variables
- [ ] Reconcile token names: ensure CSS variable names from `generate.php` match what component `tokens_used` arrays reference
- [ ] Write initial `styles/GUIDE.md`

**Test:** Run `php styles/generate.php` → get a valid CSS file. Token variable names match component expectations.

---

### Phase 3: Components Tool Core

**Goal:** Render any component from its schema + data + tokens.

- [ ] Clean all component schemas (ai_metadata removed, permissions removed)
- [ ] Build a PHP rendering function: `render_component($name, $data)` → HTML string
  - Loads the component's schema
  - Validates data against schema fields
  - Renders the PHP template with interpolated data
- [ ] Verify all existing components render correctly with default data
- [ ] Identify which new components are needed for the explorer's main content area
  - Likely needed early: `code-block` (for schema/CSS/template display)
  - Others added as needed per phase
- [ ] Write initial `components/GUIDE.md`

**Test:** `render_component('button', ['text' => 'Get Started', 'variant' => 'solid'])` → correct HTML output.

---

### Phase 4: Explorer Shell

**Goal:** A working PHP app with the settings panel + component-based main content area.

- [ ] Create `explorer/index.php` — entry point with routing
- [ ] Port the existing editor panel sidebar from `anticustom-editor/`
  - Panel HTML/CSS/JS (the settings sidebar)
  - Alpine.js state management
- [ ] Build the main content area layout using components
  - `section` for page wrapper
  - `container` for layout
  - New components as needed (created in `components/`)
- [ ] Style everything with generated token CSS (dogfooding)
- [ ] Create stub views for styles, components, and forms
- [ ] Wire up tool navigation (Styles | Components | Forms)

**Test:** Open in browser. Settings panel renders on the side. Main area shows component-based content. Navigate between tools.

---

### Phase 5: Explorer — Styles View

**Goal:** Edit design tokens visually, see them applied to real components, export CSS.

- [ ] Port token editor modules into the settings panel
  - `panel-colors.js`, `panel-colorways.js`
  - `panel-typography.js`
  - `panel-spacing.js`
  - `panel-borders.js` (borders, radius, shadows)
  - `panel-style-renderer.js`
- [ ] Build the main content area (using components):
  - **Component showcase:** Render a selection of components (button, card, intro, etc.) styled with current tokens. Tokens update → components re-render live.
  - **Token reference table:** All CSS variables with current values, color swatches, size previews. (May need a new `table` or dedicated component for this.)
- [ ] Add CSS export: generates and downloads the CSS variables file
- [ ] Add JSON export: downloads current token configuration

**Test:** Edit a color in the panel → see buttons/cards update in the main area → check the reference table → export CSS file → verify.

---

### Phase 6: Explorer — Components View

**Goal:** Browse, preview, and inspect all components interactively.

- [ ] Build component browser (list all available components — in panel or as navigation)
- [ ] Settings panel: interactive prop editor
  - Auto-generated from component schema fields
  - Organized by schema tabs (content, settings)
  - Respects field types (text, select, boolean, colorway, url)
- [ ] Main content area (using components, scrollable):
  - **Live preview** — selected component rendered with current props + token styles
  - **Schema section** — component's JSON schema (using `code-block` component)
  - **CSS section** — component's stylesheet(s) (using `code-block` component)
  - **Template section** — rendered PHP template source (using `code-block` component)
  - **Token usage section** — which design tokens this component references

**Test:** Select button → edit text and variant in panel → see live preview update in main area → scroll down to see schema, CSS, template output.

---

### Phase 7: Forms Tool Core

**Goal:** Define form fields and validation using JSON Schema.

- [ ] Design `fields.schema.json` — defines available field types
  - Text, email, url, number, textarea, select, checkbox, radio, date, file
  - Each field type specifies its allowed validation rules
- [ ] Design `validation.schema.json` — JSON Schema validation keywords
  - required, minLength, maxLength, pattern, format, minimum, maximum, enum
  - Custom keywords for business rules (defined as extensions)
- [ ] Build a PHP validation function: `validate_form($schema, $data)` → errors array
- [ ] Create any components needed for form rendering (input, label, form-group, etc.)
- [ ] Write initial `forms/GUIDE.md`

**Test:** Define a contact form schema → validate sample data → get correct error messages.

---

### Phase 8: Explorer — Forms View

**Goal:** Build and preview forms using the forms tool + components.

- [ ] Settings panel: form schema editor
  - Add/remove/reorder fields
  - Configure field types and validation rules
- [ ] Main content area (using components, scrollable):
  - **Form preview** — rendered using anticustom components with live validation feedback
  - **Schema section** — form's JSON schema (using `code-block` component)

**Test:** Define a signup form in the panel → see it rendered with components in the main area → fill in data → see validation.

---

### Phase 9: Integration & Polish

**Goal:** Cross-tool features and production-quality explorer.

- [ ] Token usage visualization: which components use which tokens, highlight gaps
- [ ] Component composition preview: nest components (e.g., card with button children)
- [ ] Form → component mapping: show which components a form uses
- [ ] Complete export workflows for all views
- [ ] Error handling and edge cases
- [ ] Responsive main content area

**Test:** Full workflow: define tokens → preview a component using those tokens → build a form using that component → export everything.

---

### Phase 10: LLM Documentation

**Goal:** Each GUIDE.md is battle-tested and complete.

- [ ] Finalize `styles/GUIDE.md` with real-world examples and anti-patterns
- [ ] Finalize `components/GUIDE.md` with composition examples and common recipes
- [ ] Finalize `forms/GUIDE.md` with validation examples and field type reference
- [ ] Update project-level `CLAUDE.md` to reference all three guides
- [ ] Test: provide an AI with only the tool files + GUIDE.md, verify it can build a landing page

**Test:** Give Claude only the anticustom folder + guides → ask it to build a pricing page → verify it uses tokens, components, and forms correctly.

---

## Components Roadmap

Components are built as needed. This tracks which components exist and which the explorer will likely require.

### Existing Components
- `button` — CTAs, form submits, actions
- `card` — Contained content blocks with optional image + button children
- `badge` — Status/category labels
- `table` — Tabular data with sorting, selection, row actions
- `hero` — Page header with intro + CTA children
- `section` — Page wrapper with colorway context
- `container` — Grid/flex layout wrapper
- `intro` — Hierarchical text (eyebrow, title, subtitle)
- `faq` — Expandable Q&A accordion
- `stats` — Numerical metrics display
- `testimonial` — Customer feedback with ratings

### Likely New Components (built as explorer demands them)
- `code-block` — Syntax-highlighted code display (needed for schema/CSS/template viewers)
- `input` — Form text input (needed for forms rendering)
- `select` — Form dropdown (needed for forms rendering)
- `checkbox` / `radio` — Form controls (needed for forms rendering)
- `textarea` — Multi-line text input (needed for forms rendering)
- `form-group` — Label + input + error message wrapper (needed for forms rendering)
- Others as needed

---

## Future Work (Not In Scope)

- **CMS repo** — Separate Laravel app using the three tools. Will be planned independently.
- **Additional template formats** — Blade, Etch, etc. Add once PHP templates are solid.
- **CLI tool** — shadcn-style `anticustom add button` for copying tool files into projects.
- **Package distribution** — npm/composer publishing if/when tools stabilize.
- **Rebuild panel from components** — The settings panel sidebar currently uses its own HTML/CSS. Eventually rebuild it using anticustom components.

## Open Questions

1. **Distribution model** — How exactly should projects consume these tools? Leaning shadcn-style copy. Decide after Phase 9.
2. **Token naming** — Current component schemas reference tokens like `spacing-1`, `typography-font-size-sm`. These need to align with the CSS variables the styles tool generates. Reconcile during Phase 2.
3. **Form rendering components** — Which components are needed for form inputs? Design during Phase 7.
4. **Panel rebuild** — When to rebuild the settings panel sidebar using components. After core explorer is stable.
