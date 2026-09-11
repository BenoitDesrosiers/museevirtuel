<?php

/**
 * Messages de validation en français pour les règles liées aux mots de passe.
 *
 * Ce fichier ne couvre que les règles utilisées dans l'application.
 * Les autres règles retombent sur les messages anglais par défaut de Laravel.
 */
return [
    'required' => 'Le champ :attribute est obligatoire.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'string' => 'Le champ :attribute doit être une chaîne de caractères.',
    'max' => [
        'numeric' => 'Le champ :attribute ne peut pas être supérieur à :max.',
        'string' => 'Le champ :attribute ne peut pas contenir plus de :max caractères.',
    ],
    'min' => [
        'numeric' => 'Le champ :attribute doit être au moins :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'gte' => [
        'numeric' => 'Le champ :attribute doit être supérieur ou égal à :value.',
    ],
    'enum' => 'La valeur sélectionnée pour :attribute est invalide.',
    'digits' => 'Le champ :attribute doit contenir :digits chiffres.',
    'unique' => 'Cette valeur de :attribute est déjà utilisée.',

    'confirmed' => 'La confirmation du :attribute ne correspond pas.',
    'current_password' => 'Le mot de passe actuel est incorrect.',

    'password' => [
        'min' => 'Le :attribute doit contenir au moins :min caractères.',
        'numbers' => 'Le :attribute doit contenir au moins un chiffre.',
    ],

    'attributes' => [
        'nom_cours' => 'nom du cours',
        'code' => 'code de cours',
        'groupe' => 'groupe',
        'annee' => 'année',
        'session' => 'session',
        'type_cours' => 'niveau du cours',
        'taille_equipe_min' => 'taille d\'équipe minimale',
        'taille_equipe_max' => 'taille d\'équipe maximale',
        'description' => 'description',
        'password' => 'mot de passe',
        'current_password' => 'mot de passe actuel',
        'password_confirmation' => 'confirmation du mot de passe',
        'numero' => 'numéro de classe',
        'nom' => 'nom',
        'jour_semaine' => 'jour',
        'plage_horaire' => 'plage horaire',
    ],
];
