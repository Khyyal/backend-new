<?php

namespace Modules\Exports\Notifications;

use Modules\Exports\Models\Export;
use Modules\Support\Notifications\AppNotification;

/**
 * Tells the requester that their export can be downloaded.
 */
final class ExportReadyNotification extends AppNotification
{
    public function __construct(
        public readonly Export $export,
    ) {}

    public function type(): string
    {
        return 'export_ready';
    }

    protected function translationKey(): string
    {
        return 'exports::notifications.ready';
    }

    public function data(): array
    {
        return [
            'export_id' => $this->export->getKey(),
            'export_type' => $this->export->type->value,
            'center_id' => $this->export->center_id,
        ];
    }

    protected function replacements(string $locale): array
    {
        return [
            'file' => $this->export->file_name,
        ];
    }
}
