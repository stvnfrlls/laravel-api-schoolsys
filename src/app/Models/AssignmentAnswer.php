<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentAnswer extends Model
{
    protected $fillable = [
        'submission_id',
        'question_id',
        'answer_text',
        'selected_option_ids',
        'auto_score',
        'manual_score',
    ];

    protected $casts = [
        'selected_option_ids' => 'array',
        'auto_score'          => 'decimal:2',
        'manual_score'        => 'decimal:2',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssignmentSubmission::class, 'submission_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AssignmentQuestion::class, 'question_id');
    }
}
