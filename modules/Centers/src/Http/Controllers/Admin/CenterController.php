<?php

namespace Modules\Centers\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Exports\CentersExport;
use Modules\Centers\Http\Requests\Admin\ExportCentersRequest;
use Modules\Centers\Http\Requests\Admin\ListCentersRequest;
use Modules\Centers\Http\Requests\Admin\StoreCenterRequest;
use Modules\Centers\Http\Requests\Admin\UpdateCenterRequest;
use Modules\Centers\Http\Requests\Admin\UpdateCenterSiteThemeRequest;
use Modules\Centers\Http\Requests\Admin\UpdateCenterSocialLinksRequest;
use Modules\Centers\Http\Resources\Center\CenterResource;
use Modules\Centers\Models\Center;
use Modules\Centers\Services\CenterAdminService;
use Modules\Exports\Enums\ExportType;
use Modules\Exports\Http\Resources\ExportResource;
use Modules\Exports\Models\Export;
use Modules\Exports\Services\ExportService;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Admin / Centers', 'Centers as managed by system users. Requires the matching `centers.*` permission.')]
class CenterController extends Controller
{
    public function __construct(private readonly CenterAdminService $service) {}

    #[Endpoint('List centers', 'Paginated, newest first unless `sort` is given. Filters and sort are query parameters. Deleted centers are left out unless `trashed` is given.')]
    #[ResponseFromApiResource(CenterResource::class, Center::class, status: 200, description: 'Paginated centers.', paginate: 15, with: ['city'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `centers.view_any`.')]
    public function index(ListCentersRequest $request): AnonymousResourceCollection
    {
        return CenterResource::collection(
            $this->service->paginate($request->validated(), $request->integer('per_page', 15))
        );
    }

    #[Endpoint('Create a center', 'Also creates its primary (owner) user. `status` defaults to `invisible`.')]
    #[ResponseFromApiResource(CenterResource::class, Center::class, status: 201, description: 'The created center.', with: ['city', 'tags'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `centers.create`.')]
    #[ApiResponse(['message' => 'The primary user.phone has already been taken.', 'errors' => ['primary_user.phone' => ['The primary user.phone has already been taken.']]], 422, 'Validation failed (e.g. `primary_user.phone` in use), or a media UUID is invalid.')]
    public function store(StoreCenterRequest $request): JsonResponse
    {
        $center = $this->service->create($request->user(), $request->validated());

        return CenterResource::make($center)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Endpoint('Show a center', 'With city, tags and media. Also returns a deleted center (`deleted_at` is set).')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(CenterResource::class, Center::class, status: 200, description: 'The center.', with: ['city', 'tags'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `centers.view`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No center with that ID.')]
    public function show(Request $request, Center $center): CenterResource
    {
        Gate::forUser($request->user())->authorize('view', $center);

        return CenterResource::make($this->service->find($center));
    }

    #[Endpoint('Update a center', 'Partial update; same fields as create except `primary_user`, plus `points`. Social links and the site theme have their own endpoints.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(CenterResource::class, Center::class, status: 200, description: 'The updated center.', with: ['city', 'tags'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `centers.update`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No center with that ID.')]
    #[ApiResponse(['message' => 'The domain has already been taken.', 'errors' => ['domain' => ['The domain has already been taken.']]], 422, 'Validation failed, or a media UUID is invalid.')]
    public function update(UpdateCenterRequest $request, Center $center): CenterResource
    {
        return CenterResource::make($this->service->update($request->user(), $center, $request->validated()));
    }

    #[Endpoint('Update a center\'s social links', 'Replaces all links.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(CenterResource::class, Center::class, status: 200, description: 'The updated center.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `centers.update`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No center with that ID.')]
    #[ApiResponse(['message' => 'The social_links.x field must not be greater than 255 characters.', 'errors' => ['social_links.x' => ['The social_links.x field must not be greater than 255 characters.']]], 422, 'Unknown platform or value too long.')]
    public function updateSocialLinks(UpdateCenterSocialLinksRequest $request, Center $center): CenterResource
    {
        return CenterResource::make($this->service->update($request->user(), $center, $request->validated()));
    }

    #[Endpoint('Update a center\'s site theme', 'Replaces the theme.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(CenterResource::class, Center::class, status: 200, description: 'The updated center.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `centers.update`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No center with that ID.')]
    #[ApiResponse(['message' => 'The site_theme.primary field must be a valid hexadecimal color.', 'errors' => ['site_theme.primary' => ['The site_theme.primary field must be a valid hexadecimal color.']]], 422, 'Unknown color role or invalid hex color.')]
    public function updateSiteTheme(UpdateCenterSiteThemeRequest $request, Center $center): CenterResource
    {
        return CenterResource::make($this->service->update($request->user(), $center, $request->validated()));
    }

    #[Endpoint('Delete a center', 'Soft delete. The team loses access but stays assigned, so the center can be restored with it.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `centers.delete`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No center with that ID.')]
    public function destroy(Request $request, Center $center): Response
    {
        Gate::forUser($request->user())->authorize('delete', $center);

        $this->service->delete($center);

        return response()->noContent();
    }

    #[Endpoint('Restore a deleted center', 'Brings the center back with the state, status and team it had when it was deleted.')]
    #[UrlParam('center', 'integer', 'ID of a deleted center.', example: 8)]
    #[ResponseFromApiResource(CenterResource::class, Center::class, status: 200, description: 'The restored center.', with: ['city', 'tags'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `centers.restore`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No center with that ID, or the center is not deleted.')]
    public function restore(Request $request, Center $center): CenterResource
    {
        Gate::forUser($request->user())->authorize('restore', $center);

        abort_unless($center->trashed(), 404);

        return CenterResource::make($this->service->restore($center));
    }

    #[Endpoint('Export centers', 'Queues an `.xlsx` export of the centers matching the list filters. Download it from `GET /api/v1/admin/exports/{export}/download` once `completed`.')]
    #[ResponseFromApiResource(ExportResource::class, Export::class, status: 202, description: 'The queued export; the requester gets an `export_ready` or `export_failed` notification when it finishes.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `centers.export`.')]
    #[ApiResponse(['message' => 'The to field must be a date after or equal to from.', 'errors' => ['to' => ['The to field must be a date after or equal to from.']]], 422, 'Invalid filters.')]
    #[ApiResponse(['message' => 'An export of this kind is already being generated. You will be notified when it is ready.'], 429, 'An export of this kind is already being generated.')]
    public function export(ExportCentersRequest $request, ExportService $exports): JsonResponse
    {
        $export = $exports->start($request->user(), ExportType::AdminCenters, new CentersExport($request->validated()));

        return ExportResource::make($export)->response()->setStatusCode(ResponseAlias::HTTP_ACCEPTED);
    }
}
