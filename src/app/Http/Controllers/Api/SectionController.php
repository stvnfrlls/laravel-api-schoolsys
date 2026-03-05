<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\GradeLevel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SectionController extends Controller
{

    public function index(Request $request): JsonResponse
    {
        $sections = Section::with('gradeLevel')
            ->when($request->grade_level_id, fn($q, $id) => $q->forGrade($id))
            ->orderBy('grade_level_id')
            ->orderBy('name')
            ->get();

        return response()->json($sections);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'grade_level_id' => 'required|exists:grade_levels,id',
            'name' => 'required|string|max:50',
            'room' => 'nullable|string|max:50',
            'capacity' => 'nullable|integer|min:1|max:100',
        ]);

        $exists = Section::where('grade_level_id', $data['grade_level_id'])
            ->where('name', $data['name'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => "A section named '{$data['name']}' already exists in this grade level.",
            ], 422);
        }

        $grade = GradeLevel::findOrFail($data['grade_level_id']);
        if (!$grade->is_active) {
            return response()->json([
                'message' => "Cannot add a section to an inactive grade level.",
            ], 422);
        }

        $section = Section::create($data);

        return response()->json($section->load('gradeLevel'), 201);
    }

    public function show(Section $section): JsonResponse
    {
        return response()->json($section->load('gradeLevel'));
    }

    public function update(Request $request, Section $section): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:50',
            'room' => 'sometimes|nullable|string|max:50',
            'capacity' => 'sometimes|nullable|integer|min:1|max:100',
        ]);

        if (isset($data['name']) && $data['name'] !== $section->name) {
            $exists = Section::where('grade_level_id', $section->grade_level_id)
                ->where('name', $data['name'])
                ->where('id', '!=', $section->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => "A section named '{$data['name']}' already exists in this grade level.",
                ], 422);
            }
        }

        $section->update($data);

        return response()->json($section->load('gradeLevel'));
    }

    public function activate(Section $section): JsonResponse
    {
        if (!$section->gradeLevel->is_active) {
            return response()->json([
                'message' => 'Cannot activate a section whose grade level is inactive.',
            ], 422);
        }

        $section->update(['is_active' => true]);

        return response()->json(['message' => "{$section->name} activated."]);
    }

    public function deactivate(Section $section): JsonResponse
    {
        $section->update(['is_active' => false]);

        return response()->json(['message' => "{$section->name} deactivated."]);
    }

    public function destroy(Section $section): JsonResponse
    {
        $section->delete();

        return response()->json(['message' => "{$section->name} deleted."]);
    }
}