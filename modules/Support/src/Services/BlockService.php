<?php

namespace Modules\Support\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Support\Models\Block;

class BlockService
{
    /**
     * Block a model on behalf of a blocker. Re-blocking updates the reason.
     */
    public function block(Model $blocker, Model $blockable, ?string $reason = null): Block
    {
        return Block::query()->updateOrCreate(
            [
                'blocker_type' => $blocker->getMorphClass(),
                'blocker_id' => $blocker->getKey(),
                'blockable_type' => $blockable->getMorphClass(),
                'blockable_id' => $blockable->getKey(),
            ],
            ['reason' => $reason],
        );
    }

    /**
     * Remove a block. Returns true when a block existed.
     */
    public function unblock(Model $blocker, Model $blockable): bool
    {
        return $this->query($blocker, $blockable)->delete() > 0;
    }

    public function isBlocked(Model $blocker, Model $blockable): bool
    {
        return $this->query($blocker, $blockable)->exists();
    }

    private function query(Model $blocker, Model $blockable)
    {
        return Block::query()
            ->where('blocker_type', $blocker->getMorphClass())
            ->where('blocker_id', $blocker->getKey())
            ->where('blockable_type', $blockable->getMorphClass())
            ->where('blockable_id', $blockable->getKey());
    }
}
