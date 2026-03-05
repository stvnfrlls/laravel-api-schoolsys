<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GradeLevel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'level', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'level' => 'integer',
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function activeSections(): HasMany
    {
        return $this->hasMany(Section::class)->where('is_active', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('level');
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'grade_level_subjects')
            ->withPivot(['units', 'hours_per_week'])
            ->withTimestamps();
    }

    public function activeSubjects(): BelongsToMany
    {
        return $this->subjects()->where('is_active', true);
    }
}