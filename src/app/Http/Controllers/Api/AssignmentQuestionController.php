<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentQuestionController extends Controller
{
    public function index(Assignment $assignment): JsonResponse
    {
        return response()->json([
            'data' => $assignment->questions()->with('options')->get(),
        ]);
    }

    public function store(Request $request, Assignment $assignment): JsonResponse
    {
        $data = $request->validate([
            'type'                        => 'required|in:multiple_choice,checkbox,short_answer,paragraph',
            'question_text'               => 'required|string',
            'points'                      => 'required|numeric|min:0',
            'order'                       => 'nullable|integer|min:0',
            'is_required'                 => 'nullable|boolean',
            'options'                     => 'required_if:type,multiple_choice,checkbox|array|min:2',
            'options.*.option_text'       => 'required|string',
            'options.*.is_correct'        => 'required|boolean',
            'options.*.order'             => 'nullable|integer|min:0',
        ]);

        $this->validateCorrectOptions($data);

        $question = $assignment->questions()->create([
            'type'          => $data['type'],
            'question_text' => $data['question_text'],
            'points'        => $data['points'],
            'order'         => $data['order'] ?? $assignment->questions()->count(),
            'is_required'   => $data['is_required'] ?? true,
        ]);

        if (!empty($data['options'])) {
            $question->options()->createMany($data['options']);
        }

        return response()->json([
            'data'    => $question->load('options'),
            'message' => 'Question created.',
        ], 201);
    }

    public function update(Request $request, Assignment $assignment, AssignmentQuestion $question): JsonResponse
    {
        abort_if($question->assignment_id !== $assignment->id, 404);

        $data = $request->validate([
            'question_text'               => 'sometimes|string',
            'points'                      => 'sometimes|numeric|min:0',
            'order'                       => 'sometimes|integer|min:0',
            'is_required'                 => 'sometimes|boolean',
            'options'                     => 'sometimes|array|min:2',
            'options.*.id'                => 'nullable|exists:assignment_question_options,id',
            'options.*.option_text'       => 'required_with:options|string',
            'options.*.is_correct'        => 'required_with:options|boolean',
            'options.*.order'             => 'nullable|integer|min:0',
        ]);

        $question->update($data);

        if (isset($data['options'])) {
            // Replace all options
            $question->options()->delete();
            $question->options()->createMany($data['options']);
        }

        return response()->json([
            'data'    => $question->fresh()->load('options'),
            'message' => 'Question updated.',
        ]);
    }

    public function destroy(Assignment $assignment, AssignmentQuestion $question): JsonResponse
    {
        abort_if($question->assignment_id !== $assignment->id, 404);

        $question->delete();

        return response()->json(['message' => 'Question deleted.']);
    }

    public function reorder(Request $request, Assignment $assignment): JsonResponse
    {
        $data = $request->validate([
            'order'    => 'required|array',
            'order.*'  => 'integer|exists:assignment_questions,id',
        ]);

        foreach ($data['order'] as $index => $questionId) {
            AssignmentQuestion::where('id', $questionId)
                ->where('assignment_id', $assignment->id)
                ->update(['order' => $index]);
        }

        return response()->json(['message' => 'Questions reordered.']);
    }

    // --- Helpers ---

    private function validateCorrectOptions(array $data): void
    {
        if (!in_array($data['type'], ['multiple_choice', 'checkbox'])) {
            return;
        }

        $correctCount = collect($data['options'] ?? [])->where('is_correct', true)->count();

        if ($data['type'] === 'multiple_choice' && $correctCount !== 1) {
            abort(422, 'Multiple choice questions must have exactly one correct answer.');
        }

        if ($data['type'] === 'checkbox' && $correctCount < 1) {
            abort(422, 'Checkbox questions must have at least one correct answer.');
        }
    }
}