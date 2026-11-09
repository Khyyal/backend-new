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
use Modules\Billing\Http\Requests\StoreLimitRequest;
use Modules\Billing\Http\Requests\UpdateLimitRequest;
use Modules\Billing\Http\Resources\LimitCollection;
use Modules\Billing\Http\Resources\LimitResource;
use Modules\Billing\Models\Limit;
use Modules\Support\Documentation\ApiExamples;

#[Group('Admin / Limits', 'Reusable plan limits (attach them to plans). Viewing requires `plans.view_any`; changes require `plans.manage_catalog`.')]
class LimitController extends Controller
{
    #[Endpoint('List limits', 'Newest first, 50 per page.')]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(LimitResource::class, Limit::class, status: 200, description: 'Paginated limits.', paginate: 50)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `plans.view_any`.')]
    public function index(): ResourceCollection
    {
        return LimitCollection::make(
            Limit::query()->latest()->paginate(50)
        );
    }

    #[Endpoint('Create a limit')]
    #[ResponseFromApiResource(LimitResource::class, Limit::class, status: 201, description: 'The created limit.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `plans.manage_catalog`.')]
    #[ApiResponse(['message' => 'The key has already been taken.', 'errors' => ['key' => ['The key has already been taken.']]], 422, 'Validation failed or duplicate key.')]
    public function store(StoreLimitRequest $request)
    {
        $limit = Limit::query()->create($request->validated());

        return LimitResource::make($limit)->response()->setStatusCode(201);
    }

    #[Endpoint('Show a limit')]
    #[UrlParam('limit', 'integer', 'Limit ID.', example: 2)]
    #[ResponseFromApiResource(LimitResource::class, Limit::class, status: 200, description: 'The limit.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `plans.view_any`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Limit not found.')]
    public function show(Limit $limit): LimitResource
    {
        return LimitResource::make($limit);
    }

    #[Endpoint('Update a limit', 'Partial update.')]
    #[UrlParam('limit', 'integer', 'Limit ID.', example: 2)]
    #[ResponseFromApiResource(LimitResource::class, Limit::class, status: 200, description: 'The updated limit.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `plans.manage_catalog`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Limit not found.')]
    #[ApiResponse(['message' => 'The name field must not be greater than 255 characters.', 'errors' => ['name' => ['The name field must not be greater than 255 characters.']]], 422, 'Validation failed.')]
    public function update(UpdateLimitRequest $request, Limit $limit): LimitResource
    {
        $limit->update($request->validated());

        return LimitResource::make($limit);
    }

    #[Endpoint('Delete a limit', 'Also removes it from every plan.')]
    #[UrlParam('limit', 'integer', 'Limit ID.', example: 2)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `plans.manage_catalog`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Limit not found.')]
    public function destroy(Limit $limit): JsonResponse
    {
        $limit->delete();

        return response()->json(null, 204);
    }
}
