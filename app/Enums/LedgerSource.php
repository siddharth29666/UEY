<?php

namespace App\Enums;

enum LedgerSource: string
{
    case RIDE_PAYMENT = 'ride_payment';
    case WALLET_TOPUP = 'wallet_topup';
    case WITHDRAWAL = 'withdrawal';
    case REFUND = 'refund';
    case REFERRAL_BONUS = 'referral_bonus';
    case ADMIN_CREDIT = 'admin_credit';
    case ADMIN_DEBIT = 'admin_debit';
    case PROMO_CREDIT = 'promo_credit';
    case MANUAL_ADJUSTMENT = 'manual_adjustment';
    case STRIPE = 'stripe';
    case CASH = 'cash';
    case SUBSCRIPTION_PURCHASE = 'subscription_purchase';
}
