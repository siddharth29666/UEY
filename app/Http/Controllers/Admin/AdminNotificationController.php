<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BroadcastNotificationRequest;
use App\Jobs\BroadcastNotificationJob;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class AdminNotificationController extends Controller
{
    public function __construct(
        protected AuditLogService $auditService
    ) {}

    /**
     * Broadcast notification to riders, drivers, or all users.
     */
    #[OA\Post(
        path: '/admin/notifications/broadcast',
        summary: 'Broadcast Announcement',
        description: 'Queues a system-wide push and database notification broadcast to selected targets.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Notification Broadcast'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/BroadcastNotificationRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Broadcast queued.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Broadcast queued successfully.')
                    ]
                )
            ),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse')
        ]
    )]
    public function broadcast(BroadcastNotificationRequest $request): JsonResponse
    {
        $target = $request->input('target');
        $userIds = $request->input('user_ids', []);
        $title = $request->input('title');
        $body = $request->input('body');
        $category = $request->input('category');
        $priority = $request->input('priority');

        // Dispatch background queued broadcast
        BroadcastNotificationJob::dispatch(
            $target,
            $userIds,
            $title,
            $body,
            $category,
            $priority
        );

        // Audit Log
        $this->auditService->log(
            $request->user(),
            'notifications',
            'notification_broadcast',
            null,
            null,
            null,
            [
                'target' => $target,
                'user_ids_count' => count($userIds),
                'title' => $title,
                'category' => $category,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Broadcast notification queued successfully.',
        ]);
    }
}
