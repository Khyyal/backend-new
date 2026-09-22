<?php

namespace Modules\Support\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

#[Translatable('name')]
class City extends Model
{
    use HasFactory;
    use HasTranslations;





    protected $fillable = ['name', 'lat', 'lng', "radius"];










}

