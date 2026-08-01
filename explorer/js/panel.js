/**
 * Anticustom Style Editor Panel — Schema-Driven Engine
 *
 * Reads tokens.schema.json + defaults.json to auto-build the editor UI.
 * No hardcoded categories, defaults, scales, or template blocks —
 * everything is derived from the schema at runtime.
 *
 * Settings structure matches defaults.json directly.
 * localStorage persists with a version key for migration.
 */

// ============================================
// Color Conversion Utilities (mirrors generate.php)
// ============================================

function hexToHsl(hex) {
    hex = hex.replace('#', '');
    const r = parseInt(hex.substring(0, 2), 16) / 255;
    const g = parseInt(hex.substring(2, 4), 16) / 255;
    const b = parseInt(hex.substring(4, 6), 16) / 255;

    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    let h, s, l = (max + min) / 2;

    if (max === min) {
        return { h: 0, s: 0, l: l * 100 };
    }

    const d = max - min;
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);

    if (max === r) {
        h = ((g - b) / d + (g < b ? 6 : 0)) / 6;
    } else if (max === g) {
        h = ((b - r) / d + 2) / 6;
    } else {
        h = ((r - g) / d + 4) / 6;
    }

    return { h: h * 360, s: s * 100, l: l * 100 };
}

function hueToRgb(p, q, t) {
    if (t < 0) t += 1;
    if (t > 1) t -= 1;
    if (t < 1/6) return p + (q - p) * 6 * t;
    if (t < 1/2) return q;
    if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
    return p;
}

function hslToHex(h, s, l) {
    h /= 360; s /= 100; l /= 100;
    let r, g, b;

    if (s === 0) {
        r = g = b = l;
    } else {
        const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
        const p = 2 * l - q;
        r = hueToRgb(p, q, h + 1/3);
        g = hueToRgb(p, q, h);
        b = hueToRgb(p, q, h - 1/3);
    }

    const toHex = v => Math.round(v * 255).toString(16).padStart(2, '0');
    return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
}

// ============================================
// UI-Only Icons (panel chrome)
// ============================================

const UI_ICONS = {
    close: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
    </svg>`,

    chevronLeft: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="15 18 9 12 15 6"></polyline>
    </svg>`,

    palette: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="13.5" cy="6.5" r="1.5" fill="currentColor" stroke="none"></circle>
        <circle cx="17.5" cy="10.5" r="1.5" fill="currentColor" stroke="none"></circle>
        <circle cx="8.5" cy="7.5" r="1.5" fill="currentColor" stroke="none"></circle>
        <circle cx="6.5" cy="12.5" r="1.5" fill="currentColor" stroke="none"></circle>
        <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"></path>
    </svg>`,

    export: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
        <polyline points="17 8 12 3 7 8"></polyline>
        <line x1="12" y1="3" x2="12" y2="15"></line>
    </svg>`,

    panelLeft: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="3" width="18" height="18" rx="2"></rect>
        <path d="M9 3v18"></path>
    </svg>`
};

// ============================================
// Settings version — bump when structure changes
// ============================================
const SETTINGS_VERSION = 3;

// ============================================
// Alpine.js Component Registration
// ============================================

function registerStylePanel() {
    Alpine.data('stylePanel', () => ({
        // Panel state
        isOpen: true,
        settingsOpen: false,
        activeCategory: null,
        activeTab: null,

        // Data state
        notificationVisible: false,
        notificationText: '',
        notificationType: 'success',

        // Colorway picker state
        colorwayDropdownId: null,
        colorwayCustomMode: null,

        // Colorway management state
        addingColorway: false,
        newColorwayName: '',

        // Schema-driven data
        schema: null,
        settings: {},
        defaultSettings: {},

        // ============================================
        // Initialization
        // ============================================

        init() {
            // Read schema + defaults from inlined globals (set by PHP)
            this.schema = window.ANTI_SCHEMA;
            const rawDefaults = window.ANTI_DEFAULTS;

            if (!this.schema || !rawDefaults) {
                console.error('Schema or defaults not found — ensure ANTI_SCHEMA and ANTI_DEFAULTS are set');
                return;
            }

            // Build settings from defaults (the open-set token model — every
            // step in `sizes` is a member; scale families carry `default`/`ratio`).
            this.settings = JSON.parse(JSON.stringify(rawDefaults));
            this.defaultSettings = JSON.parse(JSON.stringify(this.settings));

            // Load saved settings from localStorage
            const saved = localStorage.getItem('antiExplorer_data');
            if (saved) {
                try {
                    const parsed = JSON.parse(saved);
                    if (parsed._version === SETTINGS_VERSION) {
                        this.settings = this.deepMerge(this.settings, parsed);
                    }
                    // Old version or missing: discard, start from defaults
                } catch (e) {
                    console.error('Failed to parse saved settings');
                }
            }

            // Event listeners
            window.addEventListener('antiTogglePanel', () => this.togglePanel());
            window.addEventListener('antiOpenPanel', () => {
                if (!this.isOpen) this.togglePanel();
            });

            // Restore panel state
            const savedIsOpen = localStorage.getItem('antiExplorer_isOpen');
            if (savedIsOpen !== null) {
                this.isOpen = savedIsOpen === 'true';
            }

            const savedCategory = localStorage.getItem('antiExplorer_category');
            const savedTab = localStorage.getItem('antiExplorer_tab');
            if (savedCategory) {
                this.openCategory(savedCategory);
                const panel = this.getPanel(savedCategory);
                if (savedTab && panel?.tabs?.some(t => t.id === savedTab)) this.activeTab = savedTab;
            }

            this.applyAllSettings();
            window.__antiSettings = this.settings;
            window.dispatchEvent(new CustomEvent('anti-settings-changed'));

            // Close colorway dropdown on outside click
            document.addEventListener('click', (e) => {
                if (this.colorwayDropdownId && !e.target.closest('.anti-colorway-picker') && !e.target.closest('.clr-picker')) {
                    this.closeColorwayDropdown();
                }
            });

            // Cross-panel notification support (e.g. from component panel)
            window.addEventListener('anti-show-notification', (e) => {
                this.showNotification(e.detail.message, e.detail.type || 'error');
            });
        },

        // ============================================
        // Path Helpers
        // ============================================

        getByPath(path) {
            if (!path) return this.settings;
            return path.split('.').reduce((obj, key) => obj?.[key], this.settings);
        },

        getByPathFrom(root, path) {
            if (!path) return root;
            return path.split('.').reduce((obj, key) => obj?.[key], root);
        },

        setByPath(path, value) {
            const parts = path.split('.');
            const last = parts.pop();
            const parent = parts.reduce((obj, key) => obj?.[key], this.settings);
            if (parent) parent[last] = value;
        },

        getParentKey(settingsKey) {
            return settingsKey.split('.').slice(0, -1).join('.');
        },

        deepMerge(target, source) {
            const result = { ...target };
            for (const key of Object.keys(source)) {
                if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key])) {
                    result[key] = this.deepMerge(target[key] || {}, source[key]);
                } else {
                    result[key] = source[key];
                }
            }
            return result;
        },

        // ============================================
        // Navigation
        // ============================================

        togglePanel() {
            this.isOpen = !this.isOpen;
            localStorage.setItem('antiExplorer_isOpen', this.isOpen.toString());
            window.dispatchEvent(new CustomEvent('anti-panel-toggled', { detail: { isOpen: this.isOpen, settingsOpen: this.settingsOpen } }));
        },

        openCategory(categoryId) {
            const panel = this.getPanel(categoryId);
            if (!panel) return;

            this.activeCategory = categoryId;
            this.settingsOpen = true;

            if (panel.tabs?.length > 0) {
                this.activeTab = panel.tabs[0].id;
            } else {
                this.activeTab = null;
            }

            localStorage.setItem('antiExplorer_category', categoryId);
        },

        closeSettings() {
            this.settingsOpen = false;
            this.activeCategory = null;
            this.activeTab = null;
            localStorage.removeItem('antiExplorer_category');
            localStorage.removeItem('antiExplorer_tab');
        },

        switchTab(tabId) {
            this.activeTab = tabId;
            localStorage.setItem('antiExplorer_tab', tabId);
        },

        // ============================================
        // Schema Helpers
        // ============================================

        getPanel(panelId) {
            return this.schema?.panels?.find(p => p.id === panelId);
        },

        getCurrentPanel() {
            return this.getPanel(this.activeCategory);
        },

        get currentTabs() {
            return this.getCurrentPanel()?.tabs || [];
        },

        /** Sections to render for the current active category/tab */
        get currentSections() {
            if (!this.schema || !this.activeCategory) return [];
            const panel = this.getCurrentPanel();
            if (!panel) return [];

            if (panel.tabs) {
                const tab = panel.tabs.find(t => t.id === this.activeTab);
                if (!tab) return [];
                return tab.sections || [tab];
            }
            return panel.sections || [];
        },

        /** Reset button for the current active tab (or panel if no tabs) */
        get currentResetButton() {
            const panel = this.getCurrentPanel();
            if (!panel) return null;
            if (panel.tabs) {
                const tab = panel.tabs.find(t => t.id === this.activeTab);
                return tab?.resetButton || null;
            }
            return panel.resetButton || null;
        },

        /**
         * Section render kind. Size families declare `type`
         * (scale | pickone | composite); color sections are detected by shape.
         */
        getSectionType(section) {
            if (section.type) return section.type;
            if (section.properties) return 'colorways';
            if (section.colors) return 'palette';
            return 'unknown';
        },

        getItemLabel(key) {
            if (key.length <= 3) return key.toUpperCase();
            return key.charAt(0).toUpperCase() + key.slice(1);
        },

        presetValue(opt) {
            return typeof opt === 'object' ? opt.value : opt;
        },

        presetLabel(opt) {
            return typeof opt === 'object' ? opt.label : String(opt);
        },

        // ============================================
        // Size-family helpers (open sets, key-identity, anchor-as-origin)
        // ============================================

        /** The settings object for a size family (its { default, ratio?, sizes }). */
        familyOf(section) {
            return this.getByPath(section.settingsKey) || {};
        },

        /** Open-set membership: the step keys a family currently declares. */
        sizeKeys(section) {
            return Object.keys(this.familyOf(section).sizes || {});
        },

        /** Emitted variable name for a step — key-identity `--{cssPrefix}{key}`. */
        itemCssName(section, key) {
            return `--${section.cssPrefix || ''}${key}`;
        },

        /** A scale family's anchor step (the origin) — its value / position. */
        anchorValue(section) {
            const fam = this.familyOf(section);
            return fam.sizes?.[fam.default]?.value ?? 16;
        },

        anchorPosition(section) {
            const fam = this.familyOf(section);
            return fam.sizes?.[fam.default]?.position ?? 0;
        },

        /** Computed px for a scale step: anchorValue * ratio^(pos − anchorPos). */
        computeSize(section, key) {
            const fam = this.familyOf(section);
            const pos = fam.sizes?.[key]?.position ?? 0;
            const raw = this.anchorValue(section) * Math.pow(fam.ratio ?? 1, pos - this.anchorPosition(section));
            // Text keeps a decimal (finer type steps); spacing/headings round to int.
            return section.cssPrefix === 'text-' ? Math.round(raw * 10) / 10 : Math.round(raw);
        },

        // ============================================
        // Scale-family editing (spacing / text / headings)
        // ============================================

        applyScale(section) {
            for (const key of this.sizeKeys(section)) {
                this.applyCSSVariable(this.itemCssName(section, key), `${this.computeSize(section, key)}px`);
            }
            const fam = this.familyOf(section);
            if (section.alias && fam.default) {
                this.applyCSSVariable(`--${section.alias}`, `var(--${section.cssPrefix}${fam.default})`);
            }
        },

        setAnchor(section, value) {
            const fam = this.familyOf(section);
            if (!fam.sizes?.[fam.default]) return;
            fam.sizes[fam.default].value = parseFloat(value);
            this.applyScale(section);
            this.markChanged();
        },

        setRatio(section, value) {
            const fam = this.familyOf(section);
            fam.ratio = parseFloat(value);
            this.applyScale(section);
            this.markChanged();
        },

        // ============================================
        // Pick-one editing (border / radius) + designated default → bare alias
        // ============================================

        applyPickOne(section) {
            const fam = this.familyOf(section);
            for (const [key, data] of Object.entries(fam.sizes || {})) {
                if (data.value !== undefined) {
                    this.applyCSSVariable(this.itemCssName(section, key), `${data.value}${section.unit || ''}`);
                }
            }
            this.applyAlias(section);
        },

        setPickValue(section, key, value) {
            const fam = this.familyOf(section);
            if (!fam.sizes?.[key]) return;
            fam.sizes[key].value = parseFloat(value);
            this.applyCSSVariable(this.itemCssName(section, key), `${value}${section.unit || ''}`);
            this.markChanged();
        },

        setDefault(section, key) {
            this.familyOf(section).default = key;
            this.applyAlias(section);
            this.markChanged();
        },

        /** Emit the bare family alias pointing at the designated default step. */
        applyAlias(section) {
            if (!section.alias) return;
            const fam = this.familyOf(section);
            if (fam.default && fam.sizes?.[fam.default]) {
                this.applyCSSVariable(`--${section.alias}`, `var(--${section.cssPrefix}${fam.default})`);
            } else {
                // No member default (e.g. shadow's seeded `none`) → literal none.
                this.applyCSSVariable(`--${section.alias}`, 'none');
            }
        },

        // ============================================
        // Composite editing (shadows)
        // ============================================

        compositeValue(section, key) {
            const data = this.familyOf(section).sizes?.[key];
            if (!data) return '';
            let value = section.composite.template;
            for (const field of section.composite.fields) {
                value = value.replace(`{${field.id}}`, data[field.id]);
            }
            return value;
        },

        applyComposite(section) {
            for (const key of this.sizeKeys(section)) {
                this.applyCSSVariable(this.itemCssName(section, key), this.compositeValue(section, key));
            }
            this.applyAlias(section);
        },

        setCompositeField(section, key, field, value) {
            const fam = this.familyOf(section);
            if (!fam.sizes?.[key]) return;
            fam.sizes[key][field] = parseFloat(value);
            this.applyCSSVariable(this.itemCssName(section, key), this.compositeValue(section, key));
            this.markChanged();
        },

        // ============================================
        // CSS Application
        // ============================================

        applyCSSVariable(name, value) {
            document.documentElement.style.setProperty(name, value);
        },

        applyAllSettings() {
            if (!this.schema) return;

            for (const panel of this.schema.panels) {
                const tabs = panel.tabs || [panel];
                for (const tab of tabs) {
                    const sections = tab.sections || [tab];
                    for (const section of sections) {
                        const type = this.getSectionType(section);
                        if (type === 'scale') {
                            this.applyScale(section);
                        } else if (type === 'pickone') {
                            this.applyPickOne(section);
                        } else if (type === 'composite') {
                            this.applyComposite(section);
                        } else if (type === 'palette') {
                            this.applyPaletteSection(section);
                        }
                    }
                    if (tab.properties) {
                        this.applyColorwaySettings();
                    }
                }
            }
        },

        // ============================================
        // Color Methods
        // ============================================

        applyPaletteSection(section) {
            const colors = this.getByPath(section.settingsKey);
            if (!colors) return;
            for (const [name, data] of Object.entries(colors)) {
                if (!data.enabled) {
                    this.applyCSSVariable(`--${name}`, 'initial');
                    for (const shade of this.getHueShades()) {
                        this.applyCSSVariable(`--${name}-${shade.id}`, 'initial');
                    }
                    continue;
                }
                if (data.color) {
                    this.applyCSSVariable(`--${name}`, data.color);
                    this.applyColorHues(name, data.color);
                }
            }
        },

        updateColorBase(settingsKey, colorName, value) {
            const colors = this.getByPath(settingsKey);
            if (colors?.[colorName]) {
                colors[colorName].color = value;
                this.applyCSSVariable(`--${colorName}`, value);
                this.applyColorHues(colorName, value);
                this.markChanged();
            }
        },

        toggleColor(settingsKey, colorName, enabled) {
            const colors = this.getByPath(settingsKey);
            if (colors?.[colorName]) {
                colors[colorName].enabled = enabled;
                if (enabled && colors[colorName].color) {
                    this.applyCSSVariable(`--${colorName}`, colors[colorName].color);
                    this.applyColorHues(colorName, colors[colorName].color);
                } else {
                    this.applyCSSVariable(`--${colorName}`, 'initial');
                    for (const shade of this.getHueShades()) {
                        this.applyCSSVariable(`--${colorName}-${shade.id}`, 'initial');
                    }
                }
                this.markChanged();
            }
        },

        applyColorHues(name, hex) {
            const hsl = hexToHsl(hex);
            for (const shade of this.getHueShades()) {
                const shadeHex = hslToHex(hsl.h, hsl.s, shade.lightness);
                this.applyCSSVariable(`--${name}-${shade.id}`, shadeHex);
            }
        },

        getHueShades() {
            const colorsPanel = this.schema?.panels?.find(p => p.id === 'colors');
            const paletteTab = colorsPanel?.tabs?.find(t => t.id === 'palette');
            return paletteTab?.hues?.shades || [];
        },

        getPaletteTab() {
            const colorsPanel = this.schema?.panels?.find(p => p.id === 'colors');
            return colorsPanel?.tabs?.find(t => t.id === 'palette');
        },

        // ============================================
        // Colorway Methods
        // ============================================

        updateColorway(wayName, prop, value) {
            const colorways = this.settings.color?.colorways;
            if (colorways?.[wayName]) {
                colorways[wayName][prop] = value;
                this.applyColorwaySettings();
                this.markChanged();
            }
        },

        getPaletteOptions() {
            const shades = this.getHueShades();
            const groups = [{
                group: 'Fixed',
                base: null,
                shades: [
                    { value: '#ffffff', label: 'White', hex: '#ffffff' },
                    { value: '#000000', label: 'Black', hex: '#000000' }
                ]
            }];

            const paletteTab = this.getPaletteTab();
            if (!paletteTab) return groups;

            for (const section of paletteTab.sections || []) {
                if (!section.colors) continue;
                const colors = this.getByPath(section.settingsKey);
                for (const name of section.colors) {
                    const data = colors?.[name];
                    if (!data?.enabled || !data?.color) continue;

                    const displayName = name.charAt(0).toUpperCase() + name.slice(1);
                    const hsl = hexToHsl(data.color);

                    const base = { value: `var(--${name})`, label: displayName, hex: data.color };
                    const shadeList = shades.map(shade => ({
                        value: `var(--${name}-${shade.id})`,
                        label: `${displayName} ${shade.id.split('-').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')}`,
                        hex: hslToHex(hsl.h, hsl.s, shade.lightness)
                    }));

                    groups.push({ group: displayName, base, shades: shadeList });
                }
            }

            return groups;
        },

        resolveColorwayHex(value) {
            if (!value) return '#cccccc';
            if (value.startsWith('#')) return value;

            const match = value.match(/^var\(--(.+)\)$/);
            if (!match) return '#cccccc';

            const varName = match[1];
            const shades = this.getHueShades();
            const shadeMap = {};
            for (const shade of shades) {
                shadeMap[shade.id] = shade.lightness;
            }

            const paletteTab = this.getPaletteTab();
            for (const section of paletteTab?.sections || []) {
                const colors = this.getByPath(section.settingsKey);
                for (const [name, data] of Object.entries(colors || {})) {
                    if (varName === name) return data.color || '#cccccc';
                    if (varName.startsWith(name + '-')) {
                        const shade = varName.slice(name.length + 1);
                        if (shade in shadeMap) {
                            const hsl = hexToHsl(data.color);
                            return hslToHex(hsl.h, hsl.s, shadeMap[shade]);
                        }
                    }
                }
            }

            return '#cccccc';
        },

        toggleColorwayDropdown(id) {
            if (this.colorwayDropdownId === id) {
                this.closeColorwayDropdown();
            } else {
                this.colorwayDropdownId = id;
                this.colorwayCustomMode = null;
            }
        },

        closeColorwayDropdown() {
            this.colorwayDropdownId = null;
            this.colorwayCustomMode = null;
        },

        selectColorwayOption(wayName, tokenId, value) {
            this.updateColorway(wayName, tokenId, value);
            this.closeColorwayDropdown();
        },

        enterCustomHexMode(id) {
            this.colorwayCustomMode = id;
        },

        applyCustomHex(wayName, tokenId, hex) {
            if (/^#[0-9a-fA-F]{6}$/.test(hex)) {
                this.updateColorway(wayName, tokenId, hex);
                this.closeColorwayDropdown();
            }
        },

        getColorwayDisplayLabel(value) {
            if (!value) return '';
            if (value === '#ffffff') return 'White';
            if (value === '#000000') return 'Black';
            if (value.startsWith('#')) return value.toUpperCase();

            const match = value.match(/^var\(--(.+)\)$/);
            if (!match) return value;

            return match[1].split('-').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
        },

        applyColorwaySettings() {
            const colorways = this.settings.color?.colorways;
            if (!colorways) return;

            const colorsPanel = this.schema?.panels?.find(p => p.id === 'colors');
            const colorwaysTab = colorsPanel?.tabs?.find(t => t.id === 'colorways');
            const properties = colorwaysTab?.properties || [];

            let styleEl = document.getElementById('anti-colorway-overrides');
            if (!styleEl) {
                styleEl = document.createElement('style');
                styleEl.id = 'anti-colorway-overrides';
                document.head.appendChild(styleEl);
            }

            let css = '';
            for (const [wayName, data] of Object.entries(colorways)) {
                const selector = wayName === 'default' ? ':root' : `[data-colorway="${wayName}"]`;
                const lines = [];
                for (const prop of properties) {
                    if (data[prop.id]) lines.push(`    --colorway-${prop.id}: ${data[prop.id]};`);
                }
                if (lines.length) {
                    css += `${selector} {\n${lines.join('\n')}\n}\n`;
                }
            }
            styleEl.textContent = css;

            this.syncColorwayList();
        },

        /** Broadcast current colorway names so other components (e.g. preview selector) stay in sync */
        syncColorwayList() {
            const colorways = Object.keys(this.settings.color?.colorways || {});
            window.__antiColorways = colorways;
            window.dispatchEvent(new CustomEvent('anti-colorways-changed', { detail: { colorways } }));
        },

        // ============================================
        // Colorway Management (Add / Delete)
        // ============================================

        addColorway() {
            const name = this.newColorwayName.trim();
            if (!name) return;

            const colorways = this.settings.color?.colorways;
            if (!colorways || colorways[name]) {
                this.showNotification(colorways[name] ? 'Colorway already exists' : 'Error', 'error');
                return;
            }

            // Clone from default colorway as starting point
            const defaultWay = colorways['default'] || {};
            colorways[name] = JSON.parse(JSON.stringify(defaultWay));

            this.newColorwayName = '';
            this.addingColorway = false;
            this.applyColorwaySettings();
            this.markChanged();
            this.showNotification(`Colorway "${name}" added`, 'success');
        },

        deleteColorway(wayName) {
            if (wayName === 'default') return;
            const colorways = this.settings.color?.colorways;
            if (!colorways?.[wayName]) return;

            delete colorways[wayName];
            this.applyColorwaySettings();
            this.markChanged();
            this.showNotification(`Colorway "${wayName}" removed`, 'success');
        },

        cancelAddColorway() {
            this.addingColorway = false;
            this.newColorwayName = '';
        },

        // ============================================
        // Change Tracking
        // ============================================

        markChanged() {
            this.settings._version = SETTINGS_VERSION;
            localStorage.setItem('antiExplorer_data', JSON.stringify(this.settings));
            window.__antiSettings = this.settings;
            window.dispatchEvent(new CustomEvent('anti-settings-changed'));
        },

        // ============================================
        // Reset Methods
        // ============================================

        discardChanges() {
            localStorage.removeItem('antiExplorer_data');
            this.settings = JSON.parse(JSON.stringify(this.defaultSettings));
            this.applyAllSettings();
            window.__antiSettings = this.settings;
            window.dispatchEvent(new CustomEvent('anti-settings-changed'));
            this.showNotification('Changes discarded', 'success');
        },

        resetSettings() {
            this.settings = JSON.parse(JSON.stringify(this.defaultSettings));
            this.markChanged();
            this.applyAllSettings();
            this.showNotification('Settings reset to defaults', 'success');
        },

        resetSections(sectionPaths) {
            for (const path of sectionPaths) {
                const defaultValue = this.getByPathFrom(this.defaultSettings, path);
                if (defaultValue !== undefined) {
                    this.setByPath(path, JSON.parse(JSON.stringify(defaultValue)));
                }
            }
            this.applyAllSettings();
            this.markChanged();
            this.showNotification('Reset complete', 'success');
        },

        // ============================================
        // Export Methods
        // ============================================

        settingsToTokenJSON() {
            const result = JSON.parse(JSON.stringify(this.settings));

            // Strip panel-only state
            delete result._version;

            return result;
        },

        async exportCSS() {
            const tokenJSON = this.settingsToTokenJSON();
            try {
                const res = await fetch('shared/export.php?format=css', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(tokenJSON)
                });
                if (!res.ok) throw new Error('Export failed');
                const css = await res.text();
                const blob = new Blob([css], { type: 'text/css' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'tokens.css';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                this.showNotification('CSS exported', 'success');
            } catch (e) {
                this.showNotification('Export failed', 'error');
            }
        },

        exportJSON() {
            const tokenJSON = this.settingsToTokenJSON();
            const json = JSON.stringify(tokenJSON, null, 2);
            const blob = new Blob([json], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'tokens.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            this.showNotification('JSON exported', 'success');
        },

        // ============================================
        // Notification
        // ============================================

        showNotification(message, type = 'success') {
            this.notificationText = message;
            this.notificationType = type;
            this.notificationVisible = true;
            setTimeout(() => { this.notificationVisible = false; }, 3000);
        }
    }));
}

// ============================================
// Panel HTML Template — Generic Section Renderers
// ============================================

const getPanelHTML = () => `
    <div x-data="stylePanel"
         class="anti-panel anti-panel-container"
         :class="{ 'settings-open': settingsOpen, 'is-hidden': !isOpen }"
         @keydown.escape.window="colorwayDropdownId ? closeColorwayDropdown() : closeSettings()">

        <!-- Navigation Panel -->
        <nav class="anti-nav">
            <div class="anti-nav__header">
                <div class="anti-nav__logo">${UI_ICONS.palette}</div>
                <h1 class="anti-nav__title">Styles</h1>
                <button class="anti-nav__close" @click="togglePanel" aria-label="Close panel" title="Close panel">
                    ${UI_ICONS.close}
                </button>
            </div>

            <div class="anti-nav__menu">
                <template x-if="schema">
                    <template x-for="panel in schema.panels" :key="panel.id">
                        <button
                            class="anti-nav__item"
                            :class="{ 'is-active': activeCategory === panel.id }"
                            @click="openCategory(panel.id)"
                            :title="panel.label"
                        >
                            <span class="anti-nav__item-icon" x-html="schema.icons[panel.icon]"></span>
                            <span class="anti-nav__item-label" x-text="panel.label"></span>
                        </button>
                    </template>
                </template>
            </div>

            <div class="anti-nav__footer">
                <button class="anti-nav__item" @click="exportCSS" title="Export CSS">
                    <span class="anti-nav__item-icon">${UI_ICONS.export}</span>
                    <span class="anti-nav__item-type">CSS</span>
                    <span class="anti-nav__item-label">Export CSS</span>
                </button>
                <button class="anti-nav__item" @click="exportJSON" title="Export JSON">
                    <span class="anti-nav__item-icon">${UI_ICONS.export}</span>
                    <span class="anti-nav__item-type">JSON</span>
                    <span class="anti-nav__item-label">Export JSON</span>
                </button>
            </div>
        </nav>

        <!-- Settings Panel -->
        <aside class="anti-settings">
            <header class="anti-settings__header">
                <button class="anti-settings__back" @click="closeSettings" aria-label="Back to navigation" title="Back">
                    ${UI_ICONS.chevronLeft}
                </button>
                <h2 class="anti-settings__title" x-text="getCurrentPanel()?.label || 'Settings'"></h2>
                <button class="anti-settings__close" @click="togglePanel" aria-label="Close panel" title="Close panel">
                    ${UI_ICONS.close}
                </button>
            </header>

            <!-- Tabs -->
            <div class="anti-settings__tabs" x-show="currentTabs.length > 0">
                <template x-for="tab in currentTabs" :key="tab.id">
                    <button
                        class="anti-settings__tab"
                        :class="{ 'is-active': activeTab === tab.id }"
                        @click="switchTab(tab.id)"
                        x-text="tab.label"
                    ></button>
                </template>
            </div>

            <!-- Settings Content -->
            <main class="anti-settings__content">
                <div class="anti-settings__panel" x-show="activeCategory">

                    <template x-for="section in currentSections" :key="section.id">
                        <div>

                            <!-- ===== SCALE section (spacing / text / headings) ===== -->
                            <template x-if="getSectionType(section) === 'scale'">
                                <div>
                                    <div class="anti-section-title" x-text="section.label"></div>

                                    <!-- Anchor size (the scale origin) -->
                                    <div class="anti-size-section is-enabled">
                                        <div class="anti-size-header">
                                            <span class="anti-size-name" x-text="section.anchor.label"></span>
                                        </div>
                                        <div class="anti-size-controls" style="display: block;">
                                            <div class="anti-control-row">
                                                <input type="range" class="anti-range"
                                                    :min="section.anchor.min" :max="section.anchor.max" :step="section.anchor.step"
                                                    :value="anchorValue(section)"
                                                    @input="setAnchor(section, $event.target.value)">
                                                <div class="anti-control-value">
                                                    <input type="number" :step="section.anchor.step"
                                                        :value="anchorValue(section)"
                                                        @change="setAnchor(section, $event.target.value)">
                                                    <span x-show="section.anchor.unit" x-text="section.anchor.unit"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Ratio -->
                                    <div class="anti-size-section is-enabled">
                                        <div class="anti-size-header">
                                            <span class="anti-size-name" x-text="section.ratio.label"></span>
                                        </div>
                                        <div class="anti-size-controls" style="display: block;">
                                            <div class="anti-control-row">
                                                <input type="range" class="anti-range"
                                                    :min="section.ratio.min" :max="section.ratio.max" :step="section.ratio.step"
                                                    :value="familyOf(section).ratio"
                                                    @input="setRatio(section, $event.target.value)">
                                                <div class="anti-control-value">
                                                    <input type="number" :step="section.ratio.step"
                                                        :value="familyOf(section).ratio"
                                                        @change="setRatio(section, $event.target.value)">
                                                </div>
                                            </div>
                                            <template x-if="section.ratio.presets">
                                                <select class="anti-select" style="margin-top: 12px;"
                                                    @change="setRatio(section, $event.target.value)">
                                                    <option value="">Custom</option>
                                                    <template x-for="preset in (schema.presets[section.ratio.presets] || [])" :key="presetValue(preset)">
                                                        <option :value="presetValue(preset)" x-text="presetLabel(preset)"
                                                            :selected="familyOf(section).ratio === presetValue(preset)"></option>
                                                    </template>
                                                </select>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Computed steps (read-only; presence in sizes is membership) -->
                                    <div class="anti-section-title" style="margin-top: 12px;">Steps</div>
                                    <template x-for="key in sizeKeys(section)" :key="key">
                                        <div class="anti-size-section is-enabled">
                                            <div class="anti-size-header">
                                                <span class="anti-size-name" x-text="getItemLabel(key)"></span>
                                                <span class="anti-size-computed"
                                                    x-text="computeSize(section, key) + (section.unit || '')"></span>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- ===== PICK-ONE section (border / radius) ===== -->
                            <template x-if="getSectionType(section) === 'pickone'">
                                <div>
                                    <div class="anti-section-title" x-text="section.label"></div>
                                    <template x-for="key in sizeKeys(section)" :key="key">
                                        <div class="anti-size-section is-enabled">
                                            <div class="anti-size-header">
                                                <span class="anti-size-name" x-text="getItemLabel(key)"></span>
                                                <label class="anti-default-radio">
                                                    <input type="radio" :name="section.id + '-default'"
                                                        :checked="familyOf(section).default === key"
                                                        @change="setDefault(section, key)">
                                                    <span>Default</span>
                                                </label>
                                            </div>
                                            <div class="anti-size-controls">
                                                <div class="anti-control-row">
                                                    <input type="range" class="anti-range"
                                                        :min="section.value.min" :max="section.value.max" :step="section.value.step"
                                                        :value="familyOf(section).sizes[key].value"
                                                        @input="setPickValue(section, key, $event.target.value)">
                                                    <div class="anti-control-value">
                                                        <input type="number" :step="section.value.step"
                                                            :value="familyOf(section).sizes[key].value"
                                                            @change="setPickValue(section, key, $event.target.value)">
                                                        <span x-show="section.unit" x-text="section.unit"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- ===== COMPOSITE section (shadows) ===== -->
                            <template x-if="getSectionType(section) === 'composite'">
                                <div>
                                    <div class="anti-section-title" x-text="section.label"></div>
                                    <template x-for="key in sizeKeys(section)" :key="key">
                                        <div class="anti-size-section is-enabled">
                                            <div class="anti-size-header">
                                                <span class="anti-size-name" x-text="getItemLabel(key)"></span>
                                                <label class="anti-default-radio">
                                                    <input type="radio" :name="section.id + '-default'"
                                                        :checked="familyOf(section).default === key"
                                                        @change="setDefault(section, key)">
                                                    <span>Default</span>
                                                </label>
                                            </div>
                                            <div class="anti-size-controls">
                                                <div class="anti-control-group">
                                                    <template x-for="cf in section.composite.fields" :key="cf.id">
                                                        <div style="margin-top: 4px;">
                                                            <label class="anti-control-label" x-text="cf.label"></label>
                                                            <div class="anti-control-row">
                                                                <input type="range" class="anti-range"
                                                                    :min="cf.min" :max="cf.max" :step="cf.step"
                                                                    :value="familyOf(section).sizes[key][cf.id]"
                                                                    @input="setCompositeField(section, key, cf.id, $event.target.value)">
                                                                <div class="anti-control-value">
                                                                    <input type="number" :step="cf.step"
                                                                        :value="familyOf(section).sizes[key][cf.id]"
                                                                        @change="setCompositeField(section, key, cf.id, $event.target.value)">
                                                                    <span x-show="cf.unit" x-text="cf.unit"></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- ===== PALETTE section (colors) ===== -->
                            <template x-if="getSectionType(section) === 'palette'">
                                <div>
                                    <div class="anti-section-title" x-text="section.label" style="margin-top: 8px;"></div>
                                    <template x-for="colorName in section.colors" :key="colorName">
                                        <div class="anti-size-section"
                                            :class="{ 'is-enabled': getByPath(section.settingsKey)?.[colorName]?.enabled }">
                                            <div class="anti-size-header">
                                                <span class="anti-size-name"
                                                    x-text="colorName.charAt(0).toUpperCase() + colorName.slice(1)"></span>
                                                <label class="anti-toggle">
                                                    <input type="checkbox"
                                                        :checked="getByPath(section.settingsKey)?.[colorName]?.enabled"
                                                        @change="toggleColor(section.settingsKey, colorName, $event.target.checked)">
                                                    <span class="anti-toggle-slider"></span>
                                                </label>
                                            </div>
                                            <div class="anti-size-controls">
                                                <div class="anti-control-group">
                                                    <div class="anti-color-input">
                                                        <span class="anti-color-swatch"
                                                            :style="'background:' + (getByPath(section.settingsKey)?.[colorName]?.color || '#cccccc')"></span>
                                                        <input type="text" data-coloris
                                                            :value="getByPath(section.settingsKey)?.[colorName]?.color"
                                                            @input="updateColorBase(section.settingsKey, colorName, $event.target.value)">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- ===== COLORWAYS section ===== -->
                            <template x-if="getSectionType(section) === 'colorways'">
                                <div>
                                    <template x-for="(way, wayName) in (getByPath(section.settingsKey) || {})" :key="wayName">
                                        <div class="anti-size-section is-enabled">
                                            <div class="anti-size-header">
                                                <span class="anti-size-name"
                                                    x-text="wayName.charAt(0).toUpperCase() + wayName.slice(1)"></span>
                                                <button x-show="wayName !== 'default'"
                                                    class="anti-colorway-delete"
                                                    @click="deleteColorway(wayName)"
                                                    :title="'Delete ' + wayName + ' colorway'"
                                                    aria-label="Delete colorway">
                                                    ${UI_ICONS.close}
                                                </button>
                                            </div>
                                            <div class="anti-size-controls">
                                                <template x-for="token in section.properties" :key="token.id">
                                                    <div class="anti-control-group" style="margin-top: 8px;">
                                                        <label class="anti-control-label" style="font-size: 11px;" x-text="token.label"></label>
                                                        <div class="anti-colorway-picker">
                                                            <button class="anti-colorway-picker__trigger"
                                                                @click="toggleColorwayDropdown(wayName + '-' + token.id)">
                                                                <span class="anti-colorway-picker__swatch"
                                                                    :class="{ 'is-white': resolveColorwayHex(way[token.id]) === '#ffffff' }"
                                                                    :style="'background:' + resolveColorwayHex(way[token.id])"></span>
                                                                <span class="anti-colorway-picker__label"
                                                                    x-text="getColorwayDisplayLabel(way[token.id])"></span>
                                                                <span class="anti-colorway-picker__chevron">&#9662;</span>
                                                            </button>
                                                            <div class="anti-colorway-picker__dropdown"
                                                                x-show="colorwayDropdownId === wayName + '-' + token.id"
                                                                x-cloak>
                                                                <template x-if="colorwayCustomMode !== wayName + '-' + token.id">
                                                                    <div>
                                                                        <template x-for="group in getPaletteOptions()" :key="group.group">
                                                                            <div class="anti-colorway-picker__group">
                                                                                <button x-show="group.base"
                                                                                    class="anti-colorway-picker__color-row"
                                                                                    :class="{ 'is-selected': way[token.id] === group.base?.value }"
                                                                                    @click="selectColorwayOption(wayName, token.id, group.base?.value)">
                                                                                    <span class="anti-colorway-picker__parent-swatch"
                                                                                        :class="{ 'is-white': group.base?.hex === '#ffffff' }"
                                                                                        :style="'background:' + group.base?.hex"></span>
                                                                                    <span class="anti-colorway-picker__parent-label"
                                                                                        x-text="group.base?.label"></span>
                                                                                </button>
                                                                                <div x-show="!group.base" class="anti-colorway-picker__group-label"
                                                                                    x-text="group.group"></div>
                                                                                <div class="anti-colorway-picker__shade-row">
                                                                                    <template x-for="shade in group.shades" :key="shade.value">
                                                                                        <button class="anti-colorway-picker__shade"
                                                                                            :class="{ 'is-selected': way[token.id] === shade.value, 'is-white': shade.hex === '#ffffff' }"
                                                                                            :style="'background:' + shade.hex"
                                                                                            :title="shade.label"
                                                                                            @click="selectColorwayOption(wayName, token.id, shade.value)">
                                                                                        </button>
                                                                                    </template>
                                                                                </div>
                                                                            </div>
                                                                        </template>
                                                                        <div class="anti-colorway-picker__group">
                                                                            <button class="anti-colorway-picker__option"
                                                                                @click="enterCustomHexMode(wayName + '-' + token.id)">
                                                                                <span class="anti-colorway-picker__option-swatch"
                                                                                    style="background: linear-gradient(135deg, #ff0000, #ff8800, #ffff00, #00ff00, #0088ff, #8800ff)"></span>
                                                                                <span class="anti-colorway-picker__option-label">Custom hex...</span>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </template>
                                                                <template x-if="colorwayCustomMode === wayName + '-' + token.id">
                                                                    <div class="anti-colorway-picker__custom">
                                                                        <input type="text" data-coloris
                                                                            :value="way[token.id]?.startsWith?.('#') ? way[token.id] : ''"
                                                                            placeholder="#000000"
                                                                            @input="updateColorway(wayName, token.id, $event.target.value)"
                                                                            @keydown.enter="applyCustomHex(wayName, token.id, $event.target.value)">
                                                                    </div>
                                                                </template>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Add Colorway -->
                                    <div class="anti-colorway-add">
                                        <template x-if="!addingColorway">
                                            <button class="anti-btn anti-btn--add-colorway" @click="addingColorway = true">
                                                + Add Colorway
                                            </button>
                                        </template>
                                        <template x-if="addingColorway">
                                            <div class="anti-colorway-add__form">
                                                <input type="text" class="anti-input"
                                                    x-model="newColorwayName"
                                                    placeholder="Colorway Name"
                                                    @keydown.enter="addColorway()"
                                                    @keydown.escape="cancelAddColorway()"
                                                    x-init="$nextTick(() => $el.focus())">
                                                <div class="anti-colorway-add__actions">
                                                    <button class="anti-btn anti-btn--small anti-btn--primary" @click="addColorway()">Add</button>
                                                    <button class="anti-btn anti-btn--small anti-btn--secondary" @click="cancelAddColorway()">Cancel</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                        </div>
                    </template>

                    <!-- Reset button -->
                    <template x-if="currentResetButton">
                        <button class="anti-btn anti-btn--reset"
                            @click="resetSections(currentResetButton.sections)"
                            x-text="currentResetButton.label">
                        </button>
                    </template>

                </div>
            </main>

            <!-- Settings Footer -->
            <footer class="anti-settings__footer">
                <div class="anti-settings__actions">
                    <button class="anti-btn anti-btn--secondary" @click="resetSettings">
                        Reset All
                    </button>
                </div>
            </footer>
        </aside>

        <!-- Notification -->
        <div
            x-show="notificationVisible"
            x-transition:enter="anti-notification-enter"
            x-transition:enter-start="anti-notification-off"
            x-transition:enter-end="anti-notification-on"
            x-transition:leave="anti-notification-leave"
            x-transition:leave-start="anti-notification-on"
            x-transition:leave-end="anti-notification-off"
            class="anti-notification"
            :class="'anti-notification--' + notificationType"
            x-text="notificationText"
        ></div>
    </div>
`;

// ============================================
// Initialization
// ============================================

function initStylePanel() {
    document.body.insertAdjacentHTML('afterbegin', getPanelHTML());

    const panelElement = document.body.querySelector('.anti-panel-container');
    if (window.Alpine && panelElement) {
        Alpine.initTree(panelElement);
    }
}

function bootStylePanel() {
    registerStylePanel();
    initStylePanel();
}

// Handle different Alpine loading scenarios
if (window.Alpine) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootStylePanel);
    } else {
        bootStylePanel();
    }
} else {
    document.addEventListener('alpine:init', () => {
        registerStylePanel();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initStylePanel);
    } else {
        initStylePanel();
    }
}
