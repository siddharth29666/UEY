<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'driver' => $this->resource['driver'] ?? null,
            'vehicle' => $this->resource['vehicle'] ?? null,
            'coordinates' => $this->resource['coordinates'] ?? null,
            'heading' => isset($this->resource['heading']) ? (float) $this->resource['heading'] : null,
            'speed' => isset($this->resource['speed']) ? (float) $this->resource['speed'] : null,
            'eta' => [
                'remaining_distance' => isset($this->resource['eta']['remaining_distance']) ? (float) $this->resource['eta']['remaining_distance'] : 0.0,
                'remaining_time' => isset($this->resource['eta']['remaining_time']) ? (int) $this->resource['eta']['remaining_time'] : 0,
                'estimated_arrival' => $this->resource['eta']['estimated_arrival'] ?? null,
            ],
            'status' => $this->resource['status'] ?? null,
            'last_updated' => $this->resource['last_updated'] ?? null,
        ];
    }
}
