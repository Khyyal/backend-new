<?php

namespace Modules\Centers\Http\Controllers\Center;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Modules\Centers\Http\Resources\Center\TagResource;
use Modules\Centers\Models\Tag;
use Modules\Support\Documentation\ApiExamples;

#[Group('Center / Tags', 'The global tags a center can pick from.')]
class TagController extends Controller
{
    #[Endpoint('List the available tags', 'Ordered by ID, not paginated. Names follow `Accept-Language`. Send the chosen IDs as `tags` when updating the center.')]
    #[ResponseFromApiResource(TagResource::class, Tag::class, status: 200, description: 'All tags.', collection: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    public function index(): AnonymousResourceCollection
    {
        return TagResource::collection(Tag::query()->orderBy('id')->get());
    }
}
