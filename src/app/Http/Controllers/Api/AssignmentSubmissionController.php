<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AssignmentSubmissionController extends Controller
{
    /**
     * Get all submissions for an assignment
     */
    public function index(Assignment $assignment): JsonResponse
    {
        $submissions = $assignment->submissions()
            ->with(['student:id,first_name,last_name,email', 'gradedBy:id,first_name,last_name'])
            ->get();

        return response()->json([
            'data' => $submissions,
        ]);
    }

    /**
     * Get a student's submission for an assignment
     */
    public function show(Assignment $assignment, AssignmentSubmission $submission): JsonResponse
    {
        // Ensure submission belongs to this assignment
        if ($submission->assignment_id !== $assignment->id) {
            return response()->json(['message' => 'Submission not found'], 404);
        }

        $submission->load(['student:id,first_name,last_name,email', 'gradedBy:id,first_name,last_name']);

        return response()->json([
            'data' => $submission,
        ]);
    }

    /**
     * Get current user's submission for an assignment
     */
    public function getMySubmission(Assignment $assignment): JsonResponse
    {
        $submission = $assignment->submissions()
            ->where('student_id', auth()->id())
            ->with('gradedBy:id,first_name,last_name')
            ->first();

        if (!$submission) {
            // Return empty submission in draft status
            return response()->json([
                'data' => [
                    'assignment_id' => $assignment->id,
                    'student_id' => auth()->id(),
                    'submission_text' => null,
                    'submitted_at' => null,
                    'status' => 'draft',
                    'score' => null,
                    'feedback' => null,
                ],
            ]);
        }

        return response()->json([
            'data' => $submission,
        ]);
    }

    /**
     * Create or update a submission (draft)
     */
    public function saveDraft(Request $request, Assignment $assignment): JsonResponse
    {
        $validated = $request->validate([
            'submission_text' => 'nullable|string',
        ]);

        $submission = $assignment->submissions()
            ->firstOrCreate(
                [
                    'student_id' => auth()->id(),
                ],
                [
                    'status' => 'draft',
                    'submission_text' => null,
                ]
            );

        $submission->update([
            'submission_text' => $validated['submission_text'] ?? $submission->submission_text,
        ]);

        return response()->json([
            'data' => $submission,
            'message' => 'Draft saved successfully',
        ]);
    }

    /**
     * Submit assignment (student)
     */
    public function submit(Request $request, Assignment $assignment): JsonResponse
    {
        $validated = $request->validate([
            'submission_text' => 'nullable|string',
        ]);

        $submission = $assignment->submissions()
            ->firstOrCreate(
                [
                    'student_id' => auth()->id(),
                ],
                [
                    'status' => 'draft',
                    'submission_text' => null,
                ]
            );

        $submission->update([
            'submission_text' => $validated['submission_text'] ?? $submission->submission_text,
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        return response()->json([
            'data' => $submission,
            'message' => 'Assignment submitted successfully',
        ]);
    }

    /**
     * Grade a submission (teacher)
     */
    public function grade(Request $request, Assignment $assignment, AssignmentSubmission $submission): JsonResponse
    {
        $this->authorize('update', $assignment);

        // Ensure submission belongs to this assignment
        if ($submission->assignment_id !== $assignment->id) {
            return response()->json(['message' => 'Submission not found'], 404);
        }

        $validated = $request->validate([
            'score' => 'required|numeric|min:0|max:' . $assignment->total_points,
            'feedback' => 'nullable|string',
        ]);

        $submission->update([
            'score' => $validated['score'],
            'feedback' => $validated['feedback'] ?? null,
            'status' => 'graded',
            'graded_at' => now(),
            'graded_by' => auth()->id(),
        ]);

        return response()->json([
            'data' => $submission,
            'message' => 'Submission graded successfully',
        ]);
    }

    /**
     * Get submission status summary for an assignment
     */
    public function getSummary(Assignment $assignment): JsonResponse
    {
        $this->authorize('view', $assignment);

        $total = $assignment->submissions()->count();
        $submitted = $assignment->submissions()->whereNotNull('submitted_at')->count();
        $graded = $assignment->submissions()->where('status', 'graded')->count();
        $pending = $assignment->submissions()->where('status', 'submitted')->count();

        return response()->json([
            'data' => [
                'total_students' => $total,
                'submitted' => $submitted,
                'graded' => $graded,
                'pending' => $pending,
                'submission_rate' => $total > 0 ? round(($submitted / $total) * 100, 2) : 0,
                'grading_rate' => $total > 0 ? round(($graded / $total) * 100, 2) : 0,
            ],
        ]);
    }

    /**
     * Get all submissions for a student across all assignments
     */
    public function getStudentSubmissions(): JsonResponse
    {
        $submissions = AssignmentSubmission::where('student_id', auth()->id())
            ->with(['assignment:id,title,due_date,total_points', 'assignment.subject:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $submissions,
        ]);
    }

    /**
     * Resubmit an assignment (reset grading)
     */
    public function resubmit(Request $request, Assignment $assignment): JsonResponse
    {
        $validated = $request->validate([
            'submission_text' => 'nullable|string',
        ]);

        $submission = $assignment->submissions()
            ->where('student_id', auth()->id())
            ->first();

        if (!$submission) {
            return response()->json(['message' => 'Submission not found'], 404);
        }

        $submission->update([
            'submission_text' => $validated['submission_text'] ?? $submission->submission_text,
            'submitted_at' => now(),
            'status' => 'submitted',
            'score' => null,
            'feedback' => null,
            'graded_at' => null,
            'graded_by' => null,
        ]);

        return response()->json([
            'data' => $submission,
            'message' => 'Assignment resubmitted successfully',
        ]);
    }
}