<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GradeLevel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class GradeLevelController extends Controller
{
    const CACHE_KEY = 'grade_levels.all';
    const CACHE_TTL = 3600; // 1 hour

    public function index(): JsonResponse
    {
        $grades = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return GradeLevel::withCount('sections')
                ->ordered()
                ->get();
        });

        return response()->json($grades);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:50',
            'level' => 'required|integer|min:1|max:13|unique:grade_levels,level',
        ]);

        $grade = GradeLevel::create($data);
        Cache::forget(self::CACHE_KEY);

        return response()->json($grade, 201);
    }

    public function show(GradeLevel $gradeLevel): JsonResponse
    {
        $gradeLevel->load('sections');

        return response()->json($gradeLevel);
    }

    public function update(Request $request, GradeLevel $gradeLevel): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:50',
            'level' => "sometimes|integer|min:1|max:13|unique:grade_levels,level,{$gradeLevel->id}",
        ]);

        $gradeLevel->update($data);
        Cache::forget(self::CACHE_KEY);

        return response()->json($gradeLevel);
    }

    public function activate(GradeLevel $gradeLevel): JsonResponse
    {
        $gradeLevel->update(['is_active' => true]);
        Cache::forget(self::CACHE_KEY);

        return response()->json(['message' => "{$gradeLevel->name} activated."]);
    }

    public function deactivate(GradeLevel $gradeLevel): JsonResponse
    {
        $gradeLevel->update(['is_active' => false]);

        $gradeLevel->sections()->update(['is_active' => false]);
        Cache::forget(self::CACHE_KEY);

        return response()->json(['message' => "{$gradeLevel->name} and its sections deactivated."]);
    }

    public function destroy(GradeLevel $gradeLevel): JsonResponse
    {
        if ($gradeLevel->activeSections()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a grade level with active sections. Deactivate first.',
            ], 422);
        }

        $gradeLevel->delete();
        Cache::forget(self::CACHE_KEY);

        return response()->json(['message' => "{$gradeLevel->name} deleted."]);
    }
}
