<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AssignmentController extends Controller
{
    public function index(): JsonResponse
    {
        $user = auth()->user();

        $assignment = Assignment::with(['details', 'teacher:id,first_name,last_name', 'subject:id,name,code', 'gradeLevel:id,name,level']);

        if ($user->hasRole('faculty')) {
            $assignment->where('teacher_id', $user->teacher->id);
        } else if ($user->hasRole('student')) {
            $assignment->where('gradelevel_id', $user->student->id)
                ->where('is_published', 1);
        }

        $assignment = $assignment->orderBy('created_at', 'desc')->paginate(15);

        return response()->json($assignment);
    }

    public function show(Assignment $assignment): JsonResponse
    {
        $assignment = $assignment->load(['details', 'teacher:id, first_name, last_name', 'subject:id, name', 'gradeLevel:id,name,level']);

        return response()->json([
            'data' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'total_points' => $assignment->total_points,
                'due_date' => $assignment->due_date,
                'is_published' => $assignment->is_published,
                'teacher' => $assignment->teacher,
                'subject' => $assignment->subject,
                'details' => [
                    'description' => $assignment->details?->description,
                    'instructions' => json_decode($assignment->details?->instructions ?? '[]', true),
                ],
                'created_at' => $assignment->created_at,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            "gradelevel_id" => ["required", "exists:grade_levels,id"],
            "subject_id" => ["required", "exists:subjects,id"],
            "teacher_id" => ["required", "exists:teachers,id"],
            "title" => ["required", "string", "max:255"],
            "total_points" => ["required", "integer", "min:1"],
            "due_date" => ["required", "date_format:Y-m-d H:i:s"],
            "description" => ["nullable", "string"],
            "instructions" => ["nullable"],
            "is_published" => ["nullable", "boolean"],
        ]);

        $assignment = Assignment::create([
            "gradelevel_id" => $data["gradelevel_id"],
            "subject_id" => $data["subject_id"],
            "teacher_id" => $data["teacher_id"],
            "title" => $data["title"],
            "total_points" => $data["total_points"],
            "due_date" => $data["due_date"],
            "is_published" => $data["is_published"] ?? false,
        ]);

        $assignment->details()->create([
            "description" => $data["description"] ?? null,
            "instructions" => $data["instructions"] ?? null,
        ]);

        return response()->json([
            'data' => $assignment->load("details")->toArray(),
            'message' => 'Assignment created successfully!',
        ]);
    }

    public function update(Request $request, Assignment $assignment): JsonResponse
    {
        $data = $request->validate([
            "gradelevel_id" => ["required", "exists:grade_levels,id"],
            "subject_id" => ["required", "exists:subjects,id"],
            "teacher_id" => ["required", "exists:teachers,id"],
            "title" => ["required", "string", "max:255"],
            "total_points" => ["required", "integer", "min:1"],
            "due_date" => ["required", "date_format:Y-m-d H:i:s"],
            "description" => ["nullable", "string"],
            "instructions" => ["nullable"],
            "is_published" => ["nullable", "boolean"],
        ]);

        $assignment->update([
            "gradelevel_id" => $data["gradelevel_id"],
            "subject_id" => $data["subject_id"],
            "teacher_id" => $data["teacher_id"],
            "title" => $data["title"],
            "total_points" => $data["total_points"],
            "due_date" => $data["due_date"],
            "is_published" => $data["is_published"],
        ]);

        $assignment->details()->update([
            "description" => $data["description"],
            "instructions" => $data["instructions"],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Assignment updated successfully!',
            'data' => $assignment->fresh()->load("details")->toArray(),
        ]);
    }

    public function destroy(Assignment $assignment): JsonResponse
    {
        $assignment->delete();

        return response()->json([
            'message' => 'Assignment deleted successfully',
        ]);
    }

    public function togglePublish(Assignment $assignment): JsonResponse
    {
        $assignment->update([
            'is_published' => !$assignment->is_published,
        ]);

        return response()->json([
            'data' => $assignment,
            'message' => 'Assignment ' . ($assignment->is_published ? 'published' : 'unpublished') . ' successfully',
        ]);
    }
}
