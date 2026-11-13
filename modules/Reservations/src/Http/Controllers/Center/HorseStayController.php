<?php

namespace Modules\Reservations\Http\Controllers\Center;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Models\Center;
use Modules\Reservations\Http\Requests\Center\ListHorseStaysRequest;
use Modules\Reservations\Http\Resources\HorseStayBoardResource;
use Modules\Reservations\Http\Resources\PurchaseSummaryResource;
use Modules\Reservations\Http\Resources\StayDetailsResource;
use Modules\Reservations\Models\Reservation;
use Modules\Reservations\Services\HorseStayBoardQuery;
use Modules\Services\Enums\ServiceType;
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

    #[Endpoint('Show a stay with its invoices', 'A stabling stay of this center in any status: every reservation field (code, dates, price option, extras, prices, client, purchase summary), the horse with its client and trainers, `planned_end_date` after an early end, `unpaid_operations_count`, and the invoices billed during the stay, newest first.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('reservation', 'integer', 'Reservation ID of the stay (the `id` of a `stay` item).', example: 412)]
    #[ResponseFromApiResource(StayDetailsResource::class, Reservation::class, status: 200, description: 'The stay.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `horses.manage` and `reservations.manage` for this center (system users: `centers.manage_horses` or `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No stabling stay with this ID in this center.')]
    public function show(Request $request, Center $center, int $reservation): StayDetailsResource
    {
        Gate::forUser($request->user())->authorize('viewHorseStays', $center);

        $stay = Reservation::query()
            ->where('center_id', $center->getKey())
            ->whereHas('service', fn (Builder $service) => $service->where('type', ServiceType::Stabling))
            ->with([
                'service', 'trainer', 'client', 'priceOption', 'lines.priceOption', ...PurchaseSummaryResource::RELATIONS,
                'horse.media', 'horse.client', 'horse.trainers',
                'invoices' => fn ($invoices) => $invoices->with(['service', 'priceOption', 'purchaseItem.purchase.payments'])->latest('id'),
            ])
            ->tap(fn (Builder $query) => HorseStayBoardQuery::withUnpaidCounts($query))
            ->findOrFail($reservation);

        return StayDetailsResource::make($stay);
    }
}
