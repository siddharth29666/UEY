<?php

namespace App\Http\Controllers\Auth;

use App\DTOs\VerifyOtpDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyOtpRequest;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class VerifyOtpController extends Controller
{
    public function __construct(
        protected OtpService $otpService
    ) {}

    /**
     * Verify the OTP code.
     */
    #[OA\Post(
        path: '/otp/verify',
        summary: 'Verify OTP Code',
        description: 'Verifies the 6-digit OTP code sent to the user\'s phone number.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/VerifyOtpRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'OTP code verified successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'OTP verified successfully.')
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                ref: '#/components/responses/ValidationErrorResponse'
            )
        ]
    )]
    public function __invoke(VerifyOtpRequest $request): JsonResponse
    {
        $dto = VerifyOtpDTO::fromRequest($request);
        $verified = $this->otpService->verifyOtp($dto->phone, $dto->code, $dto->type);

        if (!$verified) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP code.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
        ]);
    }
}
