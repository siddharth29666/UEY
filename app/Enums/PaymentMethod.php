<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case WALLET = 'wallet';
}
