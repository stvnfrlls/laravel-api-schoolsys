<?php

namespace App\Http\Controllers;

use App\Models\GradeLevel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GradeLevelController extends Controller
{

    public function index(): JsonResponse
    {
        $grades = GradeLevel::withCount('sections')
            ->ordered()
            ->get();

        return response()->json($grades);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:50',
            'level' => 'required|integer|min:1|max:13|unique:grade_levels,level',
        ]);

        $grade = GradeLevel::create($data);

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

        return response()->json($gradeLevel);
    }

    public function activate(GradeLevel $gradeLevel): JsonResponse
    {
        $gradeLevel->update(['is_active' => true]);

        return response()->json(['message' => "{$gradeLevel->name} activated."]);
    }

    public function deactivate(GradeLevel $gradeLevel): JsonResponse
    {
        $gradeLevel->update(['is_active' => false]);

        $gradeLevel->sections()->update(['is_active' => false]);

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

        return response()->json(['message' => "{$gradeLevel->name} deleted."]);
    }
}
