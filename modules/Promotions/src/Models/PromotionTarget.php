<?php

namespace Modules\Promotions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Promotions\Data\DiscountableLine;
use Modules\Promotions\Enums\TargetMode;
use Modules\Promotions\Enums\TargetType;

class PromotionTarget extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'promotion_id',
        'target_type',
        'target_id',
        'mode',
    ];

    protected function casts(): array
    {
        return [
            'target_type' => TargetType::class,
            'mode' => TargetMode::class,
            'target_id' => 'integer',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function matches(DiscountableLine $line): bool
    {
        return match ($this->target_type) {
            TargetType::Center => $line->centerId === $this->target_id,
            TargetType::Service => $line->serviceId === $this->target_id,
        };
    }
}
