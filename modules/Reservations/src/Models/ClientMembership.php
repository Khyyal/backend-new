<?php

namespace Modules\Reservations\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Clients\Models\Client;
use Modules\Purchase\Contracts\Purchasable;
use Modules\Purchase\Traits\IsPurchasable;
use Modules\Reservations\Database\Factories\ClientMembershipFactory;
use Modules\Reservations\Enums\ClientMembershipStatus;
use Modules\Services\Models\Membership;
use Modules\Services\Models\Service;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A membership bought by a client: one client package per included service
 * package, plus up to `max_visits_per_day` free entries to the visit service
 * until the membership expires. Visit terms are copied at purchase so later
 * edits of the membership do not change what was sold.
 */
class ClientMembership extends Model implements Purchasable
{
    use HasFactory, IsPurchasable, LogsActivity;

    protected $fillable = [
        'client_id',
        'service_id',
        'membership_id',
        'price',
        'status',
        'visit_service_id',
        'max_visits_per_day',
        'duration',
        'expires_at',
        'expiry_reminder_sent_at',
    ];

    protected static function newFactory(): ClientMembershipFactory
    {
        return ClientMembershipFactory::new();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(['expiry_reminder_sent_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('client_membership');
    }

    protected function casts(): array
    {
        return [
            'status' => ClientMembershipStatus::class,
            'price' => 'integer',
            'max_visits_per_day' => 'integer',
            'duration' => 'integer',
            'expires_at' => 'datetime',
            'expiry_reminder_sent_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class)->withTrashed();
    }

    public function visitService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'visit_service_id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(ClientPackage::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ClientMembershipLog::class);
    }

    public function isUsable(): bool
    {
        return $this->status === ClientMembershipStatus::Active && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && ! $this->expires_at->isFuture();
    }

    public function grantsVisits(): bool
    {
        return $this->visit_service_id !== null && $this->max_visits_per_day !== null;
    }
}
