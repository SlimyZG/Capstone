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

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = ['ai_metadata'];

    protected $fillable = [
        'student_id',
        'academic_unit_id',
        'target_type',
        'dept_id',
        'recurring_incident_id',
        'title',
        'description',
        'category',
        'issue_category',
        'issue_subcategory',
        'classification_explanation',
        'classification_confidence',
        'workflow_type',
        'status',
        'submitted_at',
        'last_updated',
        'resolved_at',
        'forwarded_to_cad_at',
        'cad_status',
        'cad_document_generated_at',
        'cad_department_notes',
        'cad_submission_notes',
        'cad_submitted_at',
        'cad_submitted_by',
        'cad_decision',
        'cad_decision_at',
        'cad_decision_by',
        'cad_decision_notes',
        'cad_signed_document_path',
        'is_anonymous',
        'attachments',
        'transferred_from',
        'priority',
        'ai_priority',
        'ai_priority_explanation',
        'is_priority_overridden',
        'priority_override_reason',
        'priority_overridden_by',
        'priority_overridden_at',
        'critical_notified_at',
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
            'forwarded_to_cad_at' => 'datetime',
            'cad_document_generated_at' => 'datetime',
            'cad_submitted_at' => 'datetime',
            'cad_decision_at' => 'datetime',
            'sentiment_score' => 'float',
            'classification_confidence' => 'float',
            'is_priority_overridden' => 'boolean',
            'priority_overridden_at' => 'datetime',
            'critical_notified_at' => 'datetime',
            'attachments' => 'array',
        ];
    }

    /**
     * Invalidate the cached historical resolution dataset whenever a concern
     * is created, updated (e.g. resolved, priority overridden, transferred), or deleted.
     */
    protected static function booted(): void
    {
        static::saved(function () {
            \App\Services\ResolutionPredictionService::flushCache();
        });

        static::deleted(function () {
            \App\Services\ResolutionPredictionService::flushCache();
        });
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

    /**
     * Get the user who manually overrode the priority.
     */
    public function priorityOverriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'priority_overridden_by');
    }

    /**
     * Get the recurring incident this concern is linked to.
     */
    public function recurringIncident(): BelongsTo
    {
        return $this->belongsTo(RecurringIncident::class, 'recurring_incident_id');
    }

    /**
     * Get the staff user who recorded physical CAD submission.
     */
    public function cadSubmittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cad_submitted_by');
    }

    /**
     * Get the staff user who recorded the CAD decision.
     */
    public function cadDecisionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cad_decision_by');
    }

    /**
     * Get all interdepartmental transfer requests for this concern.
     */
    public function transferRequests(): HasMany
    {
        return $this->hasMany(ConcernTransferRequest::class, 'concern_id', 'concern_id')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');
    }

    /**
     * Get all interdepartmental collaboration records for this concern.
     */
    public function collaborations(): HasMany
    {
        return $this->hasMany(ConcernCollaboration::class, 'concern_id', 'concern_id')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');
    }

    /**
     * Get all PCO department approval requests for this concern.
     */
    public function pcoApprovalRequests(): HasMany
    {
        return $this->hasMany(PcoApprovalRequest::class, 'concern_id', 'concern_id')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');
    }

    /**
     * Get internal department-only messages for this concern.
     * NEVER eager-load on student-facing endpoints.
     */
    public function internalMessages(): HasMany
    {
        return $this->hasMany(ConcernInternalMessage::class, 'concern_id', 'concern_id')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');
    }

    /**
     * Get metadata describing the source and status of AI sentiment/summary analysis.
     * Internal token usage is strictly restricted to authorized staff roles.
     */
    public function getAiMetadataAttribute(): array
    {
        $status = $this->ai_status ?? 'pending';

        $user = auth('sanctum')->user() ?? auth()->user() ?? request()?->user();
        $isAuthorized = $user && in_array($user->role, ['dept_staff', 'dean', 'sao', 'admin'], true);

        if ($status === 'failed') {
            $meta = [
                'status' => 'failed',
                'source' => 'failed',
                'label' => 'Analysis Failed',
                'is_fallback' => false,
            ];
            if ($isAuthorized) {
                $meta['tokens_used'] = 0;
            }
            return $meta;
        }

        if ($status === 'pending') {
            $meta = [
                'status' => 'pending',
                'source' => 'pending',
                'label' => 'Processing...',
                'is_fallback' => false,
            ];
            if ($isAuthorized) {
                $meta['tokens_used'] = 0;
            }
            return $meta;
        }

        $tokensUsed = 0;
        try {
            $log = \Illuminate\Support\Facades\DB::table('sentiment_logs')
                ->where('concern_id', $this->concern_id)
                ->orderBy('id', 'desc')
                ->first();

            if ($log) {
                $tokensUsed = (int) ($log->api_tokens_used ?? 0);
            }
        } catch (\Throwable $e) {
            $tokensUsed = 0;
        }

        $isGemini = $tokensUsed > 0;

        $meta = [
            'status' => 'complete',
            'source' => $isGemini ? 'gemini' : 'local_fallback',
            'label' => $isGemini ? 'Gemini AI' : 'Rule-based Engine',
            'is_fallback' => !$isGemini,
        ];

        if ($isAuthorized) {
            $meta['tokens_used'] = $tokensUsed;
        }

        return $meta;
    }
}
