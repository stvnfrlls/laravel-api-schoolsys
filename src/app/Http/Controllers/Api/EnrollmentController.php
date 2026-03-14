<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentResource;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EnrollmentController extends Controller
{
    // GET /enrollments
    public function index(Request $request)
    {
        $query = Enrollment::with(['student.user', 'section', 'gradeLevel']);

        if ($request->filled('section_id'))
            $query->where('section_id', $request->section_id);
        if ($request->filled('school_year'))
            $query->where('school_year', $request->school_year);
        if ($request->filled('semester'))
            $query->where('semester', $request->semester);
        if ($request->filled('status'))
            $query->where('status', $request->status);

        return EnrollmentResource::collection($query->paginate(20));
    }

    // POST /enrollments
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'section_id' => ['required', 'exists:sections,id'],
            'grade_level_id' => ['required', 'exists:grade_levels,id'],
            'school_year' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['required', Rule::in(['1st', '2nd', 'summer'])],
        ]);

        // Guard: prevent duplicate enrollment for the same period
        $duplicate = Enrollment::where('student_id', $data['student_id'])
            ->where('school_year', $data['school_year'])
            ->where('semester', $data['semester'])
            ->exists();

        if ($duplicate) {
            return response()->json([
                'message' => 'Student is already enrolled for this school year and semester.',
            ], 409);
        }

        // Guard: section must belong to the given grade level
        $section = Section::findOrFail($data['section_id']);
        if ((int) $section->grade_level_id !== (int) $data['grade_level_id']) {
            return response()->json([
                'message' => 'The selected section does not belong to the specified grade level.',
            ], 422);
        }

        $enrollment = Enrollment::create($data);

        // refresh() re-queries the DB so columns with database-level defaults
        // (status, enrolled_at) are present in the response — create() alone
        // only returns what you passed in.
        return response()->json(new EnrollmentResource($enrollment->refresh()->load(['student.user', 'section', 'gradeLevel'])), 201);
    }

    // GET /enrollments/{enrollment}
    public function show(Enrollment $enrollment): JsonResponse
    {
        return response()->json(new EnrollmentResource($enrollment->load(['student.user', 'section', 'gradeLevel'])));
    }

    // PUT /enrollments/{enrollment}
    public function update(Request $request, Enrollment $enrollment): JsonResponse
    {
        $data = $request->validate([
            'section_id' => ['sometimes', 'exists:sections,id'],
            'grade_level_id' => ['sometimes', 'exists:grade_levels,id'],
            'status' => ['sometimes', Rule::in(['active', 'dropped', 'completed'])],
        ]);

        if (isset($data['section_id'])) {
            $gradeLevelId = $data['grade_level_id'] ?? $enrollment->grade_level_id;
            $section = Section::findOrFail($data['section_id']);

            if ((int) $section->grade_level_id !== (int) $gradeLevelId) {
                return response()->json([
                    'message' => 'The selected section does not belong to the specified grade level.',
                ], 422);
            }
        }

        $enrollment->update($data);

        return response()->json(new EnrollmentResource($enrollment->fresh(['student.user', 'section', 'gradeLevel'])));
    }

    // DELETE /enrollments/{enrollment}
    public function destroy(Enrollment $enrollment): JsonResponse
    {
        $enrollment->delete();
        return response()->json(['message' => 'Enrollment removed successfully.']);
    }

    // GET /sections/{section}/enrollments
    public function bySection(Request $request, Section $section)
    {
        $request->validate([
            'school_year' => ['nullable', 'string'],
            'semester' => ['nullable', Rule::in(['1st', '2nd', 'summer'])],
            'status' => ['nullable', Rule::in(['active', 'dropped', 'completed'])],
        ]);

        $query = $section->enrollments()->with(['student.user', 'gradeLevel']);

        if ($request->filled('school_year'))
            $query->where('school_year', $request->school_year);
        if ($request->filled('semester'))
            $query->where('semester', $request->semester);
        if ($request->filled('status'))
            $query->where('status', $request->status);

        return EnrollmentResource::collection($query->paginate(20));
    }
}
