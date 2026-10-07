<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Services\Concerns\HasSchedules;
use Modules\Services\Concerns\HasService;

class RecreationalRiding extends Model
{

    use HasFactory, SoftDeletes  , HasSchedules , HasService;


    public $timestamps = false;


}
