<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BroadcastStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'success' => $this->resource['success'] ?? true,
            'message' => $this->resource['message'] ?? '',
            'data' => $this->resource['data'] ?? null,
        ];
    }
}
