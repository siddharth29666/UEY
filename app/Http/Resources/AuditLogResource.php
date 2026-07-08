<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'admin_id' => $this->admin_id,
            'admin_name' => $this->admin_name,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'module' => $this->module,
            'action' => $this->action,
            'affected_table' => $this->affected_table,
            'affected_record_id' => $this->affected_record_id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
