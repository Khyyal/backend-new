<?php

namespace Modules\Homepage\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Versioned cache keys for composed home content.
 *
 * Any write to a section, item, banner, banner image or to something a section shows (centers, services,
 * promotions, campaigns) bumps the content version, retiring every cached payload at once. Display prices
 * also depend on the promotions version the Promotions module maintains, so it is folded into the key.
 */
class HomeContentCache
{
    public const CONTENT_VERSION_KEY = 'homepage:version:content';

    public const TTL_SECONDS = 300;

    public function bump(): void
    {
        Cache::add(self::CONTENT_VERSION_KEY, 1);
        Cache::increment(self::CONTENT_VERSION_KEY);
    }

    public function key(string $suffix): string
    {
        Cache::add(self::CONTENT_VERSION_KEY, 1);

        $versions = (int) Cache::get(self::CONTENT_VERSION_KEY, 1).'.'.(int) Cache::get('promotions:version', 1);

        return "homepage:content:v{$versions}:".app()->getLocale().":{$suffix}";
    }

    /**
     * Seconds a payload may be cached: the standard TTL, cut short when something inside it
     * starts or stops being visible sooner.
     *
     * @param  list<CarbonInterface|null>  $boundaries
     */
    public function ttl(array $boundaries): int
    {
        $ttl = self::TTL_SECONDS;

        foreach ($boundaries as $boundary) {
            if ($boundary !== null && $boundary->isFuture()) {
                $ttl = min($ttl, max(1, (int) now()->diffInSeconds($boundary, true)));
            }
        }

        return $ttl;
    }
}
