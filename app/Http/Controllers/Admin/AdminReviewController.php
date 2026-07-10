<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\RideReview;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AdminReviewController extends Controller
{
    public function __construct(
        protected AuditLogService $auditService
    ) {}

    /**
     * Get paginated, filtered list of reviews.
     */
    #[OA\Get(
        path: '/admin/reviews',
        summary: 'List Reviews',
        description: 'Retrieves all reviews, supporting filters for rating, review type (rider/driver), and keyword search.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Review Moderation'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'rating', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(type: 'string', enum: ['rider', 'driver'])),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Reviews list retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'reviews', type: 'array', items: new OA\Items(ref: '#/components/schemas/ReviewResource')),
                        new OA\Property(property: 'meta', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = RideReview::with(['ride', 'reviewer', 'reviewee']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('review', 'like', "%{$search}%");
        }

        if ($request->filled('rating')) {
            $query->where('rating', $request->input('rating'));
        }

        if ($request->filled('type')) {
            $type = $request->input('type');
            $query->whereHas('reviewer', function ($q) use ($type) {
                $q->where('role', $type);
            });
        }

        $query->orderBy('id', 'desc');

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'reviews' => ReviewResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Get specific Review details.
     */
    #[OA\Get(
        path: '/admin/reviews/{id}',
        summary: 'Get Review Details',
        description: 'Retrieves details of a specific ride review.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Review Moderation'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Review details retrieved.',
                content: new OA\JsonContent(ref: '#/components/schemas/ReviewResource')
            ),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
        ]
    )]
    public function show($id): JsonResponse
    {
        $review = RideReview::with(['ride', 'reviewer', 'reviewee'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'review' => new ReviewResource($review),
        ]);
    }

    /**
     * Delete/moderate a review (Soft delete).
     */
    #[OA\Delete(
        path: '/admin/reviews/{id}',
        summary: 'Delete Review',
        description: 'Soft deletes a review from public visibility.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Review Moderation'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Review deleted successfully.'),
        ]
    )]
    public function destroy(Request $request, $id): JsonResponse
    {
        $review = RideReview::findOrFail($id);

        $admin = $request->user();
        $oldValues = $review->toArray();

        $review->delete();

        $this->auditService->log(
            $admin,
            'reviews',
            'review_delete',
            'ride_reviews',
            $review->id,
            $oldValues,
            null
        );

        return response()->json([
            'success' => true,
            'message' => 'Review moderated and soft-deleted successfully.',
        ]);
    }
}
