<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'grade_level_id',
        'name',
        'room',
        'capacity',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'integer',
    ];

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForGrade($query, int $gradeLevelId)
    {
        return $query->where('grade_level_id', $gradeLevelId);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function activeStudents(): HasMany
    {
        return $this->hasMany(Enrollment::class)->where('status', 'active');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}