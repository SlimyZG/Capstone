<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringIncident extends Model
{
    use HasFactory;

    protected $table = 'recurring_incidents';

    protected $fillable = [
        'incident_code',
        'title',
        'summary',
        'issue_category',
        'issue_subcategory',
        'workflow_type',
        'academic_unit_id',
        'dept_id',
        'status',
        'priority',
        'recurrence_tier',
        'is_priority_overridden',
        'priority_override_reason',
        'priority_overridden_by',
        'priority_overridden_at',
        'unique_students_count',
        'total_reports_count',
        'cluster_signature',
        'first_reported_at',
        'latest_reported_at',
        'escalated_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'is_priority_overridden' => 'boolean',
            'priority_overridden_at' => 'datetime',
            'first_reported_at'      => 'datetime',
            'latest_reported_at'     => 'datetime',
            'escalated_at'           => 'datetime',
            'resolved_at'            => 'datetime',
            'unique_students_count'  => 'integer',
            'total_reports_count'    => 'integer',
        ];
    }

    /**
     * Get the individual concerns linked to this recurring incident.
     */
    public function concerns(): HasMany
    {
        return $this->hasMany(Concern::class, 'recurring_incident_id', 'id');
    }

    /**
     * Get the academic unit for this incident (academic workflow).
     */
    public function academicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class, 'academic_unit_id');
    }

    /**
     * Get the department for this incident (non-academic workflow).
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'dept_id');
    }

    /**
     * Get the user who manually overrode the priority.
     */
    public function priorityOverriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'priority_overridden_by');
    }

    /**
     * Get the historical audit log for this recurring incident.
     */
    public function history(): HasMany
    {
        return $this->hasMany(RecurringIncidentHistory::class, 'recurring_incident_id', 'id');
    }
}
