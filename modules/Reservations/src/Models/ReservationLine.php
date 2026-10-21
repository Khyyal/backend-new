<?php

namespace Modules\Reservations\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Reservations\Database\Factories\ReservationLineFactory;
use Modules\Reservations\Enums\ReservationLineKind;
use Modules\Services\Models\PriceOption;

/**
 * A priced line of a reservation: the single ticket option of an event, or a
 * resort addon.
 */
class ReservationLine extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'reservation_id',
        'price_option_id',
        'kind',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    protected static function newFactory(): ReservationLineFactory
    {
        return ReservationLineFactory::new();
    }

    protected function casts(): array
    {
        return [
            'kind' => ReservationLineKind::class,
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function priceOption(): BelongsTo
    {
        return $this->belongsTo(PriceOption::class);
    }
}
