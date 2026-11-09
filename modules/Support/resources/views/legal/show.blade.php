<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        :root { color-scheme: light dark; --text: #1f2328; --muted: #59636e; --bg: #ffffff; --link: #0b6bcb; --border: #d1d9e0; }
        @media (prefers-color-scheme: dark) { :root { --text: #e6edf3; --muted: #9198a1; --bg: #0d1117; --link: #4493f8; --border: #3d444d; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 16px/1.7 -apple-system, BlinkMacSystemFont, "Segoe UI", Tahoma, "Noto Sans Arabic", sans-serif; overflow-wrap: break-word; }
        main { max-width: 760px; margin: 0 auto; padding: 24px 16px 48px; }
        h1 { font-size: 1.6rem; line-height: 1.3; margin: 0 0 1rem; }
        h2 { font-size: 1.3rem; margin: 1.8rem 0 .6rem; }
        h3 { font-size: 1.1rem; margin: 1.4rem 0 .5rem; }
        a { color: var(--link); }
        ul, ol { padding-inline-start: 1.4rem; }
        blockquote { margin: 1rem 0; padding-inline-start: 1rem; border-inline-start: 3px solid var(--border); color: var(--muted); }
        table { border-collapse: collapse; width: 100%; display: block; overflow-x: auto; }
        th, td { border: 1px solid var(--border); padding: 6px 10px; text-align: start; }
        img { max-width: 100%; height: auto; }
        hr { border: 0; border-top: 1px solid var(--border); margin: 2rem 0; }
        .empty { color: var(--muted); }
    </style>
</head>
<body>
<main>
    <h1>{{ $title }}</h1>
    @if (filled($content))
        {{-- Sanitized by LegalHtmlSanitizer when saved. --}}
        {!! $content !!}
    @else
        <p class="empty">{{ __('support::legal.empty', locale: $locale) }}</p>
    @endif
</main>
</body>
</html>
