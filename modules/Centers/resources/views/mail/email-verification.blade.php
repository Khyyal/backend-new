<div dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" style="font-family: sans-serif">
    <p>{{ __('centers::email_verification.mail.greeting') }}</p>
    <p>{{ __('centers::email_verification.mail.line', ['center' => $centerName, 'minutes' => $ttlMinutes]) }}</p>
    <p style="font-size: 28px; letter-spacing: 6px; font-weight: bold">{{ $code }}</p>
    <p>{{ __('centers::email_verification.mail.ignore') }}</p>
</div>
