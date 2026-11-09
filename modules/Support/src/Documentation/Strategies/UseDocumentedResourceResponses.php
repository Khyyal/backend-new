<?php

namespace Modules\Support\Documentation\Strategies;

use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseAttributes;
use Modules\Support\Documentation\ResourceExample;

/**
 * Same as Scribe's attribute strategy, except #[ResponseFromApiResource] examples are assembled from the
 * #[ResponseField] attributes on the resource's toArray() instead of factory models. Generation therefore
 * never touches the database, and the example always matches the documented fields.
 */
class UseDocumentedResourceResponses extends UseResponseAttributes
{
    /**
     * @return array{status: int, description: string|null, content: string}
     */
    protected function getApiResourceResponse(ResponseFromApiResource $attributeInstance): array
    {
        return [
            'status' => $attributeInstance->status,
            'description' => $attributeInstance->description,
            'content' => json_encode(ResourceExample::response($attributeInstance), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }
}
