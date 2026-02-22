/**
 * Anticustom Component Playground
 *
 * Alpine.js component providing:
 * 1. Searchable component list
 * 2. Schema-driven prop editor
 * 3. Live AJAX preview with debounce
 */

document.addEventListener('alpine:init', () => {
    Alpine.data('antiPlayground', () => ({
        components: window.__antiComponents || {},
        search: '',
        selected: null,
        props: {},
        childrenJson: '',
        previewHtml: '',
        loading: false,
        showSource: false,
        _debounceTimer: null,

        init() {
            // Select first component on load
            const names = Object.keys(this.components);
            if (names.length) {
                this.selectComponent(names[0]);
            }
        },

        /**
         * Components filtered by search query, grouped by category.
         */
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

        /**
         * Select a component: populate props from sample data, trigger render.
         */
        selectComponent(name) {
            this.selected = name;
            const comp = this.components[name];
            if (!comp) return;

            // Start with schema defaults, then overlay sample props
            const defaults = {};
            for (const field of comp.fields) {
                defaults[field.name] = field.default ?? '';
            }

            const sample = comp.sampleProps || {};
            this.props = { ...defaults, ...sample };

            // Handle children separately
            if (sample.children) {
                this.childrenJson = JSON.stringify(sample.children, null, 2);
                delete this.props.children;
            } else {
                this.childrenJson = '';
            }

            this.renderPreview();
        },

        /**
         * Fields for the currently selected component.
         */
        currentFields() {
            if (!this.selected) return [];
            const comp = this.components[this.selected];
            return comp ? comp.fields : [];
        },

        /**
         * Whether the selected component has children.
         */
        hasChildren() {
            if (!this.selected) return false;
            const comp = this.components[this.selected];
            return comp && comp.hasChildren;
        },

        /**
         * Debounced render — called on any prop change.
         */
        scheduleRender() {
            clearTimeout(this._debounceTimer);
            this._debounceTimer = setTimeout(() => this.renderPreview(), 300);
        },

        /**
         * POST to /shared/render.php and update the preview.
         */
        async renderPreview() {
            if (!this.selected) return;

            this.loading = true;

            // Build props payload
            const payload = { ...this.props };

            // Parse children JSON if present
            if (this.childrenJson.trim()) {
                try {
                    payload.children = JSON.parse(this.childrenJson);
                } catch (e) {
                    // Invalid JSON — skip children
                }
            }

            try {
                const resp = await fetch('/shared/render.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        component: this.selected,
                        props: payload,
                    }),
                });

                if (resp.ok) {
                    this.previewHtml = await resp.text();
                } else {
                    this.previewHtml = `<p style="color: #ef4444;">Render error: ${resp.status}</p>`;
                }
            } catch (err) {
                this.previewHtml = `<p style="color: #ef4444;">Fetch error: ${err.message}</p>`;
            }

            this.loading = false;
        },

        /**
         * Colorway options for colorway fields.
         */
        colorwayOptions: [
            { value: 'inherit', label: 'Inherit' },
            { value: 'default', label: 'Default' },
            { value: 'primary', label: 'Primary' },
        ],

        /**
         * Space options for buttongroup fields with optionsFrom=spaces.
         */
        spaceOptions: [
            { value: 'xxs', label: 'XXS' },
            { value: 'xs', label: 'XS' },
            { value: 's', label: 'S' },
            { value: 'm', label: 'M' },
            { value: 'l', label: 'L' },
            { value: 'xl', label: 'XL' },
            { value: 'xxl', label: 'XXL' },
        ],
    }));
});
