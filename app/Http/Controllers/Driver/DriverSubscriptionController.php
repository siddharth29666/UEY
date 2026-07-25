<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseSubscriptionRequest;
use App\Http\Resources\DriverSubscriptionResource;
use App\Http\Resources\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use App\Services\DriverSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DriverSubscriptionController extends Controller
{
    public function __construct(
        protected DriverSubscriptionService $subscriptionService
    ) {}

    /**
     * Get list of active subscription plans available for drivers.
     */
    #[OA\Get(
        path: '/driver/subscription/plans',
        summary: 'Get Subscription Plans',
        description: 'Retrieves all active, non-deleted subscription plans in EUR currency.',
        security: [['bearerAuth' => []]],
        tags: ['Driver Subscription'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Subscription plans list retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'plans',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/SubscriptionPlanResource')
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function plans(): JsonResponse
    {
        $plans = $this->subscriptionService->getAvailablePlans();

        return response()->json([
            'success' => true,
            'plans' => SubscriptionPlanResource::collection($plans),
        ]);
    }

    /**
     * Get current active subscription for authenticated driver.
     */
    #[OA\Get(
        path: '/driver/subscription/current',
        summary: 'Get Current Driver Subscription',
        description: 'Retrieves the authenticated driver\'s current active subscription details.',
        security: [['bearerAuth' => []]],
        tags: ['Driver Subscription'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Current subscription details.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'subscription', ref: '#/components/schemas/DriverSubscriptionResource', nullable: true),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function current(Request $request): JsonResponse
    {
        $driverProfile = $request->user()->driverProfile;
        if (! $driverProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 404);
        }

        $subscription = $this->subscriptionService->getCurrentSubscription($driverProfile);

        return response()->json([
            'success' => true,
            'subscription' => $subscription ? new DriverSubscriptionResource($subscription) : null,
        ]);
    }

    /**
     * Get paginated subscription purchase history for driver.
     */
    #[OA\Get(
        path: '/driver/subscription/history',
        summary: 'Get Subscription Purchase History',
        description: 'Retrieves paginated history of subscription plan purchases for authenticated driver.',
        security: [['bearerAuth' => []]],
        tags: ['Driver Subscription'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Subscription history list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'subscriptions', type: 'array', items: new OA\Items(ref: '#/components/schemas/DriverSubscriptionResource')),
                        new OA\Property(property: 'meta', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function history(Request $request): JsonResponse
    {
        $driverProfile = $request->user()->driverProfile;
        if (! $driverProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 404);
        }

        $perPage = (int) $request->query('per_page', 15);
        $paginator = $this->subscriptionService->getSubscriptionHistory($driverProfile, $perPage);

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
     * Get driver credit balance overview.
     */
    #[OA\Get(
        path: '/driver/subscription/credits',
        summary: 'Get Driver Credit Balance Overview',
        description: 'Retrieves current available ride credits, allocated total, used count, and expiration date.',
        security: [['bearerAuth' => []]],
        tags: ['Driver Subscription'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Credit balance details.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function credits(Request $request): JsonResponse
    {
        $driverProfile = $request->user()->driverProfile;
        if (! $driverProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 404);
        }

        $creditData = $this->subscriptionService->getAvailableCredits($driverProfile);

        return response()->json([
            'success' => true,
            'data' => $creditData,
        ]);
    }

    /**
     * Purchase subscription plan using internal driver wallet balance (EUR).
     */
    #[OA\Post(
        path: '/driver/subscription/purchase',
        summary: 'Purchase Subscription Plan via Driver Wallet',
        description: 'Deducts subscription plan price in EUR from driver internal wallet balance and activates subscription immediately.',
        security: [['bearerAuth' => []]],
        tags: ['Driver Subscription'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['subscription_plan_id'],
                properties: [
                    new OA\Property(property: 'subscription_plan_id', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Subscription purchased successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Subscription purchased successfully.'),
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'subscription', ref: '#/components/schemas/DriverSubscriptionResource'),
                            new OA\Property(property: 'wallet', type: 'object', properties: [
                                new OA\Property(property: 'balance_before', type: 'number', format: 'float', example: 25.00),
                                new OA\Property(property: 'amount_deducted', type: 'number', format: 'float', example: 10.00),
                                new OA\Property(property: 'balance_after', type: 'number', format: 'float', example: 15.00),
                            ]),
                        ]),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Insufficient wallet balance or validation error.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Insufficient wallet balance. Please top up your wallet to purchase this subscription plan.'),
                    ]
                )
            ),
        ]
    )]
    public function purchase(PurchaseSubscriptionRequest $request): JsonResponse
    {
        $driverProfile = $request->user()->driverProfile;
        if (! $driverProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 404);
        }

        $plan = SubscriptionPlan::findOrFail($request->validated('subscription_plan_id'));

        try {
            $result = $this->subscriptionService->purchasePlan($driverProfile, $plan);

            return response()->json([
                'success' => true,
                'message' => 'Subscription purchased successfully.',
                'data' => [
                    'subscription' => new DriverSubscriptionResource($result['subscription']),
                    'wallet' => [
                        'balance_before' => (float) $result['wallet']['balance_before'],
                        'amount_deducted' => (float) $result['wallet']['amount_deducted'],
                        'balance_after' => (float) $result['wallet']['balance_after'],
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
