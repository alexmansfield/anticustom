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
// Color Conversion Utilities (mirrors generate.php OKLCH ramp math)
//
// EXACT JS port of styles/generate.php OKLCH + color-mix math. The explorer's
// live preview MUST produce byte-identical hex to the generator.
// Verified: colorShade('#737373',65) === '#8f8f8f'; colorShade('#336699',90) === '#bbe2ff'.
// ============================================

function cbrtSigned(x){ return x < 0 ? -Math.pow(-x, 1/3) : Math.pow(x, 1/3); }
function srgbDecode(v){ const s = v<0?-1:1; v=Math.abs(v); return s*(v<=0.04045 ? v/12.92 : Math.pow((v+0.055)/1.055, 2.4)); }
function srgbEncode(v){ const s = v<0?-1:1; v=Math.abs(v); return s*(v>0.0031308 ? 1.055*Math.pow(v,1/2.4)-0.055 : 12.92*v); }

function linearToOklab(r,g,b){
    const l = 0.4122214708*r + 0.5363325363*g + 0.0514459929*b;
    const m = 0.2119034982*r + 0.6806995451*g + 0.1073969566*b;
    const s = 0.0883024619*r + 0.2817188376*g + 0.6299787005*b;
    const l_=cbrtSigned(l), m_=cbrtSigned(m), s_=cbrtSigned(s);
    return [
        0.2104542553*l_ + 0.7936177850*m_ - 0.0040720468*s_,
        1.9779984951*l_ - 2.4285922050*m_ + 0.4505937099*s_,
        0.0259040371*l_ + 0.7827717662*m_ - 0.8086757660*s_,
    ];
}
function oklchToLinear(L,C,H){
    const hr = H*Math.PI/180, a = C*Math.cos(hr), b = C*Math.sin(hr);
    const l_ = L + 0.3963377773761749*a + 0.2158037573099136*b;
    const m_ = L - 0.1055613458156586*a - 0.0638541728258133*b;
    const s_ = L - 0.0894841775298119*a - 1.2914855480194092*b;
    const l=l_**3, m=m_**3, s=s_**3;
    return [
         4.0767416621*l - 3.3077115913*m + 0.2309699292*s,
        -1.2684380046*l + 2.6097574011*m - 0.3413193965*s,
        -0.0041960863*l - 0.7034186147*m + 1.7076147010*s,
    ];
}
function hexToOklch(hex){
    hex = hex.replace('#','');
    const r=srgbDecode(parseInt(hex.substr(0,2),16)/255);
    const g=srgbDecode(parseInt(hex.substr(2,2),16)/255);
    const b=srgbDecode(parseInt(hex.substr(4,2),16)/255);
    const [L,a,bb]=linearToOklab(r,g,b);
    let H = Math.atan2(bb,a)*180/Math.PI; if (H<0) H+=360;
    return [L, Math.sqrt(a*a+bb*bb), H];
}
function oklchInGamut(L,C,H){ return oklchToLinear(L,C,H).every(c => c>=-0.0001 && c<=1.0001); }
function oklchClipToHex(L,C,H){
    const enc = oklchToLinear(L,C,H).map(c => Math.max(0, Math.min(1, srgbEncode(c))));
    return '#' + enc.map(c => Math.round(c*255).toString(16).padStart(2,'0')).join('');
}
function oklchToHex(L,C,H){
    if (L>=1) return '#ffffff'; if (L<=0) return '#000000';
    if (oklchInGamut(L,C,H)) return oklchClipToHex(L,C,H);
    const JND=0.02, EPS=0.0001, hr=H*Math.PI/180;
    let min=0, max=C, minIn=true, chroma=C;
    while (max-min > EPS){
        chroma=(min+max)/2;
        if (minIn && oklchInGamut(L,chroma,H)){ min=chroma; continue; }
        const clip = oklchToLinear(L,chroma,H).map(c => srgbDecode(Math.max(0,Math.min(1,srgbEncode(c)))));
        const [cl,ca,cb]=linearToOklab(clip[0],clip[1],clip[2]);
        const E=Math.sqrt((cl-L)**2 + (ca-chroma*Math.cos(hr))**2 + (cb-chroma*Math.sin(hr))**2);
        if (E<JND){ if (JND-E<EPS) return oklchClipToHex(L,chroma,H); minIn=false; min=chroma; }
        else max=chroma;
    }
    return oklchClipToHex(L,chroma,H);
}
// stop `value` is 0..100 → OKLCH L by /100. L100/L0 → literal white/black (handled by caller for stops).
function colorShade(hex, L100){ const [,C,H]=hexToOklch(hex); return oklchToHex(L100/100, C, H); }

// state pole for a resolved fill hex (ADR 0026 v1): L>0.5 → white, else black.
function statePole(hex){ const [L]=hexToOklch(hex); return L>0.5 ? 'white' : 'black'; }

// color-mix(in srgb, fillHex, pole pct%) resolved to hex (mirrors CSS color-mix).
function colorMix(fillHex, pole, pct){ const p=pct/100, pv=pole==='white'?1:0, h=fillHex.replace('#',''); let o='#'; for(let i=0;i<6;i+=2){const c=parseInt(h.substr(i,2),16)/255; o+=Math.round((c*(1-p)+pv*p)*255).toString(16).padStart(2,'0');} return o; }

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
const SETTINGS_VERSION = 6;

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

        // Palette slot-picker state
        paletteDropdownId: null,
        paletteCustomMode: null,

        // Palette management state
        addingPalette: false,
        newPaletteName: '',

        // Schema-driven data
        schema: null,
        settings: {},
        defaultSettings: {},

        // Spec lifecycle (ADR 0023): the followed spec drives which token rows are
        // spec-guaranteed (non-deletable) vs custom, and seeds default labels.
        spec: null,
        showTokenNames: false,       // CSS var names hidden by default (ADR 0023)
        addingStepFor: null,         // settingsKey of the family currently adding a custom token
        newStepKey: '',              // in-progress custom-token key

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

            // Followed spec (ADR 0023) + show-token-names preference
            this.spec = window.ANTI_SPEC || null;
            this.showTokenNames = localStorage.getItem('antiExplorer_tokenNames') === 'true';

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

            // Close palette slot-picker dropdown on outside click
            document.addEventListener('click', (e) => {
                if (this.paletteDropdownId && !e.target.closest('.anti-colorway-picker') && !e.target.closest('.clr-picker')) {
                    this.closePaletteDropdown();
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
            if (section.properties) return 'palettes';
            if (section.settingsKey === 'color.colors') return 'ramp';
            if (section.settingsKey === 'color.stops') return 'stops';
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
        // Global viewport anchors (ADR 0018) — the two widths every scale
        // family's fluid clamps interpolate between. Lives outside the axes.
        // ============================================

        viewport() {
            return this.settings.viewport || { mobile: 390, desktop: 1440 };
        },

        setViewport(device, value) {
            if (!this.settings.viewport) this.settings.viewport = { mobile: 390, desktop: 1440 };
            this.settings.viewport[device] = parseInt(value, 10) || 0;
            this.applyAllSettings();   // every scale family's clamp depends on this
            this.markChanged();
        },

        /** True when the active panel contains any scale section (viewport UI). */
        get panelHasScale() {
            const panel = this.getCurrentPanel();
            if (!panel) return false;
            const tabs = panel.tabs || [panel];
            return tabs.some(t => (t.sections || [t]).some(s => this.getSectionType(s) === 'scale'));
        },

        /** True when the active panel has any size family (scale/pickone/composite) —
         *  the families that carry per-token spec rows and the token-names toggle. */
        get panelHasSizeTokens() {
            const panel = this.getCurrentPanel();
            if (!panel) return false;
            const tabs = panel.tabs || [panel];
            return tabs.some(t => (t.sections || [t]).some(s =>
                ['scale', 'pickone', 'composite'].includes(this.getSectionType(s))));
        },

        // ============================================
        // Size-family helpers (open sets, key-identity, per-device anchors)
        // ============================================

        /** The settings object for a size family ({ mode, default, scale, sizes, custom }). */
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

        // ============================================
        // Spec lifecycle (ADR 0023): spec vs custom rows, labels, aliasing.
        // A step's emitted key (cssPrefix + key) decides membership; the followed
        // spec (window.ANTI_SPEC) is the source of truth, mirroring styles/verify.
        // ============================================

        /** The followed spec's guaranteed token keys as a Set (emitted `--{key}` names). */
        specKeySet() {
            return new Set(Object.keys(this.spec?.tokens || {}));
        },

        /** True when a step is defined by the followed spec (guaranteed, non-deletable). */
        isSpecStep(section, key) {
            return this.specKeySet().has(`${section.cssPrefix || ''}${key}`);
        },

        /** The spec's default label for a step's emitted key, if the spec defines one. */
        specLabel(section, key) {
            return this.spec?.tokens?.[`${section.cssPrefix || ''}${key}`]?.label || null;
        },

        /** Display label for a step: site override → spec default → uppercased key. */
        stepLabel(section, key) {
            const site = this.familyOf(section).sizes?.[key]?.label;
            return site || this.specLabel(section, key) || this.getItemLabel(key);
        },

        setStepLabel(section, key, value) {
            const fam = this.familyOf(section);
            if (!fam.sizes?.[key]) return;
            const v = (value || '').trim();
            // Relabeling touches nothing downstream (the variable is the spec's, not
            // the label's) — store a site override, or clear it to fall back to spec.
            if (v && v !== this.specLabel(section, key) && v !== this.getItemLabel(key)) {
                fam.sizes[key].label = v;
            } else {
                delete fam.sizes[key].label;
            }
            this.markChanged();
        },

        toggleTokenNames() {
            this.showTokenNames = !this.showTokenNames;
            localStorage.setItem('antiExplorer_tokenNames', this.showTokenNames.toString());
        },

        // ---- aliasing (ADR 0023): point one step at a sibling's value ----

        aliasOf(section, key) {
            return this.familyOf(section).sizes?.[key]?.alias || null;
        },

        /** Sibling step keys a step may alias to (any other member without a cycle). */
        aliasTargets(section, key) {
            return this.sizeKeys(section).filter(k => k !== key && this.aliasOf(section, k) !== key);
        },

        setAlias(section, key, target) {
            const fam = this.familyOf(section);
            if (!fam.sizes?.[key]) return;
            if (target) {
                fam.sizes[key].alias = target;   // generator prefers alias; position/value stay dormant
            } else {
                delete fam.sizes[key].alias;
            }
            this.applyFamily(section);
            this.markChanged();
        },

        // ---- custom token add / delete (spec rows are non-deletable) ----

        startAddStep(section) {
            this.addingStepFor = section.settingsKey;
            this.newStepKey = '';
        },
        cancelAddStep() {
            this.addingStepFor = null;
            this.newStepKey = '';
        },

        /** Create a custom token. Scale steps get a position past the current max;
         *  pick-one/composite steps get a seeded value so they emit immediately. */
        addCustomStep(section) {
            const fam = this.familyOf(section);
            const raw = (this.newStepKey || '').trim().toLowerCase();
            const key = raw.replace(/[^a-z0-9-]/g, '');
            if (!key) { this.showNotification('Enter a token key', 'error'); return; }
            if (fam.sizes?.[key]) { this.showNotification(`"${key}" already exists`, 'error'); return; }
            if (!fam.sizes) fam.sizes = {};

            const type = this.getSectionType(section);
            if (type === 'scale') {
                const maxPos = Math.max(0, ...this.sizeKeys(section).map(k => fam.sizes[k].position ?? 0));
                fam.sizes[key] = { position: maxPos + 1 };
            } else if (type === 'composite') {
                const seed = {};
                for (const f of section.composite.fields) seed[f.id] = f.id === 'opacity' ? 0.1 : 0;
                fam.sizes[key] = seed;
            } else { // pickone
                fam.sizes[key] = { value: section.value?.min ?? 1 };
            }
            this.cancelAddStep();
            this.applyFamily(section);
            this.markChanged();
        },

        /** Delete a step — refused for spec tokens (ADR 0023: they're guaranteed). */
        deleteStep(section, key) {
            if (this.isSpecStep(section, key)) {
                this.showNotification(`"${this.stepLabel(section, key)}" is defined by the ${this.spec?.name} spec — removing it would take your site out of spec.`, 'error');
                return;
            }
            const fam = this.familyOf(section);
            if (!fam.sizes?.[key]) return;
            delete fam.sizes[key];
            if (fam.custom) delete fam.custom[key];
            if (fam.customStyle) delete fam.customStyle[key];
            if (fam.default === key) fam.default = this.sizeKeys(section)[0];   // keep an anchor
            this.applyFamily(section);
            this.markChanged();
        },

        /** Re-apply whichever emission a family uses (used after structural edits). */
        applyFamily(section) {
            const type = this.getSectionType(section);
            if (type === 'scale') this.applyScale(section);
            else if (type === 'pickone') this.applyPickOne(section);
            else if (type === 'composite') this.applyComposite(section);
        },

        familyMode(section) {
            return this.familyOf(section).mode || 'scale';
        },

        anchorPosition(section) {
            const fam = this.familyOf(section);
            return fam.sizes?.[fam.default]?.position ?? 0;
        },

        /** Per-device anchor value / ratio from the family's scale block. */
        deviceAnchor(section, device) {
            return this.familyOf(section).scale?.[device]?.value ?? 16;
        },
        deviceRatio(section, device) {
            return this.familyOf(section).scale?.[device]?.ratio ?? 1;
        },

        roundSize(section, raw) {
            // Text keeps a decimal (finer type steps); spacing/headings round to int.
            return section.cssPrefix === 'text-' ? Math.round(raw * 10) / 10 : Math.round(raw);
        },

        /** Computed px for a scale step at one device: anchor * ratio^(pos − anchorPos). */
        computeDeviceSize(section, key, device) {
            const fam = this.familyOf(section);
            const pos = fam.sizes?.[key]?.position ?? 0;
            const raw = this.deviceAnchor(section, device) * Math.pow(this.deviceRatio(section, device), pos - this.anchorPosition(section));
            return this.roundSize(section, raw);
        },

        /** The two device px values for a step, honoring mode (scale vs custom). */
        deviceValues(section, key) {
            const fam = this.familyOf(section);
            if (this.familyMode(section) === 'custom') {
                const pair = fam.custom?.[key] || {};
                return {
                    mobile: pair.mobile ?? this.computeDeviceSize(section, key, 'mobile'),
                    desktop: pair.desktop ?? this.computeDeviceSize(section, key, 'desktop'),
                };
            }
            return {
                mobile: this.computeDeviceSize(section, key, 'mobile'),
                desktop: this.computeDeviceSize(section, key, 'desktop'),
            };
        },

        // ---- generate.php mirror: num() + fluid clamp() ----
        numFmt(v) {
            return (Math.round(v * 10000) / 10000).toString();
        },

        /** Mirror of generate.php fluid_clamp(): static when anchors equal, else clamp. */
        fluidClamp(mob, desk, round) {
            const m = round ? Math.round(mob) : Math.round(mob * 10) / 10;
            const d = round ? Math.round(desk) : Math.round(desk * 10) / 10;
            if (m === d) return `${this.numFmt(m)}px`;
            const vp = this.viewport();
            const min = Math.min(m, d), max = Math.max(m, d);
            const slope = (d - m) / (vp.desktop - vp.mobile);
            const intercept = m - slope * vp.mobile;
            const vw = slope * 100;
            const sign = vw < 0 ? '-' : '+';
            return `clamp(${this.numFmt(min)}px, calc(${this.numFmt(intercept)}px ${sign} ${this.numFmt(Math.abs(vw))}vw), ${this.numFmt(max)}px)`;
        },

        /** The emitted CSS value for a scale step (clamp or static), mode-aware. */
        sizeCssValue(section, key) {
            const { mobile, desktop } = this.deviceValues(section, key);
            return this.fluidClamp(mobile, desktop, section.cssPrefix !== 'text-');
        },

        // ============================================
        // Scale-family editing (spacing / text / headings)
        // ============================================

        applyScale(section) {
            for (const key of this.sizeKeys(section)) {
                const alias = this.aliasOf(section, key);
                const value = alias ? `var(--${section.cssPrefix}${alias})` : this.sizeCssValue(section, key);
                this.applyCSSVariable(this.itemCssName(section, key), value);
            }
            const fam = this.familyOf(section);
            if (section.alias && fam.default) {
                this.applyCSSVariable(`--${section.alias}`, `var(--${section.cssPrefix}${fam.default})`);
            }
            if (section.derivedHeadings) this.applyHeadingTypography(section);
        },

        setDeviceAnchor(section, device, value) {
            const fam = this.familyOf(section);
            if (!fam.scale) fam.scale = {};
            if (!fam.scale[device]) fam.scale[device] = {};
            fam.scale[device].value = parseFloat(value);
            this.applyScale(section);
            this.markChanged();
        },

        setDeviceRatio(section, device, value) {
            const fam = this.familyOf(section);
            if (!fam.scale) fam.scale = {};
            if (!fam.scale[device]) fam.scale[device] = {};
            fam.scale[device].ratio = parseFloat(value);
            this.applyScale(section);
            this.markChanged();
        },

        /**
         * Re-point the anchor step (#47). The anchor is a pointer (`default`)
         * into `sizes`, not a standalone scalar — moving it makes another step
         * the scale origin and every other step re-derives around it. Mirrors
         * the pick-one families' Default radio; recomputes (setDefault only
         * re-emits the alias, which is right for pick-one but not for scale).
         */
        setScaleAnchor(section, key) {
            this.familyOf(section).default = key;
            this.applyScale(section);
            this.markChanged();
        },

        /** The anchor step's display key (uppercased) for labelling the origin control. */
        anchorLabel(section) {
            return (this.familyOf(section).default || '').toUpperCase();
        },

        // ---- mode switching (ADR 0018): nondestructive; seed per-key on → custom ----
        setMode(section, mode) {
            const fam = this.familyOf(section);
            if (mode === 'custom') {
                if (!fam.custom) fam.custom = {};
                for (const key of this.sizeKeys(section)) {
                    if (!fam.custom[key]) {
                        fam.custom[key] = {
                            mobile: this.computeDeviceSize(section, key, 'mobile'),
                            desktop: this.computeDeviceSize(section, key, 'desktop'),
                        };
                    }
                }
                if (section.derivedHeadings) this.seedCustomStyle(section);
            }
            fam.mode = mode;
            this.applyScale(section);
            this.markChanged();
        },

        setCustomSize(section, key, device, value) {
            const fam = this.familyOf(section);
            if (!fam.custom) fam.custom = {};
            if (!fam.custom[key]) fam.custom[key] = {};
            fam.custom[key][device] = parseFloat(value);
            this.applyScale(section);
            this.markChanged();
        },

        /** Custom-store value for a step/device, falling back to the scale computation. */
        customValue(section, key, device) {
            const pair = this.familyOf(section).custom?.[key];
            return pair?.[device] ?? this.computeDeviceSize(section, key, device);
        },

        // ---- derived heading typography (ADR 0022) ----
        headingStyle(section) {
            return this.familyOf(section).style || {};
        },

        setHeadingStyle(section, knob, value) {
            const fam = this.familyOf(section);
            if (!fam.style) fam.style = {};
            fam.style[knob] = parseFloat(value);
            this.applyScale(section);
            this.markChanged();
        },

        applyHeadingTypography(section) {
            const fam = this.familyOf(section);
            if (this.familyMode(section) === 'custom') {
                const store = fam.customStyle || {};
                for (const key of this.sizeKeys(section)) {
                    const b = store[key] || {};
                    this.applyCSSVariable(`--${key}-line-height`, `${this.numFmt(b.lineHeight ?? 1.2)}`);
                    this.applyCSSVariable(`--${key}-letter-spacing`, `${this.numFmt(b.letterSpacing ?? 0)}em`);
                    this.applyCSSVariable(`--${key}-weight`, `${b.weight ?? 600}`);
                }
                return;
            }
            const s = this.headingStyle(section);
            const leading = s.leading ?? 8;
            const slope = s.letterSpacingSlope ?? 0;
            const constant = s.letterSpacingConstant ?? 0;
            const weight = s.weight ?? 600;
            const lh = `calc(1em + ${this.numFmt(leading)}px)`;
            const lsSign = constant < 0 ? '-' : '+';
            const ls = `calc(${this.numFmt(slope)}em ${lsSign} ${this.numFmt(Math.abs(constant))}px)`;
            for (const key of this.sizeKeys(section)) {
                this.applyCSSVariable(`--${key}-line-height`, lh);
                this.applyCSSVariable(`--${key}-letter-spacing`, ls);
                this.applyCSSVariable(`--${key}-weight`, `${weight}`);
            }
        },

        /** Seed customStyle from the derived formulas at each level's desktop size. */
        seedCustomStyle(section) {
            const fam = this.familyOf(section);
            if (!fam.customStyle) fam.customStyle = {};
            const s = this.headingStyle(section);
            const leading = s.leading ?? 8, slope = s.letterSpacingSlope ?? 0,
                  constant = s.letterSpacingConstant ?? 0, weight = s.weight ?? 600;
            for (const key of this.sizeKeys(section)) {
                if (fam.customStyle[key]) continue;
                const sizePx = this.computeDeviceSize(section, key, 'desktop') || 16;
                fam.customStyle[key] = {
                    lineHeight: Math.round(((sizePx + leading) / sizePx) * 100) / 100,
                    letterSpacing: Math.round((slope + constant / sizePx) * 1000) / 1000,
                    weight,
                };
            }
        },

        // ============================================
        // Pick-one editing (border / radius) + designated default → bare alias
        // ============================================

        applyPickOne(section) {
            const fam = this.familyOf(section);
            for (const [key, data] of Object.entries(fam.sizes || {})) {
                if (data.alias) {
                    this.applyCSSVariable(this.itemCssName(section, key), `var(--${section.cssPrefix}${data.alias})`);
                } else if (data.value !== undefined) {
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
                const alias = this.aliasOf(section, key);
                const value = alias ? `var(--${section.cssPrefix}${alias})` : this.compositeValue(section, key);
                this.applyCSSVariable(this.itemCssName(section, key), value);
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
                        } else if (type === 'ramp') {
                            this.applyRamp();
                        } else if (type === 'palettes') {
                            this.applyPalettes();
                        }
                        // 'stops' emits no CSS of its own — ramp reads the stop
                        // scale directly; changing a stop re-runs applyRamp().
                    }
                }
            }
        },

        // ============================================
        // Ramp-tier Methods (ADR 0025/0019 — OKLCH, mirrors generate.php)
        // ============================================

        /**
         * Build the ramp map exactly as generate.php resolve_ramp():
         * `{name}` → source hex, `{name}-{stop}` → shade. A pin wins; an L≥100
         * stop is literal white, L≤0 literal black; else colorShade (OKLCH).
         */
        buildRampMap() {
            const colors = this.getByPath('color.colors') || {};
            const stops = this.getByPath('color.stops') || {};
            const map = {};
            for (const [name, data] of Object.entries(colors)) {
                const hex = data.value;
                if (!hex) continue;
                map[name] = hex;
                const pins = data.pins || {};
                for (const [stopName, stopData] of Object.entries(stops)) {
                    if (stopData.value === undefined) continue;
                    const L = parseFloat(stopData.value);
                    let val;
                    if (pins[stopName] !== undefined) val = pins[stopName];
                    else if (L >= 100) val = '#ffffff';
                    else if (L <= 0) val = '#000000';
                    else val = colorShade(hex, L);
                    map[`${name}-${stopName}`] = val;
                }
            }
            return map;
        },

        /** Emit `--{name}` + `--{name}-{stop}` for every source color × stop. */
        applyRamp() {
            const map = this.buildRampMap();
            for (const [key, hex] of Object.entries(map)) {
                this.applyCSSVariable(`--${key}`, hex);
            }
        },

        updateRampColor(name, value) {
            const colors = this.getByPath('color.colors');
            if (colors?.[name]) {
                colors[name].value = value;
                this.applyRamp();
                this.applyPalettes();   // palette state-mix poles depend on ramp hexes
                this.markChanged();
            }
        },

        updateStop(name, value) {
            const stops = this.getByPath('color.stops');
            if (stops?.[name]) {
                stops[name].value = parseFloat(value);
                this.applyRamp();
                this.applyPalettes();
                this.markChanged();
            }
        },

        /** Title-case a hyphenated key for display (e.g. "ultra-light" → "Ultra Light"). */
        titleCaseKey(key) {
            return key.split('-').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
        },

        // ============================================
        // Palette-tier Methods (ADR 0015/0016/0020/0026 — mirrors generate.php)
        // ============================================

        /** Resolve a palette value (`#hex` or `var(--rampKey)`) to a hex, or null. */
        resolvePaletteHex(value, rampMap) {
            if (!value) return null;
            const v = value.trim();
            if (/^#[0-9a-fA-F]{6}$/.test(v)) return v;
            const m = v.match(/^var\(\s*--([a-z0-9-]+)\s*\)$/i);
            if (m) return rampMap[m[1]] ?? null;
            return null;
        },

        /** The fill slots of a palette (keys not an -on/-hover/-active of another key). */
        paletteSlots(palette) {
            const slots = [];
            for (const key of Object.keys(palette)) {
                const m = key.match(/^(.*)-(on|hover|active)$/);
                if (m && palette[m[1]] !== undefined) continue;
                slots.push(key);
            }
            return slots;
        },

        /** color-mix pole for a slot's state; unresolvable → black (ADR 0026 v1). */
        paletteStatePole(value, rampMap) {
            const hex = this.resolvePaletteHex(value, rampMap);
            if (hex === null) return 'black';
            return statePole(hex);
        },

        /** One sparse palette block's lines, mirroring emit_palette_block(). */
        emitPaletteBlock(palette, rampMap) {
            const STATE_MIX = { hover: 12, active: 20 };
            const lines = [];
            for (const slot of this.paletteSlots(palette)) {
                const value = palette[slot];
                lines.push(`    --palette-${slot}: ${value};`);
                if (palette[`${slot}-on`] !== undefined) {
                    lines.push(`    --palette-${slot}-on: ${palette[`${slot}-on`]};`);
                }
                for (const [state, pct] of Object.entries(STATE_MIX)) {
                    if (palette[`${slot}-${state}`] !== undefined) {
                        lines.push(`    --palette-${slot}-${state}: ${palette[`${slot}-${state}`]};`);
                    } else {
                        const pole = this.paletteStatePole(value, rampMap);
                        lines.push(`    --palette-${slot}-${state}: color-mix(in srgb, var(--palette-${slot}), ${pole} ${pct}%);`);
                    }
                }
            }
            return lines;
        },

        /**
         * Emit the live-preview palette overrides: sparse per-palette blocks
         * (default → :root, others → [data-palette="name"]) with color-mix
         * states, plus one [data-intent] binding per intent — byte-for-byte the
         * generator's palette-tier output.
         */
        applyPalettes() {
            const palettes = this.settings.color?.palettes;
            if (!palettes) return;
            const rampMap = this.buildRampMap();
            const defaultPalette = palettes.default || {};

            let styleEl = document.getElementById('anti-palette-overrides');
            if (!styleEl) {
                styleEl = document.createElement('style');
                styleEl.id = 'anti-palette-overrides';
                document.head.appendChild(styleEl);
            }

            let css = '';
            for (const [paletteName, palette] of Object.entries(palettes)) {
                const lines = this.emitPaletteBlock(palette, rampMap);
                if (!lines.length) continue;
                const selector = paletteName === 'default' ? ':root' : `[data-palette="${paletteName}"]`;
                css += `${selector} {\n${lines.join('\n')}\n}\n`;
            }

            for (const slot of this.paletteSlots(defaultPalette)) {
                if (defaultPalette[`${slot}-on`] === undefined) continue;
                css += `[data-intent="${slot}"] {\n`;
                css += `    --intent: var(--palette-${slot});\n`;
                css += `    --intent-on: var(--palette-${slot}-on);\n`;
                css += `    --intent-hover: var(--palette-${slot}-hover);\n`;
                css += `    --intent-active: var(--palette-${slot}-active);\n`;
                css += `}\n`;
            }

            styleEl.textContent = css;
            this.syncPaletteList();
        },

        updatePalette(paletteName, slot, value) {
            const palettes = this.settings.color?.palettes;
            if (palettes?.[paletteName]) {
                palettes[paletteName][slot] = value;
                this.applyPalettes();
                this.markChanged();
            }
        },

        /** Ramp source colors × stops as picker option groups (+ fixed white/black). */
        getPaletteOptions() {
            const groups = [{
                group: 'Fixed',
                base: null,
                shades: [
                    { value: '#ffffff', label: 'White', hex: '#ffffff' },
                    { value: '#000000', label: 'Black', hex: '#000000' }
                ]
            }];

            const colors = this.getByPath('color.colors') || {};
            const stops = this.getByPath('color.stops') || {};

            for (const [name, data] of Object.entries(colors)) {
                if (!data.value) continue;
                const displayName = this.titleCaseKey(name);
                const base = { value: `var(--${name})`, label: displayName, hex: data.value };
                const pins = data.pins || {};
                const shadeList = [];
                for (const [stopName, stopData] of Object.entries(stops)) {
                    if (stopData.value === undefined) continue;
                    const L = parseFloat(stopData.value);
                    let hex;
                    if (pins[stopName] !== undefined) hex = pins[stopName];
                    else if (L >= 100) hex = '#ffffff';
                    else if (L <= 0) hex = '#000000';
                    else hex = colorShade(data.value, L);
                    shadeList.push({
                        value: `var(--${name}-${stopName})`,
                        label: `${displayName} ${this.titleCaseKey(stopName)}`,
                        hex
                    });
                }
                groups.push({ group: displayName, base, shades: shadeList });
            }

            return groups;
        },

        /** Resolve a palette slot value to a swatch hex (OKLCH-accurate). */
        resolvePaletteSwatch(value) {
            if (!value) return '#cccccc';
            const v = value.trim();
            if (/^#[0-9a-fA-F]{6}$/.test(v)) return v;
            return this.resolvePaletteHex(v, this.buildRampMap()) ?? '#cccccc';
        },

        togglePaletteDropdown(id) {
            if (this.paletteDropdownId === id) {
                this.closePaletteDropdown();
            } else {
                this.paletteDropdownId = id;
                this.paletteCustomMode = null;
            }
        },

        closePaletteDropdown() {
            this.paletteDropdownId = null;
            this.paletteCustomMode = null;
        },

        selectPaletteOption(paletteName, slot, value) {
            this.updatePalette(paletteName, slot, value);
            this.closePaletteDropdown();
        },

        enterCustomHexMode(id) {
            this.paletteCustomMode = id;
        },

        applyCustomHex(paletteName, slot, hex) {
            if (/^#[0-9a-fA-F]{6}$/.test(hex)) {
                this.updatePalette(paletteName, slot, hex);
                this.closePaletteDropdown();
            }
        },

        getPaletteValueLabel(value) {
            if (!value) return 'Inherit';
            if (value === '#ffffff') return 'White';
            if (value === '#000000') return 'Black';
            if (value.startsWith('#')) return value.toUpperCase();

            const match = value.match(/^var\(--(.+)\)$/);
            if (!match) return value;

            return this.titleCaseKey(match[1]);
        },

        /** Broadcast current palette names so the preview region selector stays in sync. */
        syncPaletteList() {
            const palettes = Object.keys(this.settings.color?.palettes || {});
            window.__antiPalettes = palettes;
            window.dispatchEvent(new CustomEvent('anti-palettes-changed', { detail: { palettes } }));
        },

        // ============================================
        // Palette Management (Add / Delete)
        // ============================================

        addPalette() {
            const name = this.newPaletteName.trim();
            if (!name) return;

            const palettes = this.settings.color?.palettes;
            if (!palettes || palettes[name]) {
                this.showNotification(palettes[name] ? 'Palette already exists' : 'Error', 'error');
                return;
            }

            // Clone from default palette as a starting point.
            const defaultPalette = palettes['default'] || {};
            palettes[name] = JSON.parse(JSON.stringify(defaultPalette));

            this.newPaletteName = '';
            this.addingPalette = false;
            this.applyPalettes();
            this.markChanged();
            this.showNotification(`Palette "${name}" added`, 'success');
        },

        deletePalette(paletteName) {
            if (paletteName === 'default') return;
            const palettes = this.settings.color?.palettes;
            if (!palettes?.[paletteName]) return;

            delete palettes[paletteName];
            this.applyPalettes();
            this.markChanged();
            this.showNotification(`Palette "${paletteName}" removed`, 'success');
        },

        cancelAddPalette() {
            this.addingPalette = false;
            this.newPaletteName = '';
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
         @keydown.escape.window="paletteDropdownId ? closePaletteDropdown() : closeSettings()">

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

                    <!-- Global viewport anchors (ADR 0018) — shared by every scale family -->
                    <template x-if="panelHasScale && schema.viewport">
                        <div class="anti-device-block anti-viewport-block">
                            <div class="anti-section-title anti-device-block__title" x-text="schema.viewport.label"></div>
                            <div class="anti-control-hint" x-show="schema.viewport.hint" x-text="schema.viewport.hint"></div>
                            <template x-for="device in ['mobile', 'desktop']" :key="device">
                                <div class="anti-size-section is-enabled">
                                    <div class="anti-size-header">
                                        <span class="anti-size-name" x-text="device.charAt(0).toUpperCase() + device.slice(1)"></span>
                                        <div class="anti-control-value">
                                            <input type="number" :step="schema.viewport.step"
                                                :value="viewport()[device]"
                                                @change="setViewport(device, $event.target.value)">
                                            <span x-text="schema.viewport.unit"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- Show token names (ADR 0023): CSS var names hidden by default -->
                    <template x-if="panelHasSizeTokens">
                        <label class="anti-tokennames-toggle">
                            <span class="anti-toggle">
                                <input type="checkbox" :checked="showTokenNames" @change="toggleTokenNames()">
                                <span class="anti-toggle-slider"></span>
                            </span>
                            <span>Show token names</span>
                        </label>
                    </template>

                    <template x-for="section in currentSections" :key="section.id">
                        <div>

                            <!-- ===== SCALE section (spacing / text / headings) ===== -->
                            <template x-if="getSectionType(section) === 'scale'">
                                <div>
                                    <div class="anti-section-title" x-text="section.label"></div>

                                    <!-- Mode switch: systematic scale vs hand-authored custom (ADR 0018) -->
                                    <div class="anti-mode-switch">
                                        <button class="anti-mode-switch__btn"
                                            :class="{ 'is-active': familyMode(section) === 'scale' }"
                                            @click="setMode(section, 'scale')">Scale</button>
                                        <button class="anti-mode-switch__btn"
                                            :class="{ 'is-active': familyMode(section) === 'custom' }"
                                            @click="setMode(section, 'custom')">Custom</button>
                                    </div>

                                    <!-- ---------- SCALE MODE ---------- -->
                                    <template x-if="familyMode(section) === 'scale'">
                                        <div>
                                            <!-- Per-device anchor + ratio (mobile / desktop) -->
                                            <template x-for="device in ['mobile', 'desktop']" :key="device">
                                                <div class="anti-device-block">
                                                    <div class="anti-section-title anti-device-block__title"
                                                        x-text="device.charAt(0).toUpperCase() + device.slice(1)"></div>

                                                    <div class="anti-size-section is-enabled">
                                                        <div class="anti-size-header">
                                                            <span class="anti-size-name">
                                                                <span x-text="section.anchor.label"></span>
                                                                <span class="anti-anchor-tag" x-text="anchorLabel(section)"></span>
                                                            </span>
                                                        </div>
                                                        <div class="anti-size-controls" style="display: block;">
                                                            <div class="anti-control-row">
                                                                <input type="range" class="anti-range"
                                                                    :min="section.anchor.min" :max="section.anchor.max" :step="section.anchor.step"
                                                                    :value="deviceAnchor(section, device)"
                                                                    @input="setDeviceAnchor(section, device, $event.target.value)">
                                                                <div class="anti-control-value">
                                                                    <input type="number" :step="section.anchor.step"
                                                                        :value="deviceAnchor(section, device)"
                                                                        @change="setDeviceAnchor(section, device, $event.target.value)">
                                                                    <span x-show="section.anchor.unit" x-text="section.anchor.unit"></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="anti-size-section is-enabled">
                                                        <div class="anti-size-header">
                                                            <span class="anti-size-name" x-text="section.ratio.label"></span>
                                                        </div>
                                                        <div class="anti-size-controls" style="display: block;">
                                                            <div class="anti-control-row">
                                                                <input type="range" class="anti-range"
                                                                    :min="section.ratio.min" :max="section.ratio.max" :step="section.ratio.step"
                                                                    :value="deviceRatio(section, device)"
                                                                    @input="setDeviceRatio(section, device, $event.target.value)">
                                                                <div class="anti-control-value">
                                                                    <input type="number" :step="section.ratio.step"
                                                                        :value="deviceRatio(section, device)"
                                                                        @change="setDeviceRatio(section, device, $event.target.value)">
                                                                </div>
                                                            </div>
                                                            <template x-if="section.ratio.presets">
                                                                <select class="anti-select" style="margin-top: 12px;"
                                                                    @change="setDeviceRatio(section, device, $event.target.value)">
                                                                    <option value="">Custom</option>
                                                                    <template x-for="preset in (schema.presets[section.ratio.presets] || [])" :key="presetValue(preset)">
                                                                        <option :value="presetValue(preset)" x-text="presetLabel(preset)"
                                                                            :selected="deviceRatio(section, device) === presetValue(preset)"></option>
                                                                    </template>
                                                                </select>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- Derived heading typography knobs (ADR 0022) -->
                                            <template x-if="section.derivedHeadings && section.style">
                                                <div class="anti-device-block">
                                                    <div class="anti-section-title anti-device-block__title">Typography</div>
                                                    <template x-for="knob in section.style" :key="knob.id">
                                                        <div class="anti-size-section is-enabled">
                                                            <div class="anti-size-header">
                                                                <span class="anti-size-name" x-text="knob.label"></span>
                                                            </div>
                                                            <div class="anti-size-controls" style="display: block;">
                                                                <div class="anti-control-row">
                                                                    <input type="range" class="anti-range"
                                                                        :min="knob.min" :max="knob.max" :step="knob.step"
                                                                        :value="headingStyle(section)[knob.id]"
                                                                        @input="setHeadingStyle(section, knob.id, $event.target.value)">
                                                                    <div class="anti-control-value">
                                                                        <input type="number" :step="knob.step"
                                                                            :value="headingStyle(section)[knob.id]"
                                                                            @change="setHeadingStyle(section, knob.id, $event.target.value)">
                                                                        <span x-show="knob.unit" x-text="knob.unit"></span>
                                                                    </div>
                                                                </div>
                                                                <div class="anti-control-hint" x-show="knob.hint" x-text="knob.hint"></div>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>

                                            <!-- Steps: values computed (read-only), anchor is a re-pointable
                                                 designated step — the origin, not a standalone scalar (#47) -->
                                            <div class="anti-section-title" style="margin-top: 12px;">Steps</div>
                                            <template x-for="key in sizeKeys(section)" :key="key">
                                                <div class="anti-size-section is-enabled"
                                                    :class="{ 'is-anchor': familyOf(section).default === key }">
                                                    <div class="anti-size-header">
                                                        <span class="anti-size-name anti-token-meta">
                                                            <input class="anti-token-label" type="text" :value="stepLabel(section, key)" @change="setStepLabel(section, key, $event.target.value)">
                                                            <code class="anti-token-name" x-show="showTokenNames" x-text="itemCssName(section, key)"></code>
                                                            <span class="anti-token-badge" :class="isSpecStep(section, key) ? 'is-spec' : 'is-custom'" x-text="isSpecStep(section, key) ? 'spec' : 'custom'" :title="isSpecStep(section, key) ? ('Defined by the ' + (spec ? spec.name : '') + ' spec — non-deletable') : 'Custom token'"></span>
                                                        </span>
                                                        <span class="anti-size-meta">
                                                            <span class="anti-size-computed"
                                                                x-text="aliasOf(section, key) ? ('= ' + itemCssName(section, aliasOf(section, key))) : (computeDeviceSize(section, key, 'mobile') + ' → ' + computeDeviceSize(section, key, 'desktop') + (section.unit || ''))"></span>
                                                            <label class="anti-default-radio" x-show="!aliasOf(section, key)">
                                                                <input type="radio" :name="section.id + '-anchor'"
                                                                    :checked="familyOf(section).default === key"
                                                                    @change="setScaleAnchor(section, key)">
                                                                <span>Anchor</span>
                                                            </label>
                                                        </span>
                                                        <span class="anti-token-actions">
                                                            <select class="anti-token-alias" @change="setAlias(section, key, $event.target.value)" :title="'Alias ' + itemCssName(section, key)">
                                                                <option value="" :selected="!aliasOf(section, key)">value</option>
                                                                <template x-for="t in aliasTargets(section, key)" :key="t">
                                                                    <option :value="t" :selected="aliasOf(section, key) === t" x-text="'= ' + t"></option>
                                                                </template>
                                                            </select>
                                                            <button class="anti-token-del" type="button" x-show="!isSpecStep(section, key)" @click="deleteStep(section, key)" title="Delete custom token">✕</button>
                                                        </span>
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- Add a custom token (open-set; spec tokens are non-deletable, ADR 0023) -->
                                            <div class="anti-add-token">
                                                <button class="anti-btn anti-btn--sm" type="button" x-show="addingStepFor !== section.settingsKey" @click="startAddStep(section)">+ Add custom token</button>
                                                <div class="anti-add-token__form" x-show="addingStepFor === section.settingsKey">
                                                    <input type="text" class="anti-add-token__key" placeholder="key, e.g. xxl" x-model="newStepKey" @keydown.enter="addCustomStep(section)" @keydown.escape="cancelAddStep()">
                                                    <button class="anti-btn anti-btn--sm" type="button" @click="addCustomStep(section)">Add</button>
                                                    <button class="anti-btn anti-btn--sm anti-btn--ghost" type="button" @click="cancelAddStep()">Cancel</button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- ---------- CUSTOM MODE ---------- -->
                                    <template x-if="familyMode(section) === 'custom'">
                                        <div>
                                            <div class="anti-control-hint" style="margin-bottom: 8px;">
                                                Hand-authored per-size values. Seeded from the scale on switch; edits here don't re-derive.
                                            </div>
                                            <template x-for="key in sizeKeys(section)" :key="key">
                                                <div class="anti-size-section is-enabled">
                                                    <div class="anti-size-header">
                                                        <span class="anti-size-name anti-token-meta">
                                                            <input class="anti-token-label" type="text" :value="stepLabel(section, key)" @change="setStepLabel(section, key, $event.target.value)">
                                                            <code class="anti-token-name" x-show="showTokenNames" x-text="itemCssName(section, key)"></code>
                                                            <span class="anti-token-badge" :class="isSpecStep(section, key) ? 'is-spec' : 'is-custom'" x-text="isSpecStep(section, key) ? 'spec' : 'custom'"></span>
                                                        </span>
                                                    </div>
                                                    <div class="anti-size-controls" style="display: block;">
                                                        <template x-for="device in ['mobile', 'desktop']" :key="device">
                                                            <div class="anti-control-row">
                                                                <label class="anti-control-label" style="min-width: 60px;"
                                                                    x-text="device.charAt(0).toUpperCase() + device.slice(1)"></label>
                                                                <div class="anti-control-value">
                                                                    <input type="number" :step="section.cssPrefix === 'text-' ? 0.1 : 1"
                                                                        :value="customValue(section, key, device)"
                                                                        @change="setCustomSize(section, key, device, $event.target.value)">
                                                                    <span x-show="section.unit" x-text="section.unit"></span>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
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
                                                <span class="anti-size-name anti-token-meta">
                                                    <input class="anti-token-label" type="text" :value="stepLabel(section, key)" @change="setStepLabel(section, key, $event.target.value)">
                                                    <code class="anti-token-name" x-show="showTokenNames" x-text="itemCssName(section, key)"></code>
                                                    <span class="anti-token-badge" :class="isSpecStep(section, key) ? 'is-spec' : 'is-custom'" x-text="isSpecStep(section, key) ? 'spec' : 'custom'"></span>
                                                </span>
                                                <label class="anti-default-radio" x-show="!aliasOf(section, key)">
                                                    <input type="radio" :name="section.id + '-default'"
                                                        :checked="familyOf(section).default === key"
                                                        @change="setDefault(section, key)">
                                                    <span>Default</span>
                                                </label>
                                                <span class="anti-token-actions">
                                                    <select class="anti-token-alias" @change="setAlias(section, key, $event.target.value)" :title="'Alias ' + itemCssName(section, key)">
                                                        <option value="" :selected="!aliasOf(section, key)">value</option>
                                                        <template x-for="t in aliasTargets(section, key)" :key="t">
                                                            <option :value="t" :selected="aliasOf(section, key) === t" x-text="'= ' + t"></option>
                                                        </template>
                                                    </select>
                                                    <button class="anti-token-del" type="button" x-show="!isSpecStep(section, key)" @click="deleteStep(section, key)" title="Delete custom token">✕</button>
                                                </span>
                                            </div>
                                            <div class="anti-size-controls" x-show="!aliasOf(section, key)">
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

                                    <!-- Add a custom token (open-set; spec tokens are non-deletable, ADR 0023) -->
                                    <div class="anti-add-token">
                                        <button class="anti-btn anti-btn--sm" type="button" x-show="addingStepFor !== section.settingsKey" @click="startAddStep(section)">+ Add custom token</button>
                                        <div class="anti-add-token__form" x-show="addingStepFor === section.settingsKey">
                                            <input type="text" class="anti-add-token__key" placeholder="key, e.g. xl" x-model="newStepKey" @keydown.enter="addCustomStep(section)" @keydown.escape="cancelAddStep()">
                                            <button class="anti-btn anti-btn--sm" type="button" @click="addCustomStep(section)">Add</button>
                                            <button class="anti-btn anti-btn--sm anti-btn--ghost" type="button" @click="cancelAddStep()">Cancel</button>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- ===== COMPOSITE section (shadows) ===== -->
                            <template x-if="getSectionType(section) === 'composite'">
                                <div>
                                    <div class="anti-section-title" x-text="section.label"></div>
                                    <template x-for="key in sizeKeys(section)" :key="key">
                                        <div class="anti-size-section is-enabled">
                                            <div class="anti-size-header">
                                                <span class="anti-size-name anti-token-meta">
                                                    <input class="anti-token-label" type="text" :value="stepLabel(section, key)" @change="setStepLabel(section, key, $event.target.value)">
                                                    <code class="anti-token-name" x-show="showTokenNames" x-text="itemCssName(section, key)"></code>
                                                    <span class="anti-token-badge" :class="isSpecStep(section, key) ? 'is-spec' : 'is-custom'" x-text="isSpecStep(section, key) ? 'spec' : 'custom'"></span>
                                                </span>
                                                <label class="anti-default-radio" x-show="!aliasOf(section, key)">
                                                    <input type="radio" :name="section.id + '-default'"
                                                        :checked="familyOf(section).default === key"
                                                        @change="setDefault(section, key)">
                                                    <span>Default</span>
                                                </label>
                                                <span class="anti-token-actions">
                                                    <select class="anti-token-alias" @change="setAlias(section, key, $event.target.value)" :title="'Alias ' + itemCssName(section, key)">
                                                        <option value="" :selected="!aliasOf(section, key)">value</option>
                                                        <template x-for="t in aliasTargets(section, key)" :key="t">
                                                            <option :value="t" :selected="aliasOf(section, key) === t" x-text="'= ' + t"></option>
                                                        </template>
                                                    </select>
                                                    <button class="anti-token-del" type="button" x-show="!isSpecStep(section, key)" @click="deleteStep(section, key)" title="Delete custom token">✕</button>
                                                </span>
                                            </div>
                                            <div class="anti-size-controls" x-show="!aliasOf(section, key)">
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

                                    <!-- Add a custom token (open-set; spec tokens are non-deletable, ADR 0023) -->
                                    <div class="anti-add-token">
                                        <button class="anti-btn anti-btn--sm" type="button" x-show="addingStepFor !== section.settingsKey" @click="startAddStep(section)">+ Add custom token</button>
                                        <div class="anti-add-token__form" x-show="addingStepFor === section.settingsKey">
                                            <input type="text" class="anti-add-token__key" placeholder="key, e.g. xxl" x-model="newStepKey" @keydown.enter="addCustomStep(section)" @keydown.escape="cancelAddStep()">
                                            <button class="anti-btn anti-btn--sm" type="button" @click="addCustomStep(section)">Add</button>
                                            <button class="anti-btn anti-btn--sm anti-btn--ghost" type="button" @click="cancelAddStep()">Cancel</button>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- ===== RAMP section (flat source colors) ===== -->
                            <template x-if="getSectionType(section) === 'ramp'">
                                <div>
                                    <div class="anti-section-title" x-text="section.label" style="margin-top: 8px;"></div>
                                    <template x-for="(data, colorName) in (getByPath(section.settingsKey) || {})" :key="colorName">
                                        <div class="anti-size-section is-enabled">
                                            <div class="anti-size-header">
                                                <span class="anti-size-name"
                                                    x-text="titleCaseKey(colorName)"></span>
                                            </div>
                                            <div class="anti-size-controls">
                                                <div class="anti-control-group">
                                                    <div class="anti-color-input">
                                                        <span class="anti-color-swatch"
                                                            :style="'background:' + (getByPath(section.settingsKey)?.[colorName]?.value || '#cccccc')"></span>
                                                        <input type="text" data-coloris
                                                            :value="getByPath(section.settingsKey)?.[colorName]?.value"
                                                            @input="updateRampColor(colorName, $event.target.value)">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- ===== STOPS section (contrast lightness scale) ===== -->
                            <template x-if="getSectionType(section) === 'stops'">
                                <div>
                                    <div class="anti-section-title" x-text="section.label" style="margin-top: 8px;"></div>
                                    <template x-for="(data, stopName) in (getByPath(section.settingsKey) || {})" :key="stopName">
                                        <div class="anti-size-section is-enabled">
                                            <div class="anti-size-header">
                                                <span class="anti-size-name" x-text="titleCaseKey(stopName)"></span>
                                            </div>
                                            <div class="anti-size-controls">
                                                <div class="anti-control-row">
                                                    <input type="range" class="anti-range"
                                                        :min="section.value.min" :max="section.value.max" :step="section.value.step"
                                                        :value="getByPath(section.settingsKey)?.[stopName]?.value"
                                                        @input="updateStop(stopName, $event.target.value)">
                                                    <div class="anti-control-value">
                                                        <input type="number"
                                                            :min="section.value.min" :max="section.value.max" :step="section.value.step"
                                                            :value="getByPath(section.settingsKey)?.[stopName]?.value"
                                                            @change="updateStop(stopName, $event.target.value)">
                                                        <span>L</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- ===== PALETTES section ===== -->
                            <template x-if="getSectionType(section) === 'palettes'">
                                <div>
                                    <template x-for="(palette, paletteName) in (getByPath(section.settingsKey) || {})" :key="paletteName">
                                        <div class="anti-size-section is-enabled">
                                            <div class="anti-size-header">
                                                <span class="anti-size-name"
                                                    x-text="titleCaseKey(paletteName)"></span>
                                                <button x-show="paletteName !== 'default'"
                                                    class="anti-colorway-delete"
                                                    @click="deletePalette(paletteName)"
                                                    :title="'Delete ' + paletteName + ' palette'"
                                                    aria-label="Delete palette">
                                                    ${UI_ICONS.close}
                                                </button>
                                            </div>
                                            <div class="anti-size-controls">
                                                <template x-for="token in section.properties" :key="token.id">
                                                    <div class="anti-control-group" style="margin-top: 8px;">
                                                        <label class="anti-control-label" style="font-size: 11px;" x-text="token.label"></label>
                                                        <div class="anti-colorway-picker">
                                                            <button class="anti-colorway-picker__trigger"
                                                                @click="togglePaletteDropdown(paletteName + '-' + token.id)">
                                                                <span class="anti-colorway-picker__swatch"
                                                                    :class="{ 'is-white': resolvePaletteSwatch(palette[token.id]) === '#ffffff' }"
                                                                    :style="'background:' + resolvePaletteSwatch(palette[token.id])"></span>
                                                                <span class="anti-colorway-picker__label"
                                                                    x-text="getPaletteValueLabel(palette[token.id])"></span>
                                                                <span class="anti-colorway-picker__chevron">&#9662;</span>
                                                            </button>
                                                            <div class="anti-colorway-picker__dropdown"
                                                                x-show="paletteDropdownId === paletteName + '-' + token.id"
                                                                x-cloak>
                                                                <template x-if="paletteCustomMode !== paletteName + '-' + token.id">
                                                                    <div>
                                                                        <template x-for="group in getPaletteOptions()" :key="group.group">
                                                                            <div class="anti-colorway-picker__group">
                                                                                <button x-show="group.base"
                                                                                    class="anti-colorway-picker__color-row"
                                                                                    :class="{ 'is-selected': palette[token.id] === group.base?.value }"
                                                                                    @click="selectPaletteOption(paletteName, token.id, group.base?.value)">
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
                                                                                            :class="{ 'is-selected': palette[token.id] === shade.value, 'is-white': shade.hex === '#ffffff' }"
                                                                                            :style="'background:' + shade.hex"
                                                                                            :title="shade.label"
                                                                                            @click="selectPaletteOption(paletteName, token.id, shade.value)">
                                                                                        </button>
                                                                                    </template>
                                                                                </div>
                                                                            </div>
                                                                        </template>
                                                                        <div class="anti-colorway-picker__group">
                                                                            <button class="anti-colorway-picker__option"
                                                                                @click="enterCustomHexMode(paletteName + '-' + token.id)">
                                                                                <span class="anti-colorway-picker__option-swatch"
                                                                                    style="background: linear-gradient(135deg, #ff0000, #ff8800, #ffff00, #00ff00, #0088ff, #8800ff)"></span>
                                                                                <span class="anti-colorway-picker__option-label">Custom hex...</span>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </template>
                                                                <template x-if="paletteCustomMode === paletteName + '-' + token.id">
                                                                    <div class="anti-colorway-picker__custom">
                                                                        <input type="text" data-coloris
                                                                            :value="palette[token.id]?.startsWith?.('#') ? palette[token.id] : ''"
                                                                            placeholder="#000000"
                                                                            @input="updatePalette(paletteName, token.id, $event.target.value)"
                                                                            @keydown.enter="applyCustomHex(paletteName, token.id, $event.target.value)">
                                                                    </div>
                                                                </template>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Add Palette -->
                                    <div class="anti-colorway-add">
                                        <template x-if="!addingPalette">
                                            <button class="anti-btn anti-btn--add-colorway" @click="addingPalette = true">
                                                + Add Palette
                                            </button>
                                        </template>
                                        <template x-if="addingPalette">
                                            <div class="anti-colorway-add__form">
                                                <input type="text" class="anti-input"
                                                    x-model="newPaletteName"
                                                    placeholder="Palette Name"
                                                    @keydown.enter="addPalette()"
                                                    @keydown.escape="cancelAddPalette()"
                                                    x-init="$nextTick(() => $el.focus())">
                                                <div class="anti-colorway-add__actions">
                                                    <button class="anti-btn anti-btn--small anti-btn--primary" @click="addPalette()">Add</button>
                                                    <button class="anti-btn anti-btn--small anti-btn--secondary" @click="cancelAddPalette()">Cancel</button>
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
