<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubjectResource;
use App\Models\GradeLevel;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class SubjectController extends Controller
{
    const CACHE_TTL = 1800; // 30 minutes

    private function indexCacheKey(Request $request): string
    {
        $params = [
            'status' => $request->input('status', 'all'),
            'grade_level_id' => $request->input('grade_level_id', 'all'),
            'per_page' => $request->input('per_page', 15),
            'page' => $request->input('page', 1),
        ];

        return 'subjects.index.' . md5(serialize($params));
    }

    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', 'in:active,inactive,all'],
            'grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $subjects = Cache::remember($this->indexCacheKey($request), self::CACHE_TTL, function () use ($request) {
            return Subject::with('gradeLevels')
                ->when($request->status === 'active', fn($q) => $q->active())
                ->when($request->status === 'inactive', fn($q) => $q->inactive())
                ->when($request->grade_level_id, fn($q) => $q->whereHas(
                    'gradeLevels',
                    fn($q) => $q->where('grade_level_id', $request->grade_level_id)
                ))
                ->orderBy('name')
                ->paginate($request->input('per_page', 15));
        });

        $subjects->load('gradeLevels');

        return SubjectResource::collection($subjects);
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
            $subject->gradingComponents()->create([...$component, 'is_active' => true]);
        }

        $this->clearCache();

        return response()->json(new SubjectResource($subject), 201);
    }

    public function show(Subject $subject): JsonResponse
    {
        $subject->load(['gradeLevels', 'gradingComponents']);
        return response()->json(new SubjectResource($subject));
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

            if (
                Subject::whereRaw('UPPER(code) = ?', [$data['code']])
                    ->where('id', '!=', $subject->id)
                    ->exists()
            ) {
                return response()->json(['errors' => ['code' => ['The code has already been taken.']]], 422);
            }
        }

        $subject->update($data);
        $this->clearCache();

        return response()->json(new SubjectResource($subject->fresh()));
    }

    public function destroy(Subject $subject): JsonResponse
    {
        if ($subject->gradeLevels()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a subject assigned to a grade level.',
            ], 422);
        }

        $subject->delete();
        $this->clearCache();

        return response()->json(['message' => 'Subject deleted successfully.']);
    }

    public function activate(Subject $subject): JsonResponse
    {
        $subject->activate();
        $this->clearCache();

        return response()->json(new SubjectResource($subject->fresh()));
    }

    public function deactivate(Subject $subject): JsonResponse
    {
        $subject->deactivate();
        $this->clearCache();

        return response()->json(new SubjectResource($subject->fresh()));
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

        $this->clearCache();

        return response()->json(new SubjectResource($subject->load('gradeLevels')));
    }

    public function removeFromGradeLevel(Subject $subject, GradeLevel $gradeLevel): JsonResponse
    {
        $subject->gradeLevels()->detach($gradeLevel->id);
        $this->clearCache();

        return response()->json(['message' => "Subject removed from {$gradeLevel->name}."]);
    }

    // Clears all subject index cache keys by tag pattern
    private function clearCache(): void
    {
        $prefix = config('cache.prefix');

        \DB::table('cache')
            ->where('key', 'like', $prefix . 'subjects.index.%')
            ->delete();
    }
}