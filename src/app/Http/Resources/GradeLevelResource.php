<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GradeLevelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'level' => $this->level,
            'is_active' => $this->is_active,
            'sections_count' => $this->whenNotNull($this->sections_count),
            'sections' => SectionResource::collection($this->whenLoaded('sections')),
            'pivot' => $this->when(
                $this->pivot !== null,
                fn() => [
                    'units' => $this->pivot->units,
                    'hours_per_week' => $this->pivot->hours_per_week,
                ]
            ),
        ];
    }
}