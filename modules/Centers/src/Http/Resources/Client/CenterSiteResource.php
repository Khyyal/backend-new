<?php

namespace Modules\Centers\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Centers\Models\Center;

/**
 * @property Center $resource
 */
class CenterSiteResource extends JsonResource
{
    /**
     * @return array{id: int, slug: string, site_theme: array<string, string>|null}
     */
    #[ResponseField('id', 'integer', 'Center ID.', example: 8)]
    #[ResponseField('slug', 'string', 'Slug; use it on `GET /api/v1/clients/centers/{slug}`.', example: 'al-fursan-stables')]
    #[ResponseField('site_theme', 'object', 'Hex colors keyed by role (`background`, `foreground`, `primary`, `button`, `secondary`, `accent`); null when unset.', nullable: true, example: ['primary' => '#8B4513', 'background' => '#FFFFFF'])]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'slug' => $this->resource->slug,
            'site_theme' => $this->resource->site_theme,
        ];
    }
}
