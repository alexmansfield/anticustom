/**
 * Anticustom Component Panel
 *
 * Two-panel collapsible right sidebar mirroring panel.js exactly:
 * - Icon strip (right edge): 2 items — Components (list) + Properties (editor)
 * - Content panel (left of strip): shows list or editor based on active nav item
 * - Preview communicated via Alpine.store('componentPreview')
 */

// ============================================
// SVG Icons
// ============================================

const COMP_ICONS = {
    close: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>`,

    chevronLeft: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>`,

    components: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>`,

    properties: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line><line x1="1" y1="14" x2="7" y2="14"></line><line x1="9" y1="8" x2="15" y2="8"></line><line x1="17" y1="16" x2="23" y2="16"></line></svg>`,
};

// ============================================
// Alpine.js Registration
// ============================================

function registerComponentPanel() {
    Alpine.store('componentPreview', {
        html: '',
        css: '',
        php: '',
        componentName: '',
        sourceView: null,   // null = preview, 'php' = PHP source, 'css' = CSS source
        loading: false,
        previewColorway: localStorage.getItem('antiExplorer_previewColorway') || '',
        cssFiles: {},
        activeCssFile: null,
        activeComponentStyles: [],
        activeStyle: window.__antiActiveStyle || 'plato',

        get cssFileNames() {
            return Object.keys(this.cssFiles);
        },

        get activeCssContent() {
            return this.cssFiles[this.activeCssFile] || '';
        },

        get styleAvailable() {
            if (this.activeStyle === 'none') return true;
            if (!this.activeComponentStyles.length) return true;
            return this.activeComponentStyles.includes(this.activeStyle);
        },

        toggleSource(type) {
            this.sourceView = this.sourceView === type ? null : type;
        },
    });

    Alpine.data('componentPanel', () => ({
        components: window.__antiComponents || {},
        isOpen: true,
        settingsOpen: true,
        activeView: 'list',     // 'list' or 'editor'
        selected: null,
        search: '',
        props: {},
        childrenJson: '',
        _debounceTimer: null,
        activeStyle: window.__antiActiveStyle || 'plato',

        colorwayOptions: [
            { value: 'inherit', label: 'Inherit' },
            { value: 'default', label: 'Default' },
            { value: 'primary', label: 'Primary' },
        ],

        spaceOptions: [
            { value: 'xxs', label: 'XXS' },
            { value: 'xs', label: 'XS' },
            { value: 's', label: 'S' },
            { value: 'm', label: 'M' },
            { value: 'l', label: 'L' },
            { value: 'xl', label: 'XL' },
            { value: 'xxl', label: 'XXL' },
        ],

        init() {
            // Restore panel open/closed state
            const savedIsOpen = localStorage.getItem('antiExplorer_componentPanelOpen');
            if (savedIsOpen !== null) {
                this.isOpen = savedIsOpen !== 'false';
            }

            // Restore or auto-select component
            const savedSelected = localStorage.getItem('antiExplorer_selectedComponent');
            const names = Object.keys(this.components);

            if (savedSelected && this.components[savedSelected]) {
                this.selectComponent(savedSelected);
            } else if (names.length) {
                this.selectComponent(names[0]);
            }

            // Listen for external toggle/open requests
            window.addEventListener('antiToggleComponentPanel', () => {
                this.togglePanel();
            });
            window.addEventListener('antiOpenComponentPanel', () => {
                if (!this.isOpen) this.togglePanel();
            });

            // Listen for style changes from the nav dropdown
            window.addEventListener('antiStyleChange', (e) => {
                this.switchStyle(e.detail.style);
            });

            // Listen for colorway changes from the nav dropdown
            window.addEventListener('antiColorwayChange', (e) => {
                const store = Alpine.store('componentPreview');
                store.previewColorway = e.detail.colorway;
                localStorage.setItem('antiExplorer_previewColorway', e.detail.colorway);
            });
        },

        togglePanel() {
            this.isOpen = !this.isOpen;
            localStorage.setItem('antiExplorer_componentPanelOpen', this.isOpen.toString());
            window.dispatchEvent(new CustomEvent('anti-component-panel-toggled', {
                detail: { isOpen: this.isOpen }
            }));
        },

        async switchStyle(style) {
            this.activeStyle = style;
            Alpine.store('componentPreview').activeStyle = style;

            // Persist preference
            document.cookie = `antiExplorer_style=${encodeURIComponent(style)};path=/;max-age=31536000`;
            localStorage.setItem('antiExplorer_style', style);

            // Swap all component CSS in one request
            try {
                const resp = await fetch(`/shared/component-css.php?all=1&style=${encodeURIComponent(style)}`);
                if (resp.ok) {
                    const css = await resp.text();
                    const el = document.getElementById('anti-components');
                    if (el) el.textContent = css;
                }
            } catch (err) {
                // Silently fail — old styles remain visible
            }

            // Refresh per-component source view CSS
            this.fetchComponentCSS();
        },

        openView(viewId) {
            if (viewId === 'editor' && !this.selected) return;
            this.activeView = viewId;
            this.settingsOpen = true;
        },

        closeSettings() {
            this.activeView = 'list';
        },

        selectComponent(name) {
            this.selected = name;
            this.activeView = 'editor';
            this.settingsOpen = true;
            localStorage.setItem('antiExplorer_selectedComponent', name);
            document.cookie = `antiExplorer_selectedComponent=${encodeURIComponent(name)};path=/;max-age=31536000`;

            const comp = this.components[name];
            if (!comp) return;

            // Start with schema defaults, then overlay sample props
            const defaults = {};
            for (const field of comp.fields) {
                defaults[field.name] = field.default ?? '';
            }

            const sample = comp.sampleProps || {};
            this.props = { ...defaults, ...sample };

            // Initialize interface props
            if (comp.interface && comp.interface.length) {
                if (!('padding' in this.props)) this.props.padding = '';
                if (!('border_width' in this.props)) this.props.border_width = '';
                if (!('border_radius' in this.props)) this.props.border_radius = '';
                if (!('shadow' in this.props)) this.props.shadow = '';
            }

            // Handle children separately
            if (sample.children) {
                this.childrenJson = JSON.stringify(sample.children, null, 2);
                delete this.props.children;
            } else {
                this.childrenJson = '';
            }

            // Update store with this component's available styles
            const store = Alpine.store('componentPreview');
            store.activeComponentStyles = comp.styles || [];

            // Use server-rendered preview if available for this component
            const initial = window.__antiInitialPreview;
            if (initial && initial.component === name) {
                store.html = initial.html;
                store.css = initial.css;
                store.php = initial.php || '';
                store.componentName = initial.componentName;
                store.cssFiles = initial.cssFiles || {};
                store.activeCssFile = initial.activeCssFile || null;
                store.loading = false;
                delete window.__antiInitialPreview;
                return;
            }

            this.renderPreview();
            this.fetchComponentCSS();
            this.fetchTemplatePHP();
        },

        async fetchComponentCSS() {
            if (!this.selected) return;
            const store = Alpine.store('componentPreview');
            try {
                const resp = await fetch(`/shared/component-css.php?name=${encodeURIComponent(this.selected)}&style=${encodeURIComponent(this.activeStyle)}&split=1`);
                if (resp.ok) {
                    const files = await resp.json();
                    store.cssFiles = files;
                    // Combined CSS for backward compat
                    store.css = Object.values(files).join('\n');
                    // Default to theme file, fall back to _base.css
                    const styleKey = this.activeStyle + '.css';
                    store.activeCssFile = files[styleKey] !== undefined ? styleKey : Object.keys(files)[0] || null;
                } else {
                    store.cssFiles = {};
                    store.activeCssFile = null;
                    store.css = '/* Could not load CSS */';
                }
            } catch (err) {
                store.cssFiles = {};
                store.activeCssFile = null;
                store.css = `/* Fetch error: ${err.message} */`;
            }
        },

        async fetchTemplatePHP() {
            if (!this.selected) return;
            const store = Alpine.store('componentPreview');
            try {
                const resp = await fetch(`/shared/template-source.php?name=${encodeURIComponent(this.selected)}`);
                store.php = resp.ok ? await resp.text() : '/* Could not load template */';
            } catch (err) {
                store.php = `/* Fetch error: ${err.message} */`;
            }
        },

        get settingsTitle() {
            if (this.activeView === 'editor' && this.selected) {
                return this.components[this.selected]?.label || 'Properties';
            }
            return 'Components';
        },

        filteredComponents() {
            const q = this.search.toLowerCase().trim();
            const result = {};

            for (const [name, comp] of Object.entries(this.components)) {
                if (q && !comp.label.toLowerCase().includes(q) && !name.includes(q)) {
                    continue;
                }
                const cat = comp.category || 'other';
                if (!result[cat]) result[cat] = [];
                result[cat].push({ name, ...comp });
            }

            return result;
        },

        currentFields() {
            if (!this.selected) return [];
            const comp = this.components[this.selected];
            return comp ? comp.fields : [];
        },

        hasChildren() {
            if (!this.selected) return false;
            const comp = this.components[this.selected];
            return comp && comp.hasChildren;
        },

        getAvailableTokens(feature) {
            const defaults = window.ANTI_DEFAULTS || {};
            let saved = {};
            try { saved = JSON.parse(localStorage.getItem('antiExplorer_data') || '{}'); } catch(e) {}

            let source, scaleBased = false;
            if (feature === 'padding') {
                const merged = { ...defaults.spacing?.sizes };
                const over = saved.spacing?.sizes || {};
                for (const k in over) merged[k] = { ...merged[k], ...over[k] };
                source = merged;
                scaleBased = true;
            } else if (feature === 'border') {
                const merged = { ...defaults.borders?.sizes };
                const over = saved.borders?.sizes || {};
                for (const k in over) merged[k] = { ...merged[k], ...over[k] };
                source = merged;
            } else if (feature === 'radius') {
                const merged = { ...defaults.radius?.sizes };
                const over = saved.radius?.sizes || {};
                for (const k in over) merged[k] = { ...merged[k], ...over[k] };
                source = merged;
            } else if (feature === 'shadow') {
                const merged = { ...defaults.shadows };
                const over = saved.shadows || {};
                for (const k in over) merged[k] = { ...merged[k], ...over[k] };
                source = merged;
            } else {
                return [];
            }

            // Scale-based tokens always exist; non-scale require enabled
            return Object.entries(source || {})
                .filter(([, v]) => v && (scaleBased || v.enabled))
                .map(([key]) => ({ value: key, label: key.toUpperCase() }));
        },

        getInterfaceControls() {
            if (!this.selected) return [];
            const comp = this.components[this.selected];
            if (!comp || !comp.interface || !comp.interface.length) return [];

            const controls = [];
            for (const feature of comp.interface) {
                if (feature === 'padding') {
                    const opts = this.getAvailableTokens('padding');
                    if (opts.length) controls.push({ feature: 'padding', label: 'Padding', prop: 'padding', options: opts });
                } else if (feature === 'border') {
                    const widthOpts = this.getAvailableTokens('border');
                    if (widthOpts.length) controls.push({ feature: 'border', label: 'Border Width', prop: 'border_width', options: widthOpts });
                    const radiusOpts = this.getAvailableTokens('radius');
                    if (radiusOpts.length) controls.push({ feature: 'radius', label: 'Border Radius', prop: 'border_radius', options: radiusOpts });
                } else if (feature === 'shadow') {
                    const opts = this.getAvailableTokens('shadow');
                    if (opts.length) controls.push({ feature: 'shadow', label: 'Shadow', prop: 'shadow', options: opts });
                }
            }
            return controls;
        },

        applyInterfaceStyles() {
            const el = document.querySelector('.anti-playground__render [x-html] > :first-child');
            if (!el) return;

            const parts = [];
            if (this.props.padding) parts.push(`padding: var(--space-${this.props.padding})`);
            if (this.props.border_width) parts.push(`border: var(--border-${this.props.border_width}) solid var(--colorway-soft-contrast)`);
            if (this.props.border_radius) parts.push(`border-radius: var(--radius-${this.props.border_radius})`);
            if (this.props.shadow) parts.push(`box-shadow: var(--shadow-${this.props.shadow})`);
            el.style.cssText = parts.join('; ');
        },

        scheduleRender() {
            clearTimeout(this._debounceTimer);
            this._debounceTimer = setTimeout(() => this.renderPreview(), 300);
        },

        async renderPreview() {
            if (!this.selected) return;

            const store = Alpine.store('componentPreview');
            store.loading = true;
            store.componentName = this.components[this.selected]?.label || this.selected;

            const payload = { ...this.props };

            if (this.childrenJson.trim()) {
                try {
                    payload.children = JSON.parse(this.childrenJson);
                } catch (e) { /* Invalid JSON — skip children */ }
            }

            try {
                const resp = await fetch('/shared/render.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ component: this.selected, props: payload }),
                });

                store.html = resp.ok
                    ? await resp.text()
                    : `<p style="color: #ef4444;">Render error: ${resp.status}</p>`;
            } catch (err) {
                store.html = `<p style="color: #ef4444;">Fetch error: ${err.message}</p>`;
            }

            store.loading = false;
        },
    }));
}

// ============================================
// Panel HTML Template
// ============================================

const getComponentPanelHTML = () => `
    <div x-data="componentPanel"
         class="anti-component-panel anti-component-panel-container"
         :class="{ 'settings-open': settingsOpen, 'is-hidden': !isOpen }">

        <!-- Content Panel (left of icon strip) -->
        <aside class="anti-comp-settings">
            <!-- Header (mirrored: close on left, back on right) -->
            <header class="anti-comp-settings__header">
                <button class="anti-comp-settings__close"
                    @click="togglePanel()"
                    aria-label="Close panel"
                    title="Close panel">
                    ${COMP_ICONS.close}
                </button>
                <h2 class="anti-comp-settings__title" x-text="settingsTitle"></h2>
                <button class="anti-comp-settings__back"
                    x-show="activeView === 'editor'"
                    @click="closeSettings()"
                    aria-label="Back to components"
                    title="Back">
                    ${COMP_ICONS.chevronLeft}
                </button>
            </header>

            <!-- Search (list view only) -->
            <div class="anti-comp-settings__search" x-show="activeView === 'list'">
                <input type="text"
                       x-model="search"
                       placeholder="Search components...">
            </div>

            <!-- Content -->
            <main class="anti-comp-settings__content">

                <!-- Component List View -->
                <div x-show="activeView === 'list'" class="anti-comp-settings__panel">
                    <template x-for="(comps, category) in filteredComponents()" :key="category">
                        <div class="anti-comp-list__group">
                            <div class="anti-comp-list__category" x-text="category"></div>
                            <template x-for="comp in comps" :key="comp.name">
                                <button
                                    class="anti-comp-list__item"
                                    :class="{ 'is-active': selected === comp.name }"
                                    @click="selectComponent(comp.name)">
                                    <span x-text="comp.label"></span>
                                </button>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Properties View -->
                <div x-show="activeView === 'editor'" class="anti-comp-settings__panel">
                    <template x-if="!selected">
                        <div class="anti-comp-empty">Select a component</div>
                    </template>

                    <template x-for="field in currentFields()" :key="field.name">
                        <div class="anti-comp-field">

                            <!-- Text / URL input -->
                            <template x-if="field.type === 'text' || field.type === 'url'">
                                <div>
                                    <label class="anti-comp-field__label" x-text="field.label"></label>
                                    <input class="anti-comp-field__input"
                                           :type="field.type === 'url' ? 'url' : 'text'"
                                           x-model="props[field.name]"
                                           @input="scheduleRender()"
                                           :placeholder="field.description || ''">
                                </div>
                            </template>

                            <!-- Image URL -->
                            <template x-if="field.type === 'image'">
                                <div>
                                    <label class="anti-comp-field__label" x-text="field.label + ' (URL)'"></label>
                                    <input class="anti-comp-field__input"
                                           type="url"
                                           x-model="props[field.name]"
                                           @input="scheduleRender()"
                                           placeholder="Image URL">
                                </div>
                            </template>

                            <!-- Textarea -->
                            <template x-if="field.type === 'textarea'">
                                <div>
                                    <label class="anti-comp-field__label" x-text="field.label"></label>
                                    <textarea class="anti-comp-field__textarea"
                                              x-model="props[field.name]"
                                              @input="scheduleRender()"
                                              :placeholder="field.description || ''"></textarea>
                                </div>
                            </template>

                            <!-- Number -->
                            <template x-if="field.type === 'number'">
                                <div>
                                    <label class="anti-comp-field__label" x-text="field.label"></label>
                                    <input class="anti-comp-field__input"
                                           type="number"
                                           x-model="props[field.name]"
                                           @input="scheduleRender()">
                                </div>
                            </template>

                            <!-- Select -->
                            <template x-if="field.type === 'select'">
                                <div>
                                    <label class="anti-comp-field__label" x-text="field.label"></label>
                                    <select class="anti-comp-field__select"
                                            x-model="props[field.name]"
                                            @change="scheduleRender()">
                                        <template x-for="opt in field.options" :key="opt.value">
                                            <option :value="opt.value" x-text="opt.label"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>

                            <!-- Boolean -->
                            <template x-if="field.type === 'boolean'">
                                <label class="anti-comp-field__checkbox">
                                    <input type="checkbox"
                                           x-model="props[field.name]"
                                           @change="scheduleRender()">
                                    <span x-text="field.label"></span>
                                </label>
                            </template>

                            <!-- Colorway -->
                            <template x-if="field.type === 'colorway'">
                                <div>
                                    <label class="anti-comp-field__label" x-text="field.label"></label>
                                    <select class="anti-comp-field__select"
                                            x-model="props[field.name]"
                                            @change="scheduleRender()">
                                        <template x-for="opt in colorwayOptions" :key="opt.value">
                                            <option :value="opt.value" x-text="opt.label"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>

                            <!-- Buttongroup (spaces) -->
                            <template x-if="field.type === 'buttongroup'">
                                <div>
                                    <label class="anti-comp-field__label" x-text="field.label"></label>
                                    <select class="anti-comp-field__select"
                                            x-model="props[field.name]"
                                            @change="scheduleRender()">
                                        <template x-for="opt in spaceOptions" :key="opt.value">
                                            <option :value="opt.value" x-text="opt.label"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>

                        </div>
                    </template>

                    <!-- Interface controls -->
                    <template x-if="getInterfaceControls().length > 0">
                        <div class="anti-comp-interface">
                            <div class="anti-comp-interface__heading">Interface</div>
                            <template x-for="ctrl in getInterfaceControls()" :key="ctrl.prop">
                                <div class="anti-comp-field">
                                    <label class="anti-comp-field__label" x-text="ctrl.label"></label>

                                    <!-- Single option: checkbox toggle -->
                                    <template x-if="ctrl.options.length === 1">
                                        <label class="anti-comp-field__checkbox">
                                            <input type="checkbox"
                                                   :checked="props[ctrl.prop] === ctrl.options[0].value"
                                                   @change="props[ctrl.prop] = $event.target.checked ? ctrl.options[0].value : ''; applyInterfaceStyles(); scheduleRender()">
                                            <span x-text="ctrl.options[0].label"></span>
                                        </label>
                                    </template>

                                    <!-- Multiple options: button group -->
                                    <template x-if="ctrl.options.length > 1">
                                        <div class="anti-select__btngroup">
                                            <template x-for="opt in ctrl.options" :key="opt.value">
                                                <button class="anti-select__btn"
                                                        :class="{ 'is-active': props[ctrl.prop] === opt.value }"
                                                        @click="props[ctrl.prop] = props[ctrl.prop] === opt.value ? '' : opt.value; applyInterfaceStyles(); scheduleRender()"
                                                        x-text="opt.label">
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- Children JSON editor -->
                    <template x-if="hasChildren()">
                        <div class="anti-comp-field">
                            <label class="anti-comp-field__label">Children (JSON)</label>
                            <textarea class="anti-comp-field__textarea anti-comp-field__textarea--json"
                                      x-model="childrenJson"
                                      @input="scheduleRender()"></textarea>
                        </div>
                    </template>
                </div>

            </main>
        </aside>

        <!-- Icon Strip (right edge — mirrors left panel's nav) -->
        <nav class="anti-comp-nav">
            <div class="anti-comp-nav__header">
                <div class="anti-comp-nav__logo">
                    ${COMP_ICONS.components}
                </div>
                <h2 class="anti-comp-nav__title">Components</h2>
                <button class="anti-comp-nav__close"
                    @click="togglePanel()"
                    aria-label="Close panel"
                    title="Close panel">
                    ${COMP_ICONS.close}
                </button>
            </div>

            <div class="anti-comp-nav__menu">
                <button
                    class="anti-comp-nav__item"
                    :class="{ 'is-active': activeView === 'list' }"
                    @click="openView('list')"
                    title="Components">
                    <span class="anti-comp-nav__item-icon">${COMP_ICONS.components}</span>
                    <span class="anti-comp-nav__item-label">Components</span>
                </button>
                <button
                    class="anti-comp-nav__item"
                    :class="{ 'is-active': activeView === 'editor', 'is-disabled': !selected }"
                    @click="openView('editor')"
                    title="Properties">
                    <span class="anti-comp-nav__item-icon">${COMP_ICONS.properties}</span>
                    <span class="anti-comp-nav__item-label">Properties</span>
                </button>
            </div>
        </nav>
    </div>
`;

// ============================================
// Initialization
// ============================================

function initComponentPanel() {
    const html = getComponentPanelHTML();

    // Insert before .anti-explorer so sibling selectors (~) work for margin-right
    const explorer = document.querySelector('.anti-explorer');
    if (explorer) {
        explorer.insertAdjacentHTML('beforebegin', html);
    } else {
        document.body.insertAdjacentHTML('beforeend', html);
    }

    const panelElement = document.querySelector('.anti-component-panel-container');
    if (window.Alpine && panelElement) {
        Alpine.initTree(panelElement);
    }
}

function bootComponentPanel() {
    registerComponentPanel();
    initComponentPanel();
}

// Handle different Alpine loading scenarios
if (window.Alpine) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootComponentPanel);
    } else {
        bootComponentPanel();
    }
} else {
    document.addEventListener('alpine:init', () => {
        registerComponentPanel();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initComponentPanel);
    } else {
        initComponentPanel();
    }
}
