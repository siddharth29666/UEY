<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectVehicleRequest;
use App\Http\Requests\Admin\UpdateVehicleStatusRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AdminVehicleController extends Controller
{
    /**
     * List driver vehicles with status, type, and driver filters.
     */
    #[OA\Get(
        path: '/admin/vehicles',
        summary: 'Admin — List Driver Vehicles',
        description: 'Retrieves driver vehicles with filters for status, vehicle_type_id, driver_id, and search query.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Vehicle Management'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'approved', 'rejected'])),
            new OA\Parameter(name: 'vehicle_type_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'driver_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Driver vehicles retrieved successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/VehicleResource')),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Vehicle::with(['driverProfile.user', 'vehicleType']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('vehicle_type_id')) {
            $query->where('vehicle_type_id', $request->query('vehicle_type_id'));
        }

        if ($request->filled('driver_id')) {
            $query->where('driver_profile_id', $request->query('driver_id'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('make', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('plate_number', 'like', "%{$search}%");
            });
        }

        $vehicles = $query->orderBy('id', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => VehicleResource::collection($vehicles),
            'pagination' => [
                'total' => $vehicles->total(),
                'per_page' => $vehicles->perPage(),
                'current_page' => $vehicles->currentPage(),
                'last_page' => $vehicles->lastPage(),
            ],
        ]);
    }

    /**
     * List pending driver vehicles awaiting approval.
     */
    #[OA\Get(
        path: '/admin/vehicles/pending',
        summary: 'Admin — List Pending Driver Vehicles',
        description: 'Returns only driver vehicles with pending approval status.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Vehicle Management'],
        responses: [
            new OA\Response(response: 200, description: 'Pending driver vehicles retrieved.'),
        ]
    )]
    public function pending(): JsonResponse
    {
        $vehicles = Vehicle::with(['driverProfile.user', 'vehicleType'])
            ->where('status', VehicleStatus::PENDING)
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => VehicleResource::collection($vehicles),
        ]);
    }

    /**
     * Show vehicle details.
     */
    #[OA\Get(
        path: '/admin/vehicles/{id}',
        summary: 'Admin — Show Vehicle Details',
        description: 'Retrieves complete vehicle details including driver profile and vehicle type.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Vehicle Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Vehicle details retrieved.'),
            new OA\Response(response: 404, description: 'Vehicle not found.'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $vehicle = Vehicle::with(['driverProfile.user', 'vehicleType'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new VehicleResource($vehicle),
        ]);
    }

    /**
     * Approve driver vehicle.
     */
    #[OA\Post(
        path: '/admin/vehicles/{id}/approve',
        summary: 'Admin — Approve Driver Vehicle',
        description: 'Approves a driver vehicle and sets status to approved.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Vehicle Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Vehicle approved successfully.'),
            new OA\Response(response: 404, description: 'Vehicle not found.'),
        ]
    )]
    public function approve(int $id): JsonResponse
    {
        $vehicle = Vehicle::with(['driverProfile.user', 'vehicleType'])->findOrFail($id);

        $vehicle->update([
            'status' => VehicleStatus::APPROVED,
            'rejection_reason' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vehicle approved successfully.',
            'data' => new VehicleResource($vehicle),
        ]);
    }

    /**
     * Reject driver vehicle.
     */
    #[OA\Post(
        path: '/admin/vehicles/{id}/reject',
        summary: 'Admin — Reject Driver Vehicle',
        description: 'Rejects a driver vehicle with optional rejection reason.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Vehicle Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'rejection_reason', type: 'string', example: 'Vehicle plate number does not match registration documents.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Vehicle rejected successfully.'),
            new OA\Response(response: 404, description: 'Vehicle not found.'),
        ]
    )]
    public function reject(RejectVehicleRequest $request, int $id): JsonResponse
    {
        $vehicle = Vehicle::with(['driverProfile.user', 'vehicleType'])->findOrFail($id);

        $vehicle->update([
            'status' => VehicleStatus::REJECTED,
            'rejection_reason' => $request->input('rejection_reason', 'Vehicle rejected by administrator.'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vehicle rejected successfully.',
            'data' => new VehicleResource($vehicle),
        ]);
    }

    /**
     * Update vehicle status directly.
     */
    #[OA\Patch(
        path: '/admin/vehicles/{id}/status',
        summary: 'Admin — Update Vehicle Status',
        description: 'Updates vehicle status directly to approved, rejected, or pending.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Vehicle Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'approved', 'rejected'], example: 'approved'),
                    new OA\Property(property: 'rejection_reason', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Vehicle status updated successfully.'),
            new OA\Response(response: 404, description: 'Vehicle not found.'),
            new OA\Response(response: 422, description: 'Validation Error.'),
        ]
    )]
    public function status(UpdateVehicleStatusRequest $request, int $id): JsonResponse
    {
        $vehicle = Vehicle::with(['driverProfile.user', 'vehicleType'])->findOrFail($id);
        $data = $request->validated();

        $vehicle->update([
            'status' => $data['status'],
            'rejection_reason' => $data['status'] === 'rejected' ? ($data['rejection_reason'] ?? 'Rejected by admin') : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vehicle status updated successfully.',
            'data' => new VehicleResource($vehicle),
        ]);
    }
}
