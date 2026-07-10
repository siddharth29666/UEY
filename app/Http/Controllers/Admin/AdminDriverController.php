<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DriverDocumentResource;
use App\Http\Resources\DriverResource;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AdminDriverController extends Controller
{
    public function __construct(
        protected AdminService $adminService
    ) {}

    /**
     * Get paginated and filtered list of drivers.
     */
    #[OA\Get(
        path: '/admin/drivers',
        summary: 'List Drivers',
        description: 'Retrieves a list of all registered drivers, supporting sorting, searching, pagination, and status filters.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Driver Management'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['active', 'suspended', 'pending'])),
            new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', enum: ['latest', 'oldest'])),
            new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Driver list retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'drivers', type: 'array', items: new OA\Items(ref: '#/components/schemas/DriverResource')),
                        new OA\Property(property: 'meta', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = User::where('role', 'driver')->with(['driverProfile.vehicles', 'wallet', 'devices']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $sort = $request->input('sort', 'latest');
        $direction = ($sort === 'oldest') ? 'asc' : 'desc';
        $query->orderBy('id', $direction);

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'drivers' => DriverResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Get specific Driver details.
     */
    #[OA\Get(
        path: '/admin/drivers/{id}',
        summary: 'Get Driver Details',
        description: 'Retrieves a single driver user\'s profile, vehicles, wallet, and devices details.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Driver Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Driver details retrieved.',
                content: new OA\JsonContent(ref: '#/components/schemas/DriverResource')
            ),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
        ]
    )]
    public function show($id): JsonResponse
    {
        $driver = User::where('role', 'driver')->with(['driverProfile.vehicles', 'wallet', 'devices'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'driver' => new DriverResource($driver),
        ]);
    }

    /**
     * Update Driver details.
     */
    #[OA\Put(
        path: '/admin/drivers/{id}',
        summary: 'Update Driver Profile',
        description: 'Allows editing a driver\'s name or email address.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Driver Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Jane Driver'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jane.driver@example.com'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Driver profile updated.'),
        ]
    )]
    public function update(Request $request, $id): JsonResponse
    {
        $driver = User::where('role', 'driver')->findOrFail($id);

        $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:users,email,'.$driver->id],
        ]);

        $driver->update(array_filter($request->only(['name', 'email'])));

        return response()->json([
            'success' => true,
            'message' => 'Driver updated successfully.',
            'driver' => new DriverResource($driver),
        ]);
    }

    /**
     * Approve onboarding Driver.
     */
    #[OA\Post(
        path: '/admin/drivers/{id}/approve',
        summary: 'Approve Driver Onboarding',
        description: 'Marks onboarding driver status as active and approves all their pending documents.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Driver Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Driver approved successfully.'),
        ]
    )]
    public function approve(Request $request, $id): JsonResponse
    {
        $driver = User::where('role', 'driver')->findOrFail($id);
        $this->adminService->approveDriver($driver, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Driver approved successfully.',
        ]);
    }

    /**
     * Reject onboarding Driver.
     */
    #[OA\Post(
        path: '/admin/drivers/{id}/reject',
        summary: 'Reject Driver Onboarding',
        description: 'Resets driver status to pending and rejects pending documents with a reason.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Driver Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'rejection_reason', type: 'string', example: 'Driver license photo blurry.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Driver rejected successfully.'),
        ]
    )]
    public function reject(Request $request, $id): JsonResponse
    {
        $driver = User::where('role', 'driver')->findOrFail($id);
        $reason = $request->input('rejection_reason');

        $this->adminService->rejectDriver($driver, $reason, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Driver onboarding rejected.',
        ]);
    }

    /**
     * Suspend Driver.
     */
    #[OA\Post(
        path: '/admin/drivers/{id}/block',
        summary: 'Block Driver',
        description: 'Suspends the driver user account.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Driver Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Driver blocked successfully.'),
        ]
    )]
    public function block(Request $request, $id): JsonResponse
    {
        $driver = User::where('role', 'driver')->findOrFail($id);
        $this->adminService->blockUser($driver, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Driver blocked successfully.',
        ]);
    }

    /**
     * Unsuspend Driver.
     */
    #[OA\Post(
        path: '/admin/drivers/{id}/unblock',
        summary: 'Unblock Driver',
        description: 'Unsuspends the driver user account, restoring access.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Driver Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Driver unblocked successfully.'),
        ]
    )]
    public function unblock(Request $request, $id): JsonResponse
    {
        $driver = User::where('role', 'driver')->findOrFail($id);
        $this->adminService->unblockUser($driver, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Driver unblocked successfully.',
        ]);
    }

    /**
     * Get Driver Documents list.
     */
    #[OA\Get(
        path: '/admin/drivers/{id}/documents',
        summary: 'Get Driver Documents',
        description: 'Retrieves all onboarding documents uploaded by this driver.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Driver Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Driver documents retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'documents', type: 'array', items: new OA\Items(ref: '#/components/schemas/DriverDocumentResource')),
                    ]
                )
            ),
        ]
    )]
    public function documents($id): JsonResponse
    {
        $driver = User::where('role', 'driver')->findOrFail($id);
        $profile = $driver->driverProfile;

        $documents = $profile ? $profile->documents()->get() : collect();

        return response()->json([
            'success' => true,
            'documents' => DriverDocumentResource::collection($documents),
        ]);
    }
}
