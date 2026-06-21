---
status: accepted
thread: 01kvm96zncr2xwewexncege2ct
---

# Failure-state params have registry defaults with per-component override

A predicate carries sensible default params in the shared registry, and a component may override them by switching its `failure_states` entry from a bare string to an object form `{ id, params }` (e.g. a tighter `min-contrast` threshold, or a `no-upscale` source-resolution cap). This supports issue #2's principle that every layout failure state implies a derived input requirement obtained by capping a component-specific variable — caps that legitimately differ between, say, a hero and a card — while global defaults keep the common case terse.

## Considered Options

- **Global-only thresholds** — simplest, but cannot express the per-component caps issue #2 argues are mathematically required.
- **Per-component-only (no registry defaults)** — maximal control but forces every component to restate params, with no shared baseline.

## Consequences

A `failure_states` entry is polymorphic (string *or* object). Consumers must accept both forms, and the registry must define the param schema each predicate accepts.

---
*Deliberation: [grill thread →](https://decisionrecords.localhost:8453/s/01kvm93z7bc8cj1cws1qtccmjt)*
