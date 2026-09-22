<?php

namespace Modules\Centers\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
}
