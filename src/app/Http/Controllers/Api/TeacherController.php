<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
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

    // ---------------------------------------------------------------
    // Teacher self-service routes (role: faculty)
    // ---------------------------------------------------------------

    // GET /api/teacher/schedule
    // Returns the authenticated teacher's schedule with subject + section
    public function mySchedule(Request $request): JsonResponse
    {
        $query = Schedule::with(['subject', 'section.gradeLevel'])
            ->where('teacher_id', $request->user()->id);

        if ($request->filled('school_year')) {
            $query->where('school_year', $request->school_year);
        }

        if ($request->filled('semester')) {
            $query->where('semester', $request->semester);
        }

        if ($request->filled('day')) {
            $query->forDay($request->day);
        }

        return response()->json($query->orderBy('day')->orderBy('start_time')->get());
    }

    // GET /api/teacher/subjects
    // Returns distinct subjects and their sections assigned to the authenticated teacher
    public function mySubjects(Request $request): JsonResponse
    {
        $schedules = Schedule::with(['subject', 'section.gradeLevel'])
            ->where('teacher_id', $request->user()->id);

        if ($request->filled('school_year')) {
            $schedules->where('school_year', $request->school_year);
        }

        if ($request->filled('semester')) {
            $schedules->where('semester', $request->semester);
        }

        // Group by subject so the teacher sees each subject with the sections they handle
        $grouped = $schedules->get()
            ->groupBy('subject_id')
            ->map(function ($rows) {
                $first = $rows->first();
                return [
                    'subject' => $first->subject,
                    'sections' => $rows->pluck('section')->unique('id')->values(),
                ];
            })
            ->values();

        return response()->json($grouped);
    }
}