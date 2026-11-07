<?php

namespace Modules\Support\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Carbon;

/**
 * One client-supplied Idempotency-Key per actor. While the first request runs
 * the row is locked (locked_until); once it succeeds it stores the response,
 * which is replayed to every retry with the same key and request.
 *
 * @property int $id
 * @property string $actor_type
 * @property int $actor_id
 * @property string $key
 * @property string $route
 * @property string $fingerprint
 * @property int|null $status_code
 * @property string|null $response_body
 * @property Carbon|null $locked_until
 */
class IdempotencyKey extends Model
{
    use Prunable;

    protected $fillable = [
        'actor_type',
        'actor_id',
        'key',
        'route',
        'fingerprint',
        'status_code',
        'response_body',
        'locked_until',
    ];

    protected function casts(): array
    {
        return [
            'actor_id' => 'integer',
            'status_code' => 'integer',
            'locked_until' => 'datetime',
        ];
    }

    public function hasResponse(): bool
    {
        return $this->status_code !== null;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subHours((int) config('support.idempotency.ttl_hours')));
    }
}
