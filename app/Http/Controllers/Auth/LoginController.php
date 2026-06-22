<?php

namespace App\Http\Controllers\Auth;

use App\DTOs\LoginDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class LoginController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Log in a user.
     */
    #[OA\Post(
        path: '/login',
        summary: 'Login User',
        description: 'Authenticates a user using phone and password. Returns user profile details and access token.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/LoginRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logged in successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Logged in successfully.'),
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
    public function __invoke(LoginRequest $request): JsonResponse
    {
        try {
            $dto = LoginDTO::fromRequest($request);
            $authData = $this->authService->login($dto);

            return response()->json([
                'success' => true,
                'message' => 'Logged in successfully.',
                'token' => $authData['token'],
                'user' => new UserResource($authData['user']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
