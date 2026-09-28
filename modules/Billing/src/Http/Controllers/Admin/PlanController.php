<?php

namespace Modules\Billing\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Enums\PlanStatus;
use Modules\Billing\Events\PlanCreated;
use Modules\Billing\Http\Requests\AttachFeaturesRequest;
use Modules\Billing\Http\Requests\AttachLimitsRequest;
use Modules\Billing\Http\Requests\StorePlanRequest;
use Modules\Billing\Http\Requests\UpdatePlanRequest;
use Modules\Billing\Http\Requests\UpdatePlanStatusRequest;
use Modules\Billing\Http\Resources\PlanCollection;
use Modules\Billing\Http\Resources\PlanResource;
use Modules\Billing\Models\Feature;
use Modules\Billing\Models\Limit;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PlanFeature;
use Modules\Billing\Models\PlanLimit;

#[Group(name: 'Admin / Plans', description: 'Platform admin: manage subscription plans, features, and limits.')]
class PlanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    #[Response(status: 200, description: 'Paginated list of plans with feature, limit, and subscription counts.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Plan::query()
            ->withCount(['features', 'limits', 'subscriptions']);

        $this->applyFilters($query, $request);

        return PlanCollection::make($query->latest()->paginate(25));
    }

    #[Response(status: 201, description: 'Plan created successfully.')]
    #[Response(status: 401, description: 'Unauthenticated.')]
    #[Response(status: 422, description: 'Invalid payload.')]
    public function store(StorePlanRequest $request)
    {
        $validated = $request->validated();

        $slug = $this->resolveSlug($validated);

        $plan = DB::transaction(function () use ($validated, $slug) {
            $plan = Plan::query()->create(array_merge($validated, [
                'slug' => $slug,
                'status' => $validated['status'] ?? PlanStatus::Active,
                'trial_days' => $validated['trial_days'] ?? 0,
            ]));

            DB::afterCommit(fn () => event(new PlanCreated($plan)));

            return $plan;
        });

        $plan->loadCount(['features', 'limits', 'subscriptions']);

        return PlanResource::make($plan)->response()->setStatusCode(201);
    }

    #[Response(status: 200, description: 'Plan detail with features, limits, and counts.')]
    #[Response(status: 401, description: 'Unauthenticated.')]
    #[Response(status: 404, description: 'Plan not found.')]
    public function show(Plan $plan): PlanResource
    {
        $plan->load(['features', 'limits']);
        $plan->loadCount(['features', 'limits', 'subscriptions']);

        return PlanResource::make($plan);
    }

    #[Response(status: 200, description: 'Plan updated.')]
    #[Response(status: 401, description: 'Unauthenticated.')]
    #[Response(status: 404, description: 'Plan not found.')]
    #[Response(status: 422, description: 'Invalid payload.')]
    public function update(UpdatePlanRequest $request, Plan $plan): PlanResource
    {
        $validated = $request->validated();

        if (isset($validated['slug'])) {
            $validated['slug'] = $this->resolveSlugOnUpdate($plan, $validated['slug']);
        }

        $plan->update($validated);
        $plan->loadCount(['features', 'limits', 'subscriptions']);

        return PlanResource::make($plan);
    }

    #[Response(status: 204, description: 'Plan deleted.')]
    #[Response(status: 401, description: 'Unauthenticated.')]
    #[Response(status: 404, description: 'Plan not found.')]
    #[Response(status: 422, description: 'Plan has subscriptions and cannot be deleted.')]
    public function destroy(Plan $plan): JsonResponse
    {
        if ($plan->subscriptions()->exists()) {
            throw ValidationException::withMessages([
                'plan_id' => [__('billing::validation.plan_has_subscriptions')],
            ]);
        }

        $plan->delete();

        return response()->json(null, 204);
    }

    #[Response(status: 200, description: 'Plan status updated.')]
    #[Response(status: 401, description: 'Unauthenticated.')]
    #[Response(status: 422, description: 'Invalid status value.')]
    public function updateStatus(UpdatePlanStatusRequest $request, Plan $plan): PlanResource
    {
        $plan->update(['status' => $request->validated()['status']]);
        $plan->loadCount(['features', 'limits', 'subscriptions']);

        return PlanResource::make($plan);
    }

    // ---------- Nested Plan Features ----------

    #[Group(name: 'Admin / Plan Features')]
    #[Response(status: 201, description: 'Plan features attached (idempotent upsert).')]
    #[Response(status: 422, description: 'Invalid feature IDs.')]
    public function attachFeatures(AttachFeaturesRequest $request, Plan $plan): JsonResponse
    {
        $items = $request->validated()['items'];
        $now = now();
        $upsert = [];

        foreach ($items as $item) {
            $upsert[] = [
                'plan_id' => $plan->id,
                'feature_id' => (int) $item['feature_id'],
                'enabled' => (bool) ($item['enabled'] ?? true),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (count($upsert) > 0) {
            PlanFeature::query()->upsert(
                $upsert,
                ['plan_id', 'feature_id'],
                ['enabled', 'updated_at']
            );
        }

        return response()->json([
            'attached' => count($items),
            'feature_count' => $plan->planFeatures()->count(),
        ], 201);
    }

    #[Group(name: 'Admin / Plan Features')]
    #[Response(status: 204, description: 'Plan feature detached.')]
    #[Response(status: 404, description: 'Feature is not attached to the plan.')]
    public function detachFeature(Plan $plan, Feature $feature): JsonResponse
    {
        $detached = PlanFeature::query()
            ->where('plan_id', $plan->id)
            ->where('feature_id', $feature->id)
            ->delete();

        if ($detached === 0) {
            abort(404);
        }

        return response()->json(null, 204);
    }

    // ---------- Nested Plan Limits ----------

    #[Group(name: 'Admin / Plan Limits')]
    #[Response(status: 201, description: 'Plan limits attached (idempotent upsert).')]
    #[Response(status: 422, description: 'Invalid limit IDs or missing values.')]
    public function attachLimits(AttachLimitsRequest $request, Plan $plan): JsonResponse
    {
        $items = $request->validated()['items'];
        $now = now();
        $upsert = [];

        foreach ($items as $item) {
            $upsert[] = [
                'plan_id' => $plan->id,
                'limit_id' => (int) $item['limit_id'],
                'value' => (int) $item['value'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (count($upsert) > 0) {
            PlanLimit::query()->upsert(
                $upsert,
                ['plan_id', 'limit_id'],
                ['value', 'updated_at']
            );
        }

        return response()->json([
            'attached' => count($items),
            'limit_count' => $plan->planLimits()->count(),
        ], 201);
    }

    #[Group(name: 'Admin / Plan Limits')]
    #[Response(status: 204, description: 'Plan limit detached.')]
    #[Response(status: 404, description: 'Limit is not attached to the plan.')]
    public function detachLimit(Plan $plan, Limit $limit): JsonResponse
    {
        $detached = PlanLimit::query()
            ->where('plan_id', $plan->id)
            ->where('limit_id', $limit->id)
            ->delete();

        if ($detached === 0) {
            abort(404);
        }

        return response()->json(null, 204);
    }

    // ---------- helpers ----------

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($interval = $request->input('billing_interval')) {
            $query->where('billing_interval', $interval);
        }
    }

    private function resolveSlug(array $validated): string
    {
        if (isset($validated['slug']) && $validated['slug'] !== null && trim($validated['slug']) !== '') {
            return $this->dedupeSlug(trim($validated['slug']));
        }

        $base = Str::slug($validated['name']['en'] ?? 'plan');

        return $this->dedupeSlug($base);
    }

    private function resolveSlugOnUpdate(Plan $plan, string $newSlug): string
    {
        $trimmed = trim($newSlug);
        $existing = Plan::query()->where('slug', $trimmed)->where('id', '!=', $plan->id)->exists();
        if (! $existing) {
            return $trimmed;
        }

        return $this->dedupeSlug($trimmed);
    }

    private function dedupeSlug(string $base): string
    {
        $base = trim($base);
        if ($base === '') {
            $base = 'plan';
        }

        $slug = $base;
        $counter = 1;
        while (Plan::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
