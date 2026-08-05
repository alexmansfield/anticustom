<?php
/**
 * Agent primer emitter (sketch) — spec → CLAUDE.md/AGENTS.md
 *
 * Hangs off the M5 spec lifecycle (ADR 0023). A published spec is the *contract*:
 * versioned token keys, labels, and an `extends` lineage. This CLI renders that
 * contract as a Markdown primer a person OR an AI can read to build to spec —
 * the "last-mile artifact" Astryx ships as generated AGENTS.md and we don't yet.
 *
 * It emits the CONTRACT from the spec (authoritative, versioned) and, when a
 * site is available, an advisory RESOLVED column from the generator — mirroring
 * the spec-vs-site split: the spec promises `--{key}` exists; the site supplies
 * the pixels. The primer never invents vocabulary; every row traces to a spec token.
 *
 *   php styles/primer.php <spec-name> [--site defaults.json] [--output docs/agents/tokens.md]
 *
 * Design intent, not shipped: values below (grouping heuristics, rule text) are a
 * first cut to make the shape concrete and reviewable on :8702-adjacent docs.
 */

// Borrow the generator as a library for load_spec (same argv-neutralizing dance
// as spec.php: keep argv[0] != "generate.php" so it stays in library mode).
$anti_real_argv = $argv; $anti_real_argc = $argc;
$argv = [$argv[0]]; $argc = 1; $GLOBALS['argv'] = $argv; $GLOBALS['argc'] = 1;
require __DIR__ . '/generate.php';
$argv = $anti_real_argv; $argc = $anti_real_argc;
$GLOBALS['argv'] = $argv; $GLOBALS['argc'] = $argc;

const SPEC_DIR = __DIR__ . '/specs';

function pfail(string $msg): void { fprintf(STDERR, "Error: %s\n", $msg); exit(1); }

// ── args ────────────────────────────────────────────────────────────────────
$name = null; $site = __DIR__ . '/defaults.json'; $output = null;
for ($i = 1; $i < $argc; $i++) {
    $a = $argv[$i];
    if ($a === '--site')        { $site = $argv[++$i] ?? pfail('--site needs a path'); }
    elseif ($a === '--output')  { $output = $argv[++$i] ?? pfail('--output needs a path'); }
    elseif ($a[0] !== '-')      { $name = $a; }
}
$name ?? pfail('usage: primer.php <spec-name> [--site <json>] [--output <md>]');

$spec = load_spec($name, SPEC_DIR);
$spec ?? pfail("no spec named '{$name}' in " . SPEC_DIR);

// ── resolve advisory values by running the generator on the site ─────────────
// The spec is the contract; the site is one witness that satisfies it. We parse
// the emitted CSS so the primer shows what `--{key}` *currently* resolves to,
// flagged advisory (a different site keeps the same promises with other pixels).
$resolved = [];
if (is_file($site)) {
    $css = shell_exec('php ' . escapeshellarg(__DIR__ . '/generate.php') . ' ' . escapeshellarg($site) . ' 2>/dev/null');
    if (is_string($css)) {
        // Scope-aware: a token re-resolves inside `[data-palette]`/`[data-intent]`
        // regions (ADR 0016), so a flat scan would capture an override, not the
        // canonical value. Only read declarations in `:root` — the contract's
        // resolution; regional overrides are a separate, situational concern.
        $selector = null;
        foreach (explode("\n", $css) as $line) {
            if (preg_match('/^\s*([^{};]+?)\s*\{\s*$/', $line, $s)) { $selector = trim($s[1]); continue; }
            if (str_contains($line, '}'))                          { $selector = null;         continue; }
            if ($selector === ':root'
                && preg_match('/^\s*(--[A-Za-z0-9-]+):\s*(.+?);\s*$/', $line, $m)) {
                $resolved[$m[1]] = $m[2];
            }
        }
    }
}

// ── group spec tokens into families by key shape (derivable, no new vocab) ───
// Grouping is presentational only — membership still lives in the spec (ADR 0025).
function family_of(string $key): string {
    if (str_starts_with($key, 'space')) return 'Spacing';
    if (str_starts_with($key, 'text'))  return 'Text';
    if (preg_match('/^h[1-6]$/', $key))  return 'Headings';
    if (in_array($key, ['border', 'radius', 'shadow'], true)) return 'Shape';
    if (str_starts_with($key, 'palette') || str_starts_with($key, 'ramp')) return 'Color';
    return 'Other';
}
$order = ['Spacing', 'Text', 'Headings', 'Shape', 'Color', 'Other'];
$groups = [];
foreach ($spec['tokens'] as $key => $meta) {
    $groups[family_of($key)][$key] = $meta['label'] ?? $key;
}

// ── render ───────────────────────────────────────────────────────────────────
$L = [];
$ver = $spec['version'];
$lineage = $spec['extends'] ? "extends `{$spec['extends']}`" : 'fresh root (no `extends`)';
$total = count($spec['tokens']);
$missing = array_filter(array_keys($spec['tokens']), fn($k) => !isset($resolved["--$k"]));

$L[] = "# {$name}@{$ver} — design token primer";
$L[] = "";
$L[] = "> **For humans and AI.** Generated from `styles/specs/{$name}@{$ver}.json`. This is the";
$L[] = "> agent-facing contract for the **{$name}** design language: the CSS custom properties";
$L[] = "> it guarantees, what each means, and the one rule for using them.";
$L[] = "";
$L[] = "## What this is";
$L[] = "";
$L[] = "`{$name}`, version {$ver} — {$lineage}. Guarantees **{$total} tokens**.";
$L[] = "A *spec* promises the token exists; a *site* supplies the value. The Resolved column";
$L[] = "below is one site (`" . basename($site) . "`) — advisory, not part of the contract.";
$L[] = "";
$L[] = "## The one rule";
$L[] = "";
$L[] = "**Reference every value as `var(--key)`. Never hardcode the resolved pixels or hex.**";
$L[] = "App and component code stays theme-agnostic (ADR 0027) so themes, dark mode, and";
$L[] = "spec evolution work automatically. If a token is missing, `var()` falls back to the";
$L[] = "family's bare alias (`--space`, `--text`, `--border`, `--radius`, `--shadow`) — never";
$L[] = "to a raw literal.";
$L[] = "";
$L[] = "## Token vocabulary";
$L[] = "";
foreach ($order as $fam) {
    if (empty($groups[$fam])) continue;
    $L[] = "### {$fam}";
    $L[] = "";
    $L[] = "| Reference | Meaning | Resolved (`" . basename($site) . "`, advisory) |";
    $L[] = "| --- | --- | --- |";
    foreach ($groups[$fam] as $key => $label) {
        $val = $resolved["--$key"] ?? '_(unmet by this site)_';
        $L[] = "| `var(--{$key})` | {$label} | `{$val}` |";
    }
    $L[] = "";
}
$L[] = "## Conformance";
$L[] = "";
if ($missing) {
    $L[] = "⚠ This site does **not** fully satisfy the spec — unmet: " .
           implode(', ', array_map(fn($k) => "`--$k`", $missing)) . ".";
    $L[] = "Run `php styles/verify.php` for the authoritative check.";
} else {
    $L[] = "✓ `" . basename($site) . "` satisfies every spec token (advisory; " .
           "`php styles/verify.php` is authoritative).";
}
$L[] = "";
$L[] = "## Anti-patterns";
$L[] = "";
$L[] = "- ❌ `padding: 16px` → ✅ `padding: var(--space-m)`";
$L[] = "- ❌ `color: #1a1a1a` → ✅ `color: var(--palette-on-surface)`";
$L[] = "- ❌ inventing a token the spec doesn't list — it won't emit. Draft + publish a new";
$L[] = "  spec version instead (`php styles/spec.php evolve {$name}`).";
$L[] = "- ❌ hand-writing `-hover`/`-active` colors — interaction states derive at the palette";
$L[] = "  tier (ADR 0026); read them as `var(--palette-…-hover)`.";
$L[] = "";
$L[] = "<!-- generated by styles/primer.php from {$name}@{$ver}; regenerate on publish -->";

$out = implode("\n", $L) . "\n";
if ($output) { file_put_contents($output, $out); fprintf(STDERR, "Wrote primer for %s@%d → %s\n", $name, $ver, $output); }
else         { echo $out; }
