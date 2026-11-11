<?php

namespace Modules\Reservations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Clients\Models\Client;
use Modules\Reservations\Enums\ClientMembershipLogType;

/**
 * One entry of a membership's log: a guest the member invited or a center check-in.
 * Entries are only ever appended.
 */
class ClientMembershipLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'client_membership_id',
        'type',
        'reservation_id',
        'guest_client_id',
        'actor_type',
        'actor_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => ClientMembershipLogType::class,
            'created_at' => 'datetime',
        ];
    }

    public function clientMembership(): BelongsTo
    {
        return $this->belongsTo(ClientMembership::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'guest_client_id');
    }

    /**
     * Who did it: the member, a center user or a system user.
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
