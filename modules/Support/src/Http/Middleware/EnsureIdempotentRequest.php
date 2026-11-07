<?php

namespace Modules\Support\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Support\Models\IdempotencyKey;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Makes a write endpoint safe to retry. The client sends an Idempotency-Key
 * header (required); the first request with a key runs and, when it succeeds
 * (2xx), its response is stored and replayed to every retry with the same key
 * and the same request. A failed request releases its key so it can be retried.
 *
 * Keys are scoped to the authenticated actor, so this must run after auth.
 * A worker that dies after the action commits but before the response is
 * stored leaves the key unlocked once locked_until passes, and a retry then
 * runs the action again.
 */
class EnsureIdempotentRequest
{
    public const string HEADER = 'Idempotency-Key';

    public const string REPLAYED_HEADER = 'Idempotent-Replayed';

    private const int MAX_KEY_LENGTH = 100;

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header(self::HEADER));

        if ($key === '' || mb_strlen($key) > self::MAX_KEY_LENGTH) {
            return $this->error('idempotency_key_required', 400);
        }

        $actor = $request->user();

        if (! $actor instanceof Model) {
            throw new AuthenticationException;
        }

        $route = (string) ($request->route()?->getName() ?? $request->path());
        $fingerprint = $this->fingerprint($request, $route);

        $record = IdempotencyKey::query()->createOrFirst([
            'actor_type' => $actor->getMorphClass(),
            'actor_id' => $actor->getKey(),
            'key' => $key,
        ], [
            'route' => $route,
            'fingerprint' => $fingerprint,
            'locked_until' => $this->lockExpiry(),
        ]);

        if (! $record->wasRecentlyCreated) {
            if ($record->route !== $route || $record->fingerprint !== $fingerprint) {
                return $this->error('idempotency_key_reused', 422);
            }

            if ($record->hasResponse()) {
                return $this->replay($record);
            }

            if ($record->isLocked() || ! $this->takeOverExpiredLock($record)) {
                return $this->error('idempotency_request_in_progress', 409)
                    ->header('Retry-After', (string) config('support.idempotency.lock_seconds'));
            }
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $record->delete();

            throw $exception;
        }

        if ($response->isSuccessful()) {
            $record->forceFill([
                'status_code' => $response->getStatusCode(),
                'response_body' => (string) $response->getContent(),
                'locked_until' => null,
            ])->save();
        } else {
            $record->delete();
        }

        return $response;
    }

    /**
     * Claims a key whose request died without storing a response. Only one
     * concurrent retry wins the conditional update.
     */
    private function takeOverExpiredLock(IdempotencyKey $record): bool
    {
        return IdempotencyKey::query()
            ->whereKey($record->getKey())
            ->whereNull('status_code')
            ->where(fn ($query) => $query->whereNull('locked_until')->orWhere('locked_until', '<=', now()))
            ->update(['locked_until' => $this->lockExpiry()]) === 1;
    }

    private function replay(IdempotencyKey $record): Response
    {
        return response((string) $record->response_body, (int) $record->status_code)
            ->header('Content-Type', 'application/json')
            ->header(self::REPLAYED_HEADER, 'true');
    }

    private function fingerprint(Request $request, string $route): string
    {
        $payload = [
            'method' => $request->method(),
            'route' => $route,
            'parameters' => $request->route()?->originalParameters() ?? [],
            'body' => $this->canonical($request->input()),
        ];

        return hash('sha256', (string) json_encode($payload));
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function canonical(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data);
        }

        return array_map(fn (mixed $value): mixed => is_array($value) ? $this->canonical($value) : $value, $data);
    }

    private function lockExpiry(): Carbon
    {
        return now()->addSeconds((int) config('support.idempotency.lock_seconds'));
    }

    private function error(string $code, int $status): JsonResponse
    {
        return response()->json([
            'message' => __("support::idempotency.{$code}"),
            'code' => $code,
        ], $status);
    }
}
