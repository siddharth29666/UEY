<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case RIDE = 'ride';
    case WALLET = 'wallet';
    case PAYMENT = 'payment';
    case REVIEW = 'review';
    case PROMOTION = 'promotion';
    case ADMIN = 'admin';
    case SYSTEM = 'system';
    case DRIVER = 'driver';
}
