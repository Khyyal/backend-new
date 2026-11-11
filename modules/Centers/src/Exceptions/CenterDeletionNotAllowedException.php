<?php

namespace Modules\Centers\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A center cannot be deleted yet. `reason` is a stable key, also used to look
 * up the translated message in `centers::deletion`.
 */
class CenterDeletionNotAllowedException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct(__('centers::deletion.'.$reason));
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'reason' => $this->reason,
            'errors' => ['center' => [$this->getMessage()]],
        ], 422);
    }

    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
