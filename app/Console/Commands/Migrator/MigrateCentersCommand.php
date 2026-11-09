<?php

namespace App\Console\Commands\Migrator;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Centers\Enums\CenterState;
use Modules\Centers\Enums\CenterStatus;
use Modules\Centers\Enums\SocialPlatform;
use Modules\Centers\Models\Center;
use Throwable;

#[Signature('migrator:centers
    {--dry-run : Run inside a transaction and roll it back}
    {--media-base= : Base URL or local directory the legacy image paths (e.g. /storage/images/x.png) are resolved against; logos are skipped without it}')]
#[Description('Copy legacy barns into the centers table, keeping their ids')]
class MigrateCentersCommand extends LegacyMigrator
{
    /** @var array<string, string> Legacy social link types that map to a different platform. */
    private const SOCIAL_ALIASES = [
        'twitter' => 'x',
        'site' => 'website',
        'web' => 'website',
    ];

    /** @var array<int, string> Legacy image path per center id, attached as logos after commit. */
    private array $images = [];

    protected function migrate(): void
    {
        $cityIds = DB::table('cities')->pluck('id')->flip();
        $socialLinks = $this->socialLinksByBarn();
        $existing = DB::table('centers')->get(['id', 'slug', 'created_at']);
        $existingById = $existing->keyBy('id');
        $existingBySlug = $existing->pluck('id', 'slug');

        $this->chunkLegacy('barns', function (Collection $barns) use ($cityIds, $socialLinks, $existingById, $existingBySlug): void {
            $barns = $barns->filter(function (object $barn) use ($existingById, $existingBySlug): bool {
                $current = $existingById->get($barn->id);

                if ($current !== null && (string) $current->created_at !== (string) $barn->created_at) {
                    $this->addWarning("Barn #{$barn->id}: center #{$barn->id} already exists with other data; skipped.");

                    return false;
                }

                $slugOwner = $existingBySlug->get($barn->slug);

                if ($slugOwner !== null && $slugOwner !== $barn->id) {
                    $this->addWarning("Barn #{$barn->id}: slug '{$barn->slug}' is taken by center #{$slugOwner}; skipped.");

                    return false;
                }

                return true;
            });

            $rows = $barns->map(function (object $barn) use ($cityIds, $socialLinks): array {
                [$lat, $lng] = $this->coordinates($barn);

                $cityId = $barn->city_id;

                if ($cityId !== null && ! $cityIds->has($cityId)) {
                    $this->addWarning("Barn #{$barn->id}: city #{$cityId} does not exist; city left empty.");
                    $cityId = null;
                }

                if (filled($barn->image)) {
                    $this->images[$barn->id] = $barn->image;
                }

                $links = $socialLinks->get($barn->id);

                return [
                    'id' => $barn->id,
                    'city_id' => $cityId,
                    'name' => $barn->name,
                    'slug' => $barn->slug,
                    'description' => $barn->description,
                    'lat' => $lat,
                    'lng' => $lng,
                    'address' => filled($barn->zone) ? $barn->zone : null,
                    'contact_phone' => filled($barn->contact_number) ? $barn->contact_number : null,
                    'social_links' => $links ? json_encode($links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    'points' => max(0, (int) $barn->points),
                    'status' => $barn->active ? CenterStatus::VISIBLE->value : CenterStatus::INVISIBLE->value,
                    'state' => CenterState::Active->value,
                    'created_at' => $barn->created_at,
                    'updated_at' => $barn->updated_at,
                ];
            })->values()->all();

            if ($rows === []) {
                return;
            }

            DB::table('centers')->upsert($rows, ['id'], [
                'city_id', 'name', 'slug', 'description', 'lat', 'lng', 'address',
                'contact_phone', 'social_links', 'points', 'status', 'state', 'updated_at',
            ]);

            $this->tally('centers', count($rows));
        });
    }

    protected function afterCommit(bool $dryRun): void
    {
        Center::flushSearchCache();

        $base = $this->option('media-base');

        if ($dryRun || blank($base)) {
            $this->tally('logos skipped (no --media-base or dry run)', count($this->images));

            return;
        }

        foreach ($this->images as $centerId => $path) {
            $this->attachLogo($centerId, rtrim($base, '/').'/'.ltrim($path, '/'));
        }
    }

    private function attachLogo(int $centerId, string $source): void
    {
        $center = Center::query()->find($centerId);

        if ($center === null || $center->hasMedia('logo')) {
            $this->tally('logos already present');

            return;
        }

        try {
            Str::startsWith($source, ['http://', 'https://'])
                ? $center->addMediaFromUrl($source)->toMediaCollection('logo')
                : $center->addMedia($source)->preservingOriginal()->toMediaCollection('logo');

            $this->tally('logos attached');
        } catch (Throwable $exception) {
            $this->addWarning("Center #{$centerId}: logo {$source} not attached ({$exception->getMessage()}).");
        }
    }

    /**
     * Legacy `location` is a JSON string like {"lat":24.8,"lng":46.6}.
     *
     * @return array{0: float|null, 1: float|null}
     */
    private function coordinates(object $barn): array
    {
        if (blank($barn->location)) {
            return [null, null];
        }

        $location = json_decode($barn->location, true);
        $lat = $location['lat'] ?? null;
        $lng = $location['lng'] ?? null;

        if (! is_numeric($lat) || ! is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            $this->addWarning("Barn #{$barn->id}: invalid location '{$barn->location}'; coordinates left empty.");

            return [null, null];
        }

        return [round((float) $lat, 7), round((float) $lng, 7)];
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function socialLinksByBarn(): Collection
    {
        $platforms = SocialPlatform::values();

        return $this->legacy()->table('barn_social_links')->orderBy('id')->get()
            ->groupBy('barn_id')
            ->map(function (Collection $links, int $barnId) use ($platforms): array {
                $mapped = [];

                foreach ($links as $link) {
                    $type = strtolower(trim($link->type));
                    $type = self::SOCIAL_ALIASES[$type] ?? $type;

                    if (! in_array($type, $platforms, true)) {
                        $this->addWarning("Barn #{$barnId}: unknown social link type '{$link->type}' skipped.");

                        continue;
                    }

                    $mapped[$type] = Str::limit($link->url, 255, '');
                }

                return $mapped;
            })
            ->filter();
    }
}
