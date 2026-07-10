<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_thread_id' => $this->conversation_thread_id,
            'sender_id' => $this->sender_id,
            'message' => $this->message,
            'type' => $this->type,
            'status' => $this->status,
            'delivered_at' => $this->delivered_at ? $this->delivered_at->toIso8601String() : null,
            'read_at' => $this->read_at ? $this->read_at->toIso8601String() : null,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
