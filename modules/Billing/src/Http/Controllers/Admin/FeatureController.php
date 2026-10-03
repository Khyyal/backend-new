<?php

namespace Modules\Billing\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Billing\Http\Requests\StoreFeatureRequest;
use Modules\Billing\Http\Requests\UpdateFeatureRequest;
use Modules\Billing\Http\Resources\FeatureCollection;
use Modules\Billing\Http\Resources\FeatureResource;
use Modules\Billing\Models\Feature;

#[Group(name: 'Admin / Features', description: 'Platform admin: manage reusable feature flags.')]
class FeatureController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    #[Response(status: 200, description: 'Paginated list of features.')]
    #[Response(status: 401, description: 'Unauthenticated.')]
    public function index(): AnonymousResourceCollection
    {
        return FeatureCollection::make(
            Feature::query()->latest()->paginate(50)
        );
    }

    #[Response(status: 201, description: 'Feature created.')]
    #[Response(status: 401, description: 'Unauthenticated.')]
    #[Response(status: 422, description: 'Invalid payload or duplicate key.')]
    public function store(StoreFeatureRequest $request)
    {
        $validated = $request->validated();
        $feature = Feature::query()->create([
            'key' => $validated['key'],
            'name' => $validated['name'],
            'is_quota' => $validated['is_quota'] ?? false,
        ]);

        return FeatureResource::make($feature)->response()->setStatusCode(201);
    }

    #[Response(status: 200, description: 'Feature detail.')]
    #[Response(status: 401, description: 'Unauthenticated.')]
    #[Response(status: 404, description: 'Feature not found.')]
    public function show(Feature $feature): FeatureResource
    {
        return FeatureResource::make($feature);
    }

    #[Response(status: 200, description: 'Feature updated.')]
    #[Response(status: 401, description: 'Unauthenticated.')]
    #[Response(status: 404, description: 'Feature not found.')]
    #[Response(status: 422, description: 'Invalid payload.')]
    public function update(UpdateFeatureRequest $request, Feature $feature): FeatureResource
    {
        $validated = $request->validated();
        if (isset($validated['is_quota'])) {
            $validated['is_quota'] = (bool) $validated['is_quota'];
        }
        $feature->update($validated);

        return FeatureResource::make($feature);
    }

    #[Response(status: 204, description: 'Feature deleted.')]
    #[Response(status: 401, description: 'Unauthenticated.')]
    #[Response(status: 404, description: 'Feature not found.')]
    public function destroy(Feature $feature): JsonResponse
    {
        $feature->delete();

        return response()->json(null, 204);
    }
}
