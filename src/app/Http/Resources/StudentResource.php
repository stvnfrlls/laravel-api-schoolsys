<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_number' => $this->student_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'suffix' => $this->suffix,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'gender' => $this->gender,
            'user' => new UserResource($this->whenLoaded('user')),
            'enrollments' => EnrollmentResource::collection($this->whenLoaded('enrollments')),
            'active_enrollment' => new EnrollmentResource($this->whenLoaded('activeEnrollment')),
        ];
    }
}