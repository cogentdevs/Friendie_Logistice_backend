<?php

return [
    // Public application/media root, optionally different from the website APP_URL.
    // Null/blank preserves APP_URL fallback. Never infer this from request headers.
    'public_base_url' => env('API_PUBLIC_BASE_URL'),
];
