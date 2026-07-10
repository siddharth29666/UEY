<?php

namespace App\Services;

use App\Events\MessageDelivered;
use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MessageService
{
    /**
     * Save a new message in the conversation thread.
     */
    public function saveMessage(Conversation $conversation, User $sender, string $messageText, string $type = 'text'): Message
    {
        // Create the message record
        $message = Message::create([
            'conversation_thread_id' => $conversation->id,
            'sender_id' => $sender->id,
            'message' => $messageText,
            'type' => $type,
            'status' => 'sent',
        ]);

        // Dispatch broadcast event
        event(new MessageSent($message));

        return $message;
    }

    /**
     * Mark a message as delivered.
     */
    public function markAsDelivered(Message $message, User $user): void
    {
        $conversation = $message->conversation;
        if (! $conversation) {
            return;
        }

        // Validate that user is part of conversation
        if ($user->role->value !== 'admin' && (int) $conversation->driver_id !== (int) $user->id && (int) $conversation->rider_id !== (int) $user->id) {
            throw new AccessDeniedHttpException('You are not authorized to update this message.');
        }

        // Do not update if already read or delivered
        if ($message->status !== 'sent') {
            return;
        }

        // Only the recipient can mark as delivered
        if ((int) $message->sender_id === (int) $user->id) {
            return;
        }

        $message->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        // Dispatch broadcast event
        event(new MessageDelivered($message));
    }

    /**
     * Mark a message as read.
     */
    public function markAsRead(Message $message, User $user): void
    {
        $conversation = $message->conversation;
        if (! $conversation) {
            return;
        }

        // Validate that user is part of conversation
        if ($user->role->value !== 'admin' && (int) $conversation->driver_id !== (int) $user->id && (int) $conversation->rider_id !== (int) $user->id) {
            throw new AccessDeniedHttpException('You are not authorized to update this message.');
        }

        // Do not update if already read
        if ($message->status === 'read') {
            return;
        }

        // Only the recipient can mark as read
        if ((int) $message->sender_id === (int) $user->id) {
            return;
        }

        $message->update([
            'status' => 'read',
            'read_at' => now(),
            'delivered_at' => $message->delivered_at ?: now(), // ensure delivered_at is set
        ]);

        // Dispatch broadcast event
        event(new MessageRead($message));
    }
}
