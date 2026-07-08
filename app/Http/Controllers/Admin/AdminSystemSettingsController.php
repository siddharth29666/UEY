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
                        new OA\Property(property: 'settings', type: 'array', items: new OA\Items(ref: '#/components/schemas/SettingResource'))
                    ]
                )
            )
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
            new OA\Response(response: 200, description: 'Settings updated successfully.')
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
     * Refresh settings cache.
     */
    #[OA\Post(
        path: '/admin/settings/cache-refresh',
        summary: 'Refresh settings cache',
        description: 'Manually refreshes/invalidates system settings caching layer.',
        security: [['bearerAuth' => []]],
        tags: ['Admin System Settings'],
        responses: [
            new OA\Response(response: 200, description: 'Settings cache cleared.')
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
