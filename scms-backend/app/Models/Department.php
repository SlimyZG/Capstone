<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    /**
     * Get all staff users belonging to this department.
     */
    public function staff(): HasMany
    {
        return $this->hasMany(User::class, 'dept_id');
    }

    /**
     * Get all concerns routed to this department.
     */
    public function concerns(): HasMany
    {
        return $this->hasMany(Concern::class, 'dept_id');
    }
}
