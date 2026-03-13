<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GradeLevel;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'in:active,inactive,all'],
            'grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $subjects = Subject::with('gradeLevels')
            ->when($request->status === 'active', fn($q) => $q->active())
            ->when($request->status === 'inactive', fn($q) => $q->inactive())
            ->when($request->grade_level_id, fn($q) => $q->whereHas(
                'gradeLevels',
                fn($q) => $q->where('grade_level_id', $request->grade_level_id)
            ))
            ->orderBy('name')
            ->paginate($request->input('per_page', 15));

        return response()->json($subjects);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:20', 'alpha_num', 'unique:subjects,code'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $data['is_active'] ?? true;

        $subject = Subject::create($data);

        $defaults = [
            ['name' => 'Task', 'code' => 'T', 'weight' => 40.00],
            ['name' => 'Quarterly Exam', 'code' => 'QE', 'weight' => 30.00],
            ['name' => 'Final Exam', 'code' => 'FE', 'weight' => 30.00],
        ];

        foreach ($defaults as $component) {
            $subject->gradingComponents()->create([
                ...$component,
                'is_active' => true,
            ]);
        }

        return response()->json($subject, 201);
    }

    public function show(Subject $subject): JsonResponse
    {
        return response()->json($subject->load('gradeLevels'));
    }

    public function update(Request $request, Subject $subject): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'code' => ['sometimes', 'string', 'max:20', 'alpha_num'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);

            // Ignore current subject ID
            if (
                Subject::whereRaw('UPPER(code) = ?', [$data['code']])
                    ->where('id', '!=', $subject->id)
                    ->exists()
            ) {
                return response()->json(['errors' => ['code' => ['The code has already been taken.']]], 422);
            }
        }

        $subject->update($data);

        return response()->json($subject->fresh());
    }

    public function destroy(Subject $subject): JsonResponse
    {
        if ($subject->gradeLevels()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a subject assigned to a grade level.',
            ], 422);
        }

        $subject->delete();

        return response()->json(['message' => 'Subject deleted successfully.']);
    }

    public function activate(Subject $subject): JsonResponse
    {
        $subject->activate();

        return response()->json($subject->fresh());
    }

    public function deactivate(Subject $subject): JsonResponse
    {
        $subject->deactivate();

        return response()->json($subject->fresh());
    }

    public function assignToGradeLevel(Request $request, Subject $subject): JsonResponse
    {
        $data = $request->validate([
            'grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
            'units' => ['required', 'numeric', 'min:0', 'max:99.9'],
            'hours_per_week' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        if (!$subject->is_active) {
            return response()->json([
                'message' => 'Cannot assign an inactive subject to a grade level.',
            ], 422);
        }

        $subject->gradeLevels()->syncWithoutDetaching([
            $data['grade_level_id'] => [
                'units' => $data['units'],
                'hours_per_week' => $data['hours_per_week'],
            ],
        ]);

        return response()->json($subject->load('gradeLevels'));
    }

    public function removeFromGradeLevel(Subject $subject, GradeLevel $gradeLevel): JsonResponse
    {
        $subject->gradeLevels()->detach($gradeLevel->id);

        return response()->json(['message' => "Subject removed from {$gradeLevel->name}."]);
    }
}