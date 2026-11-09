<?php

namespace Modules\Support\Policies;

use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\LegalDocument;
use Modules\Support\Models\SystemUser;

class LegalDocumentPolicy
{
    /**
     * Inactive system users are denied everything.
     */
    public function before(SystemUser $user): ?bool
    {
        return $user->isActive() ? null : false;
    }

    public function viewAny(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ManageLegalDocuments);
    }

    public function view(SystemUser $user, LegalDocument $legalDocument): bool
    {
        return $user->hasPermissionTo(SystemPermission::ManageLegalDocuments);
    }

    public function update(SystemUser $user, LegalDocument $legalDocument): bool
    {
        return $user->hasPermissionTo(SystemPermission::ManageLegalDocuments);
    }
}
