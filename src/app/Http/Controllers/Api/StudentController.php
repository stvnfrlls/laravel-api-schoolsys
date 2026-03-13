<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentGrade;
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
        return response()->json($student->load(
            'user',
            'enrollments.section',
            'enrollments.gradeLevel',
        ));
    }

    // PUT /students/{student}
    public function update(Request $request, Student $student): JsonResponse
    {
        $data = $request->validate([
            'student_number' => ['sometimes', 'string', Rule::unique('students')->ignore($student->id)],
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'suffix' => ['sometimes', 'nullable', 'string', 'max:20'],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'gender' => ['sometimes', Rule::in(['male', 'female', 'other'])],
        ]);

        $student->update($data);

        return response()->json($student->fresh('user'));
    }

    // ---------------------------------------------------------------
    // Student self-service routes (role: student)
    // ---------------------------------------------------------------

    // GET /api/student/profile
    public function myProfile(Request $request): JsonResponse
    {
        $student = $request->user()->student;

        if (!$student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        return response()->json(
            $student->load([
                'user',
                'activeEnrollment.section.gradeLevel',
                'activeEnrollment.gradeLevel',
            ])
        );
    }

    // GET /api/student/schedule
    public function mySchedule(Request $request): JsonResponse
    {
        $student = $request->user()->student;

        if (!$student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        $enrollment = $student->activeEnrollment;

        if (!$enrollment) {
            return response()->json(['message' => 'No active enrollment found.'], 404);
        }

        $query = Schedule::with(['subject', 'section', 'teacher'])
            ->where('section_id', $enrollment->section_id);

        if ($request->filled('school_year')) {
            $query->where('school_year', $request->school_year);
        }

        if ($request->filled('semester')) {
            $query->where('semester', $request->semester);
        }

        return response()->json($query->orderBy('day')->orderBy('start_time')->get());
    }

    // GET /api/student/grades
    public function myGrades(Request $request): JsonResponse
    {
        $student = $request->user()->student;

        if (!$student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        $enrollmentIds = $student->enrollments()->pluck('id');

        $query = StudentGrade::with(['subject', 'gradingComponent', 'enrollment'])
            ->whereIn('enrollment_id', $enrollmentIds);

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('quarter')) {
            $query->where('quarter', $request->quarter);
        }

        if ($request->filled('enrollment_id')) {
            $query->where('enrollment_id', $request->enrollment_id);
        }

        $grouped = $query->get()
            ->groupBy('subject_id')
            ->map(function ($rows) {
                $first = $rows->first();
                return [
                    'subject' => $first->subject,
                    'grades' => $rows->values(),
                ];
            })
            ->values();

        return response()->json($grouped);
    }

    // GET /api/student/attendance
    public function myAttendance(Request $request): JsonResponse
    {
        $student = $request->user()->student;

        if (!$student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        $enrollmentIds = $student->enrollments()->pluck('id');

        $query = Attendance::with(['subject', 'enrollment'])
            ->whereIn('enrollment_id', $enrollmentIds);

        if ($request->filled('subject_id')) {
            $query->forSubject($request->subject_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('enrollment_id')) {
            $query->where('enrollment_id', $request->enrollment_id);
        }

        $records = $query->orderBy('date', 'desc')->get();

        $summary = [
            'total' => $records->count(),
            'present' => $records->where('status', 'present')->count(),
            'absent' => $records->where('status', 'absent')->count(),
            'late' => $records->where('status', 'late')->count(),
            'is_flagged' => $records->where('status', 'absent')->count() >= Attendance::ABSENCE_THRESHOLD,
        ];

        return response()->json([
            'summary' => $summary,
            'records' => $records,
        ]);
    }
}