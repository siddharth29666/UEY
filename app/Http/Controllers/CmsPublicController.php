<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitContactFormRequest;
use App\Http\Resources\CancellationReasonResource;
use App\Http\Resources\ContactSubmissionResource;
use App\Http\Resources\FaqCategoryResource;
use App\Http\Resources\FaqResource;
use App\Http\Resources\LegalPageResource;
use App\Models\CancellationReason;
use App\Models\ContactSubmission;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\LegalPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CmsPublicController extends Controller
{
    /**
     * Public — List cancellation reasons.
     */
    #[OA\Get(
        path: '/cancellation-reasons',
        summary: 'Public — List Cancellation Reasons',
        description: 'Returns active cancellation reasons for riders or drivers.',
        tags: ['Public CMS & Legal'],
        parameters: [
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['rider', 'driver', 'both'])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cancellation reasons list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/CancellationReasonResource')),
                    ]
                )
            ),
        ]
    )]
    public function cancellationReasons(Request $request): JsonResponse
    {
        $query = CancellationReason::where('is_active', true);

        if ($request->filled('type')) {
            $query->whereIn('type', [$request->query('type'), 'both']);
        }

        $reasons = $query->orderBy('sort_order', 'asc')->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => CancellationReasonResource::collection($reasons),
        ]);
    }

    /**
     * Public — List FAQ Categories.
     */
    #[OA\Get(
        path: '/faq-categories',
        summary: 'Public — List FAQ Categories',
        description: 'Returns active FAQ categories.',
        tags: ['Public CMS & Legal'],
        parameters: [
            new OA\Parameter(name: 'audience', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['rider', 'driver', 'both'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'FAQ categories list.'),
        ]
    )]
    public function faqCategories(Request $request): JsonResponse
    {
        $query = FaqCategory::where('is_active', true);

        if ($request->filled('audience')) {
            $query->whereIn('audience', [$request->query('audience'), 'both']);
        }

        $categories = $query->orderBy('sort_order', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => FaqCategoryResource::collection($categories),
        ]);
    }

    /**
     * Public — List FAQs.
     */
    #[OA\Get(
        path: '/faqs',
        summary: 'Public — List FAQs',
        description: 'Returns active FAQs filtered by audience.',
        tags: ['Public CMS & Legal'],
        parameters: [
            new OA\Parameter(name: 'audience', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['rider', 'driver', 'both'])),
            new OA\Parameter(name: 'faq_category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'FAQs list.'),
        ]
    )]
    public function faqs(Request $request): JsonResponse
    {
        $query = Faq::with('category')->where('is_active', true);

        if ($request->filled('audience')) {
            $query->whereIn('audience', [$request->query('audience'), 'both']);
        }

        if ($request->filled('faq_category_id')) {
            $query->where('faq_category_id', $request->query('faq_category_id'));
        }

        $faqs = $query->orderBy('sort_order', 'asc')->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => FaqResource::collection($faqs),
        ]);
    }

    /**
     * Public — Submit Contact Form.
     */
    #[OA\Post(
        path: '/contact-us',
        summary: 'Public — Submit Contact Form',
        description: 'Submits a contact form inquiry.',
        tags: ['Public CMS & Legal'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'subject', 'message'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Rider'),
                    new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                    new OA\Property(property: 'phone', type: 'string', example: '+1234567890'),
                    new OA\Property(property: 'subject', type: 'string', example: 'App Billing Question'),
                    new OA\Property(property: 'message', type: 'string', example: 'I have a question about my ride receipt.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Contact form submitted successfully.'),
        ]
    )]
    public function submitContact(SubmitContactFormRequest $request): JsonResponse
    {
        $data = $request->validated();
        if ($request->user()) {
            $data['user_id'] = $request->user()->id;
        }

        $submission = ContactSubmission::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Thank you for reaching out. Your message has been submitted successfully.',
            'data' => new ContactSubmissionResource($submission),
        ], 201);
    }

    /**
     * Public — Get Privacy Policy.
     */
    #[OA\Get(
        path: '/privacy-policy',
        summary: 'Public — Get Privacy Policy',
        description: 'Returns currently published Privacy Policy.',
        tags: ['Public CMS & Legal'],
        responses: [
            new OA\Response(response: 200, description: 'Privacy policy content.'),
        ]
    )]
    public function privacyPolicy(): JsonResponse
    {
        $page = LegalPage::where('slug', 'privacy-policy')->where('is_published', true)->first();

        if (!$page) {
            return response()->json([
                'success' => true,
                'data' => [
                    'slug' => 'privacy-policy',
                    'title' => 'Privacy Policy',
                    'content' => 'Our Privacy Policy will be published soon.',
                    'version' => '1.0',
                    'is_published' => true,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => new LegalPageResource($page),
        ]);
    }

    /**
     * Public — Get Terms & Conditions.
     */
    #[OA\Get(
        path: '/terms-and-conditions',
        summary: 'Public — Get Terms & Conditions',
        description: 'Returns currently published Terms & Conditions.',
        tags: ['Public CMS & Legal'],
        responses: [
            new OA\Response(response: 200, description: 'Terms & Conditions content.'),
        ]
    )]
    public function termsAndConditions(): JsonResponse
    {
        $page = LegalPage::where('slug', 'terms-and-conditions')->where('is_published', true)->first();

        if (!$page) {
            return response()->json([
                'success' => true,
                'data' => [
                    'slug' => 'terms-and-conditions',
                    'title' => 'Terms & Conditions',
                    'content' => 'Our Terms & Conditions will be published soon.',
                    'version' => '1.0',
                    'is_published' => true,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => new LegalPageResource($page),
        ]);
    }

    /**
     * Public — Get Legal Page by slug.
     */
    #[OA\Get(
        path: '/legal-pages/{slug}',
        summary: 'Public — Get Legal Page by Slug',
        description: 'Returns published legal page content by slug.',
        tags: ['Public CMS & Legal'],
        parameters: [
            new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Legal page details.'),
            new OA\Response(response: 404, description: 'Legal page not found.'),
        ]
    )]
    public function legalPage(string $slug): JsonResponse
    {
        $page = LegalPage::where('slug', $slug)->where('is_published', true)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => new LegalPageResource($page),
        ]);
    }
}
