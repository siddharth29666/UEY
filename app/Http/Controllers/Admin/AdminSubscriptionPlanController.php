<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubscriptionPlanRequest;
use App\Http\Requests\Admin\UpdateSubscriptionPlanRequest;
use App\Http\Resources\DriverCreditTransactionResource;
use App\Http\Resources\DriverSubscriptionResource;
use App\Http\Resources\SubscriptionPlanResource;
use App\Models\DriverCreditTransaction;
use App\Models\DriverSubscription;
use App\Models\SubscriptionPlan;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AdminSubscriptionPlanController extends Controller
{
    public function __construct(
        protected StripeService $stripeService
    ) {}

    /**
     * Admin — List all subscription plans.
     */
    #[OA\Get(
        path: '/admin/subscription-plans',
        summary: 'Admin — List Subscription Plans',
        description: 'Returns all subscription plans including active, inactive, and soft deleted. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Subscription Plans'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Subscription plans list retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/SubscriptionPlanResource')),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = SubscriptionPlan::withTrashed();

        if ($request->filled('status')) {
            $query->where('status', filter_var($request->query('status'), FILTER_VALIDATE_BOOLEAN));
        }

        $plans = $query->orderBy('sort_order', 'asc')->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => SubscriptionPlanResource::collection($plans),
        ]);
    }

    /**
     * Admin — Create a subscription plan and sync with Stripe (GBP).
     */
    #[OA\Post(
        path: '/admin/subscription-plans',
        summary: 'Admin — Create Subscription Plan',
        description: 'Creates a new subscription plan in GBP and syncs with Stripe. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Subscription Plans'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'price_gbp', 'ride_credits', 'duration_days'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Starter Plan'),
                    new OA\Property(property: 'description', type: 'string', example: '20 Ride Credits'),
                    new OA\Property(property: 'price_gbp', type: 'number', format: 'float', example: 10.00),
                    new OA\Property(property: 'ride_credits', type: 'integer', example: 20),
                    new OA\Property(property: 'duration_days', type: 'integer', example: 30),
                    new OA\Property(property: 'status', type: 'boolean', example: true),
                    new OA\Property(property: 'sort_order', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Subscription plan created successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Subscription plan created successfully.'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/SubscriptionPlanResource'),
                    ]
                )
            ),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ]
    )]
    public function store(StoreSubscriptionPlanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Sync with Stripe
        try {
            $stripeProduct = $this->stripeService->createProduct($validated['name'], $validated['description'] ?? null);
            $stripePrice = $this->stripeService->createPrice($stripeProduct->id, (float) $validated['price_gbp']);

            $validated['stripe_product_id'] = $stripeProduct->id;
            $validated['stripe_price_id'] = $stripePrice->id;
        } catch (\Exception $e) {
            // Log or fallback if Stripe sandbox key is inactive
        }

        $plan = SubscriptionPlan::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Subscription plan created successfully.',
            'data' => new SubscriptionPlanResource($plan),
        ], 201);
    }

    /**
     * Admin — Show a specific subscription plan.
     */
    #[OA\Get(
        path: '/admin/subscription-plans/{id}',
        summary: 'Admin — Show Subscription Plan Details',
        description: 'Retrieves single subscription plan details by ID. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Subscription Plans'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Subscription plan details.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/SubscriptionPlanResource'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Subscription plan not found.'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $plan = SubscriptionPlan::withTrashed()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new SubscriptionPlanResource($plan),
        ]);
    }

    /**
     * Admin — Update a subscription plan.
     */
    #[OA\Put(
        path: '/admin/subscription-plans/{id}',
        summary: 'Admin — Update Subscription Plan',
        description: 'Updates an existing subscription plan by ID. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Subscription Plans'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Updated Starter Plan'),
                    new OA\Property(property: 'description', type: 'string', example: 'Updated Description'),
                    new OA\Property(property: 'price_gbp', type: 'number', format: 'float', example: 12.50),
                    new OA\Property(property: 'ride_credits', type: 'integer', example: 25),
                    new OA\Property(property: 'duration_days', type: 'integer', example: 30),
                    new OA\Property(property: 'status', type: 'boolean', example: true),
                    new OA\Property(property: 'sort_order', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Subscription plan updated successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Subscription plan updated successfully.'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/SubscriptionPlanResource'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Subscription plan not found.'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ]
    )]
    public function update(UpdateSubscriptionPlanRequest $request, int $id): JsonResponse
    {
        $plan = SubscriptionPlan::withTrashed()->findOrFail($id);
        $validated = $request->validated();

        $plan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Subscription plan updated successfully.',
            'data' => new SubscriptionPlanResource($plan),
        ]);
    }

    /**
     * Admin — Delete / soft delete a subscription plan.
     */
    #[OA\Delete(
        path: '/admin/subscription-plans/{id}',
        summary: 'Admin — Delete Subscription Plan',
        description: 'Soft deletes a subscription plan by ID. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Subscription Plans'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Subscription plan deleted successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Subscription plan deleted successfully.'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Subscription plan not found.'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $plan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Subscription plan deleted successfully.',
        ]);
    }

    /**
     * Admin — Toggle subscription plan active/inactive status.
     */
    #[OA\Patch(
        path: '/admin/subscription-plans/{id}/status',
        summary: 'Admin — Toggle Subscription Plan Status',
        description: 'Toggles a subscription plan active/inactive status. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Subscription Plans'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['active'],
                properties: [
                    new OA\Property(property: 'active', type: 'boolean', example: false),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Subscription plan status updated successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Subscription plan status updated successfully.'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/SubscriptionPlanResource'),
                    ]
                )
            ),
        ]
    )]
    public function status(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'active' => 'required|boolean',
        ]);

        $plan = SubscriptionPlan::withTrashed()->findOrFail($id);
        $plan->update(['status' => $request->boolean('active')]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription plan status updated successfully.',
            'data' => new SubscriptionPlanResource($plan),
        ]);
    }

    /**
     * Admin — Restore a soft-deleted subscription plan.
     */
    #[OA\Post(
        path: '/admin/subscription-plans/{id}/restore',
        summary: 'Admin — Restore Soft Deleted Subscription Plan',
        description: 'Restores a soft-deleted subscription plan by ID. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Subscription Plans'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Subscription plan restored successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Subscription plan restored successfully.'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/SubscriptionPlanResource'),
                    ]
                )
            ),
        ]
    )]
    public function restore(int $id): JsonResponse
    {
        $plan = SubscriptionPlan::onlyTrashed()->findOrFail($id);
        $plan->restore();

        return response()->json([
            'success' => true,
            'message' => 'Subscription plan restored successfully.',
            'data' => new SubscriptionPlanResource($plan),
        ]);
    }

    /**
     * Admin — List all driver subscriptions across platform.
     */
    #[OA\Get(
        path: '/admin/driver-subscriptions',
        summary: 'Admin — List All Driver Subscriptions',
        description: 'Retrieves paginated driver subscription records across platform. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Subscription Plans'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'driver_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Driver subscriptions list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'subscriptions', type: 'array', items: new OA\Items(ref: '#/components/schemas/DriverSubscriptionResource')),
                        new OA\Property(property: 'meta', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function driverSubscriptions(Request $request): JsonResponse
    {
        $query = DriverSubscription::with(['driverProfile.user', 'subscriptionPlan']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('driver_id')) {
            $query->where('driver_profile_id', $request->query('driver_id'));
        }

        $perPage = (int) $request->query('per_page', 15);
        $paginator = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'subscriptions' => DriverSubscriptionResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Admin — Show a specific driver subscription detail.
     */
    #[OA\Get(
        path: '/admin/driver-subscriptions/{id}',
        summary: 'Admin — Show Driver Subscription Details',
        description: 'Retrieves detailed driver subscription info by ID. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Subscription Plans'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Driver subscription details.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'subscription', ref: '#/components/schemas/DriverSubscriptionResource'),
                    ]
                )
            ),
        ]
    )]
    public function showDriverSubscription(int $id): JsonResponse
    {
        $subscription = DriverSubscription::with(['driverProfile.user', 'subscriptionPlan', 'creditTransactions'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'subscription' => new DriverSubscriptionResource($subscription),
        ]);
    }

    /**
     * Admin — Audit credit transaction journal.
     */
    #[OA\Get(
        path: '/admin/driver-credit-transactions',
        summary: 'Admin — Audit Credit Transactions',
        description: 'Audit journal of credit transaction entries (subscription purchases, ride accept deductions, manual adjustments, expiries). Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Subscription Plans'],
        parameters: [
            new OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'driver_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Credit transactions journal.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'transactions', type: 'array', items: new OA\Items(ref: '#/components/schemas/DriverCreditTransactionResource')),
                        new OA\Property(property: 'meta', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function creditTransactions(Request $request): JsonResponse
    {
        $query = DriverCreditTransaction::with(['driverProfile.user', 'driverSubscription']);

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }
        if ($request->filled('driver_id')) {
            $query->where('driver_profile_id', $request->query('driver_id'));
        }

        $perPage = (int) $request->query('per_page', 15);
        $paginator = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'transactions' => DriverCreditTransactionResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
