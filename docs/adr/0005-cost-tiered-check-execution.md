---
status: accepted
thread: 01kvm96znfe88x9dp8bym3ettc
---

# Checks execute on a cost tier: cheap live, expensive on-demand

Following issue #2's "run cheapest-first" principle, cheap input and render invariants run live on each debounced edit in the component editor, while expensive headless-browser checks (the contrast sweep, `no-upscale`) run on-demand via an explicit "Run checks" action and/or in the authoring/CI harness — never on every keystroke. The editor's live path is a 300ms-debounced fetch round-trip; adding dozens of screenshots per edit would make the preview unusable. This keeps the live preview responsive while still giving the author a one-click deep check.

## Considered Options

- **Everything live on every edit** — simplest mental model; infeasible latency for browser-pixel checks.
- **Everything authoring-time / CI only** — no live feedback at all; forfeits the near-free cheap-invariant win.
- **Everything behind one on-demand button** — uniform, but withholds the instant feedback users expect on cheap checks.

---
*Deliberation: [grill thread →](https://decisionrecords.localhost:8453/s/01kvm93z7bc8cj1cws1qtccmjt)*
