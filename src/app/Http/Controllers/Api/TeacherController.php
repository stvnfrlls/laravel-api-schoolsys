<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScheduleResource;
use App\Http\Resources\TeacherResource;
use App\Models\Schedule;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class TeacherController extends Controller
{
    const CACHE_TTL = 600; // 10 minutes

    private function indexCacheKey(int $page = 1): string
    {
        return "teachers.index.page.{$page}";
    }

    // GET /teachers
    public function index()
    {
        $page = (int) request()->input('page', 1);
        $key = $this->indexCacheKey($page);

        $teachers = Cache::remember($key, self::CACHE_TTL, function () {
            return Teacher::with('user')->paginate(20);
        });

        return TeacherResource::collection($teachers);
    }

    // GET /teachers/{teacher}
    public function show(Teacher $teacher): JsonResponse
    {
        return response()->json(new TeacherResource($teacher->load('user', 'schedules')));
    }

    // PUT /teachers/{teacher}
    public function update(Request $request, Teacher $teacher): JsonResponse
    {
        $data = $request->validate([
            'employee_number' => ['sometimes', 'string', Rule::unique('teachers')->ignore($teacher->id)],
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'suffix' => ['sometimes', 'nullable', 'string', 'max:20'],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'gender' => ['sometimes', Rule::in(['male', 'female', 'other'])],
        ]);

        $teacher->update($data);
        $this->clearCache();

        return response()->json(new TeacherResource($teacher->fresh('user')));
    }

    public function myProfile(Request $request): JsonResponse
    {
        $teacher = Teacher::where('user_id', $request->user()->id)->firstOrFail();
        return response()->json(new TeacherResource($teacher));
    }

    private function clearCache(): void
    {
        $prefix = config('cache.prefix');

        \DB::table('cache')
            ->where('key', 'like', $prefix . 'teachers.index.page.%')
            ->delete();
    }
}
