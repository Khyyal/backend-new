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
     * HTTP status of the rendered error; a few rules answer 409 instead of 422.
     */
    public int $status = 422;

    /**
     * @param  array<string, mixed>  $replace
     */
    public function __construct(public readonly string $reason, public readonly array $replace = [], ?Throwable $previous = null)
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
            ...(isset($this->replace['available']) ? ['available' => $this->replace['available']] : []),
            'errors' => ['reservation' => [$this->getMessage()]],
        ], $this->status);
    }

    public function withStatus(int $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    public static function because(string $reason, array $replace = [], ?Throwable $previous = null): self
    {
        return new self($reason, $replace, $previous);
    }
}
