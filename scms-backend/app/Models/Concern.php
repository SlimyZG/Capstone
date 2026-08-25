<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Concern extends Model
{
    use HasFactory;

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'concern_id';

    /**
     * Indicates if the model should be timestamped.
     * We use submitted_at and last_updated instead of created_at/updated_at.
     */
    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'academic_unit_id',
        'target_type',
        'dept_id',
        'title',
        'description',
        'category',
        'status',
        'submitted_at',
        'last_updated',
        'resolved_at',
        'is_anonymous',
        'attachments',
        'transferred_from',
        'priority',
        'sentiment_score',
        'sentiment_label',
        'ai_summary',
        'ai_status',
        'resolution_summary',
    ];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'submitted_at' => 'datetime',
            'last_updated' => 'datetime',
            'resolved_at' => 'datetime',
            'sentiment_score' => 'float',
            'attachments' => 'array',
        ];
    }

    /**
     * Get the student who submitted this concern.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Get the academic unit associated with this concern.
     */
    public function academicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    /**
     * Get the department this concern is routed to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'dept_id');
    }

    /**
     * Get the routing history for this concern.
     */
    public function routingHistory(): HasMany
    {
        return $this->hasMany(ConcernRouting::class, 'concern_id', 'concern_id');
    }

    /**
     * Get the messages thread for this concern.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'concern_id', 'concern_id');
    }
}
