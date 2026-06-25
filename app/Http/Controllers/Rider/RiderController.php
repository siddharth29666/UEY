<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Http\Requests\EstimateRideRequest;
use App\Http\Requests\RequestRideRequest;
use App\Http\Requests\CancelRideRequest;
use App\Http\Resources\RideResource;
use App\Models\Ride;
use App\Enums\RideStatus;
use App\Services\RideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class RiderController extends Controller
{
    /**
     * Get fare estimates for all active vehicle types.
     */
    #[OA\Post(
        path: '/rides/estimate',
        summary: 'Estimate Fare',
        description: 'Get fare estimates for all active vehicle types between pickup and destination.',
        security: [['bearerAuth' => []]],
        tags: ['Ride Booking'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/EstimateRideRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Fare estimates retrieved successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'estimates',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'vehicle_type_id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'Standard'),
                                    new OA\Property(property: 'capacity', type: 'integer', example: 4),
                                    new OA\Property(property: 'estimated_distance', type: 'number', format: 'float', example: 2.34),
                                    new OA\Property(property: 'estimated_duration', type: 'integer', example: 4),
                                    new OA\Property(property: 'estimated_fare', type: 'number', format: 'float', example: 8.50)
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse')
        ]
    )]
    public function estimateRide(EstimateRideRequest $request): JsonResponse
    {
        $service = app(RideService::class);
        $estimates = $service->estimateFares(
            (float) $request->input('pickup_latitude'),
            (float) $request->input('pickup_longitude'),
            (float) $request->input('destination_latitude'),
            (float) $request->input('destination_longitude')
        );
        return response()->json([
            'success' => true,
            'estimates' => $estimates,
        ]);
    }

    /**
     * Create a new ride request and find nearby drivers.
     */
    #[OA\Post(
        path: '/rides/request',
        summary: 'Request Ride',
        description: 'Create a new ride request and find nearby drivers.',
        security: [['bearerAuth' => []]],
        tags: ['Ride Booking'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RequestRideRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Ride requested successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Ride requested successfully.'),
                        new OA\Property(property: 'ride', type: 'object', ref: '#/components/schemas/Ride')
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse')
        ]
    )]
    public function requestRide(RequestRideRequest $request): JsonResponse
    {
        $service = app(RideService::class);
        $ride = $service->createRide($request->user(), $request->validated());
        return response()->json([
            'success' => true,
            'message' => 'Ride requested successfully.',
            'ride' => new RideResource($ride),
        ], 201);
    }

    /**
     * Cancel an active ride request. Allowed only before trip starts.
     */
    #[OA\Post(
        path: '/rides/{ride}/cancel',
        summary: 'Cancel Ride',
        description: 'Cancel an active ride request. Allowed only before trip starts (statuses: pending, accepted, arriving, arrived).',
        security: [['bearerAuth' => []]],
        tags: ['Ride Booking'],
        parameters: [
            new OA\Parameter(name: 'ride', in: 'path', required: true, description: 'ID of the Ride', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(ref: '#/components/schemas/CancelRideRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ride cancelled successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Ride cancelled successfully.'),
                        new OA\Property(property: 'ride', type: 'object', ref: '#/components/schemas/Ride')
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse')
        ]
    )]
    public function cancelRide(CancelRideRequest $request, Ride $ride): JsonResponse
    {
        $service = app(RideService::class);
        $cancelledRide = $service->cancelRide($ride, $request->user(), $request->input('cancel_reason'));
        return response()->json([
            'success' => true,
            'message' => 'Ride cancelled successfully.',
            'ride' => new RideResource($cancelledRide),
        ]);
    }

    /**
     * Get details of a specific ride by ID.
     */
    #[OA\Get(
        path: '/rides/{ride}',
        summary: 'Get Ride Details',
        description: 'Get details of a specific ride by ID.',
        security: [['bearerAuth' => []]],
        tags: ['Ride Booking'],
        parameters: [
            new OA\Parameter(name: 'ride', in: 'path', required: true, description: 'ID of the Ride', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ride details retrieved successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'ride', type: 'object', ref: '#/components/schemas/Ride')
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 404, description: 'Ride not found.')
        ]
    )]
    public function showRide(Ride $ride): JsonResponse
    {
        $ride->load(['rider', 'driverProfile.user']);
        return response()->json([
            'success' => true,
            'ride' => new RideResource($ride),
        ]);
    }

    /**
     * Retrieve a list of past and active rides requested by the authenticated rider.
     */
    #[OA\Get(
        path: '/rides',
        summary: 'Rider Ride History',
        description: 'Retrieve a list of past and active rides requested by the authenticated rider.',
        security: [['bearerAuth' => []]],
        tags: ['Ride Booking'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ride history retrieved successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'rides',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Ride')
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse')
        ]
    )]
    public function rideHistory(Request $request): JsonResponse
    {
        $rides = Ride::where('rider_id', $request->user()->id)
            ->with(['rider', 'driverProfile.user'])
            ->latest()
            ->get();
        return response()->json([
            'success' => true,
            'rides' => RideResource::collection($rides),
        ]);
    }

    /**
     * Get the current active (non-completed and non-cancelled) ride for the rider.
     */
    #[OA\Get(
        path: '/rides/active',
        summary: 'Get Active Ride',
        description: 'Get the current active (non-completed and non-cancelled) ride for the rider.',
        security: [['bearerAuth' => []]],
        tags: ['Ride Booking'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Active ride details.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'ride', type: 'object', ref: '#/components/schemas/Ride')
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(
                response: 404,
                description: 'No active ride found.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'No active ride found.')
                    ]
                )
            )
        ]
    )]
    public function activeRide(Request $request): JsonResponse
    {
        $ride = Ride::where('rider_id', $request->user()->id)
            ->whereNotIn('status', [RideStatus::COMPLETED, RideStatus::CANCELLED])
            ->with(['rider', 'driverProfile.user'])
            ->latest()
            ->first();

        if (!$ride) {
            return response()->json([
                'success' => false,
                'message' => 'No active ride found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'ride' => new RideResource($ride),
        ]);
    }

    // Existing stub methods preserved to prevent breaking other routes or stubs
    public function dashboard(Request $request) {}
    public function vehicleTypes(Request $request) {}
    public function fareEstimate(Request $request) {}
    public function scheduleRide(Request $request) {}
    public function currentRide(Request $request) {}
    public function driverDetails(Request $request, $ride) {}
    public function rideReceipt(Request $request, $ride) {}
    public function reviewDriver(Request $request, $ride) {}
    public function getWallet(Request $request) {}
    public function walletTopup(Request $request) {}
    public function paymentMethods(Request $request) {}
    public function addPaymentMethod(Request $request) {}
}
