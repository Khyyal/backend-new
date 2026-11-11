<?php

namespace Modules\Settlements\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * The layout every printed payout invoice uses: company details, logo,
 * accent color, notes and which sections are shown. One row, created by its
 * migration and only ever updated. Notes are sanitized HTML keyed by en and ar.
 */
#[Translatable('company_name', 'company_address', 'header_note', 'footer_note')]
class PayoutInvoiceTemplate extends Model implements HasMedia
{
    use HasTranslations;
    use InteractsWithMedia;
    use LogsActivity;

    public const LOCALES = ['en', 'ar'];

    public const TRANSLATABLE = ['company_name', 'company_address', 'header_note', 'footer_note'];

    public const SECTIONS = ['show_bank_details', 'show_entry_summary', 'show_entries_table', 'show_signature'];

    protected $fillable = [
        'company_name',
        'company_address',
        'tax_number',
        'commercial_registration',
        'email',
        'phone',
        'accent_color',
        'header_note',
        'footer_note',
        'show_bank_details',
        'show_entry_summary',
        'show_entries_table',
        'show_signature',
    ];

    protected function casts(): array
    {
        return [
            'show_bank_details' => 'boolean',
            'show_entry_summary' => 'boolean',
            'show_entries_table' => 'boolean',
            'show_signature' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return self::query()->oldest('id')->firstOrFail();
    }

    /**
     * Fill every field, replacing (not merging) each translatable field's
     * locales so a cleared locale does not keep its old value. Not saved.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function applyAttributes(array $attributes): self
    {
        foreach (self::TRANSLATABLE as $field) {
            if (array_key_exists($field, $attributes)) {
                $this->replaceTranslations($field, $attributes[$field]);
                unset($attributes[$field]);
            }
        }

        return $this->fill($attributes);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png']);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('payout_invoice_template');
    }
}
