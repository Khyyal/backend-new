<?php

namespace Modules\Billing\Http\Controllers\Center;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Billing\Enums\PlanStatus;
use Modules\Billing\Exceptions\SubscriptionIneligibleException;
use Modules\Billing\Http\Requests\CancelSubscriptionRequest;
use Modules\Billing\Http\Requests\CreateSubscriptionRequest;
use Modules\Billing\Http\Resources\SubscriptionCheckoutResource;
use Modules\Billing\Http\Resources\SubscriptionCollection;
use Modules\Billing\Http\Resources\SubscriptionResource;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Services\SubscriptionCheckoutService;
use Modules\Billing\Services\SubscriptionLifecycleService;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User as CenterUser;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Center / Subscriptions', 'The center\'s plan subscriptions. Uses the first center the user is assigned to; the account-level token is enough (no center scope).')]
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionLifecycleService $lifecycle,
        private readonly SubscriptionCheckoutService $checkout,
    ) {
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

    private function assertOwner(Subscription $subscription, Center $center): void
    {
        abort_unless(
            $subscription->subscribable_type === $center->getMorphClass()
            && (int) $subscription->subscribable_id === (int) $center->id,
            ResponseAlias::HTTP_FORBIDDEN,
            __('billing::validation.subscribable_owner_mismatch')
        );
    }

    #[Endpoint('Check out a plan', 'A paid plan opens a `pending` subscription with an online payment; pay with `checkout.gateway`, and it becomes `active` once the provider confirms. Unpaid checkouts are released after the hold window. Free plans and plans with a trial start at once with `checkout` null.')]
    #[ApiResponse(['data' => ['id' => 21, 'subscribable_type' => 'center', 'subscribable_id' => 8, 'plan_id' => 1, 'status' => 'pending', 'trial_ends_at' => null, 'starts_at' => '2026-06-01T00:00:00.000000Z', 'ends_at' => '2026-07-01T00:00:00.000000Z', 'canceled_at' => null], 'checkout' => ['purchase' => ['id' => 6001, 'status' => 'pending', 'subtotal' => 49900, 'discount_amount' => 0, 'total_amount' => 49900], 'payment' => ['id' => 901, 'status' => 'pending', 'method' => 'online', 'payment_company' => 'moyasar', 'amount' => 49900], 'gateway' => ['type' => 'moyasar', 'publishable_key' => 'pk_test_xxx', 'amount_halalas' => 49900, 'currency' => 'SAR', 'given_id' => '901', 'description' => 'Khyyal Pro plan']]], 201, 'Subscription opened.')]
    #[ResponseField('data', 'object', 'The subscription (`SubscriptionResource`).')]
    #[ResponseField('checkout', 'object', 'The checkout (`SubscriptionCheckoutResource`); null for free plans and plans with a trial.', nullable: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(['message' => 'No center assignment found for user.'], 403, 'The user has no center assignment.')]
    #[ApiResponse(['message' => 'The selected plan is not active.', 'errors' => ['plan_id' => ['The selected plan is not active.']]], 422, 'Plan not active, already subscribed, or a checkout for it is pending.')]
    public function store(CreateSubscriptionRequest $request): JsonResponse
    {
        $center = $this->center($request);
        $validated = $request->validated();

        $plan = Plan::query()->findOrFail($validated['plan_id']);

        if ($plan->status !== PlanStatus::Active) {
            throw ValidationException::withMessages([
                'plan_id' => [__('billing::validation.plan_not_active')],
            ]);
        }

        try {
            $checkout = $this->checkout->execute($center, $plan, $validated['payment_company'] ?? null);
        } catch (SubscriptionIneligibleException $e) {
            throw ValidationException::withMessages([
                'plan_id' => [$e->getMessage() ?: __('billing::validation.subscription_ineligible')],
            ]);
        }

        return SubscriptionResource::make($checkout->subscription->load('plan'))
            ->additional(['checkout' => $checkout->purchase === null ? null : SubscriptionCheckoutResource::make($checkout)])
            ->response()
            ->setStatusCode(201);
    }

    #[Endpoint('Cancel a subscription', 'Only the owner center; only `active` or `trialing` subscriptions.')]
    #[UrlParam('subscription', 'integer', 'Subscription ID.', example: 21)]
    #[ApiResponse(status: 204, description: 'Canceled. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(['message' => 'This subscription does not belong to your center.'], 403, 'Not your center\'s subscription, or no center assignment.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Subscription not found.')]
    #[ApiResponse(['message' => 'You are not eligible for this subscription at this time.', 'errors' => ['subscription_id' => ['You are not eligible for this subscription at this time.']]], 422, 'Expired or already canceled.')]
    public function destroy(CancelSubscriptionRequest $request, Subscription $subscription)
    {
        $center = $this->center($request);
        $this->assertOwner($subscription, $center);

        $immediately = (bool) ($request->validated()['immediately'] ?? false);

        try {
            $this->lifecycle->cancel($subscription, $immediately);
        } catch (SubscriptionIneligibleException $e) {
            throw ValidationException::withMessages([
                'subscription_id' => [$e->getMessage() ?: __('billing::validation.subscription_ineligible')],
            ]);
        }

        return response()->json(null, 204);
    }

    #[Endpoint('Get the current subscription', 'The active or trialing subscription with its plan features and limits; `data` is null when there is none.')]
    #[ResponseFromApiResource(SubscriptionResource::class, Subscription::class, status: 200, description: 'The current subscription (or `{"data": null}`).')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(['message' => 'No center assignment found for user.'], 403, 'The user has no center assignment.')]
    public function current(Request $request)
    {
        $center = $this->center($request);
        $current = $center->currentSubscription();

        if ($current === null) {
            return response()->json(['data' => null]);
        }

        $current->load(['plan.features', 'plan.limits']);

        return SubscriptionResource::make($current);
    }

    #[Endpoint('List subscriptions', 'Newest start first, 25 per page.')]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(SubscriptionResource::class, Subscription::class, status: 200, description: 'Paginated subscriptions.', paginate: 25)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(['message' => 'No center assignment found for user.'], 403, 'The user has no center assignment.')]
    public function index(Request $request): ResourceCollection
    {
        $center = $this->center($request);

        $subscriptions = $center->subscriptions()
            ->with('plan')
            ->latest('starts_at')
            ->paginate(25);

        return SubscriptionCollection::make($subscriptions);
    }
}
