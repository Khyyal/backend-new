<?php

namespace Modules\Homepage\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Homepage\Enums\ItemableType;

/**
 * A record an admin picked into a curated section, with its own optional display window.
 */
class HomeSectionItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'home_section_id',
        'itemable_type',
        'itemable_id',
        'sort_order',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'itemable_type' => ItemableType::class,
            'itemable_id' => 'integer',
            'sort_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<HomeSection, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(HomeSection::class, 'home_section_id');
    }

    public function isLiveAt(CarbonInterface $at): bool
    {
        return ($this->starts_at === null || $this->starts_at <= $at)
            && ($this->ends_at === null || $this->ends_at > $at);
    }
}
