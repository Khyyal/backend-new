<?php

namespace Modules\Billing\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Billing\Enums\PlanStatus;
use Modules\Billing\Http\Resources\PlanCollection;
use Modules\Billing\Http\Resources\PlanResource;
use Modules\Billing\Models\Plan;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User as CenterUser;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Center / Plans', description: 'Authenticated center user: browse active plans (catalog).')]
class PlanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:center_user');
    }

    private function center(Request $request): Center
    {
        /** @var CenterUser $user */
        $user = $request->user('center_user');

        $center = $user->centers()->first();
        abort_if($center === null, ResponseAlias::HTTP_FORBIDDEN, 'No center assignment found for user.');

        return $center;
    }

    #[Response(status: 200, description: 'Active plan catalog with features and limits.')]
    #[Response(status: 401, description: 'Missing or invalid center_user Bearer token.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->center($request);

        $plans = Plan::query()
            ->where('status', PlanStatus::Active)
            ->with(['features', 'limits'])
            ->withCount(['features', 'limits', 'subscriptions'])
            ->latest()
            ->paginate(25);

        return PlanCollection::make($plans);
    }

    #[Response(status: 200, description: 'Active plan detail with features and limits.')]
    #[Response(status: 401, description: 'Unauthenticated.')]
    #[Response(status: 404, description: 'Plan not found or not active.')]
    public function show(Request $request, Plan $plan): PlanResource
    {
        $this->center($request);

        abort_if($plan->status !== PlanStatus::Active, ResponseAlias::HTTP_NOT_FOUND);

        $plan->load(['features', 'limits']);
        $plan->loadCount(['features', 'limits', 'subscriptions']);

        return PlanResource::make($plan);
    }
}
