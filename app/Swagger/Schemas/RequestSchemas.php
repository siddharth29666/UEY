<?php

namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "SendOtpRequest",
    required: ["phone", "type"],
    properties: [
        new OA\Property(property: "phone", type: "string", example: "+447911123456"),
        new OA\Property(property: "type", type: "string", enum: ["register", "login", "password_reset"], example: "register")
    ]
)]

#[OA\Schema(
    schema: "VerifyOtpRequest",
    required: ["phone", "code", "type"],
    properties: [
        new OA\Property(property: "phone", type: "string", example: "+447911123456"),
        new OA\Property(property: "code", type: "string", example: "123456"),
        new OA\Property(property: "type", type: "string", enum: ["register", "login", "password_reset"], example: "register")
    ]
)]

#[OA\Schema(
    schema: "RegisterRiderRequest",
    required: ["name", "phone", "password"],
    properties: [
        new OA\Property(property: "name", type: "string", example: "John Rider"),
        new OA\Property(property: "email", type: "string", format: "email", example: "john.rider@example.com"),
        new OA\Property(property: "phone", type: "string", example: "+447911123456"),
        new OA\Property(property: "password", type: "string", format: "password", example: "password123")
    ]
)]

#[OA\Schema(
    schema: "RegisterDriverRequest",
    required: [
        "name", "phone", "password", "license_number", "license_expiry",
        "vehicle_make", "vehicle_model", "vehicle_year", "vehicle_color", "vehicle_plate", "vehicle_type_id"
    ],
    properties: [
        new OA\Property(property: "name", type: "string", example: "Bob Driver"),
        new OA\Property(property: "email", type: "string", format: "email", example: "bob.driver@example.com"),
        new OA\Property(property: "phone", type: "string", example: "+447911999999"),
        new OA\Property(property: "password", type: "string", format: "password", example: "password123"),
        new OA\Property(property: "license_number", type: "string", example: "DL-999888"),
        new OA\Property(property: "license_expiry", type: "string", format: "date", example: "2027-06-21"),
        new OA\Property(property: "vehicle_make", type: "string", example: "Toyota"),
        new OA\Property(property: "vehicle_model", type: "string", example: "Prius"),
        new OA\Property(property: "vehicle_year", type: "integer", example: 2022),
        new OA\Property(property: "vehicle_color", type: "string", example: "Silver"),
        new OA\Property(property: "vehicle_plate", type: "string", example: "ABC-999"),
        new OA\Property(property: "vehicle_type_id", type: "integer", example: 1)
    ]
)]

#[OA\Schema(
    schema: "LoginRequest",
    required: ["phone", "password"],
    properties: [
        new OA\Property(property: "phone", type: "string", example: "+447911123456"),
        new OA\Property(property: "password", type: "string", format: "password", example: "password123")
    ]
)]

#[OA\Schema(
    schema: "UpdateProfileRequest",
    properties: [
        new OA\Property(property: "name", type: "string", example: "Jane Updated"),
        new OA\Property(property: "email", type: "string", format: "email", example: "jane.updated@example.com"),
        new OA\Property(property: "avatar_url", type: "string", format: "url", example: "https://example.com/avatar.png"),
        new OA\Property(property: "email_notifications", type: "boolean", example: true),
        new OA\Property(property: "sms_notifications", type: "boolean", example: false),
        new OA\Property(property: "push_notifications", type: "boolean", example: true),
        new OA\Property(property: "default_navigation", type: "string", enum: ["google_maps", "waze", "apple_maps"], example: "google_maps"),
        new OA\Property(property: "auto_accept", type: "boolean", example: true)
    ]
)]

#[OA\Schema(
    schema: "SaveBankAccountRequest",
    required: ["bank_name", "account_holder_name", "account_number"],
    properties: [
        new OA\Property(property: "bank_name", type: "string", example: "Chase Bank"),
        new OA\Property(property: "account_holder_name", type: "string", example: "Bob Driver"),
        new OA\Property(property: "account_number", type: "string", example: "1234567890"),
        new OA\Property(property: "routing_number", type: "string", example: "987654321"),
        new OA\Property(property: "swift_code", type: "string", example: "CHASUS33")
    ]
)]

#[OA\Schema(
    schema: "VerifyDocumentRequest",
    required: ["status"],
    properties: [
        new OA\Property(property: "status", type: "string", enum: ["approved", "rejected"], example: "approved"),
        new OA\Property(property: "rejection_reason", type: "string", example: "The document image is blurry.")
    ]
)]

#[OA\Schema(
    schema: "UpdateDriverStatusRequest",
    required: ["is_online"],
    properties: [
        new OA\Property(property: "is_online", type: "boolean", example: true)
    ]
)]

#[OA\Schema(
    schema: "UpdateDriverLocationRequest",
    required: ["current_latitude", "current_longitude"],
    properties: [
        new OA\Property(property: "current_latitude", type: "number", format: "float", example: 51.5074),
        new OA\Property(property: "current_longitude", type: "number", format: "float", example: -0.1278),
        new OA\Property(property: "bearing", type: "number", format: "float", example: 180.5)
    ]
)]

#[OA\Schema(
    schema: "EstimateRideRequest",
    required: ["pickup_latitude", "pickup_longitude", "destination_latitude", "destination_longitude"],
    properties: [
        new OA\Property(property: "pickup_latitude", type: "number", format: "float", example: 51.5074),
        new OA\Property(property: "pickup_longitude", type: "number", format: "float", example: -0.1278),
        new OA\Property(property: "destination_latitude", type: "number", format: "float", example: 51.5204),
        new OA\Property(property: "destination_longitude", type: "number", format: "float", example: -0.1482)
    ]
)]

#[OA\Schema(
    schema: "RequestRideRequest",
    required: ["pickup_latitude", "pickup_longitude", "pickup_address", "destination_latitude", "destination_longitude", "destination_address", "vehicle_type_id"],
    properties: [
        new OA\Property(property: "pickup_latitude", type: "number", format: "float", example: 51.5074),
        new OA\Property(property: "pickup_longitude", type: "number", format: "float", example: -0.1278),
        new OA\Property(property: "pickup_address", type: "string", example: "London Eye, London"),
        new OA\Property(property: "destination_latitude", type: "number", format: "float", example: 51.5204),
        new OA\Property(property: "destination_longitude", type: "number", format: "float", example: -0.1482),
        new OA\Property(property: "destination_address", type: "string", example: "Regent's Park, London"),
        new OA\Property(property: "vehicle_type_id", type: "integer", example: 1)
    ]
)]

#[OA\Schema(
    schema: "CancelRideRequest",
    properties: [
        new OA\Property(property: "cancel_reason", type: "string", example: "Plans changed")
    ]
)]
class RequestSchemas {}
