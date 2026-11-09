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
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Http\Requests\Admin\StoreTagRequest;
use Modules\Centers\Http\Requests\Admin\UpdateTagRequest;
use Modules\Centers\Http\Resources\Admin\TagResource;
use Modules\Centers\Models\Tag;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Admin / Tags', 'Global center tags. Requires the matching `tags.*` permission. Centers pick tags with the `tags` ID list of the center update endpoints.')]
class TagController extends Controller
{
    #[Endpoint('List tags', 'Ordered by ID, paginated, with `centers_count`.')]
    #[QueryParam('search', 'string', 'Matches the English or Arabic name (contains).', required: false, example: 'Family')]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Items per page, 1-100. Default 15.', required: false, example: 15)]
    #[ResponseFromApiResource(TagResource::class, Tag::class, status: 200, description: 'Paginated tags.', paginate: 15, withCount: ['centers'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `tags.view_any`.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAny', Tag::class);

        $tags = Tag::query()
            ->withCount('centers')
            ->when($request->query('search'), fn ($query, string $term) => $query->where(
                fn ($query) => $query->where('name->en', 'like', "%{$term}%")->orWhere('name->ar', 'like', "%{$term}%")
            ))
            ->orderBy('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 15))));

        return TagResource::collection($tags);
    }

    #[Endpoint('Create a tag')]
    #[ResponseFromApiResource(TagResource::class, Tag::class, status: 201, description: 'The created tag.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `tags.create`.')]
    #[ApiResponse(['message' => 'The name.ar field is required.', 'errors' => ['name.ar' => ['The name.ar field is required.']]], 422, 'Validation failed.')]
    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = Tag::query()->create($request->validated());

        return TagResource::make($tag->loadCount('centers'))->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Endpoint('Show a tag')]
    #[UrlParam('tag', 'integer', 'Tag ID.', example: 4)]
    #[ResponseFromApiResource(TagResource::class, Tag::class, status: 200, description: 'The tag.', withCount: ['centers'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `tags.view`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No tag with that ID.')]
    public function show(Request $request, Tag $tag): TagResource
    {
        Gate::forUser($request->user())->authorize('view', $tag);

        return TagResource::make($tag->loadCount('centers'));
    }

    #[Endpoint('Update a tag', 'When `name` is sent it needs both `en` and `ar`.')]
    #[UrlParam('tag', 'integer', 'Tag ID.', example: 4)]
    #[ResponseFromApiResource(TagResource::class, Tag::class, status: 200, description: 'The updated tag.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `tags.update`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No tag with that ID.')]
    #[ApiResponse(['message' => 'The name.en field is required when name is present.', 'errors' => ['name.en' => ['The name.en field is required when name is present.']]], 422, 'Validation failed.')]
    public function update(UpdateTagRequest $request, Tag $tag): TagResource
    {
        $tag->update($request->validated());

        return TagResource::make($tag->loadCount('centers'));
    }

    #[Endpoint('Delete a tag', 'Also removes it from every center.')]
    #[UrlParam('tag', 'integer', 'Tag ID.', example: 4)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `tags.delete`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No tag with that ID.')]
    public function destroy(Request $request, Tag $tag): Response
    {
        Gate::forUser($request->user())->authorize('delete', $tag);

        $tag->delete();

        return response()->noContent();
    }
}
