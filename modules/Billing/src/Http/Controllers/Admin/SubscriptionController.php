<?php

namespace Modules\Billing\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Billing\Exceptions\SubscriptionIneligibleException;
use Modules\Billing\Http\Requests\Admin\ChangeCenterSubscriptionRequest;
use Modules\Billing\Http\Requests\Admin\ListSubscriptionsRequest;
use Modules\Billing\Http\Resources\Admin\AdminSubscriptionResource;
use Modules\Billing\Http\Resources\SubscriptionCheckoutResource;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Services\AdminSubscriptionQuery;
use Modules\Billing\Services\AdminSubscriptionService;
use Modules\Centers\Models\Center;
use Modules\Support\Documentation\ApiExamples;

#[Group('Admin / Subscriptions', 'Center subscriptions across the platform. Requires `subscriptions.view_any` (view) or `subscriptions.change` (change).')]
class SubscriptionController extends Controller
{
    #[Endpoint('List subscriptions', 'Newest first unless `sort` is given, paginated, each with its plan and center.')]
    #[ResponseFromApiResource(AdminSubscriptionResource::class, Subscription::class, status: 200, description: 'Paginated subscriptions.', paginate: 25)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `subscriptions.view_any`.')]
    #[ApiResponse(['message' => 'The selected status is invalid.', 'errors' => ['status' => ['The selected status is invalid.']]], 422, 'Invalid filters.')]
    public function index(ListSubscriptionsRequest $request, AdminSubscriptionQuery $query): AnonymousResourceCollection
    {
        return AdminSubscriptionResource::collection(
            $query->build($request->validated())
                ->paginate($request->integer('per_page', 25))
                ->withQueryString()
        );
    }

    #[Endpoint('Show a subscription', 'With plan features and limits and the center.')]
    #[UrlParam('subscription', 'integer', 'Subscription ID.', example: 21)]
    #[ResponseFromApiResource(AdminSubscriptionResource::class, Subscription::class, status: 200, description: 'The subscription.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `subscriptions.view_any`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such subscription.')]
    public function show(Request $request, int $subscription): AdminSubscriptionResource
    {
        Gate::forUser($request->user())->authorize('viewAny', Subscription::class);

        return AdminSubscriptionResource::make(
            Subscription::query()->with(['plan.features', 'plan.limits', 'subscribable'])->findOrFail($subscription)
        );
    }

    #[Endpoint('Get a center\'s current subscription', 'The active or trialing subscription; `data` is null when the center has none.')]
    #[UrlParam('center', 'integer', 'Center ID.', example: 8)]
    #[ResponseFromApiResource(AdminSubscriptionResource::class, Subscription::class, status: 200, description: 'The current subscription (or `{"data": null}`).')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `subscriptions.view_any`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No center with that ID.')]
    public function current(Request $request, Center $center): AdminSubscriptionResource|JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', Subscription::class);

        $current = $center->currentSubscription();

        if ($current === null) {
            return response()->json(['data' => null]);
        }

        return AdminSubscriptionResource::make($current->load(['plan.features', 'plan.limits', 'subscribable']));
    }

    #[Endpoint('Change a center\'s subscription', '`free`: the current subscription is canceled now and the plan starts `active` with no payment. `required`: a `pending` subscription with an online payment (`checkout`); the current one stays active until the center pays (verify with `POST /api/v1/centers/{center}/payments/{payment}/verify`).')]
    #[UrlParam('center', 'integer', 'Center ID.', example: 8)]
    #[ApiResponse(['data' => ['id' => 21, 'subscribable_type' => 'center', 'subscribable_id' => 8, 'plan_id' => 1, 'status' => 'active', 'trial_ends_at' => null, 'starts_at' => '2026-06-01T00:00:00.000000Z', 'ends_at' => '2026-07-01T00:00:00.000000Z', 'canceled_at' => null, 'center' => ['id' => 8, 'name' => 'Al Fursan Stables', 'slug' => 'al-fursan-stables']], 'checkout' => null], 201, 'Changed or checkout opened.')]
    #[ResponseField('data', 'object', 'The new subscription (`AdminSubscriptionResource`).')]
    #[ResponseField('checkout', 'object', 'Checkout (`SubscriptionCheckoutResource`); only in `required` mode.', nullable: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `subscriptions.change`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No center with that ID.')]
    #[ApiResponse(['message' => 'Only paid plans without a trial can require payment. Use the free mode instead.', 'errors' => ['plan_id' => ['Only paid plans without a trial can require payment. Use the free mode instead.']]], 422, 'Plan not active, already the current plan, or not payable in `required` mode.')]
    public function change(ChangeCenterSubscriptionRequest $request, Center $center, AdminSubscriptionService $service): JsonResponse
    {
        $plan = Plan::query()->findOrFail($request->integer('plan_id'));

        try {
            $result = $service->change(
                $center,
                $plan,
                $request->input('payment_mode') === 'required',
                $request->input('payment_company'),
            );
        } catch (SubscriptionIneligibleException $e) {
            throw ValidationException::withMessages([
                'plan_id' => [$e->getMessage() ?: __('billing::validation.subscription_ineligible')],
            ]);
        }

        return AdminSubscriptionResource::make($result->subscription->load(['plan', 'subscribable']))
            ->additional(['checkout' => $result->purchase === null ? null : SubscriptionCheckoutResource::make($result)])
            ->response()
            ->setStatusCode(201);
    }
}
