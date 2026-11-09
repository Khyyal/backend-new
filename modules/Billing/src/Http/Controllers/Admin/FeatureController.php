<?php

namespace Modules\Billing\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Billing\Http\Requests\StoreFeatureRequest;
use Modules\Billing\Http\Requests\UpdateFeatureRequest;
use Modules\Billing\Http\Resources\FeatureCollection;
use Modules\Billing\Http\Resources\FeatureResource;
use Modules\Billing\Models\Feature;
use Modules\Support\Documentation\ApiExamples;

#[Group('Admin / Features', 'Reusable plan features (attach them to plans). Viewing requires `plans.view_any`; changes require `plans.manage_catalog`.')]
class FeatureController extends Controller
{
    #[Endpoint('List features', 'Newest first, 50 per page.')]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(FeatureResource::class, Feature::class, status: 200, description: 'Paginated features.', paginate: 50)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `plans.view_any`.')]
    public function index(): ResourceCollection
    {
        return FeatureCollection::make(
            Feature::query()->latest()->paginate(50)
        );
    }

    #[Endpoint('Create a feature')]
    #[ResponseFromApiResource(FeatureResource::class, Feature::class, status: 201, description: 'The created feature.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `plans.manage_catalog`.')]
    #[ApiResponse(['message' => 'The key has already been taken.', 'errors' => ['key' => ['The key has already been taken.']]], 422, 'Validation failed or duplicate key.')]
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

    #[Endpoint('Show a feature')]
    #[UrlParam('feature', 'integer', 'Feature ID.', example: 3)]
    #[ResponseFromApiResource(FeatureResource::class, Feature::class, status: 200, description: 'The feature.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `plans.view_any`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Feature not found.')]
    public function show(Feature $feature): FeatureResource
    {
        return FeatureResource::make($feature);
    }

    #[Endpoint('Update a feature', 'Partial update.')]
    #[UrlParam('feature', 'integer', 'Feature ID.', example: 3)]
    #[ResponseFromApiResource(FeatureResource::class, Feature::class, status: 200, description: 'The updated feature.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `plans.manage_catalog`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Feature not found.')]
    #[ApiResponse(['message' => 'The name field must not be greater than 255 characters.', 'errors' => ['name' => ['The name field must not be greater than 255 characters.']]], 422, 'Validation failed.')]
    public function update(UpdateFeatureRequest $request, Feature $feature): FeatureResource
    {
        $validated = $request->validated();
        if (isset($validated['is_quota'])) {
            $validated['is_quota'] = (bool) $validated['is_quota'];
        }
        $feature->update($validated);

        return FeatureResource::make($feature);
    }

    #[Endpoint('Delete a feature', 'Also removes it from every plan.')]
    #[UrlParam('feature', 'integer', 'Feature ID.', example: 3)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `plans.manage_catalog`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Feature not found.')]
    public function destroy(Feature $feature): JsonResponse
    {
        $feature->delete();

        return response()->json(null, 204);
    }
}
