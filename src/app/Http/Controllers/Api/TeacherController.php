<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeacherController extends Controller
{
    // GET /teachers
    public function index(): JsonResponse
    {
        return response()->json(
            Teacher::with('user')->paginate(20)
        );
    }

    // GET /teachers/{teacher}
    public function show(Teacher $teacher): JsonResponse
    {
        return response()->json(
            $teacher->load('user', 'schedules')
        );
    }

    // PUT /teachers/{teacher}
    // Allows admin / sub-admin to fill in or correct teacher profile details
    public function update(Request $request, Teacher $teacher): JsonResponse
    {
        $data = $request->validate([
            'employee_number' => ['sometimes', 'string', Rule::unique('teachers')->ignore($teacher->id)],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'gender' => ['sometimes', Rule::in(['male', 'female', 'other'])],
        ]);

        $teacher->update($data);

        return response()->json($teacher->fresh('user'));
    }
}