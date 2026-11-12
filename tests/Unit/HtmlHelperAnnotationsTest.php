<?php

use App\Helpers\HtmlHelper;

const HTML_ANNOTE = '<p>Le <mark data-comment-id="a1" data-annotation-type="commentaire">premier <strong>mot</strong></mark>'
    .' puis le <mark data-comment-id="b2" data-annotation-type="correction">second</mark>.</p>';

test('annotationIds retourne les identifiants dans l\'ordre d\'apparition', function () {
    expect(HtmlHelper::annotationIds(HTML_ANNOTE))->toBe(['a1', 'b2'])
        ->and(HtmlHelper::annotationIds('<p>Sans marque</p>'))->toBe([]);
});

test('positionAnnotation retourne le rang de la marque ou null', function () {
    expect(HtmlHelper::positionAnnotation(HTML_ANNOTE, 'a1'))->toBe(0)
        ->and(HtmlHelper::positionAnnotation(HTML_ANNOTE, 'b2'))->toBe(1)
        ->and(HtmlHelper::positionAnnotation(HTML_ANNOTE, 'absent'))->toBeNull();
});

test('motAnnote retourne le texte brut de la marque ou null', function () {
    expect(HtmlHelper::motAnnote(HTML_ANNOTE, 'a1'))->toBe('premier mot')
        ->and(HtmlHelper::motAnnote(HTML_ANNOTE, 'b2'))->toBe('second')
        ->and(HtmlHelper::motAnnote(HTML_ANNOTE, 'absent'))->toBeNull();
});
