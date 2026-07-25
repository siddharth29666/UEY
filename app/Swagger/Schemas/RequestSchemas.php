<?php

namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SendOtpRequest',
    required: ['phone', 'type'],
    properties: [
        new OA\Property(property: 'phone', type: 'string', example: '+447911123456'),
        new OA\Property(property: 'type', type: 'string', enum: ['register', 'login', 'password_reset'], example: 'register'),
    ]
)]

#[OA\Schema(
    schema: 'VerifyOtpRequest',
    required: ['phone', 'code', 'type'],
    properties: [
        new OA\Property(property: 'phone', type: 'string', example: '+447911123456'),
        new OA\Property(property: 'code', type: 'string', example: '123456'),
        new OA\Property(property: 'type', type: 'string', enum: ['register', 'login', 'password_reset'], example: 'register'),
    ]
)]

#[OA\Schema(
    schema: 'RegisterRiderRequest',
    required: ['name', 'phone', 'password'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'John Rider'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john.rider@example.com'),
        new OA\Property(property: 'phone', type: 'string', example: '+447911123456'),
        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
        new OA\Property(property: 'referral_code', type: 'string', example: 'ABC12345', nullable: true),
    ]
)]

#[OA\Schema(
    schema: 'RegisterDriverRequest',
    required: [
        'name', 'phone', 'password', 'license_number', 'license_expiry',
        'vehicle_make', 'vehicle_model', 'vehicle_year', 'vehicle_color', 'vehicle_plate', 'vehicle_type_id',
    ],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Bob Driver'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'bob.driver@example.com'),
        new OA\Property(property: 'phone', type: 'string', example: '+447911999999'),
        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
        new OA\Property(property: 'license_number', type: 'string', example: 'DL-999888'),
        new OA\Property(property: 'license_expiry', type: 'string', format: 'date', example: '2027-06-21'),
        new OA\Property(property: 'vehicle_make', type: 'string', example: 'Toyota'),
        new OA\Property(property: 'vehicle_model', type: 'string', example: 'Prius'),
        new OA\Property(property: 'vehicle_year', type: 'integer', example: 2022),
        new OA\Property(property: 'vehicle_color', type: 'string', example: 'Silver'),
        new OA\Property(property: 'vehicle_plate', type: 'string', example: 'ABC-999'),
        new OA\Property(property: 'vehicle_type_id', type: 'integer', example: 1),
        new OA\Property(property: 'referral_code', type: 'string', example: 'ABC12345', nullable: true),
    ]
)]

#[OA\Schema(
    schema: 'LoginRequest',
    required: ['phone', 'password'],
    properties: [
        new OA\Property(property: 'phone', type: 'string', example: '+447911123456'),
        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
    ]
)]

#[OA\Schema(
    schema: 'UpdateProfileRequest',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Jane Updated'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jane.updated@example.com'),
        new OA\Property(property: 'avatar_url', type: 'string', format: 'url', example: 'https://example.com/avatar.png'),
        new OA\Property(property: 'email_notifications', type: 'boolean', example: true),
        new OA\Property(property: 'sms_notifications', type: 'boolean', example: false),
        new OA\Property(property: 'push_notifications', type: 'boolean', example: true),
        new OA\Property(property: 'default_navigation', type: 'string', enum: ['google_maps', 'waze', 'apple_maps'], example: 'google_maps'),
        new OA\Property(property: 'auto_accept', type: 'boolean', example: true),
        new OA\Property(property: 'vehicle_type_id', type: 'integer', example: 1, description: 'Vehicle type ID (driver only)'),
    ]
)]

#[OA\Schema(
    schema: 'SaveBankAccountRequest',
    required: ['bank_name', 'account_holder_name', 'account_number'],
    properties: [
        new OA\Property(property: 'bank_name', type: 'string', example: 'Chase Bank'),
        new OA\Property(property: 'account_holder_name', type: 'string', example: 'Bob Driver'),
        new OA\Property(property: 'account_number', type: 'string', example: '1234567890'),
        new OA\Property(property: 'routing_number', type: 'string', example: '987654321'),
        new OA\Property(property: 'swift_code', type: 'string', example: 'CHASUS33'),
    ]
)]

#[OA\Schema(
    schema: 'VerifyDocumentRequest',
    required: ['status'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['approved', 'rejected'], example: 'approved'),
        new OA\Property(property: 'rejection_reason', type: 'string', example: 'The document image is blurry.'),
    ]
)]

#[OA\Schema(
    schema: 'UpdateDriverStatusRequest',
    required: ['is_online'],
    properties: [
        new OA\Property(property: 'is_online', type: 'boolean', example: true),
    ]
)]

#[OA\Schema(
    schema: 'UpdateDriverLocationRequest',
    required: ['current_latitude', 'current_longitude'],
    properties: [
        new OA\Property(property: 'current_latitude', type: 'number', format: 'float', example: 51.5074),
        new OA\Property(property: 'current_longitude', type: 'number', format: 'float', example: -0.1278),
        new OA\Property(property: 'bearing', type: 'number', format: 'float', example: 180.5),
    ]
)]

#[OA\Schema(
    schema: 'EstimateRideRequest',
    required: ['pickup_latitude', 'pickup_longitude', 'destination_latitude', 'destination_longitude'],
    properties: [
        new OA\Property(property: 'pickup_latitude', type: 'number', format: 'float', example: 51.5074),
        new OA\Property(property: 'pickup_longitude', type: 'number', format: 'float', example: -0.1278),
        new OA\Property(property: 'destination_latitude', type: 'number', format: 'float', example: 51.5204),
        new OA\Property(property: 'destination_longitude', type: 'number', format: 'float', example: -0.1482),
        new OA\Property(property: 'promo_code', type: 'string', example: 'WELCOME50', nullable: true),
    ]
)]

#[OA\Schema(
    schema: 'RequestRideRequest',
    required: ['pickup_latitude', 'pickup_longitude', 'pickup_address', 'destination_latitude', 'destination_longitude', 'destination_address', 'vehicle_type_id'],
    properties: [
        new OA\Property(property: 'pickup_latitude', type: 'number', format: 'float', example: 51.5074),
        new OA\Property(property: 'pickup_longitude', type: 'number', format: 'float', example: -0.1278),
        new OA\Property(property: 'pickup_address', type: 'string', example: 'London Eye, London'),
        new OA\Property(property: 'destination_latitude', type: 'number', format: 'float', example: 51.5204),
        new OA\Property(property: 'destination_longitude', type: 'number', format: 'float', example: -0.1482),
        new OA\Property(property: 'destination_address', type: 'string', example: "Regent's Park, London"),
        new OA\Property(property: 'vehicle_type_id', type: 'integer', example: 1),
        new OA\Property(property: 'promo_code', type: 'string', example: 'WELCOME50', nullable: true),
    ]
)]

#[OA\Schema(
    schema: 'CancelRideRequest',
    properties: [
        new OA\Property(property: 'cancel_reason', type: 'string', example: 'Plans changed'),
    ]
)]

#[OA\Schema(
    schema: 'RegisterDeviceRequest',
    required: ['device_type', 'device_name', 'device_token', 'platform'],
    properties: [
        new OA\Property(property: 'device_type', type: 'string', example: 'android'),
        new OA\Property(property: 'device_name', type: 'string', example: 'Pixel 7 Pro'),
        new OA\Property(property: 'device_token', type: 'string', example: 'fcm_token_xyz'),
        new OA\Property(property: 'platform', type: 'string', example: 'Android'),
        new OA\Property(property: 'os_version', type: 'string', example: '13.0', nullable: true),
        new OA\Property(property: 'app_version', type: 'string', example: '1.0.0', nullable: true),
        new OA\Property(property: 'language', type: 'string', example: 'en', nullable: true),
        new OA\Property(property: 'timezone', type: 'string', example: 'UTC', nullable: true),
    ]
)]

#[OA\Schema(
    schema: 'UpdateDeviceRequest',
    properties: [
        new OA\Property(property: 'device_type', type: 'string', example: 'android'),
        new OA\Property(property: 'device_name', type: 'string', example: 'Pixel 7 Pro'),
        new OA\Property(property: 'device_token', type: 'string', example: 'fcm_token_xyz'),
        new OA\Property(property: 'platform', type: 'string', example: 'Android'),
        new OA\Property(property: 'os_version', type: 'string', example: '13.0', nullable: true),
        new OA\Property(property: 'app_version', type: 'string', example: '1.0.0', nullable: true),
        new OA\Property(property: 'language', type: 'string', example: 'en', nullable: true),
        new OA\Property(property: 'timezone', type: 'string', example: 'UTC', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'AdminLoginRequest',
    required: ['phone', 'password'],
    properties: [
        new OA\Property(property: 'phone', type: 'string', example: '+447999999999'),
        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
    ]
)]
#[OA\Schema(
    schema: 'UpdateAdminProfileRequest',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Alice Modified'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'alice.new@example.com'),
        new OA\Property(property: 'avatar_url', type: 'string', format: 'url', example: 'https://example.com/avatar.png'),
    ]
)]
#[OA\Schema(
    schema: 'ChangePasswordRequest',
    required: ['current_password', 'new_password', 'new_password_confirmation'],
    properties: [
        new OA\Property(property: 'current_password', type: 'string', example: 'password123'),
        new OA\Property(property: 'new_password', type: 'string', example: 'newpassword123'),
        new OA\Property(property: 'new_password_confirmation', type: 'string', example: 'newpassword123'),
    ]
)]
#[OA\Schema(
    schema: 'CreditWalletRequest',
    required: ['amount', 'reason'],
    properties: [
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 50.00),
        new OA\Property(property: 'reason', type: 'string', example: 'Referral bonus'),
    ]
)]
#[OA\Schema(
    schema: 'DebitWalletRequest',
    required: ['amount', 'reason'],
    properties: [
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 20.00),
        new OA\Property(property: 'reason', type: 'string', example: 'Cancellation fee debit'),
    ]
)]
#[OA\Schema(
    schema: 'BroadcastNotificationRequest',
    required: ['target', 'title', 'body', 'category', 'priority', 'channels'],
    properties: [
        new OA\Property(property: 'target', type: 'string', enum: ['all_users', 'all_riders', 'all_drivers', 'selected_users', 'selected_riders', 'selected_drivers']),
        new OA\Property(property: 'user_ids', type: 'array', items: new OA\Items(type: 'integer'), nullable: true),
        new OA\Property(property: 'title', type: 'string', example: 'System Update'),
        new OA\Property(property: 'body', type: 'string', example: 'Maintenance starting in 15 minutes.'),
        new OA\Property(property: 'category', type: 'string', enum: ['ride', 'wallet', 'payment', 'review', 'promotion', 'admin', 'system']),
        new OA\Property(property: 'priority', type: 'string', enum: ['high', 'normal', 'low']),
        new OA\Property(property: 'channels', type: 'array', items: new OA\Items(type: 'string', enum: ['push', 'database'])),
    ]
)]
#[OA\Schema(
    schema: 'PromoCodeRequest',
    required: ['code', 'discount_type', 'discount_value', 'expires_at'],
    properties: [
        new OA\Property(property: 'code', type: 'string', example: 'WELCOME10'),
        new OA\Property(property: 'discount_type', type: 'string', enum: ['percentage', 'flat']),
        new OA\Property(property: 'discount_value', type: 'number', format: 'float', example: 10.00),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'usage_limit', type: 'integer', nullable: true),
        new OA\Property(property: 'per_user_limit', type: 'integer', nullable: true),
        new OA\Property(property: 'min_fare', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'max_discount', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'is_active', type: 'boolean', nullable: true),
        new OA\Property(property: 'first_ride_only', type: 'boolean', nullable: true),
        new OA\Property(property: 'referral_coupon', type: 'boolean', nullable: true),
        new OA\Property(property: 'ride_eligibility', type: 'array', items: new OA\Items(type: 'integer'), nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'SaveSettingsRequest',
    properties: [
        new OA\Property(property: 'platform_commission', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'currency', type: 'string', nullable: true),
        new OA\Property(property: 'distance_unit', type: 'string', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'ApplyReferralRequest',
    required: ['referral_code'],
    properties: [
        new OA\Property(property: 'referral_code', type: 'string', example: 'UEY4K8PZ'),
    ]
)]
#[OA\Schema(
    schema: 'InviteFriendRequest',
    required: ['phone'],
    properties: [
        new OA\Property(property: 'phone', type: 'string', example: '+447922222222'),
    ]
)]
#[OA\Schema(
    schema: 'StoreFavoritePlaceRequest',
    required: ['type', 'label', 'address', 'latitude', 'longitude'],
    properties: [
        new OA\Property(property: 'type', type: 'string', enum: ['home', 'work', 'saved'], example: 'home'),
        new OA\Property(property: 'label', type: 'string', example: 'Home'),
        new OA\Property(property: 'nickname', type: 'string', example: 'Sweet Home', nullable: true),
        new OA\Property(property: 'google_place_id', type: 'string', example: 'place_id_123', nullable: true),
        new OA\Property(property: 'address', type: 'string', example: '221B Baker St'),
        new OA\Property(property: 'latitude', type: 'number', format: 'float', example: 51.5237),
        new OA\Property(property: 'longitude', type: 'number', format: 'float', example: -0.1585),
    ]
)]
#[OA\Schema(
    schema: 'UpdateFavoritePlaceRequest',
    properties: [
        new OA\Property(property: 'label', type: 'string', example: 'My Home Office'),
        new OA\Property(property: 'nickname', type: 'string', example: 'Home Office', nullable: true),
        new OA\Property(property: 'address', type: 'string', example: '222 Baker St'),
        new OA\Property(property: 'latitude', type: 'number', format: 'float', example: 51.5240),
        new OA\Property(property: 'longitude', type: 'number', format: 'float', example: -0.1600),
    ]
)]
#[OA\Schema(
    schema: 'StoreEmergencyContactRequest',
    required: ['name', 'phone', 'relationship', 'priority'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
        new OA\Property(property: 'phone', type: 'string', example: '+447911111111'),
        new OA\Property(property: 'relationship', type: 'string', example: 'Brother'),
        new OA\Property(property: 'priority', type: 'integer', example: 1),
    ]
)]
#[OA\Schema(
    schema: 'UpdateEmergencyContactRequest',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Johnathan Doe'),
        new OA\Property(property: 'phone', type: 'string', example: '+447911111112'),
        new OA\Property(property: 'relationship', type: 'string', example: 'Spouse'),
        new OA\Property(property: 'priority', type: 'integer', example: 2),
    ]
)]
#[OA\Schema(
    schema: 'TriggerSOSRequest',
    required: ['latitude', 'longitude'],
    properties: [
        new OA\Property(property: 'latitude', type: 'number', format: 'float', example: 51.5237),
        new OA\Property(property: 'longitude', type: 'number', format: 'float', example: -0.1585),
        new OA\Property(property: 'message', type: 'string', example: 'Help me, vehicle broken down!', nullable: true),
        new OA\Property(property: 'photo', type: 'string', format: 'binary', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'ResolveSOSRequest',
    properties: [
        new OA\Property(property: 'admin_note', type: 'string', example: 'False alarm, situation resolved safely.', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'CreateVehicleTypeRequest',
    required: ['name', 'capacity', 'base_fare', 'per_km_rate', 'per_minute_rate', 'minimum_fare', 'commission_percentage'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Sedan'),
        new OA\Property(property: 'capacity', type: 'integer', example: 4),
        new OA\Property(property: 'base_fare', type: 'number', format: 'float', example: 2.50),
        new OA\Property(property: 'per_km_rate', type: 'number', format: 'float', example: 1.20),
        new OA\Property(property: 'per_minute_rate', type: 'number', format: 'float', example: 0.30),
        new OA\Property(property: 'minimum_fare', type: 'number', format: 'float', example: 5.00),
        new OA\Property(property: 'commission_percentage', type: 'number', format: 'float', example: 15.00),
        new OA\Property(property: 'icon_url', type: 'string', nullable: true, example: 'https://uey-assets.s3.amazonaws.com/vehicle-types/icons/sedan.png'),
        new OA\Property(property: 'active', type: 'boolean', example: true),
    ]
)]
#[OA\Schema(
    schema: 'UpdateVehicleTypeRequest',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Sedan Plus'),
        new OA\Property(property: 'capacity', type: 'integer', example: 5),
        new OA\Property(property: 'base_fare', type: 'number', format: 'float', example: 3.00),
        new OA\Property(property: 'per_km_rate', type: 'number', format: 'float', example: 1.40),
        new OA\Property(property: 'per_minute_rate', type: 'number', format: 'float', example: 0.35),
        new OA\Property(property: 'minimum_fare', type: 'number', format: 'float', example: 6.00),
        new OA\Property(property: 'commission_percentage', type: 'number', format: 'float', example: 15.00),
        new OA\Property(property: 'icon_url', type: 'string', nullable: true, example: 'https://uey-assets.s3.amazonaws.com/vehicle-types/icons/sedan.png'),
        new OA\Property(property: 'active', type: 'boolean', example: false),
    ]
)]
class RequestSchemas {}
