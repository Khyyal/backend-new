<?php

namespace Modules\Homepage\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Modules\Centers\Http\Resources\Client\CenterResource;
use Modules\Centers\Models\Center;
use Modules\Homepage\Enums\HomeSectionType;
use Modules\Homepage\Enums\ItemableType;
use Modules\Homepage\Http\Resources\Client\BannerResource;
use Modules\Homepage\Http\Resources\Client\HomeCampaignResource;
use Modules\Homepage\Http\Resources\Client\HomeServiceResource;
use Modules\Homepage\Models\Banner;
use Modules\Homepage\Models\HomeSection;
use Modules\Homepage\Models\HomeSectionItem;
use Modules\Promotions\Contracts\ServicePriceDisplay;
use Modules\Promotions\Http\Resources\OfferResource;
use Modules\Promotions\Models\Campaign;
use Modules\Promotions\Models\Promotion;
use Modules\Promotions\Services\UsageLimitGuard;
use Modules\Services\Models\Service;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Support\Haversine;

/**
 * Turns one section definition into a page of resolved (plain array) items.
 *
 * Returned items are final JSON-ready arrays, never models, so they are safe to cache.
 * `boundaries` lists the moments something inside the payload starts or stops being visible.
 *
 * @phpstan-type SectionPage array{items: list<array<string, mixed>>, total: int, boundaries: list<CarbonInterface|null>}
 */
class HomeSectionBuilder
{
    public function __construct(
        private readonly ServicePriceDisplay $priceDisplay,
        private readonly UsageLimitGuard $usageLimits,
    ) {}

    /**
     * @return SectionPage
     */
    public function build(HomeSection $section, ?float $lat, ?float $lng, int $page, int $perPage, CarbonInterface $at): array
    {
        return match ($section->type) {
            HomeSectionType::BannerCarousel => $this->banners($section, $lat, $lng, $page, $perPage, $at),
            HomeSectionType::CuratedCenters => $this->curatedCenters($section, $lat, $lng, $page, $perPage, $at),
            HomeSectionType::CuratedServices => $this->curatedServices($section, $lat, $lng, $page, $perPage, $at),
            HomeSectionType::CuratedPromotions => $this->curatedPromotions($section, $page, $perPage, $at),
            HomeSectionType::CuratedCampaigns => $this->curatedCampaigns($section, $page, $perPage, $at),
            HomeSectionType::TopCenters => $this->topCenters($section, $lat, $lng, $page, $perPage),
        };
    }

    /** Whether the section's output depends on where the client is. */
    public function isLocationDependent(HomeSection $section): bool
    {
        return match ($section->type) {
            HomeSectionType::TopCenters => true,
            HomeSectionType::CuratedCenters, HomeSectionType::CuratedServices => $section->radiusKm() !== null,
            HomeSectionType::BannerCarousel => $section->radiusKm() !== null && $section->relationLoaded('banners')
                && $section->banners->contains(fn (Banner $banner): bool => $banner->center_id !== null),
            default => false,
        };
    }

    /**
     * @return SectionPage
     */
    private function banners(HomeSection $section, ?float $lat, ?float $lng, int $page, int $perPage, CarbonInterface $at): array
    {
        $boundaries = $this->boundariesOf($section->banners);
        $banners = $section->banners->filter(fn (Banner $banner): bool => $banner->isLiveAt($at));

        $centerIds = $banners->pluck('center_id')->filter()->unique()->values()->all();

        if ($centerIds !== []) {
            $visible = $this->visibleCenters($section, $lat, $lng)->whereKey($centerIds)->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $banners = $banners->filter(fn (Banner $banner): bool => $banner->center_id === null || in_array($banner->center_id, $visible, true));
        }

        $slice = new EloquentCollection($banners->forPage($page, $perPage)->values()->all());
        $slice->load('media');

        return [
            'items' => BannerResource::collection($slice)->resolve(),
            'total' => $banners->count(),
            'boundaries' => $boundaries,
        ];
    }

    /**
     * @return SectionPage
     */
    private function curatedCenters(HomeSection $section, ?float $lat, ?float $lng, int $page, int $perPage, CarbonInterface $at): array
    {
        $items = $this->liveItems($section, ItemableType::Center, $at);

        $centers = $items->isEmpty() ? collect() : $this->visibleCenters($section, $lat, $lng)
            ->whereKey($items->pluck('itemable_id')->all())
            ->withRatingStats()
            ->with(['city', 'tags', 'media'])
            ->get()
            ->keyBy('id');

        $ordered = $this->inItemOrder($items, $centers);

        return [
            'items' => CenterResource::collection($ordered->forPage($page, $perPage)->values())->resolve(),
            'total' => $ordered->count(),
            'boundaries' => $this->boundariesOf($section->items),
        ];
    }

    /**
     * @return SectionPage
     */
    private function curatedServices(HomeSection $section, ?float $lat, ?float $lng, int $page, int $perPage, CarbonInterface $at): array
    {
        $items = $this->liveItems($section, ItemableType::Service, $at);

        $services = $items->isEmpty() ? collect() : Service::query()
            ->whereKey($items->pluck('itemable_id')->all())
            ->where('status', ActivationStatus::ACTIVE->value)
            ->whereIn('center_id', $this->visibleCenters($section, $lat, $lng)->select('centers.id'))
            ->withMin('priceOptions as start_price', 'price')
            ->with(['center', 'media'])
            ->get()
            ->keyBy('id');

        $ordered = $this->inItemOrder($items, $services);
        $slice = new EloquentCollection($ordered->forPage($page, $perPage)->values()->all());
        $prices = $this->priceDisplay->forServices($slice, $at);

        return [
            'items' => $slice->map(fn (Service $service): array => HomeServiceResource::make($service)->withPrices($prices)->resolve())->values()->all(),
            'total' => $ordered->count(),
            'boundaries' => $this->boundariesOf($section->items),
        ];
    }

    /**
     * @return SectionPage
     */
    private function curatedPromotions(HomeSection $section, int $page, int $perPage, CarbonInterface $at): array
    {
        $items = $this->liveItems($section, ItemableType::Promotion, $at);

        $all = $items->isEmpty() ? new EloquentCollection : Promotion::query()
            ->whereKey($items->pluck('itemable_id')->all())
            ->with(['center', 'schedules', 'media'])
            ->get();

        // Promotions that start later still bound the cache, so they appear on time.
        $boundaries = [...$this->boundariesOf($section->items), ...$this->boundariesOf($all)];
        $running = $this->usageLimits->available($all->filter(fn (Promotion $promotion): bool => $promotion->isActiveAt($at))->values(), null);

        $ordered = $this->inItemOrder($items, $running->keyBy('id'));

        return [
            'items' => OfferResource::collection($ordered->forPage($page, $perPage)->values())->resolve(),
            'total' => $ordered->count(),
            'boundaries' => $boundaries,
        ];
    }

    /**
     * @return SectionPage
     */
    private function curatedCampaigns(HomeSection $section, int $page, int $perPage, CarbonInterface $at): array
    {
        $items = $this->liveItems($section, ItemableType::Campaign, $at);

        $all = $items->isEmpty() ? new EloquentCollection : Campaign::query()
            ->whereKey($items->pluck('itemable_id')->all())
            ->with('media')
            ->get();

        $boundaries = [...$this->boundariesOf($section->items), ...$this->boundariesOf($all)];
        $running = $all->filter(fn (Campaign $campaign): bool => $campaign->isRunningAt($at))->keyBy('id');

        $ordered = $this->inItemOrder($items, $running);

        return [
            'items' => HomeCampaignResource::collection($ordered->forPage($page, $perPage)->values())->resolve(),
            'total' => $ordered->count(),
            'boundaries' => $boundaries,
        ];
    }

    /**
     * Visible centers, nearest first when the client's position is known, otherwise most popular first.
     *
     * @return SectionPage
     */
    private function topCenters(HomeSection $section, ?float $lat, ?float $lng, int $page, int $perPage): array
    {
        $query = $this->visibleCenters($section, $lat, $lng)
            ->withRatingStats()
            ->with(['city', 'tags', 'media']);

        if ($lat !== null && $lng !== null) {
            $query->orderByRaw(Haversine::sql('centers.lat', 'centers.lng').' asc', Haversine::bindings($lat, $lng));
        } else {
            $query->orderByDesc('points');
        }

        $paginator = $query->orderBy('centers.id')->paginate($perPage, ['centers.*'], 'page', $page);

        return [
            'items' => CenterResource::collection($paginator->getCollection())->resolve(),
            'total' => $paginator->total(),
            'boundaries' => [],
        ];
    }

    /**
     * Centers clients can see, within the section radius when it has one and the position is known.
     *
     * @return Builder<Center>
     */
    private function visibleCenters(HomeSection $section, ?float $lat, ?float $lng): Builder
    {
        $radius = $section->radiusKm();

        return Center::query()
            ->visible()
            ->when($radius !== null && $lat !== null && $lng !== null, fn (Builder $query) => $query
                ->whereNotNull('centers.lat')
                ->whereNotNull('centers.lng')
                ->whereRaw(Haversine::sql('centers.lat', 'centers.lng').' <= CAST(? AS DECIMAL(10, 4))', [...Haversine::bindings($lat, $lng), $radius]));
    }

    /**
     * @return Collection<int, HomeSectionItem>
     */
    private function liveItems(HomeSection $section, ItemableType $type, CarbonInterface $at): Collection
    {
        return $section->items
            ->filter(fn (HomeSectionItem $item): bool => $item->itemable_type === $type && $item->isLiveAt($at))
            ->values();
    }

    /**
     * The resolved records in the admin's item order; items whose record is not shown are dropped.
     *
     * @template TModel
     *
     * @param  Collection<int, HomeSectionItem>  $items
     * @param  Collection<int, TModel>  $byId
     * @return Collection<int, TModel>
     */
    private function inItemOrder(Collection $items, Collection $byId): Collection
    {
        return $items->map(fn (HomeSectionItem $item) => $byId->get($item->itemable_id))->filter()->values();
    }

    /**
     * @param  Collection<int, mixed>  $rows  Models with starts_at / ends_at.
     * @return list<CarbonInterface|null>
     */
    private function boundariesOf(Collection $rows): array
    {
        return $rows->flatMap(fn ($row): array => [$row->starts_at, $row->ends_at])->values()->all();
    }
}
