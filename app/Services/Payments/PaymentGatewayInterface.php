<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Ride;

interface PaymentGatewayInterface
{
    /**
     * Process the payment for a completed ride.
     *
     * @param Payment $payment
     * @param Ride $ride
     * @return string Returns transaction reference
     * @throws \Exception
     */
    public function process(Payment $payment, Ride $ride): string;
}
