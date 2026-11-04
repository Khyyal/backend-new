<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Services\Database\Factories\MembershipPackageFactory;

/**
 * A service package included in a membership, optionally bound to one trainer
 * of its training service.
 */
class MembershipPackage extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'membership_id',
        'service_package_id',
        'price_option_id',
        'trainer_id',
    ];

    protected static function newFactory(): MembershipPackageFactory
    {
        return MembershipPackageFactory::new();
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function servicePackage(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class)->withTrashed();
    }

    public function priceOption(): BelongsTo
    {
        return $this->belongsTo(PriceOption::class)->withTrashed();
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }
}
