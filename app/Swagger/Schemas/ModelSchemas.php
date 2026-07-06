<?php

namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "Ride",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "rider_id", type: "integer", example: 2),
        new OA\Property(property: "driver_profile_id", type: "integer", nullable: true, example: 3),
        new OA\Property(property: "vehicle_type_id", type: "integer", example: 1),
        new OA\Property(property: "pickup_address", type: "string", example: "London Eye, London"),
        new OA\Property(property: "pickup_latitude", type: "number", format: "float", example: 51.5074),
        new OA\Property(property: "pickup_longitude", type: "number", format: "float", example: -0.1278),
        new OA\Property(property: "destination_address", type: "string", example: "Regent's Park, London"),
        new OA\Property(property: "destination_latitude", type: "number", format: "float", example: 51.5204),
        new OA\Property(property: "destination_longitude", type: "number", format: "float", example: -0.1482),
        new OA\Property(property: "status", type: "string", example: "pending"),
        new OA\Property(property: "otp", type: "string", example: "123456"),
        new OA\Property(property: "estimated_distance", type: "number", format: "float", example: 2.34),
        new OA\Property(property: "estimated_duration", type: "integer", example: 4),
        new OA\Property(property: "estimated_fare", type: "number", format: "float", example: 8.50),
        new OA\Property(property: "actual_distance", type: "number", format: "float", nullable: true, example: null),
        new OA\Property(property: "actual_duration", type: "integer", nullable: true, example: null),
        new OA\Property(property: "actual_fare", type: "number", format: "float", nullable: true, example: null),
        new OA\Property(property: "accepted_at", type: "string", format: "date-time", nullable: true, example: null),
        new OA\Property(property: "arrived_at", type: "string", format: "date-time", nullable: true, example: null),
        new OA\Property(property: "started_at", type: "string", format: "date-time", nullable: true, example: null),
        new OA\Property(property: "completed_at", type: "string", format: "date-time", nullable: true, example: null),
        new OA\Property(property: "cancelled_at", type: "string", format: "date-time", nullable: true, example: null),
        new OA\Property(property: "cancelled_by", type: "string", nullable: true, example: null),
        new OA\Property(property: "cancel_reason", type: "string", nullable: true, example: null),
        new OA\Property(property: "created_at", type: "string", format: "date-time", example: "2026-06-24T00:58:13+05:30"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time", example: "2026-06-24T00:58:13+05:30")
    ]
)]

#[OA\Schema(
    schema: "RideRequest",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "ride_id", type: "integer", example: 1),
        new OA\Property(property: "driver_profile_id", type: "integer", example: 3),
        new OA\Property(property: "status", type: "string", example: "pending"),
        new OA\Property(property: "expires_at", type: "string", format: "date-time", example: "2026-06-24T01:28:13+05:30"),
        new OA\Property(property: "created_at", type: "string", format: "date-time", example: "2026-06-24T00:58:13+05:30"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time", example: "2026-06-24T00:58:13+05:30")
    ]
)]
#[OA\Schema(
    schema: "Payment",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 7),
        new OA\Property(property: "ride_id", type: "integer", example: 12),
        new OA\Property(property: "rider_id", type: "integer", example: 1),
        new OA\Property(property: "driver_profile_id", type: "integer", nullable: true, example: 3),
        new OA\Property(property: "payment_method", type: "string", example: "wallet"),
        new OA\Property(property: "payment_status", type: "string", example: "paid"),
        new OA\Property(property: "transaction_reference", type: "string", example: "PAY-20260704-000007"),
        new OA\Property(property: "subtotal", type: "number", format: "float", example: 15.00),
        new OA\Property(property: "tax", type: "number", format: "float", example: 0.00),
        new OA\Property(property: "discount", type: "number", format: "float", example: 0.00),
        new OA\Property(property: "platform_commission", type: "number", format: "float", example: 2.25),
        new OA\Property(property: "driver_earning", type: "number", format: "float", example: 12.75),
        new OA\Property(property: "total", type: "number", format: "float", example: 15.00),
        new OA\Property(property: "paid_at", type: "string", format: "date-time", nullable: true),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time")
    ]
)]
#[OA\Schema(
    schema: "RideReview",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "ride_id", type: "integer", example: 12),
        new OA\Property(property: "reviewer_id", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "reviewee_id", type: "integer", example: 3),
        new OA\Property(property: "rating", type: "integer", example: 5),
        new OA\Property(property: "review", type: "string", nullable: true, example: "Excellent trip, clean vehicle!"),
        new OA\Property(property: "review_tags", type: "array", items: new OA\Items(type: "string"), nullable: true, example: ["polite", "safe_driver"]),
        new OA\Property(property: "is_anonymous", type: "boolean", example: false),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time")
    ]
)]
#[OA\Schema(
    schema: "WalletTopup",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "wallet_id", type: "integer", example: 1),
        new OA\Property(property: "amount", type: "number", format: "float", example: 50.00),
        new OA\Property(property: "stripe_payment_intent", type: "string", example: "pi_3MtwJD2eZvKYlo2C0DGk4"),
        new OA\Property(property: "payment_status", type: "string", example: "pending"),
        new OA\Property(property: "paid_at", type: "string", format: "date-time", nullable: true)
    ]
)]
#[OA\Schema(
    schema: "WithdrawalRequest",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "wallet_id", type: "integer", example: 1),
        new OA\Property(property: "amount", type: "number", format: "float", example: 100.00),
        new OA\Property(property: "status", type: "string", example: "pending"),
        new OA\Property(property: "bank_account_id", type: "integer", nullable: true, example: 2),
        new OA\Property(property: "admin_note", type: "string", nullable: true, example: "Approved."),
        new OA\Property(property: "processed_at", type: "string", format: "date-time", nullable: true)
    ]
)]
#[OA\Schema(
    schema: "Wallet",
    properties: [
        new OA\Property(property: "balance", type: "number", format: "float", example: 150.00),
        new OA\Property(property: "currency", type: "string", example: "USD"),
        new OA\Property(property: "status", type: "string", example: "active"),
        new OA\Property(property: "last_transaction", ref: "#/components/schemas/WalletTransaction", nullable: true),
        new OA\Property(property: "updated_at", type: "string", format: "date-time")
    ]
)]
#[OA\Schema(
    schema: "WalletTransaction",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "transaction_type", type: "string", example: "top_up"),
        new OA\Property(property: "type", type: "string", example: "credit"),
        new OA\Property(property: "amount", type: "number", format: "float", example: 50.00),
        new OA\Property(property: "balance_before", type: "number", format: "float", example: 100.00),
        new OA\Property(property: "balance_after", type: "number", format: "float", example: 150.00),
        new OA\Property(property: "status", type: "string", example: "completed"),
        new OA\Property(property: "reference", type: "string", nullable: true, example: "topup_1"),
        new OA\Property(property: "remarks", type: "string", nullable: true, example: "Stripe wallet top-up completed"),
        new OA\Property(property: "created_at", type: "string", format: "date-time")
    ]
)]
class ModelSchemas {}
