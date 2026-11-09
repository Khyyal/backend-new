<?php

namespace Modules\Support\Enums;

/**
 * Who a legal document is written for: the client app or the center app.
 */
enum LegalAudience: string
{
    case Client = 'client';
    case Center = 'center';
}
