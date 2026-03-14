<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Enrollment;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    // ---------------------------------------------------------------
    // GET /api/attendance
    // Filters: enrollment_id, subject_id, date, status
    // ---------------------------------------------------------------
    public function index(Request $request)
    {
        $query = Attendance::with(['enrollment.student', 'subject']);

        if ($request->filled('enrollment_id')) {
            $query->where('enrollment_id', $request->enrollment_id);
        }

        if ($request->filled('subject_id')) {
            $query->forSubject($request->subject_id);
        }

        if ($request->filled('date')) {
            $query->forDate($request->date);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->orderBy('date', 'desc')->get());
    }

    // ---------------------------------------------------------------
    // POST /api/attendance
    // ---------------------------------------------------------------
    public function store(Request $request)
    {
        $validated = $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'date' => 'required|date',
            'status' => 'required|in:present,absent,late',
            'remarks' => 'nullable|string|max:255',
        ]);

        $attendance = Attendance::updateOrCreate(
            [
                'enrollment_id' => $validated['enrollment_id'],
                'subject_id' => $validated['subject_id'] ?? null,
                'date' => $validated['date'],
            ],
            [
                'status' => $validated['status'],
                'remarks' => $validated['remarks'] ?? null,
            ]
        );

        return response()->json($attendance->load(['enrollment.student', 'subject']), 201);
    }

    // ---------------------------------------------------------------
    // GET /api/attendance/{attendance}
    // ---------------------------------------------------------------
    public function show(Attendance $attendance)
    {
        return response()->json($attendance->load(['enrollment.student', 'subject']));
    }

    // ---------------------------------------------------------------
    // PUT /api/attendance/{attendance}
    // ---------------------------------------------------------------
    public function update(Request $request, Attendance $attendance)
    {
        $validated = $request->validate([
            'status' => 'required|in:present,absent,late',
            'remarks' => 'nullable|string|max:255',
        ]);

        $attendance->update($validated);

        return response()->json($attendance->fresh()->load(['enrollment.student', 'subject']));
    }

    // ---------------------------------------------------------------
    // DELETE /api/attendance/{attendance}
    // ---------------------------------------------------------------
    public function destroy(Attendance $attendance)
    {
        $attendance->delete();
        return response()->json(['message' => 'Attendance record deleted.']);
    }

    // ---------------------------------------------------------------
    // GET /api/attendance/summary/{enrollment}
    // Returns count of present, absent, late for a given enrollment
    // ---------------------------------------------------------------
    public function summary(Enrollment $enrollment)
    {
        $records = Attendance::where('enrollment_id', $enrollment->id)->get();

        $total = $records->count();
        $present = $records->where('status', 'present')->count();
        $absent = $records->where('status', 'absent')->count();
        $late = $records->where('status', 'late')->count();

        return response()->json([
            'enrollment_id' => $enrollment->id,
            'student' => $enrollment->student,
            'total' => $total,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'is_flagged' => $absent >= Attendance::ABSENCE_THRESHOLD,
        ]);
    }

    // ---------------------------------------------------------------
    // GET /api/attendance/flagged
    // Returns all enrollments with excessive absences
    // ---------------------------------------------------------------
    public function flagged()
    {
        $flaggedEnrollmentIds = Attendance::excessiveAbsences()
            ->pluck('enrollment_id');

        // Get all absence counts in ONE query
        $absenceCounts = Attendance::whereIn('enrollment_id', $flaggedEnrollmentIds)
            ->where('status', 'absent')
            ->groupBy('enrollment_id')
            ->selectRaw('enrollment_id, COUNT(*) as absence_count')
            ->pluck('absence_count', 'enrollment_id'); // keyed by enrollment_id

        $enrollments = Enrollment::with(['student', 'section'])
            ->whereIn('id', $flaggedEnrollmentIds)
            ->get()
            ->map(function ($enrollment) use ($absenceCounts) {
                return array_merge($enrollment->toArray(), [
                    'absence_count' => $absenceCounts[$enrollment->id] ?? 0,
                ]);
            });

        return response()->json($enrollments);
    }
}