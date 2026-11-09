<?php

namespace Modules\Support\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Modules\Support\Enums\LegalAudience;
use Modules\Support\Http\Resources\LegalLinksResource;

#[Group('Client / Legal', 'Links to the client app privacy policy and terms pages.')]
class LegalController extends Controller
{
    #[Unauthenticated]
    #[Endpoint('Get legal page links', 'Web page URLs for the client app privacy policy and terms, in the `Accept-Language` locale (`en` or `ar`, default `en`). Open them in a webview.')]
    #[ApiResponse(['data' => ['privacy_policy_url' => 'https://api.khyyal.com/legal/client/privacy_policy?lang=en', 'terms_url' => 'https://api.khyyal.com/legal/client/terms?lang=en']], 200, 'The links.')]
    public function __invoke(): LegalLinksResource
    {
        return LegalLinksResource::make(LegalAudience::Client);
    }
}
