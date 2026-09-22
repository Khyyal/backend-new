<?php

namespace Modules\Centers\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Centers\Enums\CenterStatus;
use Modules\Support\Concerns\Actionable;
use Modules\Support\Concerns\HasCity;

class Center extends Model
{
    use HasFactory;
    use HasCity;
    use Actionable;

    protected $fillable = [
        'city_id',
        'name',
        'slug',
        'description',
        'lat',
        'lng',
        'address',
        'points',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'points' => 'integer',
            'status' => CenterStatus::class,
        ];
    }



    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tag_center');
    }
}
