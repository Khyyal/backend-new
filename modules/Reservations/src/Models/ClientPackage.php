<?php

namespace Modules\Reservations\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Clients\Models\Client;
use Modules\Purchase\Contracts\Purchasable;
use Modules\Purchase\Traits\IsPurchasable;
use Modules\Reservations\Database\Factories\ClientPackageFactory;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServicePackage;

/**
 * A service package bought by a client: a stock of session credits, each one
 * spent on a single-person reservation before the package expires.
 */
class ClientPackage extends Model implements Purchasable
{
    use HasFactory, IsPurchasable;

    protected $fillable = [
        'client_id',
        'service_id',
        'service_package_id',
        'price_option_id',
        'total_sessions',
        'remaining_sessions',
        'price',
        'status',
        'expires_at',
    ];

    protected static function newFactory(): ClientPackageFactory
    {
        return ClientPackageFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => ClientPackageStatus::class,
            'total_sessions' => 'integer',
            'remaining_sessions' => 'integer',
            'price' => 'decimal:2',
            'expires_at' => 'datetime',
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

    public function servicePackage(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class);
    }

    public function priceOption(): BelongsTo
    {
        return $this->belongsTo(PriceOption::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function isUsable(): bool
    {
        return $this->status === ClientPackageStatus::Active
            && $this->remaining_sessions > 0
            && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && ! $this->expires_at->isFuture();
    }
}
