<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Services\Concerns\HasService;
use Modules\Services\Concerns\HasStartPrice;
use Modules\Services\Database\Factories\MembershipFactory;

/**
 * A bundle of service packages sold for one `price` (the service's single price
 * option), valid for `duration` months. It can also grant up to
 * `max_visits_per_day` free entries to one Visit service of the center.
 */
class Membership extends Model
{
    use HasFactory, HasService, HasStartPrice, SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'visit_service_id',
        'max_visits_per_day',
        'duration',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'max_visits_per_day' => 'integer',
            'duration' => 'integer',
        ];
    }

    protected static function newFactory(): MembershipFactory
    {
        return MembershipFactory::new();
    }

    public function packages(): HasMany
    {
        return $this->hasMany(MembershipPackage::class);
    }

    public function visitService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'visit_service_id');
    }
}
