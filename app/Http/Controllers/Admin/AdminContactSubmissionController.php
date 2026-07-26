<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateContactSubmissionRequest;
use App\Http\Resources\ContactSubmissionResource;
use App\Models\ContactSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AdminContactSubmissionController extends Controller
{
    /**
     * List Contact Submissions.
     */
    #[OA\Get(
        path: '/admin/contact-submissions',
        summary: 'Admin — List Contact Submissions',
        description: 'Returns list of user contact us submissions.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Contact Us Management'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Contact submissions list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ContactSubmissionResource')),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = ContactSubmission::with('user');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $submissions = $query->orderBy('id', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => ContactSubmissionResource::collection($submissions),
            'pagination' => [
                'total' => $submissions->total(),
                'per_page' => $submissions->perPage(),
                'current_page' => $submissions->currentPage(),
                'last_page' => $submissions->lastPage(),
            ],
        ]);
    }

    /**
     * Show Contact Submission.
     */
    #[OA\Get(
        path: '/admin/contact-submissions/{id}',
        summary: 'Admin — Show Contact Submission',
        description: 'Retrieves details of a contact submission.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Contact Us Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Submission details.'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $submission = ContactSubmission::with('user')->findOrFail($id);

        if ($submission->status === 'new') {
            $submission->status = 'read';
            $submission->save();
        }

        return response()->json([
            'success' => true,
            'data' => new ContactSubmissionResource($submission),
        ]);
    }

    /**
     * Update Contact Submission.
     */
    #[OA\Put(
        path: '/admin/contact-submissions/{id}',
        summary: 'Admin — Update Contact Submission',
        description: 'Updates status or notes of a contact submission.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Contact Us Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['new', 'read', 'in_progress', 'resolved', 'archived'], example: 'resolved'),
                    new OA\Property(property: 'admin_notes', type: 'string', example: 'Contacted user via phone and resolved inquiry.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Submission updated successfully.'),
        ]
    )]
    public function update(UpdateContactSubmissionRequest $request, int $id): JsonResponse
    {
        $submission = ContactSubmission::findOrFail($id);
        $data = $request->validated();

        if (isset($data['status']) && $data['status'] === 'resolved' && !$submission->responded_at) {
            $data['responded_at'] = now();
        }

        $submission->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Contact submission updated successfully.',
            'data' => new ContactSubmissionResource($submission),
        ]);
    }

    /**
     * Delete Contact Submission.
     */
    #[OA\Delete(
        path: '/admin/contact-submissions/{id}',
        summary: 'Admin — Delete Contact Submission',
        description: 'Soft deletes a contact submission.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Contact Us Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Submission deleted successfully.'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $submission = ContactSubmission::findOrFail($id);
        $submission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact submission deleted successfully.',
        ]);
    }
}
