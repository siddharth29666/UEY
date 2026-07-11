<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResolveSOSRequest;
use App\Http\Requests\StoreEmergencyContactRequest;
use App\Http\Requests\TriggerSOSRequest;
use App\Http\Requests\UpdateEmergencyContactRequest;
use App\Http\Resources\EmergencyAlertResource;
use App\Http\Resources\EmergencyContactResource;
use App\Models\EmergencyAlert;
use App\Models\EmergencyContact;
use App\Models\Ride;
use App\Services\EmergencyService;
use App\Services\SettingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class EmergencyController extends Controller
{
    public function __construct(
        protected EmergencyService $service,
        protected SettingService $settingService
    ) {}

    // =========================================================================
    // EMERGENCY CONTACTS
    // =========================================================================

    /**
     * List emergency contacts.
     */
    #[OA\Get(
        path: '/emergency-contacts',
        summary: 'List Emergency Contacts',
        description: 'Retrieves all emergency contacts saved by the rider.',
        security: [['bearerAuth' => []]],
        tags: ['Emergency Contacts'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List retrieved successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/EmergencyContactResource')),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
        ]
    )]
    public function contacts(Request $request): JsonResponse
    {
        $contacts = EmergencyContact::where('user_id', $request->user()->id)->get();

        return response()->json([
            'success' => true,
            'data' => EmergencyContactResource::collection($contacts),
        ]);
    }

    /**
     * Get emergency contacts ordered by priority.
     */
    #[OA\Get(
        path: '/emergency-contacts/default',
        summary: 'Get Default Emergency Contacts',
        description: 'Retrieves emergency contacts ordered by priority ascending.',
        security: [['bearerAuth' => []]],
        tags: ['Emergency Contacts'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Default emergency contacts retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'contacts', type: 'array', items: new OA\Items(ref: '#/components/schemas/EmergencyContactResource')),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function defaultContacts(Request $request): JsonResponse
    {
        $contacts = EmergencyContact::where('user_id', $request->user()->id)
            ->orderBy('priority', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'contacts' => EmergencyContactResource::collection($contacts),
        ]);
    }

    /**
     * Store emergency contact.
     */
    #[OA\Post(
        path: '/emergency-contacts',
        summary: 'Create Emergency Contact',
        description: 'Saves a new emergency contact. Respects max contacts limits.',
        security: [['bearerAuth' => []]],
        tags: ['Emergency Contacts'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreEmergencyContactRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Emergency contact created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/EmergencyContactResource'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ]
    )]
    public function storeContact(StoreEmergencyContactRequest $request): JsonResponse
    {
        // Check maximum contact limit
        $limit = (int) $this->settingService->get('maximum_emergency_contacts', 5);
        $count = EmergencyContact::where('user_id', $request->user()->id)->count();

        if ($count >= $limit) {
            throw ValidationException::withMessages([
                'phone' => ["You cannot exceed the limit of {$limit} emergency contacts."],
            ]);
        }

        $contact = EmergencyContact::create(array_merge(
            $request->validated(),
            ['user_id' => $request->user()->id]
        ));

        return response()->json([
            'success' => true,
            'data' => new EmergencyContactResource($contact),
        ], 201);
    }

    /**
     * Update emergency contact.
     */
    #[OA\Put(
        path: '/emergency-contacts/{id}',
        summary: 'Update Emergency Contact',
        description: 'Updates details of an existing emergency contact.',
        security: [['bearerAuth' => []]],
        tags: ['Emergency Contacts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateEmergencyContactRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Emergency contact updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/EmergencyContactResource'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ]
    )]
    public function updateContact(UpdateEmergencyContactRequest $request, $id): JsonResponse
    {
        $contact = EmergencyContact::findOrFail($id);
        Gate::authorize('update', $contact);

        $contact->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => new EmergencyContactResource($contact->fresh()),
        ]);
    }

    /**
     * Delete emergency contact.
     */
    #[OA\Delete(
        path: '/emergency-contacts/{id}',
        summary: 'Delete Emergency Contact',
        description: 'Deletes a saved emergency contact.',
        security: [['bearerAuth' => []]],
        tags: ['Emergency Contacts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Emergency contact deleted.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Emergency contact deleted successfully.'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
        ]
    )]
    public function destroyContact($id): JsonResponse
    {
        $contact = EmergencyContact::findOrFail($id);
        Gate::authorize('delete', $contact);

        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Emergency contact deleted successfully.',
        ]);
    }

    // =========================================================================
    // SOS OPERATIONS
    // =========================================================================

    /**
     * Trigger SOS alert.
     */
    #[OA\Post(
        path: '/rides/{ride}/sos',
        summary: 'Trigger SOS Alert',
        description: 'Triggers a new emergency SOS alarm during an active ride.',
        security: [['bearerAuth' => []]],
        tags: ['SOS & Emergency Alarms'],
        parameters: [
            new OA\Parameter(name: 'ride', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/TriggerSOSRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'SOS Alert triggered.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/EmergencyAlertResource'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
            new OA\Response(response: 409, description: 'Conflict error when an active SOS alert already exists.'),
        ]
    )]
    public function trigger(TriggerSOSRequest $request, $rideId): JsonResponse
    {
        $ride = Ride::findOrFail($rideId);

        // Enforce ride ownership: Rider must be the owner of the ride
        if ((int) $request->user()->id !== (int) $ride->rider_id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not own this ride.',
            ], 403);
        }

        // Extract file uploads
        $files = [];
        if ($request->hasFile('photo')) {
            $files['photo'] = $request->file('photo');
        }
        if ($request->hasFile('audio')) {
            $files['audio'] = $request->file('audio');
        }
        if ($request->hasFile('video')) {
            $files['video'] = $request->file('video');
        }

        $alert = $this->service->triggerSOS(
            $ride,
            $request->user(),
            (float) $request->input('latitude'),
            (float) $request->input('longitude'),
            $request->input('message'),
            $files
        );

        return response()->json([
            'success' => true,
            'data' => new EmergencyAlertResource($alert->load('histories.creator')),
        ], 201);
    }

    /**
     * Acknowledge SOS Alert.
     */
    #[OA\Post(
        path: '/emergency-alerts/{id}/acknowledge',
        summary: 'Acknowledge SOS Alert',
        description: 'Allows the assigned driver to acknowledge the triggered emergency SOS alert.',
        security: [['bearerAuth' => []]],
        tags: ['SOS & Emergency Alarms'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'SOS Alert acknowledged.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'SOS Alert acknowledged successfully.'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
        ]
    )]
    public function acknowledge(Request $request, $id): JsonResponse
    {
        $alert = EmergencyAlert::findOrFail($id);
        Gate::authorize('acknowledge', $alert);

        $this->service->acknowledgeSOS($alert, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'SOS Alert acknowledged successfully.',
        ]);
    }

    /**
     * Resolve SOS Alert (Rider or Admin).
     */
    #[OA\Post(
        path: '/emergency-alerts/{id}/resolve',
        summary: 'Resolve SOS Alert',
        description: 'Resolves the emergency alert. Can be resolved by the triggering rider or an administrator.',
        security: [['bearerAuth' => []]],
        tags: ['SOS & Emergency Alarms'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(ref: '#/components/schemas/ResolveSOSRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'SOS Alert resolved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'SOS Alert resolved successfully.'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
        ]
    )]
    public function resolve(ResolveSOSRequest $request, $id): JsonResponse
    {
        $alert = EmergencyAlert::findOrFail($id);
        Gate::authorize('resolve', $alert);

        $this->service->resolveSOS($alert, $request->user(), $request->input('admin_note'));

        return response()->json([
            'success' => true,
            'message' => 'SOS Alert resolved successfully.',
        ]);
    }

    /**
     * List emergency alerts for Rider / Driver.
     */
    #[OA\Get(
        path: '/emergency-alerts',
        summary: 'List SOS Alerts',
        description: 'Retrieves all emergency alerts related to the rider or driver.',
        security: [['bearerAuth' => []]],
        tags: ['SOS & Emergency Alarms'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/EmergencyAlertResource')),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        ]
    )]
    public function indexAlerts(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role->value === 'rider') {
            $alerts = EmergencyAlert::where('user_id', $user->id)
                ->with('histories.creator')
                ->get();
        } elseif ($user->role->value === 'driver') {
            $alerts = EmergencyAlert::where('driver_id', $user->id)
                ->with('histories.creator')
                ->get();
        } else {
            // Admin
            $alerts = EmergencyAlert::with('histories.creator')->get();
        }

        return response()->json([
            'success' => true,
            'data' => EmergencyAlertResource::collection($alerts),
        ]);
    }

    /**
     * Show details of a specific SOS alert.
     */
    #[OA\Get(
        path: '/emergency-alerts/{id}',
        summary: 'Get SOS Alert Details',
        description: 'Retrieves single SOS alert details including history logs.',
        security: [['bearerAuth' => []]],
        tags: ['SOS & Emergency Alarms'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Details retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/EmergencyAlertResource'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
        ]
    )]
    public function showAlert($id): JsonResponse
    {
        $alert = EmergencyAlert::with('histories.creator')->findOrFail($id);
        Gate::authorize('view', $alert);

        return response()->json([
            'success' => true,
            'data' => new EmergencyAlertResource($alert),
        ]);
    }

    // =========================================================================
    // ADMIN ONLY SOS APIS
    // =========================================================================

    /**
     * Admin List Emergency Alerts.
     */
    #[OA\Get(
        path: '/admin/emergency-alerts',
        summary: 'Admin List SOS Alerts',
        description: 'Retrieves all SOS alerts on the platform (Admin only).',
        security: [['bearerAuth' => []]],
        tags: ['Admin SOS Moderation'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'SOS Alert list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/EmergencyAlertResource')),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
        ]
    )]
    public function adminIndex(Request $request): JsonResponse
    {
        if ($request->user()->role->value !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $alerts = EmergencyAlert::with('histories.creator')->latest()->get();

        return response()->json([
            'success' => true,
            'data' => EmergencyAlertResource::collection($alerts),
        ]);
    }

    /**
     * Admin Show Emergency Alert.
     */
    #[OA\Get(
        path: '/admin/emergency-alerts/{id}',
        summary: 'Admin Get SOS Alert Details',
        description: 'Retrieves complete single SOS alert details including history logs (Admin only).',
        security: [['bearerAuth' => []]],
        tags: ['Admin SOS Moderation'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Details retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/EmergencyAlertResource'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
        ]
    )]
    public function adminShow(Request $request, $id): JsonResponse
    {
        if ($request->user()->role->value !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $alert = EmergencyAlert::with('histories.creator')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new EmergencyAlertResource($alert),
        ]);
    }

    /**
     * Admin Resolve Emergency Alert.
     */
    #[OA\Post(
        path: '/admin/emergency-alerts/{id}/resolve',
        summary: 'Admin Resolve SOS Alert',
        description: 'Resolves an active SOS alert with administrative notes.',
        security: [['bearerAuth' => []]],
        tags: ['Admin SOS Moderation'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(ref: '#/components/schemas/ResolveSOSRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'SOS Alert resolved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'SOS Alert resolved successfully.'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
        ]
    )]
    public function adminResolve(ResolveSOSRequest $request, $id): JsonResponse
    {
        if ($request->user()->role->value !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $alert = EmergencyAlert::findOrFail($id);
        $this->service->resolveSOS($alert, $request->user(), $request->input('admin_note'));

        return response()->json([
            'success' => true,
            'message' => 'SOS Alert resolved successfully by administrator.',
        ]);
    }

    /**
     * Admin Assign Emergency Alert.
     */
    #[OA\Post(
        path: '/admin/emergency-alerts/{id}/assign',
        summary: 'Admin Assign SOS Alert',
        description: 'Assigns an administrator to manage the emergency alert.',
        security: [['bearerAuth' => []]],
        tags: ['Admin SOS Moderation'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'SOS Alert assigned.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'SOS Alert assigned successfully.'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
        ]
    )]
    public function adminAssign(Request $request, $id): JsonResponse
    {
        if ($request->user()->role->value !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $alert = EmergencyAlert::findOrFail($id);
        Gate::authorize('assign', $alert);

        $this->service->assignAdmin($alert, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'SOS Alert assigned successfully.',
        ]);
    }

    /**
     * Admin Close Emergency Alert.
     */
    #[OA\Post(
        path: '/admin/emergency-alerts/{id}/close',
        summary: 'Admin Close SOS Alert',
        description: 'Closes a resolved SOS alert.',
        security: [['bearerAuth' => []]],
        tags: ['Admin SOS Moderation'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'SOS Alert closed.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'SOS Alert closed successfully.'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
        ]
    )]
    public function adminClose(Request $request, $id): JsonResponse
    {
        if ($request->user()->role->value !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $alert = EmergencyAlert::findOrFail($id);
        Gate::authorize('close', $alert);

        $this->service->closeSOS($alert, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'SOS Alert closed successfully.',
        ]);
    }

    /**
     * Admin SOS Statistics.
     */
    #[OA\Get(
        path: '/admin/emergency-alerts/statistics',
        summary: 'Admin SOS Statistics',
        description: 'Returns consolidated metrics: today, weekly, monthly, active, resolved, average_response_time.',
        security: [['bearerAuth' => []]],
        tags: ['Admin SOS Moderation'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'SOS statistics retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'today', type: 'integer'),
                                new OA\Property(property: 'weekly', type: 'integer'),
                                new OA\Property(property: 'monthly', type: 'integer'),
                                new OA\Property(property: 'active', type: 'integer'),
                                new OA\Property(property: 'resolved', type: 'integer'),
                                new OA\Property(property: 'average_response_time', type: 'number', format: 'float'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
        ]
    )]
    public function adminStatistics(Request $request): JsonResponse
    {
        if ($request->user()->role->value !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        // Calculating metrics
        $today = EmergencyAlert::whereDate('created_at', Carbon::today())->count();
        $weekly = EmergencyAlert::where('created_at', '>=', Carbon::now()->subDays(7))->count();
        $monthly = EmergencyAlert::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        $active = EmergencyAlert::where('status', 'active')->count();
        $resolved = EmergencyAlert::where('status', 'resolved')->count();

        // Calculate average response time safely for SQLite & MySQL in PHP
        $resolvedAlerts = EmergencyAlert::where('status', 'resolved')
            ->whereNotNull('resolved_at')
            ->get();
        $totalSeconds = 0;
        foreach ($resolvedAlerts as $alert) {
            $totalSeconds += $alert->created_at->diffInSeconds($alert->resolved_at);
        }
        $avgResponseTime = $resolvedAlerts->count() > 0
            ? round($totalSeconds / $resolvedAlerts->count(), 2)
            : 0.0;

        return response()->json([
            'success' => true,
            'data' => [
                'today' => $today,
                'weekly' => $weekly,
                'monthly' => $monthly,
                'active' => $active,
                'resolved' => $resolved,
                'average_response_time' => $avgResponseTime,
            ],
        ]);
    }
}
