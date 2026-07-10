<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationFilterRequest;
use App\Http\Resources\NotificationResource;
use App\Models\NotificationLog;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Get paginated and filtered notification logs history.
     */
    #[OA\Get(
        path: '/notifications',
        summary: 'Get Notification History',
        description: 'Retrieves a paginated list of notifications for the authenticated user, supporting filters and sorting.',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['latest', 'oldest'], default: 'latest')),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'from_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Notification list retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'notifications', type: 'array', items: new OA\Items(ref: '#/components/schemas/NotificationLog')),
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
    public function index(NotificationFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $query = NotificationLog::where('user_id', $user->id);

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        $sort = $request->input('sort', 'latest');
        $direction = ($sort === 'oldest') ? 'asc' : 'desc';
        $query->orderBy('id', $direction);

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'notifications' => NotificationResource::collection($paginator->items()),
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
     * Get unread notifications count.
     */
    #[OA\Get(
        path: '/notifications/unread-count',
        summary: 'Get Unread Count',
        description: 'Retrieves count of unread notifications for the user.',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Unread count retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'unread_count', type: 'integer', example: 5),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->notificationService->unreadCount($request->user());

        return response()->json([
            'success' => true,
            'unread_count' => $count,
        ]);
    }

    /**
     * View details of a specific notification log.
     */
    #[OA\Get(
        path: '/notifications/{notification}',
        summary: 'Get Notification Details',
        description: 'Retrieves details of a specific notification log.',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(
                name: 'notification',
                in: 'path',
                required: true,
                description: 'The ID of the notification log.',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Notification details retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'notification', ref: '#/components/schemas/NotificationLog'),
                    ]
                )
            ),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function show(NotificationLog $notification, Request $request): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            throw new AccessDeniedHttpException('This notification does not belong to you.');
        }

        return response()->json([
            'success' => true,
            'notification' => new NotificationResource($notification),
        ]);
    }

    /**
     * Mark a single notification log as read.
     */
    #[OA\Post(
        path: '/notifications/{notification}/read',
        summary: 'Mark Notification Read',
        description: 'Marks a specific notification log as read.',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(
                name: 'notification',
                in: 'path',
                required: true,
                description: 'The ID of the notification log.',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Notification marked as read successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Notification marked as read.'),
                    ]
                )
            ),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function markAsRead(NotificationLog $notification, Request $request): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            throw new AccessDeniedHttpException('This notification does not belong to you.');
        }

        $this->notificationService->markAsRead($notification);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
        ]);
    }

    /**
     * Mark all notifications of a user as read.
     */
    #[OA\Post(
        path: '/notifications/read-all',
        summary: 'Mark All Notifications Read',
        description: 'Marks all notifications of the user as read.',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'All notifications marked as read.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'All notifications marked as read.'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function markAllAsRead(Request $request): JsonResponse
    {
        $this->notificationService->markAllAsRead($request->user());

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
        ]);
    }

    /**
     * Soft-delete a notification log.
     */
    #[OA\Delete(
        path: '/notifications/{notification}',
        summary: 'Delete Notification Log',
        description: 'Soft-deletes a notification log.',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(
                name: 'notification',
                in: 'path',
                required: true,
                description: 'The ID of the notification log.',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Notification soft-deleted successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Notification deleted successfully.'),
                    ]
                )
            ),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function destroy(NotificationLog $notification, Request $request): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            throw new AccessDeniedHttpException('This notification does not belong to you.');
        }

        $this->notificationService->delete($notification);

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully.',
        ]);
    }

    /**
     * Restore a soft-deleted notification log.
     */
    #[OA\Post(
        path: '/notifications/{id}/restore',
        summary: 'Restore Notification Log',
        description: 'Restores a soft-deleted notification log.',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'The ID of the soft-deleted notification log.',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Notification restored successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Notification restored successfully.'),
                        new OA\Property(property: 'notification', ref: '#/components/schemas/NotificationLog'),
                    ]
                )
            ),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function restore($id, Request $request): JsonResponse
    {
        $log = $this->notificationService->restore((int) $id, $request->user());

        if (! $log) {
            return response()->json([
                'success' => false,
                'message' => 'Notification log not found or not deleted.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification restored successfully.',
            'notification' => new NotificationResource($log),
        ]);
    }
}
