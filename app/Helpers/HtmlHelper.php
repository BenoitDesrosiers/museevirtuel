<?php

namespace App\Helpers;

class HtmlHelper
{
    /**
     * Retire les marques d'annotation TipTap (CommentMark) d'un HTML en gardant le texte brut.
     *
     * Les balises ciblées ont la forme :
     *   <mark data-comment-id="UUID" data-annotation-type="commentaire" ...>mot</mark>
     */
    public static function stripAnnotationMarks(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        // On retire uniquement les <mark> portant l'attribut data-comment-id (marques TipTap).
        // Les éventuels <mark> de surlignage génériques (Highlight) ne sont pas touchés.
        return preg_replace(
            '/<mark\b[^>]*\bdata-comment-id\b[^>]*>(.*?)<\/mark>/is',
            '$1',
            $html,
        ) ?? $html;
    }

    /**
     * Enveloppe chaque exposant de renvoi TipTap d'un lien ancré vers la section Références du PDF.
     *
     * Les exposants sont générés par TipTap sous la forme :
     *   <sup data-renvoi-id="X" data-renvoi-numero="N" class="renvoi ...">N</sup>
     *
     * Après traitement, chaque occurrence devient :
     *   <a href="#ref-N" id="appel-N-K" style="color:inherit;text-decoration:none;">
     *     <sup ...>N</sup>
     *   </a>
     *
     * où K est le rang d'occurrence du numéro N dans le texte (1, 2, 3…).
     * Cela permet aux références de lier vers la première occurrence (#appel-N-1).
     *
     * Retourne le HTML inchangé si aucun exposant de renvoi n'est présent.
     */
    public static function addRenvoisLinks(string $html): string
    {
        if ($html === '' || ! str_contains($html, 'data-renvoi-id')) {
            return $html;
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');

        // Le wrapper complet évite que DOMDocument corrige le HTML de façon inattendue.
        @$dom->loadHTML(
            '<html><head><meta charset="UTF-8"/></head><body>'.$html.'</body></html>',
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        /** @var array<int, int> Rang d'occurrence de chaque numéro de renvoi */
        $occurrences = [];

        // iterator_to_array : évite les problèmes de mutation de la DOMNodeList en cours de boucle.
        /** @var \DOMElement[] $sups */
        $sups = iterator_to_array($dom->getElementsByTagName('sup'), false);

        foreach ($sups as $sup) {
            if (! $sup->hasAttribute('data-renvoi-id')) {
                continue;
            }

            $numero = $sup->getAttribute('data-renvoi-numero');

            if ($numero === '') {
                continue;
            }

            $n = (int) $numero;
            $occurrences[$n] = ($occurrences[$n] ?? 0) + 1;

            $link = $dom->createElement('a');
            $link->setAttribute('href', '#ref-'.$n);
            $link->setAttribute('id', 'appel-'.$n.'-'.$occurrences[$n]);
            $link->setAttribute('style', 'color:inherit;text-decoration:none;');

            $sup->parentNode->insertBefore($link, $sup);
            $link->appendChild($sup);
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        $result = '';

        foreach ($body->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        return $result;
    }
}
