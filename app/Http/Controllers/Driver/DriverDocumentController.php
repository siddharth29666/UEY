<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\DriverDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class DriverDocumentController extends Controller
{
    /**
     * View a driver document directly in browser.
     */
    #[OA\Get(
        path: '/driver/documents/{document}/view',
        summary: 'View Driver Document',
        description: 'Streams the driver document directly to the browser (inline) for previewing.',
        security: [['bearerAuth' => []]],
        tags: ['Driver Documents'],
        parameters: [
            new OA\Parameter(
                name: 'document',
                in: 'path',
                required: true,
                description: 'The ID of the driver document.',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Document file preview stream.'
            ),
            new OA\Response(
                response: 403,
                description: 'Unauthorized access.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthorized.')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Document or physical file not found.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Document file not found.')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/UnauthorizedResponse'
            )
        ]
    )]
    public function view(Request $request, DriverDocument $document)
    {
        // 1. Ownership validation
        $driverProfile = $request->user()->driverProfile;
        if (!$driverProfile || $document->driver_profile_id !== $driverProfile->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        // 2. Physical file existence validation
        if (!Storage::disk('local')->exists($document->document_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Document file not found.'
            ], 404);
        }

        return Storage::disk('local')->response($document->document_path);
    }

    /**
     * Download a driver document.
     */
    #[OA\Get(
        path: '/driver/documents/{document}/download',
        summary: 'Download Driver Document',
        description: 'Initiates a file download for the requested driver document.',
        security: [['bearerAuth' => []]],
        tags: ['Driver Documents'],
        parameters: [
            new OA\Parameter(
                name: 'document',
                in: 'path',
                required: true,
                description: 'The ID of the driver document.',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Document file download stream.'
            ),
            new OA\Response(
                response: 403,
                description: 'Unauthorized access.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthorized.')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Document or physical file not found.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Document file not found.')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/UnauthorizedResponse'
            )
        ]
    )]
    public function download(Request $request, DriverDocument $document)
    {
        // 1. Ownership validation
        $driverProfile = $request->user()->driverProfile;
        if (!$driverProfile || $document->driver_profile_id !== $driverProfile->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        // 2. Physical file existence validation
        if (!Storage::disk('local')->exists($document->document_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Document file not found.'
            ], 404);
        }

        return Storage::disk('local')->download($document->document_path);
    }
}
