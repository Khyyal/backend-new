<?php

namespace Modules\Billing\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Enums\PlanStatus;
use Modules\Billing\Exceptions\SubscriptionIneligibleException;
use Modules\Billing\Http\Requests\CancelSubscriptionRequest;
use Modules\Billing\Http\Requests\CreateSubscriptionRequest;
use Modules\Billing\Http\Resources\SubscriptionCollection;
use Modules\Billing\Http\Resources\SubscriptionResource;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Services\SubscriptionLifecycleService;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User as CenterUser;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Center / Subscriptions', description: 'Authenticated center user: manage center subscriptions.')]
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionLifecycleService $lifecycle,
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

    #[Response(status: 201, description: 'Center subscribed to the plan.')]
    #[Response(status: 401, description: 'Unauthenticated.')]
    #[Response(status: 403, description: 'No center assignment.')]
    #[Response(status: 422, description: 'Plan not active or already subscribed.')]
    public function store(CreateSubscriptionRequest $request)
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
            $subscription = $this->lifecycle->subscribe($center, $plan);
        } catch (SubscriptionIneligibleException $e) {
            throw ValidationException::withMessages([
                'plan_id' => [$e->getMessage() ?: __('billing::validation.subscription_ineligible')],
            ]);
        }

        $subscription->load('plan');

        return SubscriptionResource::make($subscription)->response()->setStatusCode(201);
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
    public function index(Request $request): AnonymousResourceCollection
    {
        $center = $this->center($request);

        $subscriptions = $center->subscriptions()
            ->with('plan')
            ->latest('starts_at')
            ->paginate(25);

        return SubscriptionCollection::make($subscriptions);
    }
}
