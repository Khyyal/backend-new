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
    private const ATTRIBUTES = ['city_id', 'status', 'state', 'custom_site_enabled', 'domain'];

    private const CREATE_ATTRIBUTES = ['name', 'city_id', 'description', 'lat', 'lng', 'address', 'contact_phone', 'custom_site_enabled', 'domain', 'status', 'state'];

    public function __construct(private readonly CenterUpdateService $updateService) {}

    /**
     * @param  array{search?: ?string, status?: ?string, state?: ?string, city_id?: ?int}  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($filters)
            ->with(['city', 'media'])
            ->paginate($perPage);
    }

    /**
     * Centers filtered like the admin center list, newest first. Shared by the
     * list endpoint and the centers export.
     *
     * @param  array{search?: string|null, status?: string|null, state?: string|null, city_id?: int|null, from?: string, to?: string}  $filters
     * @return Builder<Center>
     */
    public function query(array $filters): Builder
    {
        return Center::query()
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query
                ->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_phone', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['state'] ?? null, fn (Builder $query, string $state) => $query->where('state', $state))
            ->when($filters['city_id'] ?? null, fn (Builder $query, int $cityId) => $query->where('city_id', $cityId))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('id');
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

            $owner = User::query()->create([
                'name' => $data['primary_user']['name'],
                'phone' => $data['primary_user']['phone'],
                'password' => $data['primary_user']['password'],
                'phone_verified_at' => now(),
            ]);

            $this->assignPrimaryUser($center, $owner);

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
