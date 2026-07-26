<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class LogoutController extends Controller
{
    /**
     * Log out the authenticated user.
     */
    #[OA\Post(
        path: '/logout',
        summary: 'Logout User',
        description: 'Revokes the authenticated user\'s current Sanctum access token.',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logged out successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Logged out successfully.'),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/UnauthorizedResponse'
            ),
        ]
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. If a specific device_token/fcm_token/device_id is provided, remove that device record
        $token = $request->input('fcm_token') ?? $request->input('device_token') ?? $request->header('X-Device-Token');
        $deviceId = $request->input('device_id');

        if ($token || $deviceId) {
            \App\Models\UserDevice::where('user_id', $user->id)
                ->where(function ($query) use ($token, $deviceId) {
                    if ($token) {
                        $query->where('device_token', $token);
                    }
                    if ($deviceId) {
                        $query->orWhere('id', $deviceId);
                    }
                })
                ->delete();
        }

        // 2. Revoke current Sanctum access token
        $user->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }
}
