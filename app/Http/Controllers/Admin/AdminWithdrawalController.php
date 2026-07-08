<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\WithdrawalResource;
use App\Models\WithdrawalRequest;
use App\Services\AuditLogService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AdminWithdrawalController extends Controller
{
    public function __construct(
        protected WalletService $walletService,
        protected AuditLogService $auditService
    ) {}

    /**
     * Get paginated and filtered list of withdrawal requests.
     */
    #[OA\Get(
        path: '/admin/withdrawals',
        summary: 'List Withdrawals',
        description: 'Retrieves all withdrawal requests, supporting pagination, status, and sorting filters.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Withdrawal Management'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['pending', 'completed', 'rejected'])),
            new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', enum: ['latest', 'oldest']))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Withdrawal list retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'withdrawals', type: 'array', items: new OA\Items(ref: '#/components/schemas/WithdrawalRequest')),
                        new OA\Property(property: 'meta', type: 'object')
                    ]
                )
            )
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = WithdrawalRequest::with('wallet.user');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $sort = $request->input('sort', 'latest');
        $direction = ($sort === 'oldest') ? 'asc' : 'desc';
        $query->orderBy('id', $direction);

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'withdrawals' => WithdrawalResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Get specific Withdrawal details.
     */
    #[OA\Get(
        path: '/admin/withdrawals/{id}',
        summary: 'Get Withdrawal Details',
        description: 'Retrieves details of a specific withdrawal request.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Withdrawal Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Withdrawal details retrieved.',
                content: new OA\JsonContent(ref: '#/components/schemas/WithdrawalRequest')
            ),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse')
        ]
    )]
    public function show($id): JsonResponse
    {
        $withdrawal = WithdrawalRequest::with('wallet.user')->findOrFail($id);

        return response()->json([
            'success' => true,
            'withdrawal' => new WithdrawalResource($withdrawal),
        ]);
    }

    /**
     * Approve Withdrawal.
     */
    #[OA\Post(
        path: '/admin/withdrawals/{id}/approve',
        summary: 'Approve Withdrawal',
        description: 'Approves a pending withdrawal request, completing the wallet debit action.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Withdrawal Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'admin_note', type: 'string', example: 'Transfer approved.')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Withdrawal approved successfully.'),
            new OA\Response(response: 422, description: 'Withdrawal request is not pending.')
        ]
    )]
    public function approve(Request $request, $id): JsonResponse
    {
        $withdrawal = WithdrawalRequest::findOrFail($id);
        $note = $request->input('admin_note');

        try {
            $oldValues = $withdrawal->toArray();
            $this->walletService->approveWithdrawal($withdrawal, $note);

            $this->auditService->log(
                $request->user(),
                'withdrawals',
                'withdrawal_approve',
                'withdrawal_requests',
                $withdrawal->id,
                $oldValues,
                $withdrawal->fresh()->toArray()
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal request approved and processed.',
        ]);
    }

    /**
     * Reject Withdrawal.
     */
    #[OA\Post(
        path: '/admin/withdrawals/{id}/reject',
        summary: 'Reject Withdrawal',
        description: 'Rejects a pending withdrawal request with a descriptive note.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Withdrawal Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'admin_note', type: 'string', example: 'Invalid bank account routing details.')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Withdrawal rejected successfully.'),
            new OA\Response(response: 422, description: 'Withdrawal request is not pending.')
        ]
    )]
    public function reject(Request $request, $id): JsonResponse
    {
        $withdrawal = WithdrawalRequest::findOrFail($id);
        $note = $request->input('admin_note');

        try {
            $oldValues = $withdrawal->toArray();
            $this->walletService->rejectWithdrawal($withdrawal, $note);

            $this->auditService->log(
                $request->user(),
                'withdrawals',
                'withdrawal_reject',
                'withdrawal_requests',
                $withdrawal->id,
                $oldValues,
                $withdrawal->fresh()->toArray()
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal request rejected.',
        ]);
    }
}
