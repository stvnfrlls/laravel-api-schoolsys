<?php

namespace App\Observers;

use App\Models\Student;
use App\Models\User;

class StudentObserver
{
    public function created(User $user): void
    {
        if ($user->hasRole('student') && !$user->student()->exists()) {
            Student::updateOrCreate(
                ['user_id' => $user->id],
                [
                    // Provide safe defaults
                    'student_number' => 'STU-' . strtoupper(uniqid()),
                    'date_of_birth' => now()->subYears(18),
                    'gender' => 'other',
                ]
            );
        }
    }
}
