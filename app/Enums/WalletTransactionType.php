<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    case TOP_UP = 'top_up';
    case RIDE_PAYMENT = 'ride_payment';
    case RIDE_EARNING = 'ride_earning';
    case WITHDRAWAL = 'withdrawal';
    case REFUND = 'refund';
    case REFERRAL_BONUS = 'referral_bonus';
    case ADMIN_CREDIT = 'admin_credit';
    case ADMIN_DEBIT = 'admin_debit';
    case SUBSCRIPTION_PURCHASE = 'subscription_purchase';
}
