<?php

namespace Modules\Support\Documentation;

use Illuminate\Support\Arr;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use ReflectionMethod;

/**
 * Builds example JSON for an API resource from the #[ResponseField] attributes on its toArray().
 *
 * Field names use Scribe's notation: `a.b` for object keys and `a[].b` for keys of objects in a list.
 */
final class ResourceExample
{
    /**
     * Pagination envelope fields added to every paginated resource response.
     *
     * @var array<string, array{0: string, 1: string, 2: mixed}>
     */
    public const PAGINATION_FIELDS = [
        'links.first' => ['string', 'URL of the first page.', 'https://api.khyyal.com/api/v1/resource?page=1'],
        'links.last' => ['string', 'URL of the last page.', 'https://api.khyyal.com/api/v1/resource?page=4'],
        'links.prev' => ['string', 'URL of the previous page; null on the first page.', null],
        'links.next' => ['string', 'URL of the next page; null on the last page.', 'https://api.khyyal.com/api/v1/resource?page=2'],
        'meta.current_page' => ['integer', 'Current page number.', 1],
        'meta.from' => ['integer', 'Index of the first item on this page; null when empty.', 1],
        'meta.last_page' => ['integer', 'Last page number.', 4],
        'meta.links' => ['object[]', 'Pagination links for page navigation UIs (`url`, `label`, `active`).', null],
        'meta.path' => ['string', 'Base URL of the list.', 'https://api.khyyal.com/api/v1/resource'],
        'meta.per_page' => ['integer', 'Items per page.', 15],
        'meta.to' => ['integer', 'Index of the last item on this page; null when empty.', 15],
        'meta.total' => ['integer', 'Total number of items.', 52],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function response(ResponseFromApiResource $attribute): array
    {
        $item = self::item($attribute->name);
        $isList = $attribute->isCollection() || $attribute->paginate || $attribute->simplePaginate || $attribute->cursorPaginate;
        $body = ['data' => $isList ? [$item] : $item];

        if ($attribute->paginate) {
            foreach (self::PAGINATION_FIELDS as $name => [, , $example]) {
                Arr::set($body, $name, $example);
            }
            $body['meta']['links'] = [
                ['url' => null, 'label' => '&laquo; Previous', 'active' => false],
                ['url' => 'https://api.khyyal.com/api/v1/resource?page=1', 'label' => '1', 'active' => true],
                ['url' => 'https://api.khyyal.com/api/v1/resource?page=2', 'label' => 'Next &raquo;', 'active' => false],
            ];
        }

        return array_replace_recursive($body, $attribute->additional);
    }

    /**
     * @param  class-string  $resourceClass
     * @return array<string, mixed>
     */
    public static function item(string $resourceClass): array
    {
        $fields = collect((new ReflectionMethod($resourceClass, 'toArray'))->getAttributes(ResponseField::class))
            ->map(fn ($attribute): ResponseField => $attribute->newInstance())
            ->keyBy('name');

        $example = [];
        foreach ($fields as $name => $field) {
            self::place($example, explode('.', str_replace('[]', '.[]', $name)), $field);
        }

        return $example;
    }

    /**
     * @param  array<string, mixed>  $target
     * @param  list<string>  $segments
     */
    private static function place(array &$target, array $segments, ResponseField $field): void
    {
        $key = array_shift($segments);

        if ($segments === []) {
            if (array_key_exists($key, $target) && is_array($target[$key])) {
                return;
            }
            $target[$key] = self::value($field);

            return;
        }

        if ($segments[0] === '[]') {
            array_shift($segments);
            $target[$key] = is_array($target[$key] ?? null) && array_is_list($target[$key]) && $target[$key] !== [] ? $target[$key] : [[]];
            if ($segments === []) {
                return;
            }
            self::place($target[$key][0], $segments, $field);

            return;
        }

        $target[$key] = is_array($target[$key] ?? null) ? $target[$key] : [];
        self::place($target[$key], $segments, $field);
    }

    private static function value(ResponseField $field): mixed
    {
        $example = $field->example;

        if ($example === 'No-example') {
            return null;
        }

        if ($example !== null) {
            return $example;
        }

        return match (true) {
            str_ends_with((string) $field->type, '[]') => [],
            $field->type === 'object' => [],
            $field->nullable => null,
            $field->type === 'integer' => 1,
            $field->type === 'number' => 1.5,
            $field->type === 'boolean' => true,
            default => 'string',
        };
    }
}
