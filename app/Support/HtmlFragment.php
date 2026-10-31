<?php

namespace App\Support;

/**
 * Fragment HTML chargé dans un DOM, modifié, puis resérialisé : base commune des filtres
 * de contenu (articles, page builder).
 */
class HtmlFragment
{
    /**
     * @param  callable(\DOMElement): void  $clean  reçoit le conteneur du fragment
     * @return string|null null si le fragment n'a pas pu être lu
     */
    public static function transform(string $html, callable $clean): ?string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $container = $document->getElementsByTagName('div')->item(0);
        if (! $container) {
            return null;
        }

        $clean($container);

        $output = '';
        foreach ($container->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return $output;
    }
}
