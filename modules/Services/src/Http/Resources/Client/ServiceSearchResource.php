<?php

namespace Modules\Services\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Service;

/**
 * A service in search results.
 *
 * @property Service $resource
 */
class ServiceSearchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[ResponseField('id', 'integer', 'Service ID.', example: 31)]
    #[ResponseField('slug', 'string', 'Slug; open it with `GET /api/v1/clients/centers/{center}/services/{slug}`.', example: 'beginner-ride')]
    #[ResponseField('name', 'string', 'Name in the request locale.', example: 'Beginner ride')]
    #[ResponseField('description', 'string', 'Description in the request locale.', nullable: true, example: 'A guided ride for first-timers.')]
    #[ResponseField('type', 'string', 'Service type.', enum: ServiceType::class, example: 'recreation_riding')]
    #[ResponseField('center.id', 'integer', 'Center ID.', example: 8)]
    #[ResponseField('center.name', 'string', 'Center name.', example: 'Al Fursan Stables')]
    #[ResponseField('center.slug', 'string', 'Center slug.', example: 'al-fursan-stables')]
    public function toArray(Request $request): array
    {
        $service = $this->resource;

        return [
            'id' => $service->id,
            'slug' => $service->slug,
            'name' => $service->name,
            'description' => $service->description,
            'type' => $service->type->value,
            'center' => [
                'id' => $service->center->id,
                'name' => $service->center->name,
                'slug' => $service->center->slug,
            ],
        ];
    }
}
