<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GradingComponentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'weight' => $this->weight,
            'is_active' => $this->is_active,
            'subject' => new SubjectResource($this->whenLoaded('subject')),
        ];
    }
}