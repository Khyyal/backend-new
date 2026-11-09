<?php

namespace Modules\Support\Documentation;

/**
 * Shared error response bodies for #[Response] attributes, matching what the exception handler and middleware
 * actually return. Endpoint-specific bodies (e.g. a 422 with that request's field errors) are written inline.
 */
final class ApiExamples
{
    public const UNAUTHENTICATED = ['message' => 'Unauthenticated.'];

    public const FORBIDDEN = ['message' => 'This action is unauthorized.'];

    public const NOT_FOUND = ['message' => 'No query results for model.'];

    public const VALIDATION = [
        'message' => 'The field is required.',
        'errors' => ['field' => ['The field is required.']],
    ];

    public const THROTTLED = ['message' => 'Too Many Attempts.'];

    public const CENTER_SCOPE_REQUIRED = ['message' => 'This action requires a center-scoped access credential. Please obtain one via POST /centers/{center}/access.'];

    public const CENTER_NOT_ACTIVE = ['message' => 'This center is pending review and cannot use this feature.'];

    public const IDEMPOTENCY_KEY_REQUIRED = [
        'message' => 'Send an Idempotency-Key header (up to 100 characters) with this request.',
        'code' => 'idempotency_key_required',
    ];

    public const IDEMPOTENCY_KEY_REUSED = [
        'message' => 'This Idempotency-Key was already used for a different request.',
        'code' => 'idempotency_key_reused',
    ];

    public const IDEMPOTENCY_IN_PROGRESS = [
        'message' => 'A request with this Idempotency-Key is still being processed. Retry shortly.',
        'code' => 'idempotency_request_in_progress',
    ];

    public const INVALID_TEMPORARY_MEDIA = ['message' => 'The given media is invalid, expired or already used.'];
}
