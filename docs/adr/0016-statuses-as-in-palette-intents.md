---
status: accepted
thread: 01kypktgeaenbbhf0bypc3jefx
---

# Statuses are in-palette intents, not auto-generated colorways

A status (`success`/`warning`/`danger`/`info`) is two tokens inside a palette — the color (`--palette-success`) and its computed legible foreground (`--palette-success-on`) — plus derived hover/active states. It is a peer of `accent`, not a sub-palette with its own surface and scale. The auto-generated per-status semantic colorways (introduced in `bdf589a`) are deleted: swapping `data-colorway="success"` onto a badge replaced the surrounding palette context entirely, which is a region-theme mechanism abused as an element color. Instead the generator emits one binding rule per intent (`[data-intent="success"] { --intent: …; --intent-on: …; }`) and status components emit `data-intent`, staying inside whatever palette surrounds them.

The `-on` suffix (Material's `on-primary` pattern) is the contrast contract collapsed to one step for an element that brings its own background; it cannot be named `-contrast` because the scale's step names already end in that word. `-on` is auto-derived by luminance pick per palette and overridable by defining `{slot}-on` in data. Statuses inherit from the default palette via the CSS cascade; defining the status key in a palette is the override — no inversion flag exists. Two tokens is the default, not a ceiling: tint slots like `success-soft` are one-line data extensions when something needs them, and the full primitive status ramp remains available underneath.

## Considered Options
- **Keep auto-generated semantic colorways** — free whole-region status theming, but duplicate vocabulary, the context-discard bug stays the default, and variables multiply.
- **Statuses at the primitive tier only** — no per-palette adaptation; every component re-derives legibility, the problem tier 2 exists to solve.
- **Per-status rules in each component's CSS** — no generator involvement, but hardcodes the status list in every consumer; a new intent stops being a data edit.

## Consequences
Badge and any future status consumer change from `data-colorway` to `data-intent`; the verify assertion follows. A deliberately status-themed *region* is still possible by hand-authoring a full status palette in data — it just isn't manufactured automatically.

---
*Deliberation: [grill thread →](https://decisionrecords.localhost:8453/s/01kypkrk2b2apdt7wej95rm7qe)*
