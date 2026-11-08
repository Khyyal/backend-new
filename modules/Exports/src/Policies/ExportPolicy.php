<?php

namespace Modules\Exports\Policies;

use Modules\Centers\Models\User;
use Modules\Exports\Models\Export;
use Modules\Support\Models\SystemUser;

/**
 * An export is private to the user who requested it. Who may request each
 * export is decided by the module that owns the exported data.
 */
class ExportPolicy
{
    public function view(User|SystemUser $user, Export $export): bool
    {
        if ($user instanceof SystemUser && ! $user->isActive()) {
            return false;
        }

        return $export->isRequestedBy($user);
    }
}
