<?php

return [
    /*
    | Lifetime of an unclaimed temporary upload, in minutes.
    */
    'temporary_ttl_minutes' => (int) env('SUPPORT_MEDIA_TTL_MINUTES', 1440),

    /*
    | Maximum accepted upload size, in kilobytes.
    */
    'max_size_kb' => (int) env('SUPPORT_MEDIA_MAX_SIZE_KB', 10240),

    /*
    | Allowed MIME types (validated against the file content, not the extension).
    */
    'allowed_mimetypes' => [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ],

    /*
    | Auth guards allowed to use the media endpoints.
    */
    'guards' => ['client', 'center_user'],

    /*
    | Collection used internally to hold a media item while it is temporary.
    */
    'temporary_collection' => 'temporary',

    /*
    | Number of expired uploads loaded per cleanup chunk.
    */
    'cleanup_chunk_size' => 100,
];
