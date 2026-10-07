<?php

namespace Modules\Support\Http\Controllers;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Modules\Support\Http\Requests\UploadMediaRequest;
use Modules\Support\Http\Resources\MediaResource;
use Modules\Support\Services\Media\TemporaryMediaService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Group(name: 'Media', description: 'Generic temporary media upload.')]
class MediaController extends Controller
{
    /**
     * Upload a file as temporary media.
     *
     * Returns a stable media `id` to reference in other requests. The media
     * expires if it is not attached in time.
     */
    public function store(UploadMediaRequest $request, TemporaryMediaService $temporaryMedia): JsonResponse
    {
        $media = $temporaryMedia->createTemporary($request->file('file'), $request->user());

        return (new MediaResource($media))->response()->setStatusCode(201);
    }

    /**
     * Delete one of your own temporary media.
     */
    public function destroy(Request $request, Media $media, TemporaryMediaService $temporaryMedia): Response
    {
        Gate::authorize('delete', $media);

        $temporaryMedia->discard($media, $request->user());

        return response()->noContent();
    }
}
