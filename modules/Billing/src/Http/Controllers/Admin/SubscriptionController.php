<?php

namespace Modules\Billing\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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

#[Group(name: 'Admin / Subscriptions', description: 'Center subscriptions across the platform. Requires a system user Bearer token with the `subscriptions.view_any` (view) or `subscriptions.change` (change) permission.')]
class SubscriptionController extends Controller
{
    /**
     * List subscriptions.
     *
     * Paginated (`per_page`, default 25, max 100), newest first, each with its `plan` and `center`.
     * **Query** (all optional): `center_id`, `plan_id`, `status`, `from`/`to` (`Y-m-d`, on the start date).
     */
    #[Response(status: 200, description: 'Paginated `AdminSubscriptionResource` collection.')]
    #[Response(status: 403, description: 'Missing the `subscriptions.view_any` permission.')]
    public function index(ListSubscriptionsRequest $request, AdminSubscriptionQuery $query): AnonymousResourceCollection
    {
        return AdminSubscriptionResource::collection(
            $query->build($request->validated())
                ->paginate($request->integer('per_page', 25))
                ->withQueryString()
        );
    }

    /**
     * Show a subscription.
     */
    #[Response(status: 200, description: 'The `AdminSubscriptionResource`.')]
    #[Response(status: 403, description: 'Missing the `subscriptions.view_any` permission.')]
    #[Response(status: 404, description: 'No such subscription.')]
    public function show(Request $request, int $subscription): AdminSubscriptionResource
    {
        Gate::forUser($request->user())->authorize('viewAny', Subscription::class);

        return AdminSubscriptionResource::make(
            Subscription::query()->with(['plan.features', 'plan.limits', 'subscribable'])->findOrFail($subscription)
        );
    }

    /**
     * A center's current subscription.
     *
     * The active or trialing subscription, or `data: null` when the center has none.
     */
    #[Response(status: 200, description: 'The `AdminSubscriptionResource` or `{data: null}`.')]
    #[Response(status: 403, description: 'Missing the `subscriptions.view_any` permission.')]
    public function current(Request $request, Center $center): AdminSubscriptionResource|JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', Subscription::class);

        $current = $center->currentSubscription();

        if ($current === null) {
            return response()->json(['data' => null]);
        }

        return AdminSubscriptionResource::make($current->load(['plan.features', 'plan.limits', 'subscribable']));
    }

    /**
     * Change a center's subscription.
     *
     * **Body:** `plan_id` and `payment_mode`.
     * - `free`: the current subscription is canceled now and the new plan starts as `active` immediately, with no payment.
     * - `required`: a `pending` subscription is opened with an online payment (`checkout`, same shape as the center checkout;
     *   `payment_company` optional). The current subscription stays active until the center pays, then it is replaced.
     *   Only paid plans without a trial are allowed in this mode.
     */
    #[Response(status: 201, description: '`{data: AdminSubscriptionResource, checkout: SubscriptionCheckoutResource|null}`; `checkout` is set only in `required` mode.')]
    #[Response(status: 403, description: 'Missing the `subscriptions.change` permission.')]
    #[Response(status: 422, description: 'Plan not active, same plan already current, or not payable in `required` mode.')]
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
