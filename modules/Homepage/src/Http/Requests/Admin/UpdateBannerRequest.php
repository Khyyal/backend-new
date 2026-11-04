<?php

namespace Modules\Homepage\Http\Requests\Admin;

class UpdateBannerRequest extends StoreBannerRequest
{
    protected bool $isUpdate = true;
}
