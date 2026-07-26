<?php

namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Ride',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'rider_id', type: 'integer', example: 2),
        new OA\Property(property: 'driver_profile_id', type: 'integer', nullable: true, example: 3),
        new OA\Property(property: 'vehicle_type_id', type: 'integer', example: 1),
        new OA\Property(property: 'pickup_address', type: 'string', example: 'London Eye, London'),
        new OA\Property(property: 'pickup_latitude', type: 'number', format: 'float', example: 51.5074),
        new OA\Property(property: 'pickup_longitude', type: 'number', format: 'float', example: -0.1278),
        new OA\Property(property: 'destination_address', type: 'string', example: "Regent's Park, London"),
        new OA\Property(property: 'destination_latitude', type: 'number', format: 'float', example: 51.5204),
        new OA\Property(property: 'destination_longitude', type: 'number', format: 'float', example: -0.1482),
        new OA\Property(property: 'status', type: 'string', example: 'pending'),
        new OA\Property(property: 'otp', type: 'string', example: '123456'),
        new OA\Property(property: 'estimated_distance', type: 'number', format: 'float', example: 2.34),
        new OA\Property(property: 'estimated_duration', type: 'integer', example: 4),
        new OA\Property(property: 'estimated_fare', type: 'number', format: 'float', example: 8.50),
        new OA\Property(property: 'actual_distance', type: 'number', format: 'float', nullable: true, example: null),
        new OA\Property(property: 'actual_duration', type: 'integer', nullable: true, example: null),
        new OA\Property(property: 'actual_fare', type: 'number', format: 'float', nullable: true, example: null),
        new OA\Property(property: 'accepted_at', type: 'string', format: 'date-time', nullable: true, example: null),
        new OA\Property(property: 'arrived_at', type: 'string', format: 'date-time', nullable: true, example: null),
        new OA\Property(property: 'started_at', type: 'string', format: 'date-time', nullable: true, example: null),
        new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', nullable: true, example: null),
        new OA\Property(property: 'cancelled_at', type: 'string', format: 'date-time', nullable: true, example: null),
        new OA\Property(property: 'cancelled_by', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'cancel_reason', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-06-24T00:58:13+05:30'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-06-24T00:58:13+05:30'),
    ]
)]

#[OA\Schema(
    schema: 'RideRequest',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'ride_id', type: 'integer', example: 1),
        new OA\Property(property: 'driver_profile_id', type: 'integer', example: 3),
        new OA\Property(property: 'status', type: 'string', example: 'pending'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', example: '2026-06-24T01:28:13+05:30'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-06-24T00:58:13+05:30'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-06-24T00:58:13+05:30'),
    ]
)]
#[OA\Schema(
    schema: 'Payment',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 7),
        new OA\Property(property: 'ride_id', type: 'integer', example: 12),
        new OA\Property(property: 'rider_id', type: 'integer', example: 1),
        new OA\Property(property: 'driver_profile_id', type: 'integer', nullable: true, example: 3),
        new OA\Property(property: 'payment_method', type: 'string', example: 'wallet'),
        new OA\Property(property: 'payment_status', type: 'string', example: 'paid'),
        new OA\Property(property: 'transaction_reference', type: 'string', example: 'PAY-20260704-000007'),
        new OA\Property(property: 'subtotal', type: 'number', format: 'float', example: 15.00),
        new OA\Property(property: 'tax', type: 'number', format: 'float', example: 0.00),
        new OA\Property(property: 'discount', type: 'number', format: 'float', example: 0.00),
        new OA\Property(property: 'platform_commission', type: 'number', format: 'float', example: 2.25),
        new OA\Property(property: 'driver_earning', type: 'number', format: 'float', example: 12.75),
        new OA\Property(property: 'total', type: 'number', format: 'float', example: 15.00),
        new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'RideReview',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'ride_id', type: 'integer', example: 12),
        new OA\Property(property: 'reviewer_id', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'reviewee_id', type: 'integer', example: 3),
        new OA\Property(property: 'rating', type: 'integer', example: 5),
        new OA\Property(property: 'review', type: 'string', nullable: true, example: 'Excellent trip, clean vehicle!'),
        new OA\Property(property: 'review_tags', type: 'array', items: new OA\Items(type: 'string'), nullable: true, example: ['polite', 'safe_driver']),
        new OA\Property(property: 'is_anonymous', type: 'boolean', example: false),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'WalletTopup',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'wallet_id', type: 'integer', example: 1),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 50.00),
        new OA\Property(property: 'stripe_payment_intent', type: 'string', example: 'pi_3MtwJD2eZvKYlo2C0DGk4'),
        new OA\Property(property: 'payment_status', type: 'string', example: 'pending'),
        new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'WithdrawalRequest',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'wallet_id', type: 'integer', example: 1),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 100.00),
        new OA\Property(property: 'status', type: 'string', example: 'pending'),
        new OA\Property(property: 'bank_account_id', type: 'integer', nullable: true, example: 2),
        new OA\Property(property: 'admin_note', type: 'string', nullable: true, example: 'Approved.'),
        new OA\Property(property: 'processed_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'Wallet',
    properties: [
        new OA\Property(property: 'balance', type: 'number', format: 'float', example: 150.00),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        new OA\Property(property: 'last_transaction', ref: '#/components/schemas/WalletTransaction', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'WalletTransaction',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'transaction_type', type: 'string', example: 'top_up'),
        new OA\Property(property: 'type', type: 'string', example: 'credit'),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 50.00),
        new OA\Property(property: 'balance_before', type: 'number', format: 'float', example: 100.00),
        new OA\Property(property: 'balance_after', type: 'number', format: 'float', example: 150.00),
        new OA\Property(property: 'status', type: 'string', example: 'completed'),
        new OA\Property(property: 'reference', type: 'string', nullable: true, example: 'topup_1'),
        new OA\Property(property: 'remarks', type: 'string', nullable: true, example: 'Stripe wallet top-up completed'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]

#[OA\Schema(
    schema: 'UserDevice',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'user_id', type: 'integer', example: 1),
        new OA\Property(property: 'device_type', type: 'string', example: 'android'),
        new OA\Property(property: 'device_name', type: 'string', example: 'Pixel 7 Pro'),
        new OA\Property(property: 'device_token', type: 'string', example: 'fcm_token_xyz'),
        new OA\Property(property: 'platform', type: 'string', example: 'Android'),
        new OA\Property(property: 'os_version', type: 'string', example: '13.0', nullable: true),
        new OA\Property(property: 'app_version', type: 'string', example: '1.0.0', nullable: true),
        new OA\Property(property: 'language', type: 'string', example: 'en', nullable: true),
        new OA\Property(property: 'timezone', type: 'string', example: 'UTC', nullable: true),
        new OA\Property(property: 'last_used_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]

#[OA\Schema(
    schema: 'NotificationLog',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'user_id', type: 'integer', example: 1),
        new OA\Property(property: 'title', type: 'string', example: 'Ride Update'),
        new OA\Property(property: 'body', type: 'string', example: 'Your driver is arriving.'),
        new OA\Property(property: 'type', type: 'string', example: 'driver_arriving'),
        new OA\Property(property: 'category', type: 'string', example: 'ride'),
        new OA\Property(property: 'priority', type: 'string', example: 'high'),
        new OA\Property(property: 'payload', type: 'object', nullable: true),
        new OA\Property(property: 'status', type: 'string', example: 'sent'),
        new OA\Property(property: 'firebase_message_id', type: 'string', nullable: true, example: 'projects/uey/messages/0:16234...'),
        new OA\Property(property: 'failure_reason', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'sent_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'read_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'AuditLogResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'admin_id', type: 'integer', example: 1),
        new OA\Property(property: 'admin_name', type: 'string', example: 'Alice Admin'),
        new OA\Property(property: 'ip_address', type: 'string', example: '127.0.0.1'),
        new OA\Property(property: 'user_agent', type: 'string', example: 'Mozilla/5.0'),
        new OA\Property(property: 'module', type: 'string', example: 'users'),
        new OA\Property(property: 'action', type: 'string', example: 'user_block'),
        new OA\Property(property: 'affected_table', type: 'string', example: 'users'),
        new OA\Property(property: 'affected_record_id', type: 'integer', example: 2),
        new OA\Property(property: 'old_values', type: 'object', nullable: true),
        new OA\Property(property: 'new_values', type: 'object', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'RiderResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 2),
        new OA\Property(property: 'name', type: 'string', example: 'John Rider'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john.rider@example.com'),
        new OA\Property(property: 'phone', type: 'string', example: '+447911111111'),
        new OA\Property(property: 'role', type: 'string', example: 'rider'),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        new OA\Property(property: 'avatar_url', type: 'string', nullable: true),
        new OA\Property(property: 'last_login_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'DriverResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 3),
        new OA\Property(property: 'name', type: 'string', example: 'Bob Driver'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'bob.driver@example.com'),
        new OA\Property(property: 'phone', type: 'string', example: '+447922222222'),
        new OA\Property(property: 'role', type: 'string', example: 'driver'),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        new OA\Property(property: 'avatar_url', type: 'string', nullable: true),
        new OA\Property(property: 'last_login_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'DashboardResource',
    properties: [
        new OA\Property(property: 'metrics', type: 'object'),
        new OA\Property(property: 'charts', type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'PromoCodeResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'code', type: 'string', example: 'HELLO50'),
        new OA\Property(property: 'discount_type', type: 'string', example: 'percentage'),
        new OA\Property(property: 'discount_value', type: 'number', format: 'float', example: 50.00),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'usage_limit', type: 'integer', nullable: true, example: 100),
        new OA\Property(property: 'used_count', type: 'integer', example: 0),
        new OA\Property(property: 'per_user_limit', type: 'integer', example: 1),
        new OA\Property(property: 'min_fare', type: 'number', format: 'float', example: 10.00),
        new OA\Property(property: 'max_discount', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'first_ride_only', type: 'boolean', example: false),
        new OA\Property(property: 'referral_coupon', type: 'boolean', example: false),
        new OA\Property(property: 'ride_eligibility', type: 'array', items: new OA\Items(type: 'integer'), nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'SettingResource',
    properties: [
        new OA\Property(property: 'key', type: 'string', example: 'currency'),
        new OA\Property(property: 'value', type: 'string', example: 'USD'),
    ]
)]
#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'John Rider'),
        new OA\Property(property: 'email', type: 'string', example: 'john.rider@example.com'),
        new OA\Property(property: 'phone', type: 'string', example: '+447911123456'),
        new OA\Property(property: 'role', type: 'string', example: 'rider'),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        new OA\Property(property: 'avatar_url', type: 'string', nullable: true, example: null),
    ]
)]
#[OA\Schema(
    schema: 'DriverDocumentResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'document_type', type: 'string', example: 'driving_license'),
        new OA\Property(property: 'document_path', type: 'string', example: 'documents/driving_license.jpg'),
        new OA\Property(property: 'document_url', type: 'string', format: 'url', example: 'http://uey.test/storage/documents/driving_license.jpg'),
        new OA\Property(property: 'status', type: 'string', example: 'pending'),
        new OA\Property(property: 'rejection_reason', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date', example: '2028-12-31'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'ReviewResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'ride_id', type: 'integer', example: 12),
        new OA\Property(property: 'reviewer_name', type: 'string', example: 'John Rider'),
        new OA\Property(property: 'rating', type: 'integer', example: 5),
        new OA\Property(property: 'review', type: 'string', nullable: true, example: 'Great driver!'),
        new OA\Property(property: 'review_tags', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
        new OA\Property(property: 'is_anonymous', type: 'boolean', example: false),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'RideResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'rider_id', type: 'integer', example: 2),
        new OA\Property(property: 'pickup_address', type: 'string', example: 'London Eye'),
        new OA\Property(property: 'destination_address', type: 'string', example: "Regent's Park"),
        new OA\Property(property: 'status', type: 'string', example: 'pending'),
        new OA\Property(property: 'estimated_fare', type: 'number', format: 'float', example: 10.00),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'WalletResource',
    properties: [
        new OA\Property(property: 'balance', type: 'number', format: 'float', example: 100.00),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
    ]
)]
#[OA\Schema(
    schema: 'WalletTransactionResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'transaction_type', type: 'string', example: 'top_up'),
        new OA\Property(property: 'type', type: 'string', example: 'credit'),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 50.00),
        new OA\Property(property: 'balance_before', type: 'number', format: 'float', example: 100.00),
        new OA\Property(property: 'balance_after', type: 'number', format: 'float', example: 150.00),
        new OA\Property(property: 'status', type: 'string', example: 'completed'),
        new OA\Property(property: 'reference', type: 'string', nullable: true, example: 'topup_1'),
        new OA\Property(property: 'remarks', type: 'string', nullable: true, example: 'Stripe wallet top-up completed'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'Conversation',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'ride_id', type: 'integer', example: 1),
        new OA\Property(property: 'driver_id', type: 'integer', example: 2),
        new OA\Property(property: 'rider_id', type: 'integer', example: 3),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'Message',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'conversation_thread_id', type: 'integer', example: 1),
        new OA\Property(property: 'sender_id', type: 'integer', example: 3),
        new OA\Property(property: 'message', type: 'string', example: 'Hello, where are you?'),
        new OA\Property(property: 'type', type: 'string', example: 'text'),
        new OA\Property(property: 'status', type: 'string', example: 'sent'),
        new OA\Property(property: 'delivered_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'read_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'Tracking',
    properties: [
        new OA\Property(property: 'driver', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer', example: 2),
            new OA\Property(property: 'name', type: 'string', example: 'Bob Driver'),
        ], nullable: true),
        new OA\Property(property: 'vehicle', type: 'object', properties: [
            new OA\Property(property: 'make', type: 'string', example: 'Toyota'),
            new OA\Property(property: 'model', type: 'string', example: 'Prius'),
            new OA\Property(property: 'plate', type: 'string', example: 'AB12 CDE'),
        ], nullable: true),
        new OA\Property(property: 'coordinates', type: 'object', properties: [
            new OA\Property(property: 'latitude', type: 'number', format: 'float', example: 51.5074),
            new OA\Property(property: 'longitude', type: 'number', format: 'float', example: -0.1278),
        ], nullable: true),
        new OA\Property(property: 'heading', type: 'number', format: 'float', example: 120.0, nullable: true),
        new OA\Property(property: 'speed', type: 'number', format: 'float', example: 45.0, nullable: true),
        new OA\Property(property: 'eta', type: 'object', properties: [
            new OA\Property(property: 'remaining_distance', type: 'number', format: 'float', example: 2.50),
            new OA\Property(property: 'remaining_time', type: 'integer', example: 5),
            new OA\Property(property: 'estimated_arrival', type: 'string', format: 'date-time', nullable: true),
        ]),
        new OA\Property(property: 'status', type: 'string', example: 'accepted'),
        new OA\Property(property: 'last_updated', type: 'string', format: 'date-time', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'BroadcastStatus',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Operation completed successfully.'),
        new OA\Property(property: 'data', type: 'object', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'Referral',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'referrer_id', type: 'integer', example: 1),
        new OA\Property(property: 'referred_user_id', type: 'integer', example: 2),
        new OA\Property(property: 'referral_code', type: 'string', example: 'UEY4K8PZ'),
        new OA\Property(property: 'status', type: 'string', example: 'pending'),
        new OA\Property(property: 'first_ride_completed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'referrer_bonus', type: 'number', format: 'float', example: 10.00),
        new OA\Property(property: 'referred_bonus', type: 'number', format: 'float', example: 5.00),
        new OA\Property(property: 'rewarded_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'ReferralHistoryResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'referred_user', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer', example: 2),
            new OA\Property(property: 'name', type: 'string', example: 'Bob Friend'),
            new OA\Property(property: 'phone', type: 'string', example: '+447922222222'),
            new OA\Property(property: 'email', type: 'string', format: 'email', example: 'bob@example.com'),
        ]),
        new OA\Property(property: 'status', type: 'string', example: 'pending'),
        new OA\Property(property: 'first_ride_completed', type: 'boolean', example: false),
        new OA\Property(property: 'first_ride_completed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'ReferralBonusResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 10.00),
        new OA\Property(property: 'type', type: 'string', example: 'credit'),
        new OA\Property(property: 'transaction_type', type: 'string', example: 'referral_bonus'),
        new OA\Property(property: 'status', type: 'string', example: 'completed'),
        new OA\Property(property: 'reference', type: 'string', example: 'ref_bonus_referrer_1'),
        new OA\Property(property: 'remarks', type: 'string', example: 'Referral bonus received'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'FavoritePlaceResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'user_id', type: 'integer', example: 1),
        new OA\Property(property: 'type', type: 'string', example: 'home'),
        new OA\Property(property: 'label', type: 'string', example: 'Home'),
        new OA\Property(property: 'nickname', type: 'string', example: 'Sweet Home', nullable: true),
        new OA\Property(property: 'google_place_id', type: 'string', example: 'place_id_123', nullable: true),
        new OA\Property(property: 'address', type: 'string', example: '221B Baker St'),
        new OA\Property(property: 'latitude', type: 'number', format: 'float', example: 51.5237),
        new OA\Property(property: 'longitude', type: 'number', format: 'float', example: -0.1585),
        new OA\Property(property: 'is_default', type: 'boolean', example: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'EmergencyContactResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'user_id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
        new OA\Property(property: 'phone', type: 'string', example: '+447911111111'),
        new OA\Property(property: 'relationship', type: 'string', example: 'Brother'),
        new OA\Property(property: 'priority', type: 'integer', example: 1),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'EmergencyAlertResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'ride_id', type: 'integer', example: 1),
        new OA\Property(property: 'user_id', type: 'integer', example: 1),
        new OA\Property(property: 'driver_id', type: 'integer', example: 2, nullable: true),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        new OA\Property(property: 'latitude', type: 'number', format: 'float', example: 51.5237),
        new OA\Property(property: 'longitude', type: 'number', format: 'float', example: -0.1585),
        new OA\Property(property: 'message', type: 'string', example: 'Help me!', nullable: true),
        new OA\Property(property: 'attachment', type: 'string', example: 'sos/evidence.jpg', nullable: true),
        new OA\Property(property: 'attachment_type', type: 'string', example: 'photo', nullable: true),
        new OA\Property(property: 'resolved_by', type: 'integer', example: 3, nullable: true),
        new OA\Property(property: 'resolved_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'LedgerResource',
    description: 'Immutable accounting journal entry mirroring a single wallet transaction.',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'wallet_transaction_id', type: 'integer', example: 10),
        new OA\Property(property: 'wallet_id', type: 'integer', example: 2),
        new OA\Property(property: 'user_id', type: 'integer', example: 3),
        new OA\Property(property: 'reference', type: 'string', example: 'RIDE_PAY_001', nullable: true),
        new OA\Property(property: 'transaction_type', type: 'string', example: 'ride_payment'),
        new OA\Property(property: 'direction', type: 'string', enum: ['credit', 'debit'], example: 'debit'),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 12.50),
        new OA\Property(property: 'currency', type: 'string', example: 'GBP'),
        new OA\Property(
            property: 'source',
            type: 'string',
            enum: ['ride_payment', 'wallet_topup', 'withdrawal', 'refund', 'referral_bonus', 'admin_credit', 'admin_debit', 'promo_credit', 'manual_adjustment', 'stripe', 'cash'],
            example: 'ride_payment'
        ),
        new OA\Property(property: 'remarks', type: 'string', nullable: true),
        new OA\Property(property: 'metadata', type: 'object', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'wallet_transaction', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer', example: 10),
            new OA\Property(property: 'type', type: 'string', example: 'debit'),
            new OA\Property(property: 'status', type: 'string', example: 'completed'),
            new OA\Property(property: 'balance_before', type: 'number', format: 'float', example: 100.00),
            new OA\Property(property: 'balance_after', type: 'number', format: 'float', example: 87.50),
        ]),
        new OA\Property(property: 'user', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer', example: 3),
            new OA\Property(property: 'name', type: 'string', example: 'John Rider'),
            new OA\Property(property: 'role', type: 'string', example: 'rider'),
        ]),
    ]
)]

#[OA\Schema(
    schema: 'VehicleType',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Economy'),
        new OA\Property(property: 'capacity', type: 'integer', example: 4),
        new OA\Property(property: 'base_fare', type: 'string', example: '2.50'),
        new OA\Property(property: 'per_km_rate', type: 'string', example: '1.20'),
        new OA\Property(property: 'per_minute_rate', type: 'string', example: '0.30'),
        new OA\Property(property: 'minimum_fare', type: 'string', example: '5.00'),
        new OA\Property(property: 'commission_percentage', type: 'string', example: '15.00'),
        new OA\Property(property: 'icon_url', type: 'string', nullable: true, example: 'https://uey-assets.s3.amazonaws.com/vehicle-types/icons/sedan.png'),
        new OA\Property(property: 'active', type: 'boolean', example: true),
    ]
)]

#[OA\Schema(
    schema: 'PromoResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'code', type: 'string', example: 'WELCOME50'),
        new OA\Property(property: 'discount_type', type: 'string', example: 'percentage'),
        new OA\Property(property: 'discount_value', type: 'string', example: '50.00'),
        new OA\Property(property: 'max_discount', type: 'string', nullable: true, example: '200.00'),
        new OA\Property(property: 'minimum_fare', type: 'string', example: '300.00'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', example: '2026-12-31T23:59:59Z'),
        new OA\Property(property: 'first_ride_only', type: 'boolean', example: true),
        new OA\Property(property: 'eligible', type: 'boolean', example: true),
    ]
)]

#[OA\Schema(
    schema: 'PromoHistoryResource',
    properties: [
        new OA\Property(property: 'ride_id', type: 'integer', example: 55),
        new OA\Property(property: 'promo_code', type: 'string', example: 'WELCOME50'),
        new OA\Property(property: 'discount_amount', type: 'string', example: '120.00'),
        new OA\Property(property: 'status', type: 'string', example: 'completed'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]

#[OA\Schema(
    schema: 'SubscriptionPlanResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Monthly Starter'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: '20 Ride Credits'),
        new OA\Property(property: 'price_eur', type: 'number', format: 'float', example: 10.00),
        new OA\Property(property: 'price', type: 'number', format: 'float', example: 10.00),
        new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
        new OA\Property(property: 'ride_credits', type: 'integer', example: 20),
        new OA\Property(property: 'duration_days', type: 'integer', example: 30),
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'sort_order', type: 'integer', example: 1),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]

#[OA\Schema(
    schema: 'DriverSubscriptionResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'driver_profile_id', type: 'integer', example: 1),
        new OA\Property(property: 'subscription_plan_id', type: 'integer', example: 1),
        new OA\Property(property: 'plan', ref: '#/components/schemas/SubscriptionPlanResource', nullable: true),
        new OA\Property(property: 'amount_eur', type: 'number', format: 'float', example: 10.00),
        new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
        new OA\Property(property: 'credits_allocated', type: 'integer', example: 20),
        new OA\Property(property: 'credits_used', type: 'integer', example: 5),
        new OA\Property(property: 'credits_remaining', type: 'integer', example: 15),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        new OA\Property(property: 'payment_source', type: 'string', example: 'wallet'),
        new OA\Property(property: 'starts_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'days_remaining', type: 'integer', example: 25),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]

#[OA\Schema(
    schema: 'DriverCreditTransactionResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'driver_profile_id', type: 'integer', example: 1),
        new OA\Property(property: 'driver_subscription_id', type: 'integer', example: 1),
        new OA\Property(property: 'ride_id', type: 'integer', nullable: true, example: 5),
        new OA\Property(property: 'ride_request_id', type: 'integer', nullable: true, example: 5),
        new OA\Property(property: 'type', type: 'string', example: 'deduction'),
        new OA\Property(property: 'amount', type: 'integer', example: 1),
        new OA\Property(property: 'balance_before', type: 'integer', example: 20),
        new OA\Property(property: 'balance_after', type: 'integer', example: 19),
        new OA\Property(property: 'reference', type: 'string', nullable: true, example: 'ACCEPT_RIDE_5'),
        new OA\Property(property: 'metadata', type: 'object', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]

#[OA\Schema(
    schema: 'CancellationReasonResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'reason', type: 'string', example: 'Driver taking too long'),
        new OA\Property(property: 'type', type: 'string', enum: ['rider', 'driver', 'both'], example: 'rider'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'sort_order', type: 'integer', example: 1),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]

#[OA\Schema(
    schema: 'FaqCategoryResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Payments & Fare'),
        new OA\Property(property: 'slug', type: 'string', example: 'payments-and-fare'),
        new OA\Property(property: 'icon', type: 'string', nullable: true, example: 'credit-card'),
        new OA\Property(property: 'audience', type: 'string', enum: ['rider', 'driver', 'both'], example: 'both'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'sort_order', type: 'integer', example: 1),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]

#[OA\Schema(
    schema: 'FaqResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'faq_category_id', type: 'integer', example: 1),
        new OA\Property(property: 'category_name', type: 'string', example: 'Payments & Fare'),
        new OA\Property(property: 'question', type: 'string', example: 'How do I request a ride?'),
        new OA\Property(property: 'answer', type: 'string', example: 'Open the app, enter pickup and drop-off, select vehicle type and request.'),
        new OA\Property(property: 'audience', type: 'string', enum: ['rider', 'driver', 'both'], example: 'rider'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'sort_order', type: 'integer', example: 1),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]

#[OA\Schema(
    schema: 'ContactSubmissionResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'user_id', type: 'integer', nullable: true, example: 5),
        new OA\Property(property: 'name', type: 'string', example: 'John Rider'),
        new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+1234567890'),
        new OA\Property(property: 'subject', type: 'string', example: 'App Billing Question'),
        new OA\Property(property: 'message', type: 'string', example: 'Question regarding trip charge.'),
        new OA\Property(property: 'status', type: 'string', enum: ['new', 'read', 'in_progress', 'resolved', 'archived'], example: 'new'),
        new OA\Property(property: 'admin_notes', type: 'string', nullable: true, example: 'Resolved via email.'),
        new OA\Property(property: 'responded_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]

#[OA\Schema(
    schema: 'LegalPageResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'slug', type: 'string', example: 'privacy-policy'),
        new OA\Property(property: 'title', type: 'string', example: 'Privacy Policy'),
        new OA\Property(property: 'content', type: 'string', example: 'UEY Privacy Policy terms...'),
        new OA\Property(property: 'version', type: 'string', example: '1.0'),
        new OA\Property(property: 'is_published', type: 'boolean', example: true),
        new OA\Property(property: 'published_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]

#[OA\Schema(
    schema: 'VehicleResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'vehicle_type_id', type: 'integer', example: 1),
        new OA\Property(property: 'make', type: 'string', example: 'Toyota'),
        new OA\Property(property: 'model', type: 'string', example: 'Camry'),
        new OA\Property(property: 'year', type: 'integer', example: 2022),
        new OA\Property(property: 'color', type: 'string', example: 'Black'),
        new OA\Property(property: 'plate_number', type: 'string', example: 'UEY-7788'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'approved', 'rejected'], example: 'pending'),
        new OA\Property(property: 'rejection_reason', type: 'string', nullable: true, example: 'Plate number mismatch'),
        new OA\Property(property: 'driver', type: 'object', nullable: true),
        new OA\Property(property: 'vehicle_type', type: 'object', nullable: true),
    ]
)]
class ModelSchemas {}
