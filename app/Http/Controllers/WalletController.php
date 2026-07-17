<?php

namespace App\Http\Controllers;

use App\Http\Resources\WalletResource;
use App\Http\Resources\WalletTopupResource;
use App\Http\Resources\WalletTransactionResource;
use App\Http\Resources\WithdrawalResource;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\StripeService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Stripe\Exception\SignatureVerificationException;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService,
        protected StripeService $stripeService
    ) {}

    /**
     * Get wallet details & balance.
     */
    #[OA\Get(
        path: '/wallet',
        summary: 'Get Wallet Details',
        description: 'Retrieves current wallet balance, currency, and the last transaction details.',
        security: [['bearerAuth' => []]],
        tags: ['Wallet'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Wallet details retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'wallet', ref: '#/components/schemas/Wallet'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallet = $user->wallet()->firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0.00, 'currency' => 'USD', 'status' => 'active']
        );

        return response()->json([
            'success' => true,
            'wallet' => new WalletResource($wallet),
        ]);
    }

    /**
     * Get paginated wallet transactions ledger history.
     */
    #[OA\Get(
        path: '/wallet/transactions',
        summary: 'Get Wallet Transactions',
        description: 'Retrieves a paginated, sortable list of ledger transactions for the authenticated user.',
        security: [['bearerAuth' => []]],
        tags: ['Wallet'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['latest', 'oldest'], default: 'latest')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Transactions list retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'transactions', type: 'array', items: new OA\Items(ref: '#/components/schemas/WalletTransaction')),
                        new OA\Property(
                            property: 'meta',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'per_page', type: 'integer', example: 15),
                                new OA\Property(property: 'total', type: 'integer', example: 5),
                                new OA\Property(property: 'last_page', type: 'integer', example: 1),
                            ]
                        ),
                        new OA\Property(
                            property: 'links',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'first', type: 'string'),
                                new OA\Property(property: 'last', type: 'string'),
                                new OA\Property(property: 'prev', type: 'string', nullable: true),
                                new OA\Property(property: 'next', type: 'string', nullable: true),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallet = $user->wallet()->firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0.00, 'currency' => 'USD', 'status' => 'active']
        );

        $query = WalletTransaction::where('wallet_id', $wallet->id);

        $sort = $request->query('sort', 'latest');
        $direction = ($sort === 'oldest') ? 'asc' : 'desc';
        $query->orderBy('id', $direction);

        $perPage = (int) $request->query('per_page', 15);
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
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Request Stripe top-up payment intent creation.
     */
    #[OA\Post(
        path: '/wallet/top-up',
        summary: 'Create Top-up PaymentIntent',
        description: 'Creates a Stripe PaymentIntent for topping up the user\'s wallet balance.',
        security: [['bearerAuth' => []]],
        tags: ['Wallet'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'amount', type: 'number', format: 'float', minimum: 5.00, maximum: 5000.00, example: 50.00),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'PaymentIntent client secret generated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'client_secret', type: 'string', example: 'pi_3MtwJD2eZvKYlo2C0DGk4_secret_A1b2C3d4e5f6g7h8i9j0k1l2'),
                        new OA\Property(property: 'payment_intent', type: 'string', example: 'pi_3MtwJD2eZvKYlo2C0DGk4'),
                        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 50.00),
                        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
                        new OA\Property(property: 'stripe_publishable_key', type: 'string', example: 'pk_test_12345'),
                        new OA\Property(property: 'wallet_topup', ref: '#/components/schemas/WalletTopup'),
                    ]
                )
             ),
             new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
             new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
         ]
     )]
     public function topUp(Request $request): JsonResponse
     {
         $request->validate([
             'amount' => ['required', 'numeric', 'min:5.00', 'max:5000.00'],
         ]);
 
         $user = $request->user();
         $wallet = $user->wallet()->firstOrCreate(
             ['user_id' => $user->id],
             ['balance' => 0.00, 'currency' => 'USD', 'status' => 'active']
         );
 
         try {
             $amount = (float) $request->input('amount');
             $result = $this->walletService->createTopup($wallet, $amount);
             $topup = $result->walletTopup;
 
             return response()->json([
                 'success' => true,
                 'client_secret' => $result->clientSecret,
                 'payment_intent' => $topup->stripe_payment_intent,
                 'amount' => $amount,
                 'currency' => $wallet->currency,
                 'stripe_publishable_key' => config('services.stripe.key'),
                 'wallet_topup' => new WalletTopupResource($topup),
             ]);
         } catch (\Exception $e) {
             return response()->json([
                 'success' => false,
                 'message' => $e->getMessage(),
             ], 422);
         }
     }

    /**
     * File a withdrawal request.
     */
    #[OA\Post(
        path: '/wallet/withdraw',
        summary: 'Request Wallet Withdrawal',
        description: 'Requests withdrawal of wallet balance earnings.',
        security: [['bearerAuth' => []]],
        tags: ['Wallet'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'amount', type: 'number', format: 'float', minimum: 10.00, example: 100.00),
                    new OA\Property(property: 'bank_account_id', type: 'integer', nullable: true, example: 2),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Withdrawal request filed successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Withdrawal request submitted successfully.'),
                        new OA\Property(property: 'withdrawal', ref: '#/components/schemas/WithdrawalRequest'),
                    ]
                )
            ),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function withdraw(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:10.00'],
            'bank_account_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $wallet = $user->wallet()->firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0.00, 'currency' => 'USD', 'status' => 'active']
        );

        try {
            $amount = (float) $request->input('amount');
            $withdrawal = $this->walletService->requestWithdrawal($wallet, $amount, $request->input('bank_account_id'));

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request submitted successfully.',
                'withdrawal' => new WithdrawalResource($withdrawal),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get paginated withdrawals history.
     */
    #[OA\Get(
        path: '/wallet/withdrawals',
        summary: 'Get Withdrawals History',
        description: 'Retrieves a paginated list of withdrawal requests filed by the authenticated user.',
        security: [['bearerAuth' => []]],
        tags: ['Wallet'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['latest', 'oldest'], default: 'latest')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Withdrawals history list retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'withdrawals', type: 'array', items: new OA\Items(ref: '#/components/schemas/WithdrawalRequest')),
                        new OA\Property(
                            property: 'meta',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'per_page', type: 'integer', example: 15),
                                new OA\Property(property: 'total', type: 'integer', example: 2),
                                new OA\Property(property: 'last_page', type: 'integer', example: 1),
                            ]
                        ),
                        new OA\Property(
                            property: 'links',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'first', type: 'string'),
                                new OA\Property(property: 'last', type: 'string'),
                                new OA\Property(property: 'prev', type: 'string', nullable: true),
                                new OA\Property(property: 'next', type: 'string', nullable: true),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function withdrawals(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallet = $user->wallet()->firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0.00, 'currency' => 'USD', 'status' => 'active']
        );

        $query = WithdrawalRequest::where('wallet_id', $wallet->id);

        $sort = $request->query('sort', 'latest');
        $direction = ($sort === 'oldest') ? 'asc' : 'desc';
        $query->orderBy('id', $direction);

        $perPage = (int) $request->query('per_page', 15);
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
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    /**
     * View details of a specific withdrawal request.
     */
    #[OA\Get(
        path: '/wallet/withdrawals/{id}',
        summary: 'Get Withdrawal Details',
        description: 'Retrieves details of a specific withdrawal request.',
        security: [['bearerAuth' => []]],
        tags: ['Wallet'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'The ID of the withdrawal request.',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Withdrawal details retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'withdrawal', ref: '#/components/schemas/WithdrawalRequest'),
                    ]
                )
            ),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function showWithdrawal(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $wallet = $user->wallet()->firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0.00, 'currency' => 'USD', 'status' => 'active']
        );

        $withdrawal = WithdrawalRequest::where('wallet_id', $wallet->id)->find($id);

        if (! $withdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Withdrawal request not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'withdrawal' => new WithdrawalResource($withdrawal),
        ]);
    }

    /**
     * Handle Stripe payment webhook callback.
     */
    #[OA\Post(
        path: '/stripe/webhook',
        summary: 'Stripe webhook listener',
        description: 'Idempotently consumes PaymentIntent event callbacks to settle user top-up wallets.',
        tags: ['Wallet'],
        responses: [
            new OA\Response(response: 200, description: 'Webhook consumed successfully.'),
            new OA\Response(response: 400, description: 'Signature verification failed.'),
        ]
    )]
    public function stripeWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');

        try {
            // Bypass Stripe verify signature in testing mode if no signature header is provided
            if (app()->environment('testing') && empty($sigHeader)) {
                $eventArray = json_decode($payload, true);
            } else {
                $event = $this->stripeService->verifyWebhook($payload, $sigHeader);
                $eventArray = json_decode($payload, true);
            }

            $this->walletService->processWebhookEvent($eventArray);

            return response()->json(['success' => true]);
        } catch (SignatureVerificationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid signature.',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
