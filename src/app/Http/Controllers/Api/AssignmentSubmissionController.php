<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentAnswer;
use App\Models\AssignmentSubmission;
use App\Models\StudentGrade;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssignmentSubmissionController extends Controller
{
    public function index(Assignment $assignment): JsonResponse
    {
        $submissions = $assignment->submissions()
            ->with([
                'student:id,first_name,last_name',
                'answers.question',
            ])
            ->get();

        return response()->json(['data' => $submissions]);
    }

    public function show(Assignment $assignment, AssignmentSubmission $submission): JsonResponse
    {
        abort_if($submission->assignment_id !== $assignment->id, 404);

        $submission->load([
            'student:id,first_name,last_name',
            'gradedBy:id,first_name,last_name',
            'answers.question.options',
        ]);

        return response()->json(['data' => $submission]);
    }

    public function getMySubmission(Assignment $assignment): JsonResponse
    {
        $studentId = auth()->user()->student->id;

        $submission = $assignment->submissions()
            ->where('student_id', $studentId)
            ->with(['answers.question.options', 'gradedBy:id,first_name,last_name'])
            ->first();

        if (!$submission) {
            return response()->json([
                'data' => [
                    'assignment_id' => $assignment->id,
                    'student_id'    => $studentId,
                    'status'        => 'draft',
                    'answers'       => [],
                ],
            ]);
        }

        return response()->json(['data' => $submission]);
    }

    public function saveDraft(Request $request, Assignment $assignment): JsonResponse
    {
        $studentId = auth()->user()->student->id;

        $data = $request->validate([
            'answers'                    => 'nullable|array',
            'answers.*.question_id'      => 'required|exists:assignment_questions,id',
            'answers.*.answer_text'      => 'nullable|string',
            'answers.*.selected_option_ids' => 'nullable|array',
            'answers.*.selected_option_ids.*' => 'integer|exists:assignment_question_options,id',
        ]);

        $submission = $assignment->submissions()->firstOrCreate(
            ['student_id' => $studentId],
            ['status' => 'draft']
        );

        if (!empty($data['answers'])) {
            $this->upsertAnswers($submission, $data['answers'], autoScore: false);
        }

        return response()->json([
            'data'    => $submission->load('answers'),
            'message' => 'Draft saved.',
        ]);
    }

    public function submit(Request $request, Assignment $assignment): JsonResponse
    {
        $studentId = auth()->user()->student->id;

        $data = $request->validate([
            'answers'                         => 'required|array',
            'answers.*.question_id'           => 'required|exists:assignment_questions,id',
            'answers.*.answer_text'           => 'nullable|string',
            'answers.*.selected_option_ids'   => 'nullable|array',
            'answers.*.selected_option_ids.*' => 'integer|exists:assignment_question_options,id',
        ]);

        DB::transaction(function () use ($assignment, $studentId, $data, &$submission) {
            $submission = $assignment->submissions()->firstOrCreate(
                ['student_id' => $studentId],
                ['status' => 'draft']
            );

            abort_if($submission->status === 'graded', 422, 'Already graded. Use resubmit instead.');

            $autoScore = $this->upsertAnswers($submission, $data['answers'], autoScore: true);

            $submission->update([
                'status'       => 'submitted',
                'submitted_at' => now(),
                'auto_score'   => $autoScore,
                'total_score'  => $autoScore + $submission->manual_score,
            ]);
        });

        return response()->json([
            'data'    => $submission->load('answers'),
            'message' => 'Assignment submitted.',
        ]);
    }

    public function gradeAnswers(Request $request, Assignment $assignment, AssignmentSubmission $submission): JsonResponse
    {
        abort_if($submission->assignment_id !== $assignment->id, 404);

        $data = $request->validate([
            'answers' => 'nullable|array',
            'answers.*.id' => 'required|exists:assignment_answers,id',
            'answers.*.score' => 'required|numeric|min:0',
            'feedback' => 'nullable|string',
        ]);

        DB::transaction(function () use ($submission, $data) {
            if (!empty($data['answers'])) {
                foreach ($data['answers'] as $item) {
                    $answer = AssignmentAnswer::find($item['id']);
                    abort_if($answer->submission_id !== $submission->id, 403);

                    if (!$answer->question->isObjective()) {
                        $maxPoints = $answer->question->points;
                        $score = min($item['score'], $maxPoints);
                        $answer->update(['manual_score' => $score]);
                    }
                }
            }

            $this->recomputeSubmissionScore($submission);

            $submission->update([
                'status'     => 'graded',
                'graded_at'  => now(),
                'graded_by' => auth()->user()->teacher?->id,
                'feedback'   => $data['feedback'] ?? $submission->feedback,
            ]);
        });

        return response()->json([
            'data'    => $submission->fresh()->load('answers'),
            'message' => 'Submission graded.',
        ]);
    }

    public function pushToGradebook(Assignment $assignment, AssignmentSubmission $submission): JsonResponse
    {
        abort_if($submission->assignment_id !== $assignment->id, 404);

        if ($submission->status !== 'graded') {
            return response()->json([
                'message' => 'Submission must be graded before pushing to grade book.',
            ], 422);
        }

        if (!$assignment->grading_component_id || !$assignment->quarter) {
            return response()->json([
                'message' => 'Assignment must have a grading component and quarter assigned before pushing.',
            ], 422);
        }

        // Convert raw score to percentage (0-100)
        $percentage = round(($submission->total_score / $assignment->total_points) * 100, 2);

        // Find the student's active enrollment
        $enrollment = Enrollment::where('student_id', $submission->student_id)
            ->where('status', 'active')
            ->latest()
            ->firstOrFail();

        $component = $assignment->gradingComponent;

        DB::transaction(function () use ($enrollment, $assignment, $component, $percentage, $submission) {
            $grade = StudentGrade::updateOrCreate(
                [
                    'enrollment_id'        => $enrollment->id,
                    'subject_id'           => $assignment->subject_id,
                    'grading_component_id' => $assignment->grading_component_id,
                    'quarter'              => $assignment->quarter,
                ],
                [
                    'score'          => $percentage,
                    'weighted_score' => round($percentage * ($component->weight / 100), 2),
                ]
            );

            // Recompute final grade for this enrollment+subject+quarter
            $this->recomputeFinalGrade(
                $enrollment->id,
                $assignment->subject_id,
                $assignment->quarter
            );

            $submission->update([
                'pushed_to_gradebook' => true,
                'pushed_at'           => now(),
            ]);
        });

        return response()->json([
            'message' => 'Score pushed to grade book.',
        ]);
    }

    public function resubmit(Request $request, Assignment $assignment): JsonResponse
    {
        $studentId = auth()->user()->student->id;

        $submission = $assignment->submissions()
            ->where('student_id', $studentId)
            ->firstOrFail();

        DB::transaction(function () use ($submission, $request, $assignment, &$updatedSubmission) {
            // Clear previous answers and scores
            $submission->answers()->delete();

            $data = $request->validate([
                'answers'                         => 'required|array',
                'answers.*.question_id'           => 'required|exists:assignment_questions,id',
                'answers.*.answer_text'           => 'nullable|string',
                'answers.*.selected_option_ids'   => 'nullable|array',
                'answers.*.selected_option_ids.*' => 'integer|exists:assignment_question_options,id',
            ]);

            $autoScore = $this->upsertAnswers($submission, $data['answers'], autoScore: true);

            $submission->update([
                'status'              => 'submitted',
                'submitted_at'        => now(),
                'auto_score'          => $autoScore,
                'manual_score'        => 0,
                'total_score'         => $autoScore,
                'score'               => null,
                'feedback'            => null,
                'graded_at'           => null,
                'graded_by'           => null,
                'pushed_to_gradebook' => false,
                'pushed_at'           => null,
            ]);

            $updatedSubmission = $submission;
        });

        return response()->json([
            'data'    => $updatedSubmission->load('answers'),
            'message' => 'Resubmitted successfully.',
        ]);
    }

    public function getSummary(Assignment $assignment): JsonResponse
    {
        $total     = $assignment->submissions()->count();
        $submitted = $assignment->submissions()->where('status', 'submitted')->count();
        $graded    = $assignment->submissions()->where('status', 'graded')->count();
        $pushed    = $assignment->submissions()->where('pushed_to_gradebook', true)->count();

        return response()->json([
            'data' => [
                'total_submissions' => $total,
                'submitted'         => $submitted,
                'graded'            => $graded,
                'pushed_to_gradebook' => $pushed,
                'pending_grading'   => $submitted,
                'submission_rate'   => $total > 0 ? round(($submitted / $total) * 100, 2) : 0,
                'grading_rate'      => $total > 0 ? round(($graded / $total) * 100, 2) : 0,
            ],
        ]);
    }

    // --- Private helpers ---

    private function upsertAnswers(AssignmentSubmission $submission, array $answers, bool $autoScore): float
    {
        $totalAutoScore = 0;

        foreach ($answers as $item) {
            $answer = AssignmentAnswer::updateOrCreate(
                [
                    'submission_id' => $submission->id,
                    'question_id'   => $item['question_id'],
                ],
                [
                    'answer_text'         => $item['answer_text'] ?? null,
                    'selected_option_ids' => $item['selected_option_ids'] ?? null,
                ]
            );

            if ($autoScore) {
                $score = $this->computeAutoScore($answer);
                $answer->update(['auto_score' => $score]);
                $totalAutoScore += $score;
            }
        }

        return round($totalAutoScore, 2);
    }

    private function computeAutoScore(AssignmentAnswer $answer): float
    {
        $question = $answer->question;

        if (!$question->isObjective()) {
            return 0; // teacher grades short_answer/paragraph manually
        }

        $correctIds = $question->options()
            ->where('is_correct', true)
            ->pluck('id')
            ->sort()
            ->values()
            ->toArray();

        $selectedIds = collect($answer->selected_option_ids ?? [])
            ->sort()
            ->values()
            ->toArray();

        // All-or-nothing: full points only if selection matches correct answers exactly
        return $selectedIds === $correctIds ? (float) $question->points : 0.0;
    }

    private function recomputeSubmissionScore(AssignmentSubmission $submission): void
    {
        $answers = $submission->answers()->get();

        $autoScore   = $answers->sum('auto_score');
        $manualScore = $answers->sum('manual_score');

        $submission->update([
            'auto_score'   => round($autoScore, 2),
            'manual_score' => round($manualScore, 2),
            'total_score'  => round($autoScore + $manualScore, 2),
        ]);
    }

    private function recomputeFinalGrade(int $enrollmentId, int $subjectId, int $quarter): void
    {
        $grades    = StudentGrade::where('enrollment_id', $enrollmentId)
            ->where('subject_id', $subjectId)
            ->where('quarter', $quarter)
            ->get();

        $finalGrade = round($grades->sum('weighted_score'), 2);

        StudentGrade::where('enrollment_id', $enrollmentId)
            ->where('subject_id', $subjectId)
            ->where('quarter', $quarter)
            ->update([
                'final_grade' => $finalGrade,
                'is_failing'  => $finalGrade < 75,
            ]);
    }
}
