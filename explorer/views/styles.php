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

            // Open-set scale family: every step in `sizes` is a member, computed
            // from the anchor (default) step by anchorValue * ratio^(pos − anchorPos).
            const scaleRows = (fam, prefix, category, decimal) => {
                if (!fam?.sizes) return;
                const anchor = fam.sizes[fam.default] || {};
                const aVal = anchor.value ?? 16;
                const aPos = anchor.position ?? 0;
                const ratio = fam.ratio ?? 1;
                for (const [key, def] of Object.entries(fam.sizes)) {
                    const pos = def.position ?? 0;
                    const raw = aVal * Math.pow(ratio, pos - aPos);
                    const val = decimal ? Math.round(raw * 10) / 10 : Math.round(raw);
                    rows.push({ variable: `--${prefix}${key}`, category, value: `${val}px` });
                }
            };

            scaleRows(settings.spacing, 'space-', 'Spacing', false);
            scaleRows(settings.typography?.text, 'text-', 'Typography', true);
            // Headings are key-identity: keys h1–h6 emit --h1…--h6 (no prefix).
            scaleRows(settings.typography?.headings, '', 'Typography', false);

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
