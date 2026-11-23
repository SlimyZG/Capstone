<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelfServiceKbRevision extends Model
{
    protected $table = 'self_service_kb_revisions';

    protected $fillable = [
        'kb_entry_id',
        'version',
        'title',
        'approved_answer',
        'steps',
        'requirements',
        'responsible_office',
        'manual_page_number',
        'lifecycle_stage',
        'approval_status',
        'publication_status',
        'validated_by',
        'validated_role',
        'validated_office',
        'validated_at',
        'supporting_reference',
        'approval_recorded_by',
        'changed_by',
        'change_notes',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'requirements' => 'array',
            'version' => 'integer',
            'validated_at' => 'datetime',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(SelfServiceKbEntry::class, 'kb_entry_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
