<?php

namespace App\Observers;

use App\Models\Student;
use App\Models\User;

class StudentObserver
{
    public function updated(User $user): void
    {
        if ($user->hasRole('student') && !$user->student()->exists()) {
            Student::create([
                'user_id' => $user->id,
                // Generate a temporary unique student number;
                // admin can update it later via PUT /students/{student}
                'student_number' => 'STU-' . strtoupper(substr(md5($user->id . now()), 0, 8)),
                'date_of_birth' => null,   // nullable until filled
                'gender' => 'other', // default until filled
            ]);
        }
    }
}
