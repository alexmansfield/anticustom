---
status: accepted
thread: 01kvm96zne394smvb2xpn2hr0m
---

# Layout invariants run in a browser-driven authoring/CI harness

The layout-invariant tier is a new harness modeled on `components/verify.php`'s accounting pattern (per-check `OK`/`ERROR` strings, pass/fail counts, nonzero exit on failure), but driving a real browser: it loops `scan_components()` over components, renders each in the explorer (`localhost:8702`) with adversarial worst-case props, and measures real geometry and composite pixels via the chrome-devtools MCP (`resize_page`, `take_screenshot`, `evaluate_script`). It runs at authoring/CI time, not inside the live editor, because layout failures (upscaling, contrast, overflow) are emergent properties that only a real render exposes.

## Considered Options

- **Run layout checks live in the editor on every edit** — a contrast sweep is ~56 screenshots; infeasible latency and flakiness on the keystroke path.
- **Pure-PHP layout check (no browser)** — cannot measure rendered geometry or sample composite pixels, which is the entire point of the tier.
- **Standalone Playwright/Puppeteer runner** — adds a Node toolchain and browser dependency the in-session chrome-devtools MCP already covers.

---
*Deliberation: [grill thread →](https://decisionrecords.localhost:8453/s/01kvm93z7bc8cj1cws1qtccmjt)*
