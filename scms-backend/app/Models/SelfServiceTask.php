<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SelfServiceTask extends Model
{
    protected $table = 'self_service_tasks';

    protected $fillable = [
        'student_id',
        'kb_entry_id',
        'entry_code',
        'kb_version',
        'task_title',
        'category',
        'responsible_office',
        'source_reference',
        'manual_page_number',
        'steps_snapshot',
        'requirements_snapshot',
        'completed_step_ids',
        'blocked_steps',
        'total_steps',
        'completed_steps_count',
        'progress_percentage',
        'status',
        'student_notes',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'steps_snapshot' => 'array',
            'requirements_snapshot' => 'array',
            'completed_step_ids' => 'array',
            'blocked_steps' => 'array',
            'kb_version' => 'integer',
            'total_steps' => 'integer',
            'completed_steps_count' => 'integer',
            'progress_percentage' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function kbEntry(): BelongsTo
    {
        return $this->belongsTo(SelfServiceKbEntry::class, 'kb_entry_id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(SelfServiceFeedback::class, 'task_id');
    }
}
