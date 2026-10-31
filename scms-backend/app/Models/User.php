<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'student_id_number',
        'role',
        'academic_unit_id',
        'dept_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
        ];
    }

    /**
     * Get the academic unit this user belongs to.
     */
    public function academicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    /**
     * Get the department this user belongs to (for dept_staff).
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'dept_id');
    }

    /**
     * Get concerns submitted by this user (student).
     */
    public function concerns(): HasMany
    {
        return $this->hasMany(Concern::class, 'student_id');
    }

    /**
     * Get messages sent by this user.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Role helper checks.
     */
    public function isSao(): bool
    {
        return $this->role === 'sao';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isDean(): bool
    {
        return $this->role === 'dean';
    }

    public function isDeptStaff(): bool
    {
        return $this->role === 'dept_staff';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }
}
