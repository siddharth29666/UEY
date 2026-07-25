<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\GoogleAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class GoogleAuthController extends Controller
{
    public function __construct(
        protected GoogleAuthService $googleAuthService
    ) {}

    /**
     * Redirect to Google OAuth URL.
     */
    #[OA\Get(
        path: '/auth/google/redirect',
        summary: 'Google OAuth Redirect',
        description: 'Generates Google OAuth 2.0 authorization URL. Redirects browser or returns JSON URL if requested.',
        tags: ['Authentication'],
        parameters: [
            new OA\Parameter(name: 'role', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['rider', 'driver'], default: 'rider')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Google OAuth redirect URL generated successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'url', type: 'string', example: 'https://accounts.google.com/o/oauth2/v2/auth?...'),
                    ]
                )
            ),
            new OA\Response(response: 302, description: 'Redirect to Google OAuth consent page.'),
        ]
    )]
    public function redirect(Request $request): JsonResponse|RedirectResponse
    {
        $role = $request->query('role', 'rider');
        $url = $this->googleAuthService->getRedirectUrl($role);

        if ($request->wantsJson() || $request->header('Accept') === 'application/json') {
            return response()->json([
                'success' => true,
                'url' => $url,
            ]);
        }

        return redirect()->away($url);
    }

    /**
     * Handle Google OAuth Callback.
     */
    #[OA\Get(
        path: '/auth/google/callback',
        summary: 'Google OAuth Callback (GET)',
        description: 'Receives Google OAuth authorization code, exchanges it for tokens, authenticates user, and returns Sanctum Bearer token.',
        tags: ['Authentication'],
        parameters: [
            new OA\Parameter(name: 'code', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'state', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'role', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['rider', 'driver'], default: 'rider')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Google authentication successful.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Google authentication successful.'),
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                            new OA\Property(property: 'access_token', type: 'string'),
                            new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ]
    )]
    #[OA\Post(
        path: '/auth/google/callback',
        summary: 'Google OAuth Callback (POST)',
        description: 'Accepts Google OAuth authorization code in JSON payload for mobile/API clients.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', example: '4/0AX4XfWh...'),
                    new OA\Property(property: 'role', type: 'string', enum: ['rider', 'driver'], example: 'rider'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Google authentication successful.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Google authentication successful.'),
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ]
    )]
    public function callback(Request $request): JsonResponse
    {
        $code = $request->input('code') ?? $request->query('code');
        $role = $request->input('role') ?? $request->query('role', 'rider');

        // Extract role from state parameter if present
        $stateRaw = $request->query('state');
        if ($stateRaw) {
            $decodedState = json_decode(urldecode($stateRaw), true);
            if (is_array($decodedState) && ! empty($decodedState['role'])) {
                $role = $decodedState['role'];
            }
        }

        if (empty($code)) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization code is missing from Google response.',
            ], 422);
        }

        try {
            $result = $this->googleAuthService->handleCallback($code, $role);

            return response()->json([
                'success' => true,
                'message' => 'Google authentication successful.',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
