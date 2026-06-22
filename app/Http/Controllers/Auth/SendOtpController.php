<?php

namespace App\Http\Controllers\Auth;

use App\DTOs\SendOtpDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendOtpRequest;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class SendOtpController extends Controller
{
    public function __construct(
        protected OtpService $otpService
    ) {}

    /**
     * Send an OTP code to a user's phone.
     */
    #[OA\Post(
        path: '/otp/send',
        summary: 'Send OTP Code',
        description: 'Generates and sends a 6-digit OTP code to the user\'s phone number for login or registration.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SendOtpRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'OTP code generated and sent successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'OTP sent successfully.'),
                        new OA\Property(property: 'otp', type: 'string', example: '123456', description: 'Returned only in local/testing environments.')
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                ref: '#/components/responses/ValidationErrorResponse'
            )
        ]
    )]
    public function __invoke(SendOtpRequest $request): JsonResponse
    {
        try {
            $dto = SendOtpDTO::fromRequest($request);
            $otp = $this->otpService->sendOtp($dto->phone, $dto->type);

            $response = [
                'success' => true,
                'message' => 'OTP sent successfully.',
            ];

            // If local environment, return the generated OTP in response
            if (!is_null($otp)) {
                $response['otp'] = $otp;
            }

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
