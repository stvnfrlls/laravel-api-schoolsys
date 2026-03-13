<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_id',
        'subject_id',
        'teacher_id',
        'day',
        'start_time',
        'end_time',
        'school_year',
        'semester',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id', 'user_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeForPeriod($query, string $schoolYear, string $semester)
    {
        return $query->where('school_year', $schoolYear)
            ->where('semester', $semester);
    }

    public function scopeForDay($query, string $day)
    {
        return $query->where('day', $day);
    }
}