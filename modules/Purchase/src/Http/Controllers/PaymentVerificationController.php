<?php

namespace Modules\Purchase\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Exceptions\PaymentGatewayException;
use Modules\Purchase\Http\Requests\VerifyPaymentRequest;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Services\PaymentVerificationService;
use Modules\Support\Documentation\ApiExamples;

#[Group('Payments', 'Confirm an online payment with the provider when the app returns from paying, without waiting for the webhook. The app\'s own result is never trusted.')]
class PaymentVerificationController extends Controller
{
    public function __construct(private readonly PaymentVerificationService $verification) {}

    #[Endpoint('Verify my payment', 'Card, Apple Pay and STC Pay go through the Moyasar SDK: create the payment with the checkout `given_id` (without `manual`), then send the returned Moyasar ID here. For Tamara call it after the Tamara page returns.')]
    #[UrlParam('payment', 'integer', 'Payment ID (`checkout.payment.id`).', example: 801)]
    #[ApiResponse(['data' => ['id' => 801, 'status' => 'succeeded', 'purchase_status' => 'confirmed', 'payment_company' => 'moyasar']], 200, 'Payment state.')]
    #[ResponseField('data.id', 'integer', 'Payment ID.', example: 801)]
    #[ResponseField('data.status', 'string', 'Payment status after asking the provider; poll again while `pending` or `processing`.', enum: PaymentStatus::class, example: 'succeeded')]
    #[ResponseField('data.purchase_status', 'string', 'Purchase status after the check.', enum: PurchaseStatus::class, example: 'confirmed')]
    #[ResponseField('data.payment_company', 'string', 'Gateway.', nullable: true, example: 'moyasar')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such payment of yours.')]
    #[ApiResponse(['message' => 'The provider payment does not belong to this payment.', 'errors' => ['provider_payment_id' => ['The provider payment does not belong to this payment.']]], 422, 'Not an online payment, or the provider payment belongs to another payment.')]
    public function client(VerifyPaymentRequest $request, int $payment): JsonResponse
    {
        /** @var Client $client */
        $client = $request->user('client');

        return $this->respond($request, $this->ownedPayment($payment, $client->getMorphClass(), $client->getKey()));
    }

    #[Endpoint('Verify a center payment', 'Same as the client call, for payments the center made (e.g. a plan subscription).')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('payment', 'integer', 'Payment ID.', example: 801)]
    #[ApiResponse(['data' => ['id' => 801, 'status' => 'succeeded', 'purchase_status' => 'confirmed', 'payment_company' => 'moyasar']], 200, 'Payment state.')]
    #[ResponseField('data.id', 'integer', 'Payment ID.', example: 801)]
    #[ResponseField('data.status', 'string', 'Payment status after asking the provider; poll again while `pending` or `processing`.', enum: PaymentStatus::class, example: 'succeeded')]
    #[ResponseField('data.purchase_status', 'string', 'Purchase status after the check.', enum: PurchaseStatus::class, example: 'confirmed')]
    #[ResponseField('data.payment_company', 'string', 'Gateway.', nullable: true, example: 'moyasar')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'The token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such payment of this center.')]
    #[ApiResponse(['message' => 'The provider payment does not belong to this payment.', 'errors' => ['provider_payment_id' => ['The provider payment does not belong to this payment.']]], 422, 'Not an online payment, or the provider payment belongs to another payment.')]
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
