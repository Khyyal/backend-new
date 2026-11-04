<?php

namespace Modules\Homepage\Http\Requests\Admin;

class UpdateHomeSectionRequest extends StoreHomeSectionRequest
{
    protected bool $isUpdate = true;
}
