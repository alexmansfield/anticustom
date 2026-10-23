---
status: accepted
amends: 0014
---

# The html field type ships with a fixed, widen-only allowlist

ADR 0014 reserved `html` and deferred its allowlist until the richtext editor decision (#22). That deferral made sense for a *configurable* or *wide* allowlist, but a fixed one doesn't depend on any editor. `html` is the raw-source corner of the palette, so its control is a plain source textarea, and the sanitizer already handled blocks, lists and links. Meanwhile agents writing content downstream (AntiPress via MCP, #71) produce HTML natively and need body copy with real paragraphs now. This ships `html` ahead of #22.

## Decisions

- **`"type": "html"` means one fixed set, with no options.** Allowed: `p`, `h2`–`h6`, `ul`/`ol`/`li`, `strong`/`em` (`b`/`i` normalise to these), `br`, and `a` with `href`, `rel` and `target`. Everything else is handled the way the shared sanitizer already handles it: `script`/`style`/`iframe`/`object`/`embed`/`template`/`noscript` are dropped with their content, any other tag is unwrapped so its text survives, and every attribute not listed is stripped. There are no named styles (`span.anti-style-*` stays leantext's opt-in).
- **The allowlist may only widen.** Whatever richtext's allowlist becomes will include these tags, so `html` can later grow to it or gain options without changing how any stored value renders.
- **Headings are `h2`–`h6`; `h1` is unwrapped.** The page or hero title owns the single `h1` (ADR 0028). Whether body-copy heading levels are correct is the author's job, caught by the `heading-order` failure state, not by the sanitizer.
- **No structural policing.** A stray `<li>` outside a list survives as written. The sanitizer filters tags and attributes; it doesn't rewrite structure.
- **Links keep `rel` and `target` as authored.** The sanitizer used to drop both and force `rel="noopener"` on every link. That injection is removed: browsers already apply `noopener` to `target="_blank"`, and values like `nofollow`/`sponsored`/`ugc` are real author intent. `href` is still scheme-checked by `anti_field_safe_href()`. The machinery is shared, so this applies to any type that resolves `links`.
- **Plain text stays plain.** A value with no tags renders as one escaped run; blank lines don't become paragraphs (that's #21's question for `textarea`).
- **A bare `<` in prose is text.** Following HTML5, a `<` not followed by a letter, `/`, `!` or `?` is escaped before parsing. Before this, libxml swallowed the rest of the value (`a < b` → `a `). The fix is in the shared machinery, so leantext gets it too.

## Considered Options

- **Wait for #22 (status quo)**: rejected. The editor question doesn't touch a raw-source type, and agent authors are blocked on it today.
- **#21 (textarea → paragraphs) as the only near-term answer**: not rejected, but it doesn't help agents, who write HTML rather than blank-line text. It still matters for human editors.
- **Strip `rel`/`target` and inject `noopener`**: rejected. It was a guard against reverse tabnabbing that browsers now handle themselves, and it threw away legitimate SEO intent. A raw HTML field should stay as close to the HTML the author wrote as safety allows.
- **Unwrap `li` outside `ul`/`ol`**: rejected. It only takes a few lines, but it rewrites author structure, and when a disallowed wrapper sits between the list and the item it misfires.

## Consequences

`anti_field_features()` gains a fixed `html` branch, and `anti_field_html()` dispatches `html` to the sanitizer. The explorer renders `html` fields with the existing monospace source textarea. Components that adopt an `html` body field must style the bare elements it emits (`p`, headings, lists) in their own scope, like `.anti-faq__answer p`. Nothing provides prose styles for field content yet. Map #18's "html field spec" fog is resolved for v1; the richtext allowlist and editor stay with #22.
