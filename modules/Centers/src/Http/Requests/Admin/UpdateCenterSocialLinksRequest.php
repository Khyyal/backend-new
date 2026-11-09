<?php

namespace Modules\Centers\Http\Requests\Admin;

use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Centers\Http\Requests\Center\UpdateCenterSocialLinksRequest as CenterUpdateCenterSocialLinksRequest;

#[BodyParam('social_links', 'object', 'Links keyed by platform (`facebook`, `instagram`, `whatsapp`, `tiktok`, `snapchat`, `x`, `youtube`, `website`), each a string up to 255 or null. Replaces all links; send null to clear.', nullable: true, example: ['instagram' => 'https://instagram.com/alfursan', 'website' => 'https://alfursan.sa'])]
class UpdateCenterSocialLinksRequest extends CenterUpdateCenterSocialLinksRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('update', $this->route('center'));
    }
}
