<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssignmentQuestion extends Model
{
    protected $fillable = [
        'assignment_id',
        'type',
        'question_text',
        'points',
        'order',
        'is_required',
    ];

    protected $casts = [
        'points'      => 'decimal:2',
        'is_required' => 'boolean',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(AssignmentQuestionOption::class, 'question_id')
            ->orderBy('order');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AssignmentAnswer::class, 'question_id');
    }

    public function isObjective(): bool
    {
        return in_array($this->type, ['multiple_choice', 'checkbox']);
    }
}
