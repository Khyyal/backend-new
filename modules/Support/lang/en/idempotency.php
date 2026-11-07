<?php

return [
    'idempotency_key_required' => 'Send an Idempotency-Key header (up to 100 characters) with this request.',
    'idempotency_key_reused' => 'This Idempotency-Key was already used for a different request.',
    'idempotency_request_in_progress' => 'A request with this Idempotency-Key is still being processed. Retry shortly.',
];
