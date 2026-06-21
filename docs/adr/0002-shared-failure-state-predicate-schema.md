---
status: accepted
thread: 01kvm96znbm4yhacxgt0ttwchg
---

# The failure-state predicate library is a shared JSON schema

Predicates are defined once in a new shared schema `components/failure-states.schema.json` — a sibling of `styles/tokens.schema.json` — with each entry as a declarative record: `{ id, label, description, layer: input|render|layout, severity, params, gated }`. Components reference predicates by `id` from their `failure_states` array. The schema describes the predicate library the way `tokens.schema.json` describes the style panel; only a predicate's executable logic (e.g. pixel sampling) lives in code, parameterized by the schema. This honors the project's "everything defined via JSON schema" principle and lets the library interoperate with external auditors (per issue #4) without a code dependency.

## Considered Options

- **Hardcode predicates in PHP/JS with no schema** — simplest, but hides the library from tooling/agents and violates the schema-first philosophy.
- **Import/fork impeccable's `registry/antipatterns.mjs`** — issue #4 explicitly rejects a code dependency on impeccable's unstable detector internals; merge findings, never import.
- **Fold predicates into `tokens.schema.json`** — conflates design tokens with component test contracts.

---
*Deliberation: [grill thread →](https://decisionrecords.localhost:8453/s/01kvm93z7bc8cj1cws1qtccmjt)*
