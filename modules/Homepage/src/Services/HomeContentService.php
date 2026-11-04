<?php

namespace Modules\Homepage\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Homepage\Enums\PublishStatus;
use Modules\Homepage\Models\HomeSection;

/**
 * Composes the client home page: published, in-window sections in order, each with its first items.
 *
 * Section definitions (with their items and banners) are cached once under a versioned key; whether a
 * section is inside its date window is decided per request, so scheduling is always exact. Each section's
 * items are cached separately, per locale and (only when location matters) per rounded position.
 */
class HomeContentService
{
    public function __construct(
        private readonly HomeContentCache $cache,
        private readonly HomeSectionBuilder $builder,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function compose(?float $lat, ?float $lng, ?CarbonInterface $at = null): array
    {
        $at ??= now();
        $payload = [];

        foreach ($this->liveSections($at) as $section) {
            $limit = $section->limit();
            $result = $this->sectionPage($section, $lat, $lng, 1, $limit, $at);

            if ($result['items'] === []) {
                continue;
            }

            $payload[] = $this->present($section, $result['items'], $result['total'] > $limit);
        }

        return $payload;
    }

    /**
     * One section with pagination, or null when it isn't visible right now.
     *
     * @return array{data: array<string, mixed>, meta: array<string, int>}|null
     */
    public function section(string $key, ?float $lat, ?float $lng, int $page, ?int $perPage = null, ?CarbonInterface $at = null): ?array
    {
        $at ??= now();
        $section = $this->liveSections($at)->firstWhere('key', $key);

        if ($section === null) {
            return null;
        }

        $perPage = max(1, min(100, $perPage ?? $section->limit()));
        $result = $this->sectionPage($section, $lat, $lng, $page, $perPage, $at);

        return [
            'data' => $this->present($section, $result['items'], $result['total'] > $page * $perPage),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
                'last_page' => max(1, (int) ceil($result['total'] / $perPage)),
            ],
        ];
    }

    /**
     * @return Collection<int, HomeSection>
     */
    private function liveSections(CarbonInterface $at): Collection
    {
        return $this->definitions()->filter(fn (HomeSection $section): bool => $section->isLiveAt($at))->values();
    }

    /**
     * @return Collection<int, HomeSection>
     */
    private function definitions(): Collection
    {
        return Cache::remember(
            $this->cache->key('definitions'),
            HomeContentCache::TTL_SECONDS,
            fn (): Collection => HomeSection::query()
                ->where('status', PublishStatus::Published->value)
                ->with(['items', 'banners'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
        );
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    private function sectionPage(HomeSection $section, ?float $lat, ?float $lng, int $page, int $perPage, CarbonInterface $at): array
    {
        $position = match (true) {
            ! $this->builder->isLocationDependent($section) => 'any',
            $lat === null || $lng === null => 'none',
            default => 'at'.round($lat, 2).','.round($lng, 2),
        };

        $key = $this->cache->key("section:{$section->id}:{$position}:p{$page}:n{$perPage}");
        $cached = Cache::get($key);

        if (is_array($cached) && $cached['valid_until'] > $at->getTimestamp()) {
            return $cached;
        }

        $built = $this->builder->build($section, $lat, $lng, $page, $perPage, $at);
        $ttl = $this->cache->ttl($built['boundaries']);

        $cached = [
            'items' => $built['items'],
            'total' => $built['total'],
            'valid_until' => now()->addSeconds($ttl)->getTimestamp(),
        ];

        Cache::put($key, $cached, $ttl);

        return $cached;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function present(HomeSection $section, array $items, bool $hasMore): array
    {
        return [
            'id' => $section->id,
            'key' => $section->key,
            'type' => $section->type->value,
            'title' => $section->title,
            'subtitle' => $section->subtitle,
            'items' => $items,
            'has_more' => $hasMore,
        ];
    }
}
