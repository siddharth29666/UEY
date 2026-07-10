<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\RideResource;
use App\Models\Ride;
use App\Models\RideStatusLog;
use App\Services\AdminService;
use App\Services\AuditLogService;
use App\Services\RideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AdminRideController extends Controller
{
    public function __construct(
        protected AdminService $adminService,
        protected RideService $rideService
    ) {}

    /**
     * Get paginated and filtered list of rides.
     */
    #[OA\Get(
        path: '/admin/rides',
        summary: 'List Rides',
        description: 'Retrieves a list of all rides, supporting search, sorting, pagination, and status filters.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Ride Management'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', enum: ['latest', 'oldest'])),
            new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ride list retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'rides', type: 'array', items: new OA\Items(ref: '#/components/schemas/RideResource')),
                        new OA\Property(property: 'meta', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Ride::with(['rider', 'driverProfile.user', 'payment', 'reviews']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('pickup_address', 'like', "%{$search}%")
                    ->orWhere('destination_address', 'like', "%{$search}%")
                    ->orWhereHas('rider', function ($qr) use ($search) {
                        $qr->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('driverProfile.user', function ($qd) use ($search) {
                        $qd->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $sort = $request->input('sort', 'latest');
        $direction = ($sort === 'oldest') ? 'asc' : 'desc';
        $query->orderBy('id', $direction);

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'rides' => RideResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Get specific Ride details.
     */
    #[OA\Get(
        path: '/admin/rides/{id}',
        summary: 'Get Ride Details',
        description: 'Retrieves a single ride\'s details including rider, driver, payment, and review records.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Ride Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ride details retrieved.',
                content: new OA\JsonContent(ref: '#/components/schemas/RideResource')
            ),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
        ]
    )]
    public function show($id): JsonResponse
    {
        $ride = Ride::with(['rider', 'driverProfile.user', 'payment', 'reviews'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'ride' => new RideResource($ride),
        ]);
    }

    /**
     * Cancel Ride.
     */
    #[OA\Post(
        path: '/admin/rides/{id}/cancel',
        summary: 'Cancel Ride',
        description: 'Cancels an active ride from administrative dashboard control.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Ride Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'cancel_reason', type: 'string', example: 'Rider booked by mistake.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Ride cancelled successfully.'),
        ]
    )]
    public function cancel(Request $request, $id): JsonResponse
    {
        $ride = Ride::findOrFail($id);
        $reason = $request->input('cancel_reason', 'Cancelled by administrator.');

        $this->rideService->cancelRide($ride, $request->user(), $reason);

        // Audit Log
        app(AuditLogService::class)->log(
            $request->user(),
            'rides',
            'ride_cancel',
            'rides',
            $ride->id,
            ['status' => $ride->status->value ?? $ride->status],
            ['status' => 'cancelled']
        );

        return response()->json([
            'success' => true,
            'message' => 'Ride cancelled successfully.',
        ]);
    }

    /**
     * Refund Ride.
     */
    #[OA\Post(
        path: '/admin/rides/{id}/refund',
        summary: 'Refund Ride Payment',
        description: 'Reverses the ride fare transaction, crediting rider wallet and debiting driver wallet.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Ride Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Payment refunded successfully.'),
        ]
    )]
    public function refund(Request $request, $id): JsonResponse
    {
        $ride = Ride::findOrFail($id);

        try {
            $this->adminService->refundRide($ride, $request->user());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ride payment refunded successfully.',
        ]);
    }

    /**
     * Get Ride Lifecycle Status Timeline.
     */
    #[OA\Get(
        path: '/admin/rides/{id}/timeline',
        summary: 'Get Ride Timeline',
        description: 'Retrieves history logs of all status transitions for this ride.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Ride Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ride timeline logs.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'timeline',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer'),
                                    new OA\Property(property: 'old_status', type: 'string'),
                                    new OA\Property(property: 'new_status', type: 'string'),
                                    new OA\Property(property: 'reason', type: 'string', nullable: true),
                                    new OA\Property(property: 'changed_by_id', type: 'integer', nullable: true),
                                    new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                ]
                            )
                        ),
                    ]
                )
            ),
        ]
    )]
    public function timeline($id): JsonResponse
    {
        $ride = Ride::findOrFail($id);
        $logs = RideStatusLog::where('ride_id', $ride->id)->orderBy('id', 'asc')->get();

        return response()->json([
            'success' => true,
            'timeline' => $logs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'old_status' => $log->old_status?->value ?? $log->old_status,
                    'new_status' => $log->new_status?->value ?? $log->new_status,
                    'reason' => $log->reason,
                    'changed_by_id' => $log->changed_by_id,
                    'created_at' => $log->created_at?->toIso8601String(),
                ];
            }),
        ]);
    }
}
