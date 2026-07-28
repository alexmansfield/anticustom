/**
 * AntiRT — Anticustom rich text engine
 *
 * Hand-rolled contenteditable engine for graduated, schema-driven rich text.
 * Inline-only content model: text with bold/italic marks, named styled spans
 * (one style per range), and <br> when the field is multiline. The allowed
 * feature set comes from the field's schema (see anti_rt_features in PHP).
 *
 * Architecture: instead of mutating the DOM with Range surgery, every command
 * parses the field into a flat segment model (one entry per UTF-16 code unit,
 * each carrying its formatting), transforms the model, and re-renders
 * canonical DOM from it. Rendering from the model IS normalization: adjacent
 * identical marks merge, empty elements never exist, disallowed nodes are
 * dropped at parse time. Selection round-trips through flat text indexes.
 *
 * Public surface (the future engine-swap line — see ADR 0013):
 *   AntiRT.createEditor({root, features, onChange, onSelectionChange, ...})
 *     → { exec(cmd, arg), getHTML(), setHTML(html), destroy(), root }
 *   AntiRT.mount(container, {fieldConfig, onChange, onBlurCommit, ...})
 *     → { editors, destroy() }
 */
(function () {
    'use strict';

    var ZWSP = '​';

    // ─── Segment model ──────────────────────────────────────────────

    /**
     * Parse a DOM subtree into segments: {ch, bold, italic, style} per code
     * unit, or {br: true}. Unknown elements contribute their text only.
     */
    function parseNode(node, ctx, out) {
        var children = node.childNodes;
        for (var i = 0; i < children.length; i++) {
            var child = children[i];

            if (child.nodeType === Node.TEXT_NODE) {
                var data = child.data;
                for (var j = 0; j < data.length; j++) {
                    out.push({ ch: data[j], bold: ctx.bold, italic: ctx.italic, style: ctx.style });
                }
                continue;
            }

            if (child.nodeType !== Node.ELEMENT_NODE) continue;

            var tag = child.tagName;
            if (tag === 'BR') {
                out.push({ br: true });
                continue;
            }
            // Drop with content (mirrors the PHP sanitizer) — everything
            // else is transparent and contributes its text
            if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'TEMPLATE'
                || tag === 'IFRAME' || tag === 'OBJECT' || tag === 'NOSCRIPT') {
                continue;
            }

            var next = { bold: ctx.bold, italic: ctx.italic, style: ctx.style };
            if (tag === 'STRONG' || tag === 'B') next.bold = true;
            else if (tag === 'EM' || tag === 'I') next.italic = true;
            else if (tag === 'SPAN') {
                var m = (child.getAttribute('class') || '').match(/anti-rt-([a-z0-9-]+)/);
                if (m) next.style = m[1];
            }
            parseNode(child, next, out);
        }
        return out;
    }

    function parse(root) {
        return parseNode(root, { bold: false, italic: false, style: null }, []);
    }

    /** Strip formatting the feature set doesn't allow (paste/junk defense). */
    function applyFeatures(segments, features) {
        var allowBold = features.marks.indexOf('bold') !== -1;
        var allowItalic = features.marks.indexOf('italic') !== -1;
        var result = [];

        for (var i = 0; i < segments.length; i++) {
            var seg = segments[i];
            if (seg.br) {
                if (features.multiline) result.push(seg);
                continue;
            }
            result.push({
                ch: seg.ch,
                bold: allowBold && seg.bold,
                italic: allowItalic && seg.italic,
                style: features.styles.indexOf(seg.style) !== -1 ? seg.style : null,
            });
        }
        return result;
    }

    /**
     * Render segments to a DocumentFragment with canonical nesting:
     * span.anti-rt-* > strong > em. Adjacent identical formatting merges by
     * construction — this is the normalization pass.
     */
    function renderModel(segments) {
        var frag = document.createDocumentFragment();
        var i = 0;

        while (i < segments.length) {
            if (segments[i].br) {
                frag.appendChild(document.createElement('br'));
                i++;
                continue;
            }

            // Outermost grouping: style run
            var style = segments[i].style;
            var j = i;
            while (j < segments.length && !segments[j].br && segments[j].style === style) j++;

            var container = frag;
            if (style) {
                container = document.createElement('span');
                container.className = 'anti-rt-' + style;
                frag.appendChild(container);
            }

            // Within the style run: bold runs, then italic runs
            var k = i;
            while (k < j) {
                var bold = segments[k].bold;
                var m = k;
                while (m < j && segments[m].bold === bold) m++;

                var boldEl = container;
                if (bold) {
                    boldEl = document.createElement('strong');
                    container.appendChild(boldEl);
                }

                var p = k;
                while (p < m) {
                    var italic = segments[p].italic;
                    var q = p;
                    while (q < m && segments[q].italic === italic) q++;

                    var text = '';
                    for (var t = p; t < q; t++) text += segments[t].ch;
                    var textNode = document.createTextNode(text);

                    if (italic) {
                        var em = document.createElement('em');
                        em.appendChild(textNode);
                        boldEl.appendChild(em);
                    } else {
                        boldEl.appendChild(textNode);
                    }
                    p = q;
                }
                k = m;
            }
            i = j;
        }
        return frag;
    }

    /** Serialize segments to an HTML string (ZWSP stripped). */
    function serialize(segments) {
        var clean = [];
        for (var i = 0; i < segments.length; i++) {
            if (!segments[i].br && segments[i].ch === ZWSP) continue;
            clean.push(segments[i]);
        }
        if (!hasContent(clean)) return '';

        var div = document.createElement('div');
        div.appendChild(renderModel(clean));
        return div.innerHTML;
    }

    /** True if any segment is a real character (not just <br>s). */
    function hasContent(segments) {
        for (var i = 0; i < segments.length; i++) {
            if (!segments[i].br) return true;
        }
        return false;
    }

    // ─── Selection ↔ flat index mapping ─────────────────────────────

    function subtreeLength(node) {
        if (node.nodeType === Node.TEXT_NODE) return node.data.length;
        if (node.nodeType !== Node.ELEMENT_NODE) return 0;
        if (node.tagName === 'BR') return 1;
        var len = 0;
        for (var i = 0; i < node.childNodes.length; i++) len += subtreeLength(node.childNodes[i]);
        return len;
    }

    /** Flat model index of a DOM position (node, offset) inside root. */
    function toIndex(root, node, offset) {
        var index = 0;
        var found = false;

        (function walk(n) {
            if (found || n === node) {
                found = true;
                return;
            }
            if (n.nodeType === Node.TEXT_NODE) {
                index += n.data.length;
                return;
            }
            if (n.nodeType === Node.ELEMENT_NODE) {
                if (n.tagName === 'BR') {
                    index += 1;
                    return;
                }
                for (var i = 0; i < n.childNodes.length; i++) {
                    walk(n.childNodes[i]);
                    if (found) return;
                }
            }
        })(root);

        if (node.nodeType === Node.TEXT_NODE) {
            index += offset;
        } else {
            for (var i = 0; i < offset && i < node.childNodes.length; i++) {
                index += subtreeLength(node.childNodes[i]);
            }
        }
        return index;
    }

    /** DOM position for a flat model index inside root. */
    function fromIndex(root, index) {
        var result = null;

        (function walk(n) {
            if (result) return;
            if (n.nodeType === Node.TEXT_NODE) {
                if (index <= n.data.length) {
                    result = { node: n, offset: index };
                    return;
                }
                index -= n.data.length;
                return;
            }
            if (n.nodeType === Node.ELEMENT_NODE) {
                if (n.tagName === 'BR' && n !== root) {
                    if (index === 0) {
                        var parent = n.parentNode;
                        var at = Array.prototype.indexOf.call(parent.childNodes, n);
                        result = { node: parent, offset: at };
                        return;
                    }
                    index -= 1;
                    return;
                }
                for (var i = 0; i < n.childNodes.length; i++) {
                    walk(n.childNodes[i]);
                    if (result) return;
                }
            }
        })(root);

        return result || { node: root, offset: root.childNodes.length };
    }

    // ─── Editor ─────────────────────────────────────────────────────

    function createEditor(options) {
        var root = options.root;
        var features = {
            marks: (options.features && options.features.marks) || [],
            styles: (options.features && options.features.styles) || [],
            multiline: !!(options.features && options.features.multiline),
        };
        var onChange = options.onChange || function () {};
        var onSelectionChange = options.onSelectionChange || function () {};
        var onFocus = options.onFocus || function () {};
        var onBlur = options.onBlur || function () {};

        var undoStack = [];
        var redoStack = [];
        var lastTypingSnapshot = 0;
        var composing = false;
        var changeTimer = null;
        var destroyed = false;

        root.setAttribute('contenteditable', 'true');
        root.setAttribute('role', 'textbox');
        root.setAttribute('aria-multiline', features.multiline ? 'true' : 'false');
        root.setAttribute('spellcheck', 'false');

        // Editable regions can live inside link wrappers (e.g. a clickable
        // card renders as <a>). Links are draggable by default, so a text
        // drag would drag the link instead of selecting — and clicks would
        // navigate. Neutralize the ancestor link while the editor is attached.
        var ancestorLink = root.closest ? root.closest('a[href]') : null;
        var restoreLinkDraggable = null;

        function suppressLinkEvent(e) {
            e.preventDefault();
        }

        if (ancestorLink) {
            restoreLinkDraggable = ancestorLink.getAttribute('draggable');
            ancestorLink.setAttribute('draggable', 'false');
            ancestorLink.addEventListener('click', suppressLinkEvent);
            ancestorLink.addEventListener('dragstart', suppressLinkEvent);
        }

        // ── selection helpers ──

        function selectionIndexes() {
            var sel = window.getSelection();
            if (!sel.rangeCount) return null;
            var range = sel.getRangeAt(0);
            if (!root.contains(range.startContainer) || !root.contains(range.endContainer)) return null;
            var a = toIndex(root, range.startContainer, range.startOffset);
            var b = toIndex(root, range.endContainer, range.endOffset);
            return a <= b ? [a, b] : [b, a];
        }

        function setSelection(a, b) {
            var start = fromIndex(root, a);
            var end = a === b ? start : fromIndex(root, b);
            var range = document.createRange();
            range.setStart(start.node, start.offset);
            range.setEnd(end.node, end.offset);
            var sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
        }

        // ── rendering ──

        function rerender(segments, selStart, selEnd) {
            root.innerHTML = '';
            if (hasContent(segments)) {
                root.appendChild(renderModel(segments));
            }
            if (selStart !== null && selStart !== undefined) {
                var max = subtreeLength(root);
                setSelection(Math.min(selStart, max), Math.min(selEnd, max));
            }
        }

        function scheduleChange() {
            clearTimeout(changeTimer);
            changeTimer = setTimeout(emitChange, 150);
        }

        function emitChange() {
            clearTimeout(changeTimer);
            if (!destroyed) onChange(serialize(parse(root)));
        }

        /**
         * Re-derive the model from the DOM and re-render only if the DOM is
         * not already canonical (browsers can leave junk after deletions).
         * Skipped during IME composition — mutating mid-composition breaks it.
         */
        function normalizeDom() {
            if (composing) return;
            var segments = applyFeatures(parse(root), features);
            var canonical = document.createElement('div');
            canonical.appendChild(renderModel(segments));

            if (!hasContent(segments)) {
                if (root.innerHTML !== '') {
                    root.innerHTML = '';
                }
                return;
            }
            if (root.innerHTML !== canonical.innerHTML) {
                var sel = selectionIndexes();
                rerender(segments, sel ? sel[0] : null, sel ? sel[1] : null);
            }
        }

        // ── undo ──

        function pushSnapshot() {
            var sel = selectionIndexes() || [0, 0];
            undoStack.push({ html: root.innerHTML, sel: sel });
            if (undoStack.length > 100) undoStack.shift();
            redoStack = [];
        }

        function typingSnapshot() {
            var now = Date.now();
            if (now - lastTypingSnapshot > 400) {
                pushSnapshot();
                lastTypingSnapshot = now;
            }
        }

        function restoreSnapshot(snapshot) {
            root.innerHTML = snapshot.html;
            setSelection(snapshot.sel[0], snapshot.sel[1]);
            emitChange();
            notifySelection();
        }

        function undo() {
            if (!undoStack.length) return;
            var sel = selectionIndexes() || [0, 0];
            redoStack.push({ html: root.innerHTML, sel: sel });
            restoreSnapshot(undoStack.pop());
        }

        function redo() {
            if (!redoStack.length) return;
            var sel = selectionIndexes() || [0, 0];
            undoStack.push({ html: root.innerHTML, sel: sel });
            restoreSnapshot(redoStack.pop());
        }

        // ── selection state (for chrome) ──

        function selectionState() {
            var sel = window.getSelection();
            if (!sel.rangeCount) return null;
            var range = sel.getRangeAt(0);
            if (!root.contains(range.startContainer) || !root.contains(range.endContainer)) return null;

            var indexes = selectionIndexes();
            if (!indexes) return null;
            var a = indexes[0];
            var b = indexes[1];
            var segments = parse(root);

            var state = {
                collapsed: a === b,
                rect: range.getBoundingClientRect(),
                marks: { bold: false, italic: false },
                activeStyle: null,
            };

            // Collapsed caret takes formatting from the character before it
            var from = state.collapsed ? Math.max(0, a - 1) : a;
            var to = state.collapsed ? Math.max(1, a) : b;
            var chars = [];
            for (var i = from; i < to && i < segments.length; i++) {
                if (!segments[i].br) chars.push(segments[i]);
            }

            if (chars.length) {
                state.marks.bold = chars.every(function (s) { return s.bold; });
                state.marks.italic = chars.every(function (s) { return s.italic; });
                var style = chars[0].style;
                state.activeStyle = style && chars.every(function (s) { return s.style === style; }) ? style : null;
            }
            return state;
        }

        function notifySelection() {
            onSelectionChange(selectionState());
        }

        // ── commands ──

        function execMark(prop) {
            var indexes = selectionIndexes();
            if (!indexes) return;
            var a = indexes[0];
            var b = indexes[1];
            pushSnapshot();
            var segments = parse(root);

            if (a === b) {
                // Collapsed: seed a mark with a zero-width space so typing
                // continues inside it. Stripped at serialize/normalize time.
                var context = segments[Math.max(0, a - 1)] || { bold: false, italic: false, style: null };
                var seed = {
                    ch: ZWSP,
                    bold: context.br ? false : context.bold,
                    italic: context.br ? false : context.italic,
                    style: context.br ? null : context.style,
                };
                seed[prop] = !(state_before(segments, a, prop));
                segments.splice(a, 0, seed);
                rerender(segments, a + 1, a + 1);
            } else {
                var all = true;
                for (var i = a; i < b; i++) {
                    if (!segments[i].br && !segments[i][prop]) { all = false; break; }
                }
                for (var j = a; j < b; j++) {
                    if (!segments[j].br) segments[j][prop] = !all;
                }
                rerender(segments, a, b);
            }
            emitChange();
            notifySelection();
        }

        function state_before(segments, index, prop) {
            var prev = segments[index - 1];
            return !!(prev && !prev.br && prev[prop]);
        }

        /**
         * Bounds [start, end) of the contiguous run of `name`-styled segments
         * at a collapsed caret, or null if the caret isn't in one. The caret
         * belongs to the character before it (same rule as selectionState).
         * Runs stop at <br>: a styled range spanning lines renders as
         * separate spans, and each line is its own removable unit.
         */
        function styleRunBounds(segments, index, name) {
            var anchorAt = index > 0 ? index - 1 : 0;
            var anchor = segments[anchorAt];
            if (!anchor || anchor.br || anchor.style !== name) return null;
            var start = anchorAt;
            while (start > 0 && !segments[start - 1].br && segments[start - 1].style === name) start--;
            var end = anchorAt + 1;
            while (end < segments.length && !segments[end].br && segments[end].style === name) end++;
            return [start, end];
        }

        function execStyle(name) {
            if (features.styles.indexOf(name) === -1) return;
            var indexes = selectionIndexes();
            if (!indexes) return;
            var a = indexes[0];
            var b = indexes[1];
            var segments = parse(root);

            if (a === b) {
                // Collapsed caret states no scope, so a bare caret can only
                // REMOVE: expand to the whole styled run around it and clear
                // (Tiptap's extendMarkRange pattern). Applying always
                // requires an explicit selection.
                var bounds = styleRunBounds(segments, a, name);
                if (!bounds) return;
                pushSnapshot();
                for (var r = bounds[0]; r < bounds[1]; r++) segments[r].style = null;
                rerender(segments, a, a);
                emitChange();
                notifySelection();
                return;
            }

            pushSnapshot();

            var allActive = true;
            for (var i = a; i < b; i++) {
                if (!segments[i].br && segments[i].style !== name) { allActive = false; break; }
            }
            // One style per range: applying replaces any other, re-applying clears
            for (var j = a; j < b; j++) {
                if (!segments[j].br) segments[j].style = allActive ? null : name;
            }
            rerender(segments, a, b);
            emitChange();
            notifySelection();
        }

        function execClear() {
            var indexes = selectionIndexes();
            if (!indexes || indexes[0] === indexes[1]) return;
            var a = indexes[0];
            var b = indexes[1];
            pushSnapshot();
            var segments = parse(root);
            for (var i = a; i < b; i++) {
                if (!segments[i].br) {
                    segments[i].bold = false;
                    segments[i].italic = false;
                    segments[i].style = null;
                }
            }
            rerender(segments, a, b);
            emitChange();
            notifySelection();
        }

        function insertBreak() {
            if (!features.multiline) return;
            var indexes = selectionIndexes();
            if (!indexes) return;
            var a = indexes[0];
            var b = indexes[1];
            pushSnapshot();
            var segments = parse(root);
            segments.splice(a, b - a, { br: true });
            rerender(segments, a + 1, a + 1);
            emitChange();
        }

        function insertSegments(incoming) {
            var indexes = selectionIndexes();
            if (!indexes) return;
            var a = indexes[0];
            var b = indexes[1];
            pushSnapshot();
            var segments = parse(root);
            var args = [a, b - a].concat(incoming);
            Array.prototype.splice.apply(segments, args);
            var caret = a + incoming.length;
            rerender(applyFeatures(segments, features), caret, caret);
            emitChange();
        }

        function exec(cmd, arg) {
            if (cmd === 'bold' && features.marks.indexOf('bold') !== -1) execMark('bold');
            else if (cmd === 'italic' && features.marks.indexOf('italic') !== -1) execMark('italic');
            else if (cmd === 'style') execStyle(arg);
            else if (cmd === 'clear') execClear();
        }

        // ── event handlers ──

        function handleBeforeInput(e) {
            var type = e.inputType || '';

            if (type === 'insertText' || type === 'insertCompositionText'
                || type === 'insertReplacementText' || type.indexOf('delete') === 0) {
                typingSnapshot();
                return; // native handling, normalized after the input event
            }
            if (type === 'formatBold') {
                e.preventDefault();
                exec('bold');
                return;
            }
            if (type === 'formatItalic') {
                e.preventDefault();
                exec('italic');
                return;
            }
            if (type === 'insertParagraph' || type === 'insertLineBreak') {
                e.preventDefault();
                insertBreak();
                return;
            }
            if (type === 'historyUndo') {
                e.preventDefault();
                undo();
                return;
            }
            if (type === 'historyRedo') {
                e.preventDefault();
                redo();
                return;
            }
            // Everything else (underline, indent, fonts, drops…) never lands
            e.preventDefault();
        }

        function handleInput() {
            if (composing) return;
            normalizeDom();
            scheduleChange();
        }

        function handlePaste(e) {
            e.preventDefault();
            var html = e.clipboardData.getData('text/html');
            var text = e.clipboardData.getData('text/plain');
            var incoming = [];

            if (html && (features.marks.length || features.styles.length)) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                incoming = applyFeatures(parse(doc.body), features);
            } else if (text) {
                for (var i = 0; i < text.length; i++) {
                    var ch = text[i];
                    if (ch === '\n') {
                        if (features.multiline) incoming.push({ br: true });
                        else incoming.push({ ch: ' ', bold: false, italic: false, style: null });
                    } else if (ch !== '\r') {
                        incoming.push({ ch: ch, bold: false, italic: false, style: null });
                    }
                }
            }
            if (incoming.length) insertSegments(incoming);
        }

        function handleKeydown(e) {
            var mod = e.metaKey || e.ctrlKey;
            if (!mod) return;
            var key = e.key.toLowerCase();

            if (key === 'b') {
                e.preventDefault();
                exec('bold');
            } else if (key === 'i') {
                e.preventDefault();
                exec('italic');
            } else if (key === 'z') {
                e.preventDefault();
                if (e.shiftKey) redo(); else undo();
            } else if (key === 'y') {
                e.preventDefault();
                redo();
            }
        }

        function handleDrop(e) {
            e.preventDefault();
        }

        function handleCompositionStart() {
            composing = true;
        }

        function handleCompositionEnd() {
            composing = false;
            normalizeDom();
            scheduleChange();
        }

        function handleSelectionChange() {
            if (document.activeElement === root) notifySelection();
        }

        function handleFocus(e) {
            onFocus(e);
        }

        function handleBlur(e) {
            // Final cleanup: drop unused ZWSP seeds, emit the settled value
            normalizeDom();
            var segments = parse(root);
            var hasZwsp = segments.some(function (s) { return !s.br && s.ch === ZWSP; });
            if (hasZwsp) {
                rerender(segments.filter(function (s) { return s.br || s.ch !== ZWSP; }), null, null);
            }
            emitChange();
            onBlur(e);
        }

        root.addEventListener('beforeinput', handleBeforeInput);
        root.addEventListener('input', handleInput);
        root.addEventListener('paste', handlePaste);
        root.addEventListener('keydown', handleKeydown);
        root.addEventListener('drop', handleDrop);
        root.addEventListener('compositionstart', handleCompositionStart);
        root.addEventListener('compositionend', handleCompositionEnd);
        root.addEventListener('focus', handleFocus);
        root.addEventListener('blur', handleBlur);
        document.addEventListener('selectionchange', handleSelectionChange);

        return {
            root: root,
            features: features,
            exec: exec,
            getHTML: function () {
                return serialize(parse(root));
            },
            setHTML: function (html) {
                root.innerHTML = html;
                normalizeDom();
            },
            destroy: function () {
                destroyed = true;
                clearTimeout(changeTimer);
                if (ancestorLink) {
                    ancestorLink.removeEventListener('click', suppressLinkEvent);
                    ancestorLink.removeEventListener('dragstart', suppressLinkEvent);
                    if (restoreLinkDraggable === null) {
                        ancestorLink.removeAttribute('draggable');
                    } else {
                        ancestorLink.setAttribute('draggable', restoreLinkDraggable);
                    }
                }
                document.removeEventListener('selectionchange', handleSelectionChange);
                root.removeEventListener('beforeinput', handleBeforeInput);
                root.removeEventListener('input', handleInput);
                root.removeEventListener('paste', handlePaste);
                root.removeEventListener('keydown', handleKeydown);
                root.removeEventListener('drop', handleDrop);
                root.removeEventListener('compositionstart', handleCompositionStart);
                root.removeEventListener('compositionend', handleCompositionEnd);
                root.removeEventListener('focus', handleFocus);
                root.removeEventListener('blur', handleBlur);
                root.removeAttribute('contenteditable');
                root.removeAttribute('role');
                root.removeAttribute('aria-multiline');
                root.removeAttribute('spellcheck');
            },
        };
    }

    // ─── Feature resolution (mirrors PHP anti_rt_features) ──────────

    function featuresFromField(fieldDef, registry) {
        var options = (fieldDef && fieldDef.richtext) || {};
        var registered = Object.keys(registry || {});
        var styles = options.styles === true ? registered : (options.styles || []);

        return {
            marks: (options.marks || []).filter(function (m) {
                return m === 'bold' || m === 'italic';
            }),
            styles: styles.filter(function (s) {
                return registered.indexOf(s) !== -1;
            }),
            multiline: !!options.multiline,
        };
    }

    // ─── Mount: attach editors to server-rendered editable regions ──

    /**
     * Scan a container for [data-anti-editable] roots and attach an editor to
     * each [data-anti-field] region inside them.
     *
     * options.fieldConfig(component, field) → schema field definition
     * options.styleRegistry → {name: {label}} (window.__antiRTStyles)
     * options.onChange(field, html), options.onFocus/onBlur(field, event),
     * options.onBlurCommit(field), options.onSelectionChange(editor, state)
     */
    function mount(container, options) {
        var editors = [];
        var regions = container.querySelectorAll('[data-anti-editable] [data-anti-field]');

        Array.prototype.forEach.call(regions, function (region) {
            var component = region.closest('[data-anti-editable]').getAttribute('data-anti-editable');
            var field = region.getAttribute('data-anti-field');
            var fieldDef = options.fieldConfig ? options.fieldConfig(component, field) : null;

            var editor = createEditor({
                root: region,
                features: featuresFromField(fieldDef, options.styleRegistry),
                onChange: function (html) {
                    if (options.onChange) options.onChange(field, html);
                },
                onSelectionChange: function (state) {
                    if (options.onSelectionChange) options.onSelectionChange(editor, state);
                },
                onFocus: function (e) {
                    if (options.onFocus) options.onFocus(field, e);
                },
                onBlur: function (e) {
                    if (options.onBlur) options.onBlur(field, e);
                    if (options.onBlurCommit) options.onBlurCommit(field, e);
                },
            });
            editor.component = component;
            editor.field = field;
            editors.push(editor);
        });

        return {
            editors: editors,
            destroy: function () {
                editors.forEach(function (editor) { editor.destroy(); });
                editors.length = 0;
            },
        };
    }

    window.AntiRT = {
        createEditor: createEditor,
        mount: mount,
        featuresFromField: featuresFromField,
    };
})();
