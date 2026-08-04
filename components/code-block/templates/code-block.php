<?php
/**
 * Code Block Component
 *
 * Displays code in a formatted <pre><code> block.
 * No server-side syntax highlighting — outputs clean markup
 * with data attributes for client-side highlighting hooks.
 *
 * Props:
 * @var string $code         Code content (required)
 * @var string $language     Language label for display and highlighting
 * @var string $title        Optional title above the code block
 * @var bool   $line_numbers Show line numbers
 * @var string $max_height   Optional max height with scroll
 * @var string $palette     Color scheme: inherit|default|base|primary|secondary
 */

$code         = $props['code'] ?? '';
$language     = $props['language'] ?? '';
$title        = $props['title'] ?? '';
$line_numbers = $props['line_numbers'] ?? false;
$max_height   = $props['max_height'] ?? '';
$palette     = $props['palette'] ?? 'inherit';

if (empty($code)) {
    return;
}

$classes = anti_classes([
    'anti-code-block'                    => true,
    'anti-code-block--has-title'         => !empty($title),
    'anti-code-block--has-line-numbers'  => $line_numbers,
]);

$pre_style = !empty($max_height) ? "max-height: {$max_height}; overflow-y: auto;" : '';

$lines = explode("\n", $code);
?>

<?php
// Build palette attribute (skip if 'inherit' - let it inherit from parent)
$palette_attr = (!empty($palette) && $palette !== 'inherit')
    ? ' data-palette="' . attr_escape($palette) . '"'
    : '';
?>

<div class="<?php echo attr_escape($classes); ?>"<?php echo $palette_attr; ?><?php echo !empty($language) ? ' data-language="' . attr_escape($language) . '"' : ''; ?>>
    <?php if (!empty($title) || !empty($language)) : ?>
        <div class="anti-code-block__header">
            <?php if (!empty($title)) : ?>
                <span class="anti-code-block__title"><?php echo html_escape($title); ?></span>
            <?php endif; ?>
            <?php if (!empty($language)) : ?>
                <span class="anti-code-block__language"><?php echo html_escape($language); ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <pre class="anti-code-block__pre"<?php echo $pre_style ? ' style="' . attr_escape($pre_style) . '"' : ''; ?>><?php
        if ($line_numbers) : ?><code class="anti-code-block__code"><?php
            foreach ($lines as $i => $line) : ?><span class="anti-code-block__line" data-line="<?php echo $i + 1; ?>"><?php echo html_escape($line); ?></span>
<?php       endforeach; ?></code><?php
        else : ?><code class="anti-code-block__code"><?php echo html_escape($code); ?></code><?php
        endif; ?></pre>
</div>
