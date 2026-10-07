<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Services\Concerns\HasService;

class HorseCare extends Model
{
    use HasFactory, HasService, SoftDeletes;

    public $timestamps = false;
}
