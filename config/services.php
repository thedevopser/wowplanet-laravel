<?php

return [

    /*
     |--------------------------------------------------------------------------
     | Third Party Services
     |--------------------------------------------------------------------------
     |
     | This file is for storing the credentials for third party services such
     | as Mailgun, Postmark, AWS and more. This file provides a de facto
     | location for this type of information, allowing packages to have
     | a conventional file to locate the various service credentials.
     |
     */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_NOTIFICATIONS_CHANNEL'),
        ],
    ],

    'blizzard' => [
        'client_id' => env('BLIZZARD_CLIENT_ID'),
        'client_secret' => env('BLIZZARD_CLIENT_SECRET'),
        'region' => env('BLIZZARD_REGION', 'eu'),
        'redirect_uri' => env('BLIZZARD_REDIRECT_URI'),
        'admin_bnet_id' => env('ADMIN_BNET_ID'),

        // Réimport : plafond horaire réservé aux imports (< quota réel 36000, laisse la
        // marge au trafic site), durée max d'une passe de job, taille d'une tranche.
        'import_hourly_ceiling' => (int) env('BLIZZARD_IMPORT_HOURLY_CEILING', 30000),
        'import_chunk_timebox' => (int) env('BLIZZARD_IMPORT_CHUNK_TIMEBOX', 600),
        'appearance_slice' => (int) env('BLIZZARD_APPEARANCE_SLICE', 2000),
    ],

    'discord' => [
        'webhook_changelog' => env('DISCORD_WEBHOOK_URL_CHANGELOG'),
        'webhook_discussion' => env('DISCORD_WEBHOOK_URL_DISCUSSION'),
    ],

];
