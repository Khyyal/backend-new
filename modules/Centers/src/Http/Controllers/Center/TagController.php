<?php

namespace Modules\Centers\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Centers\Http\Resources\Center\TagResource;
use Modules\Centers\Models\Tag;

#[Group(name: 'Center / Tags', description: 'The global tags a center can pick from. Requires a center user Bearer token.')]
class TagController extends Controller
{
    /**
     * List the available tags.
     *
     * Read-only and not paginated, ordered by id. Send the chosen ids as `tags` on the center update endpoint.
     */
    #[Response(status: 200, description: 'Collection of `{id, name}` where `name` is the tag name in the request locale.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    public function index(): AnonymousResourceCollection
    {
        return TagResource::collection(Tag::query()->orderBy('id')->get());
    }
}
