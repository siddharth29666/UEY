<?php

namespace App\DTOs;

use App\Models\PromoCode;

class PromoValidationResult
{
    public function __construct(
        public readonly bool $isValid,
        public readonly ?string $reason = null,
        public readonly float $discountAmount = 0.00,
        public readonly float $finalFare = 0.00,
        public readonly ?PromoCode $promoCode = null
    ) {}
}
