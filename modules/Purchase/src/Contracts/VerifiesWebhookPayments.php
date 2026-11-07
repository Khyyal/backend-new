<?php

namespace Modules\Purchase\Contracts;

/**
 * Marks gateways whose webhook results are re-checked with the provider before a payment is
 * marked succeeded (the webhook only says "look at this payment").
 */
interface VerifiesWebhookPayments {}
