<?php

namespace App\Http\Controllers;

use App\Http\Resources\LedgerResource;
use App\Models\Ledger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class LedgerController extends Controller
{
    // =========================================================================
    // ADMIN ENDPOINTS
    // =========================================================================

    /**
     * Admin — list all ledger entries with filters.
     */
    #[OA\Get(
        path: '/admin/ledgers',
        summary: 'Admin — List Ledger Entries',
        description: 'Returns paginated ledger journal entries with optional filters. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Ledger (Admin)'],
        parameters: [
            new OA\Parameter(name: 'date_from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'wallet_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'user_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'transaction_type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'source', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'reference', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'direction', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['credit', 'debit'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ledger list retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/LedgerResource')),
                        new OA\Property(property: 'meta', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
        ]
    )]
    public function adminIndex(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Ledger::class);

        $query = Ledger::with(['user', 'wallet', 'walletTransaction'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('wallet_id')) {
            $query->where('wallet_id', $request->wallet_id);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }
        if ($request->filled('reference')) {
            $query->where('reference', 'like', '%'.$request->reference.'%');
        }
        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }

        $perPage = (int) $request->input('per_page', 20);
        $ledgers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => LedgerResource::collection($ledgers->items()),
            'meta' => [
                'total' => $ledgers->total(),
                'per_page' => $ledgers->perPage(),
                'current_page' => $ledgers->currentPage(),
                'last_page' => $ledgers->lastPage(),
            ],
        ]);
    }

    /**
     * Admin — show a single ledger entry with full detail.
     */
    #[OA\Get(
        path: '/admin/ledgers/{id}',
        summary: 'Admin — Get Ledger Entry Detail',
        description: 'Returns a single ledger entry including linked wallet, user, and wallet transaction data. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Ledger (Admin)'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ledger entry retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/LedgerResource'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
        ]
    )]
    public function adminShow(int $id): JsonResponse
    {
        Gate::authorize('viewAny', Ledger::class);

        $ledger = Ledger::with(['user', 'wallet', 'walletTransaction'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new LedgerResource($ledger),
        ]);
    }

    // =========================================================================
    // RIDER ENDPOINT
    // =========================================================================

    /**
     * Rider — view their own immutable ledger history.
     */
    #[OA\Get(
        path: '/wallet/ledger',
        summary: 'Rider — My Wallet Ledger History',
        description: 'Returns the authenticated rider\'s immutable wallet ledger entries. Read-only.',
        security: [['bearerAuth' => []]],
        tags: ['Wallet'],
        parameters: [
            new OA\Parameter(name: 'date_from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'direction', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['credit', 'debit'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ledger history retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/LedgerResource')),
                        new OA\Property(property: 'meta', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function riderLedger(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Ledger::with(['walletTransaction'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }

        $perPage = (int) $request->input('per_page', 20);
        $ledgers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => LedgerResource::collection($ledgers->items()),
            'meta' => [
                'total' => $ledgers->total(),
                'per_page' => $ledgers->perPage(),
                'current_page' => $ledgers->currentPage(),
                'last_page' => $ledgers->lastPage(),
            ],
        ]);
    }
}
