<?php

namespace App\Http\Controllers;

use App\Models\SelfServiceFeedback;
use App\Models\SelfServiceKbEntry;
use App\Models\SelfServiceKbRevision;
use App\Models\SelfServiceSearchLog;
use App\Models\SelfServiceTask;
use Database\Seeders\SelfServiceKnowledgeBaseSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaoSelfServiceController extends Controller
{
    /**
     * Ensure knowledge base entries are seeded in the database.
     */
    private function ensureKnowledgeBaseSeeded(): void
    {
        if (SelfServiceKbEntry::count() === 0) {
            app(SelfServiceKnowledgeBaseSeeder::class)->run();
        }
    }

    /**
     * List all knowledge base entries for SAO review, approval tracking, and publication management.
     */
    public function index(Request $request)
    {
        $this->ensureKnowledgeBaseSeeded();

        $query = SelfServiceKbEntry::query()
            ->withCount([
                'tasks',
                'feedbacks',
                'feedbacks as helpful_count' => fn ($q) => $q->where('is_helpful', true),
                'feedbacks as not_helpful_count' => fn ($q) => $q->where('is_helpful', false),
            ]);

        if ($request->filled('category') && $request->input('category') !== 'All') {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('lifecycle_stage') && $request->input('lifecycle_stage') !== 'All') {
            $query->where('lifecycle_stage', $request->input('lifecycle_stage'));
        }

        if ($request->filled('approval_status') && $request->input('approval_status') !== 'All') {
            $query->where('approval_status', $request->input('approval_status'));
        }

        if ($request->filled('publication_status') && $request->input('publication_status') !== 'All') {
            $query->where('publication_status', $request->input('publication_status'));
        }

        if ($request->filled('q')) {
            $search = trim((string) $request->input('q'));
            $query->where(function ($q) use ($search) {
                $q->where('entry_code', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('responsible_office', 'like', "%{$search}%")
                  ->orWhere('approved_answer', 'like', "%{$search}%");
            });
        }

        $entries = $query->orderBy('entry_code')->get();

        $summary = [
            'total_entries' => SelfServiceKbEntry::count(),
            'standard_entries' => SelfServiceKbEntry::where('is_sensitive', false)->count(),
            'sensitive_entries' => SelfServiceKbEntry::where('is_sensitive', true)->count(),
            'approved_entries' => SelfServiceKbEntry::where('approval_status', 'Approved')->count(),
            'published_entries' => SelfServiceKbEntry::publishedForStudents()->count(),
            'pending_validation' => SelfServiceKbEntry::where('approval_status', 'Pending UCLM Validation')->count(),
            'needs_revision' => SelfServiceKbEntry::where('lifecycle_stage', 'Needs Revision')->count(),
        ];

        return response()->json([
            'entries' => $entries,
            'summary' => $summary,
            'categories' => SelfServiceKbEntry::CATEGORIES,
            'lifecycle_stages' => SelfServiceKbEntry::LIFECYCLE_STAGES,
            'approval_statuses' => SelfServiceKbEntry::APPROVAL_STATUSES,
            'publication_statuses' => SelfServiceKbEntry::PUBLICATION_STATUSES,
        ]);
    }

    /**
     * Show a single knowledge base entry with its revision history.
     */
    public function show(int $id)
    {
        $this->ensureKnowledgeBaseSeeded();

        $entry = SelfServiceKbEntry::with(['revisions.changer', 'publisher'])
            ->withCount([
                'tasks',
                'feedbacks',
                'feedbacks as helpful_count' => fn ($q) => $q->where('is_helpful', true),
                'feedbacks as not_helpful_count' => fn ($q) => $q->where('is_helpful', false),
            ])
            ->findOrFail($id);

        return response()->json([
            'entry' => $entry,
        ]);
    }

    /**
     * Fields whose change alters the guidance a student would follow (substantive).
     * Changing any of these on an approved/published entry voids the prior approval.
     * Non-substantive metadata (category, common_questions, validation_notes,
     * revision_summary) does not invalidate approval.
     */
    private const SUBSTANTIVE_FIELDS = [
        'title',
        'approved_answer',
        'steps',
        'requirements',
        'responsible_office',
        'manual_page_number',
    ];

    private const APPROVAL_EVIDENCE_FIELDS = [
        'validated_by',
        'validated_role',
        'validated_office',
        'supporting_reference',
    ];

    /**
     * Update a knowledge base entry's content or official UCLM approval record.
     *
     * - Recording 'Approved' requires validated_by, validated_role, validated_office and
     *   supporting_reference. These are SAO-entered records, not independently verified proof;
     *   the authenticated SAO user is stored in approval_recorded_by.
     * - Substantive content edits on an approved/published entry snapshot the previous version
     *   (with its approval metadata), bump the version, unpublish, and reset approval to
     *   Pending UCLM Validation. Approval fields sent in the same request are ignored.
     */
    public function update(Request $request, int $id)
    {
        $entry = SelfServiceKbEntry::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|required|string|in:' . implode(',', SelfServiceKbEntry::CATEGORIES),
            'common_questions' => 'nullable|array|max:20',
            'common_questions.*' => 'string|max:500',
            'approved_answer' => 'sometimes|required|string|max:20000',
            'steps' => 'nullable|array|max:50',
            'steps.*' => 'array',
            'steps.*.step_number' => 'required|integer|min:1|max:100|distinct',
            'steps.*.title' => 'required|string|max:255',
            'steps.*.instructions' => 'required|string|max:5000',
            'steps.*.requirements' => 'nullable|array|max:30',
            'steps.*.requirements.*' => 'string|max:1000',
            'steps.*.office' => 'nullable|string|max:500',
            'steps.*.source_reference' => 'nullable|string|max:255',
            'steps.*.explanation' => 'nullable|string|max:2000',
            'requirements' => 'nullable|array|max:50',
            'requirements.*' => 'string|max:2000',
            'responsible_office' => 'sometimes|required|string|max:500',
            'manual_page_number' => 'sometimes|required|string|max:120',
            'approval_status' => 'sometimes|required|string|in:' . implode(',', SelfServiceKbEntry::APPROVAL_STATUSES),
            'validated_by' => 'nullable|string|max:255',
            'validated_role' => 'nullable|string|max:255',
            'validated_office' => 'nullable|string|max:255',
            'validation_notes' => 'nullable|string|max:2000',
            'supporting_reference' => 'nullable|string|max:255',
            'revision_summary' => 'nullable|string|max:1000',
        ]);

        if (array_key_exists('steps', $validated)) {
            $validated['steps'] = $this->normalizeSteps($validated['steps'] ?? []);
        }
        if (array_key_exists('requirements', $validated)) {
            $validated['requirements'] = array_values($validated['requirements'] ?? []);
        }

        $contentChanged = $this->hasSubstantiveChange($entry, $validated);
        $requiresReapproval = $contentChanged
            && ($entry->approval_status === 'Approved' || $entry->publication_status === 'Published');

        $user = $request->user();

        if (!$requiresReapproval) {
            $targetApproval = $validated['approval_status'] ?? $entry->approval_status;

            if ($targetApproval === 'Approved') {
                $missing = [];
                foreach (self::APPROVAL_EVIDENCE_FIELDS as $field) {
                    $value = array_key_exists($field, $validated) ? $validated[$field] : $entry->{$field};
                    if (trim((string) $value) === '') {
                        $missing[$field] = ["The {$field} field is required to record an Approved decision."];
                    }
                }
                if (!empty($missing)) {
                    return response()->json([
                        'message' => 'Approval requires validated_by, validated_role, validated_office, and a supporting_reference identifying the signed UCLM review packet or official record.',
                        'errors' => $missing,
                    ], 422);
                }
            }
        }

        return DB::transaction(function () use ($entry, $validated, $contentChanged, $requiresReapproval, $user) {
            if ($contentChanged) {
                SelfServiceKbRevision::create([
                    'kb_entry_id' => $entry->id,
                    'version' => (int) $entry->version,
                    'title' => $entry->title,
                    'approved_answer' => $entry->approved_answer,
                    'steps' => $entry->steps,
                    'requirements' => $entry->requirements,
                    'responsible_office' => $entry->responsible_office,
                    'manual_page_number' => $entry->manual_page_number,
                    'lifecycle_stage' => $entry->lifecycle_stage,
                    'approval_status' => $entry->approval_status,
                    'publication_status' => $entry->publication_status,
                    'validated_by' => $entry->validated_by,
                    'validated_role' => $entry->validated_role,
                    'validated_office' => $entry->validated_office,
                    'validated_at' => $entry->validated_at,
                    'supporting_reference' => $entry->supporting_reference,
                    'approval_recorded_by' => $entry->approval_recorded_by,
                    'changed_by' => $user->id,
                    'change_notes' => $validated['revision_summary'] ?? 'Knowledge base content updated by SAO.',
                ]);
            }

            $attributes = Arr::only($validated, [
                'title', 'category', 'common_questions', 'approved_answer', 'steps', 'requirements',
                'responsible_office', 'manual_page_number', 'validation_notes', 'revision_summary',
            ]);

            if ($contentChanged) {
                $attributes['version'] = (int) $entry->version + 1;
            }

            if ($requiresReapproval) {
                $attributes = array_merge($attributes, [
                    'approval_status' => 'Pending UCLM Validation',
                    'publication_status' => 'Unpublished',
                    'lifecycle_stage' => 'Under Office Review',
                    'validated_by' => null,
                    'validated_role' => null,
                    'validated_office' => null,
                    'validated_at' => null,
                    'supporting_reference' => null,
                    'approval_recorded_by' => null,
                    'published_by' => null,
                    'published_at' => null,
                ]);
            } else {
                $targetApproval = $validated['approval_status'] ?? $entry->approval_status;
                $attributes['approval_status'] = $targetApproval;

                if ($targetApproval === 'Approved') {
                    $newEvidence = [];
                    foreach (self::APPROVAL_EVIDENCE_FIELDS as $field) {
                        $newEvidence[$field] = trim((string) (array_key_exists($field, $validated) ? $validated[$field] : $entry->{$field}));
                    }
                    $evidenceChanged = $entry->approval_status !== 'Approved';
                    foreach ($newEvidence as $field => $value) {
                        if ($value !== (string) $entry->{$field}) {
                            $evidenceChanged = true;
                        }
                    }

                    $attributes = array_merge($attributes, $newEvidence);
                    if ($evidenceChanged) {
                        $attributes['validated_at'] = now();
                        $attributes['approval_recorded_by'] = $user->id;
                    }
                    $attributes['lifecycle_stage'] = $entry->publication_status === 'Published' ? 'Published' : 'Approved';
                } else {
                    // For non-Approved states (e.g., Pending UCLM Validation, Verbal Confirmation, Needs Revision),
                    // persist any draft validator/reference fields provided in the request without auto-approving.
                    foreach (self::APPROVAL_EVIDENCE_FIELDS as $field) {
                        if (array_key_exists($field, $validated)) {
                            $trimmed = trim((string) $validated[$field]);
                            $attributes[$field] = $trimmed !== '' ? $trimmed : null;
                        }
                    }

                    if ($targetApproval !== $entry->approval_status) {
                        // Approval downgraded: protect students immediately.
                        $attributes['publication_status'] = 'Unpublished';
                        $attributes['lifecycle_stage'] = $targetApproval === 'Needs Revision' ? 'Needs Revision' : 'Under Office Review';
                    }
                }
            }

            $entry->update($attributes);

            return response()->json([
                'message' => $requiresReapproval
                    ? 'Guidance content changed: the entry was unpublished, its version incremented, and approval reset to Pending UCLM Validation. Record fresh approval and publish explicitly.'
                    : 'Knowledge base entry updated successfully.',
                'reapproval_required' => $requiresReapproval,
                'entry' => $entry->fresh(),
            ]);
        });
    }

    /**
     * Publish an approved knowledge base entry (SAO only).
     */
    public function publish(Request $request, int $id)
    {
        $entry = SelfServiceKbEntry::findOrFail($id);

        if ($entry->approval_status !== 'Approved') {
            return response()->json([
                'message' => 'Only formally approved entries (Approval Status: Approved) may be published to students. Current approval status is "' . $entry->approval_status . '".',
            ], 422);
        }

        foreach (self::APPROVAL_EVIDENCE_FIELDS as $field) {
            if (trim((string) $entry->{$field}) === '') {
                return response()->json([
                    'message' => 'Cannot publish entry without a complete approval record (validated_by, validated_role, validated_office, supporting_reference).',
                ], 422);
            }
        }

        if (!$entry->is_sensitive && empty($this->validChecklist($entry->steps))) {
            return response()->json([
                'message' => 'A standard entry cannot be published with an empty or invalid checklist. Add at least one valid step first.',
            ], 422);
        }

        $entry->update([
            'publication_status' => 'Published',
            'lifecycle_stage' => 'Published',
            'published_by' => $request->user()->id,
            'published_at' => now(),
        ]);

        return response()->json([
            'message' => "Knowledge base entry {$entry->entry_code} is now published for Student Self-Service Assistance.",
            'entry' => $entry->fresh(),
        ]);
    }

    private function normalizeSteps(array $steps): array
    {
        return array_values(array_map(function ($s) {
            return [
                'step_number' => (int) ($s['step_number'] ?? 0),
                'title' => (string) ($s['title'] ?? ''),
                'instructions' => (string) ($s['instructions'] ?? ''),
                'requirements' => array_values(array_map('strval', (array) ($s['requirements'] ?? []))),
                'office' => $s['office'] ?? null,
                'source_reference' => $s['source_reference'] ?? null,
                'explanation' => $s['explanation'] ?? null,
            ];
        }, array_filter($steps, 'is_array')));
    }

    private function validChecklist($steps): array
    {
        if (!is_array($steps)) {
            return [];
        }

        return array_values(array_filter($steps, function ($s) {
            return is_array($s)
                && isset($s['step_number']) && (int) $s['step_number'] >= 1
                && trim((string) ($s['title'] ?? '')) !== '';
        }));
    }

    private function hasSubstantiveChange(SelfServiceKbEntry $entry, array $validated): bool
    {
        foreach (self::SUBSTANTIVE_FIELDS as $field) {
            if (!array_key_exists($field, $validated)) {
                continue;
            }

            if ($field === 'steps') {
                $old = $this->normalizeSteps(is_array($entry->steps) ? $entry->steps : []);
                if (json_encode($validated['steps'] ?? []) !== json_encode($old)) {
                    return true;
                }
            } elseif ($field === 'requirements') {
                $old = array_values(is_array($entry->requirements) ? $entry->requirements : []);
                if (json_encode($validated['requirements'] ?? []) !== json_encode($old)) {
                    return true;
                }
            } elseif ((string) $validated[$field] !== (string) $entry->{$field}) {
                return true;
            }
        }

        return false;
    }

    /**
     * Unpublish a knowledge base entry so it is immediately removed from student retrieval.
     */
    public function unpublish(Request $request, int $id)
    {
        $entry = SelfServiceKbEntry::findOrFail($id);

        $validated = $request->validate([
            'archive' => 'nullable|boolean',
            'reason' => 'nullable|string|max:1000',
        ]);

        $isArchive = !empty($validated['archive']);

        $entry->update([
            'publication_status' => $isArchive ? 'Archived' : 'Unpublished',
            'lifecycle_stage' => $isArchive
                ? 'Archived / Outdated'
                : ($entry->approval_status === 'Approved' ? 'Approved' : 'Under Office Review'),
            'revision_summary' => $validated['reason'] ?? $entry->revision_summary,
        ]);

        return response()->json([
            'message' => "Knowledge base entry {$entry->entry_code} has been unpublished.",
            'entry' => $entry->fresh(),
        ]);
    }

    /**
     * Mark a knowledge base entry as requiring revision (immediately unpublishes it from student view).
     */
    public function markNeedsRevision(Request $request, int $id)
    {
        $entry = SelfServiceKbEntry::findOrFail($id);

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $entry->update([
            'lifecycle_stage' => 'Needs Revision',
            'approval_status' => 'Needs Revision',
            'publication_status' => 'Unpublished',
            'revision_summary' => $validated['reason'],
        ]);

        return response()->json([
            'message' => "Knowledge base entry {$entry->entry_code} marked as Needs Revision and unpublished from student self-service.",
            'entry' => $entry->fresh(),
        ]);
    }

    /**
     * Aggregated Self-Service Analytics & Feedback Oversight for SAO.
     *
     * STRICT PRIVACY RULE (Option A Alignment):
     * Uses aggregated metrics (category search counts, top search terms, task completion rates,
     * blocked step counts, and feedback ratings) without exposing private formal concerns outside SAO's scope.
     */
    public function analytics(Request $request)
    {
        $this->ensureKnowledgeBaseSeeded();

        $totalTasks = SelfServiceTask::count();
        $completedTasks = SelfServiceTask::where('status', 'completed')->count();
        $inProgressTasks = SelfServiceTask::where('status', 'in_progress')->count();
        $pausedTasks = SelfServiceTask::where('status', 'paused')->count();
        $blockedTasks = SelfServiceTask::where('status', 'blocked')->count();

        $totalFeedback = SelfServiceFeedback::count();
        $helpfulFeedback = SelfServiceFeedback::where('is_helpful', true)->count();
        $notHelpfulFeedback = SelfServiceFeedback::where('is_helpful', false)->count();

        // Commonly searched queries (aggregated)
        $topSearches = SelfServiceSearchLog::whereNotNull('query')
            ->select(
                'query',
                DB::raw('COUNT(*) as search_count'),
                DB::raw('SUM(CASE WHEN has_published_match = 1 THEN 1 ELSE 0 END) as matched_count')
            )
            ->groupBy('query')
            ->orderByDesc('search_count')
            ->limit(10)
            ->get();

        // Category browsing distribution (aggregated)
        $categoryUsage = SelfServiceSearchLog::whereNotNull('selected_category')
            ->select('selected_category as category', DB::raw('COUNT(*) as views_count'))
            ->groupBy('selected_category')
            ->orderByDesc('views_count')
            ->get();

        // Unresolved / blocked self-service sessions (aggregated by entry and blocked step, no private formal concern data)
        $unresolvedSessions = SelfServiceTask::whereIn('status', ['blocked', 'paused', 'in_progress'])
            ->select(
                'entry_code',
                'task_title',
                'category',
                'status',
                DB::raw('COUNT(*) as session_count'),
                DB::raw('AVG(progress_percentage) as avg_progress')
            )
            ->groupBy('entry_code', 'task_title', 'category', 'status')
            ->orderByDesc('session_count')
            ->limit(15)
            ->get();

        // Recent student feedback on guidance quality (anonymized student identifier)
        $recentFeedback = SelfServiceFeedback::with('kbEntry:id,entry_code,title,category')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (SelfServiceFeedback $f) => [
                'id' => $f->id,
                'entry_code' => $f->kbEntry?->entry_code,
                'entry_title' => $f->kbEntry?->title,
                'category' => $f->kbEntry?->category,
                'is_helpful' => (bool) $f->is_helpful,
                'comment' => $f->comment,
                'created_at' => $f->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'usage_summary' => [
                'total_tasks_started' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'in_progress_tasks' => $inProgressTasks,
                'paused_tasks' => $pausedTasks,
                'blocked_tasks' => $blockedTasks,
                'total_searches' => SelfServiceSearchLog::count(),
                'unmatched_searches' => SelfServiceSearchLog::where('has_published_match', false)->count(),
                'sensitive_guardrail_triggers' => SelfServiceSearchLog::where('triggered_sensitive_guardrail', true)->count(),
            ],
            'feedback_summary' => [
                'total_feedback' => $totalFeedback,
                'helpful_count' => $helpfulFeedback,
                'not_helpful_count' => $notHelpfulFeedback,
                'helpful_rate' => $totalFeedback > 0 ? round(($helpfulFeedback / $totalFeedback) * 100, 1) : null,
            ],
            'top_searches' => $topSearches,
            'category_usage' => $categoryUsage,
            'unresolved_sessions' => $unresolvedSessions,
            'recent_feedback' => $recentFeedback,
        ]);
    }
}
