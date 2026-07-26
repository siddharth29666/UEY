<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCancellationReasonRequest;
use App\Http\Requests\Admin\UpdateCancellationReasonRequest;
use App\Http\Resources\CancellationReasonResource;
use App\Models\CancellationReason;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AdminCancellationReasonController extends Controller
{
    /**
     * List cancellation reasons.
     */
    #[OA\Get(
        path: '/admin/cancellation-reasons',
        summary: 'Admin — List Cancellation Reasons',
        description: 'Returns all cancellation reasons including active and inactive. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Cancellation Reasons'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cancellation reasons retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/CancellationReasonResource')),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = CancellationReason::query();

        if ($request->filled('type')) {
            $query->whereIn('type', [$request->query('type'), 'both']);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $reasons = $query->orderBy('sort_order', 'asc')->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => CancellationReasonResource::collection($reasons),
        ]);
    }

    /**
     * Create a cancellation reason.
     */
    #[OA\Post(
        path: '/admin/cancellation-reasons',
        summary: 'Admin — Create Cancellation Reason',
        description: 'Creates a new cancellation reason. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Cancellation Reasons'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reason', 'type'],
                properties: [
                    new OA\Property(property: 'reason', type: 'string', example: 'Driver taking too long'),
                    new OA\Property(property: 'type', type: 'string', enum: ['rider', 'driver', 'both'], example: 'rider'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                    new OA\Property(property: 'sort_order', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Cancellation reason created successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Cancellation reason created successfully.'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/CancellationReasonResource'),
                    ]
                )
            ),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ]
    )]
    public function store(StoreCancellationReasonRequest $request): JsonResponse
    {
        $reason = CancellationReason::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cancellation reason created successfully.',
            'data' => new CancellationReasonResource($reason),
        ], 201);
    }

    /**
     * Show cancellation reason details.
     */
    #[OA\Get(
        path: '/admin/cancellation-reasons/{id}',
        summary: 'Admin — Show Cancellation Reason',
        description: 'Retrieves details of a specific cancellation reason.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Cancellation Reasons'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cancellation reason details.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/CancellationReasonResource'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Cancellation reason not found.'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $reason = CancellationReason::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new CancellationReasonResource($reason),
        ]);
    }

    /**
     * Update a cancellation reason.
     */
    #[OA\Put(
        path: '/admin/cancellation-reasons/{id}',
        summary: 'Admin — Update Cancellation Reason',
        description: 'Updates an existing cancellation reason.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Cancellation Reasons'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'reason', type: 'string', example: 'Driver requested cancellation'),
                    new OA\Property(property: 'type', type: 'string', enum: ['rider', 'driver', 'both'], example: 'driver'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                    new OA\Property(property: 'sort_order', type: 'integer', example: 2),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cancellation reason updated successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Cancellation reason updated successfully.'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/CancellationReasonResource'),
                    ]
                )
            ),
        ]
    )]
    public function update(UpdateCancellationReasonRequest $request, int $id): JsonResponse
    {
        $reason = CancellationReason::findOrFail($id);
        $reason->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cancellation reason updated successfully.',
            'data' => new CancellationReasonResource($reason),
        ]);
    }

    /**
     * Toggle cancellation reason active status.
     */
    #[OA\Patch(
        path: '/admin/cancellation-reasons/{id}/status',
        summary: 'Admin — Toggle Cancellation Reason Status',
        description: 'Activates or deactivates a cancellation reason.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Cancellation Reasons'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Status updated successfully.'),
        ]
    )]
    public function status(int $id): JsonResponse
    {
        $reason = CancellationReason::findOrFail($id);
        $reason->is_active = !$reason->is_active;
        $reason->save();

        return response()->json([
            'success' => true,
            'message' => 'Cancellation reason status updated successfully.',
            'data' => new CancellationReasonResource($reason),
        ]);
    }

    /**
     * Delete a cancellation reason.
     */
    #[OA\Delete(
        path: '/admin/cancellation-reasons/{id}',
        summary: 'Admin — Delete Cancellation Reason',
        description: 'Soft deletes a cancellation reason.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Cancellation Reasons'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Cancellation reason deleted successfully.'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $reason = CancellationReason::findOrFail($id);
        $reason->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cancellation reason deleted successfully.',
        ]);
    }
}
