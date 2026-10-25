<?php

namespace Modules\Support\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Support\Models\Block;

trait Blocker
{
    public function blocksGiven(): MorphMany
    {
        return $this->morphMany(Block::class, 'blocker');
    }
}
