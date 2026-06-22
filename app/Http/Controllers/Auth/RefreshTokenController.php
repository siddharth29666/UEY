<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class RefreshTokenController extends Controller
{
    /**
     * Refresh the user token.
     */
    #[OA\Post(
        path: '/token/refresh',
        summary: 'Refresh Token',
        description: 'Revokes the user\'s current Sanctum access token and issues a new one with the same role ability.',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token refreshed successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'token', type: 'string', example: '2|zYxWvUtSrQ...', description: 'New Sanctum plain text token.')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/UnauthorizedResponse'
            )
        ]
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        $ability = 'role:' . $user->role->value;
        $token = $user->createToken('uey-auth-token', [$ability])->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
        ]);
    }
}
