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

    'received_payments_per_page' => 20,

    /*
    |--------------------------------------------------------------------------
    | Companion app devices
    |--------------------------------------------------------------------------
    |
    | offline_minutes: a device with no heartbeat for longer than this is
    | shown as disconnected in the panel. The throttles are "attempts,minutes"
    | pairs for the device API rate limiter.
    |
    */

    'device' => [
        'offline_minutes' => (int) env('PAYGUARD_DEVICE_OFFLINE_MINUTES', 15),
        'login_throttle' => env('PAYGUARD_DEVICE_LOGIN_THROTTLE', '10,1'),
        'api_throttle' => env('PAYGUARD_DEVICE_API_THROTTLE', '120,1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification reading
    |--------------------------------------------------------------------------
    |
    | packages maps each Android package the server accepts to its wallet.
    | Only Yape is enabled for now; Plin stays in the enum for later.
    | The yape patterns are the tolerant reading rules: ignore wins over
    | everything, then a received marker is required, then each field is
    | extracted with the first pattern that matches.
    |
    */

    'notifications' => [
        'packages' => [
            'com.bcp.innovacxion.yapeapp' => 'yape',
        ],

        'yape' => [
            'ignore_patterns' => [
                '/\byapeaste\b/iu',
                '/promoci[óo]n/iu',
                '/\bpromo\b/iu',
                '/descuento/iu',
                '/sorteo/iu',
                '/\bcompra\b/iu',
            ],
            'received_markers' => [
                '/te envi[óo] un pago/iu',
                '/recibiste/iu',
            ],
            'payment_pattern' => '/^Yape!\s*(?P<payer>.+?)\s*te envi[óo] un pago por\s*(?P<rest>.+)$/ius',
            'amount_patterns' => [
                '/S\/\.?\s*(?P<amount>[\d.,]+)/u',
                '/(?P<amount>[\d.,]+)\s*soles/iu',
            ],
            'security_code_patterns' => [
                '/(?:c[óo]digo|c[óo]d\.?)\s*(?:de seguridad)?\s*[:#-]?\s*(?<!\d)(?P<code>\d{3})(?!\d)/iu',
            ],
            'operation_patterns' => [
                '/operaci[óo]n\s*(?:n[°º.]*)?\s*[:#-]?\s*(?P<code>[A-Za-z0-9-]{4,40})/iu',
                '/(?:n[úu]mero|nro\.?)\s*de\s*operaci[óo]n\s*[:#-]?\s*(?P<code>[A-Za-z0-9-]{4,40})/iu',
            ],
        ],
    ],

];
