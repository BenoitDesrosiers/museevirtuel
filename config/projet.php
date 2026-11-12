<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nombre de mots minimum avant la remise d'un projet
    |--------------------------------------------------------------------------
    |
    | Utilisés par App\Services\VerificationCompletudeProjet. L'éditeur Vue
    | (Projets/Show) applique les mêmes seuils : les garder synchronisés.
    |
    */

    'mots_minimum' => [
        'conclusion' => 20,
        'introduction' => 20,
        'developpement_titre' => 3,
        'developpement_contenu' => 50,
    ],

];
