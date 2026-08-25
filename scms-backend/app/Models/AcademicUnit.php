<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicUnit extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    /**
     * Get all users (deans, students) belonging to this academic unit.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get all concerns associated with this academic unit.
     */
    public function concerns(): HasMany
    {
        return $this->hasMany(Concern::class);
    }
}
