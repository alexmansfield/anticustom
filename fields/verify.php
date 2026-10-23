<?php
/**
 * Field Sanitizer Verification Script
 *
 * Asserts the sanitizer's allowlist behavior for the input palette (ADR 0014):
 * plain types stay fully escaped, leantext resolves per-key against
 * fields/defaults.json then the floor, html resolves to its fixed allowlist
 * (ADR 0030), and the shared machinery's links/blocks support still holds.
 * Run: php fields/verify.php
 */

require_once __DIR__ . '/sanitize.php';

$passed = 0;
$failed = 0;
$errors = [];

/**
 * Assert sanitizer output for a given input + field definition.
 */
function verify_field(string $label, string $input, ?array $fieldDef, string $expected): void
{
    verify_features($label, $input, anti_field_features($fieldDef), $expected);
}

/**
 * Assert sanitizer output against a hand-built feature set (machinery tests
 * independent of any type's resolution).
 */
function verify_features(string $label, string $input, array $features, string $expected): void
{
    global $passed, $failed, $errors;

    $actual = anti_field_sanitize($input, $features);
    $ok = $actual === $expected;

    $dots = str_repeat('.', max(2, 40 - strlen($label)));
    echo "{$label} {$dots} " . ($ok ? 'OK' : 'FAIL') . "\n";

    if ($ok) {
        $passed++;
    } else {
        $failed++;
        $errors[] = "{$label}:\n  input:    {$input}\n  expected: {$expected}\n  actual:   {$actual}";
    }
}

$plain     = ['type' => 'text'];
$bare      = ['type' => 'leantext']; // resolves via defaults.json → floor: bold+italic, no styles, single-line
$boldOnly  = ['type' => 'leantext', 'leantext' => ['marks' => ['bold']]];
$noMarks   = ['type' => 'leantext', 'leantext' => ['marks' => []]];
$styled    = ['type' => 'leantext', 'leantext' => ['styles' => ['highlight', 'accent'], 'multiline' => true]];
$html      = ['type' => 'html'];
$machinery = ['marks' => ['bold'], 'styles' => [], 'multiline' => false, 'blocks' => ['ul'], 'links' => true];

// ─────────────────────────────────────────────────
// Plain types: everything stripped to text
// ─────────────────────────────────────────────────
verify_field('text type strips marks', 'Hello <strong>world</strong>', $plain, 'Hello world');
verify_field('text type strips spans', '<span class="anti-style-highlight">x</span>', $plain, 'x');
verify_field('null field def strips marks', 'Hello <strong>world</strong>', null, 'Hello world');
verify_field('plain text unchanged', 'Just text', $plain, 'Just text');

// ─────────────────────────────────────────────────
// Bare leantext: marks implied (per-key: defaults.json → floor)
// ─────────────────────────────────────────────────
verify_field('bare leantext keeps strong/em', 'A <strong>b</strong> <em>c</em>', $bare, 'A <strong>b</strong> <em>c</em>');
verify_field('bare leantext normalizes b to strong', '<b>bold</b>', $bare, '<strong>bold</strong>');
verify_field('bare leantext normalizes i to em', '<i>italic</i>', $bare, '<em>italic</em>');
verify_field('nested marks survive', '<strong><em>x</em></strong>', $bare, '<strong><em>x</em></strong>');
verify_field('bare leantext has no styles', '<span class="anti-style-highlight">x</span>', $bare, 'x');
verify_field('bare leantext is single-line', 'a<br>b', $bare, 'ab');

// ─────────────────────────────────────────────────
// Per-key narrowing: explicit keys replace the default wholesale
// ─────────────────────────────────────────────────
verify_field('marks narrowed to bold keeps strong', '<strong>x</strong>', $boldOnly, '<strong>x</strong>');
verify_field('marks narrowed to bold strips em', '<em>x</em>', $boldOnly, 'x');
verify_field('explicit empty marks strips all', '<strong>a</strong><em>b</em>', $noMarks, 'ab');

// ─────────────────────────────────────────────────
// Dangerous content
// ─────────────────────────────────────────────────
verify_field('script dropped with content', 'safe<script>alert(1)</script>', $bare, 'safe');
verify_field('style dropped with content', 'a<style>*{}</style>b', $bare, 'ab');
verify_field('iframe dropped', '<iframe src="https://evil.example"></iframe>x', $bare, 'x');
verify_field('event handlers stripped', '<strong onmouseover="alert(1)">x</strong>', $bare, '<strong>x</strong>');
verify_field('comments dropped', 'a<!-- secret -->b', $bare, 'ab');
verify_field('unknown tags unwrapped', '<h1>title</h1>', $bare, 'title');
verify_field('valid marks inside invalid wrappers survive', '<div><b>x</b></div>', $bare, '<strong>x</strong>');

// ─────────────────────────────────────────────────
// Styled leantext: named spans + multiline
// ─────────────────────────────────────────────────
verify_field('keeps registered style', '<span class="anti-style-highlight">x</span>', $styled, '<span class="anti-style-highlight">x</span>');
verify_field('filters foreign classes', '<span class="anti-style-highlight evil">x</span>', $styled, '<span class="anti-style-highlight">x</span>');
verify_field('unregistered style unwrapped', '<span class="anti-style-bogus">x</span>', $styled, 'x');
verify_field('classless span unwrapped', '<span>x</span>', $styled, 'x');
verify_field('legacy anti-rt class unwrapped', '<span class="anti-rt-highlight">x</span>', $styled, 'x');
verify_field('br kept when multiline', 'a<br>b', $styled, 'a<br>b');
verify_field('style not opted in unwrapped', '<span class="anti-style-accent">x</span>', ['type' => 'leantext', 'leantext' => ['styles' => ['highlight']]], 'x');
verify_field('marks still implied alongside styles', '<strong>x</strong>', $styled, '<strong>x</strong>');

// ─────────────────────────────────────────────────
// blocks/links do not resolve for leantext (html/richtext only)
// ─────────────────────────────────────────────────
verify_field('leantext cannot opt into links', '<a href="https://example.com">x</a>', ['type' => 'leantext', 'leantext' => ['links' => true]], 'x');
verify_field('leantext cannot opt into blocks', '<ul><li>a</li></ul>', ['type' => 'leantext', 'leantext' => ['blocks' => ['ul']]], 'a');

// ─────────────────────────────────────────────────
// html: fixed allowlist, no options (ADR 0030)
// ─────────────────────────────────────────────────
verify_field('html keeps paragraphs', '<p>One</p><p>Two</p>', $html, '<p>One</p><p>Two</p>');
verify_field('html keeps lists', '<ul><li>a</li></ul><ol><li>b</li></ol>', $html, '<ul><li>a</li></ul><ol><li>b</li></ol>');
verify_field('html keeps nested lists', '<ul><li>a<ul><li>b</li></ul></li></ul>', $html, '<ul><li>a<ul><li>b</li></ul></li></ul>');
verify_field('html keeps h2-h6', '<h2>a</h2><h3>b</h3><h4>c</h4><h5>d</h5><h6>e</h6>', $html, '<h2>a</h2><h3>b</h3><h4>c</h4><h5>d</h5><h6>e</h6>');
verify_field('html unwraps h1', '<h1>Title</h1><p>x</p>', $html, 'Title<p>x</p>');
verify_field('html keeps marks + br', '<p><strong>a</strong><br><em>b</em></p>', $html, '<p><strong>a</strong><br><em>b</em></p>');
verify_field('html normalizes b/i', '<b>a</b><i>b</i>', $html, '<strong>a</strong><em>b</em>');
verify_field('html keeps link rel/target as authored', '<a href="https://example.com" rel="nofollow sponsored" target="_blank">x</a>', $html, '<a href="https://example.com" rel="nofollow sponsored" target="_blank">x</a>');
verify_field('html strips other link attrs', '<a href="/x" style="color:red" onclick="alert(1)" class="c">x</a>', $html, '<a href="/x">x</a>');
verify_field('html rejects javascript href', '<a href="javascript:alert(1)">x</a>', $html, 'x');
verify_field('html rejects tab-injected href', '<a href="java&#9;script:alert(1)">x</a>', $html, 'x');
verify_field('html strips attrs from blocks', '<p onclick="alert(1)" class="lead" style="x">a</p>', $html, '<p>a</p>');
verify_field('html drops script with content', '<p>a</p><script>alert(1)</script>', $html, '<p>a</p>');
verify_field('html unwraps div/span', '<div><span class="anti-style-highlight">a</span></div>', $html, 'a');
verify_field('html leaves stray li alone', '<li>a</li>', $html, '<li>a</li>');
verify_field('html plain text stays one block', "One\n\nTwo", $html, "One\n\nTwo");
verify_field('html keeps bare less-than as text', 'a < b', $html, 'a &lt; b');
verify_field('html keeps less-than inside a block', '<p>3<5 and x <= y</p>', $html, '<p>3&lt;5 and x &lt;= y</p>');

// ─────────────────────────────────────────────────
// Links/blocks machinery (hand-built features)
// ─────────────────────────────────────────────────
verify_features('link kept with safe href', '<a href="https://example.com">x</a>', $machinery, '<a href="https://example.com">x</a>');
verify_features('javascript href rejected', '<a href="javascript:alert(1)">x</a>', $machinery, 'x');
verify_features('data href rejected', '<a href="data:text/html,x">x</a>', $machinery, 'x');
verify_features('tab-injected scheme rejected', '<a href="java&#9;script:alert(1)">x</a>', $machinery, 'x');
verify_features('newline-injected scheme rejected', '<a href="java&#10;script:alert(1)">x</a>', $machinery, 'x');
verify_features('leading-control scheme rejected', '<a href="&#1;javascript:alert(1)">x</a>', $machinery, 'x');
verify_features('mailto href kept', '<a href="mailto:a@b.co">x</a>', $machinery, '<a href="mailto:a@b.co">x</a>');
verify_features('relative href kept', '<a href="/about">x</a>', $machinery, '<a href="/about">x</a>');
verify_features('list kept when blocks allow', '<ul><li>a</li></ul>', $machinery, '<ul><li>a</li></ul>');
verify_field('links stripped for bare leantext', '<a href="https://example.com">x</a>', $bare, 'x');

// ─────────────────────────────────────────────────
// Encoding round-trips
// ─────────────────────────────────────────────────
verify_field('emoji round-trip', 'Café ☕ <strong>🚀</strong>', $bare, 'Café ☕ <strong>🚀</strong>');
verify_field('leantext keeps bare less-than as text', 'a < <strong>b</strong>', $bare, 'a &lt; <strong>b</strong>');
verify_field('interpolation placeholder untouched', 'Hi <strong>{user.name}</strong>', $bare, 'Hi <strong>{user.name}</strong>');

// ─────────────────────────────────────────────────
// Resolved features for the shipped schemas (defaults blend is never invisible)
// ─────────────────────────────────────────────────
$resolved = anti_field_features($bare);
$bareOk = $resolved['marks'] === ['bold', 'italic'] && $resolved['styles'] === [] && $resolved['multiline'] === false;
echo 'bare leantext resolves to the floor ..... ' . ($bareOk ? 'OK' : 'FAIL') . "\n";
if ($bareOk) { $passed++; } else { $failed++; $errors[] = 'bare leantext resolution: ' . json_encode($resolved); }

// ─────────────────────────────────────────────────
// anti_field_css sanity
// ─────────────────────────────────────────────────
$css = anti_field_css();
$cssOk = strpos($css, '.anti-style-highlight') !== false && strpos($css, '.anti-style-accent') !== false;
echo 'anti_field_css emits registry classes ... ' . ($cssOk ? 'OK' : 'FAIL') . "\n";
if ($cssOk) { $passed++; } else { $failed++; $errors[] = 'anti_field_css missing expected classes'; }

// ─────────────────────────────────────────────────
// Summary
// ─────────────────────────────────────────────────
echo "\n{$passed} passed, {$failed} failed\n";

if ($failed > 0) {
    echo "\n" . implode("\n\n", $errors) . "\n";
    exit(1);
}
