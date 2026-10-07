<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Services\Concerns\HasService;
use Modules\Services\Enums\EventOccurrenceType;

class Event extends Model
{
    use HasFactory, HasService, SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'occurrence_type',
        'start_date',
        'end_date',
        'open_date',
        'close_date',
        'max_tickets_per_day',
    ];

    protected function casts(): array
    {
        return [
            'occurrence_type' => EventOccurrenceType::class,
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'open_date' => 'date:Y-m-d',
            'close_date' => 'date:Y-m-d',
            'max_tickets_per_day' => 'integer',
        ];
    }
}
