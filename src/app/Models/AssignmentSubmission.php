<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssignmentSubmission extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'assignment_id',
        'student_id',
        'submitted_at',
        'status',
        'auto_score',
        'manual_score',
        'total_score',
        'score',
        'feedback',
        'graded_at',
        'graded_by',
        'pushed_to_gradebook',
        'pushed_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
        'pushed_at' => 'datetime',
        'pushed_to_gradebook' => 'boolean',
        'auto_score' => 'decimal:2',
        'manual_score' => 'decimal:2',
        'total_score' => 'decimal:2',
    ];


    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AssignmentAnswer::class, 'submission_id');
    }
}
