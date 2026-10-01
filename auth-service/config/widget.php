<?php

return [
    'token_ttl' => (int) env('WIDGET_TOKEN_TTL', 3600),
    'token_secret' => env('WIDGET_TOKEN_SECRET'),
    'internal_token' => env('INTERNAL_API_TOKEN', 'change-me-shared-internal-token'),
];
