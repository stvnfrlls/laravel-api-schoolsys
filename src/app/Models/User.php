<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $role): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains('name', $role);
        }

        return $this->roles()->where('name', $role)->exists();
    }

    public function hasAnyRole(array $roles): bool
    {
        return $this->roles->whereIn('name', $roles)->isNotEmpty();
    }

    public function assignRole(...$roles): static
    {
        foreach ($roles as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $this->roles()->syncWithoutDetaching([$role->id]);
        }

        if ($this->hasRole('student') && !$this->student()->exists()) {
            Student::create([
                'user_id' => $this->id,
                'student_number' => 'STU-' . strtoupper(substr(md5($this->id . now()), 0, 8)),
                'first_name' => 'Unknown',
                'last_name' => 'Unknown',
                'date_of_birth' => Carbon::now()->subYears(18)->toDateString(),
                'gender' => 'other',
            ]);
        }

        if ($this->hasRole('faculty') && !$this->teacher()->exists()) {
            Teacher::create([
                'user_id' => $this->id,
                'employee_number' => 'EMP-' . strtoupper(substr(md5($this->id . now()), 0, 8)),
                'first_name' => 'Unknown',
                'last_name' => 'Unknown',
                'date_of_birth' => Carbon::now()->subYears(30)->toDateString(),
                'gender' => 'other',
            ]);
        }

        return $this;
    }

    public function removeRole(string $roleName): void
    {
        $role = Role::where('name', $roleName)->first();
        if ($role) {
            $this->roles()->detach($role);
        }
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function address(): HasOne
    {
        return $this->hasOne(UserAddress::class);
    }

    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'teacher_id');
    }
}