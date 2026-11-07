<?php

namespace Modules\Purchase\Http\Controllers;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Purchase\Services\WebhookAuthenticator;
use Modules\Purchase\Services\WebhookProcessorService;

#[Group(name: 'Webhooks', description: 'Called by the payment providers, not by the apps. Each call is authenticated with the provider secret; set the URLs in the provider dashboards.')]
class WebhookController extends Controller
{
    public function __construct(
        private readonly WebhookAuthenticator $authenticator,
        private readonly WebhookProcessorService $processor,
    ) {}

    /**
     * Moyasar webhook.
     *
     * Body: `{id, type, secret_token, data: {...payment}}`. `secret_token` must equal `MOYASAR_WEBHOOK_SECRET`.
     * The payment is found by `data.given_id` (our payment id), then re-fetched from Moyasar before it is
     * marked paid. Repeated events are ignored.
     */
    #[Response(status: 200, description: '`{received: true}`.')]
    #[Response(status: 401, description: 'Wrong or missing `secret_token`.')]
    public function moyasar(Request $request): JsonResponse
    {
        abort_unless($this->authenticator->moyasar($request), 401);

        $eventId = (string) $request->input('id', '');
        $type = (string) $request->input('type', '');

        if ($eventId !== '' && $type !== '') {
            $this->processor->process('moyasar', $eventId, $type, $request->except('secret_token'));
        }

        return response()->json(['received' => true]);
    }

    /**
     * Tamara webhook.
     *
     * Body: `{order_id, order_reference_id, event_type, data}`. Authenticated by the Tamara notification JWT
     * (`Authorization: Bearer` or `tamaraToken` query). `order_reference_id` is our payment id. On
     * `order_approved` the order is authorised server-side; a success is re-checked with Tamara.
     */
    #[Response(status: 200, description: '`{received: true}`.')]
    #[Response(status: 401, description: 'Missing, invalid or expired notification token.')]
    public function tamara(Request $request): JsonResponse
    {
        abort_unless($this->authenticator->tamara($request), 401);

        $orderId = (string) $request->input('order_id', '');
        $type = (string) $request->input('event_type', '');

        if ($orderId !== '' && $type !== '') {
            $this->processor->process('tamara', $orderId.':'.$type, $type, $request->all());
        }

        return response()->json(['received' => true]);
    }
}
