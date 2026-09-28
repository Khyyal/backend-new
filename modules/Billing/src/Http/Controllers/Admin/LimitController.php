<?php

namespace Modules\Billing\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Billing\Http\Requests\StoreLimitRequest;
use Modules\Billing\Http\Requests\UpdateLimitRequest;
use Modules\Billing\Http\Resources\LimitCollection;
use Modules\Billing\Http\Resources\LimitResource;
use Modules\Billing\Models\Limit;

#[Group(name: 'Admin / Limits', description: 'Platform admin: manage reusable numeric quota limit keys.')]
class LimitController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    #[Response(status: 200, description: 'Paginated list of limits.')]
    #[Response(status: 401, description: 'Unauthenticated.')]
    public function index(): AnonymousResourceCollection
    {
        return LimitCollection::make(
            Limit::query()->latest()->paginate(50)
        );
    }

    #[Response(status: 201, description: 'Limit created.')]
    #[Response(status: 401, description: 'Unauthenticated.')]
    #[Response(status: 422, description: 'Invalid payload or duplicate key.')]
    public function store(StoreLimitRequest $request)
    {
        $limit = Limit::query()->create($request->validated());

        return LimitResource::make($limit)->response()->setStatusCode(201);
    }

    #[Response(status: 200, description: 'Limit detail.')]
    #[Response(status: 401, description: 'Unauthenticated.')]
    #[Response(status: 404, description: 'Limit not found.')]
    public function show(Limit $limit): LimitResource
    {
        return LimitResource::make($limit);
    }

    #[Response(status: 200, description: 'Limit updated.')]
    #[Response(status: 401, description: 'Unauthenticated.')]
    #[Response(status: 404, description: 'Limit not found.')]
    #[Response(status: 422, description: 'Invalid payload.')]
    public function update(UpdateLimitRequest $request, Limit $limit): LimitResource
    {
        $limit->update($request->validated());

        return LimitResource::make($limit);
    }

    #[Response(status: 204, description: 'Limit deleted.')]
    #[Response(status: 401, description: 'Unauthenticated.')]
    #[Response(status: 404, description: 'Limit not found.')]
    public function destroy(Limit $limit): JsonResponse
    {
        $limit->delete();

        return response()->json(null, 204);
    }
}
