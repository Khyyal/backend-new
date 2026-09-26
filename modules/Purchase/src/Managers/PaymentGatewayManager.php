<?php

namespace Modules\Purchase\Managers;

use Illuminate\Support\Manager;
use Modules\Purchase\Contracts\PaymentGateway;
use Modules\Purchase\Gateways\FakeGateway;

class PaymentGatewayManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('purchase.default_gateway', 'fake');
    }

    public function createFakeDriver(): PaymentGateway
    {
        return new FakeGateway();
    }
}
