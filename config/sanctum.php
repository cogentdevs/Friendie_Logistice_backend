<?php

return [
    // Mobile APIs use personal access tokens, never website session fallback.
    'guard' => [],
    'stateful' => [],
    'routes' => false,

    // Minutes; token issuance and its final policy belong to the auth phase.
    'expiration' => (int) env('SANCTUM_EXPIRATION', 43200),
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
];
