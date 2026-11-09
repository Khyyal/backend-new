<?php

namespace Modules\Support\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Support\Enums\LegalAudience;
use Modules\Support\Enums\LegalDocumentType;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * A privacy policy or terms page for one audience. The four rows (audience x type) are created by the migration
 * and only ever updated. `content` is sanitized HTML keyed by en and ar.
 *
 * @property LegalAudience $audience
 * @property LegalDocumentType $type
 */
#[Translatable('content')]
class LegalDocument extends Model
{
    use HasTranslations;
    use LogsActivity;

    protected $fillable = ['content'];

    public const LOCALES = ['en', 'ar'];

    protected function casts(): array
    {
        return [
            'audience' => LegalAudience::class,
            'type' => LegalDocumentType::class,
        ];
    }

    public static function findFor(LegalAudience $audience, LegalDocumentType $type): self
    {
        return self::query()->where('audience', $audience)->where('type', $type)->firstOrFail();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('id');
    }

    /**
     * Public web page URL in the given locale.
     */
    public function url(string $locale): string
    {
        return route('legal.show', ['audience' => $this->audience, 'type' => $this->type, 'lang' => $locale]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('legal_document');
    }
}
