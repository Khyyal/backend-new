@php
    $rtl = $locale === 'ar';
    $start = $rtl ? 'right' : 'left';
    $end = $rtl ? 'left' : 'right';
    $accent = $template->accent_color ?: '#1F4E79';
    $t = fn (string $key): string => __('settlements::invoice.'.$key);
    $company = $template->getTranslation('company_name', $locale, false) ?: $template->getTranslation('company_name', 'en', false);
    $address = $template->getTranslation('company_address', $locale, false);
    $headerNote = $template->getTranslation('header_note', $locale, false);
    $footerNote = $template->getTranslation('footer_note', $locale, false);
@endphp
<html dir="{{ $rtl ? 'rtl' : 'ltr' }}" lang="{{ $locale }}">
<head>
<style>
    body { font-family: dejavusans; font-size: 9.5pt; color: #222; }
    table { border-collapse: collapse; width: 100%; }
    td, th { vertical-align: top; }
    .muted { color: #666; }
    .title { font-size: 18pt; font-weight: bold; color: {{ $accent }}; }
    .company { font-size: 12pt; font-weight: bold; }
    .box { border: 0.6pt solid #d8d8d8; padding: 8pt; }
    .label { color: #666; font-size: 8.5pt; }
    .section { font-size: 11pt; font-weight: bold; color: {{ $accent }}; padding: 14pt 0 6pt 0; }
    .grid th { background-color: {{ $accent }}; color: #fff; font-weight: bold; padding: 5pt 6pt; text-align: {{ $start }}; font-size: 8.5pt; }
    .grid td { padding: 5pt 6pt; border-bottom: 0.5pt solid #e5e5e5; }
    .num { text-align: {{ $end }}; direction: ltr; }
    .total td { font-size: 12pt; font-weight: bold; padding: 8pt 6pt; border-top: 1.2pt solid {{ $accent }}; }
    .note { padding-top: 8pt; }
</style>
</head>
<body>
<table>
    <tr>
        <td style="width: 55%; text-align: {{ $start }};">
            @if ($hasLogo)
                <img src="var:logo" style="max-height: 60px; max-width: 180px;" /><br />
            @endif
            <div class="company">{{ $company }}</div>
            @if ($address)
                <div class="muted">{{ $address }}</div>
            @endif
            @foreach (['tax_number', 'commercial_registration', 'email', 'phone'] as $field)
                @if ($template->{$field})
                    <div class="muted">{{ $t($field) }}: <span dir="ltr">{{ $template->{$field} }}</span></div>
                @endif
            @endforeach
        </td>
        <td style="width: 45%; text-align: {{ $end }};">
            <div class="title">{{ $t('title') }}</div>
            <table style="margin-top: 6pt;">
                <tr><td class="label" style="text-align: {{ $start }};">{{ $t('number') }}</td><td class="num"><b>{{ $number }}</b></td></tr>
                <tr><td class="label" style="text-align: {{ $start }};">{{ $t('issued_at') }}</td><td class="num">{{ $date($payout->created_at) }}</td></tr>
                <tr><td class="label" style="text-align: {{ $start }};">{{ $t('period_end') }}</td><td class="num">{{ $date($payout->period_end) }}</td></tr>
                <tr><td class="label" style="text-align: {{ $start }};">{{ $t('status') }}</td><td style="text-align: {{ $end }};">{{ $t('statuses.'.$payout->status->value) }}</td></tr>
            </table>
        </td>
    </tr>
</table>

@if ($headerNote)
    <div class="note">{!! $headerNote !!}</div>
@endif

<table style="margin-top: 12pt;">
    <tr>
        <td class="box" style="width: {{ $template->show_bank_details ? '50%' : '100%' }}; text-align: {{ $start }};">
            <div class="label">{{ $t('payee') }}</div>
            <div class="company">{{ $payout->center?->name }}</div>
        </td>
        @if ($template->show_bank_details)
            <td style="width: 2%;"></td>
            <td class="box" style="width: 48%; text-align: {{ $start }};">
                <div class="label">{{ $t('bank_details') }}</div>
                <div>{{ $t('account_name') }}: {{ $payout->account_name ?: '—' }}</div>
                <div>{{ $t('bank_name') }}: {{ $payout->bank_name ?: '—' }}</div>
                <div>{{ $t('iban') }}: <span dir="ltr">{{ $payout->iban ?: '—' }}</span></div>
                @if ($payout->reference)
                    <div>{{ $t('reference') }}: <span dir="ltr">{{ $payout->reference }}</span></div>
                @endif
                @if ($payout->paid_at)
                    <div>{{ $t('paid_at') }}: {{ $date($payout->paid_at) }}</div>
                @endif
            </td>
        @endif
    </tr>
</table>

@if ($template->show_entry_summary && $summary !== [])
    <div class="section">{{ $t('summary') }}</div>
    <table class="grid">
        <thead><tr><th>{{ $t('type') }}</th><th style="text-align: {{ $end }};">{{ $t('count') }}</th><th style="text-align: {{ $end }};">{{ $t('amount') }}</th></tr></thead>
        <tbody>
            @foreach ($summary as $row)
                <tr><td>{{ $t('types.'.$row['type']) }}</td><td class="num">{{ $row['count'] }}</td><td class="num">{{ $money($row['amount']) }}</td></tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($template->show_entries_table && $entries->isNotEmpty())
    <div class="section">{{ $t('entries') }}</div>
    <table class="grid">
        <thead><tr><th>{{ $t('entry') }}</th><th>{{ $t('type') }}</th><th>{{ $t('purchase') }}</th><th>{{ $t('available_at') }}</th><th style="text-align: {{ $end }};">{{ $t('amount') }}</th></tr></thead>
        <tbody>
            @foreach ($entries as $entry)
                <tr>
                    <td dir="ltr">#{{ $entry->id }}</td>
                    <td>{{ $t('types.'.$entry->type->value) }}</td>
                    <td dir="ltr">#{{ $entry->purchase_id }}</td>
                    <td>{{ $date($entry->available_at) }}</td>
                    <td class="num">{{ $money($entry->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<table style="margin-top: 10pt;">
    <tr class="total">
        <td style="text-align: {{ $start }};">{{ $t('total') }}</td>
        <td class="num" style="color: {{ $accent }};">{{ $money($payout->amount) }}</td>
    </tr>
</table>

@if ($footerNote)
    <div class="note">{!! $footerNote !!}</div>
@endif

@if ($template->show_signature)
    <table style="margin-top: 36pt;">
        <tr>
            <td style="width: 45%; border-top: 0.6pt solid #999; padding-top: 4pt; text-align: center;" class="muted">{{ $t('signature') }}</td>
            <td style="width: 10%;"></td>
            <td style="width: 45%; border-top: 0.6pt solid #999; padding-top: 4pt; text-align: center;" class="muted">{{ $t('stamp') }}</td>
        </tr>
    </table>
@endif
</body>
</html>
