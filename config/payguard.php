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
        'vision_timeout' => (int) env('META_MODEL_VISION_TIMEOUT', 60),
        'vision_max_tokens' => (int) env('META_MODEL_VISION_MAX_TOKENS', 1000),
        'transcribe_timeout' => (int) env('META_MODEL_TRANSCRIBE_TIMEOUT', 30),
        'query_max_tokens' => (int) env('META_MODEL_QUERY_MAX_TOKENS', 300),
        'asr_mode' => env('META_MODEL_ASR_MODE', 'PUSH_TO_TALK'),
        'asr_language_bias' => ['Spanish'],
        'asr_keywords' => ['Yape', 'Plin', 'soles', 'luca', 'lucas'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Text and audio payment queries
    |--------------------------------------------------------------------------
    |
    | window_minutes: how far back to look for received payments when the
    | cashier asks without a screenshot. max_listed_matches: how many
    | candidates are named in the "several payments" reply. The prefilter
    | avoids the model call for plain greetings: if no pattern matches,
    | the cashier gets the help text without any AI call.
    |
    */

    'query' => [
        'window_minutes' => (int) env('PAYGUARD_QUERY_WINDOW_MINUTES', 10),
        'max_listed_matches' => 3,
        'prefilter_patterns' => [
            '/\d/u',
            '/\b(yape|plin|soles?|lucas?|lleg[óo]|pagos?|pagaron|yapearon|montos?)\b/iu',
            '/(recib|conf[ií]rm)/iu',
            '/\b(uno|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|once|doce|trece|catorce|quince|diecis[ée]is|diecisiete|dieciocho|diecinueve|veinte|treinta|cuarenta|cincuenta|sesenta|setenta|ochenta|noventa|cien|ciento|mil|medio|pico)\b/iu',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Local tooling
    |--------------------------------------------------------------------------
    |
    | ffmpeg converts WhatsApp voice notes (ogg/opus) to the mono WAV the
    | transcription endpoint requires. It must be installed on the server.
    |
    */

    'support' => [
        'ffmpeg_path' => env('PAYGUARD_FFMPEG_PATH', 'ffmpeg'),
        'ffmpeg_timeout' => (int) env('PAYGUARD_FFMPEG_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API
    |--------------------------------------------------------------------------
    */

    'whatsapp' => [
        'api_version' => env('WHATSAPP_API_VERSION', 'v26.0'),
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'alert_template' => env('WHATSAPP_ALERT_TEMPLATE', 'alerta_pago_sospechoso'),
        'alert_template_lang' => env('WHATSAPP_ALERT_TEMPLATE_LANG', 'es'),
        'media_disk' => env('WHATSAPP_MEDIA_DISK', 'local'),
        'media_max_mb' => (int) env('WHATSAPP_MEDIA_MAX_MB', 16),
        'media_allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'audio/ogg',
            'audio/mpeg',
            'audio/mp4',
            'audio/aac',
        ],
        'unknown_dedup_hours' => (int) env('WHATSAPP_UNKNOWN_DEDUP_HOURS', 24),
        'connect_timeout' => (int) env('WHATSAPP_CONNECT_TIMEOUT', 15),
        'timeout' => (int) env('WHATSAPP_TIMEOUT', 30),
        'media_timeout' => (int) env('WHATSAPP_MEDIA_TIMEOUT', 60),
        'lock_seconds' => (int) env('WHATSAPP_LOCK_SECONDS', 180),
        'lock_release_seconds' => (int) env('WHATSAPP_LOCK_RELEASE_SECONDS', 5),

        'replies' => [
            'unauthorized' => 'Este número no está autorizado en PayGuard. Pide al dueño que te registre.',
            'unsupported' => 'Envíame la captura del pago (imagen), un audio o un mensaje con el monto.',
            'error' => 'No pude revisar este pago. Inténtalo de nuevo en un momento.',
            'text_help' => 'Hola, soy PayGuard. Mándame la captura del Yape del cliente o pregúntame en un audio, por ejemplo "¿llegó un Yape de 50 soles de Juan?", y te confirmo si llegó.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Owner registration protection
    |--------------------------------------------------------------------------
    |
    | throttle is an "attempts,minutes" pair for the registration rate
    | limiter. The Turnstile keys default to Cloudflare's official test
    | keys; production uses the real keys from the server .env.
    |
    */

    'registration' => [
        'throttle' => env('PAYGUARD_REGISTER_THROTTLE', '5,60'),
        'honeypot_field' => 'website',
        'turnstile_site_key' => env('TURNSTILE_SITE_KEY', '1x00000000000000000000AA'),
        'turnstile_secret_key' => env('TURNSTILE_SECRET_KEY', '1x0000000000000000000000000000000AA'),
    ],

    'cashiers_per_page' => 20,

    'received_payments_per_page' => 20,

    'verifications_per_page' => 20,

    'owner_alerts_per_page' => 20,

    'devices_per_page' => 20,

    'exports' => [
        'max_rows' => (int) env('PAYGUARD_EXPORT_MAX_ROWS', 2000),
        'max_range_days' => (int) env('PAYGUARD_EXPORT_MAX_RANGE_DAYS', 31),
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification engine
    |--------------------------------------------------------------------------
    |
    | recent_minutes: a screenshot older than this goes straight to not_found
    | instead of pending. recheck_interval_seconds: delay between automatic
    | rechecks while pending. processing_timeout_minutes: a verification stuck
    | in processing for longer than this is closed by expire-stuck.
    | notify_hours: expire-stuck stays silent for older messages.
    | reasons holds the Spanish templates sent to the cashier; the same text
    | is stored in verifications.reason.
    |
    */

    'verification' => [
        'recent_minutes' => (int) env('PAYGUARD_RECENT_MINUTES', 15),
        'recheck_interval_seconds' => (int) env('PAYGUARD_RECHECK_INTERVAL_SECONDS', 20),
        'processing_timeout_minutes' => (int) env('PAYGUARD_PROCESSING_TIMEOUT_MINUTES', 30),
        'notify_hours' => (int) env('PAYGUARD_EXPIRE_NOTIFY_HOURS', 24),
        'wallet_labels' => [
            'yape' => 'Yape',
            'plin' => 'Plin',
        ],
        'status_emoji' => [
            'verified' => '✅',
            'pending' => '⏳',
            'reviewing' => '⏳',
            'not_found' => '❌',
            'mismatch' => '❌',
            'reused' => '❌',
            'offline' => '⚠',
            'multiple' => '⚠',
            'already_confirmed' => '⚠',
            'error' => '❌',
        ],
        'reasons' => [
            'verified' => ':wallet recibido: :amount de :payer',
            'verified_code_suffix' => ' · Cód. :code',
            'pending' => 'Todavía no llega el pago de :amount. Sigo revisando y te aviso.',
            'already_reviewing' => 'Ya estoy revisando esta captura.',
            'reused' => 'Esta captura ya se usó antes.',
            'not_found' => 'No llegó ningún pago de :amount.',
            'stale' => 'No encontré ningún :wallet de :amount cerca de las :time',
            'offline' => 'El celular del negocio no está conectado. No puedo confirmar este pago.',
            'unreadable' => 'No pude leer los datos de la captura.',
            'mismatch_recipient' => 'El pago es para otro titular, no para este negocio.',
            'mismatch_code' => 'El código de seguridad no coincide.',
            'mismatch_amount' => 'Llegó un pago parecido de :amount, no de :expected.',
            'expired' => 'La revisión tardó demasiado. Vuelve a enviar la captura.',
            'expired_query' => 'La revisión tardó demasiado. Vuelve a preguntar.',
            'multiple_matches' => 'Hay :count pagos por :wallet de :amount recientes: :items. Mándame la captura para saber cuál es.',
            'already_confirmed' => 'Ese :wallet ya fue confirmado a las :confirmed — mándame la captura para revisarlo.',
            'edit_suffix' => ' Además, la imagen muestra señales de edición.',
            'holder_skipped_suffix' => ' (destinatario no verificado)',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment simulator
    |--------------------------------------------------------------------------
    |
    | The payguard:simulate-payment command refuses to run in production
    | unless this flag is enabled on purpose.
    |
    */

    'simulator' => [
        'enabled' => (bool) env('PAYGUARD_SIMULATOR_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo account for judges
    |--------------------------------------------------------------------------
    |
    | The payguard:demo-seed and payguard:demo-reset commands refuse to run in
    | production unless enabled is true on purpose. The owner credentials
    | always come from the environment, never from the code. blocked_message
    | is the Spanish text shown when a demo user tries a disabled action.
    |
    */

    'demo' => [
        'enabled' => (bool) env('PAYGUARD_DEMO_ENABLED', false),
        'email' => env('PAYGUARD_DEMO_EMAIL'),
        'password' => env('PAYGUARD_DEMO_PASSWORD'),
        'business_name' => env('PAYGUARD_DEMO_BUSINESS_NAME', 'Bodega El Sol'),
        'blocked_message' => 'Cuenta de demostración: esta acción está desactivada.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Companion app devices
    |--------------------------------------------------------------------------
    |
    | offline_minutes: a device with no heartbeat for longer than this is
    | shown as disconnected in the panel. The throttles are "attempts,minutes"
    | pairs for the device API rate limiter. payment_duplicate_minutes: a
    | payment whose normalized text matches another one from the same
    | business and package within this window is rejected as a duplicate,
    | even when the idempotency key differs (old app versions).
    |
    */

    'device' => [
        'offline_minutes' => (int) env('PAYGUARD_DEVICE_OFFLINE_MINUTES', 10),
        'offline_alert_cooldown_minutes' => (int) env('PAYGUARD_OFFLINE_ALERT_COOLDOWN_MINUTES', 30),
        'login_throttle' => env('PAYGUARD_DEVICE_LOGIN_THROTTLE', '10,1'),
        'api_throttle' => env('PAYGUARD_DEVICE_API_THROTTLE', '120,1'),
        'payment_duplicate_minutes' => (int) env('PAYGUARD_PAYMENT_DUPLICATE_MINUTES', 10),
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
                '/\bganaste\b/iu',
                '/\bgana\b/iu',
                '/premio/iu',
                '/cashback/iu',
                '/oferta/iu',
                '/beneficio/iu',
                '/participa/iu',
            ],
            'received_markers' => [
                '/te envi[óo] un pago/iu',
                '/recibiste/iu',
                '/confirmaci[óo]n de pago/iu',
            ],
            'payment_patterns' => [
                '/(?:^|\n)\s*(?:Yape!\s*)?(?P<payer>[^\n]+?)\s+te envi[óo] un pago por\s*(?P<rest>.+)$/ius',
                '/^Yape!\s*(?P<payer>.+?)\s*te envi[óo] un pago por\s*(?P<rest>.+)$/ius',
            ],
            'amount_patterns' => [
                '/S\/\.?\s*(?P<amount>\d(?:[\d.,]*\d)?)/u',
                '/S\/\.?\s*(?P<amount>[\d.,]+)/u',
                '/(?P<amount>[\d.,]+)\s*soles/iu',
            ],
            'security_code_patterns' => [
                '/(?:c[óo]digo|c[óo]d\.?)\s*(?:de seguridad)?\s*(?:es\s*)?[:#-]?\s*(?<!\d)(?P<code>\d{3})(?!\d)/iu',
                '/(?:c[óo]digo|c[óo]d\.?)\s*(?:de seguridad)?\s*[:#-]?\s*(?<!\d)(?P<code>\d{3})(?!\d)/iu',
            ],
            'operation_patterns' => [
                '/operaci[óo]n\s*(?:n[°º.]*)?\s*[:#-]?\s*(?P<code>[A-Za-z0-9-]{4,40})/iu',
                '/(?:n[úu]mero|nro\.?)\s*de\s*operaci[óo]n\s*[:#-]?\s*(?P<code>[A-Za-z0-9-]{4,40})/iu',
            ],
        ],
    ],

];
