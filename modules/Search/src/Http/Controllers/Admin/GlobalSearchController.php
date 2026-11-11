<?php

namespace Modules\Search\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Search\Http\Requests\Admin\GlobalSearchRequest;
use Modules\Search\Http\Resources\Admin\SearchHitResource;
use Modules\Search\Services\GlobalSearch;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Models\SystemUser;

#[Group('Admin / Search', 'Global search across centers, clients, reservations, purchases, client packages, client memberships and services.')]
class GlobalSearchController extends Controller
{
    #[Endpoint('Global search', 'Searches every group the user may see and returns up to `limit` hits per group, newest first. A group is omitted when the user lacks its permission: `centers.view_any` (centers), `clients.view_any` (clients), `purchases.view_any` (purchases), `centers.manage_reservations` (reservations, client packages, client memberships), `centers.manage_services` (services). A 6-character public code matches reservations and purchases exactly and is listed first.')]
    #[QueryParam('q', 'string', 'Search term, 2–100 characters: a name, phone number, email, slug or public code.', required: true, example: 'Aisha')]
    #[QueryParam('limit', 'integer', 'Hits per group, 1–10. Defaults to 5.', required: false, example: 5)]
    #[QueryParam('locale', 'string', 'Language of translated titles (`ar` or `en`). Defaults to Accept-Language, then `ar`.', required: false, example: 'en')]
    #[ApiResponse([
        'data' => [
            'clients' => [['id' => 12, 'title' => 'Aisha Ali', 'subtitle' => '500000001', 'code' => null, 'center_id' => null, 'kind' => null, 'meta' => []]],
            'reservations' => [['id' => 40, 'title' => 'Aisha Ali', 'subtitle' => 'Training · 2026-11-20', 'code' => 'K7M2QX', 'center_id' => 8, 'kind' => null, 'meta' => ['status' => 'confirmed', 'date' => '2026-11-20']]],
            'services' => [['id' => 3, 'title' => 'Training', 'subtitle' => 'Al Faris Center', 'code' => null, 'center_id' => 8, 'kind' => 'training-services', 'meta' => ['type' => 'training', 'serviceable_id' => 2, 'status' => 'active']]],
        ],
    ], 200, 'Hits keyed by group (`centers`, `clients`, `reservations`, `purchases`, `client_packages`, `client_memberships`, `services`).')]
    #[ResponseField('data.*.*.center_id', 'integer', 'Center the hit belongs to; null for clients.')]
    #[ResponseField('data.services.*.kind', 'string', 'Admin route segment of the service type, e.g. `training-services`; the detail id is `meta.serviceable_id`.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::VALIDATION, 422, 'Missing or too short `q`, or invalid `limit`.')]
    public function __invoke(GlobalSearchRequest $request, GlobalSearch $search): JsonResponse
    {
        /** @var SystemUser $user */
        $user = $request->user();
        $groups = collect($search->search($user, $request->term(), $request->limit(), $request->searchLocale()))
            ->map(fn ($hits) => SearchHitResource::collection($hits)->resolve($request));

        return response()->json(['data' => (object) $groups->all()]);
    }
}
