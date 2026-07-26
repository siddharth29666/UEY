<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFaqCategoryRequest;
use App\Http\Requests\Admin\UpdateFaqCategoryRequest;
use App\Http\Resources\FaqCategoryResource;
use App\Models\FaqCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class AdminFaqCategoryController extends Controller
{
    /**
     * List FAQ Categories.
     */
    #[OA\Get(
        path: '/admin/faq-categories',
        summary: 'Admin — List FAQ Categories',
        description: 'Returns all FAQ categories including inactive.',
        security: [['bearerAuth' => []]],
        tags: ['Admin FAQ Management'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'FAQ categories list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/FaqCategoryResource')),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = FaqCategory::with('faqs');

        if ($request->filled('audience')) {
            $query->whereIn('audience', [$request->query('audience'), 'both']);
        }

        $categories = $query->orderBy('sort_order', 'asc')->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => FaqCategoryResource::collection($categories),
        ]);
    }

    /**
     * Store FAQ Category.
     */
    #[OA\Post(
        path: '/admin/faq-categories',
        summary: 'Admin — Create FAQ Category',
        description: 'Creates a new FAQ Category.',
        security: [['bearerAuth' => []]],
        tags: ['Admin FAQ Management'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'audience'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Payments & Fare'),
                    new OA\Property(property: 'slug', type: 'string', example: 'payments-and-fare'),
                    new OA\Property(property: 'icon', type: 'string', example: 'credit-card'),
                    new OA\Property(property: 'audience', type: 'string', enum: ['rider', 'driver', 'both'], example: 'both'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                    new OA\Property(property: 'sort_order', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Category created successfully.'),
        ]
    )]
    public function store(StoreFaqCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category = FaqCategory::create($data);

        return response()->json([
            'success' => true,
            'message' => 'FAQ category created successfully.',
            'data' => new FaqCategoryResource($category),
        ], 201);
    }

    /**
     * Show FAQ Category.
     */
    #[OA\Get(
        path: '/admin/faq-categories/{id}',
        summary: 'Admin — Show FAQ Category',
        description: 'Returns details of a specific FAQ Category.',
        security: [['bearerAuth' => []]],
        tags: ['Admin FAQ Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Category details.'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $category = FaqCategory::with('faqs')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new FaqCategoryResource($category),
        ]);
    }

    /**
     * Update FAQ Category.
     */
    #[OA\Put(
        path: '/admin/faq-categories/{id}',
        summary: 'Admin — Update FAQ Category',
        description: 'Updates an existing FAQ Category.',
        security: [['bearerAuth' => []]],
        tags: ['Admin FAQ Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Category updated successfully.'),
        ]
    )]
    public function update(UpdateFaqCategoryRequest $request, int $id): JsonResponse
    {
        $category = FaqCategory::findOrFail($id);
        $data = $request->validated();

        if (isset($data['name']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->update($data);

        return response()->json([
            'success' => true,
            'message' => 'FAQ category updated successfully.',
            'data' => new FaqCategoryResource($category->fresh('faqs')),
        ]);
    }

    /**
     * Delete FAQ Category.
     */
    #[OA\Delete(
        path: '/admin/faq-categories/{id}',
        summary: 'Admin — Delete FAQ Category',
        description: 'Soft deletes an FAQ Category.',
        security: [['bearerAuth' => []]],
        tags: ['Admin FAQ Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Category deleted successfully.'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $category = FaqCategory::findOrFail($id);
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'FAQ category deleted successfully.',
        ]);
    }
}
