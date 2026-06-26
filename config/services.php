<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whisper' => [
        // Modèles : tiny, base, small, medium, large — trade-off vitesse/précision.
        // small est un bon équilibre pour du français sur CPU.
        'model' => env('WHISPER_MODEL', 'small'),
        // Chemin vers l'exécutable whisper — nécessaire si whisper.exe n'est pas dans le PATH.
        // Ex. Windows : C:\Users\Adam\AppData\Roaming\Python\Python313\Scripts\whisper.exe
        'binary' => env('WHISPER_BINARY', 'whisper'),
    ],

];
