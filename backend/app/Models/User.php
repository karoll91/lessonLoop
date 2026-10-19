<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'login', 'email', 'role', 'password', 'blocked_at', 'must_change_password', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Mirrors the column defaults so a freshly created user has them in memory.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'teacher',
        'must_change_password' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'blocked_at' => 'datetime',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'teacher_id');
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    /**
     * @return HasMany<Course, $this>
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'teacher_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Admins see every subject; teachers see the subjects of their courses.
     */
    public function teachesSubject(int $subjectId): bool
    {
        return $this->isAdmin() || $this->courses()->where('subject_id', $subjectId)->exists();
    }

    /**
     * Admins see every group; teachers see the groups of their courses.
     */
    public function teachesGroup(int $studyGroupId): bool
    {
        return $this->isAdmin() || $this->courses()->where('study_group_id', $studyGroupId)->exists();
    }
}
