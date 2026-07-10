<?php

namespace App\Services;

use Stripe\Event;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create a Stripe PaymentIntent.
     */
    public function createPaymentIntent(float $amount, string $currency = 'usd', array $metadata = []): PaymentIntent
    {
        return PaymentIntent::create([
            'amount' => (int) ($amount * 100), // convert to cents
            'currency' => strtolower($currency),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Verify Stripe Webhook Signature.
     */
    public function verifyWebhook(string $payload, string $signatureHeader): Event
    {
        $webhookSecret = config('services.stripe.webhook_secret');

        return Webhook::constructEvent($payload, $signatureHeader, $webhookSecret);
    }

    /**
     * Create a refund for a payment intent.
     */
    public function refund(string $paymentIntentId, ?float $amount = null): Refund
    {
        $params = ['payment_intent' => $paymentIntentId];
        if ($amount !== null) {
            $params['amount'] = (int) ($amount * 100);
        }

        return Refund::create($params);
    }
}
