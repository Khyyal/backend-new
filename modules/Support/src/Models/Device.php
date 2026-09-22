<?php

namespace Modules\Support\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;



    protected $fillable = ['device_identifier', 'fcm_token', 'deviceable_id', "deviceable_type"];






    // relation
    public function deviceable()
    {
        return $this->morphTo();
    }
}
