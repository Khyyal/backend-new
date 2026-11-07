<?php

namespace Modules\Purchase\Http\Controllers;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Purchase\Exceptions\PaymentGatewayException;
use Modules\Purchase\Http\Requests\VerifyPaymentRequest;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Services\PaymentVerificationService;

#[Group(name: 'Payments', description: 'Confirm an online payment with the provider after the app returns from paying.')]
class PaymentVerificationController extends Controller
{
    public function __construct(private readonly PaymentVerificationService $verification) {}

    /**
     * Verify one of my payments (client).
     *
     * Call it when the Moyasar SDK or the Tamara page returns, so the status does not wait for the provider's
     * webhook. **Request body:** `provider_payment_id`, the Moyasar payment id returned by the SDK (required
     * the first time for `moyasar`, ignored for `tamara`). The backend asks the provider and moves the payment
     * and its purchase accordingly; the app's own result is never trusted.
     *
     * Card, Apple Pay and STC Pay all go through the Moyasar SDK: create the payment with the checkout's
     * `given_id`, without `manual`, then send the returned Moyasar id here.
     *
     * Poll this endpoint (or the booking) while `status` is `pending` or `processing`.
     */
    #[Response(status: 200, description: '`{data: {id, status, purchase_status, payment_company}}`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 404, description: 'No such payment of this client.')]
    #[Response(status: 422, description: 'Not an online payment, or the provider payment belongs to another payment.')]
    public function client(VerifyPaymentRequest $request, int $payment): JsonResponse
    {
        /** @var Client $client */
        $client = $request->user('client');

        return $this->respond($request, $this->ownedPayment($payment, $client->getMorphClass(), $client->getKey()));
    }

    /**
     * Verify one of the center's payments (center).
     *
     * Same as the client call, for payments the center made (for example a plan subscription).
     */
    #[Response(status: 200, description: '`{data: {id, status, purchase_status, payment_company}}`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 404, description: 'No such payment of this center.')]
    #[Response(status: 422, description: 'Not an online payment, or the provider payment belongs to another payment.')]
    public function center(VerifyPaymentRequest $request, Center $center, int $payment): JsonResponse
    {
        return $this->respond($request, $this->ownedPayment($payment, $center->getMorphClass(), $center->getKey()));
    }

    private function ownedPayment(int $payment, string $buyerType, int|string $buyerId): Payment
    {
        return Payment::query()
            ->whereKey($payment)
            ->whereHas('purchase', fn ($query) => $query->where('buyer_type', $buyerType)->where('buyer_id', $buyerId))
            ->firstOrFail();
    }

    private function respond(VerifyPaymentRequest $request, Payment $payment): JsonResponse
    {
        try {
            $payment = $this->verification->verify($payment, $request->validated('provider_payment_id'));
        } catch (PaymentGatewayException $e) {
            throw ValidationException::withMessages(['provider_payment_id' => [$e->getMessage()]]);
        }

        $payment->load('purchase');

        return response()->json(['data' => [
            'id' => $payment->id,
            'status' => $payment->status->value,
            'purchase_status' => $payment->purchase->status->value,
            'payment_company' => $payment->payment_company,
        ]]);
    }
}
