<?php

namespace Modules\Purchase\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Modules\Purchase\Services\WebhookAuthenticator;
use Modules\Purchase\Services\WebhookProcessorService;
use Modules\Support\Documentation\ApiExamples;

#[Group('Webhooks', 'Called by the payment providers, not the apps. Each call is authenticated with the provider secret; configure the URLs in the provider dashboards. Limited to 120 calls per minute.')]
class WebhookController extends Controller
{
    public function __construct(
        private readonly WebhookAuthenticator $authenticator,
        private readonly WebhookProcessorService $processor,
    ) {}

    #[Unauthenticated]
    #[Endpoint('Moyasar webhook', 'The payment is found by `data.given_id` (our payment ID) and re-fetched from Moyasar before it is marked paid.')]
    #[BodyParam('id', 'string', 'Moyasar event ID.', example: 'evt_123')]
    #[BodyParam('type', 'string', 'Event type, e.g. `payment_paid`, `payment_failed`, `payment_refunded`.', example: 'payment_paid')]
    #[BodyParam('secret_token', 'string', 'Must equal `MOYASAR_WEBHOOK_SECRET`.', example: 'whsec_xxx')]
    #[BodyParam('data', 'object', 'The Moyasar payment object; `data.given_id` is our payment ID.', example: ['id' => 'f3e5a1b2-7c9d-4e0f-8a1b-2c3d4e5f6a7b', 'status' => 'paid', 'amount' => 29325, 'given_id' => '801'])]
    #[ApiResponse(['received' => true], 200, 'Accepted.')]
    #[ResponseField('received', 'boolean', 'Always true once authenticated; repeated events are ignored.', example: true)]
    #[ApiResponse(['message' => 'Unauthorized.'], 401, 'Wrong or missing `secret_token`.')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many attempts; retry after the `Retry-After` header.')]
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

    #[Unauthenticated]
    #[Endpoint('Tamara webhook', 'Authenticated by the Tamara notification JWT in `Authorization: Bearer` or the `tamaraToken` query parameter. On `order_approved` the order is authorised server-side; a success is re-checked with Tamara.')]
    #[QueryParam('tamaraToken', 'string', 'Tamara notification JWT, when not sent as a bearer header.', required: false, example: 'eyJhbGciOiJIUzI1NiJ9.e30.x')]
    #[BodyParam('order_id', 'string', 'Tamara order ID.', example: 'ae8b5c2e-1d3f-4a5b-9c7d-0e1f2a3b4c5d')]
    #[BodyParam('order_reference_id', 'string', 'Our payment ID.', example: '801')]
    #[BodyParam('event_type', 'string', 'Event, e.g. `order_approved`, `order_declined`, `order_canceled`.', example: 'order_approved')]
    #[BodyParam('data', 'object', 'Event payload from Tamara.', required: false, example: [])]
    #[ApiResponse(['received' => true], 200, 'Accepted.')]
    #[ResponseField('received', 'boolean', 'Always true once authenticated; repeated events are ignored.', example: true)]
    #[ApiResponse(['message' => 'Unauthorized.'], 401, 'Missing, invalid or expired notification token.')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many attempts; retry after the `Retry-After` header.')]
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
