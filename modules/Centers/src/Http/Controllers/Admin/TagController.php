<?php

namespace Modules\Centers\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Http\Requests\Admin\StoreTagRequest;
use Modules\Centers\Http\Requests\Admin\UpdateTagRequest;
use Modules\Centers\Http\Resources\Admin\TagResource;
use Modules\Centers\Models\Tag;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Admin / Tags', description: 'System user management of the global center tags. Requires a system user Bearer token with the matching `tags.*` permission. Tags are attached to a center with the `tags` id array of the center update endpoints.')]
class TagController extends Controller
{
    /**
     * List tags.
     *
     * Paginated (15 per page, `per_page` up to 100), ordered by id, each with its `centers_count`.
     */
    #[QueryParameter('search', description: 'Matches the English or Arabic name.', type: 'string')]
    #[Response(status: 200, description: 'Paginated `TagResource` collection.')]
    #[Response(status: 403, description: 'Missing the `tags.view_any` permission.')]
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

    /**
     * Create a tag.
     *
     * **Request body:** `name` (`{en, ar}`), both required.
     */
    #[Response(status: 201, description: 'The created `TagResource`.')]
    #[Response(status: 403, description: 'Missing the `tags.create` permission.')]
    #[Response(status: 422, description: 'Validation failed.')]
    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = Tag::query()->create($request->validated());

        return TagResource::make($tag->loadCount('centers'))->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Show a tag.
     */
    #[Response(status: 200, description: 'The `TagResource`.')]
    #[Response(status: 403, description: 'Missing the `tags.view` permission.')]
    #[Response(status: 404, description: 'No tag with that id.')]
    public function show(Request $request, Tag $tag): TagResource
    {
        Gate::forUser($request->user())->authorize('view', $tag);

        return TagResource::make($tag->loadCount('centers'));
    }

    /**
     * Update a tag.
     *
     * Send `name` with both `en` and `ar`.
     */
    #[Response(status: 200, description: 'The updated `TagResource`.')]
    #[Response(status: 403, description: 'Missing the `tags.update` permission.')]
    #[Response(status: 404, description: 'No tag with that id.')]
    #[Response(status: 422, description: 'Validation failed.')]
    public function update(UpdateTagRequest $request, Tag $tag): TagResource
    {
        $tag->update($request->validated());

        return TagResource::make($tag->loadCount('centers'));
    }

    /**
     * Delete a tag.
     *
     * The tag is also removed from every center that used it.
     */
    #[Response(status: 204, description: 'Deleted; no content.')]
    #[Response(status: 403, description: 'Missing the `tags.delete` permission.')]
    #[Response(status: 404, description: 'No tag with that id.')]
    public function destroy(Request $request, Tag $tag): \Illuminate\Http\Response
    {
        Gate::forUser($request->user())->authorize('delete', $tag);

        $tag->delete();

        return response()->noContent();
    }
}
