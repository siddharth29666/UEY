<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class PasswordResetController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Request Password Reset OTP.
     */
    #[OA\Post(
        path: '/auth/forgot-password',
        summary: 'Forgot Password Request OTP',
        description: 'Requests a 6-digit password reset OTP code. Sends the code to the user\'s email.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com', description: 'The registered email address.')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password reset OTP sent successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Password reset OTP sent successfully.')
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                ref: '#/components/responses/ValidationErrorResponse'
            )
        ]
    )]
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->sendPasswordResetOtp($request->input('email'));

        return response()->json([
            'success' => true,
            'message' => 'Password reset OTP sent successfully.',
        ]);
    }

    /**
     * Verify OTP & Reset Password.
     */
    #[OA\Post(
        path: '/auth/reset-password',
        summary: 'Reset Password with OTP',
        description: 'Verifies the 6-digit OTP code and updates the user\'s password. Revokes all active tokens.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'otp', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                    new OA\Property(property: 'otp', type: 'string', example: '123456', description: 'The 6-digit OTP code received via email.'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'NewPassword123!'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'NewPassword123!')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password reset successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Password reset successfully.')
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                ref: '#/components/responses/ValidationErrorResponse'
            )
        ]
    )]
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword(
            $request->input('email'),
            $request->input('otp'),
            $request->input('password')
        );

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully.',
        ]);
    }
}
