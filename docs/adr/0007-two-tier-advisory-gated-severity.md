---
status: accepted
thread: 01kvm96znhte0tgpe0awdydddx
---

# Failure-state severity has two non-blurring tiers: advisory and gated

Every failure state carries both an advisory `warning` threshold (dismissible, tuned for human editors) and a stricter `gated` threshold (blocking, tuned for AI agents and the publish gate), with the gate materially stricter than the warning. The two tiers must never blur: advisory findings inform, gated findings block. This is the boundary issue #4 settled — false positives are lethal, so humans need gentle guidance while AI agents need a hard, machine-checkable floor they provably cannot ship past.

## Consequences

The predicate registry expresses both thresholds per failure state. Consumers select the tier by audience: the editor UI shows warnings; the publish/AI gate enforces the gated tier. The cost is two thresholds to tune per predicate, and the discipline to never let an advisory finding silently become blocking.

---
*Deliberation: [grill thread →](https://decisionrecords.localhost:8453/s/01kvm93z7bc8cj1cws1qtccmjt)*
