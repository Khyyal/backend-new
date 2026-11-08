<?php

namespace Modules\Exports\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Models\Center;
use Modules\Exports\Http\Resources\ExportResource;
use Modules\Exports\Models\Export;
use Modules\Exports\Services\ExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Group(name: 'Center / Exports', description: 'Spreadsheet exports the center user requested for this center. Exports are built in the background; the requester gets an `export_ready` (or `export_failed`) notification with `export_id`, then downloads the file here. Files are kept for 7 days. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class ExportController extends Controller
{
    public function __construct(private readonly ExportService $exports) {}

    /**
     * List my exports of this center.
     *
     * Paginated (25 per page), newest first.
     */
    #[Response(status: 200, description: 'Paginated `ExportResource` collection.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        $user = $request->user();

        return ExportResource::collection(
            Export::query()
                ->where('requester_type', $user->getMorphClass())
                ->where('requester_id', $user->getKey())
                ->where('center_id', $center->id)
                ->latest('id')
                ->paginate(25)
        );
    }

    /**
     * Show an export.
     */
    #[Response(status: 200, description: 'The `ExportResource`.')]
    #[Response(status: 403, description: 'Requested by another user.')]
    #[Response(status: 404, description: 'No such export in this center.')]
    public function show(Request $request, Center $center, Export $export): ExportResource
    {
        $this->authorize($request, $center, $export);

        return ExportResource::make($export);
    }

    /**
     * Download an export.
     *
     * Streams the `.xlsx` file.
     */
    #[Response(status: 200, description: 'The spreadsheet file.')]
    #[Response(status: 403, description: 'Requested by another user.')]
    #[Response(status: 404, description: 'No such export in this center.')]
    #[Response(status: 409, description: 'Still being generated, or failed.')]
    #[Response(status: 410, description: 'Expired; request a new export.')]
    public function download(Request $request, Center $center, Export $export): StreamedResponse
    {
        $this->authorize($request, $center, $export);

        return $this->exports->download($export);
    }

    private function authorize(Request $request, Center $center, Export $export): void
    {
        abort_unless($export->center_id === $center->id, 404);

        Gate::forUser($request->user())->authorize('view', $export);
    }
}
