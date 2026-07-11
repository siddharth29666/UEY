<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFavoritePlaceRequest;
use App\Http\Requests\UpdateFavoritePlaceRequest;
use App\Http\Resources\FavoritePlaceResource;
use App\Models\FavoritePlace;
use App\Services\FavoritePlaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class FavoritePlaceController extends Controller
{
    public function __construct(
        protected FavoritePlaceService $service
    ) {}

    /**
     * List all favorite places.
     */
    #[OA\Get(
        path: '/favorite-places',
        summary: 'List Favorite Places',
        description: 'Retrieves all saved favorite locations for the authenticated rider.',
        security: [['bearerAuth' => []]],
        tags: ['Favorite Places'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Favorite places list retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/FavoritePlaceResource')),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $places = FavoritePlace::where('user_id', $request->user()->id)->get();

        return response()->json([
            'success' => true,
            'data' => FavoritePlaceResource::collection($places),
        ]);
    }

    /**
     * Get default and nearest favorite places.
     */
    #[OA\Get(
        path: '/favorite-places/default',
        summary: 'Get Default & Nearest Places',
        description: 'Returns Home and Work saved places, along with the nearest Saved place if latitude and longitude are supplied.',
        security: [['bearerAuth' => []]],
        tags: ['Favorite Places'],
        parameters: [
            new OA\Parameter(name: 'latitude', in: 'query', required: false, schema: new OA\Schema(type: 'number', format: 'float')),
            new OA\Parameter(name: 'longitude', in: 'query', required: false, schema: new OA\Schema(type: 'number', format: 'float')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Default places retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'home', ref: '#/components/schemas/FavoritePlaceResource', nullable: true),
                        new OA\Property(property: 'work', ref: '#/components/schemas/FavoritePlaceResource', nullable: true),
                        new OA\Property(property: 'nearest_saved_place', ref: '#/components/schemas/FavoritePlaceResource', nullable: true),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ]
    )]
    public function defaults(Request $request): JsonResponse
    {
        $lat = $request->filled('latitude') ? (float) $request->query('latitude') : null;
        $lng = $request->filled('longitude') ? (float) $request->query('longitude') : null;

        $res = $this->service->getDefaults($request->user(), $lat, $lng);

        return response()->json([
            'success' => true,
            'home' => $res['home'] ? new FavoritePlaceResource($res['home']) : null,
            'work' => $res['work'] ? new FavoritePlaceResource($res['work']) : null,
            'nearest_saved_place' => $res['nearestSaved'] ? new FavoritePlaceResource($res['nearestSaved']) : null,
        ]);
    }

    /**
     * Store new favorite place.
     */
    #[OA\Post(
        path: '/favorite-places',
        summary: 'Create Favorite Place',
        description: 'Saves a new favorite location for the rider.',
        security: [['bearerAuth' => []]],
        tags: ['Favorite Places'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreFavoritePlaceRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Favorite place created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/FavoritePlaceResource'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
            new OA\Response(response: 409, description: 'Conflict error or duplication limit exceeded.'),
        ]
    )]
    public function store(StoreFavoritePlaceRequest $request): JsonResponse
    {
        $place = $this->service->createForUser($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'data' => new FavoritePlaceResource($place),
        ], 201);
    }

    /**
     * Show details of a specific favorite place.
     */
    #[OA\Get(
        path: '/favorite-places/{id}',
        summary: 'Get Favorite Place Details',
        description: 'Retrieves a single favorite place by ID.',
        security: [['bearerAuth' => []]],
        tags: ['Favorite Places'],
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
                        new OA\Property(property: 'data', ref: '#/components/schemas/FavoritePlaceResource'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
        ]
    )]
    public function show($id): JsonResponse
    {
        $place = FavoritePlace::findOrFail($id);
        Gate::authorize('view', $place);

        return response()->json([
            'success' => true,
            'data' => new FavoritePlaceResource($place),
        ]);
    }

    /**
     * Update an existing favorite place.
     */
    #[OA\Put(
        path: '/favorite-places/{id}',
        summary: 'Update Favorite Place',
        description: 'Updates details of an existing favorite place.',
        security: [['bearerAuth' => []]],
        tags: ['Favorite Places'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateFavoritePlaceRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Favorite place updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/FavoritePlaceResource'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ]
    )]
    public function update(UpdateFavoritePlaceRequest $request, $id): JsonResponse
    {
        $place = FavoritePlace::findOrFail($id);
        Gate::authorize('update', $place);

        $updated = $this->service->updateForUser($request->user(), $place, $request->validated());

        return response()->json([
            'success' => true,
            'data' => new FavoritePlaceResource($updated),
        ]);
    }

    /**
     * Delete a favorite place.
     */
    #[OA\Delete(
        path: '/favorite-places/{id}',
        summary: 'Delete Favorite Place',
        description: 'Permanently deletes a saved favorite location.',
        security: [['bearerAuth' => []]],
        tags: ['Favorite Places'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Favorite place deleted.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Favorite place deleted successfully.'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenResponse'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFoundResponse'),
        ]
    )]
    public function destroy($id): JsonResponse
    {
        $place = FavoritePlace::findOrFail($id);
        Gate::authorize('delete', $place);

        $place->delete();

        return response()->json([
            'success' => true,
            'message' => 'Favorite place deleted successfully.',
        ]);
    }
}
