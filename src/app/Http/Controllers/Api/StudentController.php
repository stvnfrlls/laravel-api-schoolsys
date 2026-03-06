<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    // GET /students
    public function index(): JsonResponse
    {
        return response()->json(
            Student::with('user')->paginate(20)
        );
    }

    // GET /students/{student}
    public function show(Student $student): JsonResponse
    {
        return response()->json($student->load('user', 'enrollments'));
    }

    // PUT /students/{student}
    // Allows admin / sub-admin to fill in or correct student profile details
    public function update(Request $request, Student $student): JsonResponse
    {
        $data = $request->validate([
            'student_number' => ['sometimes', 'string', Rule::unique('students')->ignore($student->id)],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'gender' => ['sometimes', Rule::in(['male', 'female', 'other'])],
        ]);

        $student->update($data);

        return response()->json($student->fresh('user'));
    }
}
