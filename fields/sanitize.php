<?php
/**
 * Anticustom Fields — server-side sanitizer & style registry
 *
 * Formatted field types (leantext today; richtext/html reserved) store
 * constrained HTML fragments. The security boundary is sanitize-on-output:
 * anti_field_html() (components/render.php) passes every value through
 * anti_field_sanitize() with an allowlist derived from the field's type and
 * options, so tampered stored props and interpolated values are both
 * neutralized at render time. Plain types (text/textarea) never reach the
 * sanitizer — they are always fully escaped.
 *
 * See docs/adr/0014-input-palette-five-field-types.md (amends 0013).
 */

if (!function_exists('anti_field_registry')) {
    /**
     * Load the global named-style registry (fields/styles.json).
     *
     * @return array { styles: { name: { label, css } } }
     */
    function anti_field_registry(): array
    {
        static $cache = null;

        if ($cache === null) {
            $file = __DIR__ . '/styles.json';
            $data = file_exists($file) ? json_decode(file_get_contents($file), true) : null;
            $cache = is_array($data) ? $data : ['styles' => []];
            if (!isset($cache['styles']) || !is_array($cache['styles'])) {
                $cache['styles'] = [];
            }
        }

        return $cache;
    }
}

if (!function_exists('anti_field_defaults')) {
    /**
     * Load the optional project-level field defaults (fields/defaults.json),
     * keyed by field type. Absent file = no project defaults (floor applies).
     *
     * @param string $type Field type (e.g. 'leantext')
     * @return array Options object for the type, or []
     */
    function anti_field_defaults(string $type): array
    {
        static $cache = null;

        if ($cache === null) {
            $file = __DIR__ . '/defaults.json';
            $data = file_exists($file) ? json_decode(file_get_contents($file), true) : null;
            $cache = is_array($data) ? $data : [];
        }

        $options = $cache[$type] ?? [];
        return is_array($options) ? $options : [];
    }
}

if (!function_exists('anti_field_features')) {
    /**
     * Resolve a field definition into a full feature set.
     *
     * Only formatted types carry features; anything else (text, textarea,
     * unknown, null) resolves to the empty set — always fully escaped.
     *
     * For leantext, each option key resolves independently (per-key):
     *   field options → project defaults (fields/defaults.json) → built-in floor.
     * The floor is the bare type's meaning: bold+italic marks, no styles,
     * single-line. An explicit key replaces the default wholesale (arrays
     * never union), so `"styles": []` narrows below a project default.
     *
     * blocks/links belong to the reserved richtext type and resolve to none
     * here; the sanitizer machinery below already supports them.
     *
     * @param array|null $fieldDef Schema field definition (or null)
     * @return array { marks: string[], styles: string[], multiline: bool, blocks: string[], links: bool }
     */
    function anti_field_features(?array $fieldDef): array
    {
        $none = ['marks' => [], 'styles' => [], 'multiline' => false, 'blocks' => [], 'links' => false];

        $type = $fieldDef['type'] ?? '';
        if ($type !== 'leantext') {
            return $none;
        }

        $floor = ['marks' => ['bold', 'italic'], 'styles' => [], 'multiline' => false];
        $projectDefaults = anti_field_defaults('leantext');
        $options = is_array($fieldDef['leantext'] ?? null) ? $fieldDef['leantext'] : [];

        // Per-key resolution: field → project default → floor
        $resolved = [];
        foreach ($floor as $key => $floorValue) {
            $resolved[$key] = $options[$key] ?? $projectDefaults[$key] ?? $floorValue;
        }

        $styles = $resolved['styles'];
        if ($styles === true) {
            $styles = array_keys(anti_field_registry()['styles']);
        }

        return [
            'marks'     => array_values(array_intersect((array) $resolved['marks'], ['bold', 'italic'])),
            'styles'    => array_values(array_intersect((array) $styles, array_keys(anti_field_registry()['styles']))),
            'multiline' => (bool) $resolved['multiline'],
            'blocks'    => [],
            'links'     => false,
        ];
    }
}

if (!function_exists('anti_field_sanitize')) {
    /**
     * Sanitize a formatted-field HTML fragment against a resolved feature set.
     *
     * Allowed nodes derive from features: bold→strong (b normalized),
     * italic→em (i normalized), styles→span[class=anti-style-*], multiline→br,
     * links→a[href], blocks→ul/ol/li. Dangerous elements are dropped with
     * their content; everything else is unwrapped so its text survives.
     *
     * @param string $html Stored fragment (may be hostile)
     * @param array $features Output of anti_field_features()
     * @return string Sanitized fragment
     */
    function anti_field_sanitize(string $html, array $features): string
    {
        if ($html === '' || strpos($html, '<') === false) {
            // No markup: escape entities the cheap way for parity with html output
            return htmlspecialchars(html_entity_decode($html, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
        }

        $allowed = ['strong' => [], 'em' => []];
        if (empty($features['marks']) || !in_array('bold', $features['marks'], true)) {
            unset($allowed['strong']);
        }
        if (empty($features['marks']) || !in_array('italic', $features['marks'], true)) {
            unset($allowed['em']);
        }
        if (!empty($features['styles'])) {
            $allowed['span'] = ['class'];
        }
        if (!empty($features['multiline'])) {
            $allowed['br'] = [];
        }
        if (!empty($features['links'])) {
            $allowed['a'] = ['href'];
        }
        if (!empty($features['blocks'])) {
            foreach ($features['blocks'] as $block) {
                $allowed[$block] = [];
            }
            $allowed['li'] = [];
        }

        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="utf-8"?><div id="anti-field-root">' . $html . '</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('anti-field-root');
        if (!$root) {
            return '';
        }

        anti_field_sanitize_node($root, $allowed, $features);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }
}

if (!function_exists('anti_field_sanitize_node')) {
    /**
     * Recursively sanitize the children of a node in place.
     * Internal helper for anti_field_sanitize().
     *
     * @param DOMNode $node Parent whose children are walked
     * @param array $allowed tag => allowed attribute names
     * @param array $features Resolved feature set (for class/href filtering)
     * @return void
     */
    function anti_field_sanitize_node(DOMNode $node, array $allowed, array $features): void
    {
        // Snapshot: the child list mutates as we drop/unwrap
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        $dropWithContent = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'noscript'];

        foreach ($children as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);
                continue;
            }

            if (!($child instanceof DOMElement)) {
                continue;
            }

            $tag = strtolower($child->nodeName);

            // Normalize legacy tags before the allowlist check
            if ($tag === 'b' || $tag === 'i') {
                $target = $tag === 'b' ? 'strong' : 'em';
                $replacement = $child->ownerDocument->createElement($target);
                while ($child->firstChild) {
                    $replacement->appendChild($child->firstChild);
                }
                $node->replaceChild($replacement, $child);
                $child = $replacement;
                $tag = $target;
            }

            if (in_array($tag, $dropWithContent, true)) {
                $node->removeChild($child);
                continue;
            }

            // Recurse first so grandchildren are clean before any unwrap
            anti_field_sanitize_node($child, $allowed, $features);

            if (!isset($allowed[$tag])) {
                anti_field_unwrap($child);
                continue;
            }

            // Strip attributes down to the allowlist
            $keep = $allowed[$tag];
            $attrs = [];
            foreach ($child->attributes as $attr) {
                $attrs[] = $attr->name;
            }
            foreach ($attrs as $name) {
                if (!in_array($name, $keep, true)) {
                    $child->removeAttribute($name);
                }
            }

            if ($tag === 'span') {
                $classes = preg_split('/\s+/', trim($child->getAttribute('class'))) ?: [];
                $valid = [];
                foreach ($classes as $class) {
                    if (preg_match('/^anti-style-([a-z0-9-]+)$/', $class, $m)
                        && in_array($m[1], $features['styles'], true)) {
                        $valid[] = $class;
                    }
                }
                if (empty($valid)) {
                    anti_field_unwrap($child);
                    continue;
                }
                $child->setAttribute('class', implode(' ', $valid));
            }

            if ($tag === 'a') {
                $href = trim($child->getAttribute('href'));
                if (!anti_field_safe_href($href)) {
                    anti_field_unwrap($child);
                    continue;
                }
                $child->setAttribute('rel', 'noopener');
            }
        }
    }
}

if (!function_exists('anti_field_unwrap')) {
    /**
     * Replace an element with its children (text survives, wrapper dies).
     *
     * @param DOMNode $element Element to unwrap
     * @return void
     */
    function anti_field_unwrap(DOMNode $element): void
    {
        $parent = $element->parentNode;
        if (!$parent) {
            return;
        }
        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }
}

if (!function_exists('anti_field_safe_href')) {
    /**
     * Allow http/https/mailto/tel and relative URLs; reject everything else
     * (javascript:, data:, vbscript:, protocol-relative is allowed as https).
     *
     * @param string $href Candidate href value
     * @return bool
     */
    function anti_field_safe_href(string $href): bool
    {
        if ($href === '') {
            return false;
        }
        // Browsers strip TAB/LF/CR (and treat other C0 controls as garbage) from
        // anywhere in a URL before parsing the scheme, so "java\tscript:" would
        // execute as "javascript:" and slip past the scheme check below. A
        // legitimate href never contains raw control characters — reject rather
        // than guess what a browser will collapse the value down to.
        if (preg_match('/[\x00-\x1F\x7F]/', $href)) {
            return false;
        }
        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $href, $m)) {
            return in_array(strtolower($m[1]), ['http', 'https', 'mailto', 'tel'], true);
        }
        // Relative or protocol-relative
        return true;
    }
}

if (!function_exists('anti_field_css')) {
    /**
     * Emit CSS for all registered named styles: .anti-style-<name> { ... }
     * Consumed by explorer/shared/css.php and styles/generate.php so classes
     * work identically in the explorer and in production output.
     *
     * @return string CSS block
     */
    function anti_field_css(): string
    {
        $registry = anti_field_registry();
        $css = "/* === Named Styles (fields/styles.json) === */\n";

        foreach ($registry['styles'] as $name => $style) {
            if (!preg_match('/^[a-z0-9-]+$/', $name) || empty($style['css']) || !is_array($style['css'])) {
                continue;
            }
            $declarations = [];
            foreach ($style['css'] as $property => $value) {
                if (preg_match('/^[a-z-]+$/', $property) && is_string($value) && strpos($value, ';') === false) {
                    $declarations[] = "    {$property}: {$value};";
                }
            }
            if ($declarations) {
                $css .= ".anti-style-{$name} {\n" . implode("\n", $declarations) . "\n}\n";
            }
        }

        return $css;
    }
}
