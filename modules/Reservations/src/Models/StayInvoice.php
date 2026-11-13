<?php

namespace Modules\Reservations\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Purchase\Contracts\Purchasable;
use Modules\Purchase\Traits\IsPurchasable;
use Modules\Reservations\Database\Factories\StayInvoiceFactory;
use Modules\Reservations\Enums\StayInvoiceKind;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\Service;

/**
 * An extra charge billed to the client during a stabling stay: a horse
 * service, a horse care or a standalone line. `service_id` is the billed
 * service, or the stay's stabling service for standalone invoices, so
 * purchase visibility by service type applies.
 */
class StayInvoice extends Model implements Purchasable
{
    use HasFactory, IsPurchasable, SoftDeletes;

    protected $fillable = [
        'reservation_id',
        'center_id',
        'client_id',
        'kind',
        'service_id',
        'price_option_id',
        'date',
        'name',
        'description',
        'price',
        'total_amount',
        'metadata',
        'created_by_type',
        'created_by_id',
    ];

    protected static function newFactory(): StayInvoiceFactory
    {
        return StayInvoiceFactory::new();
    }

    protected function casts(): array
    {
        return [
            'kind' => StayInvoiceKind::class,
            'date' => 'date:Y-m-d',
            'price' => 'integer',
            'total_amount' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function priceOption(): BelongsTo
    {
        return $this->belongsTo(PriceOption::class);
    }

    public function createdBy(): MorphTo
    {
        return $this->morphTo();
    }
}
