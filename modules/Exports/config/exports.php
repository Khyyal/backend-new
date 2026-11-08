<?php

return [

    /*
     * Disk the finished files are written to. Every queue worker and web
     * server must see it (use s3 when workers run on several machines).
     */
    'disk' => env('EXPORTS_DISK', 'local'),

    /*
     * Queue the export chunk jobs run on.
     */
    'queue' => env('EXPORTS_QUEUE', 'default'),

    /*
     * Days a finished export can be downloaded before it is pruned.
     */
    'retention_days' => (int) env('EXPORTS_RETENTION_DAYS', 7),

    /*
     * Minutes after which a still-pending export no longer blocks a new
     * export of the same type (e.g. its worker died).
     */
    'stale_after_minutes' => (int) env('EXPORTS_STALE_AFTER_MINUTES', 60),

];
