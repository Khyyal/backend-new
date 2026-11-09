<?php

namespace App\Console\Commands\Migrator;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Centers\Enums\CenterUserRole;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Support\Enums\ActivationStatus;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

#[Signature('migrator:center-users {--dry-run : Run inside a transaction and roll it back}')]
#[Description('Copy legacy barn managers, their barn assignments and permissions into center users')]
class MigrateCenterUsersCommand extends LegacyMigrator
{
    public const ENTITY = 'center_user';

    private const ROLE_OWNER = 'owner';

    private const ROLE_SUPER_MANAGER = 'super_manager';

    /**
     * Legacy permission ("group:name") → new center permissions.
     *
     * @var array<string, array<int, CenterUserPermission>>
     */
    private const PERMISSION_MAP = [
        'other:can_edit' => [CenterUserPermission::EditReservations],
        'other:can_change_status' => [CenterUserPermission::ManageReservations],
        'other:can_view_service_prices' => [CenterUserPermission::ViewPurchasePrices],
        'other:can_view_purchases_not_linked_to_other_employees' => [CenterUserPermission::ViewUnassignedPurchases],
        'other:can_view_purchases_of_other_employees' => [CenterUserPermission::ViewOtherTrainersPurchases],
        'other:account_management' => [CenterUserPermission::ManageUsers],
        'other:packages_management' => [CenterUserPermission::ManageMembershipServices],
        'coupons:can_view_coupons' => [CenterUserPermission::ManagePromotions],

        'services_management:free_riding' => [CenterUserPermission::ManageRecreationRidingServices],
        'services_management:training' => [CenterUserPermission::ManageTrainingServices],
        'services_management:visits' => [CenterUserPermission::ManageVisitServices],
        'services_management:events' => [CenterUserPermission::ManageEventServices],
        'services_management:horse_stabling' => [CenterUserPermission::ManageResortServices],

        'viewable_purchases:free_riding' => [CenterUserPermission::ViewRecreationRidingPurchases],
        'viewable_purchases:training' => [CenterUserPermission::ViewTrainingPurchases],
        'viewable_purchases:visits' => [CenterUserPermission::ViewVisitPurchases],
        'viewable_purchases:events' => [CenterUserPermission::ViewEventPurchases],
        'viewable_purchases:horse_stabling' => [CenterUserPermission::ViewResortPurchases],

        'horses_management:care_plan' => [CenterUserPermission::ManageHorseCareServices, CenterUserPermission::ViewHorseCarePurchases],
        'horses_management:care_tasks' => [CenterUserPermission::ManageHorseCareServices, CenterUserPermission::ViewHorseCarePurchases],
        'horses_management:purchases' => [CenterUserPermission::ManageHorseCareServices, CenterUserPermission::ViewHorseCarePurchases],
        'horses_management:orders' => [CenterUserPermission::ManageHorseCareServices, CenterUserPermission::ViewHorseCarePurchases],
    ];

    protected function migrate(): void
    {
        $assignments = $this->legacy()->table('barn_manager_assignments')->orderBy('id')->get();
        $managers = $this->legacy()->table('barn_managers')
            ->whereIn('id', $assignments->pluck('barn_manager_id')->unique())
            ->get()
            ->keyBy('id');

        $this->tally('legacy managers without assignment (skipped)', $this->legacy()->table('barn_managers')->count() - $managers->count());

        $canonical = $this->migrateUsers($managers);
        $this->migrateAssignments($assignments, $managers, $canonical);
    }

    protected function afterCommit(bool $dryRun): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Upserts one center user per phone. Legacy managers sharing a phone are
     * folded into one canonical user and recorded in legacy_id_maps.
     *
     * @param  Collection<int, object>  $managers
     * @return array<int, int> legacy manager id → new user id (null entries are skipped managers)
     */
    private function migrateUsers(Collection $managers): array
    {
        $canonical = [];
        $existingByPhone = DB::table('center_users')->pluck('id', 'phone');
        $existingById = DB::table('center_users')->get(['id', 'created_at'])->keyBy('id');

        foreach ($managers->groupBy('phone_number') as $phone => $group) {
            $keeper = $group->sortBy([
                fn (object $a, object $b): int => ($a->deleted_at !== null) <=> ($b->deleted_at !== null),
                fn (object $a, object $b): int => strcmp((string) $b->updated_at, (string) $a->updated_at),
            ])->first();

            $userId = $keeper->id;
            $phoneOwner = $existingByPhone->get($phone);

            if ($phoneOwner !== null && ! $group->pluck('id')->contains($phoneOwner)) {
                $this->addWarning("Manager #{$keeper->id}: phone {$phone} already belongs to center user #{$phoneOwner}; merged into it.");
                $userId = $phoneOwner;
            } elseif ($this->isForeignRow($existingById->get($keeper->id), $keeper)) {
                $this->addWarning("Manager #{$keeper->id}: center user #{$keeper->id} already exists with other data; skipped.");

                continue;
            } else {
                DB::table('center_users')->upsert([[
                    'id' => $keeper->id,
                    'name' => $keeper->full_name,
                    'phone' => $phone,
                    'password' => $keeper->password,
                    'phone_verified_at' => $keeper->created_at,
                    'deleted_at' => $keeper->deleted_at,
                    'created_at' => $keeper->created_at,
                    'updated_at' => $keeper->updated_at,
                ]], ['id'], ['name', 'phone', 'password', 'deleted_at', 'updated_at']);

                $this->tally('center users');
            }

            foreach ($group as $manager) {
                $canonical[$manager->id] = $userId;

                if ($manager->id !== $userId) {
                    $this->mapId(self::ENTITY, $manager->id, $userId);
                    $this->tally('managers merged (duplicate phone)');
                }
            }
        }

        return $canonical;
    }

    /**
     * @param  Collection<int, object>  $assignments
     * @param  Collection<int, object>  $managers
     * @param  array<int, int>  $canonical
     */
    private function migrateAssignments(Collection $assignments, Collection $managers, array $canonical): void
    {
        $centerIds = DB::table('centers')->pluck('id')->flip();
        $permissions = $this->legacyPermissions();

        $merged = $assignments
            ->filter(fn (object $row): bool => isset($canonical[$row->barn_manager_id]))
            ->groupBy(fn (object $row): string => $row->barn_id.'-'.$canonical[$row->barn_manager_id])
            ->map(fn (Collection $rows): object => $rows->firstWhere('role', self::ROLE_OWNER) ?? $rows->first());

        $owners = [];

        foreach ($merged as $row) {
            $userId = $canonical[$row->barn_manager_id];

            if (! $centerIds->has($row->barn_id)) {
                $this->addWarning("Assignment #{$row->id}: barn #{$row->barn_id} was not migrated; skipped.");

                continue;
            }

            $isPrimary = $row->role === self::ROLE_OWNER;
            $deleted = ($managers->get($userId)?->deleted_at ?? null) !== null;

            DB::table('center_user_assignment')->upsert([[
                'center_id' => $row->barn_id,
                'user_id' => $userId,
                'status' => $deleted ? ActivationStatus::INACTIVE->value : ActivationStatus::ACTIVE->value,
                'joined_at' => Carbon::parse($row->created_at ?? now())->toDateString(),
                'is_primary' => $isPrimary,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]], ['center_id', 'user_id'], ['status', 'joined_at', 'is_primary', 'updated_at']);

            $this->tally('assignments');

            if ($isPrimary) {
                $owners[$row->barn_id] = true;
            }

            $this->grantAccess($row, $userId, $permissions);
        }

        $withoutOwner = $centerIds->keys()->reject(fn (int $id): bool => isset($owners[$id]));
        $this->tally('centers without primary user', $withoutOwner->count());

        if ($withoutOwner->isNotEmpty()) {
            $this->addWarning('Centers without a primary user: '.$withoutOwner->implode(', '));
        }
    }

    /**
     * Owners get the owner role; super managers every permission; managers
     * their mapped legacy permissions, scoped to the center's team.
     *
     * @param  Collection<int, string>  $permissions  legacy permission id → "group:name"
     */
    private function grantAccess(object $row, int $userId, Collection $permissions): void
    {
        $user = User::withTrashed()->findOrFail($userId);
        $center = Center::withTrashed()->findOrFail($row->barn_id);

        app(PermissionRegistrar::class)->setPermissionsTeamId($center->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        if ($row->role === self::ROLE_OWNER) {
            Role::findOrCreate(CenterUserRole::Owner->value, 'center_user');
            $user->assignRole(CenterUserRole::Owner->value);

            return;
        }

        if ($row->role === self::ROLE_SUPER_MANAGER) {
            $user->syncCenterPermissions($center, CenterUserPermission::cases());
            $this->tally('super managers (all permissions)');

            return;
        }

        $granted = $this->legacy()->table('manager_permissions')
            ->where('barn_id', $row->barn_id)
            ->where('barn_manager_id', $row->barn_manager_id)
            ->pluck('permission_id')
            ->flatMap(function (int $permissionId) use ($permissions, $row): array {
                $key = $permissions->get($permissionId);

                if (! isset(self::PERMISSION_MAP[$key])) {
                    $this->addWarning("Manager #{$row->barn_manager_id} on barn #{$row->barn_id}: legacy permission '{$key}' has no mapping; not granted.");

                    return [];
                }

                return self::PERMISSION_MAP[$key];
            })
            ->unique(fn (CenterUserPermission $permission): string => $permission->value)
            ->values()
            ->all();

        $user->syncCenterPermissions($center, $granted);
        $this->tally('managers with mapped permissions');
    }

    /**
     * @return Collection<int, string> legacy permission id → "group:name"
     */
    private function legacyPermissions(): Collection
    {
        return $this->legacy()->table('permissions')->get()
            ->mapWithKeys(fn (object $permission): array => [$permission->id => $permission->group.':'.$permission->name]);
    }

    /**
     * A row with this id exists but was not created by this migrator (its
     * created_at differs from the legacy row's).
     */
    private function isForeignRow(?object $existing, object $legacy): bool
    {
        return $existing !== null && (string) $existing->created_at !== (string) $legacy->created_at;
    }
}
