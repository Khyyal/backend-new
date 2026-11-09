<?php

namespace Modules\Billing\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Billing\Enums\BillingInterval;
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
use Modules\Support\Documentation\ApiExamples;

#[Group('Admin / Plans', 'Subscription plans and the features and limits attached to them. Requires the matching `plans.*` permission.')]
class PlanController extends Controller
{
    #[Endpoint('List plans', 'Newest first, 25 per page, with feature, limit and subscription counts.')]
    #[QueryParam('status', 'string', 'Only this status.', required: false, enum: PlanStatus::class, example: 'active')]
    #[QueryParam('billing_interval', 'string', 'Only this interval.', required: false, enum: BillingInterval::class, example: 'monthly')]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(PlanResource::class, Plan::class, status: 200, description: 'Paginated plans.', paginate: 25, withCount: ['features', 'limits', 'subscriptions'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `plans.view_any`.')]
    public function index(Request $request): ResourceCollection
    {
        $query = Plan::query()
            ->withCount(['features', 'limits', 'subscriptions']);

        $this->applyFilters($query, $request);

        return PlanCollection::make($query->latest()->paginate(25));
    }

    #[Endpoint('Create a plan', 'Status defaults to `active`, trial to 0 days.')]
    #[ResponseFromApiResource(PlanResource::class, Plan::class, status: 201, description: 'The created plan.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `plans.create`.')]
    #[ApiResponse(['message' => 'The name.en field is required.', 'errors' => ['name.en' => ['The name.en field is required.']]], 422, 'Validation failed.')]
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

    #[Endpoint('Show a plan', 'With features, limits and counts.')]
    #[UrlParam('plan', 'integer', 'Plan ID.', example: 1)]
    #[ResponseFromApiResource(PlanResource::class, Plan::class, status: 200, description: 'The plan.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `plans.view_any`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Plan not found.')]
    public function show(Plan $plan): PlanResource
    {
        $plan->load(['features', 'limits']);
        $plan->loadCount(['features', 'limits', 'subscriptions']);

        return PlanResource::make($plan);
    }

    #[Endpoint('Update a plan', 'Partial update.')]
    #[UrlParam('plan', 'integer', 'Plan ID.', example: 1)]
    #[ResponseFromApiResource(PlanResource::class, Plan::class, status: 200, description: 'The updated plan.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `plans.update`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Plan not found.')]
    #[ApiResponse(['message' => 'The name.en field is required.', 'errors' => ['name.en' => ['The name.en field is required.']]], 422, 'Validation failed.')]
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

    #[Endpoint('Delete a plan', 'Refused while the plan has subscriptions; deactivate it instead.')]
    #[UrlParam('plan', 'integer', 'Plan ID.', example: 1)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `plans.delete`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Plan not found.')]
    #[ApiResponse(['message' => 'This plan cannot be deleted because it has active subscriptions.', 'errors' => ['plan_id' => ['This plan cannot be deleted because it has active subscriptions.']]], 422, 'The plan has subscriptions.')]
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

    #[Endpoint('Change a plan status')]
    #[UrlParam('plan', 'integer', 'Plan ID.', example: 1)]
    #[ResponseFromApiResource(PlanResource::class, Plan::class, status: 200, description: 'The updated plan.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `plans.update`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Plan not found.')]
    #[ApiResponse(['message' => 'The selected status is invalid.', 'errors' => ['status' => ['The selected status is invalid.']]], 422, 'Invalid status.')]
    public function updateStatus(UpdatePlanStatusRequest $request, Plan $plan): PlanResource
    {
        $plan->update(['status' => $request->validated()['status']]);
        $plan->loadCount(['features', 'limits', 'subscriptions']);

        return PlanResource::make($plan);
    }

    // ---------- Nested Plan Features ----------

    #[Group('Admin / Plan features', 'Features attached to a plan. Requires `plans.update`.')]
    #[Endpoint('Attach features to a plan', 'Idempotent upsert: existing ones get the new `enabled` value.')]
    #[UrlParam('plan', 'integer', 'Plan ID.', example: 1)]
    #[ApiResponse(['attached' => 2, 'feature_count' => 4], 201, 'Attached.')]
    #[ResponseField('attached', 'integer', 'Items processed.', example: 2)]
    #[ResponseField('feature_count', 'integer', 'Features now on the plan.', example: 4)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `plans.update`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Plan not found.')]
    #[ApiResponse(['message' => 'The selected items.0.feature_id is invalid.', 'errors' => ['items.0.feature_id' => ['The selected items.0.feature_id is invalid.']]], 422, 'Unknown feature IDs.')]
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

    #[Group('Admin / Plan features', 'Features attached to a plan. Requires `plans.update`.')]
    #[Endpoint('Detach a feature from a plan')]
    #[UrlParam('plan', 'integer', 'Plan ID.', example: 1)]
    #[UrlParam('feature', 'integer', 'Feature ID.', example: 3)]
    #[ApiResponse(status: 204, description: 'Detached. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `plans.update`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Plan or feature not found, or the feature is not attached.')]
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

    #[Group('Admin / Plan limits', 'Limits attached to a plan. Requires `plans.update`.')]
    #[Endpoint('Attach limits to a plan', 'Idempotent upsert: existing ones get the new `value`.')]
    #[UrlParam('plan', 'integer', 'Plan ID.', example: 1)]
    #[ApiResponse(['attached' => 1, 'limit_count' => 2], 201, 'Attached.')]
    #[ResponseField('attached', 'integer', 'Items processed.', example: 1)]
    #[ResponseField('limit_count', 'integer', 'Limits now on the plan.', example: 2)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `plans.update`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Plan not found.')]
    #[ApiResponse(['message' => 'The items.0.value field is required.', 'errors' => ['items.0.value' => ['The items.0.value field is required.']]], 422, 'Unknown limit IDs or missing values.')]
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

    #[Group('Admin / Plan limits', 'Limits attached to a plan. Requires `plans.update`.')]
    #[Endpoint('Detach a limit from a plan')]
    #[UrlParam('plan', 'integer', 'Plan ID.', example: 1)]
    #[UrlParam('limit', 'integer', 'Limit ID.', example: 2)]
    #[ApiResponse(status: 204, description: 'Detached. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `plans.update`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Plan or limit not found, or the limit is not attached.')]
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
