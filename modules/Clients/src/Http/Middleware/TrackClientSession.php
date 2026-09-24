<?php

namespace Modules\Clients\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackClientSession
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
