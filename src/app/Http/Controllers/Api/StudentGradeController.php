<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentGradeResource;
use App\Models\GradingComponent;
use App\Models\StudentGrade;
use Illuminate\Http\Request;

class StudentGradeController extends Controller
{
    public function index(Request $request)
    {
        $query = StudentGrade::with(['enrollment.student', 'subject', 'gradingComponent']);

        if ($request->filled('enrollment_id')) {
            $query->where('enrollment_id', $request->enrollment_id);
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('quarter')) {
            $query->where('quarter', $request->quarter);
        }

        if ($request->filled('is_failing')) {
            $query->where('is_failing', $request->boolean('is_failing'));
        }

        return response()->json(StudentGradeResource::collection($query->get()));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id',
            'subject_id' => 'required|exists:subjects,id',
            'grading_component_id' => 'required|exists:grading_components,id',
            'quarter' => 'required|integer|between:1,4',
            'score' => 'required|numeric|min:0|max:100',
        ]);

        $component = GradingComponent::findOrFail($validated['grading_component_id']);
        $validated['weighted_score'] = round($validated['score'] * ($component->weight / 100), 2);

        // Compute final grade: sum of all weighted scores for this enrollment+subject+quarter
        $grade = StudentGrade::updateOrCreate(
            [
                'enrollment_id' => $validated['enrollment_id'],
                'subject_id' => $validated['subject_id'],
                'grading_component_id' => $validated['grading_component_id'],
                'quarter' => $validated['quarter'],
            ],
            [
                'score' => $validated['score'],
                'weighted_score' => $validated['weighted_score'],
            ]
        );

        $this->recomputeFinalGrade(
            $validated['enrollment_id'],
            $validated['subject_id'],
            $validated['quarter']
        );

        return response()->json(new StudentGradeResource($grade->fresh()), 201);
    }

    public function show(StudentGrade $studentGrade)
    {
        return response()->json(new StudentGradeResource($studentGrade->load(['enrollment.student', 'subject', 'gradingComponent'])));
    }

    public function update(Request $request, StudentGrade $studentGrade)
    {
        $validated = $request->validate([
            'score' => 'required|numeric|min:0|max:100',
        ]);

        $component = $studentGrade->gradingComponent;
        $studentGrade->update([
            'score' => $validated['score'],
            'weighted_score' => round($validated['score'] * ($component->weight / 100), 2),
        ]);

        $this->recomputeFinalGrade(
            $studentGrade->enrollment_id,
            $studentGrade->subject_id,
            $studentGrade->quarter
        );

        return response()->json(new StudentGradeResource($studentGrade->fresh()));
    }

    public function destroy(StudentGrade $studentGrade)
    {
        $studentGrade->delete();
        return response()->json(['message' => 'Grade deleted.']);
    }

    // --- Helper ---

    private function recomputeFinalGrade(int $enrollmentId, int $subjectId, int $quarter): void
    {
        $grades = StudentGrade::where('enrollment_id', $enrollmentId)
            ->where('subject_id', $subjectId)
            ->where('quarter', $quarter)
            ->get();

        $finalGrade = round($grades->sum('weighted_score'), 2);
        $isFailing = $finalGrade < 75;

        // One UPDATE instead of one per row
        StudentGrade::where('enrollment_id', $enrollmentId)
            ->where('subject_id', $subjectId)
            ->where('quarter', $quarter)
            ->update([
                'final_grade' => $finalGrade,
                'is_failing' => $isFailing,
            ]);
    }
}