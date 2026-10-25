<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Services\Concerns\HasSchedules;
use Modules\Services\Concerns\HasService;
use Modules\Services\Concerns\HasSettings;
use Modules\Services\Concerns\HasStartPrice;
use Modules\Services\Enums\VisitEnterType;

class Visit extends Model
{
    use HasFactory, HasSchedules, HasService, HasSettings, HasStartPrice, SoftDeletes;

    public $timestamps = false;

    const array SETTINGS_KEYS = [
        'enable_cash_on_arrival',
    ];

    protected $fillable = [
        'enter_type',
        'max_tickets_per_day',
    ];

    protected function casts(): array
    {
        return [
            'enter_type' => VisitEnterType::class,
            'max_tickets_per_day' => 'integer',
        ];
    }
}
