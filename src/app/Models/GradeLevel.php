<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
}