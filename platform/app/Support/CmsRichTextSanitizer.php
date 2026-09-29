<?php

namespace App\Support;

class CmsRichTextSanitizer
{
    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'a', 'span', 'font'];

    public static function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $html = strip_tags($html, self::ALLOWED_TAGS);

        $document = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();

        $container = $document->getElementsByTagName('div')->item(0);

        if (! $container) {
            return trim($html);
        }

        self::sanitizeNode($container);

        $output = '';

        foreach ($container->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private static function sanitizeNode(\DOMNode $node): void
    {
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return;
        }

        /** @var \DOMElement $element */
        $element = $node;

        if ($element->tagName === 'a') {
            $href = trim($element->getAttribute('href'));

            if ($href === '' || ! self::isSafeHref($href)) {
                while ($element->firstChild) {
                    $element->parentNode?->insertBefore($element->firstChild, $element);
                }
                $element->parentNode?->removeChild($element);

                return;
            }

            $element->setAttribute('href', $href);
            $element->setAttribute('rel', 'noopener noreferrer');

            foreach (iterator_to_array($element->attributes) as $attribute) {
                if (! in_array($attribute->name, ['href', 'rel'], true)) {
                    $element->removeAttribute($attribute->name);
                }
            }
        } elseif (in_array($element->tagName, ['span', 'font'], true)) {
            $style = $element->getAttribute('style');
            $size = $element->getAttribute('size');

            foreach (iterator_to_array($element->attributes) as $attribute) {
                $element->removeAttribute($attribute->name);
            }

            if ($style !== '' && preg_match('/font-size\s*:\s*(\d{1,2})px/i', $style, $matches)) {
                $px = max(11, min(20, (int) $matches[1]));
                $element->setAttribute('style', "font-size: {$px}px");
            } elseif ($size !== '') {
                $px = max(11, min(20, 11 + ((int) $size - 1) * 2));
                $element->setAttribute('style', "font-size: {$px}px");
            }
        } else {
            foreach (iterator_to_array($element->attributes) as $attribute) {
                $element->removeAttribute($attribute->name);
            }
        }

        $children = [];

        foreach ($element->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            self::sanitizeNode($child);
        }
    }

    private static function isSafeHref(string $href): bool
    {
        if (preg_match('/^(https?:\/\/|\/|#|[a-z0-9][a-z0-9._\-\/]*(\.html)?(#.*)?)$/i', $href)) {
            return ! str_starts_with(strtolower($href), 'javascript:');
        }

        return false;
    }
}
