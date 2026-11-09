<?php

namespace Modules\Exports\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Exports\Http\Resources\ExportResource;
use Modules\Exports\Models\Export;
use Modules\Exports\Services\ExportService;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Group('Admin / Exports', 'Spreadsheet exports the system user requested. They are built in the background; the requester gets an `export_ready` (or `export_failed`) notification with `export_id`. Files are kept for 7 days.')]
class ExportController extends Controller
{
    public function __construct(private readonly ExportService $exports) {}

    #[Endpoint('List my exports', 'Only the exports you requested, newest first, 25 per page.')]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(ExportResource::class, Export::class, status: 200, description: 'Paginated exports.', paginate: 25)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        return ExportResource::collection(
            Export::query()
                ->where('requester_type', $user->getMorphClass())
                ->where('requester_id', $user->getKey())
                ->latest('id')
                ->paginate(25)
        );
    }

    #[Endpoint('Show an export')]
    #[UrlParam('export', 'integer', 'Export ID.', example: 14)]
    #[ResponseFromApiResource(ExportResource::class, Export::class, status: 200, description: 'The export.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Requested by another user.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such export.')]
    public function show(Request $request, Export $export): ExportResource
    {
        Gate::forUser($request->user())->authorize('view', $export);

        return ExportResource::make($export);
    }

    #[Endpoint('Download an export', 'Send your bearer token; streams the file.')]
    #[UrlParam('export', 'integer', 'Export ID.', example: 14)]
    #[ApiResponse(status: 200, description: 'The `.xlsx` file (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`), streamed as an attachment.')]
    #[ApiResponse(['message' => 'This export is not ready yet.'], 409, 'Still being generated, or failed.')]
    #[ApiResponse(['message' => 'This export has expired. Please request a new one.'], 410, 'Expired; request a new export.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Requested by another user.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such export.')]
    public function download(Request $request, Export $export): StreamedResponse
    {
        Gate::forUser($request->user())->authorize('view', $export);

        return $this->exports->download($export);
    }
}
