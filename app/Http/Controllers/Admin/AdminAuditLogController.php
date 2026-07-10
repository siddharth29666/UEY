<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AdminAuditLogController extends Controller
{
    /**
     * Get paginated and filtered audit logs.
     */
    #[OA\Get(
        path: '/admin/audit-logs',
        summary: 'List Audit Logs',
        description: 'Retrieves history logs of all administrative actions, supporting filter parameters.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Audit Logs'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'module', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'admin_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Audit logs retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'audit_logs', type: 'array', items: new OA\Items(ref: '#/components/schemas/AuditLogResource')),
                        new OA\Property(property: 'meta', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('admin');

        if ($request->filled('module')) {
            $query->where('module', $request->input('module'));
        }

        if ($request->filled('admin_id')) {
            $query->where('admin_id', $request->input('admin_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('admin_name', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('module', 'like', "%{$search}%");
            });
        }

        $query->orderBy('id', 'desc');

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'audit_logs' => AuditLogResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Get specific Audit Log details.
     */
    #[OA\Get(
        path: '/admin/audit-logs/{id}',
        summary: 'Get Audit Log Details',
        description: 'Retrieves details of a specific administrative audit log.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Audit Logs'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Audit log details.',
                content: new OA\JsonContent(ref: '#/components/schemas/AuditLogResource')
            ),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
        ]
    )]
    public function show($id): JsonResponse
    {
        $log = AuditLog::with('admin')->findOrFail($id);

        return response()->json([
            'success' => true,
            'audit_log' => new AuditLogResource($log),
        ]);
    }
}
