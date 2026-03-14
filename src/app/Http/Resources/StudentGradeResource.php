<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentGradeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quarter' => $this->quarter,
            'score' => $this->score,
            'weighted_score' => $this->weighted_score,
            'final_grade' => $this->final_grade,
            'is_failing' => $this->is_failing,
            'enrollment' => new EnrollmentResource($this->whenLoaded('enrollment')),
            'subject' => new SubjectResource($this->whenLoaded('subject')),
            'grading_component' => new GradingComponentResource($this->whenLoaded('gradingComponent')),
        ];
    }
}