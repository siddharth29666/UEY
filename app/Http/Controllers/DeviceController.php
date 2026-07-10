<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Events\AdminAnnouncementEvent;
use App\Http\Requests\RegisterDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DeviceController extends Controller
{
    /**
     * Register or update FCM device token.
     */
    #[OA\Post(
        path: '/devices/register',
        summary: 'Register Device Token',
        description: 'Registers or updates a user\'s device token for FCM push notifications.',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RegisterDeviceRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Device token registered successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Device registered successfully.'),
                        new OA\Property(property: 'device', ref: '#/components/schemas/UserDevice'),
                    ]
                )
            ),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function register(RegisterDeviceRequest $request): JsonResponse
    {
        $user = $request->user();
        $token = $request->input('device_token');

        $exists = UserDevice::where('user_id', $user->id)
            ->where('device_token', $token)
            ->exists();

        $device = UserDevice::updateOrCreate(
            ['device_token' => $token],
            [
                'user_id' => $user->id,
                'device_type' => $request->input('device_type'),
                'device_name' => $request->input('device_name'),
                'platform' => $request->input('platform'),
                'os_version' => $request->input('os_version'),
                'app_version' => $request->input('app_version'),
                'language' => $request->input('language'),
                'timezone' => $request->input('timezone'),
                'last_used_at' => now(),
            ]
        );

        if (! $exists) {
            event(new AdminAnnouncementEvent($user, NotificationType::SYSTEM, 'Security Alert', __('notifications.auth.login_new_device')));
        }

        return response()->json([
            'success' => true,
            'message' => 'Device registered successfully.',
            'device' => new DeviceResource($device),
        ], 201);
    }

    /**
     * Update device details.
     */
    #[OA\Put(
        path: '/devices/{device}',
        summary: 'Update Device Token Details',
        description: 'Updates parameters of a previously registered device token.',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(
                name: 'device',
                in: 'path',
                required: true,
                description: 'The ID of the user device.',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateDeviceRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Device token details updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Device updated successfully.'),
                        new OA\Property(property: 'device', ref: '#/components/schemas/UserDevice'),
                    ]
                )
            ),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function update(UserDevice $device, UpdateDeviceRequest $request): JsonResponse
    {
        if ($device->user_id !== $request->user()->id) {
            throw new AccessDeniedHttpException('This device does not belong to you.');
        }

        $device->update(array_filter($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'Device updated successfully.',
            'device' => new DeviceResource($device),
        ]);
    }

    /**
     * Delete/deregister a device token.
     */
    #[OA\Delete(
        path: '/devices/{device}',
        summary: 'Delete Device Token',
        description: 'Deletes and deregisters a device token from receiving push notifications.',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(
                name: 'device',
                in: 'path',
                required: true,
                description: 'The ID of the user device.',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Device token deleted.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Device deleted successfully.'),
                    ]
                )
            ),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function destroy(UserDevice $device, Request $request): JsonResponse
    {
        if ($device->user_id !== $request->user()->id) {
            throw new AccessDeniedHttpException('This device does not belong to you.');
        }

        $device->delete();

        return response()->json([
            'success' => true,
            'message' => 'Device deleted successfully.',
        ]);
    }
}
