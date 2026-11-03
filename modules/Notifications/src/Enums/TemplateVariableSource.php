<?php

namespace Modules\Notifications\Enums;

enum TemplateVariableSource: string
{
    /** The admin types one value for the whole campaign. */
    case Fixed = 'fixed';

    /** The value is read from each recipient. */
    case Recipient = 'recipient';
}
