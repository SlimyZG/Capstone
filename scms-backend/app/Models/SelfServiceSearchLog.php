<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelfServiceSearchLog extends Model
{
    protected $table = 'self_service_search_logs';

    protected $fillable = [
        'student_id',
        'query',
        'selected_category',
        'matched_entries_count',
        'has_published_match',
        'triggered_sensitive_guardrail',
        'escalated_to_formal_concern',
    ];

    protected function casts(): array
    {
        return [
            'matched_entries_count' => 'integer',
            'has_published_match' => 'boolean',
            'triggered_sensitive_guardrail' => 'boolean',
            'escalated_to_formal_concern' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
