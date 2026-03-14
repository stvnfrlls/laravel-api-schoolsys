<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_year' => $this->school_year,
            'semester' => $this->semester,
            'status' => $this->status,
            'enrolled_at' => $this->enrolled_at,
            'student' => new StudentResource($this->whenLoaded('student')),
            'section' => new SectionResource($this->whenLoaded('section')),
            'grade_level' => new GradeLevelResource($this->whenLoaded('gradeLevel')),
        ];
    }
}