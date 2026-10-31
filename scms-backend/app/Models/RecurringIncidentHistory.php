<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringIncidentHistory extends Model
{
    use HasFactory;

    protected $table = 'recurring_incident_history';

    public $timestamps = false;

    protected $fillable = [
        'recurring_incident_id',
        'action',
        'performed_by',
        'performed_by_name',
        'notes',
        'unique_students_snapshot',
        'created_at',
    ];

    protected $appends = ['actor_name'];

    protected function casts(): array
    {
        return [
            'created_at'               => 'datetime',
            'unique_students_snapshot' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (RecurringIncidentHistory $history) {
            if (empty($history->created_at)) {
                $history->created_at = now();
            }
            if (empty($history->performed_by_name) && !empty($history->performed_by)) {
                $user = User::find($history->performed_by);
                if ($user) {
                    $history->performed_by_name = $user->name;
                }
            }
        });
    }

    /**
     * Get the incident this history entry belongs to.
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(RecurringIncident::class, 'recurring_incident_id');
    }

    /**
     * Get the user who performed this action.
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Get the actor display name (preserves name even after user deletion).
     */
    public function getActorNameAttribute(): string
    {
        return $this->performer?->name ?? $this->performed_by_name ?? 'System/Automated';
    }
}
