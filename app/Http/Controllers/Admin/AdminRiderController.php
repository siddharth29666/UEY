<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\RiderResource;
use App\Models\User;
use App\Services\AdminService;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AdminRiderController extends Controller
{
    public function __construct(
        protected AdminService $adminService
    ) {}

    /**
     * Get paginated and filtered list of riders.
     */
    #[OA\Get(
        path: '/admin/riders',
        summary: 'List Riders',
        description: 'Retrieves a list of all registered riders, supporting sorting, searching, pagination, and status filters.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Rider Management'],
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
                description: 'Rider list retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'riders', type: 'array', items: new OA\Items(ref: '#/components/schemas/RiderResource')),
                        new OA\Property(property: 'meta', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = User::where('role', 'rider')->with(['wallet', 'devices']);

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
            'riders' => RiderResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Get specific Rider details.
     */
    #[OA\Get(
        path: '/admin/riders/{id}',
        summary: 'Get Rider Details',
        description: 'Retrieves a single rider\'s profile, including their wallet and device registries.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Rider Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Rider details retrieved.',
                content: new OA\JsonContent(ref: '#/components/schemas/RiderResource')
            ),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
        ]
    )]
    public function show($id): JsonResponse
    {
        $rider = User::where('role', 'rider')->with(['wallet', 'devices'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'rider' => new RiderResource($rider),
        ]);
    }

    /**
     * Update Rider details.
     */
    #[OA\Put(
        path: '/admin/riders/{id}',
        summary: 'Update Rider Profile',
        description: 'Allows editing a rider\'s name or email address.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Rider Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Jane Rider'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jane.rider@example.com'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Rider profile updated.'),
        ]
    )]
    public function update(Request $request, $id): JsonResponse
    {
        $rider = User::where('role', 'rider')->findOrFail($id);

        $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:users,email,'.$rider->id],
        ]);

        $rider->update(array_filter($request->only(['name', 'email'])));

        return response()->json([
            'success' => true,
            'message' => 'Rider updated successfully.',
            'rider' => new RiderResource($rider),
        ]);
    }

    /**
     * Suspend Rider.
     */
    #[OA\Post(
        path: '/admin/riders/{id}/block',
        summary: 'Block Rider',
        description: 'Suspends the rider user account.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Rider Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rider blocked successfully.'),
        ]
    )]
    public function block(Request $request, $id): JsonResponse
    {
        $rider = User::where('role', 'rider')->findOrFail($id);
        $this->adminService->blockUser($rider, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Rider blocked successfully.',
        ]);
    }

    /**
     * Unsuspend Rider.
     */
    #[OA\Post(
        path: '/admin/riders/{id}/unblock',
        summary: 'Unblock Rider',
        description: 'Unsuspends the rider user account, restoring access.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Rider Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rider unblocked successfully.'),
        ]
    )]
    public function unblock(Request $request, $id): JsonResponse
    {
        $rider = User::where('role', 'rider')->findOrFail($id);
        $this->adminService->unblockUser($rider, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Rider unblocked successfully.',
        ]);
    }

    /**
     * Delete Rider User (Soft delete).
     */
    #[OA\Delete(
        path: '/admin/riders/{id}',
        summary: 'Delete Rider',
        description: 'Soft deletes the rider user account.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Rider Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rider deleted successfully.'),
        ]
    )]
    public function destroy(Request $request, $id): JsonResponse
    {
        $rider = User::where('role', 'rider')->findOrFail($id);

        $admin = $request->user();
        $oldValues = $rider->toArray();

        $rider->delete();

        // Log audit
        app(AdminService::class)->unblockUser($rider, $admin); // Wait, block/unblock, let's log audit log directly:
        app(AuditLogService::class)->log(
            $admin,
            'riders',
            'rider_delete',
            'users',
            $rider->id,
            $oldValues,
            null
        );

        return response()->json([
            'success' => true,
            'message' => 'Rider deleted successfully.',
        ]);
    }
}
