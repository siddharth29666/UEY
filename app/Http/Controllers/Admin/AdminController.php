<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyDocumentRequest;
use App\Http\Resources\DriverDocumentResource;
use App\Models\DriverDocument;
use App\Services\DriverVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AdminController extends Controller
{
    public function __construct(
        protected DriverVerificationService $verificationService
    ) {}

    /**
     * View all pending documents across all drivers.
     */
    #[OA\Get(
        path: '/admin/documents/pending',
        summary: 'Get Pending Documents',
        description: 'Retrieves a list of all uploaded driver documents currently pending verification.',
        security: [['bearerAuth' => []]],
        tags: ['Admin', 'Driver Verification'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of pending documents retrieved successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
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
                                    new OA\Property(property: 'expires_at', type: 'string', format: 'date', example: '2028-12-31'),
                                    new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-06-23T00:58:13+05:30'),
                                    new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-06-23T00:58:13+05:30')
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse')
        ]
    )]
    public function pendingDocuments(Request $request): JsonResponse
    {
        $documents = DriverDocument::with('driverProfile.user')
            ->where('status', DocumentStatus::PENDING)
            ->get();

        return response()->json([
            'success' => true,
            'documents' => DriverDocumentResource::collection($documents),
        ]);
    }

    /**
     * Approve or reject a driver document.
     */
    #[OA\Post(
        path: '/admin/documents/{document}/verify',
        summary: 'Verify Driver Document',
        description: 'Approves or rejects a driver onboarding document. Rejection requires a reason.',
        security: [['bearerAuth' => []]],
        tags: ['Admin', 'Driver Verification'],
        parameters: [
            new OA\Parameter(
                name: 'document',
                in: 'path',
                required: true,
                description: 'The ID of the driver document to verify.',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/VerifyDocumentRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Document has been approved or rejected successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Document has been approved successfully.'),
                        new OA\Property(
                            property: 'document',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'document_type', type: 'string', example: 'driving_license'),
                                new OA\Property(property: 'document_path', type: 'string', example: 'documents/driving_license_123.jpg'),
                                new OA\Property(property: 'document_url', type: 'string', format: 'url', example: 'http://uey.test/storage/documents/driving_license_123.jpg'),
                                new OA\Property(property: 'status', type: 'string', example: 'approved'),
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
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse')
        ]
    )]
    public function verifyDocument(VerifyDocumentRequest $request, $documentId): JsonResponse
    {
        $status = DocumentStatus::from($request->validated('status'));
        $reason = $request->validated('rejection_reason');

        $document = $this->verificationService->verifyDocument((int) $documentId, $status, $reason);

        $statusStr = $status === DocumentStatus::APPROVED ? 'approved' : 'rejected';

        return response()->json([
            'success' => true,
            'message' => "Document has been {$statusStr} successfully.",
            'document' => new DriverDocumentResource($document),
        ]);
    }

    // Remaining admin stubs for future phases
    public function listDrivers(Request $request) {}
    public function listRiders(Request $request) {}
    public function activeRides(Request $request) {}
    public function updatePricing(Request $request, $vehicleType) {}
    public function createPromo(Request $request) {}
}
