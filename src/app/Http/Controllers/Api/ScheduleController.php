<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class ScheduleController extends Controller
{
    const CACHE_TTL = 600; // 10 minutes

    private function indexCacheKey(Request $request): string
    {
        $params = [
            'section_id' => $request->input('section_id', 'all'),
            'teacher_id' => $request->input('teacher_id', 'all'),
            'subject_id' => $request->input('subject_id', 'all'),
            'day' => $request->input('day', 'all'),
            'school_year' => $request->input('school_year', 'all'),
            'semester' => $request->input('semester', 'all'),
            'page' => $request->input('page', 1),
        ];

        return 'schedules.index.' . md5(serialize($params));
    }

    // GET /schedules
    public function index(Request $request): JsonResponse
    {
        $key = $this->indexCacheKey($request);

        $schedules = Cache::remember($key, self::CACHE_TTL, function () use ($request) {
            $query = Schedule::with(['section', 'subject', 'teacher']);

            if ($request->filled('section_id'))
                $query->where('section_id', $request->section_id);
            if ($request->filled('teacher_id'))
                $query->where('teacher_id', $request->teacher_id);
            if ($request->filled('subject_id'))
                $query->where('subject_id', $request->subject_id);
            if ($request->filled('day'))
                $query->where('day', $request->day);
            if ($request->filled('school_year'))
                $query->where('school_year', $request->school_year);
            if ($request->filled('semester'))
                $query->where('semester', $request->semester);

            return $query->orderBy('day')->orderBy('start_time')->paginate(20);
        });

        return response()->json($schedules);
    }

    // POST /schedules
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'section_id' => ['required', 'exists:sections,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'teacher_id' => ['required', 'exists:users,id'],
            'day' => ['required', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'school_year' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['required', Rule::in(['1st', '2nd', 'summer'])],
        ]);

        $teacher = User::findOrFail($data['teacher_id']);
        if (!$teacher->hasRole('faculty')) {
            return response()->json([
                'message' => 'The specified user does not have the faculty role.',
            ], 422);
        }

        if ($conflict = $this->detectConflict($data)) {
            return response()->json(['message' => $conflict], 422);
        }

        $schedule = Schedule::create($data);
        $this->clearCache();

        return response()->json(
            $schedule->load(['section', 'subject', 'teacher']),
            201
        );
    }

    // GET /schedules/{schedule}
    public function show(Schedule $schedule): JsonResponse
    {
        return response()->json($schedule->load(['section', 'subject', 'teacher']));
    }

    // PUT /schedules/{schedule}
    public function update(Request $request, Schedule $schedule): JsonResponse
    {
        $data = $request->validate([
            'section_id' => ['sometimes', 'exists:sections,id'],
            'subject_id' => ['sometimes', 'exists:subjects,id'],
            'teacher_id' => ['sometimes', 'exists:users,id'],
            'day' => ['sometimes', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i', 'after:start_time'],
            'school_year' => ['sometimes', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['sometimes', Rule::in(['1st', '2nd', 'summer'])],
        ]);

        if (isset($data['teacher_id'])) {
            $teacher = User::findOrFail($data['teacher_id']);
            if (!$teacher->hasRole('faculty')) {
                return response()->json([
                    'message' => 'The specified user does not have the faculty role.',
                ], 422);
            }
        }

        $merged = array_merge($schedule->only([
            'section_id',
            'subject_id',
            'teacher_id',
            'day',
            'start_time',
            'end_time',
            'school_year',
            'semester',
        ]), $data);

        if ($conflict = $this->detectConflict($merged, $schedule->id)) {
            return response()->json(['message' => $conflict], 422);
        }

        $schedule->update($data);
        $this->clearCache();

        return response()->json(
            $schedule->fresh(['section', 'subject', 'teacher'])
        );
    }

    // DELETE /schedules/{schedule}
    public function destroy(Schedule $schedule): JsonResponse
    {
        $schedule->delete();
        $this->clearCache();

        return response()->json(['message' => 'Schedule removed successfully.']);
    }

    // GET /sections/{section}/schedules
    public function bySection(Request $request, Section $section): JsonResponse
    {
        $query = $section->schedules()->with(['subject', 'teacher']);

        if ($request->filled('school_year'))
            $query->where('school_year', $request->school_year);
        if ($request->filled('semester'))
            $query->where('semester', $request->semester);
        if ($request->filled('day'))
            $query->where('day', $request->day);

        return response()->json(
            $query->orderBy('day')->orderBy('start_time')->get()
        );
    }

    private function detectConflict(array $data, ?int $excludeId = null): ?string
    {
        $base = Schedule::where('day', $data['day'])
            ->where('school_year', $data['school_year'])
            ->where('semester', $data['semester'])
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId));

        if ((clone $base)->where('section_id', $data['section_id'])->exists()) {
            return 'The section already has a subject scheduled during this time slot.';
        }

        if ((clone $base)->where('teacher_id', $data['teacher_id'])->exists()) {
            return 'The teacher is already assigned to another class during this time slot.';
        }

        return null;
    }

    private function clearCache(): void
    {
        \DB::table('cache')
            ->where('key', 'like', '%schedules.index.%')
            ->delete();
    }
}