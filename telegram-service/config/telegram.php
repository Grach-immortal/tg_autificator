<?php

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'chat_id' => env('TELEGRAM_CHAT_ID'),
    'api_url' => env('TELEGRAM_API_URL', 'https://api.telegram.org'),
    'auth_service_url' => env('AUTH_SERVICE_URL', 'http://auth-nginx'),
    'auth_service_token' => env('AUTH_SERVICE_TOKEN', 'change-me-shared-internal-token'),
];
