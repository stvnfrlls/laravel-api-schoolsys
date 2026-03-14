<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'room' => $this->room,
            'capacity' => $this->capacity,
            'is_active' => $this->is_active,
            'grade_level' => new GradeLevelResource($this->whenLoaded('gradeLevel')),
        ];
    }
}