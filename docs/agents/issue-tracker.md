# Issue tracker

This repo tracks issues in **GitHub Issues** (`alexmansfield/anticustom`), driven via the `gh` CLI.

Skills that read/write issues (`wayfinder`, `to-tickets`, `triage`, `to-spec`, `qa`) use `gh issue …`.

## Triage labels

Defaults (canonical five roles): `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`.

## Domain docs

Single-context: one `CONTEXT.md` and `docs/adr/` at the repo root. ADRs are the canonical decision records; grilling deliberations are stamped into each ADR's `thread:` frontmatter + footer.

## Wayfinding operations (how this repo expresses a Wayfinder map)

Wayfinder's tracker-specific pieces map to GitHub as follows:

- **Map** — one issue labelled `wayfinder:map`, plus a per-effort **scope label** (e.g. `wayfinder:richtext`) carried by the map and all its tickets. The map body holds Destination / Notes / Decisions-so-far / Not-yet-specified / Out-of-scope.
- **Tickets** — issues carrying the effort's scope label and a `wayfinder:<type>` label (`grilling`, `prototype`, `research`, `task`). Each links its parent map with `Part of #<map>` in the body.
- **Claim** — assign the ticket to the driving dev before work (`gh issue edit <n> --add-assignee @me`).
- **Blocking** — `gh` 2.93 predates convenient native issue-dependency commands, so blocking uses a body convention: a `Blocked by #<n>` line in the ticket body. A ticket is unblocked when every issue it lists is closed.
- **Frontier query** — open, unblocked, unassigned tickets for an effort:
  `gh issue list --label wayfinder:<scope> --state open --search "no:assignee"` then drop any whose `Blocked by` issues are still open.
- **Resolution** — post the answer as a comment, close the issue, and add a one-line gist + link to the map's Decisions-so-far.
