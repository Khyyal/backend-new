<?php

namespace Modules\Support\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Support\Http\Requests\Admin\ListActivityLogsRequest;
use Spatie\Activitylog\Models\Activity;

/**
 * @property Activity $resource
 */
class ActivityLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[ResponseField('id', 'integer', 'Log entry ID.', example: 5012)]
    #[ResponseField('log_name', 'string', 'Audited area.', enum: ListActivityLogsRequest::LOG_NAMES, example: 'service')]
    #[ResponseField('event', 'string', 'What happened.', enum: ['created', 'updated', 'deleted', 'restored'], example: 'updated')]
    #[ResponseField('subject_id', 'integer', 'ID of the changed record (in the `log_name` area).', nullable: true, example: 31)]
    #[ResponseField('causer', 'object', 'Who made the change; null for system/automated changes.', nullable: true)]
    #[ResponseField('causer.type', 'string', 'Actor model: `SystemUser`, `User` (center user) or `Client`.', example: 'SystemUser')]
    #[ResponseField('causer.id', 'integer', 'Actor ID.', example: 2)]
    #[ResponseField('causer.name', 'string', 'Actor display name.', nullable: true, example: 'Sara Admin')]
    #[ResponseField('changes', 'object', 'Changed attributes: `attributes` (new values) and `old` (previous values) for updates.', nullable: true, example: ['attributes' => ['status' => 'inactive'], 'old' => ['status' => 'active']])]
    #[ResponseField('created_at', 'string', 'When it happened (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    public function toArray(Request $request): array
    {
        $causer = $this->resource->causer;

        return [
            'id' => $this->resource->id,
            'log_name' => $this->resource->log_name,
            'event' => $this->resource->event,
            'subject_id' => $this->resource->subject_id,
            'causer' => $causer === null ? null : [
                'type' => class_basename($causer),
                'id' => $causer->getKey(),
                'name' => $causer->name ?? null,
            ],
            'changes' => $this->resource->attribute_changes,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
