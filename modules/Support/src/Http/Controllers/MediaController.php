<?php

namespace Modules\Support\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Http\Requests\UploadMediaRequest;
use Modules\Support\Http\Resources\MediaResource;
use Modules\Support\Services\Media\TemporaryMediaService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Group('Media', 'Temporary uploads shared by every actor. Upload first, then pass the returned `id` to a field such as `cover`, `logo` or `images`.')]
class MediaController extends Controller
{
    #[Endpoint('Upload a temporary file', 'Send `multipart/form-data`. The file belongs to the uploader and is deleted if not attached within 24 hours (`expires_at`). Each upload can be attached once. Accepts client, center-user and system-user tokens.')]
    #[ResponseFromApiResource(MediaResource::class, Media::class, status: 201, description: 'The temporary media.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(['message' => 'The file field must be a file of type: image/jpeg, image/png, image/webp, application/pdf.', 'errors' => ['file' => ['The file field must be a file of type: image/jpeg, image/png, image/webp, application/pdf.']]], 422, 'Missing file, too large, or unsupported type.')]
    public function store(UploadMediaRequest $request, TemporaryMediaService $temporaryMedia): JsonResponse
    {
        $media = $temporaryMedia->createTemporary($request->file('file'), $request->user());

        return (new MediaResource($media))->response()->setStatusCode(201);
    }

    #[Endpoint('Delete a temporary file', 'Only your own media that is still temporary (not yet attached).')]
    #[UrlParam('media', 'string', 'Media UUID.', example: '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90')]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Not your media, or it is already attached.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No media with that UUID.')]
    public function destroy(Request $request, Media $media, TemporaryMediaService $temporaryMedia): Response
    {
        Gate::authorize('delete', $media);

        $temporaryMedia->discard($media, $request->user());

        return response()->noContent();
    }
}
