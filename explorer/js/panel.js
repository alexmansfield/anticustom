/**
 * Anticustom Style Editor Panel — Explorer Edition
 *
 * Two-panel collapsible sidebar with Alpine.js:
 * - Navigation panel (collapses to icons when settings open)
 * - Settings panel (slides in when category selected)
 *
 * Adapted from anticustom-styles/panel.js:
 * - Stripped CMS-specific code (server sync, canEdit check, FABs)
 * - Removed "Elements" category (button tokens use a different format)
 * - Starts open by default
 * - Save/load via localStorage only
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
// SVG Icons
// ============================================

const ICONS = {
    // Category icons
    typography: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="4 7 4 4 20 4 20 7"></polyline>
        <line x1="9" y1="20" x2="15" y2="20"></line>
        <line x1="12" y1="4" x2="12" y2="20"></line>
    </svg>`,

    colors: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="8" y="1" width="8" height="18" rx="2" transform="rotate(-25 12 19)" fill="var(--icon-fill, white)"></rect>
        <rect x="8" y="1" width="8" height="18" rx="2" transform="rotate(0 12 19)" fill="var(--icon-fill, white)"></rect>
        <rect x="8" y="1" width="8" height="18" rx="2" transform="rotate(25 12 19)" fill="var(--icon-fill, white)"></rect>
    </svg>`,

    spacing: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
        <polyline points="3.29 7 12 12 20.71 7"></polyline>
        <line x1="12" y1="22" x2="12" y2="12"></line>
    </svg>`,

    borders: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
        <line x1="3" y1="9" x2="21" y2="9"></line>
        <line x1="9" y1="21" x2="9" y2="9"></line>
    </svg>`,

    // UI icons
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
// Category Configuration (no Elements)
// ============================================

const CATEGORIES = [
    {
        id: 'typography',
        label: 'Typography',
        icon: 'typography',
        tabs: [
            { id: 'headings', label: 'Headings' },
            { id: 'text', label: 'Text' }
        ]
    },
    {
        id: 'colors',
        label: 'Colors',
        icon: 'colors',
        tabs: [
            { id: 'palette', label: 'Palette' },
            { id: 'colorways', label: 'Colorways' }
        ]
    },
    {
        id: 'spacing',
        label: 'Spacing',
        icon: 'spacing',
        tabs: null
    },
    {
        id: 'borders',
        label: 'Borders',
        icon: 'borders',
        tabs: [
            { id: 'borders', label: 'Borders' },
            { id: 'shadows', label: 'Shadows' },
            { id: 'radius', label: 'Radius' }
        ]
    },
];

// ============================================
// Default Settings
// ============================================

const DEFAULT_SETTINGS = {
    typography: {
        headings: {
            baseSize: 16,
            scale: 1.618,
            customized: false,
            sizes: {
                h1: { value: 178, enabled: false, lineHeight: 1.2, letterSpacing: -0.02, weight: 700 },
                h2: { value: 110, enabled: false, lineHeight: 1.25, letterSpacing: -0.02, weight: 700 },
                h3: { value: 68, enabled: false, lineHeight: 1.3, letterSpacing: -0.01, weight: 600 },
                h4: { value: 42, enabled: false, lineHeight: 1.35, letterSpacing: -0.01, weight: 600 },
                h5: { value: 26, enabled: false, lineHeight: 1.4, letterSpacing: 0, weight: 600 },
                h6: { value: 16, enabled: false, lineHeight: 1.4, letterSpacing: 0, weight: 600 }
            }
        },
        text: {
            baseSize: 16,
            scale: 1.125,
            customized: false,
            sizes: {
                xl: { value: 20, enabled: false, lineHeight: 1.5, letterSpacing: 0, weight: 400 },
                l: { value: 18, enabled: false, lineHeight: 1.5, letterSpacing: 0, weight: 400 },
                m: { value: 16, enabled: true, lineHeight: 1.5, letterSpacing: 0, weight: 400 },
                s: { value: 14, enabled: false, lineHeight: 1.5, letterSpacing: 0, weight: 400 },
                xs: { value: 12, enabled: false, lineHeight: 1.5, letterSpacing: 0, weight: 400 }
            }
        }
    },
    colors: {
        primary: { enabled: true, base: '#336699' },
        secondary: { enabled: false, base: '#64748b' },
        accent: { enabled: false, base: '#8b5cf6' },
        neutral: { enabled: true, base: '#737373' },
        info: { enabled: false, base: '#0ea5e9' },
        success: { enabled: false, base: '#22c55e' },
        warning: { enabled: false, base: '#eab308' },
        danger: { enabled: false, base: '#ef4444' }
    },
    spacing: {
        baseSize: 16,
        scale: 1.5,
        customized: false,
        sizes: {
            xxs: { value: 4, enabled: false },
            xs: { value: 7, enabled: false },
            s: { value: 11, enabled: false },
            m: { value: 16, enabled: false },
            l: { value: 24, enabled: false },
            xl: { value: 36, enabled: false },
            xxl: { value: 54, enabled: false }
        }
    },
    borders: {
        defaultSize: 'm',
        sizes: {
            s: { value: 1, enabled: false },
            m: { value: 2, enabled: false },
            l: { value: 4, enabled: false }
        }
    },
    shadows: {
        xs: { enabled: false, x: 0, y: 1, blur: 1, spread: 0, opacity: 0.05 },
        s: { enabled: false, x: 0, y: 1, blur: 2, spread: 0, opacity: 0.05 },
        m: { enabled: false, x: 0, y: 4, blur: 6, spread: -1, opacity: 0.1 },
        l: { enabled: false, x: 0, y: 10, blur: 15, spread: -3, opacity: 0.1 },
        xl: { enabled: false, x: 0, y: 20, blur: 25, spread: -5, opacity: 0.15 }
    },
    radius: {
        sizes: {
            xs: { value: 2, enabled: false },
            s: { value: 4, enabled: false },
            m: { value: 8, enabled: false },
            l: { value: 16, enabled: false },
            xl: { value: 24, enabled: false },
            full: { value: 9999, enabled: false }
        }
    },
    colorways: {
        default: { base: 'var(--neutral-ultra-light)', 'hard-contrast': 'var(--neutral-dark)', 'soft-contrast': 'var(--neutral)', accent: 'var(--primary)' },
        primary: { base: 'var(--primary)', 'hard-contrast': '#ffffff', 'soft-contrast': 'var(--primary-light)', accent: 'var(--primary-dark)' }
    }
};

// ============================================
// Type Scale Presets
// ============================================

const TYPE_SCALES = [
    { value: 1.067, label: 'Minor Second (1.067)' },
    { value: 1.125, label: 'Major Second (1.125)' },
    { value: 1.2, label: 'Minor Third (1.2)' },
    { value: 1.25, label: 'Major Third (1.25)' },
    { value: 1.333, label: 'Perfect Fourth (1.333)' },
    { value: 1.414, label: 'Augmented Fourth (1.414)' },
    { value: 1.5, label: 'Perfect Fifth (1.5)' },
    { value: 1.618, label: 'Golden Ratio (1.618)' }
];

const SPACING_SCALES = [
    { value: 1.25, label: 'Minor (1.25)' },
    { value: 1.5, label: 'Standard (1.5)' },
    { value: 1.618, label: 'Golden Ratio (1.618)' },
    { value: 2, label: 'Double (2)' }
];

const FONT_WEIGHTS = [100, 200, 300, 400, 500, 600, 700, 800, 900];

// ============================================
// Alpine.js Component Registration
// ============================================

function registerStylePanel() {
    Alpine.data('stylePanel', () => ({
        // Panel state — start open by default
        isOpen: true,
        settingsOpen: false,
        activeCategory: null,
        activeTab: null,

        // Data state
        isSaving: false,
        hasChanges: false,
        notification: null,

        // Colorway picker state
        colorwayDropdownId: null,
        colorwayCustomMode: null,

        // Settings data
        settings: JSON.parse(JSON.stringify(DEFAULT_SETTINGS)),
        originalSettings: JSON.parse(JSON.stringify(DEFAULT_SETTINGS)),

        // Configuration
        categories: CATEGORIES,
        icons: ICONS,
        typeScales: TYPE_SCALES,
        spacingScales: SPACING_SCALES,
        fontWeights: FONT_WEIGHTS,

        /**
         * Initialize the panel
         */
        init() {
            // Check for settings in localStorage
            const savedSettings = localStorage.getItem('antiExplorer_data');
            if (savedSettings) {
                try {
                    const saved = JSON.parse(savedSettings);
                    this.settings = this.deepMerge(this.settings, saved);
                    // Prune stale keys that no longer exist in defaults
                    for (const section of ['colors', 'colorways', 'shadows', 'spacing.sizes', 'borders.sizes', 'radius.sizes', 'typography.headings.sizes', 'typography.text.sizes']) {
                        const parts = section.split('.');
                        const defaults = parts.reduce((o, k) => o?.[k], DEFAULT_SETTINGS);
                        const current = parts.reduce((o, k) => o?.[k], this.settings);
                        if (current && defaults) {
                            for (const key of Object.keys(current)) {
                                if (!(key in defaults)) delete current[key];
                            }
                        }
                    }
                } catch (e) {
                    console.error('Failed to parse saved settings');
                }
            }

            this.originalSettings = JSON.parse(JSON.stringify(this.settings));

            // Listen for external toggle/open requests
            window.addEventListener('antiTogglePanel', () => {
                this.togglePanel();
            });
            window.addEventListener('antiOpenPanel', () => {
                if (!this.isOpen) this.togglePanel();
            });

            // Restore panel state
            const savedIsOpen = localStorage.getItem('antiExplorer_isOpen');
            if (savedIsOpen !== null) {
                this.isOpen = savedIsOpen === 'true';
            }

            const savedCategory = localStorage.getItem('antiExplorer_category');
            if (savedCategory) {
                this.openCategory(savedCategory);
            }

            this.applyAllSettings();

            // Close colorway dropdown on outside click
            document.addEventListener('click', (e) => {
                if (this.colorwayDropdownId && !e.target.closest('.anti-colorway-picker') && !e.target.closest('.clr-picker')) {
                    this.closeColorwayDropdown();
                }
            });
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

        togglePanel() {
            this.isOpen = !this.isOpen;
            localStorage.setItem('antiExplorer_isOpen', this.isOpen.toString());
            window.dispatchEvent(new CustomEvent('anti-panel-toggled', { detail: { isOpen: this.isOpen, settingsOpen: this.settingsOpen } }));
        },

        openCategory(categoryId) {
            const category = this.categories.find(c => c.id === categoryId);
            if (!category) return;

            this.activeCategory = categoryId;
            this.settingsOpen = true;

            if (category.tabs && category.tabs.length > 0) {
                this.activeTab = category.tabs[0].id;
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
        },

        switchTab(tabId) {
            this.activeTab = tabId;
        },

        get currentCategory() {
            return this.categories.find(c => c.id === this.activeCategory);
        },

        get currentTabs() {
            return this.currentCategory?.tabs || [];
        },

        // ============================================
        // Typography Methods
        // ============================================

        updateHeadingBaseSize(value) {
            this.settings.typography.headings.baseSize = parseInt(value);
            if (!this.settings.typography.headings.customized) {
                this.recalculateHeadings();
            }
            this.markChanged();
        },

        updateHeadingScale(value) {
            this.settings.typography.headings.scale = parseFloat(value);
            if (!this.settings.typography.headings.customized) {
                this.recalculateHeadings();
            }
            this.markChanged();
        },

        updateHeadingSize(level, value) {
            this.settings.typography.headings.sizes[level].value = parseInt(value);
            this.settings.typography.headings.customized = true;
            const num = level.replace('h', '');
            this.applyCSSVariable(`--heading-${num}`, `${value}px`);
            this.markChanged();
        },

        updateHeadingProperty(level, prop, value) {
            this.settings.typography.headings.sizes[level][prop] = parseFloat(value);
            const num = level.replace('h', '');
            const data = this.settings.typography.headings.sizes[level];
            if (prop === 'lineHeight') {
                this.applyCSSVariable(`--heading-${num}-line-height`, data.lineHeight);
            } else if (prop === 'letterSpacing') {
                this.applyCSSVariable(`--heading-${num}-letter-spacing`, `${data.letterSpacing}em`);
            } else if (prop === 'weight') {
                this.applyCSSVariable(`--heading-${num}-weight`, data.weight);
            }
            this.markChanged();
        },

        toggleHeadingSize(level, enabled) {
            this.settings.typography.headings.sizes[level].enabled = enabled;
            this.markChanged();
        },

        recalculateHeadings() {
            const { baseSize, scale } = this.settings.typography.headings;
            const sizes = this.settings.typography.headings.sizes;

            sizes.h6.value = Math.round(baseSize);
            sizes.h5.value = Math.round(baseSize * scale);
            sizes.h4.value = Math.round(baseSize * Math.pow(scale, 2));
            sizes.h3.value = Math.round(baseSize * Math.pow(scale, 3));
            sizes.h2.value = Math.round(baseSize * Math.pow(scale, 4));
            sizes.h1.value = Math.round(baseSize * Math.pow(scale, 5));

            this.settings.typography.headings.customized = false;
            this.applyTypographySettings();
        },

        updateTextBaseSize(value) {
            this.settings.typography.text.baseSize = parseInt(value);
            if (!this.settings.typography.text.customized) {
                this.recalculateText();
            }
            this.markChanged();
        },

        updateTextScale(value) {
            this.settings.typography.text.scale = parseFloat(value);
            if (!this.settings.typography.text.customized) {
                this.recalculateText();
            }
            this.markChanged();
        },

        updateTextSize(size, value) {
            this.settings.typography.text.sizes[size].value = parseInt(value);
            this.settings.typography.text.customized = true;
            this.applyCSSVariable(`--text-${size}`, `${value}px`);
            this.markChanged();
        },

        updateTextProperty(size, prop, value) {
            this.settings.typography.text.sizes[size][prop] = parseFloat(value);
            this.markChanged();
        },

        toggleTextSize(size, enabled) {
            this.settings.typography.text.sizes[size].enabled = enabled;
            this.markChanged();
        },

        recalculateText() {
            const { baseSize, scale } = this.settings.typography.text;
            const sizes = this.settings.typography.text.sizes;

            sizes.xs.value = Math.round(baseSize / Math.pow(scale, 2));
            sizes.s.value = Math.round(baseSize / scale);
            sizes.m.value = Math.round(baseSize);
            sizes.l.value = Math.round(baseSize * scale);
            sizes.xl.value = Math.round(baseSize * Math.pow(scale, 2));

            this.settings.typography.text.customized = false;
            this.applyTypographySettings();
        },

        // ============================================
        // Colors Methods
        // ============================================

        updateColorBase(colorName, value) {
            this.settings.colors[colorName].base = value;
            this.applyCSSVariable(`--${colorName}`, value);
            this.applyColorHues(colorName, value);
            this.markChanged();
        },

        toggleColor(colorName, enabled) {
            this.settings.colors[colorName].enabled = enabled;
            this.markChanged();
        },

        applyColorHues(name, hex) {
            const hsl = hexToHsl(hex);
            const hues = {
                'ultra-light': 90, 'light': 80, 'semi-light': 65,
                'semi-dark': 35, 'dark': 20, 'ultra-dark': 10
            };
            for (const [hueName, lightness] of Object.entries(hues)) {
                const shade = hslToHex(hsl.h, hsl.s, lightness);
                this.applyCSSVariable(`--${name}-${hueName}`, shade);
            }
        },

        // ============================================
        // Spacing Methods
        // ============================================

        updateSpacingBaseSize(value) {
            this.settings.spacing.baseSize = parseInt(value);
            if (!this.settings.spacing.customized) {
                this.recalculateSpacing();
            }
            this.markChanged();
        },

        updateSpacingScale(value) {
            this.settings.spacing.scale = parseFloat(value);
            if (!this.settings.spacing.customized) {
                this.recalculateSpacing();
            }
            this.markChanged();
        },

        updateSpacingSize(size, value) {
            this.settings.spacing.sizes[size].value = parseInt(value);
            this.settings.spacing.customized = true;
            this.applyCSSVariable(`--space-${size}`, `${value}px`);
            this.markChanged();
        },

        toggleSpacingSize(size, enabled) {
            this.settings.spacing.sizes[size].enabled = enabled;
            this.markChanged();
        },

        recalculateSpacing() {
            const { baseSize, scale } = this.settings.spacing;
            const sizes = this.settings.spacing.sizes;

            sizes.xxs.value = Math.round(baseSize / Math.pow(scale, 3));
            sizes.xs.value = Math.round(baseSize / Math.pow(scale, 2));
            sizes.s.value = Math.round(baseSize / scale);
            sizes.m.value = Math.round(baseSize);
            sizes.l.value = Math.round(baseSize * scale);
            sizes.xl.value = Math.round(baseSize * Math.pow(scale, 2));
            sizes.xxl.value = Math.round(baseSize * Math.pow(scale, 3));

            this.settings.spacing.customized = false;
            this.applySpacingSettings();
        },

        // ============================================
        // Borders Methods
        // ============================================

        updateBorderSize(size, value) {
            this.settings.borders.sizes[size].value = parseInt(value);
            this.applyCSSVariable(`--border-${size}`, `${value}px`);
            this.markChanged();
        },

        toggleBorderSize(size, enabled) {
            this.settings.borders.sizes[size].enabled = enabled;
            this.markChanged();
        },

        // ============================================
        // Shadows Methods
        // ============================================

        updateShadowProperty(size, prop, value) {
            this.settings.shadows[size][prop] = parseFloat(value);
            this.applyShadowCSS(size);
            this.markChanged();
        },

        toggleShadow(size, enabled) {
            this.settings.shadows[size].enabled = enabled;
            this.markChanged();
        },

        applyShadowCSS(size) {
            const s = this.settings.shadows[size];
            const value = `${s.x}px ${s.y}px ${s.blur}px ${s.spread}px rgba(0,0,0,${s.opacity})`;
            this.applyCSSVariable(`--shadow-${size}`, value);
        },

        // ============================================
        // Radius Methods
        // ============================================

        updateRadiusSize(size, value) {
            this.settings.radius.sizes[size].value = parseInt(value);
            this.applyCSSVariable(`--radius-${size}`, `${value}px`);
            this.markChanged();
        },

        toggleRadiusSize(size, enabled) {
            this.settings.radius.sizes[size].enabled = enabled;
            this.markChanged();
        },

        // ============================================
        // Colorway Methods
        // ============================================

        updateColorway(wayName, prop, value) {
            this.settings.colorways[wayName][prop] = value;
            this.applyColorwaySettings();
            this.markChanged();
        },

        getPaletteOptions() {
            const shadeMap = [
                ['ultra-light', 90], ['light', 80], ['semi-light', 65],
                ['semi-dark', 35], ['dark', 20], ['ultra-dark', 10]
            ];
            const groups = [];

            // Fixed group — no parent, just swatches
            groups.push({
                group: 'Fixed',
                base: null,
                shades: [
                    { value: '#ffffff', label: 'White', hex: '#ffffff' },
                    { value: '#000000', label: 'Black', hex: '#000000' }
                ]
            });

            // Palette colors — parent + shade swatches
            for (const [name, data] of Object.entries(this.settings.colors)) {
                if (!data.enabled) continue;
                const displayName = name.charAt(0).toUpperCase() + name.slice(1);
                const hsl = hexToHsl(data.base);

                const base = {
                    value: `var(--${name})`,
                    label: displayName,
                    hex: data.base
                };

                const shades = [];
                for (const [shade, lightness] of shadeMap) {
                    const shadeHex = hslToHex(hsl.h, hsl.s, lightness);
                    const shadeLabel = shade.split('-').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
                    shades.push({
                        value: `var(--${name}-${shade})`,
                        label: `${displayName} ${shadeLabel}`,
                        hex: shadeHex
                    });
                }

                groups.push({ group: displayName, base, shades });
            }

            return groups;
        },

        resolveColorwayHex(value) {
            if (!value) return '#cccccc';
            if (value.startsWith('#')) return value;

            const match = value.match(/^var\(--(.+)\)$/);
            if (!match) return '#cccccc';

            const varName = match[1];
            const shadeMap = {
                'ultra-light': 90, 'light': 80, 'semi-light': 65,
                'semi-dark': 35, 'dark': 20, 'ultra-dark': 10
            };

            for (const [name, data] of Object.entries(this.settings.colors)) {
                if (varName === name) {
                    return data.base;
                }
                if (varName.startsWith(name + '-')) {
                    const shade = varName.slice(name.length + 1);
                    if (shade in shadeMap) {
                        const hsl = hexToHsl(data.base);
                        return hslToHex(hsl.h, hsl.s, shadeMap[shade]);
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
            let styleEl = document.getElementById('anti-colorway-overrides');
            if (!styleEl) {
                styleEl = document.createElement('style');
                styleEl.id = 'anti-colorway-overrides';
                document.head.appendChild(styleEl);
            }

            let css = '';
            for (const [wayName, data] of Object.entries(this.settings.colorways)) {
                const selector = wayName === 'default' ? ':root' : `[data-colorway="${wayName}"]`;
                const lines = [];
                const tokens = ['base', 'hard-contrast', 'soft-contrast', 'accent'];
                for (const token of tokens) {
                    if (data[token]) lines.push(`    --colorway-${token}: ${data[token]};`);
                }
                if (lines.length) {
                    css += `${selector} {\n${lines.join('\n')}\n}\n`;
                }
            }
            styleEl.textContent = css;
        },

        // ============================================
        // Change Tracking
        // ============================================

        markChanged() {
            this.hasChanges = true;
            localStorage.setItem('antiExplorer_data', JSON.stringify(this.settings));
        },

        // ============================================
        // CSS Variable Methods
        // ============================================

        applyCSSVariable(name, value) {
            document.documentElement.style.setProperty(name, value);
        },

        applyAllSettings() {
            this.applyTypographySettings();
            this.applyColorSettings();
            this.applySpacingSettings();
            this.applyBorderSettings();
            this.applyShadowSettings();
            this.applyRadiusSettings();
            this.applyColorwaySettings();
        },

        applyTypographySettings() {
            Object.entries(this.settings.typography.headings.sizes).forEach(([level, data]) => {
                const num = level.replace('h', '');
                this.applyCSSVariable(`--heading-${num}`, `${data.value}px`);
                this.applyCSSVariable(`--heading-${num}-line-height`, data.lineHeight);
                this.applyCSSVariable(`--heading-${num}-letter-spacing`, `${data.letterSpacing}em`);
                this.applyCSSVariable(`--heading-${num}-weight`, data.weight);
            });
            Object.entries(this.settings.typography.text.sizes).forEach(([size, data]) => {
                this.applyCSSVariable(`--text-${size}`, `${data.value}px`);
            });
        },

        applyColorSettings() {
            Object.entries(this.settings.colors).forEach(([name, data]) => {
                this.applyCSSVariable(`--${name}`, data.base);
                this.applyColorHues(name, data.base);
            });
        },

        applySpacingSettings() {
            Object.entries(this.settings.spacing.sizes).forEach(([size, data]) => {
                this.applyCSSVariable(`--space-${size}`, `${data.value}px`);
            });
        },

        applyBorderSettings() {
            Object.entries(this.settings.borders.sizes).forEach(([size, data]) => {
                this.applyCSSVariable(`--border-${size}`, `${data.value}px`);
            });
        },

        applyShadowSettings() {
            Object.keys(this.settings.shadows).forEach(size => {
                this.applyShadowCSS(size);
            });
        },

        applyRadiusSettings() {
            Object.entries(this.settings.radius.sizes).forEach(([size, data]) => {
                this.applyCSSVariable(`--radius-${size}`, `${data.value}px`);
            });
        },

        // ============================================
        // Save/Reset Methods
        // ============================================

        saveSettings() {
            this.isSaving = true;
            localStorage.setItem('antiExplorer_data', JSON.stringify(this.settings));
            this.originalSettings = JSON.parse(JSON.stringify(this.settings));
            this.hasChanges = false;
            this.isSaving = false;
            this.showNotification('Settings saved', 'success');
        },

        discardChanges() {
            localStorage.removeItem('antiExplorer_data');
            this.settings = JSON.parse(JSON.stringify(DEFAULT_SETTINGS));
            this.originalSettings = JSON.parse(JSON.stringify(this.settings));
            this.hasChanges = false;
            this.applyAllSettings();
            this.showNotification('Changes discarded', 'success');
        },

        resetSettings() {
            this.settings = JSON.parse(JSON.stringify(DEFAULT_SETTINGS));
            this.markChanged();
            this.applyAllSettings();
            this.showNotification('Settings reset to defaults', 'success');
        },

        resetCategory(category) {
            if (category === 'typography') {
                this.settings.typography = JSON.parse(JSON.stringify(DEFAULT_SETTINGS.typography));
                this.applyTypographySettings();
            } else if (category === 'colors') {
                this.settings.colors = JSON.parse(JSON.stringify(DEFAULT_SETTINGS.colors));
                this.applyColorSettings();
            } else if (category === 'spacing') {
                this.settings.spacing = JSON.parse(JSON.stringify(DEFAULT_SETTINGS.spacing));
                this.applySpacingSettings();
            } else if (category === 'borders') {
                this.settings.borders = JSON.parse(JSON.stringify(DEFAULT_SETTINGS.borders));
                this.settings.shadows = JSON.parse(JSON.stringify(DEFAULT_SETTINGS.shadows));
                this.settings.radius = JSON.parse(JSON.stringify(DEFAULT_SETTINGS.radius));
                this.applyBorderSettings();
                this.applyShadowSettings();
                this.applyRadiusSettings();
            } else if (category === 'colorways') {
                this.settings.colorways = JSON.parse(JSON.stringify(DEFAULT_SETTINGS.colorways));
                this.applyColorwaySettings();
            }
            this.markChanged();
            this.showNotification(`${category} reset`, 'success');
        },

        // ============================================
        // Export Methods
        // ============================================

        settingsToTokenJSON() {
            const s = this.settings;

            // Route flat panel colors into defaults.json section structure
            const semanticNames = ['info', 'success', 'warning', 'danger'];
            const brandColors = {};
            const neutralColors = {};
            const semanticColors = {};
            for (const [name, data] of Object.entries(s.colors)) {
                const entry = { enabled: data.enabled };
                if (data.base) entry.color = data.base;
                if (name === 'neutral') {
                    neutralColors[name] = entry;
                } else if (semanticNames.includes(name)) {
                    semanticColors[name] = entry;
                } else {
                    brandColors[name] = entry;
                }
            }

            // Strip customized flags (panel-only UI state)
            const stripCustomized = (obj) => {
                const copy = JSON.parse(JSON.stringify(obj));
                delete copy.customized;
                return copy;
            };

            return {
                typography: {
                    headings: stripCustomized(s.typography.headings),
                    text: stripCustomized(s.typography.text)
                },
                color: {
                    sections: {
                        brand: { label: 'Brand Colors', colors: brandColors },
                        neutral: { label: 'Neutral Colors', colors: neutralColors },
                        semantic: { label: 'Semantic Colors', colors: semanticColors }
                    },
                    hues: {
                        'ultra-light': { value: 90, enabled: true },
                        'light': { value: 80, enabled: true },
                        'semi-light': { value: 65, enabled: true },
                        'semi-dark': { value: 35, enabled: true },
                        'dark': { value: 20, enabled: true },
                        'ultra-dark': { value: 10, enabled: true }
                    },
                    colorways: s.colorways
                },
                spacing: stripCustomized(s.spacing),
                borders: s.borders,
                shadows: s.shadows,
                radius: s.radius
            };
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
            this.notification = { message, type };
            setTimeout(() => {
                this.notification = null;
            }, 3000);
        }
    }));
}

// ============================================
// Panel HTML Template
// ============================================

const getPanelHTML = () => `
        <!-- Style Editor Panel -->
        <div x-data="stylePanel"
             class="anti-panel anti-panel-container"
             :class="{ 'settings-open': settingsOpen, 'is-hidden': !isOpen }"
             @keydown.escape.window="colorwayDropdownId ? closeColorwayDropdown() : closeSettings()">

            <!-- Navigation Panel -->
            <nav class="anti-nav">
                <!-- Nav Header -->
                <div class="anti-nav__header">
                    <div class="anti-nav__logo">
                        ${ICONS.palette}
                    </div>
                    <h1 class="anti-nav__title">Styles</h1>
                    <button
                        class="anti-nav__close"
                        @click="togglePanel"
                        aria-label="Close panel"
                        title="Close panel"
                    >
                        ${ICONS.close}
                    </button>
                </div>

                <!-- Nav Menu -->
                <div class="anti-nav__menu">
                    <template x-for="cat in categories" :key="cat.id">
                        <button
                            class="anti-nav__item"
                            :class="{ 'is-active': activeCategory === cat.id }"
                            @click="openCategory(cat.id)"
                            :title="cat.label"
                        >
                            <span class="anti-nav__item-icon" x-html="icons[cat.icon]"></span>
                            <span class="anti-nav__item-label" x-text="cat.label"></span>
                        </button>
                    </template>
                </div>

                <!-- Nav Footer -->
                <div class="anti-nav__footer">
                    <button
                        class="anti-nav__item"
                        @click="exportCSS"
                        title="Export CSS"
                    >
                        <span class="anti-nav__item-icon">${ICONS.export}</span>
                        <span class="anti-nav__item-type">CSS</span>
                        <span class="anti-nav__item-label">Export CSS</span>
                    </button>
                    <button
                        class="anti-nav__item"
                        @click="exportJSON"
                        title="Export JSON"
                    >
                        <span class="anti-nav__item-icon">${ICONS.export}</span>
                        <span class="anti-nav__item-type">JSON</span>
                        <span class="anti-nav__item-label">Export JSON</span>
                    </button>
                </div>
            </nav>

            <!-- Settings Panel -->
            <aside class="anti-settings">
                <!-- Settings Header -->
                <header class="anti-settings__header">
                    <button
                        class="anti-settings__back"
                        @click="closeSettings"
                        aria-label="Back to navigation"
                        title="Back"
                    >
                        ${ICONS.chevronLeft}
                    </button>
                    <h2 class="anti-settings__title" x-text="currentCategory?.label || 'Settings'"></h2>
                    <button
                        class="anti-settings__close"
                        @click="togglePanel"
                        aria-label="Close panel"
                        title="Close panel"
                    >
                        ${ICONS.close}
                    </button>
                </header>

                <!-- Settings Tabs -->
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

                    <!-- ==================== TYPOGRAPHY: HEADINGS ==================== -->
                    <div x-show="activeCategory === 'typography' && activeTab === 'headings'" class="anti-settings__panel">
                        <div class="anti-section-title">Base</div>

                        <div class="anti-size-section is-enabled">
                            <div class="anti-size-header">
                                <span class="anti-size-name">Font Size</span>
                            </div>
                            <div class="anti-size-controls" style="display: block;">
                                <div class="anti-control-row">
                                    <input type="range" class="anti-range"
                                        min="12" max="24" step="1"
                                        :value="settings.typography.headings.baseSize"
                                        @input="updateHeadingBaseSize($event.target.value)">
                                    <div class="anti-control-value">
                                        <input type="number"
                                            :value="settings.typography.headings.baseSize"
                                            @change="updateHeadingBaseSize($event.target.value)">
                                        <span>px</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="anti-size-section is-enabled">
                            <div class="anti-size-header">
                                <span class="anti-size-name">Type Scale</span>
                            </div>
                            <div class="anti-size-controls" style="display: block;">
                                <div class="anti-control-row">
                                    <input type="range" class="anti-range"
                                        min="1" max="2" step="0.001"
                                        :value="settings.typography.headings.scale"
                                        @input="updateHeadingScale($event.target.value)">
                                    <div class="anti-control-value">
                                        <input type="number" step="0.001"
                                            :value="settings.typography.headings.scale"
                                            @change="updateHeadingScale($event.target.value)">
                                    </div>
                                </div>
                                <select class="anti-select" style="margin-top: 12px;" @change="updateHeadingScale($event.target.value)">
                                    <option value="">Custom</option>
                                    <template x-for="scale in typeScales" :key="scale.value">
                                        <option :value="scale.value" x-text="scale.label"
                                            :selected="settings.typography.headings.scale === scale.value"></option>
                                    </template>
                                </select>
                            </div>
                        </div>

                        <div class="anti-section-title">Individual Sizes</div>

                        <div class="anti-recalc-notice" :class="{ 'is-visible': settings.typography.headings.customized }">
                            Sizes have been manually edited.
                            <button @click="recalculateHeadings()">Recalculate from scale</button>
                        </div>

                        <template x-for="level in ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']" :key="level">
                            <div class="anti-size-section" :class="{ 'is-enabled': settings.typography.headings.sizes[level].enabled }">
                                <div class="anti-size-header">
                                    <span class="anti-size-name" x-text="level.toUpperCase()"></span>
                                    <label class="anti-toggle">
                                        <input type="checkbox"
                                            :checked="settings.typography.headings.sizes[level].enabled"
                                            @change="toggleHeadingSize(level, $event.target.checked)">
                                        <span class="anti-toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="anti-size-controls">
                                    <div class="anti-control-row">
                                        <input type="range" class="anti-range"
                                            min="12" max="128" step="1"
                                            :value="settings.typography.headings.sizes[level].value"
                                            @input="updateHeadingSize(level, $event.target.value)">
                                        <div class="anti-control-value">
                                            <input type="number"
                                                :value="settings.typography.headings.sizes[level].value"
                                                @change="updateHeadingSize(level, $event.target.value)">
                                            <span>px</span>
                                        </div>
                                    </div>
                                    <div class="anti-control-group" style="margin-top: 8px;">
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                            <div>
                                                <label class="anti-control-label" style="font-size: 11px;">Line Height</label>
                                                <input type="number" class="anti-input" step="0.05" min="0.5" max="3"
                                                    :value="settings.typography.headings.sizes[level].lineHeight"
                                                    @change="updateHeadingProperty(level, 'lineHeight', $event.target.value)">
                                            </div>
                                            <div>
                                                <label class="anti-control-label" style="font-size: 11px;">Letter Spacing</label>
                                                <input type="number" class="anti-input" step="0.01" min="-0.1" max="0.5"
                                                    :value="settings.typography.headings.sizes[level].letterSpacing"
                                                    @change="updateHeadingProperty(level, 'letterSpacing', $event.target.value)">
                                            </div>
                                        </div>
                                        <div style="margin-top: 8px;">
                                            <label class="anti-control-label" style="font-size: 11px;">Weight</label>
                                            <select class="anti-select"
                                                @change="updateHeadingProperty(level, 'weight', $event.target.value)">
                                                <template x-for="w in fontWeights" :key="w">
                                                    <option :value="w" x-text="w"
                                                        :selected="settings.typography.headings.sizes[level].weight === w"></option>
                                                </template>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <button class="anti-btn anti-btn--reset" @click="resetCategory('typography')">
                            Reset Typography
                        </button>
                    </div>

                    <!-- ==================== TYPOGRAPHY: TEXT ==================== -->
                    <div x-show="activeCategory === 'typography' && activeTab === 'text'" class="anti-settings__panel">
                        <div class="anti-section-title">Base</div>

                        <div class="anti-size-section is-enabled">
                            <div class="anti-size-header">
                                <span class="anti-size-name">Font Size</span>
                            </div>
                            <div class="anti-size-controls" style="display: block;">
                                <div class="anti-control-row">
                                    <input type="range" class="anti-range"
                                        min="12" max="24" step="1"
                                        :value="settings.typography.text.baseSize"
                                        @input="updateTextBaseSize($event.target.value)">
                                    <div class="anti-control-value">
                                        <input type="number"
                                            :value="settings.typography.text.baseSize"
                                            @change="updateTextBaseSize($event.target.value)">
                                        <span>px</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="anti-size-section is-enabled">
                            <div class="anti-size-header">
                                <span class="anti-size-name">Type Scale</span>
                            </div>
                            <div class="anti-size-controls" style="display: block;">
                                <div class="anti-control-row">
                                    <input type="range" class="anti-range"
                                        min="1" max="2" step="0.001"
                                        :value="settings.typography.text.scale"
                                        @input="updateTextScale($event.target.value)">
                                    <div class="anti-control-value">
                                        <input type="number" step="0.001"
                                            :value="settings.typography.text.scale"
                                            @change="updateTextScale($event.target.value)">
                                    </div>
                                </div>
                                <select class="anti-select" style="margin-top: 12px;" @change="updateTextScale($event.target.value)">
                                    <option value="">Custom</option>
                                    <template x-for="scale in typeScales" :key="scale.value">
                                        <option :value="scale.value" x-text="scale.label"
                                            :selected="settings.typography.text.scale === scale.value"></option>
                                    </template>
                                </select>
                            </div>
                        </div>

                        <div class="anti-section-title">Individual Sizes</div>

                        <div class="anti-recalc-notice" :class="{ 'is-visible': settings.typography.text.customized }">
                            Sizes have been manually edited.
                            <button @click="recalculateText()">Recalculate from scale</button>
                        </div>

                        <template x-for="size in ['xl', 'l', 'm', 's', 'xs']" :key="size">
                            <div class="anti-size-section" :class="{ 'is-enabled': settings.typography.text.sizes[size].enabled }">
                                <div class="anti-size-header">
                                    <span class="anti-size-name" x-text="size.toUpperCase()"></span>
                                    <label class="anti-toggle">
                                        <input type="checkbox"
                                            :checked="settings.typography.text.sizes[size].enabled"
                                            @change="toggleTextSize(size, $event.target.checked)">
                                        <span class="anti-toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="anti-size-controls">
                                    <div class="anti-control-row">
                                        <input type="range" class="anti-range"
                                            min="10" max="48" step="1"
                                            :value="settings.typography.text.sizes[size].value"
                                            @input="updateTextSize(size, $event.target.value)">
                                        <div class="anti-control-value">
                                            <input type="number"
                                                :value="settings.typography.text.sizes[size].value"
                                                @change="updateTextSize(size, $event.target.value)">
                                            <span>px</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- ==================== COLORS: PALETTE ==================== -->
                    <div x-show="activeCategory === 'colors' && activeTab === 'palette'" class="anti-settings__panel">
                        <template x-for="(color, name) in settings.colors" :key="name">
                            <div class="anti-size-section" :class="{ 'is-enabled': color.enabled }">
                                <div class="anti-size-header">
                                    <span class="anti-size-name" x-text="name.charAt(0).toUpperCase() + name.slice(1)"></span>
                                    <label class="anti-toggle">
                                        <input type="checkbox"
                                            :checked="color.enabled"
                                            @change="toggleColor(name, $event.target.checked)">
                                        <span class="anti-toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="anti-size-controls">
                                    <div class="anti-control-group">
                                        <div class="anti-color-input">
                                            <span class="anti-color-swatch" :style="'background:' + color.base"></span>
                                            <input type="text" data-coloris
                                                :value="color.base"
                                                @input="updateColorBase(name, $event.target.value)">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <button class="anti-btn anti-btn--reset" @click="resetCategory('colors')">
                            Reset Colors
                        </button>
                    </div>

                    <!-- ==================== SPACING ==================== -->
                    <div x-show="activeCategory === 'spacing'" class="anti-settings__panel">
                        <div class="anti-section-title">Base</div>

                        <div class="anti-size-section is-enabled">
                            <div class="anti-size-header">
                                <span class="anti-size-name">Spacing</span>
                            </div>
                            <div class="anti-size-controls" style="display: block;">
                                <div class="anti-control-row">
                                    <input type="range" class="anti-range"
                                        min="4" max="64" step="1"
                                        :value="settings.spacing.baseSize"
                                        @input="updateSpacingBaseSize($event.target.value)">
                                    <div class="anti-control-value">
                                        <input type="number"
                                            :value="settings.spacing.baseSize"
                                            @change="updateSpacingBaseSize($event.target.value)">
                                        <span>px</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="anti-size-section is-enabled">
                            <div class="anti-size-header">
                                <span class="anti-size-name">Scale</span>
                            </div>
                            <div class="anti-size-controls" style="display: block;">
                                <div class="anti-control-row">
                                    <input type="range" class="anti-range"
                                        min="1" max="3" step="0.001"
                                        :value="settings.spacing.scale"
                                        @input="updateSpacingScale($event.target.value)">
                                    <div class="anti-control-value">
                                        <input type="number" step="0.001"
                                            :value="settings.spacing.scale"
                                            @change="updateSpacingScale($event.target.value)">
                                    </div>
                                </div>
                                <select class="anti-select" style="margin-top: 12px;" @change="updateSpacingScale($event.target.value)">
                                    <option value="">Custom</option>
                                    <template x-for="scale in spacingScales" :key="scale.value">
                                        <option :value="scale.value" x-text="scale.label"
                                            :selected="settings.spacing.scale === scale.value"></option>
                                    </template>
                                </select>
                            </div>
                        </div>

                        <div class="anti-section-title">Individual Sizes</div>

                        <div class="anti-recalc-notice" :class="{ 'is-visible': settings.spacing.customized }">
                            Sizes have been manually edited.
                            <button @click="recalculateSpacing()">Recalculate from scale</button>
                        </div>

                        <template x-for="size in ['xxs', 'xs', 's', 'm', 'l', 'xl', 'xxl']" :key="size">
                            <div class="anti-size-section" :class="{ 'is-enabled': settings.spacing.sizes[size].enabled }">
                                <div class="anti-size-header">
                                    <span class="anti-size-name" x-text="size.toUpperCase()"></span>
                                    <label class="anti-toggle">
                                        <input type="checkbox"
                                            :checked="settings.spacing.sizes[size].enabled"
                                            @change="toggleSpacingSize(size, $event.target.checked)">
                                        <span class="anti-toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="anti-size-controls">
                                    <div class="anti-control-row">
                                        <input type="range" class="anti-range"
                                            min="1" max="128" step="1"
                                            :value="settings.spacing.sizes[size].value"
                                            @input="updateSpacingSize(size, $event.target.value)">
                                        <div class="anti-control-value">
                                            <input type="number"
                                                :value="settings.spacing.sizes[size].value"
                                                @change="updateSpacingSize(size, $event.target.value)">
                                            <span>px</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <button class="anti-btn anti-btn--reset" @click="resetCategory('spacing')">
                            Reset Spacing
                        </button>
                    </div>

                    <!-- ==================== BORDERS: BORDERS TAB ==================== -->
                    <div x-show="activeCategory === 'borders' && activeTab === 'borders'" class="anti-settings__panel">
                        <div class="anti-section-title">Border Widths</div>

                        <template x-for="size in ['s', 'm', 'l']" :key="size">
                            <div class="anti-size-section" :class="{ 'is-enabled': settings.borders.sizes[size].enabled }">
                                <div class="anti-size-header">
                                    <span class="anti-size-name" x-text="size.toUpperCase()"></span>
                                    <label class="anti-toggle">
                                        <input type="checkbox"
                                            :checked="settings.borders.sizes[size].enabled"
                                            @change="toggleBorderSize(size, $event.target.checked)">
                                        <span class="anti-toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="anti-size-controls">
                                    <div class="anti-control-row">
                                        <input type="range" class="anti-range"
                                            min="1" max="16" step="1"
                                            :value="settings.borders.sizes[size].value"
                                            @input="updateBorderSize(size, $event.target.value)">
                                        <div class="anti-control-value">
                                            <input type="number"
                                                :value="settings.borders.sizes[size].value"
                                                @change="updateBorderSize(size, $event.target.value)">
                                            <span>px</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <button class="anti-btn anti-btn--reset" @click="resetCategory('borders')">
                            Reset Borders
                        </button>
                    </div>

                    <!-- ==================== BORDERS: SHADOWS TAB ==================== -->
                    <div x-show="activeCategory === 'borders' && activeTab === 'shadows'" class="anti-settings__panel">
                        <template x-for="size in ['xs', 's', 'm', 'l', 'xl']" :key="size">
                            <div class="anti-size-section" :class="{ 'is-enabled': settings.shadows[size].enabled }">
                                <div class="anti-size-header">
                                    <span class="anti-size-name" x-text="size.toUpperCase()"></span>
                                    <label class="anti-toggle">
                                        <input type="checkbox"
                                            :checked="settings.shadows[size].enabled"
                                            @change="toggleShadow(size, $event.target.checked)">
                                        <span class="anti-toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="anti-size-controls">
                                    <div class="anti-control-group">
                                        <label class="anti-control-label">X Offset</label>
                                        <div class="anti-control-row">
                                            <input type="range" class="anti-range" min="-50" max="50"
                                                :value="settings.shadows[size].x"
                                                @input="updateShadowProperty(size, 'x', $event.target.value)">
                                            <div class="anti-control-value">
                                                <input type="number"
                                                    :value="settings.shadows[size].x"
                                                    @change="updateShadowProperty(size, 'x', $event.target.value)">
                                                <span>px</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="anti-control-group">
                                        <label class="anti-control-label">Y Offset</label>
                                        <div class="anti-control-row">
                                            <input type="range" class="anti-range" min="-50" max="50"
                                                :value="settings.shadows[size].y"
                                                @input="updateShadowProperty(size, 'y', $event.target.value)">
                                            <div class="anti-control-value">
                                                <input type="number"
                                                    :value="settings.shadows[size].y"
                                                    @change="updateShadowProperty(size, 'y', $event.target.value)">
                                                <span>px</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="anti-control-group">
                                        <label class="anti-control-label">Blur</label>
                                        <div class="anti-control-row">
                                            <input type="range" class="anti-range" min="0" max="100"
                                                :value="settings.shadows[size].blur"
                                                @input="updateShadowProperty(size, 'blur', $event.target.value)">
                                            <div class="anti-control-value">
                                                <input type="number"
                                                    :value="settings.shadows[size].blur"
                                                    @change="updateShadowProperty(size, 'blur', $event.target.value)">
                                                <span>px</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="anti-control-group">
                                        <label class="anti-control-label">Spread</label>
                                        <div class="anti-control-row">
                                            <input type="range" class="anti-range" min="-50" max="50"
                                                :value="settings.shadows[size].spread"
                                                @input="updateShadowProperty(size, 'spread', $event.target.value)">
                                            <div class="anti-control-value">
                                                <input type="number"
                                                    :value="settings.shadows[size].spread"
                                                    @change="updateShadowProperty(size, 'spread', $event.target.value)">
                                                <span>px</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="anti-control-group">
                                        <label class="anti-control-label">Opacity</label>
                                        <div class="anti-control-row">
                                            <input type="range" class="anti-range" min="0" max="1" step="0.01"
                                                :value="settings.shadows[size].opacity"
                                                @input="updateShadowProperty(size, 'opacity', $event.target.value)">
                                            <div class="anti-control-value">
                                                <input type="number" step="0.01"
                                                    :value="settings.shadows[size].opacity"
                                                    @change="updateShadowProperty(size, 'opacity', $event.target.value)">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- ==================== BORDERS: RADIUS TAB ==================== -->
                    <div x-show="activeCategory === 'borders' && activeTab === 'radius'" class="anti-settings__panel">
                        <template x-for="size in ['xs', 's', 'm', 'l', 'xl', 'full']" :key="size">
                            <div class="anti-size-section" :class="{ 'is-enabled': settings.radius.sizes[size].enabled }">
                                <div class="anti-size-header">
                                    <span class="anti-size-name" x-text="size === 'full' ? 'Full' : size.toUpperCase()"></span>
                                    <label class="anti-toggle">
                                        <input type="checkbox"
                                            :checked="settings.radius.sizes[size].enabled"
                                            @change="toggleRadiusSize(size, $event.target.checked)">
                                        <span class="anti-toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="anti-size-controls" x-show="size !== 'full'">
                                    <div class="anti-control-row">
                                        <input type="range" class="anti-range"
                                            min="0" max="64" step="1"
                                            :value="settings.radius.sizes[size].value"
                                            @input="updateRadiusSize(size, $event.target.value)">
                                        <div class="anti-control-value">
                                            <input type="number"
                                                :value="settings.radius.sizes[size].value"
                                                @change="updateRadiusSize(size, $event.target.value)">
                                            <span>px</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="anti-size-controls" x-show="size === 'full'" style="color: var(--anti-text-muted); font-size: 12px;">
                                    9999px (pill/circle)
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- ==================== COLORS: COLORWAYS ==================== -->
                    <div x-show="activeCategory === 'colors' && activeTab === 'colorways'" class="anti-settings__panel">
                        <template x-for="(way, wayName) in settings.colorways" :key="wayName">
                            <div class="anti-size-section is-enabled">
                                <div class="anti-size-header">
                                    <span class="anti-size-name" x-text="wayName.charAt(0).toUpperCase() + wayName.slice(1)"></span>
                                </div>
                                <div class="anti-size-controls">
                                    <template x-for="token in [{id:'base',label:'Base'},{id:'hard-contrast',label:'Hard Contrast'},{id:'soft-contrast',label:'Soft Contrast'},{id:'accent',label:'Accent'}]" :key="token.id">
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
                                                                    <!-- Parent color row (palette colors) -->
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
                                                                    <!-- Group label for Fixed -->
                                                                    <div x-show="!group.base" class="anti-colorway-picker__group-label"
                                                                        x-text="group.group"></div>
                                                                    <!-- Shade swatches row -->
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
                                                                :value="way[token.id].startsWith('#') ? way[token.id] : ''"
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

                        <button class="anti-btn anti-btn--reset" @click="resetCategory('colorways')">
                            Reset Colorways
                        </button>
                    </div>

                </main>

                <!-- Settings Footer -->
                <footer class="anti-settings__footer">
                    <div class="anti-settings__actions">
                        <button
                            class="anti-btn anti-btn--secondary"
                            @click="resetSettings"
                        >
                            Reset All
                        </button>
                        <button
                            class="anti-btn anti-btn--primary"
                            @click="saveSettings"
                            :disabled="!hasChanges || isSaving"
                        >
                            <span x-show="!isSaving">Save</span>
                            <span x-show="isSaving">Saving...</span>
                        </button>
                    </div>
                </footer>
            </aside>

            <!-- Notification -->
            <div
                x-show="notification"
                x-transition
                class="anti-notification"
                :class="'anti-notification--' + (notification?.type || 'success')"
                x-text="notification?.message"
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
