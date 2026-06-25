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
class ModelSchemas {}
