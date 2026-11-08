<?php

namespace Modules\Billing\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
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
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Center / Subscriptions', description: 'Authenticated center user: manage center subscriptions.')]
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

    /**
     * Check out a plan.
     *
     * **Request body:** `plan_id` (required), `payment_company` (optional, defaults to the default gateway).
     * A paid plan opens a `pending` subscription with an online payment; `checkout.gateway` holds what is needed
     * to pay, and the subscription becomes `active` once the payment succeeds. Unpaid checkouts are released after
     * the hold window. Free plans and plans with a trial start right away and `checkout` is null.
     *
     * **`checkout.gateway`** is what the app needs to pay; its shape depends on `payment_company`
     * (`moyasar`, `tamara`, or the default gateway; `fake` exists only in development and tests).
     * Amounts are integer halalas. The app never decides the outcome: the status changes only
     * after the payment provider confirms the payment to the backend.
     *
     * - `moyasar` (card, paid inside the app with the Moyasar SDK, no redirect from us):
     *   `{type: "moyasar", publishable_key, amount_halalas, currency, given_id, description}`.
     *   Create the Moyasar payment with `publishable_key`, `amount_halalas` and `currency` as given, and
     *   send `given_id` unchanged as the payment's `given_id`: it is our payment id and links the
     *   Moyasar payment to this checkout. `description` is the text to show. Moyasar returns a
     *   `transaction_url` for 3-D Secure; open it when present.
     * - `tamara` (installments, paid on Tamara's page): `{checkout_url, provider_reference, checkout_id, tamara_status}`.
     *   Open `checkout_url` in a browser or web view. `provider_reference` is the Tamara order id,
     *   `checkout_id` the Tamara checkout id, `tamara_status` the order status when created (`new`).
     *   Tamara sends the customer back to the success, failure or cancel URL configured for the app.
     * - `fake` (testing only): `{checkout_url, provider_reference}`, a placeholder that never charges.
     *
     * `checkout.payment.payment_company` repeats the gateway used. `checkout.gateway` is null when no
     * online payment is needed.
     */
    #[Response(status: 201, description: '`{data: SubscriptionResource, checkout: SubscriptionCheckoutResource|null}`.')]
    #[Response(status: 401, description: 'Unauthenticated.')]
    #[Response(status: 403, description: 'No center assignment.')]
    #[Response(status: 422, description: 'Plan not active, already subscribed, or a checkout for it is pending.')]
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

    #[Response(status: 204, description: 'Subscription canceled.')]
    #[Response(status: 401, description: 'Unauthenticated.')]
    #[Response(status: 403, description: 'Subscription does not belong to center or no center assignment.')]
    #[Response(status: 404, description: 'Subscription not found.')]
    #[Response(status: 422, description: 'Subscription cannot be canceled (expired or already canceled).')]
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

    #[Response(status: 200, description: 'Current active/trialing subscription for the center.')]
    #[Response(status: 401, description: 'Unauthenticated.')]
    #[Response(status: 403, description: 'No center assignment.')]
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

    #[Response(status: 200, description: 'All subscriptions (paginated) for the center.')]
    #[Response(status: 401, description: 'Unauthenticated.')]
    #[Response(status: 403, description: 'No center assignment.')]
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
