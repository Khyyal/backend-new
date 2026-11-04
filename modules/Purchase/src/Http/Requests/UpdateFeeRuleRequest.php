<?php

namespace Modules\Purchase\Http\Requests;

class UpdateFeeRuleRequest extends StoreFeeRuleRequest
{
    protected bool $isUpdate = true;
}
