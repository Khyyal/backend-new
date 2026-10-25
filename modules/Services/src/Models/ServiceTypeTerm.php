<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Centers\Models\Center;
use Modules\Services\Enums\ServiceType;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

#[Translatable('terms')]
class ServiceTypeTerm extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'center_id',
        'type',
        'terms',
    ];

    protected function casts(): array
    {
        return [
            'type' => ServiceType::class,
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }
}
