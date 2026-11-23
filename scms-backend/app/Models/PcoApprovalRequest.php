<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PcoApprovalRequest extends Model
{
    use HasFactory;

    protected $table = 'pco_approval_requests';

    protected $fillable = [
        'concern_id',
        'supersedes_request_id',
        'requesting_dept_id',
        'requesting_office_name',
        'requested_by',
        'requested_by_name',
        'approving_dept_id',
        'approving_academic_unit_id',
        'approving_office_name',
        'approval_reason',
        'proposed_work_description',
        'work_scope',
        'supporting_notes',
        'attachments',
        'status',
        'is_work_authorized',
        'decided_by',
        'decided_by_name',
        'decided_by_role',
        'decision_notes',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'is_work_authorized' => 'boolean',
            'decided_at' => 'datetime',
        ];
    }

    public function concern(): BelongsTo
    {
        return $this->belongsTo(Concern::class, 'concern_id', 'concern_id');
    }

    public function supersededRequest(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_request_id');
    }

    public function requestingDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'requesting_dept_id');
    }

    public function approvingDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'approving_dept_id');
    }

    public function approvingAcademicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class, 'approving_academic_unit_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PcoApprovalEvent::class, 'pco_approval_request_id')->orderBy('created_at', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Return raw stored attachment entries for internal server-side streaming only.
     */
    public function getRawAttachments(): array
    {
        $raw = $this->getAttributeValue('attachments');
        return is_array($raw) ? array_values($raw) : [];
    }

    /**
     * Sanitize JSON serialization so raw private storage disk paths are never exposed to API clients.
     * Exposes only safe attachment metadata (index and original_name).
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        $rawAttachments = $this->getRawAttachments();

        if (empty($rawAttachments)) {
            $array['attachments'] = [];
        } else {
            $safe = [];
            foreach ($rawAttachments as $idx => $entry) {
                if (is_array($entry)) {
                    $name = (string) ($entry['original_name'] ?? basename((string) ($entry['path'] ?? 'attachment')));
                } else {
                    $base = basename((string) $entry);
                    $clean = preg_replace('/^pco_\d+_c?\d+_[a-f0-9]+_/i', '', $base);
                    $name = $clean ?: $base;
                }
                $safe[] = [
                    'index' => (int) $idx,
                    'original_name' => $name,
                ];
            }
            $array['attachments'] = $safe;
        }

        return $array;
    }
}
