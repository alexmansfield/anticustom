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
            const schema = window.ANTI_SCHEMA;
            if (!settings || !schema) return;

            const rows = [];
            const sizes = schema.sizes || {};

            // Spacing
            const spaceBase = settings.spacing?.baseSize ?? 16;
            const spaceScale = settings.spacing?.scale ?? 1.5;
            for (const [size, def] of Object.entries(sizes.spacingSizes?.items || {})) {
                const sizeData = settings.spacing?.sizes?.[size] ?? {};
                let val;
                if (sizeData.enabled && sizeData.value !== undefined) {
                    val = sizeData.value;
                } else if (def.position !== undefined) {
                    val = Math.round(spaceBase * Math.pow(spaceScale, def.position));
                } else {
                    continue;
                }
                rows.push({ variable: `--space-${size}`, category: 'Spacing', value: `${val}px` });
            }

            // Typography — text sizes
            const textBase = settings.typography?.text?.baseSize ?? 16;
            const textScale = settings.typography?.text?.scale ?? 1.125;
            for (const [size, def] of Object.entries(sizes.textSizes?.items || {})) {
                const pos = def.position ?? 0;
                const val = Math.round(textBase * Math.pow(textScale, pos) * 10) / 10;
                rows.push({ variable: `--text-${size}`, category: 'Typography', value: `${val}px` });
            }

            // Typography — heading sizes
            const headingBase = settings.typography?.headings?.baseSize ?? 16;
            const headingScale = settings.typography?.headings?.scale ?? 1.618;
            for (const [key, def] of Object.entries(sizes.headingLevels?.items || {})) {
                const pos = def.position ?? 0;
                const cssKey = def.cssKey ?? `heading-${key}`;
                const val = Math.round(headingBase * Math.pow(headingScale, pos));
                rows.push({ variable: `--${cssKey}`, category: 'Typography', value: `${val}px` });
            }

            // Colors
            for (const section of Object.values(settings.color?.sections || {})) {
                for (const [name, colorData] of Object.entries(section.colors || {})) {
                    if (colorData.enabled && colorData.color) {
                        rows.push({ variable: `--${name}`, category: 'Colors', value: colorData.color });
                    }
                }
            }

            // Borders
            for (const [size, data] of Object.entries(settings.borders?.sizes || {})) {
                if (data.enabled && data.value !== undefined) {
                    rows.push({ variable: `--border-${size}`, category: 'Borders', value: `${data.value}px` });
                }
            }

            // Shadows
            for (const [size, s] of Object.entries(settings.shadows || {})) {
                if (typeof s !== 'object') continue;
                if (s.enabled === false) continue;
                const val = `${s.x ?? 0}px ${s.y ?? 0}px ${s.blur ?? 0}px ${s.spread ?? 0}px rgba(0,0,0,${s.opacity ?? 0.1})`;
                rows.push({ variable: `--shadow-${size}`, category: 'Shadows', value: val });
            }

            // Radius
            for (const [size, data] of Object.entries(settings.radius?.sizes || {})) {
                if (data.enabled && data.value !== undefined) {
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
