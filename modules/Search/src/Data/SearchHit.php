<?php

namespace Modules\Search\Data;

/**
 * One search result. `center_id` and `kind` let the panel build nested detail routes.
 */
final readonly class SearchHit
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public int $id,
        public string $title,
        public ?string $subtitle = null,
        public ?string $code = null,
        public ?int $centerId = null,
        public ?string $kind = null,
        public array $meta = [],
    ) {}

    /**
     * @return array{id: int, title: string, subtitle: string|null, code: string|null, center_id: int|null, kind: string|null, meta: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'code' => $this->code,
            'center_id' => $this->centerId,
            'kind' => $this->kind,
            'meta' => (object) $this->meta,
        ];
    }
}
