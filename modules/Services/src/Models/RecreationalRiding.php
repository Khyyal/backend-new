<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Services\Concerns\HasSchedules;
use Modules\Services\Concerns\HasService;

class RecreationalRiding extends Model
{
    use HasFactory, HasSchedules  , HasService , SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'max_tickets_per_hour',
    ];

    protected function casts(): array
    {
        return [
            'max_tickets_per_hour' => 'integer',
        ];
    }
}
