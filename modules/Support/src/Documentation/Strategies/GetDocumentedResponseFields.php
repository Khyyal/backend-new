<?php

namespace Modules\Support\Documentation\Strategies;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Extracting\Strategies\ResponseFields\GetFromResponseFieldAttribute;
use Modules\Support\Documentation\ResourceExample;

/**
 * Scribe's #[ResponseField] strategy plus the `links`/`meta` envelope of paginated resource responses.
 */
class GetDocumentedResponseFields extends GetFromResponseFieldAttribute
{
    /**
     * @return array<string, array<string, mixed>>|null
     */
    protected function extractFromAttributes(
        ExtractedEndpointData $endpointData,
        array $attributesOnMethod,
        array $attributesOnFormRequest = [],
        array $attributesOnController = [],
    ): ?array {
        $fields = parent::extractFromAttributes($endpointData, $attributesOnMethod, $attributesOnFormRequest, $attributesOnController) ?? [];

        $isPaginated = collect($endpointData->method->getAttributes(ResponseFromApiResource::class))
            ->contains(fn (\ReflectionAttribute $attribute): bool => (bool) $attribute->newInstance()->paginate);

        if (! $isPaginated) {
            return $fields;
        }

        foreach (ResourceExample::PAGINATION_FIELDS as $name => [$type, $description, $example]) {
            $fields[$name] ??= [
                'name' => $name,
                'type' => $type,
                'description' => $description,
                'required' => true,
                'example' => $example,
                'enumValues' => [],
                'nullable' => $example === null,
                'deprecated' => false,
            ];
        }

        return $fields;
    }
}
