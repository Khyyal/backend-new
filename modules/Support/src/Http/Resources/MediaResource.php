<?php

namespace Modules\Support\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Support\Enums\MediaStatus;
use Modules\Support\Models\TemporaryUpload;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property Media $resource
 */
class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isTemporary = $this->resource->model_type === (new TemporaryUpload)->getMorphClass();

        return [
            /** @var string */
            'id' => $this->resource->uuid,
            'status' => ($isTemporary ? MediaStatus::Temporary : MediaStatus::Attached)->value,
            'url' => $this->resource->getFullUrl(),
            'filename' => $this->resource->file_name,
            'mime_type' => $this->resource->mime_type,
            'size' => $this->resource->size,
            'expires_at' => $this->when($isTemporary, fn () => $this->resource->model?->expires_at),
        ];
    }
}
