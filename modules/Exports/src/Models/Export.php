<?php

namespace Modules\Exports\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Modules\Exports\Database\Factories\ExportFactory;
use Modules\Exports\Enums\ExportStatus;
use Modules\Exports\Enums\ExportType;

/**
 * A spreadsheet export and where its file is stored.
 *
 * @property int $id
 * @property string $requester_type
 * @property int $requester_id
 * @property int|null $center_id
 * @property ExportType $type
 * @property array<string, mixed>|null $filters
 * @property ExportStatus $status
 * @property string $disk
 * @property string $path
 * @property string $file_name
 * @property int|null $rows_count
 * @property string|null $error
 * @property Carbon|null $completed_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 */
class Export extends Model
{
    use HasFactory;

    protected $fillable = [
        'requester_type',
        'requester_id',
        'center_id',
        'type',
        'filters',
        'status',
        'disk',
        'path',
        'file_name',
        'rows_count',
        'error',
        'completed_at',
        'failed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExportType::class,
            'status' => ExportStatus::class,
            'filters' => 'array',
            'rows_count' => 'integer',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function requester(): MorphTo
    {
        return $this->morphTo();
    }

    public function isRequestedBy(Model $user): bool
    {
        return $this->requester_type === $user->getMorphClass()
            && $this->requester_id === $user->getKey();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    protected static function newFactory(): ExportFactory
    {
        return ExportFactory::new();
    }
}
