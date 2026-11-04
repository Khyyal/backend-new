<?php

namespace Modules\Homepage\Enums;

enum HomeSectionType: string
{
    case BannerCarousel = 'banner_carousel';
    case CuratedCenters = 'curated_centers';
    case CuratedServices = 'curated_services';
    case CuratedPromotions = 'curated_promotions';
    case CuratedCampaigns = 'curated_campaigns';
    case TopCenters = 'top_centers';

    /**
     * The item type a curated section holds; null for sections that fill themselves.
     */
    public function itemType(): ?ItemableType
    {
        return match ($this) {
            self::CuratedCenters => ItemableType::Center,
            self::CuratedServices => ItemableType::Service,
            self::CuratedPromotions => ItemableType::Promotion,
            self::CuratedCampaigns => ItemableType::Campaign,
            default => null,
        };
    }

    /** Whether `config.radius_km` applies (the section shows centers or things tied to one). */
    public function usesRadius(): bool
    {
        return in_array($this, [self::BannerCarousel, self::CuratedCenters, self::CuratedServices, self::TopCenters], true);
    }
}
