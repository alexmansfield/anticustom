<?php
/**
 * Rich Text Sanitizer Verification Script
 *
 * Asserts the sanitizer's allowlist behavior across capability tiers.
 * Run: php richtext/verify.php
 */

require_once __DIR__ . '/sanitize.php';

$passed = 0;
$failed = 0;
$errors = [];

/**
 * Assert sanitizer output for a given input + field definition.
 */
function verify_rt(string $label, string $input, ?array $fieldDef, string $expected): void
{
    global $passed, $failed, $errors;

    $actual = anti_rt_sanitize($input, anti_rt_features($fieldDef));
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

$tier1 = null; // no richtext options at all
$tier2 = ['richtext' => ['marks' => ['bold', 'italic']]];
$tier3 = ['richtext' => ['marks' => ['bold', 'italic'], 'styles' => ['highlight', 'accent'], 'multiline' => true]];
$tier4 = ['richtext' => ['marks' => ['bold'], 'links' => true, 'blocks' => ['ul']]];

// ─────────────────────────────────────────────────
// Tier 1: everything stripped to text
// ─────────────────────────────────────────────────
verify_rt('tier1 strips marks', 'Hello <strong>world</strong>', $tier1, 'Hello world');
verify_rt('tier1 strips spans', '<span class="anti-rt-highlight">x</span>', $tier1, 'x');
verify_rt('tier1 plain text unchanged', 'Just text', $tier1, 'Just text');

// ─────────────────────────────────────────────────
// Tier 2: marks allowed, normalized
// ─────────────────────────────────────────────────
verify_rt('keeps strong/em', 'A <strong>b</strong> <em>c</em>', $tier2, 'A <strong>b</strong> <em>c</em>');
verify_rt('normalizes b to strong', '<b>bold</b>', $tier2, '<strong>bold</strong>');
verify_rt('normalizes i to em', '<i>italic</i>', $tier2, '<em>italic</em>');
verify_rt('nested marks survive', '<strong><em>x</em></strong>', $tier2, '<strong><em>x</em></strong>');
verify_rt('span unwrapped at tier2', '<span class="anti-rt-highlight">x</span>', $tier2, 'x');
verify_rt('br dropped when not multiline', 'a<br>b', $tier2, 'ab');

// ─────────────────────────────────────────────────
// Dangerous content
// ─────────────────────────────────────────────────
verify_rt('script dropped with content', 'safe<script>alert(1)</script>', $tier2, 'safe');
verify_rt('style dropped with content', 'a<style>*{}</style>b', $tier2, 'ab');
verify_rt('iframe dropped', '<iframe src="https://evil.example"></iframe>x', $tier2, 'x');
verify_rt('event handlers stripped', '<strong onmouseover="alert(1)">x</strong>', $tier2, '<strong>x</strong>');
verify_rt('comments dropped', 'a<!-- secret -->b', $tier2, 'ab');
verify_rt('unknown tags unwrapped', '<h1>title</h1>', $tier2, 'title');
verify_rt('valid marks inside invalid wrappers survive', '<div><b>x</b></div>', $tier2, '<strong>x</strong>');

// ─────────────────────────────────────────────────
// Tier 3: styled spans + multiline
// ─────────────────────────────────────────────────
verify_rt('keeps registered style', '<span class="anti-rt-highlight">x</span>', $tier3, '<span class="anti-rt-highlight">x</span>');
verify_rt('filters foreign classes', '<span class="anti-rt-highlight evil">x</span>', $tier3, '<span class="anti-rt-highlight">x</span>');
verify_rt('unregistered style unwrapped', '<span class="anti-rt-bogus">x</span>', $tier3, 'x');
verify_rt('classless span unwrapped', '<span>x</span>', $tier3, 'x');
verify_rt('br kept when multiline', 'a<br>b', $tier3, 'a<br>b');
verify_rt('style not opted in unwrapped', '<span class="anti-rt-accent">x</span>', ['richtext' => ['styles' => ['highlight']]], 'x');

// ─────────────────────────────────────────────────
// Tier 4 (sanitizer support; engine defers)
// ─────────────────────────────────────────────────
verify_rt('link kept with safe href', '<a href="https://example.com">x</a>', $tier4, '<a href="https://example.com" rel="noopener">x</a>');
verify_rt('javascript href rejected', '<a href="javascript:alert(1)">x</a>', $tier4, 'x');
verify_rt('data href rejected', '<a href="data:text/html,x">x</a>', $tier4, 'x');
verify_rt('mailto href kept', '<a href="mailto:a@b.co">x</a>', $tier4, '<a href="mailto:a@b.co" rel="noopener">x</a>');
verify_rt('relative href kept', '<a href="/about">x</a>', $tier4, '<a href="/about" rel="noopener">x</a>');
verify_rt('list kept when blocks allow', '<ul><li>a</li></ul>', $tier4, '<ul><li>a</li></ul>');
verify_rt('links stripped at tier2', '<a href="https://example.com">x</a>', $tier2, 'x');

// ─────────────────────────────────────────────────
// Encoding round-trips
// ─────────────────────────────────────────────────
verify_rt('emoji round-trip', 'Café ☕ <strong>🚀</strong>', $tier2, 'Café ☕ <strong>🚀</strong>');
verify_rt('interpolation placeholder untouched', 'Hi <strong>{user.name}</strong>', $tier2, 'Hi <strong>{user.name}</strong>');

// ─────────────────────────────────────────────────
// anti_rt_css sanity
// ─────────────────────────────────────────────────
$css = anti_rt_css();
$cssOk = strpos($css, '.anti-rt-highlight') !== false && strpos($css, '.anti-rt-accent') !== false;
echo 'anti_rt_css emits registry classes ..... ' . ($cssOk ? 'OK' : 'FAIL') . "\n";
if ($cssOk) { $passed++; } else { $failed++; $errors[] = 'anti_rt_css missing expected classes'; }

// ─────────────────────────────────────────────────
// Summary
// ─────────────────────────────────────────────────
echo "\n{$passed} passed, {$failed} failed\n";

if ($failed > 0) {
    echo "\n" . implode("\n\n", $errors) . "\n";
    exit(1);
}
