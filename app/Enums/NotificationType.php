<?php

namespace App\Enums;

enum NotificationType: string
{
    case RIDE_REQUESTED = 'ride_requested';
    case RIDE_ACCEPTED = 'ride_accepted';
    case DRIVER_ARRIVING = 'driver_arriving';
    case DRIVER_ARRIVED = 'driver_arrived';
    case RIDE_STARTED = 'ride_started';
    case RIDE_COMPLETED = 'ride_completed';
    case RIDE_CANCELLED = 'ride_cancelled';

    case WALLET_TOPUP = 'wallet_topup';
    case WALLET_CREDIT = 'wallet_credit';
    case WALLET_DEBIT = 'wallet_debit';
    case WITHDRAW_REQUESTED = 'withdraw_requested';
    case WITHDRAW_APPROVED = 'withdraw_approved';
    case WITHDRAW_REJECTED = 'withdraw_rejected';
    case WITHDRAW_COMPLETED = 'withdraw_completed';

    case PAYMENT_SUCCESS = 'payment_success';
    case PAYMENT_FAILED = 'payment_failed';

    case REVIEW_RECEIVED = 'review_received';

    case DRIVER_DOCUMENT_APPROVED = 'driver_document_approved';
    case DRIVER_DOCUMENT_REJECTED = 'driver_document_rejected';

    case ADMIN_NOTIFICATION = 'admin_notification';
    case PROMOTION = 'promotion';
    case COUPON = 'coupon';
    case SYSTEM = 'system';
    case REFERRAL_BONUS = 'referral_bonus';

    /**
     * Get category associated with the notification type.
     */
    public function category(): NotificationCategory
    {
        return match ($this) {
            self::RIDE_REQUESTED,
            self::RIDE_ACCEPTED,
            self::DRIVER_ARRIVING,
            self::DRIVER_ARRIVED,
            self::RIDE_STARTED,
            self::RIDE_COMPLETED,
            self::RIDE_CANCELLED => NotificationCategory::RIDE,

            self::WALLET_TOPUP,
            self::WALLET_CREDIT,
            self::WALLET_DEBIT,
            self::WITHDRAW_REQUESTED,
            self::WITHDRAW_APPROVED,
            self::WITHDRAW_REJECTED,
            self::WITHDRAW_COMPLETED => NotificationCategory::WALLET,

            self::PAYMENT_SUCCESS,
            self::PAYMENT_FAILED => NotificationCategory::PAYMENT,

            self::REVIEW_RECEIVED => NotificationCategory::REVIEW,

            self::DRIVER_DOCUMENT_APPROVED,
            self::DRIVER_DOCUMENT_REJECTED => NotificationCategory::DRIVER,

            self::ADMIN_NOTIFICATION => NotificationCategory::ADMIN,

            self::PROMOTION,
            self::COUPON => NotificationCategory::PROMOTION,

            self::SYSTEM,
            self::REFERRAL_BONUS => NotificationCategory::SYSTEM,
        };
    }

    /**
     * Get priority associated with the notification type.
     */
    public function priority(): NotificationPriority
    {
        return match ($this) {
            self::RIDE_REQUESTED,
            self::RIDE_ACCEPTED,
            self::DRIVER_ARRIVING,
            self::DRIVER_ARRIVED,
            self::RIDE_STARTED,
            self::RIDE_COMPLETED,
            self::RIDE_CANCELLED,
            self::WALLET_TOPUP,
            self::WALLET_CREDIT,
            self::WALLET_DEBIT,
            self::WITHDRAW_APPROVED,
            self::WITHDRAW_COMPLETED,
            self::PAYMENT_SUCCESS,
            self::PAYMENT_FAILED => NotificationPriority::HIGH,

            self::WITHDRAW_REQUESTED,
            self::WITHDRAW_REJECTED,
            self::REVIEW_RECEIVED,
            self::DRIVER_DOCUMENT_APPROVED,
            self::DRIVER_DOCUMENT_REJECTED,
            self::ADMIN_NOTIFICATION,
            self::REFERRAL_BONUS => NotificationPriority::NORMAL,

            self::PROMOTION,
            self::COUPON,
            self::SYSTEM => NotificationPriority::LOW,
        };
    }
}
