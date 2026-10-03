---
status: accepted
---

# Component breakpoints are container queries; the host provides the container

Component CSS responds to the width of its **query container**, not the viewport: every breakpoint is written `@container (max-width: N)` / `@container (min-width: N)` instead of `@media (…)`. A host that renders components gives some ancestor `container-type: inline-size`; on an ordinary page that is `body`, whose content box is the page width.

**Why.** A viewport media query only tells a component how wide the *window* is, but the space a component actually gets is often narrower. The motivating case is the AntiPress editor (Nonboxed/antipress#59): it opens side panels by padding `<body>`, so the page canvas shrinks while the window doesn't, and a 688px canvas rendered desktop multi-column layouts. The explorer has the same problem in its preview pane. With container queries the canvas, the preview pane and a narrow sidebar all lay out by their own width, and on a normal page, where the container is the full-width `body`, the result is what the media queries produced.

**The host contract.** anticustom can't pick the container itself, because only the host knows what wraps its components. So each host declares one. If it doesn't, `@container` rules never match and components stay in their default (wide) layout instead of breaking. The explorer makes `.anti-playground__preview` the container. AntiPress makes `body` the container on every page it renders. Checked in Chrome: `container-type: inline-size` doesn't make the element a containing block for `position: fixed` descendants, so `body` can be the container without breaking fixed headers, drawers or editor chrome.

## Considered Options

- **Keep `@media`, let hosts cope.** The AntiPress editor would have to iframe its canvas (a large change touching selection, live re-render and every panel) or rewrite the CSS it delivers in edit mode, a second pipeline that drifts from the front end.
- **Ship `body { container-type: inline-size }` in anticustom's own CSS.** It would make components work with no host step, but anticustom would be setting a style on an element it doesn't own, and hosts that render components inside a narrower wrapper would still need their own container.
- **Named containers (`@container anti (…)`).** They'd guard against matching an unrelated container someone else declared, at the cost of every host adopting the name. Not needed today, and easy to add later.

## Consequences

The twelve existing breakpoint rules (card, container, faq, hero, stats, testimonial and their named styles) move to `@container` with unchanged widths. New components use `@container` for breakpoints (components/GUIDE.md). Container widths exclude the scrollbar where media queries included it, so on systems with classic (non-overlay) scrollbars a breakpoint triggers about one scrollbar width later. Third-party CSS around the components still uses viewport media queries; only anticustom's own components follow the container.
