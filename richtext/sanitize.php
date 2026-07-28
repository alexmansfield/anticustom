<?php
/**
 * Anticustom Rich Text — server-side sanitizer & style registry
 *
 * Rich text props store constrained HTML fragments. The security boundary is
 * sanitize-on-output: anti_rich() (components/render.php) passes every value
 * through anti_rt_sanitize() with an allowlist derived from the field's
 * schema options, so tampered stored props and interpolated values are both
 * neutralized at render time.
 *
 * See docs/adr/0013-graduated-richtext-fields.md.
 */

if (!function_exists('anti_rt_registry')) {
    /**
     * Load the global named-style registry (richtext/styles.json).
     *
     * @return array { styles: { name: { label, css } } }
     */
    function anti_rt_registry(): array
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

if (!function_exists('anti_rt_features')) {
    /**
     * Resolve a field definition's richtext options into a full feature set.
     * Tiers are derived, never declared: no options = tier 1 (plain text),
     * marks = tier 2, styles = tier 3, blocks/links = tier 4.
     *
     * @param array|null $fieldDef Schema field definition (or null)
     * @return array { marks: string[], styles: string[], multiline: bool, blocks: string[], links: bool }
     */
    function anti_rt_features(?array $fieldDef): array
    {
        $options = $fieldDef['richtext'] ?? [];

        $styles = $options['styles'] ?? [];
        if ($styles === true) {
            $styles = array_keys(anti_rt_registry()['styles']);
        }

        return [
            'marks'     => array_values(array_intersect((array) ($options['marks'] ?? []), ['bold', 'italic'])),
            'styles'    => array_values(array_intersect((array) $styles, array_keys(anti_rt_registry()['styles']))),
            'multiline' => (bool) ($options['multiline'] ?? false),
            'blocks'    => array_values(array_intersect((array) ($options['blocks'] ?? []), ['ul', 'ol'])),
            'links'     => (bool) ($options['links'] ?? false),
        ];
    }
}

if (!function_exists('anti_rt_sanitize')) {
    /**
     * Sanitize a rich text HTML fragment against a resolved feature set.
     *
     * Allowed nodes derive from features: bold→strong (b normalized),
     * italic→em (i normalized), styles→span[class=anti-rt-*], multiline→br,
     * links→a[href], blocks→ul/ol/li. Dangerous elements are dropped with
     * their content; everything else is unwrapped so its text survives.
     *
     * @param string $html Stored fragment (may be hostile)
     * @param array $features Output of anti_rt_features()
     * @return string Sanitized fragment
     */
    function anti_rt_sanitize(string $html, array $features): string
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
            '<?xml encoding="utf-8"?><div id="anti-rt-root">' . $html . '</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('anti-rt-root');
        if (!$root) {
            return '';
        }

        anti_rt_sanitize_node($root, $allowed, $features);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }
}

if (!function_exists('anti_rt_sanitize_node')) {
    /**
     * Recursively sanitize the children of a node in place.
     * Internal helper for anti_rt_sanitize().
     *
     * @param DOMNode $node Parent whose children are walked
     * @param array $allowed tag => allowed attribute names
     * @param array $features Resolved feature set (for class/href filtering)
     * @return void
     */
    function anti_rt_sanitize_node(DOMNode $node, array $allowed, array $features): void
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
            anti_rt_sanitize_node($child, $allowed, $features);

            if (!isset($allowed[$tag])) {
                anti_rt_unwrap($child);
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
                    if (preg_match('/^anti-rt-([a-z0-9-]+)$/', $class, $m)
                        && in_array($m[1], $features['styles'], true)) {
                        $valid[] = $class;
                    }
                }
                if (empty($valid)) {
                    anti_rt_unwrap($child);
                    continue;
                }
                $child->setAttribute('class', implode(' ', $valid));
            }

            if ($tag === 'a') {
                $href = trim($child->getAttribute('href'));
                if (!anti_rt_safe_href($href)) {
                    anti_rt_unwrap($child);
                    continue;
                }
                $child->setAttribute('rel', 'noopener');
            }
        }
    }
}

if (!function_exists('anti_rt_unwrap')) {
    /**
     * Replace an element with its children (text survives, wrapper dies).
     *
     * @param DOMNode $element Element to unwrap
     * @return void
     */
    function anti_rt_unwrap(DOMNode $element): void
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

if (!function_exists('anti_rt_safe_href')) {
    /**
     * Allow http/https/mailto/tel and relative URLs; reject everything else
     * (javascript:, data:, vbscript:, protocol-relative is allowed as https).
     *
     * @param string $href Candidate href value
     * @return bool
     */
    function anti_rt_safe_href(string $href): bool
    {
        if ($href === '') {
            return false;
        }
        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $href, $m)) {
            return in_array(strtolower($m[1]), ['http', 'https', 'mailto', 'tel'], true);
        }
        // Relative or protocol-relative
        return true;
    }
}

if (!function_exists('anti_rt_css')) {
    /**
     * Emit CSS for all registered named styles: .anti-rt-<name> { ... }
     * Consumed by explorer/shared/css.php and styles/generate.php so classes
     * work identically in the explorer and in production output.
     *
     * @return string CSS block
     */
    function anti_rt_css(): string
    {
        $registry = anti_rt_registry();
        $css = "/* === Rich Text Styles (richtext/styles.json) === */\n";

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
                $css .= ".anti-rt-{$name} {\n" . implode("\n", $declarations) . "\n}\n";
            }
        }

        return $css;
    }
}
