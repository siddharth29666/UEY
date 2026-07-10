<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Ride;
use App\Models\RideReview;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ReviewController extends Controller
{
    public function __construct(
        protected ReviewService $reviewService
    ) {}

    /**
     * Submit a rating and review for a completed ride.
     */
    #[OA\Post(
        path: '/rides/{ride}/review',
        summary: 'Submit Ride Review',
        description: 'Submits a rating (1-5 stars) and optional review text for a completed ride. Only participants of the ride (rider or driver) can review it once.',
        security: [['bearerAuth' => []]],
        tags: ['Reviews'],
        parameters: [
            new OA\Parameter(
                name: 'ride',
                in: 'path',
                required: true,
                description: 'The ID of the ride.',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'rating', type: 'integer', minimum: 1, maximum: 5, example: 5),
                    new OA\Property(property: 'review', type: 'string', nullable: true, maxLength: 1000, example: 'Great driver! Very polite.'),
                    new OA\Property(property: 'review_tags', type: 'array', items: new OA\Items(type: 'string'), nullable: true, example: ['polite', 'clean_car']),
                    new OA\Property(property: 'is_anonymous', type: 'boolean', default: false, example: false),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Review submitted successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Review submitted successfully.'),
                        new OA\Property(property: 'review', ref: '#/components/schemas/Payment'), // Reuse schema patterns or separate
                        new OA\Property(
                            property: 'reviewee_stats',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'average_rating', type: 'number', format: 'float', example: 4.85),
                                new OA\Property(property: 'total_reviews', type: 'integer', example: 12),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed (e.g. invalid rating or duplicate submission).',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden. User is not a participant of this ride or user is soft deleted.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'You are not authorized to review this ride.'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function store(SubmitReviewRequest $request, Ride $ride): JsonResponse
    {
        try {
            $review = $this->reviewService->submitReview($ride, $request->user(), $request->validated());

            $reviewee = $review->reviewee;
            if ($reviewee->isDriver()) {
                $profile = $reviewee->driverProfile;
                $avg = $profile ? (float) $profile->rating : 5.00;
                $total = $profile ? (int) $profile->total_reviews : 0;
            } else {
                $avg = (float) $reviewee->rating;
                $total = (int) $reviewee->total_reviews;
            }

            return response()->json([
                'success' => true,
                'message' => 'Review submitted successfully.',
                'review' => new ReviewResource($review),
                'reviewee_stats' => [
                    'average_rating' => $avg,
                    'total_reviews' => $total,
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (AccessDeniedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * View both reviews for a specific ride.
     */
    #[OA\Get(
        path: '/rides/{ride}/review',
        summary: 'Get Ride Reviews',
        description: 'Retrieves reviews (rider-to-driver and driver-to-rider) associated with a ride. Restricted to the ride participants.',
        security: [['bearerAuth' => []]],
        tags: ['Reviews'],
        parameters: [
            new OA\Parameter(
                name: 'ride',
                in: 'path',
                required: true,
                description: 'The ID of the ride.',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Reviews retrieved successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'rider_review', ref: '#/components/schemas/RideReview', nullable: true),
                        new OA\Property(property: 'driver_review', ref: '#/components/schemas/RideReview', nullable: true),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Unauthorized access.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'You are not authorized to view reviews for this ride.'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function show(Request $request, Ride $ride): JsonResponse
    {
        try {
            $reviews = $this->reviewService->getRideReviews($ride, $request->user());

            return response()->json([
                'success' => true,
                'rider_review' => $reviews['riderReview'] ? new ReviewResource($reviews['riderReview']) : null,
                'driver_review' => $reviews['driverReview'] ? new ReviewResource($reviews['driverReview']) : null,
            ]);
        } catch (AccessDeniedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Get Driver public reviews history.
     */
    #[OA\Get(
        path: '/drivers/{driver}/reviews',
        summary: 'Get Driver Reviews',
        description: 'Retrieves public review history for a specific driver user.',
        security: [['bearerAuth' => []]],
        tags: ['Reviews'],
        parameters: [
            new OA\Parameter(
                name: 'driver',
                in: 'path',
                required: true,
                description: 'The User ID of the driver.',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['latest', 'highest_rating', 'lowest_rating'], default: 'latest')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Driver reviews retrieved successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'reviews', type: 'array', items: new OA\Items(ref: '#/components/schemas/RideReview')),
                        new OA\Property(
                            property: 'meta',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'per_page', type: 'integer', example: 15),
                                new OA\Property(property: 'total', type: 'integer', example: 25),
                                new OA\Property(property: 'last_page', type: 'integer', example: 2),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function driverReviews(Request $request, User $driver): JsonResponse
    {
        if (! $driver->isDriver()) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a driver.',
            ], 404);
        }

        $query = RideReview::where('reviewee_id', $driver->id)->with('reviewer');

        $this->applySorting($query, $request->query('sort', 'latest'));

        $perPage = (int) $request->query('per_page', 15);
        $paginator = $query->paginate($perPage);

        // Rating Summary
        $profile = $driver->driverProfile;
        $average_rating = $profile ? (float) $profile->rating : 5.00;
        $total_reviews = $profile ? (int) $profile->total_reviews : 0;

        $counts = RideReview::where('reviewee_id', $driver->id)
            ->selectRaw('rating, count(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating');

        $ratingSummary = [
            'average_rating' => $average_rating,
            'total_reviews' => $total_reviews,
            'five_star' => (int) $counts->get(5, 0),
            'four_star' => (int) $counts->get(4, 0),
            'three_star' => (int) $counts->get(3, 0),
            'two_star' => (int) $counts->get(2, 0),
            'one_star' => (int) $counts->get(1, 0),
        ];

        return response()->json([
            'success' => true,
            'reviews' => ReviewResource::collection($paginator->items()),
            'rating_summary' => $ratingSummary,
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
     * Get Rider reviews history.
     */
    #[OA\Get(
        path: '/riders/{rider}/reviews',
        summary: 'Get Rider Reviews',
        description: 'Retrieves review history for a specific rider user.',
        security: [['bearerAuth' => []]],
        tags: ['Reviews'],
        parameters: [
            new OA\Parameter(
                name: 'rider',
                in: 'path',
                required: true,
                description: 'The User ID of the rider.',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['latest', 'highest_rating', 'lowest_rating'], default: 'latest')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Rider reviews retrieved successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'reviews', type: 'array', items: new OA\Items(ref: '#/components/schemas/RideReview')),
                        new OA\Property(
                            property: 'meta',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'per_page', type: 'integer', example: 15),
                                new OA\Property(property: 'total', type: 'integer', example: 10),
                                new OA\Property(property: 'last_page', type: 'integer', example: 1),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function riderReviews(Request $request, User $rider): JsonResponse
    {
        if (! $rider->isRider()) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a rider.',
            ], 404);
        }

        $query = RideReview::where('reviewee_id', $rider->id)->with('reviewer');

        $this->applySorting($query, $request->query('sort', 'latest'));

        $perPage = (int) $request->query('per_page', 15);
        $paginator = $query->paginate($perPage);

        // Rating Summary
        $average_rating = (float) $rider->rating;
        $total_reviews = (int) $rider->total_reviews;

        $counts = RideReview::where('reviewee_id', $rider->id)
            ->selectRaw('rating, count(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating');

        $ratingSummary = [
            'average_rating' => $average_rating,
            'total_reviews' => $total_reviews,
            'five_star' => (int) $counts->get(5, 0),
            'four_star' => (int) $counts->get(4, 0),
            'three_star' => (int) $counts->get(3, 0),
            'two_star' => (int) $counts->get(2, 0),
            'one_star' => (int) $counts->get(1, 0),
        ];

        return response()->json([
            'success' => true,
            'reviews' => ReviewResource::collection($paginator->items()),
            'rating_summary' => $ratingSummary,
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
     * Apply sorting conditions to the query.
     */
    protected function applySorting($query, string $sort): void
    {
        match ($sort) {
            'highest_rating' => $query->orderBy('rating', 'desc')->orderBy('id', 'desc'),
            'lowest_rating' => $query->orderBy('rating', 'asc')->orderBy('id', 'desc'),
            default => $query->orderBy('id', 'desc'),
        };
    }
}
