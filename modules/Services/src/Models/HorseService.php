<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Services\Concerns\HasService;

class HorseService extends Model
{
    use HasFactory, HasService, SoftDeletes;

    public $timestamps = false;
}
