<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConcernTransferRequest extends Model
{
    use HasFactory;

    protected $table = 'concern_transfer_requests';

    protected $fillable = [
        'concern_id',
        'from_dept_id',
        'from_academic_unit_id',
        'from_office_name',
        'to_dept_id',
        'to_academic_unit_id',
        'to_office_name',
        'requested_by',
        'requested_by_name',
        'transfer_reason',
        'handoff_summary',
        'transfer_sequence',
        'is_reversal',
        'reversal_warning',
        'status',
        'decided_by',
        'decided_by_name',
        'decision_notes',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'transfer_sequence' => 'integer',
            'is_reversal' => 'boolean',
            'decided_at' => 'datetime',
        ];
    }

    public function concern(): BelongsTo
    {
        return $this->belongsTo(Concern::class, 'concern_id', 'concern_id');
    }

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_dept_id');
    }

    public function fromAcademicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class, 'from_academic_unit_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_dept_id');
    }

    public function toAcademicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class, 'to_academic_unit_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
