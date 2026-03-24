<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentDetail extends Model
{
    protected $fillable = [
        'assignment_id',
        'description',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }
}
