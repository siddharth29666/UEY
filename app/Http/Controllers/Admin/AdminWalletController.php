<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreditWalletRequest;
use App\Http\Requests\Admin\DebitWalletRequest;
use App\Http\Resources\WalletResource;
use App\Http\Resources\WalletTransactionResource;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AdminWalletController extends Controller
{
    public function __construct(
        protected AdminService $adminService
    ) {}

    /**
     * Get paginated and filtered list of user wallets.
     */
    #[OA\Get(
        path: '/admin/wallets',
        summary: 'List Wallets',
        description: 'Retrieves a list of all user wallets, supporting sorting, searching, and pagination.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Wallet Administration'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Wallet list retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'wallets', type: 'array', items: new OA\Items(ref: '#/components/schemas/WalletResource')),
                        new OA\Property(property: 'meta', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Wallet::with('user');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('user', function ($qu) use ($search) {
                $qu->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'wallets' => WalletResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Get specific Wallet details.
     */
    #[OA\Get(
        path: '/admin/wallets/{id}',
        summary: 'Get Wallet Details',
        description: 'Retrieves details of a user\'s wallet by its ID.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Wallet Administration'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Wallet details retrieved.',
                content: new OA\JsonContent(ref: '#/components/schemas/WalletResource')
            ),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
        ]
    )]
    public function show($id): JsonResponse
    {
        $wallet = Wallet::with('user')->findOrFail($id);

        return response()->json([
            'success' => true,
            'wallet' => new WalletResource($wallet),
        ]);
    }

    /**
     * Manually Credit Wallet.
     */
    #[OA\Post(
        path: '/admin/wallets/{id}/credit',
        summary: 'Credit Wallet',
        description: 'Credits funds manually to a user\'s wallet with auditing.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Wallet Administration'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreditWalletRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Wallet credited successfully.'),
        ]
    )]
    public function credit(CreditWalletRequest $request, $id): JsonResponse
    {
        $wallet = Wallet::findOrFail($id);
        $amount = (float) $request->input('amount');
        $reason = $request->input('reason');

        $this->adminService->creditWallet($wallet, $amount, $reason, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Wallet credited successfully.',
            'balance' => (float) $wallet->fresh()->balance,
        ]);
    }

    /**
     * Manually Debit Wallet.
     */
    #[OA\Post(
        path: '/admin/wallets/{id}/debit',
        summary: 'Debit Wallet',
        description: 'Debits funds manually from a user\'s wallet with auditing. Validates balance.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Wallet Administration'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/DebitWalletRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Wallet debited successfully.'),
            new OA\Response(response: 422, description: 'Insufficient funds.'),
        ]
    )]
    public function debit(DebitWalletRequest $request, $id): JsonResponse
    {
        $wallet = Wallet::findOrFail($id);
        $amount = (float) $request->input('amount');
        $reason = $request->input('reason');

        if ($wallet->balance < $amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient funds to perform debit operation.',
            ], 422);
        }

        $this->adminService->debitWallet($wallet, $amount, $reason, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Wallet debited successfully.',
            'balance' => (float) $wallet->fresh()->balance,
        ]);
    }

    /**
     * Get paginated and filtered Wallet Transactions (Ledger history).
     */
    #[OA\Get(
        path: '/admin/wallet-transactions',
        summary: 'List Wallet Transactions',
        description: 'Retrieves ledger logs of all wallet balance adjustments across the platform.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Wallet Administration'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Transactions list retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'transactions', type: 'array', items: new OA\Items(ref: '#/components/schemas/WalletTransactionResource')),
                        new OA\Property(property: 'meta', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function transactions(Request $request): JsonResponse
    {
        $query = WalletTransaction::with('wallet.user');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('wallet.user', function ($qu) use ($search) {
                        $qu->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $query->orderBy('id', 'desc');

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'transactions' => WalletTransactionResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
