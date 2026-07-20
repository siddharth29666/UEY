<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Services\PromoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PromoController extends Controller
{
    public function __construct(
        protected PromoService $promoService
    ) {}

    /**
     * Validate a promo code for a ride request.
     */
    #[OA\Post(
        path: '/promos/validate',
        summary: 'Validate Promo Code',
        description: 'Validates a promo code against vehicle type and estimated fare constraints.',
        security: [['bearerAuth' => []]],
        tags: ['Promo Codes'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['promo_code', 'vehicle_type_id', 'estimated_fare'],
                properties: [
                    new OA\Property(property: 'promo_code', type: 'string', example: 'WELCOME50'),
                    new OA\Property(property: 'vehicle_type_id', type: 'integer', example: 1),
                    new OA\Property(property: 'estimated_fare', type: 'number', format: 'float', example: 350.00),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Promo code is valid.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'promo',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'code', type: 'string', example: 'WELCOME50'),
                                        new OA\Property(property: 'discount_type', type: 'string', example: 'percentage'),
                                        new OA\Property(property: 'discount_value', type: 'string', example: '50'),
                                    ]
                                ),
                                new OA\Property(property: 'discount_amount', type: 'string', example: '100.00'),
                                new OA\Property(property: 'final_fare', type: 'string', example: '250.00'),
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Invalid or unavailable promo code.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Promo code is invalid or unavailable.')
                    ]
                )
            ),
        ]
    )]
    public function validatePromo(Request $request): JsonResponse
    {
        $request->validate([
            'promo_code' => ['required', 'string'],
            'vehicle_type_id' => ['required', 'integer', 'exists:vehicle_types,id'],
            'estimated_fare' => ['required', 'numeric', 'min:0'],
        ]);

        $result = $this->promoService->validatePromo(
            $request->user(),
            $request->input('promo_code'),
            (int) $request->input('vehicle_type_id'),
            (float) $request->input('estimated_fare')
        );

        if (!$result->isValid) {
            return response()->json([
                'success' => false,
                'message' => 'Promo code is invalid or unavailable.',
            ], 422);
        }

        $promo = $result->promoCode;

        return response()->json([
            'success' => true,
            'data' => [
                'promo' => [
                    'id' => $promo->id,
                    'code' => $promo->code,
                    'discount_type' => $promo->discount_type,
                    'discount_value' => strval((float) $promo->discount_value),
                ],
                'discount_amount' => number_format($result->discountAmount, 2, '.', ''),
                'final_fare' => number_format($result->finalFare, 2, '.', ''),
            ],
        ]);
    }

    /**
     * Get available promo codes for authenticated rider.
     */
    #[OA\Get(
        path: '/promos',
        summary: 'List Available Promos',
        description: 'Retrieves all available promo codes for the authenticated rider.',
        security: [['bearerAuth' => []]],
        tags: ['Promo Codes'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Promo list retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PromoResource'))
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $rider = $request->user();
        $hasRides = \Illuminate\Support\Facades\DB::table('rides')
            ->where('rider_id', $rider->id)
            ->where('status', 'completed')
            ->exists();

        $promos = \App\Models\PromoCode::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->get();

        $filtered = $promos->filter(function ($promo) use ($rider) {
            // Check global usage limit
            if ($promo->usage_limit !== null) {
                $globalUsages = \Illuminate\Support\Facades\DB::table('promo_code_usages')
                    ->where('promo_code_id', $promo->id)
                    ->whereIn('status', ['reserved', 'completed'])
                    ->count();
                if ($globalUsages >= $promo->usage_limit) {
                    return false;
                }
            }

            // Check per-user limit
            if ($promo->per_user_limit !== null) {
                $userUsages = \Illuminate\Support\Facades\DB::table('promo_code_usages')
                    ->where('promo_code_id', $promo->id)
                    ->where('user_id', $rider->id)
                    ->whereIn('status', ['reserved', 'completed'])
                    ->count();
                if ($userUsages >= $promo->per_user_limit) {
                    return false;
                }
            }

            return true;
        });

        // Map eligible flag
        foreach ($filtered as $promo) {
            $promo->eligible = true;
            if ($promo->first_ride_only && $hasRides) {
                $promo->eligible = false;
            }
        }

        // Sort: eligible DESC, expires_at ASC, code ASC
        $sorted = $filtered->sortBy([
            ['eligible', 'desc'],
            ['expires_at', 'asc'],
            ['code', 'asc'],
        ]);

        return response()->json([
            'success' => true,
            'data' => \App\Http\Resources\PromoResource::collection($sorted->values()),
        ]);
    }

    /**
     * Get promo usage history for authenticated rider.
     */
    #[OA\Get(
        path: '/promos/history',
        summary: 'Rider Promo Usage History',
        description: 'Retrieves history of promo code redemptions with pagination.',
        security: [['bearerAuth' => []]],
        tags: ['Promo Codes'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Promo history retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PromoHistoryResource')),
                        new OA\Property(property: 'meta', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function history(Request $request): JsonResponse
    {
        $usages = \App\Models\PromoCodeUsage::with('promoCode')
            ->where('user_id', $request->user()->id)
            ->orderBy('id', 'desc')
            ->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => \App\Http\Resources\PromoHistoryResource::collection($usages->items()),
            'meta' => [
                'current_page' => $usages->currentPage(),
                'per_page' => $usages->perPage(),
                'total' => $usages->total(),
                'last_page' => $usages->lastPage(),
            ],
        ]);
    }
}
