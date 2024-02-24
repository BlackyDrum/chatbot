<?php

return [
    'url' => env('API_URL'),

    'username' => env('API_USERNAME'),

    'password' => env('API_PASSWORD'),

    'scope' => env('API_SCOPE'),

    'grant_type' => env('API_GRANT_TYPE'),

    'client_id' => env('API_CLIENT_ID'),

    'client_secret' => env('API_CLIENT_SECRET'),

    'chroma_host' => env('CHROMA_HOST', 'http://localhost'),

    'chroma_port' => env('CHROMA_PORT', 8000),

    'chroma_database' => env('CHROMA_DATABASE', 'new_database'),

    'chroma_tenant' => env('CHROMA_TENANT', 'new_tenant'),

    'jina_api_key' => env('JINA_API_KEY'),

    'max_requests' => 100, // Maximum number of messages per day per user

    'remaining_requests_alert_levels' => [10, 25, 50], // Show info when the user has n messages left for the day

    'max_tokens' => 1000, // Maximum number of tokens in the generated response

    'max_message_length' => 2048,

    'temperature' => 0.5,
];
