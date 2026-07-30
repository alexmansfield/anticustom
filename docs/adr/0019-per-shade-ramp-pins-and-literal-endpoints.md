---
status: accepted
---

# Ramp shades are pinnable by presence-of-key; ramps span literal endpoints

Individual shades of a generated ramp are overridable via a **pin**: each color gains an optional `pins` map, stop name → bare hex (`"pins": { "dark": "#1e2f45" }`). Key presence is the pin; clearing a pin is deleting the key, which falls the shade back to the generated ramp. There is no dense per-stop grid, no `pinned` flag, and no materialized computed values in `defaults.json` — the generated ramp stays computable from `color` + `hues` independent of pins, so absence unambiguously means "generated" (the ambiguity that disqualified sparse-custom for sizes in ADR 0018 does not arise). Pins are absolute: they survive source-color changes and ramp regeneration. The editor indicates a pinned stop and offers removal; no drift-since-pinning is tracked and no computed-value snapshots are stored — the current generated value is always live-computable for display. This is the deepened color half of the ADR 0012 amendment, and it matches the presence-of-key idiom ADR 0015 set for palette slot overrides.

A pinned shade's `-hover`/`-active` derive from the pinned value through the normal band logic — the pin replaces the *input* to state derivation, not the derivation — so the legibility and visibility invariants (color-system-spec §4) hold over pins with no special-casing. Explicit `{stop}-{state}` keys remain the override hatch above that.

Every ramp spans **literal endpoints** (adopting dossier item 3 / spec §3d): `white`/`black` are ordinary data stops in `hues` (`value: 100`/`0`, `enabled`, renameable), not generator hardcoding. The special behavior triggers on the lightness value, not the name: a stop at exactly L100/L0 emits literal `#ffffff`/`#000000` and derives *tinted* hover/active forward from the retained source hue (runtime recovery is impossible — hue is powerless at L=0/100). Endpoints accept pins like any stop; literalness is the default's guarantee, not an enforced invariant, and a pinned endpoint routes through the normal band path as whatever value it now is. Net generator rule everywhere: resolve each stop's rest value (pin wins over computation), then derive states from it — with one value-triggered special case at the true extremes.

## Considered Options
- **Dense flag + value per stop (old sizes pattern)** — materializes computed hexes for the whole color × stop grid into `defaults.json`; they go stale on any source or ramp-math change, importing the drift problem ADR 0018 killed pins over into the data file itself.
- **Sparse entry with a `pinned` flag** — nondestructive un-pin/re-pin, but leaves `pinned: false` corpses the panel and verify must reason about; un-pin means "back to the system," not "mute my value," and re-pin re-seeds from the current computed shade anyway.
- **Drift-since-pinning tracking** — requires a computed-value snapshot at pin time; a second stored value that rots silently (an HSL→OKLCH migration would falsely flag every pin) and answers no question the live delta doesn't.
- **Endpoints as generator invariants** — hardcodes two stops one layer below the slot array ADR 0015 just made data-driven; special-casing on L=100/0 keeps the list open and lets an edited endpoint gracefully become an ordinary stop.
- **Endpoints exempt from pinning** — a carve-out the panel, sanitizer, and verify would each have to carry, to enforce a guarantee that only ever applied to the default state.

## Consequences
`defaults.json` colors gain an optional `pins` map; `generate.php` resolves rest values pin-first and adds the L100/L0 literal-endpoint path with forward-derived tints; `hues` gains `white`/`black` stops. The editor contract is presence-based: show a pin indicator where the key exists, delete the key to clear. Verify can assert the §4 invariants over pinned values identically to generated ones. Colors and sizes now sit deliberately on opposite sides of the ADR 0012 amendment: per-value pins deepened here, dropped there.
