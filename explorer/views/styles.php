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

            // Colors
            for (const section of Object.values(settings.color?.sections || {})) {
                for (const [name, colorData] of Object.entries(section.colors || {})) {
                    if (colorData.enabled && colorData.color) {
                        rows.push({ variable: `--${name}`, category: 'Colors', value: colorData.color });
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
