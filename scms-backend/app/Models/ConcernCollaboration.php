<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConcernCollaboration extends Model
{
    use HasFactory;

    protected $table = 'concern_collaborations';

    protected $fillable = [
        'concern_id',
        'primary_dept_id',
        'primary_academic_unit_id',
        'primary_office_name',
        'collaborator_dept_id',
        'collaborator_office_name',
        'requested_by',
        'requested_by_name',
        'collaboration_reason',
        'requested_assistance',
        'status',
        'responded_by',
        'responded_by_name',
        'response_notes',
        'accepted_at',
        'declined_at',
        'completed_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'completed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function concern(): BelongsTo
    {
        return $this->belongsTo(Concern::class, 'concern_id', 'concern_id');
    }

    public function primaryDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'primary_dept_id');
    }

    public function primaryAcademicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class, 'primary_academic_unit_id');
    }

    public function collaboratorDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'collaborator_dept_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
