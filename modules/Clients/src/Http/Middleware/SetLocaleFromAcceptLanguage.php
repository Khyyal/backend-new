<?php

namespace Modules\Clients\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromAcceptLanguage
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('Accept-Language');

        if (is_string($locale) && $locale !== '') {
            $primary = explode(',', $locale)[0] ?? null;
            if ($primary !== null) {
                $primary = trim(explode(';', $primary)[0] ?? '');
                if (in_array($primary, ['en', 'ar', 'fr'], true)) {
                    app()->setLocale($primary);
                }
            }
        }

        return $next($request);
    }
}
