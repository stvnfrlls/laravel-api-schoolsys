<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'enrollment_id',
        'subject_id',
        'date',
        'status',
        'remarks',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    // Absence threshold — flag student if absences meet or exceed this
    const ABSENCE_THRESHOLD = 5;

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopePresent(Builder $query): Builder
    {
        return $query->where('status', 'present');
    }

    public function scopeAbsent(Builder $query): Builder
    {
        return $query->where('status', 'absent');
    }

    public function scopeLate(Builder $query): Builder
    {
        return $query->where('status', 'late');
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('date', $date);
    }

    public function scopeForSubject(Builder $query, int $subjectId): Builder
    {
        return $query->where('subject_id', $subjectId);
    }

    public function scopeExcessiveAbsences(Builder $query, int $threshold = self::ABSENCE_THRESHOLD): Builder
    {
        return $query->select('enrollment_id')
            ->where('status', 'absent')
            ->groupBy('enrollment_id')
            ->havingRaw('COUNT(*) >= ?', [$threshold]);
    }
}