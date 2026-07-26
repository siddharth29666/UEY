<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFaqRequest;
use App\Http\Requests\Admin\UpdateFaqRequest;
use App\Http\Resources\FaqResource;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AdminFaqController extends Controller
{
    /**
     * List FAQs.
     */
    #[OA\Get(
        path: '/admin/faqs',
        summary: 'Admin — List FAQs',
        description: 'Returns all FAQs including inactive.',
        security: [['bearerAuth' => []]],
        tags: ['Admin FAQ Management'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'FAQs list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/FaqResource')),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Faq::with('category');

        if ($request->filled('faq_category_id')) {
            $query->where('faq_category_id', $request->query('faq_category_id'));
        }

        if ($request->filled('audience')) {
            $query->whereIn('audience', [$request->query('audience'), 'both']);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $faqs = $query->orderBy('sort_order', 'asc')->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => FaqResource::collection($faqs),
        ]);
    }

    /**
     * Create FAQ.
     */
    #[OA\Post(
        path: '/admin/faqs',
        summary: 'Admin — Create FAQ',
        description: 'Creates a new FAQ item.',
        security: [['bearerAuth' => []]],
        tags: ['Admin FAQ Management'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['faq_category_id', 'question', 'answer', 'audience'],
                properties: [
                    new OA\Property(property: 'faq_category_id', type: 'integer', example: 1),
                    new OA\Property(property: 'question', type: 'string', example: 'How do I request a ride?'),
                    new OA\Property(property: 'answer', type: 'string', example: 'Open the app, set your pickup and drop-off location, select vehicle type and tap Request.'),
                    new OA\Property(property: 'audience', type: 'string', enum: ['rider', 'driver', 'both'], example: 'rider'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                    new OA\Property(property: 'sort_order', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'FAQ created successfully.'),
        ]
    )]
    public function store(StoreFaqRequest $request): JsonResponse
    {
        $faq = Faq::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'FAQ created successfully.',
            'data' => new FaqResource($faq->load('category')),
        ], 201);
    }

    /**
     * Show FAQ.
     */
    #[OA\Get(
        path: '/admin/faqs/{id}',
        summary: 'Admin — Show FAQ',
        description: 'Returns details of a specific FAQ.',
        security: [['bearerAuth' => []]],
        tags: ['Admin FAQ Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'FAQ details.'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $faq = Faq::with('category')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new FaqResource($faq),
        ]);
    }

    /**
     * Update FAQ.
     */
    #[OA\Put(
        path: '/admin/faqs/{id}',
        summary: 'Admin — Update FAQ',
        description: 'Updates an existing FAQ item.',
        security: [['bearerAuth' => []]],
        tags: ['Admin FAQ Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'FAQ updated successfully.'),
        ]
    )]
    public function update(UpdateFaqRequest $request, int $id): JsonResponse
    {
        $faq = Faq::findOrFail($id);
        $faq->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'FAQ updated successfully.',
            'data' => new FaqResource($faq->fresh('category')),
        ]);
    }

    /**
     * Toggle FAQ active status.
     */
    #[OA\Patch(
        path: '/admin/faqs/{id}/status',
        summary: 'Admin — Toggle FAQ Status',
        description: 'Activates or deactivates an FAQ.',
        security: [['bearerAuth' => []]],
        tags: ['Admin FAQ Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'FAQ status updated successfully.'),
        ]
    )]
    public function status(int $id): JsonResponse
    {
        $faq = Faq::findOrFail($id);
        $faq->is_active = !$faq->is_active;
        $faq->save();

        return response()->json([
            'success' => true,
            'message' => 'FAQ status updated successfully.',
            'data' => new FaqResource($faq->load('category')),
        ]);
    }

    /**
     * Delete FAQ.
     */
    #[OA\Delete(
        path: '/admin/faqs/{id}',
        summary: 'Admin — Delete FAQ',
        description: 'Soft deletes an FAQ item.',
        security: [['bearerAuth' => []]],
        tags: ['Admin FAQ Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'FAQ deleted successfully.'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $faq = Faq::findOrFail($id);
        $faq->delete();

        return response()->json([
            'success' => true,
            'message' => 'FAQ deleted successfully.',
        ]);
    }
}
