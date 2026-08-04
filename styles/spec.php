<?php
/**
 * Spec lifecycle helper (ADR 0023 — draft → publish → evolve)
 *
 * A spec is a file. This CLI performs the *mechanical* half of the lifecycle:
 * freezing a draft into an immutable, versioned artifact and stamping the
 * inheritance edge. Who may draft or publish is host-side policy, out of scope.
 *
 * File model (styles/specs/):
 *   {name}@{N}.json         canonical, immutable — never rewritten once written
 *   {name}.json             convenience "latest" pointer, mirrors the top version
 *   {name}@{N}.draft.json   a working draft seeded by `evolve` (freely edited)
 *
 * A site follows a spec by name: "base" resolves to the latest pointer, a pinned
 * "base@1" resolves to that frozen version (load_spec reads {name}.json either
 * way — the `@N` is just part of the filename). Prior versions keep their
 * promises to whatever still follows them.
 *
 * Commands:
 *   php styles/spec.php publish <draft.json>   freeze + stamp extends + advance latest
 *   php styles/spec.php evolve  <name>         seed the next version's draft
 *   php styles/spec.php extends <draft.json>   dry-run: report the extends stamp only
 *
 * The mechanical extends rule (ADR 0023): a successor retaining *every* token of
 * its seed is stamped `extends: {name}@{seedVersion}` — skins following the seed
 * stay guaranteed. Dropping any promised token clears the stamp (a break).
 */

// Load the generator as a library for load_spec / spec_extends_stamp. It parses
// $argv for an input path at include time, so neutralize argv across the require
// (leaving argv[0] non-"generate.php" keeps it in library mode — no emission) and
// restore ours for the command dispatch below.
$anti_real_argv = $argv; $anti_real_argc = $argc;
$argv = [$argv[0]]; $argc = 1; $GLOBALS['argv'] = $argv; $GLOBALS['argc'] = 1;
require __DIR__ . '/generate.php';
$argv = $anti_real_argv; $argc = $anti_real_argc;
$GLOBALS['argv'] = $argv; $GLOBALS['argc'] = $argc;

const SPEC_DIR = __DIR__ . '/specs';

function fail(string $msg): void {
    fprintf(STDERR, "Error: %s\n", $msg);
    exit(1);
}

/** Read + decode a spec/draft JSON file, or die. */
function spec_read(string $path): array {
    if (!is_file($path)) fail("file not found: {$path}");
    $data = json_decode(file_get_contents($path), true);
    if (!is_array($data)) fail("invalid JSON in {$path}");
    foreach (['name', 'version', 'tokens'] as $req) {
        if (!isset($data[$req])) fail("spec is missing required key '{$req}' ({$path})");
    }
    return $data;
}

/** Pretty-print a spec to disk with a trailing newline (stable diff shape). */
function spec_write(string $path, array $data): void {
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

/** Highest published version file for a name with version < $below (PHP_INT_MAX = any). Null if none. */
function spec_latest(string $name, int $below = PHP_INT_MAX): ?array {
    $best = null;
    foreach (glob(SPEC_DIR . '/' . $name . '@*.json') ?: [] as $file) {
        if (str_ends_with($file, '.draft.json')) continue;
        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data) || !isset($data['version'])) continue;
        $v = (int) $data['version'];
        if ($v < $below && ($best === null || $v > (int) $best['version'])) {
            $best = $data;
        }
    }
    return $best;
}

// ────────────────────────────────────────────────────────────────────────────

$cmd = $argv[1] ?? '';

if ($cmd === 'publish') {
    $draftPath = $argv[2] ?? fail('usage: spec.php publish <draft.json>');
    $draft = spec_read($draftPath);
    $name = $draft['name'];
    $version = (int) $draft['version'];

    $frozenPath = SPEC_DIR . '/' . $name . '@' . $version . '.json';
    if (is_file($frozenPath)) {
        fail("{$name}@{$version} is already published — published versions are immutable. Bump the version to evolve.");
    }

    // Stamp the inheritance edge against the seed (highest version below this one).
    $seed = spec_latest($name, $version);
    $draft['extends'] = spec_extends_stamp($draft['tokens'], $seed);

    spec_write($frozenPath, $draft);                        // immutable snapshot
    spec_write(SPEC_DIR . '/' . $name . '.json', $draft);   // advance the latest pointer

    $edge = $draft['extends'] ? "extends {$draft['extends']}" : 'no extends (fresh or breaking successor)';
    fprintf(STDERR, "Published %s@%d (%d tokens) — %s\n", $name, $version, count($draft['tokens']), $edge);
    exit(0);
}

if ($cmd === 'evolve') {
    $name = $argv[2] ?? fail('usage: spec.php evolve <name>');
    $latest = spec_latest($name) ?? load_spec($name, SPEC_DIR);
    if ($latest === null) fail("no published spec named '{$name}' to evolve");

    $next = (int) $latest['version'] + 1;
    $draft = [
        'name'    => $name,
        'version' => $next,
        'extends' => null,               // stamped mechanically at publish, not now
        'tokens'  => $latest['tokens'],  // seed: freely add / remove / relabel before publishing
    ];
    $draftPath = SPEC_DIR . '/' . $name . '@' . $next . '.draft.json';
    spec_write($draftPath, $draft);
    fprintf(STDERR, "Seeded draft %s (v%d, from v%d). Edit it, then: php styles/spec.php publish %s\n",
        $draftPath, $next, (int) $latest['version'], $draftPath);
    exit(0);
}

if ($cmd === 'extends') {
    $draftPath = $argv[2] ?? fail('usage: spec.php extends <draft.json>');
    $draft = spec_read($draftPath);
    $seed = spec_latest($draft['name'], (int) $draft['version']);
    $stamp = spec_extends_stamp($draft['tokens'], $seed);
    echo ($stamp ?? '(none)') . "\n";
    exit(0);
}

fail("unknown command '{$cmd}'. Commands: publish, evolve, extends");
