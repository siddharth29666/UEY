<?php

namespace App\DTOs;

use App\Models\WalletTopup;

class TopupResultDTO
{
    public function __construct(
        public readonly WalletTopup $walletTopup,
        public readonly string $clientSecret
    ) {}
}
