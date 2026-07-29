---
status: accepted
thread: 01kypktge45qer68kmf0w86vw4
---

# Palettes are surface-anchored, open contrast scales

A palette is an open contrast scale anchored on `surface` — default steps `ultra-soft-contrast`, `soft-contrast`, `hard-contrast`, `ultra-hard-contrast`, deliberately no middle step — plus hued intents, not a fixed role list. Steps are named by contrast (soft↔hard), not lightness, so component CSS survives a light→dark palette flip. All slot names are editable data keys: the generator iterates each palette's own keys (a key matching `{existing-slot}-(on|hover|active)` attaches to that slot as an explicit override, per ADR 0012's hybrid pattern; every other key is a slot), replacing the hardcoded `$colorwayTokens` array. Emission is sparse — unset slots emit nothing — which makes the `var()` fallback in component CSS a mandatory contract, enforced by a verify check, and makes inheritance from the default palette a plain CSS-cascade effect.

The anchor is `surface`, not `base`: `base` collides with the primitive brand color of the same name, and `surface` is the standard term. Existing `--colorway-contrast` (middle step) refs remap to `hard-contrast`; the fallback sweep must land before sparse emission does, or missing tokens fail silently.

## Considered Options
- **Fixed 5-role colorway (status quo, ADR 0012)** — closed vocabulary; every new emphasis level or intent is a code change.
- **Lightness naming (light↔dark)** — familiar, but inverts and breaks on a light→dark flip.
- **Keep a default middle `contrast` step** — avoids a 19-ref remap, but the name reads as neither soft nor hard and the refs are being renamed anyway.
- **Explicit slot registry in config** — no key iteration, but recreates the closed role list the open scale exists to avoid.

## Consequences
Extending the system (more steps, renamed steps) becomes a data edit, not a code change. Components must always carry `var()` fallbacks. Evolves ADR 0012's 5-role colorway; the per-value override hatch survives unchanged.

---
*Deliberation: [grill thread →](https://decisionrecords.localhost:8453/s/01kypkrk2b2apdt7wej95rm7qe)*
