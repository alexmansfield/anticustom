<?php
/**
 * Anticustom Component Helpers
 *
 * Standalone helper functions for rendering components.
 * Merged from anticustom-components/explorer.php and antisloth/app/Helpers/anti.php.
 */

// === Class & Attribute Helpers ===

if (!function_exists('anti_classes')) {
    /**
     * Build a class string from conditional class array.
     *
     * @param array $classes Array of class => condition pairs
     * @return string Space-separated class string
     */
    function anti_classes(array $classes): string
    {
        $result = [];

        foreach ($classes as $class => $condition) {
            if ($condition) {
                $result[] = $class;
            }
        }

        return implode(' ', $result);
    }
}

if (!function_exists('anti_attrs')) {
    /**
     * Build HTML attributes from array, skipping null/false/empty values.
     *
     * @param array $attrs Array of attribute => value pairs
     * @return string HTML attributes string
     */
    function anti_attrs(array $attrs): string
    {
        $result = [];

        foreach ($attrs as $attr => $value) {
            if ($value !== null && $value !== false && $value !== '') {
                $result[] = $attr . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
            }
        }

        return implode(' ', $result);
    }
}

if (!function_exists('attr_escape')) {
    function attr_escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('html_escape')) {
    function html_escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('url_escape')) {
    function url_escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// === Component Discovery ===

if (!function_exists('scan_components')) {
    /**
     * Scan a directory for component schemas.
     *
     * @param string $baseDir Components directory path
     * @return array Component data keyed by name
     */
    function scan_components(string $baseDir): array
    {
        $components = [];
        $dirs = glob($baseDir . '/*', GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            $name = basename($dir);
            $schemaFile = $dir . '/' . $name . '.schema.json';

            if (file_exists($schemaFile)) {
                $schema = json_decode(file_get_contents($schemaFile), true);
                $components[$name] = [
                    'name' => $name,
                    'label' => $schema['label'] ?? ucfirst($name),
                    'schema' => $schema,
                    'path' => $dir,
                    'styles' => scan_styles($dir),
                ];
            }
        }

        return $components;
    }
}

if (!function_exists('scan_styles')) {
    /**
     * Scan a component directory for available style variations.
     *
     * @param string $componentDir Component directory path
     * @return array List of style names (excluding _base.css)
     */
    function scan_styles(string $componentDir): array
    {
        $styles = [];
        $styleDir = $componentDir . '/styles';

        if (is_dir($styleDir)) {
            $files = glob($styleDir . '/*.css');
            foreach ($files as $file) {
                $name = basename($file, '.css');
                if ($name[0] !== '_') {
                    $styles[] = $name;
                }
            }
        }

        return $styles;
    }
}

// === Rendering ===

if (!function_exists('render_component')) {
    /**
     * Render a component template with props.
     *
     * @param string $componentDir Component directory path
     * @param array $props Component props
     * @return string Rendered HTML
     */
    function render_component(string $componentDir, array $props): string
    {
        $templateFile = $componentDir . '/templates/php.php';

        if (!file_exists($templateFile)) {
            return '<!-- Template not found -->';
        }

        ob_start();
        include $templateFile;
        return ob_get_clean();
    }
}

if (!function_exists('get_default_props')) {
    /**
     * Extract default prop values from a component schema.
     *
     * @param array $schema Component schema
     * @return array Default prop values keyed by field name
     */
    function get_default_props(array $schema): array
    {
        $props = [];

        foreach ($schema['fields'] ?? [] as $field) {
            $props[$field['name']] = $field['default'] ?? '';
        }

        return $props;
    }
}

// === Stub Functions (Phase 3) ===

if (!function_exists('render_components')) {
    /**
     * Render an array of child components.
     *
     * @param array $components Array of child component definitions
     * @return void
     */
    function render_components(array $components): void
    {
        // TODO: Phase 3
    }
}

if (!function_exists('anti_component')) {
    /**
     * Render a component by type name and output directly.
     *
     * @param string $type Component type name
     * @param array $props Component props
     * @return void
     */
    function anti_component(string $type, array $props): void
    {
        // TODO: Phase 3
    }
}

if (!function_exists('anti_get_schema')) {
    /**
     * Load a component schema by name.
     *
     * @param string $name Component name
     * @return array Schema data
     */
    function anti_get_schema(string $name): array
    {
        // TODO: Phase 3
        return [];
    }
}

if (!function_exists('resolve_child_props')) {
    /**
     * Resolve child component props from parent schema defaults.
     *
     * @param array $schema Parent component schema
     * @param int $index Child index
     * @param array $props Raw child props
     * @return array Resolved props
     */
    function resolve_child_props(array $schema, int $index, array $props): array
    {
        // TODO: Phase 3
        return $props;
    }
}
