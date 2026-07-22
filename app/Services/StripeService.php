<?php

namespace App\Services;

use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Event;
use Stripe\PaymentIntent;
use Stripe\Price;
use Stripe\Product;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret', env('STRIPE_SECRET')));
    }

    /**
     * Create a Stripe PaymentIntent.
     */
    public function createPaymentIntent(float $amount, string $currency = 'usd', array $metadata = []): PaymentIntent
    {
        return PaymentIntent::create([
            'amount' => (int) round($amount * 100), // convert to cents in integer
            'currency' => strtolower($currency),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Create a Stripe Checkout Session for Subscription Purchase.
     */
    public function createCheckoutSession(float $amount, string $currency = 'eur', array $metadata = [], ?string $successUrl = null, ?string $cancelUrl = null): CheckoutSession
    {
        $successUrl = $successUrl ?? config('app.url').'/driver/subscription/success?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $cancelUrl ?? config('app.url').'/driver/subscription/cancel';

        return CheckoutSession::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($currency),
                    'product_data' => [
                        'name' => $metadata['plan_name'] ?? 'Driver Subscription Plan',
                    ],
                    'unit_amount' => (int) round($amount * 100),
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Create or sync a Product in Stripe.
     */
    public function createProduct(string $name, ?string $description = null): Product
    {
        return Product::create([
            'name' => $name,
            'description' => $description,
        ]);
    }

    /**
     * Create a Price in Stripe.
     */
    public function createPrice(string $productId, float $amountEur): Price
    {
        return Price::create([
            'product' => $productId,
            'unit_amount' => (int) round($amountEur * 100),
            'currency' => 'eur',
        ]);
    }

    /**
     * Verify Stripe Webhook Signature.
     */
    public function verifyWebhook(string $payload, string $signatureHeader): Event
    {
        $webhookSecret = config('services.stripe.webhook_secret', env('STRIPE_WEBHOOK_SECRET'));

        return Webhook::constructEvent($payload, $signatureHeader, $webhookSecret);
    }

    /**
     * Create a refund for a payment intent.
     */
    public function refund(string $paymentIntentId, ?float $amount = null): Refund
    {
        $params = ['payment_intent' => $paymentIntentId];
        if ($amount !== null) {
            $params['amount'] = (int) round($amount * 100);
        }

        return Refund::create($params);
    }
}
