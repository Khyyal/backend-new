<?php

namespace Modules\Centers\Policies;

use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;

class CenterPolicy
{
    /**
     * Only the center's primary user may update it.
     */
    public function update(User $user, Center $center): bool
    {
        return $user->centers()
            ->whereKey($center->getKey())
            ->wherePivot('is_primary', true)
            ->exists();
    }

    /**
     * Any user assigned to the center may manage its services.
     */
    public function manageServices(User $user, Center $center): bool
    {
        return $user->centers()->whereKey($center->getKey())->exists();
    }
}
