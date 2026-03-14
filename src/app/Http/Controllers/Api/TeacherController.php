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

    // ---------------------------------------------------------------
    // Teacher self-service routes — no caching, always real-time
    // ---------------------------------------------------------------

    // GET /api/teacher/schedule
    public function mySchedule(Request $request): JsonResponse
    {
        $query = Schedule::with(['subject', 'section.gradeLevel'])
            ->where('teacher_id', $request->user()->id);

        if ($request->filled('school_year')) {
            $query->where('school_year', $request->school_year);
        }

        if ($request->filled('semester')) {
            $query->where('semester', $request->semester);
        }

        if ($request->filled('day')) {
            $query->forDay($request->day);
        }

        return response()->json(ScheduleResource::collection($query->orderBy('day')->orderBy('start_time')->get()));
    }

    // GET /api/teacher/subjects
    public function mySubjects(Request $request): JsonResponse
    {
        $schedules = Schedule::with(['subject', 'section.gradeLevel'])
            ->where('teacher_id', $request->user()->id);

        if ($request->filled('school_year')) {
            $schedules->where('school_year', $request->school_year);
        }

        if ($request->filled('semester')) {
            $schedules->where('semester', $request->semester);
        }

        $grouped = $schedules->get()
            ->groupBy('subject_id')
            ->map(function ($rows) {
                $first = $rows->first();
                return [
                    'subject' => $first->subject,
                    'sections' => $rows->pluck('section')->unique('id')->values(),
                ];
            })
            ->values();

        return response()->json($grouped);
    }

    private function clearCache(): void
    {
        $prefix = config('cache.prefix');

        \DB::table('cache')
            ->where('key', 'like', $prefix . 'teachers.index.page.%')
            ->delete();
    }
}