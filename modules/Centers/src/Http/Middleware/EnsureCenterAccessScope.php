<?php

namespace Modules\Centers\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Centers\Models\Center;
use Modules\Centers\Services\CenterAuthService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

/**
 * Guards center-scoped endpoints: the token must carry the center's access
 * ability, and the center must be active. `centers.show` stays reachable for a
 * pending or blocked center so its users can see its `state`.
 */
class EnsureCenterAccessScope
{
    private const ROUTES_ALLOWED_WHEN_INACTIVE = ['centers.show'];

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

        if (! $center->isActive() && ! $request->routeIs(...self::ROUTES_ALLOWED_WHEN_INACTIVE)) {
            abort(ResponseAlias::HTTP_FORBIDDEN, __('centers::auth.center_not_active', [
                'state' => __('centers::auth.center_states.'.$center->state->value),
            ]));
        }

        return $next($request);
    }
}
