<?php

namespace App\Support;

/**
 * Nettoyage des sections du page builder avant enregistrement.
 *
 * Le site public affiche plusieurs champs tels quels (texte riche, titres, styles en ligne, liens) :
 * sans ce filtre, un éditeur sans la permission « HTML libre » pourrait y glisser du script.
 * On ne retire que ce qui exécute du code (balises actives, attributs on*, liens javascript:,
 * sortie d'un attribut style) : la mise en page saisie dans l'éditeur est conservée.
 * Seul le widget « html », réservé à cms.html, est laissé intact.
 */
class CmsSectionsSanitizer
{
    private const RAW_HTML_WIDGET = 'html';

    private const ACTIVE_TAGS = [
        'script', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'base', 'meta', 'link',
        'style', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'template', 'noscript',
    ];

    private const URL_ATTRIBUTES = ['href', 'src', 'action', 'formaction', 'xlink:href', 'data', 'poster', 'background'];

    private const HEADING_TAGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span'];

    /** Champs de contenu insérés comme HTML par le site public. */
    private const HTML_KEYS = ['html'];

    /** Champs de contenu insérés dans un attribut style. */
    private const CSS_KEYS = ['direction', 'gap'];

    /**
     * @param  array<int, mixed>  $sections
     * @return array<int, mixed>
     */
    public static function sanitize(array $sections): array
    {
        foreach ($sections as $s => $section) {
            if (! is_array($section)) {
                continue;
            }

            $section['settings'] = self::styles($section['settings'] ?? null);

            foreach ($section['columns'] ?? [] as $c => $column) {
                if (! is_array($column)) {
                    continue;
                }

                $column['settings'] = self::styles($column['settings'] ?? null);

                foreach ($column['widgets'] ?? [] as $w => $widget) {
                    if (is_array($widget)) {
                        $column['widgets'][$w] = self::widget($widget);
                    }
                }

                $section['columns'][$c] = $column;
            }

            $sections[$s] = $section;
        }

        return $sections;
    }

    private static function widget(array $widget): array
    {
        if (($widget['type'] ?? null) === self::RAW_HTML_WIDGET) {
            return $widget;
        }

        $heading = ($widget['type'] ?? null) === 'elementor_heading';

        if (isset($widget['content']) && is_array($widget['content'])) {
            $widget['content'] = self::content($widget['content'], $heading);
        }

        foreach (['style', 'advanced'] as $key) {
            if (array_key_exists($key, $widget)) {
                $widget[$key] = self::styles($widget[$key]);
            }
        }

        return $widget;
    }

    private static function content(array $content, bool $heading): array
    {
        foreach ($content as $key => $value) {
            if (is_array($value)) {
                $content[$key] = self::content($value, false);

                continue;
            }

            if (! is_string($value) || ! is_string($key)) {
                continue;
            }

            $content[$key] = match (true) {
                in_array($key, self::HTML_KEYS, true), $heading && $key === 'title' => self::html($value),
                $heading && $key === 'tag' => in_array(strtolower($value), self::HEADING_TAGS, true) ? strtolower($value) : 'h2',
                in_array($key, self::CSS_KEYS, true) => self::css($value),
                self::isUrlKey($key) => self::url($value),
                default => $value,
            };
        }

        return $content;
    }

    private static function styles(mixed $styles): mixed
    {
        if (is_string($styles)) {
            return self::css($styles);
        }

        if (! is_array($styles)) {
            return $styles;
        }

        foreach ($styles as $key => $value) {
            $styles[$key] = self::styles($value);
        }

        return $styles;
    }

    private static function isUrlKey(string $key): bool
    {
        $key = strtolower($key);

        return in_array($key, ['url', 'href', 'src'], true)
            || str_ends_with($key, 'url') || str_ends_with($key, 'href') || str_ends_with($key, 'src');
    }

    /** Valeur CSS sans moyen de sortir de l'attribut style ni de charger quoi que ce soit. */
    public static function css(string $value): string
    {
        $value = str_replace(['"', '\'', '<', '>', ';', '{', '}', '\\'], '', $value);

        return preg_match('/expression\s*\(|url\s*\(|@import|javascript:/i', $value) ? '' : $value;
    }

    /** Liens et sources : pas de schéma exécutable (javascript:, data:, vbscript:…). */
    public static function url(string $value): string
    {
        $compact = strtolower(preg_replace('/[\x00-\x20]+/', '', $value) ?? '');

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/', $compact, $matches)
            && ! in_array($matches[1], ['http', 'https', 'mailto', 'tel'], true)) {
            return '#';
        }

        return $value;
    }

    public static function html(string $html): string
    {
        if (trim($html) === '' || ! preg_match('/[<&]/', $html)) {
            return $html;
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $container = $document->getElementsByTagName('div')->item(0);
        if (! $container) {
            return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
        }

        self::clean($container);

        $output = '';
        foreach ($container->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return $output;
    }

    private static function clean(\DOMElement $element): void
    {
        foreach (iterator_to_array($element->childNodes) as $child) {
            if (! $child instanceof \DOMElement) {
                if ($child instanceof \DOMComment) {
                    $element->removeChild($child);
                }

                continue;
            }

            if (in_array(strtolower($child->tagName), self::ACTIVE_TAGS, true)) {
                $element->removeChild($child);

                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $unsafe = str_starts_with($name, 'on')
                    || $name === 'srcdoc'
                    || (in_array($name, self::URL_ATTRIBUTES, true) && self::url($attribute->value) !== $attribute->value)
                    || ($name === 'style' && self::css($attribute->value) === '' && trim($attribute->value) !== '');

                if ($unsafe) {
                    $child->removeAttribute($attribute->name);
                }
            }

            self::clean($child);
        }
    }
}
