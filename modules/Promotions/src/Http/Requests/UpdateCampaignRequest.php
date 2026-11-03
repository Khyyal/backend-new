<?php

namespace Modules\Promotions\Http\Requests;

class UpdateCampaignRequest extends StoreCampaignRequest
{
    protected bool $isUpdate = true;
}
