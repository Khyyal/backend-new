<?php

namespace Modules\Exports\Enums;

enum ExportStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
