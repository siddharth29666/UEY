<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Ride;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\CashGateway;
use App\Services\Payments\WalletGateway;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Resolve the corresponding payment gateway implementation.
     */
    public function resolveGateway(string $method): PaymentGatewayInterface
    {
        return match ($method) {
            'cash' => new CashGateway(),
            'wallet' => new WalletGateway(),
            default => throw new \Exception("Unsupported payment method: {$method}"),
        };
    }

    /**
     * Process payment for a completed ride.
     */
    public function processPaymentForRide(Ride $ride): Payment
    {
        // 1. Idempotency Check:
        $existingPayment = Payment::where('ride_id', $ride->id)->first();
        if ($existingPayment && $existingPayment->payment_status === PaymentStatus::PAID) {
            return $existingPayment;
        }

        // Calculations
        $subtotal = (float) $ride->actual_fare;
        $tax = 0.00;
        $discount = 0.00;
        $total = $subtotal;

        $commissionRate = (float) config('services.payments.commission_rate', 15.0);
        $commission = round($subtotal * ($commissionRate / 100), 2);
        $driverEarning = round($subtotal - $commission, 2);

        try {
            return DB::transaction(function () use ($ride, $existingPayment, $subtotal, $tax, $discount, $total, $commission, $driverEarning) {
                // Re-use existing failed/pending payment or create a new one
                $payment = $existingPayment ?? Payment::create([
                    'ride_id' => $ride->id,
                    'rider_id' => $ride->rider_id,
                    'driver_profile_id' => $ride->driver_profile_id,
                    'payment_method' => $ride->payment_method,
                    'payment_status' => PaymentStatus::PENDING,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'discount' => $discount,
                    'platform_commission' => $commission,
                    'driver_earning' => $driverEarning,
                    'total' => $total,
                ]);

                // Resolve gateway
                $gateway = $this->resolveGateway($ride->payment_method->value);

                // Process payment via gateway
                $reference = $gateway->process($payment, $ride);

                // Update payment status
                $payment->update([
                    'payment_status' => PaymentStatus::PAID,
                    'transaction_reference' => $reference,
                    'paid_at' => now(),
                ]);

                // Update ride payment status
                $ride->update([
                    'payment_status' => 'paid',
                ]);

                event(new \App\Events\PaymentSucceededEvent($ride->rider, \App\Enums\NotificationType::PAYMENT_SUCCESS, null, null, ['amount' => $total, 'ride_id' => $ride->id]));

                return $payment;
            });
        } catch (\Exception $e) {
            // Write failed payment record outside the rolled back transaction
            $payment = Payment::updateOrCreate(
                ['ride_id' => $ride->id],
                [
                    'rider_id' => $ride->rider_id,
                    'driver_profile_id' => $ride->driver_profile_id,
                    'payment_method' => $ride->payment_method,
                    'payment_status' => PaymentStatus::FAILED,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'discount' => $discount,
                    'platform_commission' => $commission,
                    'driver_earning' => $driverEarning,
                    'total' => $total,
                ]
            );

            $ride->update([
                'payment_status' => 'failed',
            ]);

            event(new \App\Events\PaymentFailedEvent($ride->rider, \App\Enums\NotificationType::PAYMENT_FAILED, null, null, ['amount' => $total, 'ride_id' => $ride->id]));

            throw $e;
        }
    }

    /**
     * Generate complete invoice details for a completed ride.
     */
    public function generateInvoice(Ride $ride): array
    {
        $payment = $ride->payment;
        if (!$payment) {
            throw new \Exception("No payment record found for this ride.");
        }

        return [
            'ride_id' => $ride->id,
            'pickup_address' => $ride->pickup_address,
            'destination_address' => $ride->destination_address,
            'distance' => (float) $ride->actual_distance,
            'duration' => (int) $ride->actual_duration,
            'payment_method' => $ride->payment_method->value,
            'payment_status' => $ride->payment_status,
            'transaction_reference' => $payment->transaction_reference,
            'completed_at' => $ride->completed_at?->toIso8601String(),
            'rider' => [
                'id' => $ride->rider->id,
                'name' => $ride->rider->name,
            ],
            'driver' => $ride->driverProfile ? [
                'id' => $ride->driverProfile->id,
                'name' => $ride->driverProfile->user->name,
            ] : null,
            'fare_breakdown' => [
                'subtotal' => (float) $payment->subtotal,
                'tax' => (float) $payment->tax,
                'discount' => (float) $payment->discount,
                'platform_commission' => (float) $payment->platform_commission,
                'driver_earning' => (float) $payment->driver_earning,
                'total' => (float) $payment->total,
            ],
            'paid_at' => $payment->paid_at?->toIso8601String(),
        ];
    }
}
