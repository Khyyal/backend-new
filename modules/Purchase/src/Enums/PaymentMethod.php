<?php

namespace Modules\Purchase\Enums;

enum PaymentMethod: string
{
    case Online = 'online';
    case CashOnArrival = 'cash_on_arrival';
    case Manual = 'manual';
}
