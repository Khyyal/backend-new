<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Services\Concerns\HasSchedules;
use Modules\Services\Concerns\HasService;
use Modules\Services\Enums\VisitEnterType;

class Visit extends Model
{
    use HasFactory, HasSchedules, HasService, SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'enter_type',
    ];

    protected function casts(): array
    {
        return [
            'enter_type' => VisitEnterType::class,
        ];
    }
}
