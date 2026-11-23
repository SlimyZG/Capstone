<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SelfServiceKbEntry extends Model
{
    protected $table = 'self_service_kb_entries';

    public const LIFECYCLE_STAGES = [
        'Draft Extracted',
        'Under Office Review',
        'Approved',
        'Published',
        'Needs Revision',
        'Archived / Outdated',
    ];

    public const APPROVAL_STATUSES = [
        'Pending UCLM Validation',
        'Verbal Confirmation Pending Formal Approval',
        'Approved',
        'Needs Revision',
        'Rejected',
    ];

    public const PUBLICATION_STATUSES = [
        'Unpublished',
        'Published',
        'Archived',
    ];

    public const CATEGORIES = [
        'Academic',
        'Enrollment',
        'Financial',
        'Student ID',
        'Library',
        'Facilities',
        'Technology',
        'Student Services',
        'Medical',
        'Guidance',
        'Safety and Security',
        'Other Concerns',
    ];

    protected $fillable = [
        'entry_code',
        'title',
        'category',
        'manual_category',
        'common_questions',
        'approved_answer',
        'steps',
        'requirements',
        'responsible_office',
        'primary_review_office',
        'secondary_review_offices',
        'source_reference',
        'manual_page_number',
        'is_sensitive',
        'sensitive_pathway_type',
        'validation_needed',
        'lifecycle_stage',
        'approval_status',
        'publication_status',
        'validated_by',
        'validated_role',
        'validated_office',
        'validated_at',
        'validation_notes',
        'supporting_reference',
        'published_by',
        'published_at',
        'approval_recorded_by',
        'version',
        'revision_summary',
    ];

    protected function casts(): array
    {
        return [
            'common_questions' => 'array',
            'steps' => 'array',
            'requirements' => 'array',
            'secondary_review_offices' => 'array',
            'validation_needed' => 'array',
            'is_sensitive' => 'boolean',
            'validated_at' => 'datetime',
            'published_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    /**
     * Scope to strictly retrieve only university-approved AND SAO-published entries.
     */
    public function scopePublishedForStudents($query)
    {
        return $query->where('approval_status', 'Approved')
            ->where('publication_status', 'Published')
            ->where('lifecycle_stage', 'Published');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(SelfServiceKbRevision::class, 'kb_entry_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(SelfServiceTask::class, 'kb_entry_id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(SelfServiceFeedback::class, 'kb_entry_id');
    }

    public function isPublishedForStudents(): bool
    {
        return $this->approval_status === 'Approved'
            && $this->publication_status === 'Published'
            && $this->lifecycle_stage === 'Published';
    }
}
