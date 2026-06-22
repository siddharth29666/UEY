<?php

namespace App\Http\Controllers\Auth;

use App\DTOs\RegisterRiderDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRiderRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class RegisterRiderController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Register a new Rider.
     */
    #[OA\Post(
        path: '/register/rider',
        summary: 'Register Rider',
        description: 'Registers a new rider user, creates an automatic wallet, logs them in and returns a bearer token.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RegisterRiderRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Rider registered successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Rider registered successfully.'),
                        new OA\Property(property: 'token', type: 'string', example: '1|abcde12345...', description: 'Sanctum authentication token.'),
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'John Rider'),
                                new OA\Property(property: 'email', type: 'string', example: 'john.rider@example.com'),
                                new OA\Property(property: 'phone', type: 'string', example: '+447911123456'),
                                new OA\Property(property: 'role', type: 'string', example: 'rider'),
                                new OA\Property(property: 'status', type: 'string', example: 'active'),
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
    public function __invoke(RegisterRiderRequest $request): JsonResponse
    {
        try {
            $dto = RegisterRiderDTO::fromRequest($request);
            $user = $this->authService->registerRider($dto);

            // Log user in automatically after registration
            $token = $user->createToken('uey-auth-token', ['role:rider'])->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Rider registered successfully.',
                'token' => $token,
                'user' => new UserResource($user),
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
