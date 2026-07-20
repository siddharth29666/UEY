<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminVehicleTypeRequest;
use App\Http\Resources\VehicleTypeResource;
use App\Models\VehicleType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class VehicleTypeController extends Controller
{
    /**
     * List all vehicle types for admins.
     */
    #[OA\Get(
        path: '/admin/vehicle-types',
        summary: 'Admin: List All Vehicle Types',
        description: 'Retrieves all vehicle types (active and inactive) sorted alphabetically.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Vehicle Types'],
        responses: [
            new OA\Response(response: 200, description: 'List of all vehicle types.'),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
        ]
    )]
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', VehicleType::class);

        $types = VehicleType::withTrashed()->orderBy('name', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => VehicleTypeResource::collection($types),
        ]);
    }

    /**
     * Create a new vehicle type.
     */
    #[OA\Post(
        path: '/admin/vehicle-types',
        summary: 'Admin: Create Vehicle Type',
        description: 'Creates a new vehicle type with rates and capacity settings.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Vehicle Types'],
        requestBody: new OA\RequestBody(required: true),
        responses: [
            new OA\Response(response: 201, description: 'Vehicle type created successfully.'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
        ]
    )]
    public function store(AdminVehicleTypeRequest $request): JsonResponse
    {
        Gate::authorize('create', VehicleType::class);

        $data = $request->validated();
        if (!isset($data['active'])) {
            $data['active'] = true;
        }

        $type = VehicleType::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Vehicle type created successfully.',
            'data' => new VehicleTypeResource($type),
        ], 201);
    }

    /**
     * Get a specific vehicle type.
     */
    #[OA\Get(
        path: '/admin/vehicle-types/{id}',
        summary: 'Admin: Show Vehicle Type',
        description: 'Retrieves details of a specific vehicle type.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Vehicle Types'],
        responses: [
            new OA\Response(response: 200, description: 'Vehicle type details.'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $type = VehicleType::withTrashed()->findOrFail($id);

        Gate::authorize('view', $type);

        return response()->json([
            'success' => true,
            'data' => new VehicleTypeResource($type),
        ]);
    }

    /**
     * Update a vehicle type.
     */
    #[OA\Put(
        path: '/admin/vehicle-types/{id}',
        summary: 'Admin: Update Vehicle Type',
        description: 'Updates configuration rates or metadata for a vehicle type.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Vehicle Types'],
        requestBody: new OA\RequestBody(required: true),
        responses: [
            new OA\Response(response: 200, description: 'Vehicle type updated successfully.'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ]
    )]
    public function update(int $id, AdminVehicleTypeRequest $request): JsonResponse
    {
        $type = VehicleType::withTrashed()->findOrFail($id);

        Gate::authorize('update', $type);

        $type->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Vehicle type updated successfully.',
            'data' => new VehicleTypeResource($type),
        ]);
    }

    /**
     * Delete a vehicle type.
     */
    #[OA\Delete(
        path: '/admin/vehicle-types/{id}',
        summary: 'Admin: Delete Vehicle Type',
        description: 'Soft deletes a vehicle type from the system.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Vehicle Types'],
        responses: [
            new OA\Response(response: 200, description: 'Vehicle type deleted successfully.'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $type = VehicleType::withTrashed()->findOrFail($id);

        Gate::authorize('delete', $type);

        $type->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vehicle type deleted successfully.',
        ]);
    }

    /**
     * Update active status of a vehicle type.
     */
    #[OA\Patch(
        path: '/admin/vehicle-types/{id}/status',
        summary: 'Admin: Toggle Vehicle Type Status',
        description: 'Activates or deactivates a vehicle type.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Vehicle Types'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['active'],
                properties: [
                    new OA\Property(property: 'active', type: 'boolean', example: false)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Vehicle type status updated successfully.'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
        ]
    )]
    public function status(int $id, \Illuminate\Http\Request $request): JsonResponse
    {
        $type = VehicleType::withTrashed()->findOrFail($id);

        Gate::authorize('update', $type);

        $data = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        $type->update(['active' => $data['active']]);

        return response()->json([
            'success' => true,
            'message' => 'Vehicle type status updated successfully.',
            'data' => new VehicleTypeResource($type),
        ]);
    }

    /**
     * Restore a soft deleted vehicle type.
     */
    #[OA\Post(
        path: '/admin/vehicle-types/{id}/restore',
        summary: 'Admin: Restore Vehicle Type',
        description: 'Restores a previously soft deleted vehicle type.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Vehicle Types'],
        responses: [
            new OA\Response(response: 200, description: 'Vehicle type restored successfully.'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
        ]
    )]
    public function restore(int $id): JsonResponse
    {
        $type = VehicleType::onlyTrashed()->findOrFail($id);

        Gate::authorize('update', $type);

        $type->restore();

        return response()->json([
            'success' => true,
            'message' => 'Vehicle type restored successfully.',
        ]);
    }
}
