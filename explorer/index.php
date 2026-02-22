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

// Generate CSS
$tokenCSS = explorer_get_token_css();
$componentCSS = explorer_get_component_css();

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
    <?php if ($tool === 'components') : ?>
        <link rel="stylesheet" href="css/playground.css">
    <?php endif; ?>
    <?php if ($tool === 'components') : ?>
        <script defer src="js/playground.js"></script>
    <?php endif; ?>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>
</head>
<body>
    <!-- Panel JS injects sidebar into body -->
    <script src="js/panel.js"></script>

    <!-- Panel toggle (visible when panel is hidden) -->
    <button
        x-data="{ hidden: localStorage.getItem('antiExplorer_isOpen') === 'false' }"
        x-show="hidden"
        x-transition
        @anti-panel-toggled.window="hidden = !$event.detail.isOpen"
        @click="window.dispatchEvent(new CustomEvent('antiOpenPanel')); hidden = false"
        class="anti-panel-toggle"
        aria-label="Open style panel"
        title="Open style panel"
    >
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
            <path d="M9 3v18"></path>
        </svg>
    </button>

    <!-- Main content area -->
    <main class="anti-explorer" id="explorer-main">
        <nav class="anti-explorer__nav">
            <?php foreach ($navItems as $key => $label) : ?>
                <a href="?tool=<?php echo $key; ?>"
                   class="anti-explorer__nav-link<?php echo $tool === $key ? ' is-active' : ''; ?>">
                    <?php echo $label; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="anti-explorer__content">
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
