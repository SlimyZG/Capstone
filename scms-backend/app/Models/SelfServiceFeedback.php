<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelfServiceFeedback extends Model
{
    protected $table = 'self_service_feedbacks';

    protected $fillable = [
        'student_id',
        'kb_entry_id',
        'task_id',
        'is_helpful',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'is_helpful' => 'boolean',
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

    public function task(): BelongsTo
    {
        return $this->belongsTo(SelfServiceTask::class, 'task_id');
    }
}
