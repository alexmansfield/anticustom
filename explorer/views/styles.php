<?php
/**
 * Styles View — Design Token Reference
 *
 * Alpine-reactive table that rebuilds from the sidebar's live settings
 * whenever tokens are enabled, disabled, or changed.
 */
?>

<script>
document.addEventListener('alpine:init', () => {

    // OKLCH ramp math — same port as panel.js / generate.php, so the table's
    // resolved ramp shades match the emitted CSS byte-for-byte.
    const cbrtSigned = x => x < 0 ? -Math.pow(-x, 1/3) : Math.pow(x, 1/3);
    const srgbDecode = v => { const s = v<0?-1:1; v=Math.abs(v); return s*(v<=0.04045 ? v/12.92 : Math.pow((v+0.055)/1.055, 2.4)); };
    const srgbEncode = v => { const s = v<0?-1:1; v=Math.abs(v); return s*(v>0.0031308 ? 1.055*Math.pow(v,1/2.4)-0.055 : 12.92*v); };
    const linearToOklab = (r,g,b) => {
        const l = 0.4122214708*r + 0.5363325363*g + 0.0514459929*b;
        const m = 0.2119034982*r + 0.6806995451*g + 0.1073969566*b;
        const s = 0.0883024619*r + 0.2817188376*g + 0.6299787005*b;
        const l_=cbrtSigned(l), m_=cbrtSigned(m), s_=cbrtSigned(s);
        return [
            0.2104542553*l_ + 0.7936177850*m_ - 0.0040720468*s_,
            1.9779984951*l_ - 2.4285922050*m_ + 0.4505937099*s_,
            0.0259040371*l_ + 0.7827717662*m_ - 0.8086757660*s_,
        ];
    };
    const oklchToLinear = (L,C,H) => {
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
    };
    const hexToOklch = hex => {
        hex = hex.replace('#','');
        const r=srgbDecode(parseInt(hex.substr(0,2),16)/255);
        const g=srgbDecode(parseInt(hex.substr(2,2),16)/255);
        const b=srgbDecode(parseInt(hex.substr(4,2),16)/255);
        const [L,a,bb]=linearToOklab(r,g,b);
        let H = Math.atan2(bb,a)*180/Math.PI; if (H<0) H+=360;
        return [L, Math.sqrt(a*a+bb*bb), H];
    };
    const oklchInGamut = (L,C,H) => oklchToLinear(L,C,H).every(c => c>=-0.0001 && c<=1.0001);
    const oklchClipToHex = (L,C,H) => {
        const enc = oklchToLinear(L,C,H).map(c => Math.max(0, Math.min(1, srgbEncode(c))));
        return '#' + enc.map(c => Math.round(c*255).toString(16).padStart(2,'0')).join('');
    };
    const oklchToHex = (L,C,H) => {
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
    };
    const colorShade = (hex, L100) => { const [,C,H]=hexToOklch(hex); return oklchToHex(L100/100, C, H); };

    Alpine.data('tokenTable', () => ({
        rows: [],

        init() {
            this.$nextTick(() => this.rebuild());
            window.addEventListener('anti-settings-changed', () => this.rebuild());
        },

        rebuild() {
            const settings = window.__antiSettings;
            if (!settings) return;

            const rows = [];
            const vp = settings.viewport || { mobile: 390, desktop: 1440 };
            const numFmt = v => (Math.round(v * 10000) / 10000).toString();

            // Mirror of generate.php fluid_clamp(): static when the two device
            // anchors coincide, else a clamp interpolating across the viewport.
            const fluidClamp = (mob, desk, round) => {
                const m = round ? Math.round(mob) : Math.round(mob * 10) / 10;
                const d = round ? Math.round(desk) : Math.round(desk * 10) / 10;
                if (m === d) return `${numFmt(m)}px`;
                const min = Math.min(m, d), max = Math.max(m, d);
                const slope = (d - m) / (vp.desktop - vp.mobile);
                const intercept = m - slope * vp.mobile;
                const w = slope * 100;
                return `clamp(${numFmt(min)}px, calc(${numFmt(intercept)}px ${w < 0 ? '-' : '+'} ${numFmt(Math.abs(w))}vw), ${numFmt(max)}px)`;
            };

            // Open-set scale family (per-device, mode-aware). Scale mode computes
            // each step from the per-device anchor+ratio; custom mode reads the store.
            const scaleRows = (fam, prefix, category, decimal) => {
                if (!fam?.sizes) return;
                const round = !decimal;
                const aPos = fam.sizes[fam.default]?.position ?? 0;
                const mode = fam.mode || 'scale';
                const deviceVal = (key, device) => {
                    if (mode === 'custom') {
                        const pair = fam.custom?.[key];
                        if (pair && pair[device] !== undefined) return pair[device];
                    }
                    const sc = fam.scale?.[device] || {};
                    const raw = (sc.value ?? 16) * Math.pow(sc.ratio ?? 1, (fam.sizes[key].position ?? 0) - aPos);
                    return decimal ? Math.round(raw * 10) / 10 : Math.round(raw);
                };
                for (const key of Object.keys(fam.sizes)) {
                    rows.push({
                        variable: `--${prefix}${key}`,
                        category,
                        value: fluidClamp(deviceVal(key, 'mobile'), deviceVal(key, 'desktop'), round),
                    });
                }
            };

            scaleRows(settings.spacing, 'space-', 'Spacing', false);
            scaleRows(settings.typography?.text, 'text-', 'Typography', true);
            // Headings are key-identity: keys h1–h6 emit --h1…--h6 (no prefix).
            scaleRows(settings.typography?.headings, '', 'Typography', false);

            // Derived heading typography (ADR 0022): line-height / letter-spacing
            // calc() forms + one authored weight; custom mode reads customStyle.
            const heads = settings.typography?.headings;
            if (heads?.sizes) {
                const mode = heads.mode || 'scale';
                const st = heads.style || {};
                const lh = `calc(1em + ${numFmt(st.leading ?? 8)}px)`;
                const slope = st.letterSpacingSlope ?? 0, constant = st.letterSpacingConstant ?? 0;
                const ls = `calc(${numFmt(slope)}em ${constant < 0 ? '-' : '+'} ${numFmt(Math.abs(constant))}px)`;
                for (const key of Object.keys(heads.sizes)) {
                    const b = (heads.customStyle || {})[key] || {};
                    rows.push({ variable: `--${key}-line-height`, category: 'Typography',
                        value: mode === 'custom' ? `${numFmt(b.lineHeight ?? 1.2)}` : lh });
                    rows.push({ variable: `--${key}-letter-spacing`, category: 'Typography',
                        value: mode === 'custom' ? `${numFmt(b.letterSpacing ?? 0)}em` : ls });
                    rows.push({ variable: `--${key}-weight`, category: 'Typography',
                        value: mode === 'custom' ? `${b.weight ?? 600}` : `${st.weight ?? 600}` });
                }
            }

            // Colors — ramp tier: each source color's base + resolved stop shades
            // (pin wins; L100/L0 → literal white/black; else OKLCH colorShade).
            const rampColors = settings.color?.colors || {};
            const rampStops = settings.color?.stops || {};
            const rampMap = {};
            for (const [name, data] of Object.entries(rampColors)) {
                const hex = data.value;
                if (!hex) continue;
                rampMap[name] = hex;
                rows.push({ variable: `--${name}`, category: 'Colors', value: hex });
                const pins = data.pins || {};
                for (const [stopName, stopData] of Object.entries(rampStops)) {
                    if (stopData.value === undefined) continue;
                    const L = parseFloat(stopData.value);
                    let shade;
                    if (pins[stopName] !== undefined) shade = pins[stopName];
                    else if (L >= 100) shade = '#ffffff';
                    else if (L <= 0) shade = '#000000';
                    else shade = colorShade(hex, L);
                    rampMap[`${name}-${stopName}`] = shade;
                    rows.push({ variable: `--${name}-${stopName}`, category: 'Colors', value: shade });
                }
            }

            // Palette tier — the default palette's --palette-* tokens (sparse,
            // mirrors emit_palette_block: value, -on, generator color-mix states).
            const resolvePaletteHex = (value) => {
                if (!value) return null;
                const v = String(value).trim();
                if (/^#[0-9a-fA-F]{6}$/.test(v)) return v;
                const m = v.match(/^var\(\s*--([a-z0-9-]+)\s*\)$/i);
                if (m) return rampMap[m[1]] ?? null;
                return null;
            };
            const defaultPalette = (settings.color?.palettes || {}).default || {};
            const paletteSlots = [];
            for (const key of Object.keys(defaultPalette)) {
                const m = key.match(/^(.*)-(on|hover|active)$/);
                if (m && defaultPalette[m[1]] !== undefined) continue;
                paletteSlots.push(key);
            }
            const STATE_MIX = { hover: 12, active: 20 };
            for (const slot of paletteSlots) {
                const value = defaultPalette[slot];
                rows.push({ variable: `--palette-${slot}`, category: 'Colors', value });
                if (defaultPalette[`${slot}-on`] !== undefined) {
                    rows.push({ variable: `--palette-${slot}-on`, category: 'Colors', value: defaultPalette[`${slot}-on`] });
                }
                for (const [state, pct] of Object.entries(STATE_MIX)) {
                    if (defaultPalette[`${slot}-${state}`] !== undefined) {
                        rows.push({ variable: `--palette-${slot}-${state}`, category: 'Colors', value: defaultPalette[`${slot}-${state}`] });
                    } else {
                        const hex = resolvePaletteHex(value);
                        const [L] = hex ? hexToOklch(hex) : [0];
                        const pole = hex && L > 0.5 ? 'white' : 'black';
                        rows.push({ variable: `--palette-${slot}-${state}`, category: 'Colors',
                            value: `color-mix(in srgb, var(--palette-${slot}), ${pole} ${pct}%)` });
                    }
                }
            }

            // Borders (presence is membership — no enabled gate)
            for (const [size, data] of Object.entries(settings.borders?.sizes || {})) {
                if (data.value !== undefined) {
                    rows.push({ variable: `--border-${size}`, category: 'Borders', value: `${data.value}px` });
                }
            }

            // Shadows
            for (const [size, s] of Object.entries(settings.shadows?.sizes || {})) {
                if (typeof s !== 'object') continue;
                const val = `${s.x ?? 0}px ${s.y ?? 0}px ${s.blur ?? 0}px ${s.spread ?? 0}px rgba(0,0,0,${s.opacity ?? 0.1})`;
                rows.push({ variable: `--shadow-${size}`, category: 'Shadows', value: val });
            }

            // Radius
            for (const [size, data] of Object.entries(settings.radius?.sizes || {})) {
                if (data.value !== undefined) {
                    rows.push({ variable: `--radius-${size}`, category: 'Radius', value: `${data.value}px` });
                }
            }

            this.rows = rows;
        }
    }));
});
</script>

<section class="anti-section" data-padding-top="xl" data-padding-bottom="xl" data-gap="l">
    <div class="anti-section__inner">
        <?php anti_component('intro', ['title' => 'Design Tokens', 'size' => 'l']); ?>

        <div class="anti-container" data-layout="grid" data-columns="1" data-align="stretch"
             x-data="tokenTable">
            <div class="anti-table-wrap">
                <table class="anti-table">
                    <thead class="anti-table__head">
                        <tr>
                            <th class="anti-table__th">Variable</th>
                            <th class="anti-table__th">Category</th>
                            <th class="anti-table__th">Value</th>
                        </tr>
                    </thead>
                    <tbody class="anti-table__body">
                        <template x-for="row in rows" :key="row.variable">
                            <tr class="anti-table__row">
                                <td class="anti-table__td" x-text="row.variable"></td>
                                <td class="anti-table__td" x-text="row.category"></td>
                                <td class="anti-table__td" x-text="row.value"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
