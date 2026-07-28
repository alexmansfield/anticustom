/**
 * AntiRTToolbar — detachable chrome for AntiRT editors
 *
 * The toolbar core is mount-agnostic: it turns an editor's allowed features
 * into a button list and dispatches commands; a mount decides where and how
 * that list appears. V1 ships bubbleMount (Ghost-style floating pill). It
 * shows the full button set while a selection exists; on a collapsed caret
 * inside a styled run it shows a single remove-the-run chip instead. Future
 * mounts (attached-to-input, sidebar, bottom bar) implement the same
 * contract: { show(context), hide(), destroy() } where context is
 * { state, buttons, exec }. See ADR 0013.
 */
(function () {
    'use strict';

    // ─── Button building (shared by all mounts) ─────────────────────

    function buildButtons(editor, styleRegistry) {
        var features = editor.features;
        var buttons = [];

        if (features.marks.indexOf('bold') !== -1) {
            buttons.push({
                cmd: 'bold',
                kind: 'bold',
                text: 'B',
                title: 'Bold (⌘B)',
                isActive: function (state) { return state.marks.bold; },
            });
        }
        if (features.marks.indexOf('italic') !== -1) {
            buttons.push({
                cmd: 'italic',
                kind: 'italic',
                text: 'I',
                title: 'Italic (⌘I)',
                isActive: function (state) { return state.marks.italic; },
            });
        }

        if (features.styles.length && buttons.length) {
            buttons.push({ kind: 'divider' });
        }

        features.styles.forEach(function (name) {
            var entry = (styleRegistry || {})[name] || {};
            buttons.push({
                cmd: 'style',
                arg: name,
                kind: 'style',
                style: name,
                text: 'Aa',
                title: entry.label || name,
                isActive: function (state) { return state.activeStyle === name; },
            });
        });

        return buttons;
    }

    /**
     * Collapsed-caret chrome: a bare caret states no scope, so the bubble
     * offers only removal of the styled run the caret sits inside — never
     * buttons that would apply formatting to an unstated range. Clicking
     * the chip dispatches the style command, which the engine resolves as
     * whole-run removal (Ghost/Tiptap link pattern).
     */
    function buildCaretButtons(editor, styleRegistry, activeStyle) {
        if (!activeStyle || editor.features.styles.indexOf(activeStyle) === -1) return [];
        var entry = (styleRegistry || {})[activeStyle] || {};
        return [{
            cmd: 'style',
            arg: activeStyle,
            kind: 'style',
            style: activeStyle,
            remove: true,
            text: 'Aa',
            title: 'Remove ' + (entry.label || activeStyle),
            isActive: function () { return true; },
        }];
    }

    // ─── Bubble mount ────────────────────────────────────────────────

    function bubbleMount() {
        var element = null;
        var visible = false;
        var currentContext = null;

        function ensureElement() {
            if (element) return element;
            element = document.createElement('div');
            element.className = 'anti-rt-bubble';
            element.setAttribute('role', 'toolbar');
            element.setAttribute('aria-label', 'Text formatting');
            document.body.appendChild(element);
            return element;
        }

        function renderButtons(context) {
            var el = ensureElement();
            el.innerHTML = '';

            context.buttons.forEach(function (button) {
                if (button.kind === 'divider') {
                    var divider = document.createElement('span');
                    divider.className = 'anti-rt-bubble__divider';
                    el.appendChild(divider);
                    return;
                }

                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'anti-rt-bubble__button anti-rt-bubble__button--' + button.kind;
                btn.title = button.title;
                btn.setAttribute('aria-pressed', button.isActive(context.state) ? 'true' : 'false');

                if (button.kind === 'style') {
                    var chip = document.createElement('span');
                    chip.className = 'anti-rt-chip anti-rt-' + button.style;
                    chip.textContent = button.text;
                    btn.appendChild(chip);
                } else {
                    btn.textContent = button.text;
                }

                if (button.remove) {
                    btn.classList.add('anti-rt-bubble__button--remove');
                    var x = document.createElement('span');
                    x.className = 'anti-rt-bubble__remove-x';
                    x.setAttribute('aria-hidden', 'true');
                    x.textContent = '×';
                    btn.appendChild(x);
                }

                // preventDefault on mousedown keeps focus (and the selection)
                // in the editor, so clicking never collapses the selection
                btn.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                });
                btn.addEventListener('click', function () {
                    context.exec(button.cmd, button.arg);
                });

                el.appendChild(btn);
            });
        }

        // Anchored to the FIELD, not the selection: the bubble sits at the
        // field's top edge and stays put while the selection changes within it
        function position(rect) {
            var el = ensureElement();
            var height = el.offsetHeight || 40;
            var width = el.offsetWidth || 120;
            var gap = 8;

            var top = rect.top - height - gap;
            var below = top < 8;
            if (below) top = rect.bottom + gap;

            var left = rect.left + rect.width / 2 - width / 2;
            left = Math.max(8, Math.min(left, window.innerWidth - width - 8));

            el.style.top = top + 'px';
            el.style.left = left + 'px';
            el.classList.toggle('anti-rt-bubble--below', below);
        }

        function anchorRect() {
            return currentContext && currentContext.editor
                ? currentContext.editor.root.getBoundingClientRect()
                : null;
        }

        function reposition() {
            if (!visible) return;
            var rect = anchorRect();
            if (rect) position(rect);
        }

        function handleKeydown(e) {
            if (e.key === 'Escape' && visible) hide();
        }

        function show(context) {
            currentContext = context;
            renderButtons(context);
            var el = ensureElement();
            el.classList.add('is-visible');
            visible = true;
            reposition();

            window.addEventListener('scroll', reposition, true);
            window.addEventListener('resize', reposition);
            document.addEventListener('keydown', handleKeydown);
        }

        function hide() {
            if (!element || !visible) return;
            element.classList.remove('is-visible');
            visible = false;
            currentContext = null;
            window.removeEventListener('scroll', reposition, true);
            window.removeEventListener('resize', reposition);
            document.removeEventListener('keydown', handleKeydown);
        }

        return {
            show: show,
            hide: hide,
            destroy: function () {
                hide();
                if (element) {
                    element.remove();
                    element = null;
                }
            },
        };
    }

    // ─── Toolbar session ─────────────────────────────────────────────

    function create(options) {
        var mounts = (options && options.mounts) || [bubbleMount()];
        var styleRegistry = (options && options.styleRegistry) || window.__antiRTStyles || {};
        var activeEditor = null;

        function hideAll() {
            mounts.forEach(function (mount) { mount.hide(); });
        }

        function setActiveEditor(editor) {
            activeEditor = editor;
            if (!editor) hideAll();
        }

        function updateSelection(state) {
            if (!activeEditor || !state) {
                hideAll();
                return;
            }
            var buttons = state.collapsed
                ? buildCaretButtons(activeEditor, styleRegistry, state.activeStyle)
                : buildButtons(activeEditor, styleRegistry);
            if (!buttons.length) {
                hideAll();
                return;
            }
            var context = {
                state: state,
                buttons: buttons,
                editor: activeEditor,
                exec: function (cmd, arg) { activeEditor.exec(cmd, arg); },
            };
            mounts.forEach(function (mount) { mount.show(context); });
        }

        return {
            setActiveEditor: setActiveEditor,
            updateSelection: updateSelection,
            hide: hideAll,
            destroy: function () {
                mounts.forEach(function (mount) { mount.destroy(); });
                activeEditor = null;
            },
        };
    }

    /** True if an element belongs to toolbar chrome (for blur/relatedTarget checks). */
    function ownsElement(el) {
        return !!(el && el.closest && el.closest('.anti-rt-bubble'));
    }

    window.AntiRTToolbar = {
        create: create,
        bubbleMount: bubbleMount,
        buildButtons: buildButtons,
        buildCaretButtons: buildCaretButtons,
        ownsElement: ownsElement,
    };
})();
