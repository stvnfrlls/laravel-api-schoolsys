<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function gradeLevels(): BelongsToMany
    {
        return $this->belongsToMany(GradeLevel::class, 'grade_level_subjects')
            ->withPivot(['units', 'hours_per_week'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', strtoupper($code));
    }

    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    public function isAssignedToGradeLevel(int $gradeLevelId): bool
    {
        return $this->gradeLevels()->where('grade_level_id', $gradeLevelId)->exists();
    }

    public function gradingComponents(): HasMany
    {
        return $this->hasMany(GradingComponent::class);
    }
}