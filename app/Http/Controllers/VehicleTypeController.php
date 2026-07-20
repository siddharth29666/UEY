<?php

namespace App\Http\Controllers;

use App\Http\Resources\VehicleTypeResource;
use App\Models\VehicleType;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class VehicleTypeController extends Controller
{
    /**
     * List active vehicle types.
     */
    #[OA\Get(
        path: '/vehicle-types',
        summary: 'List Available Vehicle Types',
        description: 'Retrieves a list of all active vehicle types sorted alphabetically by name.',
        tags: ['Vehicle Types'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'A list of active vehicle types.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/VehicleType')
                        ),
                    ]
                )
            )
        ]
    )]
    public function index(): JsonResponse
    {
        $types = VehicleType::where('active', true)
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => VehicleTypeResource::collection($types),
        ]);
    }
}
