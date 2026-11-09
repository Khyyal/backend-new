<?php

namespace Modules\Services\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\ServiceTypeTerm;

/**
 * @property ServiceTypeTerm $resource
 */
class ServiceTypeTermResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Terms record ID; null when none is defined yet.', nullable: true, example: 3)]
    #[ResponseField('type', 'string', 'Service type.', enum: ServiceType::class, example: 'visit')]
    #[ResponseField('terms', 'object', 'Terms keyed by locale; null when none is defined yet.', nullable: true, example: ['ar' => 'يجب الحضور قبل 15 دقيقة.', 'en' => 'Arrive 15 minutes early.'])]
    #[ResponseField('updated_at', 'string', 'Last update (ISO-8601); null when none.', nullable: true, example: '2026-05-20T09:30:00.000000Z')]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'type' => $this->resource->type,
            'terms' => $this->resource->exists ? $this->resource->getTranslations('terms') : null,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
