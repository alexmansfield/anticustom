<?php
/**
 * CSS Aggregation Helper
 *
 * Collects all CSS needed for the explorer page:
 * - Token CSS variables (generated from defaults.json)
 * - Component CSS (_base.css + {style}.css for each component)
 *
 * Requires components/render.php for scan_components().
 */

/**
 * Generate token CSS by capturing output from styles/generate.php.
 *
 * generate.php is a CLI script that reads defaults.json and echoes CSS.
 * We buffer its output and restore the global state afterward.
 */
function explorer_get_token_css(): string {
    $savedArgv = $GLOBALS['argv'] ?? [];
    $savedArgc = $GLOBALS['argc'] ?? 0;
    $GLOBALS['argv'] = ['generate.php'];
    $GLOBALS['argc'] = 1;

    $cwd = getcwd();
    ob_start();
    chdir(dirname(__DIR__) . '/../styles');
    include dirname(__DIR__) . '/../styles/generate.php';
    chdir($cwd);

    $GLOBALS['argv'] = $savedArgv;
    $GLOBALS['argc'] = $savedArgc;

    return ob_get_clean();
}

/**
 * Concatenate all component CSS files.
 *
 * For each discovered component, loads _base.css then {$style}.css.
 */
function explorer_get_component_css(string $style = 'plato'): string {
    $css = '';
    $baseDir = dirname(__DIR__) . '/../components';
    $components = scan_components($baseDir);

    foreach ($components as $name => $comp) {
        $stylesDir = $comp['path'] . '/styles';

        $baseFile = $stylesDir . '/_base.css';
        if (file_exists($baseFile)) {
            $css .= "/* {$name} — base */\n";
            $css .= file_get_contents($baseFile) . "\n\n";
        }

        $styleFile = $stylesDir . '/' . $style . '.css';
        if (file_exists($styleFile)) {
            $css .= "/* {$name} — {$style} */\n";
            $css .= file_get_contents($styleFile) . "\n\n";
        }
    }

    return $css;
}
