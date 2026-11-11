<?php

namespace Modules\Settlements\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Modules\Centers\Models\Center;
use Modules\Settlements\Enums\CenterLedgerEntryType;
use Modules\Settlements\Enums\CenterPayoutStatus;
use Modules\Settlements\Models\CenterLedgerEntry;
use Modules\Settlements\Models\CenterPayout;
use Modules\Settlements\Models\PayoutInvoiceTemplate;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Renders a payout as a printable PDF invoice laid out by the invoice
 * template. mPDF is created per call (never shared between requests) and
 * shapes Arabic text and right-to-left layout.
 */
class PayoutInvoiceRenderer
{
    /**
     * @param  Media|false|null  $logo  the logo to draw; null uses the template's own, false draws none
     */
    public function render(CenterPayout $payout, PayoutInvoiceTemplate $template, string $locale, Media|false|null $logo = null): string
    {
        $previousLocale = app()->getLocale();
        app()->setLocale($locale);

        try {
            $tempDir = storage_path('framework/mpdf');
            File::ensureDirectoryExists($tempDir);

            $mpdf = new Mpdf([
                'tempDir' => $tempDir,
                'mode' => 'utf-8',
                'format' => 'A4',
                'default_font' => 'dejavusans',
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
                'margin_top' => 12,
                'margin_bottom' => 14,
                'margin_left' => 12,
                'margin_right' => 12,
            ]);
            $mpdf->SetDirectionality($locale === 'ar' ? 'rtl' : 'ltr');
            $mpdf->SetTitle(__('settlements::invoice.title').' '.self::number($payout));

            $logoMedia = $logo === null ? $template->getFirstMedia('logo') : ($logo ?: null);
            if ($logoMedia !== null) {
                $mpdf->imageVars['logo'] = Storage::disk($logoMedia->disk)->get($logoMedia->getPathRelativeToRoot());
            }

            if ($payout->status === CenterPayoutStatus::Cancelled) {
                $mpdf->SetWatermarkText(__('settlements::invoice.cancelled_watermark'), 0.12);
                $mpdf->showWatermarkText = true;
            }

            $mpdf->WriteHTML(view('settlements::invoices.payout', [
                'payout' => $payout,
                'template' => $template,
                'locale' => $locale,
                'number' => self::number($payout),
                'hasLogo' => $logoMedia !== null,
                'summary' => $this->summary($payout->entries),
                'entries' => $payout->entries->sortBy('id')->values(),
                'money' => self::money(...),
                'date' => fn (?Carbon $value): string => $value?->utc()->format('Y-m-d') ?? '—',
            ])->render());

            $mpdf->SetHTMLFooter('<div style="text-align: center; font-size: 8pt; color: #888;">'.e(self::number($payout)).' · {PAGENO} / {nbpg}</div>');

            return $mpdf->Output('', Destination::STRING_RETURN);
        } finally {
            app()->setLocale($previousLocale);
        }
    }

    /**
     * A filled-in, unsaved payout used to preview the template when no real
     * payout is chosen.
     */
    public function samplePayout(string $locale): CenterPayout
    {
        $now = now()->startOfDay();
        $types = [
            [CenterLedgerEntryType::Payment, 45000],
            [CenterLedgerEntryType::Payment, 30000],
            [CenterLedgerEntryType::PlatformSubsidy, 5000],
            [CenterLedgerEntryType::PlatformFee, -7500],
            [CenterLedgerEntryType::Refund, -10000],
        ];

        $entries = collect($types)->map(fn (array $row, int $index): CenterLedgerEntry => (new CenterLedgerEntry)->forceFill([
            'id' => 1001 + $index,
            'purchase_id' => 501 + $index,
            'type' => $row[0],
            'amount' => $row[1],
            'available_at' => $now->copy()->subDays(5 - $index),
        ]));

        $payout = (new CenterPayout)->forceFill([
            'id' => 1,
            'center_id' => 0,
            'period_end' => $now,
            'amount' => $entries->sum('amount'),
            'entries_count' => $entries->count(),
            'status' => CenterPayoutStatus::Pending,
            'iban' => 'SA0380000000608010167519',
            'account_name' => __('settlements::invoice.sample_center', [], $locale),
            'bank_name' => 'Al Rajhi Bank',
            'created_at' => $now,
        ]);

        return $payout
            ->setRelation('center', (new Center)->forceFill(['name' => __('settlements::invoice.sample_center', [], $locale)]))
            ->setRelation('entries', $entries);
    }

    public static function number(CenterPayout $payout): string
    {
        return 'PO-'.str_pad((string) $payout->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Halalas as SAR with two decimals, computed without floats.
     */
    public static function money(int $halalas): string
    {
        $absolute = abs($halalas);

        return ($halalas < 0 ? '-' : '').number_format(intdiv($absolute, 100)).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Entries grouped by type in enum order, each with its count and total.
     *
     * @param  Collection<int, CenterLedgerEntry>  $entries
     * @return array<int, array{type: string, count: int, amount: int}>
     */
    private function summary(Collection $entries): array
    {
        $byType = $entries->groupBy(fn (CenterLedgerEntry $entry): string => $entry->type->value);

        return collect(CenterLedgerEntryType::cases())
            ->filter(fn (CenterLedgerEntryType $type): bool => $byType->has($type->value))
            ->map(fn (CenterLedgerEntryType $type): array => [
                'type' => $type->value,
                'count' => $byType[$type->value]->count(),
                'amount' => (int) $byType[$type->value]->sum('amount'),
            ])
            ->values()
            ->all();
    }
}
