<?php

namespace App\Console\Commands\Migrator;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Centers\Enums\CenterState;
use Modules\Centers\Enums\CenterStatus;
use Modules\Centers\Enums\SocialPlatform;
use Modules\Centers\Models\Center;
use Throwable;

#[Signature('migrator:centers
    {--dry-run : Run inside a transaction and roll it back}
    {--logos-url=https://app.khyyal.sa : Old server the legacy logo paths (barns.image, e.g. /storage/images/x.png) are downloaded from}'
    .LegacyMigrator::MEDIA_OPTIONS)]
#[Description('Copy legacy barns into the centers table (keeping their ids), uploading their logos (old server) and gallery images (s3) to the media disk')]
class MigrateCentersCommand extends LegacyMigrator
{
    /** @var array<string, string> Legacy social link types that map to a different platform. */
    private const SOCIAL_ALIASES = [
        'twitter' => 'x',
        'site' => 'website',
        'web' => 'website',
    ];

    /** Polymorphic type of barn rows in the legacy `images` table. */
    private const LEGACY_BARN_TYPE = 'App\\Models\\Barn';

    /** @var array<int, string> Legacy image path per center id, attached as logos after commit. */
    private array $logos = [];

    /** @var array<int, int> Ids of the centers migrated in this run. */
    private array $migratedIds = [];

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
                    $this->logos[$barn->id] = $barn->image;
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

            array_push($this->migratedIds, ...array_column($rows, 'id'));
            $this->tally('centers', count($rows));
        });
    }

    protected function afterCommit(bool $dryRun): void
    {
        Center::flushSearchCache();

        $skipMedia = $dryRun || (bool) $this->option('skip-media');

        $this->migrateLogos($skipMedia);
        $this->migrateGalleryImages($skipMedia);
    }

    /**
     * Uploads the barns' logos (legacy `barns.image`, served by the old app) to
     * the media disk as each center's `logo`.
     */
    private function migrateLogos(bool $skipMedia): void
    {
        if ($skipMedia) {
            $this->tally('logos pending (dry run or --skip-media)', count($this->logos));

            return;
        }

        foreach ($this->logos as $centerId => $path) {
            $this->attachLogo($centerId, $path);
        }
    }

    /**
     * Copies the barns' gallery files (legacy `images` table, stored on S3)
     * into each center's `images` collection, in their legacy order. The
     * legacy image id is kept as a custom property so re-runs skip it.
     */
    private function migrateGalleryImages(bool $skipMedia): void
    {
        $imagesByCenter = $this->legacy()->table('images')
            ->where('imageable_type', self::LEGACY_BARN_TYPE)
            ->whereIn('imageable_id', $this->migratedIds)
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->groupBy('imageable_id');

        if ($skipMedia) {
            $this->tally('images pending (dry run or --skip-media)', $imagesByCenter->flatten(1)->count());

            return;
        }

        foreach ($imagesByCenter as $centerId => $images) {
            $this->copyLegacyImages(Center::query()->findOrFail($centerId), $images, 'images', "Center #{$centerId}");
        }
    }

    /**
     * Downloads the logo from the old server (`<logos-url>/storage/images/x.png`)
     * and stores it on the media disk.
     */
    private function attachLogo(int $centerId, string $legacyPath): void
    {
        $center = Center::query()->find($centerId);

        if ($center === null || $center->hasMedia('logo')) {
            $this->tally('logos already present');

            return;
        }

        $url = rtrim((string) $this->option('logos-url'), '/').'/'.ltrim($legacyPath, '/');

        try {
            $response = Http::timeout(30)
                ->retry(2, 500, fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()), throw: false)
                ->get($url);

            if (! $response->successful()) {
                $this->addWarning("Center #{$centerId}: logo {$url} could not be downloaded (HTTP {$response->status()}); skipped.");
                $this->tally('logos missing');

                return;
            }

            $fileName = basename((string) parse_url($url, PHP_URL_PATH));

            $center->addMediaFromString($response->body())
                ->usingFileName($fileName)
                ->usingName(pathinfo($fileName, PATHINFO_FILENAME))
                ->withCustomProperties(['legacy_path' => $legacyPath])
                ->toMediaCollection('logo', $this->option('media-disk'));

            $this->tally('logos attached');
        } catch (Throwable $exception) {
            $this->addWarning("Center #{$centerId}: logo {$url} not attached ({$exception->getMessage()}).");
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
