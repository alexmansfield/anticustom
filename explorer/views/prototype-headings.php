<?php
/**
 * THROWAWAY PROTOTYPE — wayfinder ticket #34 (map #26)
 *
 * Scale-mode heading typography, round 2. Rejected in round 1: A (single global
 * block — flattens the hierarchy) and C (h1/h6 anchors — alien grammar).
 * This round: scale-shaped derivation, two ways, against today's values.
 *   B. Per-level retained — today's defaults.json values (reference)
 *   D. Stepped scale keyed to LEVEL — lh = base × ratio^position (the size grammar);
 *      letter-spacing steps arithmetically (a ratio can't cross zero)
 *   E. Derived from SIZE — lh = calc(a·em + b·rem), ls = calc(p·em + q·px);
 *      responds to computed px, so it adapts when the type scale itself changes
 *
 * Live-fed by the sidebar panel (baseSize / scale / per-level values react via
 * anti-settings-changed). Delete this file and its $validTools entry when #34 resolves.
 */
?>

<style>
/* PROTOTYPE styles — .proto-* namespace, throwaway */
.proto-bar {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1.5rem;
    background: #1e293b;
    color: #e2e8f0;
    border-radius: 8px;
    font: 500 0.8rem/1.4 ui-sans-serif, system-ui, sans-serif;
}
.proto-bar strong { color: #fbbf24; font-weight: 700; letter-spacing: 0.02em; }
.proto-bar label { display: flex; align-items: center; gap: 0.5em; white-space: nowrap; }
.proto-bar input[type="range"] { width: 110px; }
.proto-cols {
    display: grid;
    gap: 1rem;
    align-items: start;
}
.proto-bar .proto-toggles { display: flex; gap: 0.75em; }
.proto-bar .proto-toggles label { gap: 0.3em; }
.proto-col {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #fff;
    overflow: hidden;
}
.proto-col__head {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f1f5f9;
    border-bottom: 1px solid #cbd5e1;
    padding: 0.75rem 1rem;
    font: 400 0.75rem/1.5 ui-sans-serif, system-ui, sans-serif;
    color: #334155;
}
.proto-col__title { font-size: 0.9rem; font-weight: 700; color: #0f172a; margin: 0 0 0.15rem; }
.proto-col__desc { margin: 0 0 0.5rem; }
.proto-controls { display: flex; flex-direction: column; gap: 0.3rem; }
.proto-controls label {
    display: grid;
    grid-template-columns: 8.5em 1fr 3.5em;
    align-items: center;
    gap: 0.5em;
    white-space: nowrap;
}
.proto-controls input[type="range"] { width: 100%; min-width: 0; }
.proto-controls select { justify-self: start; }
.proto-controls output { text-align: right; font-variant-numeric: tabular-nums; }
.proto-col__body { padding: 1.25rem 1rem 2rem; }
.proto-h { margin: 0 0 0.35rem; font-family: inherit; }
.proto-meta {
    font: 400 0.7rem/1.4 ui-monospace, monospace;
    color: #94a3b8;
    margin: 0 0 0.6rem;
}
.proto-p {
    font-size: 0.85rem;
    line-height: 1.5;
    color: #475569;
    margin: 0 0 1.75rem;
    max-width: 46ch;
}
</style>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('protoHeadings', () => ({
        // Preview zoom: multiplies rendered font-size only; meta lines show true px.
        previewScale: 0.3,

        // Column visibility — B and D parked after round 3 (survivors: E and F).
        show: { b: false, d: false, e: true, f: true },

        // Model D — level-keyed: lh multiplies by a ratio each step up the scale
        // (base at h6, position 0 — same grammar as baseSize/scale). Letter-spacing
        // steps arithmetically per level: a ratio can't produce nonzero from zero.
        d: { lhBase: 1.4, lhRatio: 0.97, lsBase: 0, lsStep: -0.004, w: 600 },

        // Model E — size-keyed: lh = calc(a·em + b·rem), ls = calc(p·em + q·px).
        // The fixed term dominates at small sizes (loose) and vanishes at large (tight).
        // lhEm 1 + lhRem 0.5 (= 8px) is the em restatement of F's tuned calc(8px + 2ex):
        // "the text's own size plus 8px of leading" — differs from F by ≤0.02 across h1–h6.
        e: { lhEm: 1, lhRem: 0.5, lsEm: -0.022, lsPx: 0.35, w: 600 },

        // Model F — ACSS "Smart Line Height": lh = calc(leading + N·ex). The ex term
        // reads the typeface's real x-height, so the browser resolves it; letter-spacing
        // is borrowed from E (ACSS doesn't derive ls). exPerEm is measured for the meta lines.
        f: { leading: 8, exMult: 2, w: 600 },
        exPerEm: 0.5,

        headings: { baseSize: 16, scale: 1.618, sizes: {} },

        samples: {
            1: 'Design tokens that survive the redesign',
            2: 'Palettes, ramps, and the contrast scale working together',
            3: 'Scale mode computes every size from two numbers',
            4: 'Custom mode seeds from the current computation',
            5: 'Verify guards catch incomplete stores before generation',
            6: 'Chained fallbacks keep old references degrading gracefully',
        },

        init() {
            this.pull();
            window.addEventListener('anti-settings-changed', () => this.pull());
            this.$nextTick(() => {
                const probe = document.createElement('div');
                probe.style.cssText = 'position:absolute;visibility:hidden;font-size:100px;height:1ex;';
                this.$root.appendChild(probe);
                this.exPerEm = probe.offsetHeight / 100;
                probe.remove();
            });
        },

        pull() {
            const src = window.__antiSettings || window.ANTI_DEFAULTS || {};
            const h = src.typography?.headings;
            if (h) this.headings = h;
        },

        // generate.php maps h6 → position 0 … h1 → position 5
        pos(level) { return 6 - level; },

        sizePx(level) {
            return Math.round(this.headings.baseSize * Math.pow(this.headings.scale, this.pos(level)));
        },

        style(model, level) {
            const v = this.values(model, level);
            return {
                fontSize: (this.sizePx(level) * this.previewScale) + 'px',
                // F renders the real calc so the browser resolves ex from actual font metrics;
                // the leading scales with the zoom to keep proportions honest.
                lineHeight: model === 'f'
                    ? `calc(${this.f.leading * this.previewScale}px + ${this.f.exMult}ex)`
                    : v.lh,
                letterSpacing: v.ls + 'em',
                fontWeight: v.w,
            };
        },

        values(model, level) {
            if (model === 'd') return {
                lh: Math.round(this.d.lhBase * Math.pow(this.d.lhRatio, this.pos(level)) * 100) / 100,
                ls: Math.round((this.d.lsBase + this.d.lsStep * this.pos(level)) * 1000) / 1000,
                w: this.d.w,
            };
            if (model === 'e') {
                const size = this.sizePx(level);
                return {
                    lh: Math.round(((this.e.lhEm * size + this.e.lhRem * 16) / size) * 100) / 100,
                    ls: Math.round((this.e.lsEm + this.e.lsPx / size) * 1000) / 1000,
                    w: this.e.w,
                };
            }
            if (model === 'f') {
                const size = this.sizePx(level);
                return {
                    lh: Math.round(((this.f.leading + this.f.exMult * this.exPerEm * size) / size) * 100) / 100,
                    ls: Math.round((this.e.lsEm + this.e.lsPx / size) * 1000) / 1000,
                    w: this.f.w,
                };
            }
            const b = this.headings.sizes?.['h' + level] || {};
            return { lh: b.lineHeight ?? 1.3, ls: b.letterSpacing ?? 0, w: b.weight ?? 600 };
        },

        meta(model, level) {
            const v = this.values(model, level);
            return `h${level} · ${this.sizePx(level)}px · lh ${v.lh} · ls ${v.ls}em · w ${v.w}`;
        },
    }));
});
</script>

<section class="anti-section" data-padding-top="l" data-padding-bottom="xl">
    <div class="anti-section__inner" x-data="protoHeadings">

        <div class="proto-bar">
            <strong>PROTOTYPE</strong>
            <span>wayfinder #34 round 2 — scale-shaped heading styles · throwaway route</span>
            <label>
                Preview zoom
                <input type="range" min="0.2" max="1" step="0.05" x-model.number="previewScale">
                <output x-text="Math.round(previewScale * 100) + '%'"></output>
            </label>
            <div class="proto-toggles">
                <template x-for="key in ['b', 'd', 'e', 'f']" :key="'toggle' + key">
                    <label>
                        <input type="checkbox" x-model="show[key]">
                        <span x-text="key.toUpperCase()"></span>
                    </label>
                </template>
            </div>
            <span x-text="'scale ' + headings.scale + ' on ' + headings.baseSize + 'px (live from panel)'"></span>
        </div>

        <div class="proto-cols" :style="{ gridTemplateColumns: 'repeat(' + Object.values(show).filter(Boolean).length + ', minmax(0, 1fr))' }">

            <!-- B: per-level retained (reference) -->
            <div class="proto-col" x-show="show.b">
                <div class="proto-col__head">
                    <p class="proto-col__title">B — Per-level (today, reference)</p>
                    <p class="proto-col__desc">Today's defaults.json: six authored style blocks (lh 1.2→1.4, ls −0.02→0, w 700→600). Live — edit in the sidebar's typography panel.</p>
                </div>
                <div class="proto-col__body">
                    <template x-for="level in [1, 2, 3, 4, 5, 6]" :key="'b' + level">
                        <div>
                            <div class="proto-h" :style="style('b', level)" x-text="samples[level]"></div>
                            <p class="proto-meta" x-text="meta('b', level)"></p>
                            <p class="proto-p">Body copy sits here to anchor the hierarchy — one sentence at text-m so the heading reads in context.</p>
                        </div>
                    </template>
                </div>
            </div>

            <!-- D: stepped scale keyed to level -->
            <div class="proto-col" x-show="show.d">
                <div class="proto-col__head">
                    <p class="proto-col__title">D — Stepped scale (keyed to level)</p>
                    <p class="proto-col__desc">The size grammar applied to style: lh = base × ratio<sup>position</sup> from h6 up. Letter-spacing steps per level (arithmetic — a ratio can't cross zero). Blind to actual px: change the type scale and these values stay put.</p>
                    <div class="proto-controls">
                        <label>lh base (h6)
                            <input type="range" min="1" max="1.8" step="0.05" x-model.number="d.lhBase">
                            <output x-text="d.lhBase.toFixed(2)"></output>
                        </label>
                        <label>lh ratio / step
                            <input type="range" min="0.9" max="1.02" step="0.005" x-model.number="d.lhRatio">
                            <output x-text="d.lhRatio.toFixed(3)"></output>
                        </label>
                        <label>ls step / level
                            <input type="range" min="-0.01" max="0.002" step="0.001" x-model.number="d.lsStep">
                            <output x-text="d.lsStep.toFixed(3)"></output>
                        </label>
                        <label>weight (single)
                            <select x-model.number="d.w">
                                <template x-for="w in [400, 500, 600, 700, 800]" :key="w">
                                    <option :value="w" x-text="w" :selected="w === d.w"></option>
                                </template>
                            </select>
                            <span></span>
                        </label>
                    </div>
                </div>
                <div class="proto-col__body">
                    <template x-for="level in [1, 2, 3, 4, 5, 6]" :key="'d' + level">
                        <div>
                            <div class="proto-h" :style="style('d', level)" x-text="samples[level]"></div>
                            <p class="proto-meta" x-text="meta('d', level)"></p>
                            <p class="proto-p">Body copy sits here to anchor the hierarchy — one sentence at text-m so the heading reads in context.</p>
                        </div>
                    </template>
                </div>
            </div>

            <!-- E: derived from computed size -->
            <div class="proto-col" x-show="show.e">
                <div class="proto-col__head">
                    <p class="proto-col__title">E — Derived from size</p>
                    <p class="proto-col__desc">lh = calc(a·em + b·rem), ls = calc(p·em + q·px): the fixed term loosens small sizes and vanishes at large ones. Keyed to computed px — retune the type scale in the panel and this column adapts; D doesn't.</p>
                    <div class="proto-controls">
                        <label>lh em slope
                            <input type="range" min="0.9" max="1.4" step="0.05" x-model.number="e.lhEm">
                            <output x-text="e.lhEm.toFixed(2)"></output>
                        </label>
                        <label>lh rem constant
                            <input type="range" min="0" max="1" step="0.05" x-model.number="e.lhRem">
                            <output x-text="e.lhRem.toFixed(2)"></output>
                        </label>
                        <label>ls em slope
                            <input type="range" min="-0.05" max="0.01" step="0.002" x-model.number="e.lsEm">
                            <output x-text="e.lsEm.toFixed(3)"></output>
                        </label>
                        <label>ls px constant
                            <input type="range" min="0" max="1" step="0.05" x-model.number="e.lsPx">
                            <output x-text="e.lsPx.toFixed(2)"></output>
                        </label>
                        <label>weight (single)
                            <select x-model.number="e.w">
                                <template x-for="w in [400, 500, 600, 700, 800]" :key="w">
                                    <option :value="w" x-text="w" :selected="w === e.w"></option>
                                </template>
                            </select>
                            <span></span>
                        </label>
                    </div>
                </div>
                <div class="proto-col__body">
                    <template x-for="level in [1, 2, 3, 4, 5, 6]" :key="'e' + level">
                        <div>
                            <div class="proto-h" :style="style('e', level)" x-text="samples[level]"></div>
                            <p class="proto-meta" x-text="meta('e', level)"></p>
                            <p class="proto-p">Body copy sits here to anchor the hierarchy — one sentence at text-m so the heading reads in context.</p>
                        </div>
                    </template>
                </div>
            </div>

            <!-- F: ACSS Smart Line Height -->
            <div class="proto-col" x-show="show.f">
                <div class="proto-col__head">
                    <p class="proto-col__title">F — Smart Line Height (ACSS v4)</p>
                    <p class="proto-col__desc">lh = calc(leading + N·ex): a fixed gap plus N times the typeface's real x-height. Font-metric aware — swap the font and it re-derives. One authored knob in ACSS (leading); ls borrowed from E. <span x-text="'Measured x-height: ' + exPerEm.toFixed(2) + 'em.'"></span></p>
                    <div class="proto-controls">
                        <label>leading (px)
                            <input type="range" min="0" max="16" step="0.5" x-model.number="f.leading">
                            <output x-text="f.leading.toFixed(1)"></output>
                        </label>
                        <label>ex multiplier
                            <input type="range" min="1.6" max="2.8" step="0.05" x-model.number="f.exMult">
                            <output x-text="f.exMult.toFixed(2)"></output>
                        </label>
                        <label>weight (single)
                            <select x-model.number="f.w">
                                <template x-for="w in [400, 500, 600, 700, 800]" :key="w">
                                    <option :value="w" x-text="w" :selected="w === f.w"></option>
                                </template>
                            </select>
                            <span></span>
                        </label>
                    </div>
                </div>
                <div class="proto-col__body">
                    <template x-for="level in [1, 2, 3, 4, 5, 6]" :key="'f' + level">
                        <div>
                            <div class="proto-h" :style="style('f', level)" x-text="samples[level]"></div>
                            <p class="proto-meta" x-text="meta('f', level)"></p>
                            <p class="proto-p">Body copy sits here to anchor the hierarchy — one sentence at text-m so the heading reads in context.</p>
                        </div>
                    </template>
                </div>
            </div>

        </div>
    </div>
</section>
