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
        static $cache = [];

        if (isset($cache[$baseDir])) {
            return $cache[$baseDir];
        }

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

        $cache[$baseDir] = $components;
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
        $componentName = basename($componentDir);
        $templateFile = $componentDir . '/templates/' . $componentName . '.php';

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

// === Component Registry ===

if (!function_exists('anti_components_dir')) {
    /**
     * Get or set the components base directory.
     *
     * @param string|null $dir Set the base directory (null to just get)
     * @return string Current base directory
     */
    function anti_components_dir(?string $dir = null): string
    {
        static $base = null;
        if ($dir !== null) $base = rtrim($dir, '/');
        return $base ?? __DIR__;
    }
}

// === Schema & Rendering ===

if (!function_exists('anti_get_schema')) {
    /**
     * Load a component schema by name.
     * Results are cached to avoid re-reading files.
     *
     * @param string $name Component name
     * @return array Schema data
     */
    function anti_get_schema(string $name): array
    {
        static $cache = [];

        if (isset($cache[$name])) {
            return $cache[$name];
        }

        $path = anti_components_dir() . "/{$name}/{$name}.schema.json";

        if (!file_exists($path)) {
            $cache[$name] = [];
            return [];
        }

        $schema = json_decode(file_get_contents($path), true);
        $cache[$name] = is_array($schema) ? $schema : [];

        return $cache[$name];
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
    function anti_component(string $type, array $props = []): void
    {
        $schema = anti_get_schema($type);
        $defaults = get_default_props($schema);
        $merged = array_merge($defaults, $props);

        $componentDir = anti_components_dir() . '/' . $type;
        echo render_component($componentDir, $merged);
    }
}

if (!function_exists('resolve_child_props')) {
    /**
     * Resolve child component props from parent schema defaults.
     * Merges slot defaults with the provided props (props win).
     *
     * @param array $schema Parent component schema
     * @param int $index Child slot index
     * @param array $props Raw child props
     * @return array Resolved props with slot defaults applied
     */
    function resolve_child_props(array $schema, int $index, array $props): array
    {
        $slots = $schema['children']['slots'] ?? [];

        if (!isset($slots[$index])) {
            return $props;
        }

        $defaults = $slots[$index]['defaults'] ?? [];
        return array_merge($defaults, $props);
    }
}

if (!function_exists('render_components')) {
    /**
     * Render an array of component definitions.
     * Each entry should have 'type' and optionally 'props'.
     *
     * @param array $components Array of ['type' => string, 'props' => array]
     * @return void
     */
    function render_components(array $components): void
    {
        foreach ($components as $component) {
            $type = $component['type'] ?? '';
            $props = $component['props'] ?? [];

            if (!empty($type)) {
                anti_component($type, $props);
            }
        }
    }
}

// === Interface Styles ===

if (!function_exists('anti_interface_css')) {
    /**
     * Build inline CSS from interface props (padding, border, radius, shadow).
     * Returns a CSS string for use in a style attribute, or empty string.
     *
     * @param array $props Component props containing interface values
     * @return string CSS declarations (e.g. "padding: var(--space-m); box-shadow: var(--shadow-l)")
     */
    function anti_interface_css(array $props): string
    {
        $parts = [];

        $padding = $props['padding'] ?? '';
        if ($padding !== '') {
            $parts[] = 'padding: var(--space-' . attr_escape($padding) . ')';
        }

        $borderWidth = $props['border_width'] ?? '';
        if ($borderWidth !== '') {
            $parts[] = 'border: var(--border-' . attr_escape($borderWidth) . ') solid var(--colorway-soft-contrast)';
        }

        $borderRadius = $props['border_radius'] ?? '';
        if ($borderRadius !== '') {
            $parts[] = 'border-radius: var(--radius-' . attr_escape($borderRadius) . ')';
        }

        $shadow = $props['shadow'] ?? '';
        if ($shadow !== '') {
            $parts[] = 'box-shadow: var(--shadow-' . attr_escape($shadow) . ')';
        }

        return implode('; ', $parts);
    }
}

// === Interpolation ===

if (!function_exists('anti_interpolate')) {
    /**
     * Replace {field} and {nested.field} placeholders with data values.
     * Used by table and other components to resolve row data in child props.
     *
     * @param string $template String with {field} placeholders
     * @param array $data Data to interpolate from
     * @return string Resolved string
     */
    function anti_interpolate(string $template, array $data): string
    {
        return preg_replace_callback('/\{([a-zA-Z0-9_.]+)\}/', function ($matches) use ($data) {
            $key = $matches[1];

            // Handle nested keys like {user.name}
            if (str_contains($key, '.')) {
                $value = $data;
                foreach (explode('.', $key) as $segment) {
                    if (!is_array($value) || !array_key_exists($segment, $value)) {
                        return $matches[0]; // Return placeholder unchanged
                    }
                    $value = $value[$segment];
                }
                return is_scalar($value) ? (string) $value : $matches[0];
            }

            if (array_key_exists($key, $data) && is_scalar($data[$key])) {
                return (string) $data[$key];
            }

            return $matches[0];
        }, $template);
    }
}

if (!function_exists('anti_interpolate_props')) {
    /**
     * Recursively interpolate all string values in a props array.
     *
     * @param array $props Props with potential {field} placeholders
     * @param array $data Data to interpolate from
     * @return array Props with placeholders resolved
     */
    function anti_interpolate_props(array $props, array $data): array
    {
        $result = [];
        foreach ($props as $key => $value) {
            if (is_string($value)) {
                $result[$key] = anti_interpolate($value, $data);
            } elseif (is_array($value)) {
                $result[$key] = anti_interpolate_props($value, $data);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
