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
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>
</head>
<body>
    <!-- Panel JS injects sidebar into body -->
    <script src="js/panel.js"></script>

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
