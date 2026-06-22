<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "UEY Premium Mobility API",
    description: "Complete backend API documentation for UEY Premium Mobility ride-hailing platform."
)]
#[OA\Server(
    url: "/api/v1",
    description: "Default API Gateway"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "Use Sanctum Bearer token for authorized requests (e.g. Bearer 1|...)"
)]

// Reusable standard responses
#[OA\Response(
    response: "ValidationErrorResponse",
    description: "Validation errors in request parameters.",
    content: new OA\JsonContent(
        properties: [
            new OA\Property(property: "success", type: "boolean", example: false),
            new OA\Property(property: "message", type: "string", example: "The given data was invalid."),
            new OA\Property(
                property: "errors",
                type: "object",
                properties: [
                    new OA\Property(
                        property: "phone",
                        type: "array",
                        items: new OA\Items(type: "string", example: "The phone field is required.")
                    )
                ]
            )
        ]
    )
)]
#[OA\Response(
    response: "UnauthorizedResponse",
    description: "Bearer token is missing, invalid, or expired.",
    content: new OA\JsonContent(
        properties: [
            new OA\Property(property: "message", type: "string", example: "Unauthenticated.")
        ]
    )
)]
#[OA\Response(
    response: "ForbiddenResponse",
    description: "The user does not have the required role or token abilities.",
    content: new OA\JsonContent(
        properties: [
            new OA\Property(property: "message", type: "string", example: "This action is unauthorized.")
        ]
    )
)]
class OpenApiSpec {}
