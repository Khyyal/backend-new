<?php

namespace Modules\Exports\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Exports\Http\Resources\ExportResource;
use Modules\Exports\Models\Export;
use Modules\Exports\Services\ExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Group(name: 'Admin / Exports', description: 'Spreadsheet exports the system user requested. Exports are built in the background; the requester gets an `export_ready` (or `export_failed`) notification with `export_id`, then downloads the file here. Files are kept for 7 days.')]
class ExportController extends Controller
{
    public function __construct(private readonly ExportService $exports) {}

    /**
     * List my exports.
     *
     * Paginated (25 per page), newest first. Only the exports the current system user requested.
     */
    #[Response(status: 200, description: 'Paginated `ExportResource` collection.')]
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

    /**
     * Show an export.
     */
    #[Response(status: 200, description: 'The `ExportResource`.')]
    #[Response(status: 403, description: 'Requested by another user.')]
    public function show(Request $request, Export $export): ExportResource
    {
        Gate::forUser($request->user())->authorize('view', $export);

        return ExportResource::make($export);
    }

    /**
     * Download an export.
     *
     * Streams the `.xlsx` file.
     */
    #[Response(status: 200, description: 'The spreadsheet file.')]
    #[Response(status: 403, description: 'Requested by another user.')]
    #[Response(status: 409, description: 'Still being generated, or failed.')]
    #[Response(status: 410, description: 'Expired; request a new export.')]
    public function download(Request $request, Export $export): StreamedResponse
    {
        Gate::forUser($request->user())->authorize('view', $export);

        return $this->exports->download($export);
    }
}
