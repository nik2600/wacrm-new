<?php

/**
 * Allow-list HTML sanitiser for user-authored rich text that is rendered
 * raw ({!! !!}) on public, same-origin surfaces (e.g. the storefront
 * product description / body_html).
 *
 * body_html is stored verbatim — the product controller validates it only
 * as `nullable|string|max:65535`, and the Shopify/Woo importers copy the
 * source HTML byte-for-byte — so a workspace admin (or a crafted import
 * feed) can plant `<script>` / `<img onerror=…>` that would execute in a
 * public customer's browser on a checkout/payment page. We keep the rich
 * text but strip anything script-bearing:
 *   - dangerous elements (script, iframe, object, embed, form, style, …)
 *   - every on* event-handler attribute
 *   - inline `style` attributes (CSS can smuggle expressions / url())
 *   - javascript:/vbscript:/data: URLs on href/src/etc.
 *
 * Pure DOM walk — no external package required. Self-contained so it can be
 * pulled in with a single require_once from a Blade view.
 */
if (! function_exists('wadesk_sanitize_html')) {
    function wadesk_sanitize_html(?string $html): string
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }

        // Elements that may never survive — they either execute code or can
        // be abused to load/execute remote content.
        static $blockedTags = [
            'script', 'iframe', 'object', 'embed', 'style', 'form', 'link',
            'meta', 'base', 'frame', 'frameset', 'applet', 'noscript',
            'template', 'svg', 'math', 'audio', 'video', 'source',
        ];
        // URL-bearing attributes we scrub for dangerous schemes.
        static $urlAttrs = ['href', 'src', 'xlink:href', 'action', 'formaction', 'background', 'poster', 'cite'];

        $dom = new \DOMDocument('1.0', 'UTF-8');

        // Wrap so we can parse a fragment; force UTF-8 and suppress the
        // libxml warnings that malformed user HTML always produces.
        $wrapped = '<?xml encoding="UTF-8"><body>' . $html . '</body>';
        $prev = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML($wrapped, LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (! $loaded) {
            // Unparseable — fail closed to plain text so nothing executes.
            return e($html);
        }

        $xpath = new \DOMXPath($dom);

        // 1) Drop blocked elements entirely (including their contents).
        foreach ($blockedTags as $tag) {
            foreach (iterator_to_array($xpath->query('//' . $tag)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        // 2) Scrub attributes on everything that remains.
        foreach (iterator_to_array($xpath->query('//*')) as $el) {
            if (! $el instanceof \DOMElement) {
                continue;
            }
            foreach (iterator_to_array($el->attributes) as $attr) {
                $name = strtolower($attr->nodeName);

                // Event handlers (onclick, onerror, onload, …) and inline CSS.
                if (str_starts_with($name, 'on') || $name === 'style') {
                    $el->removeAttribute($attr->nodeName);
                    continue;
                }

                // Neutralise script-bearing URL schemes.
                if (in_array($name, $urlAttrs, true)) {
                    $val = preg_replace('/[\s\x00-\x1f]+/', '', strtolower($attr->nodeValue ?? ''));
                    if (preg_match('/^(javascript|vbscript|data|about):/i', $val)) {
                        $el->removeAttribute($attr->nodeName);
                    }
                }
            }
        }

        // Serialise back the sanitised children of our wrapper <body>.
        $out = '';
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
            foreach ($body->childNodes as $child) {
                $out .= $dom->saveHTML($child);
            }
        }

        return $out;
    }
}
