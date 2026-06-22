<?php

namespace App\Http\Controllers\Auth;

use App\DTOs\RegisterDriverDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterDriverRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class RegisterDriverController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Register a new Driver.
     */
    #[OA\Post(
        path: '/register/driver',
        summary: 'Register Driver',
        description: 'Registers a new driver user, creates driver profile, vehicle details, and wallet. Automatically logs them in, returning bearer token. Account is pending documents approval.',
        tags: ['Authentication', 'Driver Onboarding'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RegisterDriverRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Driver registered successfully. Account is pending documents approval.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Driver registered successfully. Account is pending documents approval.'),
                        new OA\Property(property: 'token', type: 'string', example: '1|abcde12345...', description: 'Sanctum authentication token.'),
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 2),
                                new OA\Property(property: 'name', type: 'string', example: 'Bob Driver'),
                                new OA\Property(property: 'email', type: 'string', example: 'bob.driver@example.com'),
                                new OA\Property(property: 'phone', type: 'string', example: '+447911999999'),
                                new OA\Property(property: 'role', type: 'string', example: 'driver'),
                                new OA\Property(property: 'status', type: 'string', example: 'pending'),
                                new OA\Property(property: 'avatar_url', type: 'string', nullable: true, example: null),
                                new OA\Property(
                                    property: 'notification_preferences',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'email', type: 'boolean', example: true),
                                        new OA\Property(property: 'sms', type: 'boolean', example: true),
                                        new OA\Property(property: 'push', type: 'boolean', example: true)
                                    ]
                                ),
                                new OA\Property(
                                    property: 'driver_profile',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'license_number', type: 'string', example: 'DL-999888'),
                                        new OA\Property(property: 'license_expiry', type: 'string', format: 'date', example: '2027-06-21'),
                                        new OA\Property(property: 'is_online', type: 'boolean', example: false),
                                        new OA\Property(property: 'rating', type: 'number', example: 5.0),
                                        new OA\Property(property: 'experience_years', type: 'number', example: 0.0),
                                        new OA\Property(property: 'acceptance_rate', type: 'number', example: 100.0),
                                        new OA\Property(property: 'ontime_rate', type: 'number', example: 100.0),
                                        new OA\Property(property: 'total_online_hours', type: 'integer', example: 0),
                                        new OA\Property(
                                            property: 'preferences',
                                            type: 'object',
                                            properties: [
                                                new OA\Property(property: 'default_navigation', type: 'string', example: 'google_maps'),
                                                new OA\Property(property: 'auto_accept', type: 'boolean', example: false)
                                            ]
                                        ),
                                        new OA\Property(
                                            property: 'vehicles',
                                            type: 'array',
                                            items: new OA\Items(
                                                properties: [
                                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                                    new OA\Property(property: 'make', type: 'string', example: 'Toyota'),
                                                    new OA\Property(property: 'model', type: 'string', example: 'Prius'),
                                                    new OA\Property(property: 'year', type: 'integer', example: 2022),
                                                    new OA\Property(property: 'color', type: 'string', example: 'Silver'),
                                                    new OA\Property(property: 'plate_number', type: 'string', example: 'ABC-999'),
                                                    new OA\Property(property: 'status', type: 'string', example: 'pending')
                                                ]
                                            )
                                        )
                                    ]
                                ),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-06-23T00:58:13+05:30'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-06-23T00:58:13+05:30')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                ref: '#/components/responses/ValidationErrorResponse'
            )
        ]
    )]
    public function __invoke(RegisterDriverRequest $request): JsonResponse
    {
        try {
            $dto = RegisterDriverDTO::fromRequest($request);
            $user = $this->authService->registerDriver($dto);

            // Log user in automatically after registration (awaits document approval)
            $token = $user->createToken('uey-auth-token', ['role:driver'])->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Driver registered successfully. Account is pending documents approval.',
                'token' => $token,
                'user' => new UserResource($user->load('driverProfile.vehicles')),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
