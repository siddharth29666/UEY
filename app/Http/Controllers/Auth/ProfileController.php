<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\DeleteAccountRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Get the profile of the authenticated user.
     */
    #[OA\Get(
        path: '/profile',
        summary: 'Get User Profile',
        description: 'Returns the profile details of the authenticated Rider or Driver user.',
        security: [['bearerAuth' => []]],
        tags: ['User Profile'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User profile retrieved successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
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
                response: 401,
                ref: '#/components/responses/UnauthorizedResponse'
            )
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if ($user->isDriver()) {
            $user->load('driverProfile.vehicles');
        }

        return response()->json([
            'success' => true,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Update the profile settings.
     */
    #[OA\Put(
        path: '/profile',
        summary: 'Update User Profile',
        description: 'Updates the profile information or notification settings for the authenticated user.',
        security: [['bearerAuth' => []]],
        tags: ['User Profile'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateProfileRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile updated successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Profile updated successfully.'),
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Jane Updated'),
                                new OA\Property(property: 'email', type: 'string', example: 'jane.updated@example.com'),
                                new OA\Property(property: 'phone', type: 'string', example: '+447911123456'),
                                new OA\Property(property: 'role', type: 'string', example: 'rider'),
                                new OA\Property(property: 'status', type: 'string', example: 'active'),
                                new OA\Property(property: 'avatar_url', type: 'string', example: 'https://example.com/avatar.png'),
                                new OA\Property(
                                    property: 'notification_preferences',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'email', type: 'boolean', example: true),
                                        new OA\Property(property: 'sms', type: 'boolean', example: false),
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
                response: 401,
                ref: '#/components/responses/UnauthorizedResponse'
            ),
            new OA\Response(
                response: 422,
                ref: '#/components/responses/ValidationErrorResponse'
            )
        ]
    )]
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $updatedUser = $this->authService->updateProfile($user, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'user' => new UserResource($updatedUser),
        ]);
    }

    /**
     * Delete user account permanently.
     */
    #[OA\Delete(
        path: '/profile/delete-account',
        summary: 'Delete User Account',
        description: 'Permanently deletes the authenticated user\'s account. Performs cleanup of related data.',
        security: [['bearerAuth' => []]],
        tags: ['User Profile'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['password'],
                properties: [
                    new OA\Property(property: 'password', type: 'string', example: 'CurrentPassword123!', description: 'The user\'s current password for confirmation.')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Account deleted successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Account deleted successfully.')
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Invalid password or validation error.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid password.')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/UnauthorizedResponse'
            )
        ]
    )]
    public function deleteAccount(DeleteAccountRequest $request): JsonResponse
    {
        $user = $request->user();

        try {
            $this->authService->deleteAccount($user, $request->input('password'));

            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password.',
            ], 422);
        }
    }
}
