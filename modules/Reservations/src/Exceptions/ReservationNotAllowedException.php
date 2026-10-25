<?php

namespace Modules\Reservations\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

/**
 * A booking request broke a reservation rule. `reason` is a stable key, also
 * used to look up the translated message in `reservations::booking`.
 */
class ReservationNotAllowedException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $replace
     */
    public function __construct(public readonly string $reason, array $replace = [], ?Throwable $previous = null)
    {
        parent::__construct(__('reservations::booking.'.$reason, $replace), previous: $previous);
    }

    /**
     * Rendered like a validation error, with the rule's `reason` key for clients
     * that branch on it.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'reason' => $this->reason,
            'errors' => ['reservation' => [$this->getMessage()]],
        ], 422);
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    public static function because(string $reason, array $replace = [], ?Throwable $previous = null): self
    {
        return new self($reason, $replace, $previous);
    }
}
