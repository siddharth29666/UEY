<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveSettingsRequest;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use App\Services\AuditLogService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class AdminSystemSettingsController extends Controller
{
    public function __construct(
        protected SettingService $settingService,
        protected AuditLogService $auditService
    ) {}

    /**
     * Get system settings.
     */
    #[OA\Get(
        path: '/admin/settings',
        summary: 'List System Settings',
        description: 'Retrieves all database-driven platform configurations.',
        security: [['bearerAuth' => []]],
        tags: ['Admin System Settings'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'System configurations list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'settings', type: 'array', items: new OA\Items(ref: '#/components/schemas/SettingResource')),
                    ]
                )
            ),
        ]
    )]
    public function index(): JsonResponse
    {
        $settings = Setting::all();

        return response()->json([
            'success' => true,
            'settings' => SettingResource::collection($settings),
        ]);
    }

    /**
     * Get a specific setting by key.
     */
    #[OA\Get(
        path: '/admin/settings/{key}',
        summary: 'Admin — Get Setting By Key',
        description: 'Retrieves a single system setting value by key.',
        security: [['bearerAuth' => []]],
        tags: ['Admin System Settings'],
        parameters: [
            new OA\Parameter(name: 'key', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Setting value retrieved.'),
            new OA\Response(response: 404, description: 'Setting key not found.'),
        ]
    )]
    public function show(string $key): JsonResponse
    {
        $setting = Setting::where('key', $key)->firstOrFail();

        return response()->json([
            'success' => true,
            'setting' => new SettingResource($setting),
        ]);
    }

    /**
     * Save/Update multiple settings.
     */
    #[OA\Put(
        path: '/admin/settings',
        summary: 'Update System Settings',
        description: 'Saves platform configuration values, clearing and updating the cache.',
        security: [['bearerAuth' => []]],
        tags: ['Admin System Settings'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SaveSettingsRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Settings updated successfully.'),
        ]
    )]
    public function update(SaveSettingsRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $oldSettings = $this->settingService->all();

        $this->settingService->save($payload);
        $newSettings = $this->settingService->all();

        // Audit Log
        $this->auditService->log(
            $request->user(),
            'settings',
            'settings_update',
            'settings',
            null,
            $oldSettings,
            $newSettings
        );

        return response()->json([
            'success' => true,
            'message' => 'System settings updated successfully.',
            'settings' => $newSettings,
        ]);
    }

    /**
     * Delete a setting key.
     */
    #[OA\Delete(
        path: '/admin/settings/{key}',
        summary: 'Admin — Delete Setting Key',
        description: 'Deletes a custom setting key and refreshes cache.',
        security: [['bearerAuth' => []]],
        tags: ['Admin System Settings'],
        parameters: [
            new OA\Parameter(name: 'key', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Setting key deleted successfully.'),
        ]
    )]
    public function destroy(string $key): JsonResponse
    {
        $setting = Setting::where('key', $key)->firstOrFail();
        $setting->delete();
        $this->settingService->refreshCache();

        return response()->json([
            'success' => true,
            'message' => "Setting '{$key}' deleted successfully.",
        ]);
    }

    /**
     * Refresh settings cache.
     */
    #[OA\Post(
        path: '/admin/settings/cache-refresh',
        summary: 'Refresh settings cache',
        description: 'Manually refreshes/invalidates system settings caching layer.',
        security: [['bearerAuth' => []]],
        tags: ['Admin System Settings'],
        responses: [
            new OA\Response(response: 200, description: 'Settings cache cleared.'),
        ]
    )]
    public function refreshCache(): JsonResponse
    {
        $this->settingService->refreshCache();

        return response()->json([
            'success' => true,
            'message' => 'Settings cache cleared successfully.',
        ]);
    }
}
