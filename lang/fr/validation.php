<?php

/**
 * Messages de validation en français pour les règles liées aux mots de passe.
 *
 * Ce fichier ne couvre que les règles utilisées dans l'application.
 * Les autres règles retombent sur les messages anglais par défaut de Laravel.
 */
return [
    'confirmed' => 'La confirmation du :attribute ne correspond pas.',
    'current_password' => 'Le mot de passe actuel est incorrect.',

    'password' => [
        'min' => 'Le :attribute doit contenir au moins :min caractères.',
        'numbers' => 'Le :attribute doit contenir au moins un chiffre.',
    ],

    'attributes' => [
        'password' => 'mot de passe',
        'current_password' => 'mot de passe actuel',
        'password_confirmation' => 'confirmation du mot de passe',
    ],
];
