<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentGrade extends Model
{
    use HasFactory;

    protected $fillable = [
        'enrollment_id',
        'subject_id',
        'grading_component_id',
        'quarter',
        'score',
        'weighted_score',
        'final_grade',
        'is_failing',
    ];

    protected $casts = [
        'is_failing' => 'boolean',
        'score' => 'decimal:2',
        'weighted_score' => 'decimal:2',
        'final_grade' => 'decimal:2',
    ];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function gradingComponent()
    {
        return $this->belongsTo(GradingComponent::class);
    }
}