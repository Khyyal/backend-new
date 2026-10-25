<?php

namespace Modules\Support\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Support\Models\Block;

trait Blockable
{
    public function blocksReceived(): MorphMany
    {
        return $this->morphMany(Block::class, 'blockable');
    }
}
