<?php

namespace Modules\Reservations\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Purchase\Contracts\Purchasable;
use Modules\Purchase\Traits\IsPurchasable;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Enums\ReservationSource;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\Service;
use Modules\Services\Models\Trainer;

/**
 * The smallest session a client gets from a center. Hour-based services carry a
 * start/end time; day-based ones only a date (a date range for resorts).
 */
class Reservation extends Model implements Purchasable
{
    use HasFactory, IsPurchasable, SoftDeletes;

    protected $fillable = [
        'center_id',
        'service_id',
        'client_id',
        'trainer_id',
        'price_option_id',
        'client_package_id',
        'source',
        'status',
        'date',
        'end_date',
        'start_time',
        'end_time',
        'quantity',
        'unit_price',
        'subtotal',
        'total_amount',
        'cancelled_at',
        'cancelled_by_type',
        'cancelled_by_id',
        'cancellation_reason',
        'notes',
        'metadata',
    ];

    protected static function newFactory(): ReservationFactory
    {
        return ReservationFactory::new();
    }

    protected function casts(): array
    {
        return [
            'source' => ReservationSource::class,
            'status' => ReservationStatus::class,
            'date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    public function priceOption(): BelongsTo
    {
        return $this->belongsTo(PriceOption::class);
    }

    public function clientPackage(): BelongsTo
    {
        return $this->belongsTo(ClientPackage::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ReservationLine::class);
    }

    public function cancelledBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOccupyingCapacity(Builder $query): Builder
    {
        return $query->whereIn('status', ReservationStatus::occupyingCapacity());
    }
}
