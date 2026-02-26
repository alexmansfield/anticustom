<?php
/**
 * Anticustom Explorer — Entry Point
 *
 * Serves a browser-viewable workbench with:
 * - Generated token CSS applied to the page
 * - All component CSS loaded
 * - A settings panel sidebar (Alpine.js)
 * - Component-rendered main content area
 * - Navigation between Styles / Components / Forms views
 */

// Routing
$tool = $_GET['tool'] ?? 'styles';
$validTools = ['styles', 'components', 'forms'];
if (!in_array($tool, $validTools)) {
    $tool = 'styles';
}

// Setup
require_once __DIR__ . '/../components/render.php';
require_once __DIR__ . '/shared/css.php';
anti_components_dir(__DIR__ . '/../components');

// Active style (from cookie, default to plato)
$activeStyle = preg_replace('/[^a-z0-9-]/', '', $_COOKIE['antiExplorer_style'] ?? 'plato') ?: 'plato';

// Generate CSS
$tokenCSS = explorer_get_token_css();
$componentCSS = explorer_get_component_css($activeStyle);

// Navigation items
$navItems = [
    'styles'     => 'Styles',
    'components' => 'Components',
    'forms'      => 'Forms',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anticustom Explorer — <?php echo ucfirst($tool); ?></title>
    <style id="anti-tokens"><?php echo $tokenCSS; ?></style>
    <style id="anti-components"><?php echo $componentCSS; ?></style>
    <link rel="stylesheet" href="css/panel.css">
    <link rel="stylesheet" href="css/explorer.css">
    <link rel="stylesheet" href="vendor/coloris.min.css">
    <?php if ($tool === 'components') : ?>
        <link rel="stylesheet" href="css/playground.css">
    <?php endif; ?>
    <script src="vendor/coloris.min.js"></script>
    <script>Coloris({ alpha: false, format: 'hex', themeMode: 'light', margin: 8, swatches: [] });</script>
    <?php if ($tool === 'components') : ?>
        <script defer src="js/playground.js"></script>
    <?php endif; ?>
    <script defer src="vendor/alpine.min.js"></script>
</head>
<body>
    <!-- Schema + defaults inlined for panel.js (styles/ is outside document root) -->
    <script>
        window.ANTI_SCHEMA = <?php echo file_get_contents(__DIR__ . '/../styles/tokens.schema.json'); ?>;
        window.ANTI_DEFAULTS = <?php echo file_get_contents(__DIR__ . '/../styles/defaults.json'); ?>;
    </script>
    <!-- Panel JS injects sidebar into body -->
    <script defer src="js/panel.js"></script>

    <!-- Main content area -->
    <main class="anti-explorer" id="explorer-main">
        <nav class="anti-explorer__nav">
            <!-- Styles panel toggle -->
            <button
                x-data="{ open: localStorage.getItem('antiExplorer_isOpen') !== 'false' }"
                @anti-panel-toggled.window="open = $event.detail.isOpen"
                @click="window.dispatchEvent(new CustomEvent('antiTogglePanel'))"
                class="anti-explorer__nav-toggle"
                :class="{ 'is-active': open }"
                aria-label="Toggle style panel"
                title="Toggle style panel"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="13.5" cy="6.5" r="1.5" fill="currentColor" stroke="none"></circle>
                    <circle cx="17.5" cy="10.5" r="1.5" fill="currentColor" stroke="none"></circle>
                    <circle cx="8.5" cy="7.5" r="1.5" fill="currentColor" stroke="none"></circle>
                    <circle cx="6.5" cy="12.5" r="1.5" fill="currentColor" stroke="none"></circle>
                    <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"></path>
                </svg>
            </button>

            <!-- Colorway selector (components view only) -->
            <?php if ($tool === 'components') : ?>
            <select
                class="anti-explorer__colorway-select"
                x-data="{
                    colorway: localStorage.getItem('antiExplorer_previewColorway') || '',
                    colorways: (window.__antiColorways || []).filter(n => n !== 'default')
                }"
                x-model="colorway"
                @change="window.dispatchEvent(new CustomEvent('antiColorwayChange', { detail: { colorway: colorway } }))"
                @anti-colorways-changed.window="colorways = $event.detail.colorways.filter(n => n !== 'default')"
                aria-label="Preview colorway"
                title="Preview colorway"
            >
                <option value="">Default</option>
                <template x-for="c in colorways" :key="c">
                    <option :value="c" x-text="c.charAt(0).toUpperCase() + c.slice(1)" :selected="c === colorway"></option>
                </template>
            </select>
            <?php endif; ?>

            <!-- Center nav links -->
            <div class="anti-explorer__nav-links">
                <?php foreach ($navItems as $key => $label) : ?>
                    <a href="?tool=<?php echo $key; ?>"
                       class="anti-explorer__nav-link<?php echo $tool === $key ? ' is-active' : ''; ?>">
                        <?php echo $label; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Style selector (components view only) -->
            <?php if ($tool === 'components') : ?>
            <select
                class="anti-explorer__style-select"
                x-data="{ style: window.__antiActiveStyle || 'plato' }"
                x-model="style"
                @change="window.dispatchEvent(new CustomEvent('antiStyleChange', { detail: { style: style } }))"
                aria-label="Design style"
                title="Switch design style"
            >
                <option value="none" :selected="style === 'none'">None</option>
                <template x-for="s in (window.__antiStyles || ['plato'])" :key="s">
                    <option :value="s" x-text="s.charAt(0).toUpperCase() + s.slice(1) + ($store.componentPreview && $store.componentPreview.activeComponentStyles.length && !$store.componentPreview.activeComponentStyles.includes(s) ? ' (N/A)' : '')" :selected="s === style"></option>
                </template>
            </select>
            <?php endif; ?>

            <!-- Components panel toggle (or spacer) -->
            <?php if ($tool === 'components') : ?>
            <button
                x-data="{ open: localStorage.getItem('antiExplorer_componentPanelOpen') !== 'false' }"
                @anti-component-panel-toggled.window="open = $event.detail.isOpen"
                @click="window.dispatchEvent(new CustomEvent('antiToggleComponentPanel'))"
                class="anti-explorer__nav-toggle"
                :class="{ 'is-active': open }"
                aria-label="Toggle component panel"
                title="Toggle component panel"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="18" height="12" x="3" y="8" rx="1"></rect>
                    <path d="M10 8V5c0-.6-.4-1-1-1H6a1 1 0 0 0-1 1v3"></path>
                    <path d="M19 8V5c0-.6-.4-1-1-1h-3c-.6 0-1 .4-1 1v3"></path>
                </svg>
            </button>
            <?php else : ?>
            <div class="anti-explorer__nav-toggle-spacer"></div>
            <?php endif; ?>
        </nav>

        <div class="anti-explorer__content" data-tool="<?php echo $tool; ?>">
            <?php
            $viewFile = __DIR__ . "/views/{$tool}.php";
            if (file_exists($viewFile)) {
                include $viewFile;
            }
            ?>
        </div>
    </main>
</body>
</html>
