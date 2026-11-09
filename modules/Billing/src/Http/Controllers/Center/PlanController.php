<?php

namespace Modules\Billing\Http\Controllers\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Billing\Enums\PlanStatus;
use Modules\Billing\Http\Resources\PlanCollection;
use Modules\Billing\Http\Resources\PlanResource;
use Modules\Billing\Models\Plan;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User as CenterUser;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Center / Plans', 'The catalog of active plans a center can subscribe to. Uses the first center the user is assigned to; the account-level token is enough (no center scope).')]
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

    #[Endpoint('List active plans', 'Newest first, 25 per page, with features and limits.')]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(PlanResource::class, Plan::class, status: 200, description: 'Paginated plans.', paginate: 25)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(['message' => 'No center assignment found for user.'], 403, 'The user has no center assignment.')]
    public function index(Request $request): ResourceCollection
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

    #[Endpoint('Show an active plan', 'With features and limits.')]
    #[UrlParam('plan', 'integer', 'Plan ID.', example: 1)]
    #[ResponseFromApiResource(PlanResource::class, Plan::class, status: 200, description: 'The plan.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(['message' => 'No center assignment found for user.'], 403, 'The user has no center assignment.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Plan not found or not active.')]
    public function show(Request $request, Plan $plan): PlanResource
    {
        $this->center($request);

        abort_if($plan->status !== PlanStatus::Active, ResponseAlias::HTTP_NOT_FOUND);

        $plan->load(['features', 'limits']);
        $plan->loadCount(['features', 'limits', 'subscriptions']);

        return PlanResource::make($plan);
    }
}
