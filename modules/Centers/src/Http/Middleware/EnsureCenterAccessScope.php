<?php

namespace Modules\Centers\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Centers\Models\Center;
use Modules\Centers\Services\CenterAuthService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class EnsureCenterAccessScope
{
    public function handle(Request $request, Closure $next): ResponseAlias
    {
        $center = $request->route('center');

        if ($center === null) {
            abort(ResponseAlias::HTTP_INTERNAL_SERVER_ERROR, 'Center route parameter is required.');
        }

        if (! $center instanceof Center) {
            $center = Center::query()->findOrFail($center);
        }

        $requiredAbility = CenterAuthService::centerAccessAbilityFor($center->id);

        $token = $request->user('center_user')?->currentAccessToken();

        if ($token !== null && ! $token->can($requiredAbility)) {
            abort(ResponseAlias::HTTP_FORBIDDEN, __('centers::auth.center_access_scope_required'));
        }

        return $next($request);
    }
}
