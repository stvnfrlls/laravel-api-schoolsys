<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GradingComponent extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'weight', 'subject_id', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'weight' => 'decimal:2',
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function studentGrades()
    {
        return $this->hasMany(StudentGrade::class);
    }
}