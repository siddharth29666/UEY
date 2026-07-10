<?php

namespace App\Http\Controllers;

use App\Events\TypingStarted;
use App\Events\TypingStopped;
use App\Http\Requests\ConversationRequest;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\BroadcastStatusResource;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Models\Ride;
use App\Services\ConversationService;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class ChatController extends Controller
{
    public function __construct(
        protected ConversationService $conversationService,
        protected MessageService $messageService
    ) {}

    #[OA\Post(
        path: '/conversations',
        summary: 'Create or Get Chat Conversation',
        description: 'Creates a new chat conversation thread for an active ride or returns the existing one.',
        security: [['bearerAuth' => []]],
        tags: ['Real-Time Chat'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'ride_id', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Conversation created or retrieved successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'conversation', ref: '#/components/schemas/Conversation'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, description: 'Forbidden access to the ride conversation.'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ]
    )]
    public function createConversation(ConversationRequest $request): JsonResponse
    {
        $ride = Ride::findOrFail($request->input('ride_id'));
        $conversation = $this->conversationService->getOrCreateThread($ride, $request->user());

        return response()->json([
            'success' => true,
            'conversation' => new ConversationResource($conversation),
        ], 201);
    }

    #[OA\Post(
        path: '/messages',
        summary: 'Send Chat Message',
        description: 'Sends a chat message to a conversation thread.',
        security: [['bearerAuth' => []]],
        tags: ['Real-Time Chat'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Hello, I am at the pickup point.'),
                    new OA\Property(property: 'type', type: 'string', enum: ['text', 'image', 'location'], example: 'text'),
                    new OA\Property(property: 'conversation_id', type: 'integer', example: 1, nullable: true),
                    new OA\Property(property: 'ride_id', type: 'integer', example: 1, nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Message sent successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', ref: '#/components/schemas/Message'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, description: 'Forbidden access to the conversation.'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        ]
    )]
    public function sendMessage(SendMessageRequest $request, $rideId = null): JsonResponse
    {
        $user = $request->user();
        $conversation = null;

        if ($rideId) {
            $ride = Ride::findOrFail($rideId);
            $conversation = $this->conversationService->getOrCreateThread($ride, $user);
        } elseif ($request->has('conversation_id')) {
            $conversation = $this->conversationService->getThread($request->input('conversation_id'), $user);
        } elseif ($request->has('ride_id')) {
            $ride = Ride::findOrFail($request->input('ride_id'));
            $conversation = $this->conversationService->getOrCreateThread($ride, $user);
        } else {
            throw ValidationException::withMessages([
                'ride_id' => ['Either ride_id or conversation_id is required.'],
            ]);
        }

        $message = $this->messageService->saveMessage(
            $conversation,
            $user,
            $request->input('message'),
            $request->input('type', 'text')
        );

        return response()->json([
            'success' => true,
            'message' => new MessageResource($message),
        ], 201);
    }

    #[OA\Get(
        path: '/messages',
        summary: 'Get Conversation Messages',
        description: 'Retrieves all messages for a specific conversation thread.',
        security: [['bearerAuth' => []]],
        tags: ['Real-Time Chat'],
        parameters: [
            new OA\Parameter(name: 'conversation_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'ride_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Messages retrieved successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'messages',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Message')
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, description: 'Forbidden access to the conversation.'),
        ]
    )]
    public function getMessages(Request $request, $rideId = null): JsonResponse
    {
        $user = $request->user();
        $conversation = null;

        if ($rideId) {
            $ride = Ride::findOrFail($rideId);
            $conversation = $this->conversationService->getOrCreateThread($ride, $user);
        } elseif ($request->has('conversation_id')) {
            $conversation = $this->conversationService->getThread($request->input('conversation_id'), $user);
        } elseif ($request->has('ride_id')) {
            $ride = Ride::findOrFail($request->input('ride_id'));
            $conversation = $this->conversationService->getOrCreateThread($ride, $user);
        } else {
            throw ValidationException::withMessages([
                'ride_id' => ['Either ride_id or conversation_id is required.'],
            ]);
        }

        $messages = $conversation->messages()->orderBy('created_at', 'asc')->get();

        return response()->json([
            'success' => true,
            'messages' => MessageResource::collection($messages),
        ]);
    }

    #[OA\Post(
        path: '/messages/{id}/delivered',
        summary: 'Mark Message as Delivered',
        description: 'Updates a message status to delivered and broadcasts the delivered state event.',
        security: [['bearerAuth' => []]],
        tags: ['Real-Time Chat'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Message status updated successfully.',
                content: new OA\JsonContent(ref: '#/components/schemas/BroadcastStatus')
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, description: 'Forbidden access to update this message status.'),
        ]
    )]
    public function markDelivered(Request $request, $id): JsonResponse
    {
        $message = Message::findOrFail($id);
        $this->messageService->markAsDelivered($message, $request->user());

        return response()->json(new BroadcastStatusResource([
            'success' => true,
            'message' => 'Message marked as delivered.',
        ]));
    }

    #[OA\Post(
        path: '/messages/{id}/read',
        summary: 'Mark Message as Read',
        description: 'Updates a message status to read and broadcasts the read state event.',
        security: [['bearerAuth' => []]],
        tags: ['Real-Time Chat'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Message status updated successfully.',
                content: new OA\JsonContent(ref: '#/components/schemas/BroadcastStatus')
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, description: 'Forbidden access to update this message status.'),
        ]
    )]
    public function markRead(Request $request, $id): JsonResponse
    {
        $message = Message::findOrFail($id);
        $this->messageService->markAsRead($message, $request->user());

        return response()->json(new BroadcastStatusResource([
            'success' => true,
            'message' => 'Message marked as read.',
        ]));
    }

    #[OA\Post(
        path: '/rides/{ride}/typing/start',
        summary: 'Send Typing Started Indicator',
        description: 'Broadcasts a client typing started event on the private ride channel.',
        security: [['bearerAuth' => []]],
        tags: ['Real-Time Chat'],
        parameters: [
            new OA\Parameter(name: 'ride', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Typing started event broadcasted.',
                content: new OA\JsonContent(ref: '#/components/schemas/BroadcastStatus')
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, description: 'Forbidden access to this ride conversation.'),
        ]
    )]
    public function typingStart(Request $request, $rideId): JsonResponse
    {
        $ride = Ride::findOrFail($rideId);
        $this->conversationService->authorizeAccess($ride, $request->user());

        event(new TypingStarted($ride, $request->user()));

        return response()->json(new BroadcastStatusResource([
            'success' => true,
            'message' => 'Typing started broadcasted.',
        ]));
    }

    #[OA\Post(
        path: '/rides/{ride}/typing/stop',
        summary: 'Send Typing Stopped Indicator',
        description: 'Broadcasts a client typing stopped event on the private ride channel.',
        security: [['bearerAuth' => []]],
        tags: ['Real-Time Chat'],
        parameters: [
            new OA\Parameter(name: 'ride', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Typing stopped event broadcasted.',
                content: new OA\JsonContent(ref: '#/components/schemas/BroadcastStatus')
            ),
            new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
            new OA\Response(response: 403, description: 'Forbidden access to this ride conversation.'),
        ]
    )]
    public function typingStop(Request $request, $rideId): JsonResponse
    {
        $ride = Ride::findOrFail($rideId);
        $this->conversationService->authorizeAccess($ride, $request->user());

        event(new TypingStopped($ride, $request->user()));

        return response()->json(new BroadcastStatusResource([
            'success' => true,
            'message' => 'Typing stopped broadcasted.',
        ]));
    }

    // Preserve Call & Emergency stubs from legacy phase
    public function initiateCall(Request $request, $ride)
    {
        return response()->json(['success' => true, 'message' => 'Call initiated.']);
    }

    public function triggerEmergency(Request $request, $ride)
    {
        return response()->json(['success' => true, 'message' => 'Emergency services notified.']);
    }
}
