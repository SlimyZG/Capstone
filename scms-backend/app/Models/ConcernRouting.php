<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConcernRouting extends Model
{
    use HasFactory;

    protected $table = 'concern_routing';

    protected $fillable = [
        'concern_id',
        'action',
        'performed_by',
        'performed_by_name',
        'notes',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = ['actor_name'];

    /**
     * Boot the model.
     * Automatically capture the performer's name as an immutable snapshot.
     */
    protected static function booted(): void
    {
        static::creating(function (ConcernRouting $routing) {
            if (empty($routing->performed_by_name) && !empty($routing->performed_by)) {
                $user = User::find($routing->performed_by);
                if ($user) {
                    $routing->performed_by_name = $user->name;
                }
            }
        });
    }

    /**
     * Get the concern this routing entry belongs to.
     */
    public function concern(): BelongsTo
    {
        return $this->belongsTo(Concern::class, 'concern_id', 'concern_id');
    }

    /**
     * Get the user who performed this action.
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Get the display name of the actor who performed the action.
     * Preserves identity even if user account was deleted.
     */
    public function getActorNameAttribute(): string
    {
        return $this->performer?->name ?? $this->performed_by_name ?? 'System/Administrator';
    }
}
