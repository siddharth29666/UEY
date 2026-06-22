<?php

namespace App\Http\Controllers\Driver;

use App\DTOs\SaveBankAccountDTO;
use App\DTOs\UploadDocumentDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveBankAccountRequest;
use App\Http\Requests\UploadDocumentRequest;
use App\Http\Resources\DriverBankAccountResource;
use App\Http\Resources\DriverDocumentResource;
use App\Http\Resources\DriverOnboardingStatusResource;
use App\Services\DriverVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DriverController extends Controller
{
    public function __construct(
        protected DriverVerificationService $verificationService
    ) {}

    /**
     * Upload or re-upload a driver document.
     */
    #[OA\Post(
        path: '/driver/onboarding/documents',
        summary: 'Upload Driver Document',
        description: 'Uploads or re-uploads a driver onboarding document (driving_license, vehicle_registration, or insurance). Requires multipart/form-data payload.',
        security: [['bearerAuth' => []]],
        tags: ['Driver Onboarding'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Multipart document upload payload',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['document_type', 'document'],
                    properties: [
                        new OA\Property(property: 'document_type', type: 'string', enum: ['driving_license', 'vehicle_registration', 'insurance'], example: 'driving_license', description: 'Type of the document.'),
                        new OA\Property(property: 'document', type: 'string', format: 'binary', description: 'The file to upload.'),
                        new OA\Property(property: 'expires_at', type: 'string', format: 'date', example: '2028-12-31', description: 'Optional expiration date.')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Document uploaded successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Document uploaded successfully.'),
                        new OA\Property(
                            property: 'document',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'document_type', type: 'string', example: 'driving_license'),
                                new OA\Property(property: 'document_path', type: 'string', example: 'documents/driving_license_123.jpg'),
                                new OA\Property(property: 'document_url', type: 'string', format: 'url', example: 'http://uey.test/storage/documents/driving_license_123.jpg'),
                                new OA\Property(property: 'status', type: 'string', example: 'pending'),
                                new OA\Property(property: 'rejection_reason', type: 'string', nullable: true, example: null),
                                new OA\Property(property: 'expires_at', type: 'string', format: 'date', example: '2028-12-31'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-06-23T00:58:13+05:30'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-06-23T00:58:13+05:30')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(
                response: 404,
                description: 'Driver profile not found.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Driver profile not found.')
                    ]
                )
            ),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse')
        ]
    )]
    public function uploadDocuments(UploadDocumentRequest $request): JsonResponse
    {
        $driver = $request->user()->driverProfile;

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 404);
        }

        $dto = UploadDocumentDTO::fromRequest($request);
        $document = $this->verificationService->uploadDocument($driver, $dto);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully.',
            'document' => new DriverDocumentResource($document),
        ], 201);
    }

    /**
     * Get the onboarding and document approval status.
     */
    #[OA\Get(
        path: '/driver/onboarding/status',
        summary: 'Get Driver Onboarding Status',
        description: 'Returns the overall driver status, vehicle approval status, bank account linking status, checklist requirements status, and list of uploaded documents.',
        security: [['bearerAuth' => []]],
        tags: ['Driver Onboarding'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Onboarding status retrieved successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'onboarding',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'driver_profile_id', type: 'integer', example: 1),
                                new OA\Property(property: 'overall_status', type: 'string', example: 'pending'),
                                new OA\Property(property: 'vehicle_status', type: 'string', example: 'pending'),
                                new OA\Property(property: 'bank_account_completed', type: 'boolean', example: false),
                                new OA\Property(property: 'can_go_online', type: 'boolean', example: false),
                                new OA\Property(
                                    property: 'requirements',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'documents_approved', type: 'boolean', example: false),
                                        new OA\Property(property: 'vehicle_approved', type: 'boolean', example: false),
                                        new OA\Property(property: 'bank_account_linked', type: 'boolean', example: false)
                                    ]
                                ),
                                new OA\Property(
                                    property: 'documents',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'document_type', type: 'string', example: 'driving_license'),
                                            new OA\Property(property: 'document_path', type: 'string', example: 'documents/driving_license_123.jpg'),
                                            new OA\Property(property: 'document_url', type: 'string', format: 'url', example: 'http://uey.test/storage/documents/driving_license_123.jpg'),
                                            new OA\Property(property: 'status', type: 'string', example: 'pending'),
                                            new OA\Property(property: 'rejection_reason', type: 'string', nullable: true, example: null),
                                            new OA\Property(property: 'expires_at', type: 'string', format: 'date', example: '2028-12-31')
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'vehicle',
                                    type: 'object',
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
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(
                response: 404,
                description: 'Driver profile not found.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Driver profile not found.')
                    ]
                )
            )
        ]
    )]
    public function onboardingStatus(Request $request): JsonResponse
    {
        $driver = $request->user()->driverProfile;

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 404);
        }

        // Load relations for onboarding status calculation
        $driver->load(['user', 'vehicles', 'bankAccount', 'documents']);

        return response()->json([
            'success' => true,
            'onboarding' => new DriverOnboardingStatusResource($driver),
        ]);
    }

    /**
     * Get the driver's bank account details.
     */
    #[OA\Get(
        path: '/driver/bank-account',
        summary: 'Get Driver Bank Account',
        description: 'Retrieves the linked bank account details for the authenticated driver.',
        security: [['bearerAuth' => []]],
        tags: ['Bank Account'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Bank account retrieved successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'bank_account',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'bank_name', type: 'string', example: 'Chase Bank'),
                                new OA\Property(property: 'account_holder_name', type: 'string', example: 'Bob Driver'),
                                new OA\Property(property: 'account_number_masked', type: 'string', example: '******7890'),
                                new OA\Property(property: 'routing_number', type: 'string', example: '987654321'),
                                new OA\Property(property: 'swift_code', type: 'string', example: 'CHASUS33'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-06-23T00:58:13+05:30'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-06-23T00:58:13+05:30')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(
                response: 404,
                description: 'Driver profile or bank account details not found.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Bank account details not found.')
                    ]
                )
            )
        ]
    )]
    public function getBankAccount(Request $request): JsonResponse
    {
        $driver = $request->user()->driverProfile;

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 404);
        }

        $bankAccount = $driver->bankAccount;

        if (!$bankAccount) {
            return response()->json([
                'success' => false,
                'message' => 'Bank account details not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'bank_account' => new DriverBankAccountResource($bankAccount),
        ]);
    }

    /**
     * Link/update driver's bank account.
     */
    #[OA\Post(
        path: '/driver/bank-account',
        summary: 'Link/Update Bank Account',
        description: 'Links a new bank account or updates the existing bank account for the authenticated driver.',
        security: [['bearerAuth' => []]],
        tags: ['Bank Account'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SaveBankAccountRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Bank account details saved successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Bank account saved successfully.'),
                        new OA\Property(
                            property: 'bank_account',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'bank_name', type: 'string', example: 'Chase Bank'),
                                new OA\Property(property: 'account_holder_name', type: 'string', example: 'Bob Driver'),
                                new OA\Property(property: 'account_number_masked', type: 'string', example: '******7890'),
                                new OA\Property(property: 'routing_number', type: 'string', example: '987654321'),
                                new OA\Property(property: 'swift_code', type: 'string', example: 'CHASUS33'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-06-23T00:58:13+05:30'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-06-23T00:58:13+05:30')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(
                response: 404,
                description: 'Driver profile not found.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Driver profile not found.')
                    ]
                )
            ),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse')
        ]
    )]
    public function saveBankAccount(SaveBankAccountRequest $request): JsonResponse
    {
        $driver = $request->user()->driverProfile;

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 404);
        }

        $dto = SaveBankAccountDTO::fromRequest($request);
        $bankAccount = $this->verificationService->saveBankAccount($driver, $dto);

        return response()->json([
            'success' => true,
            'message' => 'Bank account saved successfully.',
            'bank_account' => new DriverBankAccountResource($bankAccount),
        ]);
    }

    // Remaining driver stubs for future phases
    public function toggleOnlineStatus(Request $request) {}
    public function updateLocation(Request $request) {}
    public function updateSettings(Request $request) {}
    public function activeRequests(Request $request) {}
    public function acceptRequest(Request $request, $requestId) {}
    public function declineRequest(Request $request, $requestId) {}
    public function arriveAtPickup(Request $request, $ride) {}
    public function startRide(Request $request, $ride) {}
    public function completeRide(Request $request, $ride) {}
    public function rideHistory(Request $request) {}
    public function earningsSummary(Request $request) {}
    public function reviewRider(Request $request, $ride) {}
    public function walletCashout(Request $request) {}
    public function notifications(Request $request) {}
}
