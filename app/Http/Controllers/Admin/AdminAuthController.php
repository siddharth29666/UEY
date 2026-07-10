<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Http\Requests\Admin\ChangePasswordRequest;
use App\Http\Requests\Admin\UpdateAdminProfileRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AdminAuthController extends Controller
{
    public function __construct(
        protected AuditLogService $auditService
    ) {}

    /**
     * Authenticate Admin User.
     */
    #[OA\Post(
        path: '/admin/login',
        summary: 'Admin Login',
        description: 'Authenticates admin credential and issues a Sanctum token.',
        tags: ['Admin Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AdminLoginRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logged in successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'token', type: 'string', example: '1|abcdef...'),
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                    ]
                )
            ),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ]
    )]
    public function login(AdminLoginRequest $request): JsonResponse
    {
        $user = User::where('phone', $request->input('phone'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'phone' => ['Invalid phone number or password.'],
            ]);
        }

        if (! $user->isAdmin()) {
            throw ValidationException::withMessages([
                'phone' => ['You are not authorized to access the Admin Panel.'],
            ]);
        }

        $user->update([
            'last_login_at' => now(),
        ]);

        $ability = 'role:admin';
        $token = $user->createToken('admin-token', [$ability])->plainTextToken;

        // Log login action
        $this->auditService->log($user, 'auth', 'admin_login');

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => new AdminUserResource($user),
        ]);
    }

    /**
     * Log out Admin User.
     */
    #[OA\Post(
        path: '/admin/logout',
        summary: 'Admin Logout',
        description: 'Revokes the current authenticated token.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logged out successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Logged out successfully.'),
                    ]
                )
            ),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $admin = $request->user();
        $admin->currentAccessToken()->delete();

        $this->auditService->log($admin, 'auth', 'admin_logout');

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Get Admin Profile Details.
     */
    #[OA\Get(
        path: '/admin/profile',
        summary: 'Get Admin Profile',
        description: 'Retrieves current admin profile details.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile details.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                    ]
                )
            ),
        ]
    )]
    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'user' => new AdminUserResource($request->user()),
        ]);
    }

    /**
     * Update Admin Profile.
     */
    #[OA\Put(
        path: '/admin/profile',
        summary: 'Update Admin Profile',
        description: 'Updates profile fields of the authenticated admin.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateAdminProfileRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                    ]
                )
            ),
        ]
    )]
    public function updateProfile(UpdateAdminProfileRequest $request): JsonResponse
    {
        $admin = $request->user();
        $oldValues = $admin->toArray();

        $admin->update(array_filter($request->validated()));

        $this->auditService->log(
            $admin,
            'profile',
            'profile_update',
            'users',
            $admin->id,
            $oldValues,
            $admin->fresh()->toArray()
        );

        return response()->json([
            'success' => true,
            'user' => new AdminUserResource($admin),
        ]);
    }

    /**
     * Change Admin Password.
     */
    #[OA\Put(
        path: '/admin/change-password',
        summary: 'Change Admin Password',
        description: 'Changes the password of the authenticated admin.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ChangePasswordRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password changed successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Password updated successfully.'),
                    ]
                )
            ),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ]
    )]
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $admin = $request->user();

        if (! Hash::check($request->input('current_password'), $admin->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password does not match your current password.'],
            ]);
        }

        $admin->update([
            'password' => Hash::make($request->input('new_password')),
        ]);

        $this->auditService->log($admin, 'profile', 'password_change');

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully.',
        ]);
    }
}
