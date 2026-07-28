<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromoCodeRequest;
use App\Http\Resources\PromoCodeResource;
use App\Models\PromoCode;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AdminPromoCodeController extends Controller
{
    public function __construct(
        protected AuditLogService $auditService
    ) {}

    /**
     * Get paginated and filtered promo codes.
     */
    #[OA\Get(
        path: '/admin/promo-codes',
        summary: 'List Promo Codes',
        description: 'Retrieves all promo codes config settings.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Promo Code Management'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Promo codes list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'promo_codes', type: 'array', items: new OA\Items(ref: '#/components/schemas/PromoCodeResource')),
                        new OA\Property(property: 'meta', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = PromoCode::withTrashed();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('code', 'like', "%{$search}%");
        }

        $query->orderBy('id', 'desc');

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'promo_codes' => PromoCodeResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Get specific Promo Code details.
     */
    #[OA\Get(
        path: '/admin/promo-codes/{id}',
        summary: 'Get Promo Code Details',
        description: 'Retrieves details of a single promo code setup.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Promo Code Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Promo Code details.',
                content: new OA\JsonContent(ref: '#/components/schemas/PromoCodeResource')
            ),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
        ]
    )]
    public function show($id): JsonResponse
    {
        $promo = PromoCode::withTrashed()->findOrFail($id);

        return response()->json([
            'success' => true,
            'promo_code' => new PromoCodeResource($promo),
        ]);
    }

    /**
     * Create Promo Code.
     */
    #[OA\Post(
        path: '/admin/promo-codes',
        summary: 'Create Promo Code',
        description: 'Registers a new promo code with usability limits, expiry, and discounts.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Promo Code Management'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PromoCodeRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Promo Code created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'promo_code', ref: '#/components/schemas/PromoCodeResource'),
                    ]
                )
            ),
        ]
    )]
    public function store(PromoCodeRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (isset($data['discount_type']) && in_array(strtolower($data['discount_type']), ['fixed', 'amount'])) {
            $data['discount_type'] = 'flat';
        }

        $promo = PromoCode::create($data);

        $this->auditService->log(
            $request->user(),
            'promo_codes',
            'promo_create',
            'promo_codes',
            $promo->id,
            null,
            $promo->toArray()
        );

        return response()->json([
            'success' => true,
            'promo_code' => new PromoCodeResource($promo),
        ], 201);
    }

    /**
     * Update Promo Code.
     */
    #[OA\Put(
        path: '/admin/promo-codes/{id}',
        summary: 'Update Promo Code',
        description: 'Updates attributes of an existing promo code.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Promo Code Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PromoCodeRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Promo Code updated successfully.'),
        ]
    )]
    public function update(PromoCodeRequest $request, $id): JsonResponse
    {
        $promo = PromoCode::withTrashed()->findOrFail($id);
        $oldValues = $promo->toArray();

        $data = $request->validated();
        if (isset($data['discount_type']) && in_array(strtolower($data['discount_type']), ['fixed', 'amount'])) {
            $data['discount_type'] = 'flat';
        }

        $promo->update($data);

        $this->auditService->log(
            $request->user(),
            'promo_codes',
            'promo_update',
            'promo_codes',
            $promo->id,
            $oldValues,
            $promo->fresh()->toArray()
        );

        return response()->json([
            'success' => true,
            'message' => 'Promo Code updated successfully.',
            'promo_code' => new PromoCodeResource($promo),
        ]);
    }

    /**
     * Delete Promo Code.
     */
    #[OA\Delete(
        path: '/admin/promo-codes/{id}',
        summary: 'Delete Promo Code',
        description: 'Permanently deletes a promo code config.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Promo Code Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Promo Code deleted successfully.'),
        ]
    )]
    public function destroy(Request $request, $id): JsonResponse
    {
        $promo = PromoCode::withTrashed()->findOrFail($id);
        $oldValues = $promo->toArray();

        $promo->delete();

        $this->auditService->log(
            $request->user(),
            'promo_codes',
            'promo_delete',
            'promo_codes',
            $id,
            $oldValues,
            null
        );

        return response()->json([
            'success' => true,
            'message' => 'Promo Code deleted successfully.',
        ]);
    }

    /**
     * Activate or Deactivate Promo Code.
     */
    #[OA\Patch(
        path: '/admin/promo-codes/{id}/status',
        summary: 'Activate or Deactivate Promo Code',
        description: 'Enables or disables a promo code.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Promo Code Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['active'],
                properties: [
                    new OA\Property(property: 'active', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Promo Code status updated successfully.'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ]
    )]
    public function status(Request $request, $id): JsonResponse
    {
        $promo = PromoCode::withTrashed()->findOrFail($id);
        $oldValues = $promo->toArray();

        $data = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        $promo->update(['is_active' => $data['active']]);

        $action = $data['active'] ? 'promo_activate' : 'promo_deactivate';

        $this->auditService->log(
            $request->user(),
            'promo_codes',
            $action,
            'promo_codes',
            $promo->id,
            $oldValues,
            $promo->fresh()->toArray()
        );

        return response()->json([
            'success' => true,
            'message' => 'Promo Code status updated successfully.',
            'promo_code' => new PromoCodeResource($promo),
        ]);
    }

    /**
     * Restore Soft Deleted Promo Code.
     */
    #[OA\Post(
        path: '/admin/promo-codes/{id}/restore',
        summary: 'Restore Soft Deleted Promo Code',
        description: 'Restores a previously soft deleted promo code config.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Promo Code Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Promo Code restored successfully.'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
        ]
    )]
    public function restore(Request $request, $id): JsonResponse
    {
        $promo = PromoCode::onlyTrashed()->findOrFail($id);
        $oldValues = $promo->toArray();

        $promo->restore();

        $this->auditService->log(
            $request->user(),
            'promo_codes',
            'promo_restore',
            'promo_codes',
            $promo->id,
            $oldValues,
            $promo->fresh()->toArray()
        );

        return response()->json([
            'success' => true,
            'message' => 'Promo Code restored successfully.',
            'promo_code' => new PromoCodeResource($promo),
        ]);
    }
}
