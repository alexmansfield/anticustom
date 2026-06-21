---
status: accepted
thread: 01kvm96zngdz0bw6jpxj3b87d3
---

# Checks run via a separate endpoint, not the render hot path

`explorer/shared/render.php` keeps returning bare HTML for the live-preview path, and a separate `explorer/shared/check.php` endpoint returns `{ failures: [...] }` for input and render checks (the browser harness handles layout). `render.php` is called on every debounced edit; folding check results into its response would couple a hot, intentionally-simple contract to validation work the preview doesn't always need, and would let the two run on independent cadences (see ADR 0005).

## Considered Options

- **Evolve `render.php` to return a `{html, failures}` envelope** — one round-trip carries both, but changes a simple hot contract and forces check work on every render.
- **Compute input checks purely client-side in `playground.js`** — the schema is already in `window.__antiComponents`, but this can't cover render/layout checks that need PHP or a browser.

---
*Deliberation: [grill thread →](https://decisionrecords.localhost:8453/s/01kvm93z7bc8cj1cws1qtccmjt)*
