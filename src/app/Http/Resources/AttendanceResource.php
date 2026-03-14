<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'enrollment' => new EnrollmentResource($this->whenLoaded('enrollment')),
            'subject' => new SubjectResource($this->whenLoaded('subject')),
        ];
    }
}