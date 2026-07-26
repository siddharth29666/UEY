<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLegalPageRequest;
use App\Http\Requests\Admin\UpdateLegalPageRequest;
use App\Http\Resources\LegalPageResource;
use App\Models\LegalPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class AdminLegalPageController extends Controller
{
    /**
     * List Legal Pages.
     */
    #[OA\Get(
        path: '/admin/legal-pages',
        summary: 'Admin — List Legal Pages',
        description: 'Returns all legal pages including draft/unpublished pages.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Legal Pages Management'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Legal pages list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/LegalPageResource')),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $pages = LegalPage::orderBy('id', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => LegalPageResource::collection($pages),
        ]);
    }

    /**
     * Create Legal Page.
     */
    #[OA\Post(
        path: '/admin/legal-pages',
        summary: 'Admin — Create Legal Page',
        description: 'Creates a new legal page.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Legal Pages Management'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'content'],
                properties: [
                    new OA\Property(property: 'slug', type: 'string', example: 'privacy-policy'),
                    new OA\Property(property: 'title', type: 'string', example: 'Privacy Policy'),
                    new OA\Property(property: 'content', type: 'string', example: '# Privacy Policy...'),
                    new OA\Property(property: 'version', type: 'string', example: '1.0'),
                    new OA\Property(property: 'is_published', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Legal page created successfully.'),
        ]
    )]
    public function store(StoreLegalPageRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        if (!isset($data['is_published']) || $data['is_published']) {
            $data['published_at'] = now();
        }

        $page = LegalPage::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Legal page created successfully.',
            'data' => new LegalPageResource($page),
        ], 201);
    }

    /**
     * Show Legal Page.
     */
    #[OA\Get(
        path: '/admin/legal-pages/{id}',
        summary: 'Admin — Show Legal Page',
        description: 'Retrieves details of a legal page.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Legal Pages Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Legal page details.'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $page = LegalPage::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new LegalPageResource($page),
        ]);
    }

    /**
     * Update Legal Page.
     */
    #[OA\Put(
        path: '/admin/legal-pages/{id}',
        summary: 'Admin — Update Legal Page',
        description: 'Updates an existing legal page.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Legal Pages Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Legal page updated successfully.'),
        ]
    )]
    public function update(UpdateLegalPageRequest $request, int $id): JsonResponse
    {
        $page = LegalPage::findOrFail($id);
        $data = $request->validated();

        if (isset($data['is_published']) && $data['is_published'] && !$page->is_published) {
            $data['published_at'] = now();
        }

        $page->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Legal page updated successfully.',
            'data' => new LegalPageResource($page),
        ]);
    }

    /**
     * Toggle Legal Page Publish Status.
     */
    #[OA\Patch(
        path: '/admin/legal-pages/{id}/status',
        summary: 'Admin — Toggle Legal Page Status',
        description: 'Publishes or unpublishes a legal page.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Legal Pages Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Status updated successfully.'),
        ]
    )]
    public function status(int $id): JsonResponse
    {
        $page = LegalPage::findOrFail($id);
        $page->is_published = !$page->is_published;
        if ($page->is_published && !$page->published_at) {
            $page->published_at = now();
        }
        $page->save();

        return response()->json([
            'success' => true,
            'message' => 'Legal page publish status updated successfully.',
            'data' => new LegalPageResource($page),
        ]);
    }

    /**
     * Delete Legal Page.
     */
    #[OA\Delete(
        path: '/admin/legal-pages/{id}',
        summary: 'Admin — Delete Legal Page',
        description: 'Soft deletes a legal page.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Legal Pages Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Legal page deleted successfully.'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $page = LegalPage::findOrFail($id);
        $page->delete();

        return response()->json([
            'success' => true,
            'message' => 'Legal page deleted successfully.',
        ]);
    }
}
