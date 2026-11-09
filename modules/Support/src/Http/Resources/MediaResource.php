<?php

namespace Modules\Support\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Support\Enums\MediaStatus;
use Modules\Support\Models\TemporaryUpload;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property Media $resource
 */
class MediaResource extends JsonResource
{
    #[ResponseField('id', 'string', 'Media UUID; pass it to fields such as `cover` or `logo` to attach the file.', example: '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90')]
    #[ResponseField('status', 'string', '`temporary` until attached to a record, then `attached`.', enum: MediaStatus::class, example: 'temporary')]
    #[ResponseField('url', 'string', 'Public URL of the file.', example: 'https://cdn.khyyal.com/media/91/photo.jpg')]
    #[ResponseField('filename', 'string', 'Stored file name.', example: 'photo.jpg')]
    #[ResponseField('mime_type', 'string', 'MIME type detected from the content.', example: 'image/jpeg')]
    #[ResponseField('size', 'integer', 'Size in bytes.', example: 184233)]
    #[ResponseField('expires_at', 'string', 'When an unattached upload is deleted (ISO-8601). Only for temporary media.', required: false, example: '2026-05-21T09:30:00+00:00')]
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
