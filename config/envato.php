<?php

declare(strict_types=1);

return [
    'oauth' => [
        'client_id' => env('ENVATO_OAUTH_CLIENT_ID', ''),
        'client_secret' => env('ENVATO_OAUTH_CLIENT_SECRET', ''),
        'redirect_uri' => env('ENVATO_OAUTH_REDIRECT_URI', 'https://web.local.dev/oauth/callback'),
        // Required OAuth scopes (configure in your Envato OAuth app settings):
        // - View and search Envato sites
        // - View your Envato Account username
        // - View your email address
        // - View your purchases of the app creator's items
    ],
    // Personal token for verifying purchase codes (server-side)
    // Required permission: "View your items' sales history" (scope: sale:history)
    'personal_token' => env('ENVATO_PERSONAL_TOKEN', ''),

    // Mapping of Envato item IDs to their verification type
    // 'machine_id' = bound to machine ID (for apps/tools)
    // 'ip_address' = bound to IP address (for server-side PHP scripts)
    'items' => [
        // Example: '12345678' => 'machine_id',
        // Example: '87654321' => 'ip_address',
    ],
];
