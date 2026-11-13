<?php

namespace Modules\Reservations\Http\Controllers\Center;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Models\Center;
use Modules\Reservations\Http\Requests\Center\ListHorseStaysRequest;
use Modules\Reservations\Http\Resources\HorseStayBoardResource;
use Modules\Reservations\Services\HorseStayBoardQuery;
use Modules\Support\Documentation\ApiExamples;

#[Group('Horses and stays', 'The center\'s horses board. Served on `centers/{center}/horse-stays` (center users need `horses.manage` or `reservations.manage`) and `admin/centers/{center}/horse-stays` (system users need `centers.manage_horses` or `centers.manage_reservations`).')]
class HorseStayController extends Controller
{
    #[Endpoint('List horses and stays', 'Active stabling stays (confirmed and started) and horses without an active stay, ordered by horse name, 25 per page. Each item has a `type`: `stay` items carry the horse, service, end date, status and unpaid operations count; `horse` items carry the horse with its client and trainers.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ApiResponse(['data' => [
        ['type' => 'stay', 'id' => 412, 'status' => 'confirmed', 'end_date' => '2026-12-31', 'horse' => ['id' => 5, 'name' => 'Najm', 'image' => null, 'room' => 12, 'client' => ['id' => 77, 'first_name' => 'Noura', 'last_name' => 'Al-Qahtani', 'phone_number' => '+966500000003']], 'service' => ['id' => 31, 'name' => ['ar' => 'إسطبل شهري', 'en' => 'Monthly stabling']], 'unpaid_operations_count' => 1],
        ['type' => 'horse', 'id' => 6, 'name' => 'Barq', 'image' => null, 'room' => 3, 'client' => null, 'trainers' => [['id' => 6, 'name' => 'Fahad']]],
    ], 'links' => [], 'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 2]], 200, 'Paginated board.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `horses.manage` and `reservations.manage` for this center (system users: `centers.manage_horses` or `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(['message' => 'The selected type is invalid.', 'errors' => ['type' => ['The selected type is invalid.']]], 422, 'Invalid filters.')]
    public function index(ListHorseStaysRequest $request, Center $center, HorseStayBoardQuery $board): AnonymousResourceCollection
    {
        return HorseStayBoardResource::collection($board->paginate($center, $request->validated()));
    }
}
