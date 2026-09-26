<?php

namespace Modules\Purchase\Managers;

use Illuminate\Support\Manager;
use Modules\Purchase\Contracts\PaymentGateway;
use Modules\Purchase\Gateways\FakeGateway;
use Modules\Purchase\Gateways\Moyasar\MoyasarClient;
use Modules\Purchase\Gateways\Moyasar\MoyasarGateway;
use Modules\Purchase\Gateways\Tamara\TamaraClient;
use Modules\Purchase\Gateways\Tamara\TamaraGateway;

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

    public function createTamaraDriver(): PaymentGateway
    {
        /** @var array<string, mixed> $config */
        $config = (array) $this->config->get('purchase.providers.tamara', []);

        return new TamaraGateway(new TamaraClient($config));
    }

    public function createMoyasarDriver(): PaymentGateway
    {
        /** @var array<string, mixed> $config */
        $config = (array) $this->config->get('purchase.providers.moyasar', []);

        return new MoyasarGateway(new MoyasarClient($config));
    }
}
