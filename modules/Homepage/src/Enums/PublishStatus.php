<?php

namespace Modules\Homepage\Enums;

enum PublishStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
