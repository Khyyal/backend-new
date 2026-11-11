<?php

namespace Modules\Settlements\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Models\Center;
use Modules\Settlements\Actions\SetCenterFinancialBaseline;
use Modules\Settlements\Http\Requests\Admin\SetCenterFinancialBaselineRequest;
use Modules\Settlements\Http\Resources\CenterFinancialBaselineResource;
use Modules\Settlements\Models\CenterFinancialBaseline;
use Modules\Settlements\Models\CenterPayout;
use Modules\Settlements\Services\CenterBalance;
use Modules\Settlements\Services\CenterBaselineSnapshot;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group('Admin / Financial baselines', 'The true figures of a center migrated from the old system, as finance holds them. Setting them moves the center balance to the confirmed figure with one `baseline_adjustment` ledger entry and lifts the payout block. Requires `payouts.baseline`. Amounts are halalas.')]
class CenterFinancialBaselineController extends Controller
{
    #[Endpoint('Show a center\'s financial baseline', 'Until the baseline is confirmed the `migrated_*` figures are a live preview as of now. `meta.balance` has the current balance summary.')]
    #[UrlParam('center', 'integer', 'Center ID.', example: 8)]
    #[ResponseFromApiResource(CenterFinancialBaselineResource::class, CenterFinancialBaseline::class, status: 200, description: 'The baseline; `meta.balance` has the balance summary.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `payouts.baseline`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such center.')]
    public function show(Request $request, Center $center, CenterBaselineSnapshot $snapshot, CenterBalance $balance): JsonResponse
    {
        Gate::forUser($request->user())->authorize('manageBaseline', CenterPayout::class);

        $baseline = CenterFinancialBaseline::query()->where('center_id', $center->id)->first()
            ?? new CenterFinancialBaseline(['center_id' => $center->id]);

        if (! $baseline->isConfirmed()) {
            $baseline->fill($snapshot->for($center, now()));
        }

        return $this->respond($baseline, $center, $balance);
    }

    #[Endpoint('Set a center\'s financial baseline', 'The first call freezes the cutoff and the migrated figures and posts `target_balance - migrated_balance` to the ledger. Later calls replace the targets and post only the change in `target_balance`. A zero difference posts nothing.')]
    #[UrlParam('center', 'integer', 'Center ID.', example: 8)]
    #[ResponseFromApiResource(CenterFinancialBaselineResource::class, CenterFinancialBaseline::class, status: 200, description: 'The confirmed baseline; `meta.balance` has the balance summary.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `payouts.baseline`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such center.')]
    #[ApiResponse(['message' => 'The cutoff of a confirmed baseline cannot be changed.', 'errors' => ['cutoff_at' => ['The cutoff of a confirmed baseline cannot be changed.']]], 422, 'Validation failed.')]
    public function update(SetCenterFinancialBaselineRequest $request, Center $center, SetCenterFinancialBaseline $action, CenterBalance $balance): JsonResponse
    {
        $baseline = $action->execute($center, $request->validated(), $request->user());

        return $this->respond($baseline, $center, $balance);
    }

    /**
     * Always 200: a PUT that creates the row is still an update of the
     * center's one baseline.
     */
    private function respond(CenterFinancialBaseline $baseline, Center $center, CenterBalance $balance): JsonResponse
    {
        return CenterFinancialBaselineResource::make($baseline)
            ->additional(['meta' => ['balance' => $balance->for($center)]])
            ->response()
            ->setStatusCode(HttpStatus::HTTP_OK);
    }
}
