<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'section_id',
        'grade_level_id',
        'school_year',
        'semester',
        'status',
        'enrolled_at',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    /**
     * The student profile (students.id) — NOT the User directly.
     * To get the user: $enrollment->student->user
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForPeriod($query, string $schoolYear, string $semester)
    {
        return $query->where('school_year', $schoolYear)
            ->where('semester', $semester);
    }
}