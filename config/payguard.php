<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Business defaults
    |--------------------------------------------------------------------------
    |
    | Copied to each business when it is created. The owner can change them
    | later from the panel settings screen.
    |
    */

    'defaults' => [
        'match_window_minutes' => (int) env('PAYGUARD_MATCH_WINDOW_MINUTES', 10),
        'recheck_minutes' => (int) env('PAYGUARD_RECHECK_MINUTES', 2),
        'timezone' => env('PAYGUARD_TIMEZONE', 'America/Lima'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta Model API
    |--------------------------------------------------------------------------
    */

    'meta' => [
        'base_url' => env('META_MODEL_BASE_URL', 'https://api.meta.ai/v1'),
        'api_key' => env('META_MODEL_API_KEY'),
        'vision_model' => env('META_MODEL_VISION', 'muse-spark-1.3'),
        'transcribe_model' => env('META_MODEL_TRANSCRIBE', 'muse-voice-transcribe-1.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API
    |--------------------------------------------------------------------------
    */

    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'alert_template' => env('WHATSAPP_ALERT_TEMPLATE'),
    ],

    'cashiers_per_page' => 20,

];
