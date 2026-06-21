---
status: accepted
thread: 01kvm96zna4dy345ep2tkrwdg1
---

# Failure states are declared as a per-component manifest array

Each component opts into checkable failure states via a flat `failure_states` array in its schema (e.g. `"failure_states": ["no-upscale", "text-fits", "min-contrast"]`), mirroring the existing `tokens_used` manifest. The renderer stays inert to it — exactly as it already ignores `validation` and `required` — and a separate consumer interprets the IDs against the shared registry. This keeps the contract declarative, co-located with the component, opt-in, and reusable, rather than introducing a parallel sidecar file or bespoke per-component config.

## Considered Options

- **Extend per-field `validation` only** — cannot express render/layout/relational predicates that aren't tied to a single field.
- **Separate `{name}.tests.json` sidecar** — splits the contract across two files, breaking the single-schema-per-component philosophy.
- **Inline full predicate config per component** — maximises flexibility but kills reuse and bloats every schema.

## Consequences

Failure states are cheap to add or remove per component and never affect rendering. The cost is a second indirection (component → registry) and the need for a consumer that reads the array (see ADR 0002).

---
*Deliberation: [grill thread →](https://decisionrecords.localhost:8453/s/01kvm93z7bc8cj1cws1qtccmjt)*
