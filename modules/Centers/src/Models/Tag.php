<?php

namespace Modules\Centers\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

#[Translatable('name')]
class Tag extends Model
{
    use HasFactory;
    use HasTranslations;

    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public function centers(): BelongsToMany
    {
        return $this->belongsToMany(Center::class, 'tag_center');
    }
}
