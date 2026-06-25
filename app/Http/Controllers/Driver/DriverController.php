<?php

namespace App\Http\Controllers\Driver;

use App\DTOs\SaveBankAccountDTO;
use App\DTOs\UploadDocumentDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveBankAccountRequest;
use App\Http\Requests\UploadDocumentRequest;
use App\Http\Requests\UpdateDriverStatusRequest;
use App\Http\Requests\UpdateDriverLocationRequest;
use App\Http\Resources\DriverBankAccountResource;
use App\Http\Resources\DriverDocumentResource;
use App\Http\Resources\DriverOnboardingStatusResource;
use App\Http\Resources\DriverDashboardResource;
use App\Services\DriverVerificationService;
use App\Services\DriverLocationService;
use App\Enums\UserStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DriverController extends Controller
{
    public function __construct(
        protected DriverVerificationService $verificationService,
        protected DriverLocationService $locationService
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
    #[OA\Post(
        path: '/driver/status',
        summary: 'Toggle Driver Status',
        description: 'Enables an active, verified driver to toggle their availability online or offline.',
        security: [['bearerAuth' => []]],
        tags: ['Driver Availability'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateDriverStatusRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Driver status updated successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Driver status updated successfully.'),
                        new OA\Property(property: 'is_online', type: 'boolean', example: true)
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, description: 'Only active approved drivers can go online.', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: false),
                    new OA\Property(property: 'message', type: 'string', example: 'Only active approved drivers can go online.')
                ]
            )),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse')
        ]
    )]
    public function toggleOnlineStatus(UpdateDriverStatusRequest $request): JsonResponse
    {
        $user = $request->user();
        $driver = $user->driverProfile;

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 404);
        }

        // Only active approved drivers can go online
        if ($user->status !== UserStatus::ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => 'Only active approved drivers can go online.',
            ], 403);
        }

        $this->locationService->toggleOnlineStatus($driver, $request->boolean('is_online'));

        return response()->json([
            'success' => true,
            'message' => 'Driver status updated successfully.',
            'is_online' => $driver->is_online,
        ]);
    }

    #[OA\Post(
        path: '/driver/location',
        summary: 'Update Driver Location',
        description: 'Updates the live location coordinates (latitude, longitude, bearing) for the authenticated driver. Synchronizes with Redis if the driver is online.',
        security: [['bearerAuth' => []]],
        tags: ['Driver Availability'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateDriverLocationRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Driver location updated successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Driver location updated successfully.')
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse')
        ]
    )]
    public function updateLocation(UpdateDriverLocationRequest $request): JsonResponse
    {
        $driver = $request->user()->driverProfile;

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 404);
        }

        $this->locationService->updateLocation(
            $driver,
            (float) $request->input('current_latitude'),
            (float) $request->input('current_longitude'),
            $request->has('bearing') ? (float) $request->input('bearing') : null
        );

        return response()->json([
            'success' => true,
            'message' => 'Driver location updated successfully.',
        ]);
    }

    #[OA\Get(
        path: '/driver/dashboard',
        summary: 'Get Driver Dashboard',
        description: 'Retrieves driver dashboard details including profile summary, ratings, online status, and completed rides.',
        security: [['bearerAuth' => []]],
        tags: ['Driver Availability'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Driver dashboard details retrieved successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'dashboard',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'driver_profile_id', type: 'integer', example: 1),
                                new OA\Property(property: 'is_online', type: 'boolean', example: true),
                                new OA\Property(property: 'rating', type: 'number', example: 5.0),
                                new OA\Property(property: 'acceptance_rate', type: 'number', example: 98.5),
                                new OA\Property(property: 'ontime_rate', type: 'number', example: 99.1),
                                new OA\Property(property: 'completed_rides_count', type: 'integer', example: 0),
                                new OA\Property(
                                    property: 'earnings_summary',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'today', type: 'number', example: 0.0),
                                        new OA\Property(property: 'this_week', type: 'number', example: 0.0),
                                        new OA\Property(property: 'total', type: 'number', example: 0.0)
                                    ]
                                ),
                                new OA\Property(
                                    property: 'profile',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'name', type: 'string', example: 'Bob Driver'),
                                        new OA\Property(property: 'email', type: 'string', example: 'bob.driver@example.com'),
                                        new OA\Property(property: 'phone', type: 'string', example: '+447911999999'),
                                        new OA\Property(property: 'avatar_url', type: 'string', nullable: true, example: null)
                                    ]
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse')
        ]
    )]
    public function dashboard(Request $request): JsonResponse
    {
        $driver = $request->user()->driverProfile;

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 404);
        }

        $driver->load('user');

        return response()->json([
            'success' => true,
            'dashboard' => new DriverDashboardResource($driver),
        ]);
    }
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

    /**
     * Retrieve a list of active, pending ride requests offered to the authenticated driver.
     */
    #[OA\Get(
        path: '/driver/ride-requests',
        summary: 'Get Pending Ride Requests',
        description: 'Retrieve a list of active, pending ride requests offered to the authenticated driver.',
        security: [['bearerAuth' => []]],
        tags: ['Driver Matching'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Pending ride requests list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'requests',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/RideRequest')
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse')
        ]
    )]
    public function rideRequests(Request $request): JsonResponse
    {
        $driverProfile = $request->user()->driverProfile;
        if (!$driverProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 404);
        }

        // Expire older pending requests
        \App\Models\RideRequest::where('driver_profile_id', $driverProfile->id)
            ->where('status', \App\Enums\RideRequestStatus::PENDING)
            ->where('expires_at', '<=', now())
            ->update(['status' => \App\Enums\RideRequestStatus::EXPIRED]);

        $requests = \App\Models\RideRequest::where('driver_profile_id', $driverProfile->id)
            ->where('status', \App\Enums\RideRequestStatus::PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->with(['ride.rider'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'requests' => \App\Http\Resources\RideRequestResource::collection($requests),
        ]);
    }

    /**
     * Accept a pending ride request. Assigns the driver and locks the ride to prevent race conditions.
     */
    #[OA\Post(
        path: '/driver/ride-requests/{request}/accept',
        summary: 'Accept Ride Request',
        description: 'Accept a pending ride request. Assigns the driver and locks the ride to prevent race conditions.',
        security: [['bearerAuth' => []]],
        tags: ['Driver Matching'],
        parameters: [
            new OA\Parameter(name: 'request', in: 'path', required: true, description: 'ID of the RideRequest', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ride request accepted successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Ride request accepted successfully.'),
                        new OA\Property(property: 'ride', type: 'object', ref: '#/components/schemas/Ride')
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(
                response: 422,
                description: 'Ride request is no longer available.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Ride request is no longer available.')
                    ]
                )
            )
        ]
    )]
    public function acceptRideRequest(Request $httpRequest, \App\Models\RideRequest $request): JsonResponse
    {
        $driverProfile = $httpRequest->user()->driverProfile;
        if (!$driverProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 404);
        }

        if ($request->driver_profile_id !== $driverProfile->id) {
            return response()->json([
                'success' => false,
                'message' => 'This request is not assigned to you.',
            ], 403);
        }

        if ($request->expires_at && $request->expires_at->isPast()) {
            $request->update(['status' => \App\Enums\RideRequestStatus::EXPIRED]);
        }

        if ($request->status !== \App\Enums\RideRequestStatus::PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'This request is no longer available.',
            ], 422);
        }

        try {
            $ride = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $driverProfile) {
                // Lock the ride for update to prevent race conditions
                $ride = \App\Models\Ride::where('id', $request->ride_id)->lockForUpdate()->first();

                if (!$ride || $ride->status !== \App\Enums\RideStatus::PENDING) {
                    throw new \Exception('Ride request is no longer available.');
                }

                // Accept this request
                $request->update(['status' => \App\Enums\RideRequestStatus::ACCEPTED]);

                // Mark other requests for this ride as expired
                \App\Models\RideRequest::where('ride_id', $ride->id)
                    ->where('id', '!=', $request->id)
                    ->where('status', \App\Enums\RideRequestStatus::PENDING)
                    ->update(['status' => \App\Enums\RideRequestStatus::EXPIRED]);

                // Update the Ride status and assign driver
                $ride->update([
                    'status' => \App\Enums\RideStatus::ACCEPTED,
                    'driver_profile_id' => $driverProfile->id,
                    'accepted_at' => now(),
                ]);

                return $ride;
            });

            return response()->json([
                'success' => true,
                'message' => 'Ride request accepted successfully.',
                'ride' => new \App\Http\Resources\RideResource($ride->load(['rider', 'driverProfile.user'])),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Decline a pending ride request.
     */
    #[OA\Post(
        path: '/driver/ride-requests/{request}/decline',
        summary: 'Decline Ride Request',
        description: 'Decline a pending ride request.',
        security: [['bearerAuth' => []]],
        tags: ['Driver Matching'],
        parameters: [
            new OA\Parameter(name: 'request', in: 'path', required: true, description: 'ID of the RideRequest', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ride request declined successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Ride request declined successfully.')
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse')
        ]
    )]
    public function declineRideRequest(Request $httpRequest, \App\Models\RideRequest $request): JsonResponse
    {
        $driverProfile = $httpRequest->user()->driverProfile;
        if (!$driverProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 404);
        }

        if ($request->driver_profile_id !== $driverProfile->id) {
            return response()->json([
                'success' => false,
                'message' => 'This request is not assigned to you.',
            ], 403);
        }

        if ($request->status !== \App\Enums\RideRequestStatus::PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'This request cannot be declined.',
            ], 422);
        }

        $request->update(['status' => \App\Enums\RideRequestStatus::DECLINED]);

        return response()->json([
            'success' => true,
            'message' => 'Ride request declined successfully.',
        ]);
    }

    /**
     * Retrieve the current active, non-completed, non-cancelled ride assigned to the authenticated driver.
     */
    #[OA\Get(
        path: '/driver/active-ride',
        summary: 'Get Driver Active Ride',
        description: 'Retrieve the current active, non-completed, non-cancelled ride assigned to the authenticated driver.',
        security: [['bearerAuth' => []]],
        tags: ['Driver Matching'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Active ride details.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'ride', type: 'object', ref: '#/components/schemas/Ride')
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(
                response: 404,
                description: 'No active ride found.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'No active ride found.')
                    ]
                )
            )
        ]
    )]
    public function activeRide(Request $request): JsonResponse
    {
        $driverProfile = $request->user()->driverProfile;
        if (!$driverProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 404);
        }

        $ride = \App\Models\Ride::where('driver_profile_id', $driverProfile->id)
            ->whereNotIn('status', [\App\Enums\RideStatus::COMPLETED, \App\Enums\RideStatus::CANCELLED])
            ->with(['rider', 'driverProfile.user'])
            ->latest()
            ->first();

        if (!$ride) {
            return response()->json([
                'success' => false,
                'message' => 'No active ride found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'ride' => new \App\Http\Resources\RideResource($ride),
        ]);
    }
}
