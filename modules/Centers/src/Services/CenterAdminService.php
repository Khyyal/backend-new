<?php

namespace Modules\Centers\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Centers\Enums\CenterStatus;
use Modules\Centers\Enums\CenterUserRole;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Support\Enums\ActivationStatus;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * System-user facing center management. Tags and media are delegated to
 * CenterUpdateService so they behave exactly as in the center portal.
 */
class CenterAdminService
{
    private const ATTRIBUTES = ['city_id', 'status'];

    private const CREATE_ATTRIBUTES = ['name', 'city_id', 'description', 'lat', 'lng', 'address', 'contact_phone', 'status'];

    public function __construct(private readonly CenterUpdateService $updateService) {}

    /**
     * @param  array{search?: ?string, status?: ?string, city_id?: ?int}  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Center::query()
            ->with(['city', 'media'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query
                ->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_phone', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['city_id'] ?? null, fn (Builder $query, int $cityId) => $query->where('city_id', $cityId))
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Model $actor, array $data): Center
    {
        return DB::transaction(function () use ($actor, $data) {
            $center = Center::create([
                'status' => CenterStatus::INVISIBLE,
                ...Arr::only($data, self::CREATE_ATTRIBUTES),
                'slug' => $this->generateUniqueSlug($data['name']),
            ]);

            if (! empty($data['primary_user_id'])) {
                $this->assignPrimaryUser($center, User::query()->findOrFail($data['primary_user_id']));
            }

            return $this->updateService->update($center, $actor, $data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model $actor, Center $center, array $data): Center
    {
        return DB::transaction(function () use ($actor, $center, $data) {
            $center->fill(Arr::only($data, self::ATTRIBUTES))->save();

            return $this->updateService->update($center, $actor, $data);
        });
    }

    public function delete(Center $center): void
    {
        $center->delete();
    }

    public function find(Center $center): Center
    {
        return $center->load(['city', 'tags', 'media']);
    }

    private function assignPrimaryUser(Center $center, User $user): void
    {
        $center->users()->attach($user->getKey(), [
            'status' => ActivationStatus::ACTIVE->value,
            'joined_at' => now()->toDateString(),
            'is_primary' => true,
        ]);

        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($center->getKey());
        Role::findOrCreate(CenterUserRole::Owner->value, 'center_user');
        $user->assignRole(CenterUserRole::Owner->value);

        $registrar->setPermissionsTeamId($previousTeamId);
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'center';
        $slug = $base;
        $counter = 1;

        while (Center::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
