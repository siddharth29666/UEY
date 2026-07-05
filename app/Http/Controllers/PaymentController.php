<?php

namespace App\Http\Controllers;

use App\Http\Resources\PaymentResource;
use App\Models\Ride;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Get payment details for a ride.
     */
    #[OA\Get(
        path: '/payments/{ride}',
        summary: 'Get Ride Payment Details',
        description: 'Retrieves payment information for a specific ride. Access is restricted to the rider or driver of the ride.',
        security: [['bearerAuth' => []]],
        tags: ['Payments'],
        parameters: [
            new OA\Parameter(
                name: 'ride',
                in: 'path',
                required: true,
                description: 'The ID of the ride.',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Payment details retrieved successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'payment', ref: '#/components/schemas/Payment')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Unauthorized access.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthorized.')
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse')
        ]
    )]
    public function show(Request $request, Ride $ride): JsonResponse
    {
        $user = $request->user();
        $isRider = ($ride->rider_id === $user->id);
        $isDriver = ($ride->driverProfile && $ride->driver_profile_id === $user->driverProfile?->id);

        if (!$isRider && !$isDriver) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $payment = $ride->payment;
        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment record not found for this ride.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'payment' => new PaymentResource($payment),
        ]);
    }

    /**
     * Get payment history.
     */
    #[OA\Get(
        path: '/payments/history',
        summary: 'Get Payment History',
        description: 'Retrieves payment history logs. For Riders, returns rides they paid for. For Drivers, returns their earnings and commission logs.',
        security: [['bearerAuth' => []]],
        tags: ['Payments'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Payment history list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'payments',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Payment')
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse')
        ]
    )]
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = Payment::query();

        if ($user->isDriver()) {
            $driverProfile = $user->driverProfile;
            if (!$driverProfile) {
                return response()->json([
                    'success' => true,
                    'payments' => [],
                    'meta' => [
                        'current_page' => 1,
                        'per_page' => (int) $request->query('per_page', 15),
                        'total' => 0,
                        'last_page' => 1,
                    ]
                ]);
            }
            $query->where('driver_profile_id', $driverProfile->id);
        } else {
            $query->where('rider_id', $user->id);
        }

        // Apply filters
        if ($request->filled('status')) {
            $query->where('payment_status', $request->query('status'));
        }
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->query('payment_method'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        // Sort newest first
        $query->orderBy('id', 'desc');

        // Paginate
        $perPage = (int) $request->query('per_page', 15);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'payments' => PaymentResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ]
        ]);
    }

    /**
     * Get ride invoice JSON.
     */
    #[OA\Get(
        path: '/payments/invoice/{ride}',
        summary: 'Get Ride Invoice details',
        description: 'Generates a structured receipt/invoice breakdown for a completed ride.',
        security: [['bearerAuth' => []]],
        tags: ['Payments'],
        parameters: [
            new OA\Parameter(
                name: 'ride',
                in: 'path',
                required: true,
                description: 'The ID of the ride.',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Invoice details retrieved successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'invoice',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'ride_id', type: 'integer', example: 12),
                                new OA\Property(property: 'pickup_address', type: 'string', example: 'London Eye'),
                                new OA\Property(property: 'destination_address', type: 'string', example: 'Regents Park'),
                                new OA\Property(property: 'distance', type: 'number', format: 'float', example: 3.5),
                                new OA\Property(property: 'duration', type: 'integer', example: 10),
                                new OA\Property(property: 'payment_method', type: 'string', example: 'wallet'),
                                new OA\Property(property: 'payment_status', type: 'string', example: 'paid'),
                                new OA\Property(property: 'transaction_reference', type: 'string', example: 'PAY-20260704-000001'),
                                new OA\Property(property: 'completed_at', type: 'string', format: 'date-time'),
                                new OA\Property(
                                    property: 'rider',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'name', type: 'string', example: 'Alice Rider')
                                    ]
                                ),
                                new OA\Property(
                                    property: 'driver',
                                    type: 'object',
                                    nullable: true,
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 3),
                                        new OA\Property(property: 'name', type: 'string', example: 'Bob Driver')
                                    ]
                                ),
                                new OA\Property(
                                    property: 'fare_breakdown',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'subtotal', type: 'number', format: 'float', example: 15.00),
                                        new OA\Property(property: 'tax', type: 'number', format: 'float', example: 0.00),
                                        new OA\Property(property: 'discount', type: 'number', format: 'float', example: 0.00),
                                        new OA\Property(property: 'platform_commission', type: 'number', format: 'float', example: 2.25),
                                        new OA\Property(property: 'driver_earning', type: 'number', format: 'float', example: 12.75),
                                        new OA\Property(property: 'total', type: 'number', format: 'float', example: 15.00)
                                    ]
                                ),
                                new OA\Property(property: 'paid_at', type: 'string', format: 'date-time')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Unauthorized access.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthorized.')
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse')
        ]
    )]
    public function invoice(Request $request, Ride $ride): JsonResponse
    {
        $user = $request->user();
        $isRider = ($ride->rider_id === $user->id);
        $isDriver = ($ride->driverProfile && $ride->driver_profile_id === $user->driverProfile?->id);

        if (!$isRider && !$isDriver) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        try {
            $invoiceData = $this->paymentService->generateInvoice($ride->load(['rider', 'driverProfile.user', 'payment']));
            return response()->json([
                'success' => true,
                'invoice' => $invoiceData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
