<?php

namespace App\Http\Controllers;

use App\Models\Concern;
use App\Models\ConcernRouting;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;

class DeptStaffController extends Controller
{
    /**
     * Helper to scope concerns to the dept_staff user.
     * Fixes AND/OR logic bug: matches user's department OR academic unit.
     */
    private function scopeForStaff($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $hasCondition = false;
            if ($user->dept_id) {
                $q->where('dept_id', $user->dept_id);
                $hasCondition = true;
            }
            if ($user->academic_unit_id) {
                $acadClause = function ($sub) use ($user) {
                    $sub->where('academic_unit_id', $user->academic_unit_id)
                        ->where(function ($w) {
                            $w->where('workflow_type', 'academic')
                              ->orWhere(function ($w2) {
                                  $w2->where('category', 'academic_units')
                                     ->whereNull('dept_id');
                              });
                        });
                };

                if ($hasCondition) {
                    $q->orWhere($acadClause);
                } else {
                    $q->where($acadClause);
                }
                $hasCondition = true;
            }
            // Fail-closed: a staff account with no dept or unit assignment
            // cannot match any concern. Using whereRaw('1 = 0') ensures zero
            // records are returned without altering the SQL for assigned staff.
            if (!$hasCondition) {
                $q->whereRaw('1 = 0');
            }
        });
    }

    /**
     * Get the queue of concerns routed to this staff's department or academic unit.
     * Shows concerns with target_type='department' and matching dept_id or academic_unit_id.
     * Also shows concerns that were approved/rejected/returned back to this department.
     */
    public function queue(Request $request)
    {
        $user = $request->user();

        $query = Concern::query();
        $this->scopeForStaff($query, $user);

        $sla = app(\App\Services\ConcernSlaService::class);
        $predictor = app(\App\Services\ResolutionPredictionService::class);
        $dataset = $predictor->getCleanHistoricalDataset();

        $concerns = $query->where('target_type', 'department')
            ->whereIn('status', ['pending', 'in_progress', 'approved', 'rejected', 'returned', 'resolved', 'escalated'])
            ->with(['student' => function ($q) {
                $q->select('id', 'name', 'student_id_number', 'academic_unit_id');
            }, 'academicUnit', 'department', 'routingHistory.performer'])
            ->orderBy('submitted_at', 'desc')
            ->get()
            ->map(function ($concern) use ($sla, $predictor, $dataset) {
                if ($concern->is_anonymous) {
                    $concern->student->name = 'Anonymous';
                    $concern->student->student_id_number = '********';
                }
                // Read-only resolution-target status and historical prediction (not persisted)
                $concern->sla = $sla->describe($concern);
                $concern->prediction = $predictor->predict($concern, $dataset);
                return $concern;
            });

        return response()->json([
            'concerns' => $concerns,
        ]);
    }

    /**
     * Reply to a concern (send a message to the student).
     */
    public function reply(Request $request, int $id)
    {
        $user = $request->user();

        $query = Concern::where('concern_id', $id);
        $this->scopeForStaff($query, $user);
        $concern = $query->firstOrFail();

        $validated = $request->validate([
            'body' => 'required|string',
            'is_internal' => 'boolean',
        ]);

        $message = Message::create([
            'concern_id' => $concern->concern_id,
            'sender_id' => $user->id,
            'body' => $validated['body'],
            'is_internal' => $validated['is_internal'] ?? false,
        ]);

        // Update status to in_progress if still pending
        if ($concern->status === 'pending') {
            $concern->update([
                'status' => 'in_progress',
                'last_updated' => now(),
            ]);
        } else {
            $concern->update(['last_updated' => now()]);
        }

        // Dispatch in-app notifications to authorized concern participants
        \App\Notifications\ConcernWorkflowNotification::sendNewMessageNotification(
            $concern,
            $message,
            $user
        );

        return response()->json([
            'message' => 'Reply sent successfully.',
            'data' => $message->load('sender'),
        ], 201);
    }

    /**
     * Resolve a concern directly.
     */
    public function resolve(Request $request, int $id)
    {
        $user = $request->user();

        $query = Concern::where('concern_id', $id);
        $this->scopeForStaff($query, $user);
        $concern = $query->whereIn('status', ['pending', 'in_progress', 'approved', 'returned'])->firstOrFail();

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $concern->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'last_updated' => now(),
        ]);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'resolved',
            'performed_by' => $user->id,
            'notes' => $validated['notes'] ?? 'Resolved by department staff.',
        ]);

        \App\Jobs\GenerateResolutionSummary::dispatch($concern);

        // Notify student that their concern has been resolved
        try {
            if ($concern->student) {
                $concern->student->notify(new \App\Notifications\ConcernWorkflowNotification(
                    $concern->concern_id,
                    'Concern Resolved',
                    "Your concern '{$concern->title}' has been resolved by the department staff.",
                    'resolved',
                    $concern->workflow_type ?? 'academic',
                    "/student/concerns/{$concern->concern_id}"
                ));
            }
        } catch (\Throwable $ne) {
            \Illuminate\Support\Facades\Log::warning('Failed sending resolution notification: ' . $ne->getMessage());
        }

        return response()->json([
            'message' => 'Concern resolved successfully.',
            'concern' => $concern->fresh(['academicUnit', 'department']),
        ]);
    }

    /**
     * Update the status of a concern (pending, in_progress, resolved).
     */
    public function updateStatus(Request $request, int $id)
    {
        $user = $request->user();

        $query = Concern::where('concern_id', $id);
        $this->scopeForStaff($query, $user);
        $concern = $query->firstOrFail();

        $validated = $request->validate([
            'status' => 'required|in:pending,in_progress,resolved',
            'notes' => 'nullable|string',
        ]);

        $updateData = [
            'status' => $validated['status'],
            'last_updated' => now(),
        ];
        
        if ($validated['status'] === 'resolved') {
            $updateData['resolved_at'] = now();
        }

        $concern->update($updateData);

        $notesText = 'Status changed to ' . str_replace('_', ' ', $validated['status']) . '.';
        if (!empty($validated['notes'])) {
            $notesText .= ' Notes: ' . $validated['notes'];
        }

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'status_changed',
            'performed_by' => $user->id,
            'notes' => $notesText,
        ]);

        if ($validated['status'] === 'resolved') {
            \App\Jobs\GenerateResolutionSummary::dispatch($concern);
        }

        return response()->json([
            'message' => 'Concern status updated successfully.',
            'concern' => $concern->fresh(['academicUnit', 'department']),
        ]);
    }

    /**
     * Escalate a concern (for Academic: to Dean; or standard escalation).
     */
    public function escalate(Request $request, int $id)
    {
        $user = $request->user();

        $query = Concern::where('concern_id', $id);
        $this->scopeForStaff($query, $user);
        $concern = $query->whereIn('status', ['pending', 'in_progress', 'returned'])->firstOrFail();

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $isAcademic = ($concern->workflow_type === 'academic' || $concern->category === 'academic_units');
        $action = $isAcademic ? 'forwarded_to_dean' : 'escalated_to_dean';
        $note = $validated['notes'] ?? ($isAcademic ? 'Reviewed by Chairperson/Dept Staff and forwarded to Dean.' : 'Escalated to Dean for review.');

        $concern->update([
            'status' => 'escalated',
            'last_updated' => now(),
        ]);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => $action,
            'performed_by' => $user->id,
            'notes' => $note,
        ]);

        // Notify Dean and Student about the escalation/forwarding
        try {
            $dean = \App\Models\User::where('role', 'dean')
                ->where('academic_unit_id', $concern->academic_unit_id)
                ->first();
            if ($dean) {
                $dean->notify(new \App\Notifications\ConcernWorkflowNotification(
                    $concern->concern_id,
                    'Academic Concern Forwarded to Dean',
                    "Concern '{$concern->title}' has been reviewed by department staff and forwarded to your office.",
                    'forwarded_to_dean',
                    $concern->workflow_type ?? 'academic',
                    "/dean/concerns/{$concern->concern_id}"
                ));
            }

            if ($concern->student) {
                $concern->student->notify(new \App\Notifications\ConcernWorkflowNotification(
                    $concern->concern_id,
                    'Concern Forwarded to Dean',
                    "Your concern '{$concern->title}' has been reviewed by department staff and forwarded to the Dean.",
                    'forwarded_to_dean',
                    $concern->workflow_type ?? 'academic',
                    "/student/concerns/{$concern->concern_id}"
                ));
            }
        } catch (\Throwable $ne) {
            \Illuminate\Support\Facades\Log::warning('Failed sending dean forward notification: ' . $ne->getMessage());
        }

        return response()->json([
            'message' => $isAcademic ? 'Concern forwarded to Dean successfully.' : 'Concern escalated to Dean successfully.',
            'concern' => $concern->fresh(['academicUnit', 'department']),
        ]);
    }

    /**
     * Forward Non-Academic concern to CAD (Campus Administrator / Administration) for approval.
     */
    public function forwardToCad(Request $request, int $id)
    {
        $user = $request->user();

        $query = Concern::where('concern_id', $id);
        $this->scopeForStaff($query, $user);
        $concern = $query->whereIn('status', ['pending', 'in_progress', 'returned'])->firstOrFail();

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $concern->update([
            'status' => 'escalated',
            'forwarded_to_cad_at' => now(),
            'last_updated' => now(),
        ]);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'forwarded_to_cad',
            'performed_by' => $user->id,
            'notes' => $validated['notes'] ?? 'Forwarded to CAD (Campus Administration) for approval.',
        ]);

        // Notify Dean / CAD Administration and Student
        try {
            // Find executive / administration users (deans who oversee CAD or all deans)
            $cadUsers = \App\Models\User::where('role', 'dean')->get();
            foreach ($cadUsers as $cadUser) {
                $cadUser->notify(new \App\Notifications\ConcernWorkflowNotification(
                    $concern->concern_id,
                    'Non-Academic Concern Forwarded to CAD',
                    "Non-academic concern '{$concern->title}' has been forwarded for Campus Administration approval.",
                    'forwarded_to_cad',
                    $concern->workflow_type ?? 'non_academic',
                    "/dean/concerns/{$concern->concern_id}"
                ));
            }

            if ($concern->student) {
                $concern->student->notify(new \App\Notifications\ConcernWorkflowNotification(
                    $concern->concern_id,
                    'Concern Forwarded to Campus Administration',
                    "Your non-academic concern '{$concern->title}' has been forwarded to CAD for administrative approval.",
                    'forwarded_to_cad',
                    $concern->workflow_type ?? 'non_academic',
                    "/student/concerns/{$concern->concern_id}"
                ));
            }
        } catch (\Throwable $ne) {
            \Illuminate\Support\Facades\Log::warning('Failed sending CAD notification: ' . $ne->getMessage());
        }

        return response()->json([
            'message' => 'Concern forwarded to CAD for approval.',
            'concern' => $concern->fresh(['academicUnit', 'department']),
        ]);
    }

    /**
     * Transfer a concern to a different department or academic unit.
     */
    public function transfer(Request $request, int $id)
    {
        $user = $request->user();

        $query = Concern::where('concern_id', $id);
        $this->scopeForStaff($query, $user);
        $concern = $query->whereIn('status', ['pending', 'in_progress'])->firstOrFail();

        $validated = $request->validate([
            'target_category' => 'required|in:operational_departments,academic_units',
            'target_id' => 'required|integer',
            'notes' => 'nullable|string',
        ]);

        $oldTarget = $concern->category === 'academic_units' ? ($concern->academicUnit->name ?? 'Unknown') : ($concern->department->name ?? 'Unknown');

        // Update target
        if ($validated['target_category'] === 'academic_units') {
            $unit = \App\Models\AcademicUnit::findOrFail($validated['target_id']);
            $concern->update([
                'category' => 'academic_units',
                'workflow_type' => 'academic',
                'academic_unit_id' => $unit->id,
                'dept_id' => null,
                'transferred_from' => $oldTarget,
                'last_updated' => now(),
            ]);
            $newTarget = $unit->name;
        } else {
            $dept = \App\Models\Department::findOrFail($validated['target_id']);
            $concern->update([
                'category' => 'operational_departments',
                'workflow_type' => 'non_academic',
                'dept_id' => $dept->id,
                'transferred_from' => $oldTarget,
                'last_updated' => now(),
            ]);
            $newTarget = $dept->name;
        }

        $transferNotes = "Transferred from {$oldTarget} to {$newTarget}.";
        if (!empty($validated['notes'])) {
            $transferNotes .= " Reason: " . $validated['notes'];
        }

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'transferred',
            'performed_by' => $user->id,
            'notes' => $transferNotes,
        ]);

        // Phase 6.1: Re-evaluate recurrence grouping in new department context
        if ($concern->recurring_incident_id) {
            app(\App\Services\RecurrenceDetectionService::class)->unlinkConcern($concern, $user->id);
        }
        try {
            app(\App\Services\RecurrenceDetectionService::class)->processConcern($concern->fresh());
        } catch (\Throwable $re) {
            \Illuminate\Support\Facades\Log::warning('Recurrence detection failed after transfer: ' . $re->getMessage());
        }

        // Phase 6.1: If concern is Critical, notify the new department staff
        if ($concern->fresh()->priority === 'critical') {
            $concern->update(['critical_notified_at' => null]);
            try {
                \App\Notifications\ConcernWorkflowNotification::sendCriticalConcernNotification(
                    $concern->fresh(),
                    'Department Transfer'
                );
            } catch (\Throwable $ce) {
                \Illuminate\Support\Facades\Log::warning('Critical notification failed after transfer: ' . $ce->getMessage());
            }
        }

        return response()->json([
            'message' => 'Concern transferred successfully.',
        ]);
    }

    /**
     * Get a specific concern's full details for authorized department staff.
     */
    public function show(Request $request, int $id)
    {
        $user = $request->user();

        $query = Concern::where('concern_id', $id);
        $this->scopeForStaff($query, $user);

        $concern = $query->with([
            'academicUnit',
            'department',
            'routingHistory.performer',
            'messages.sender',
            'student' => function ($q) {
                $q->select('id', 'name', 'student_id_number', 'academic_unit_id', 'email');
            }
        ])->firstOrFail();

        if ($concern->is_anonymous && $concern->student) {
            $concern->student->name = 'Anonymous';
            $concern->student->student_id_number = '********';
            $concern->student->email = 'anonymous@university.edu';
        }

        $concern->sla = app(\App\Services\ConcernSlaService::class)->describe($concern);
        $concern->prediction = app(\App\Services\ResolutionPredictionService::class)->predict($concern);

        return response()->json([
            'concern' => $concern,
        ]);
    }

    /**
     * Get messages for a specific concern.
     */
    public function getMessages(Request $request, int $id)
    {
        $user = $request->user();

        $query = Concern::where('concern_id', $id);
        $this->scopeForStaff($query, $user);
        $concern = $query->firstOrFail();

        $messages = Message::where('concern_id', $concern->concern_id)
            ->with('sender:id,name,role')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'messages' => $messages,
        ]);
    }

    // ── Staff Reports ─────────────────────────────────────────────────────────

    /**
     * Return concern-management report data for department staff.
     * Strictly scoped to the staff member's assigned department / academic unit.
     */
    public function getReport(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date|after_or_equal:date_from',
            'status'    => 'nullable|in:pending,in_progress,escalated,approved,rejected,resolved,closed,withdrawn',
            'priority'  => 'nullable|in:critical,high,medium,low',
            'category'  => 'nullable|string|max:120',
        ]);

        $scopeBase = Concern::query();
        $this->scopeForStaff($scopeBase, $user);

        // Available categories in staff's scope for filter dropdown
        $availableCategories = (clone $scopeBase)
            ->select(\Illuminate\Support\Facades\DB::raw("COALESCE(NULLIF(TRIM(issue_category), ''), NULLIF(TRIM(category), '')) as cat"))
            ->whereRaw("COALESCE(NULLIF(TRIM(issue_category), ''), NULLIF(TRIM(category), '')) IS NOT NULL")
            ->distinct()
            ->pluck('cat')
            ->filter()
            ->map(fn ($cat) => match ($cat) {
                'academic_units'          => 'Academic Concerns',
                'operational_departments' => 'Operational Departments',
                default                   => $cat,
            })
            ->unique()
            ->values();

        $base = clone $scopeBase;

        if (!empty($validated['date_from'])) {
            $base->where('submitted_at', '>=', $validated['date_from']);
        }
        if (!empty($validated['date_to'])) {
            $base->where('submitted_at', '<=', $validated['date_to'] . ' 23:59:59');
        }
        if (!empty($validated['status'])) {
            $base->where('status', $validated['status']);
        }
        if (!empty($validated['priority'])) {
            $base->where('priority', $validated['priority']);
        }
        if (!empty($validated['category'])) {
            $catFilter = $validated['category'];
            $rawAliases = [$catFilter];
            if ($catFilter === 'Academic Concerns') {
                $rawAliases[] = 'academic_units';
            } elseif ($catFilter === 'Operational Departments') {
                $rawAliases[] = 'operational_departments';
            }
            $base->where(function ($q) use ($rawAliases) {
                $q->whereIn('issue_category', $rawAliases)
                  ->orWhereIn('category', $rawAliases);
            });
        }

        $unresolvedStatuses = ['pending', 'in_progress', 'escalated', 'approved', 'returned'];

        $totalConcerns   = (clone $base)->count();
        $pendingCount    = (clone $base)->whereIn('status', ['pending', 'in_progress'])->count();
        $unresolvedCount = (clone $base)->whereIn('status', $unresolvedStatuses)->count();
        $resolvedCount   = (clone $base)->where('status', 'resolved')->count();
        $escalatedCount  = (clone $base)->where('status', 'escalated')->count();
        $criticalCount   = (clone $base)->where('priority', 'critical')->count();
        $highCount       = (clone $base)->where('priority', 'high')->count();

        $criticalUnresolvedCount = (clone $base)
            ->where('priority', 'critical')
            ->whereIn('status', $unresolvedStatuses)
            ->count();
        $highUnresolvedCount = (clone $base)
            ->where('priority', 'high')
            ->whereIn('status', $unresolvedStatuses)
            ->count();

        $resolutionRate = $totalConcerns > 0
            ? round(($resolvedCount / $totalConcerns) * 100, 1)
            : 0;

        // SLA evaluation across open concerns in filtered scope
        $slaService = app(\App\Services\ConcernSlaService::class);
        $openConcernsForSla = (clone $base)
            ->whereIn('status', $unresolvedStatuses)
            ->with(['routingHistory', 'student:id,name,student_id_number', 'academicUnit:id,name', 'department:id,name'])
            ->orderBy('submitted_at', 'asc')
            ->get();

        $overdueCount      = 0;
        $approachingCount  = 0;
        $followupSentCount = 0;
        $attentionItems    = [];

        foreach ($openConcernsForSla as $openConcern) {
            $slaDesc = $slaService->describe($openConcern);
            $state   = $slaDesc['state'] ?? 'normal';
            if ($state === 'overdue') {
                $overdueCount++;
            } elseif ($state === 'followup_sent') {
                $followupSentCount++;
                $approachingCount++;
            } elseif ($state === 'approaching') {
                $approachingCount++;
            }

            $reasons = [];
            if ($openConcern->priority === 'critical') {
                $reasons[] = 'Critical Priority';
            } elseif ($openConcern->priority === 'high') {
                $reasons[] = 'High Priority';
            }
            if ($state === 'overdue') {
                $reasons[] = 'Overdue Target';
            } elseif ($state === 'followup_sent' || $state === 'approaching') {
                $reasons[] = 'Approaching Target (≥75%)';
            }
            if ($openConcern->status === 'escalated') {
                $reasons[] = 'Escalated';
            }

            if (!empty($reasons)) {
                $attentionItems[] = [
                    'concern_id'        => $openConcern->concern_id,
                    'title'             => $openConcern->title,
                    'status'            => $openConcern->status,
                    'priority'          => $openConcern->priority,
                    'category'          => $openConcern->issue_category ?: ($openConcern->category ?: 'General'),
                    'unit_name'         => $openConcern->department->name ?? $openConcern->academicUnit->name ?? 'Assigned Unit',
                    'student_name'      => $openConcern->is_anonymous ? 'Anonymous' : ($openConcern->student->name ?? '—'),
                    'submitted_at'      => $openConcern->submitted_at,
                    'sla_state'         => $state,
                    'target_hours'      => $slaDesc['target_hours'] ?? null,
                    'elapsed_hours'     => isset($slaDesc['elapsed_seconds']) ? round($slaDesc['elapsed_seconds'] / 3600, 1) : null,
                    'reasons'           => $reasons,
                    'urgency_rank'      => ($openConcern->priority === 'critical' ? 100 : ($openConcern->priority === 'high' ? 50 : 10))
                        + ($state === 'overdue' ? 80 : ($state === 'followup_sent' ? 30 : 0))
                        + ($openConcern->status === 'escalated' ? 20 : 0),
                ];
            }
        }

        usort($attentionItems, fn ($a, $b) => $b['urgency_rank'] <=> $a['urgency_rank']);
        $attentionItems = array_slice($attentionItems, 0, 10);

        // Previous period comparison (either matching custom date range length or last 30 days vs prior 30 days)
        $periodDays = 30;
        if (!empty($validated['date_from']) && !empty($validated['date_to'])) {
            $currStart = \Carbon\Carbon::parse($validated['date_from'])->startOfDay();
            $currEnd   = \Carbon\Carbon::parse($validated['date_to'])->endOfDay();
            $periodDays = max(1, (int) $currStart->diffInDays($currEnd) + 1);
            $prevEnd   = $currStart->copy()->subSecond();
            $prevStart = $prevEnd->copy()->subDays($periodDays - 1)->startOfDay();
            $periodLabel = 'vs. previous ' . $periodDays . '-day period';
        } else {
            $currEnd   = now()->endOfDay();
            $currStart = now()->subDays(29)->startOfDay();
            $prevEnd   = $currStart->copy()->subSecond();
            $prevStart = $prevEnd->copy()->subDays(29)->startOfDay();
            $periodLabel = 'vs. previous 30 days';
        }

        $prevQuery = clone $scopeBase;
        if (!empty($validated['status'])) {
            $prevQuery->where('status', $validated['status']);
        }
        if (!empty($validated['priority'])) {
            $prevQuery->where('priority', $validated['priority']);
        }
        if (!empty($validated['category'])) {
            $catFilter = $validated['category'];
            $prevQuery->where(function ($q) use ($catFilter) {
                $q->where('issue_category', $catFilter)->orWhere('category', $catFilter);
            });
        }

        $currWindowQuery = (clone $prevQuery)->whereBetween('submitted_at', [$currStart, $currEnd]);
        $prevWindowQuery = (clone $prevQuery)->whereBetween('submitted_at', [$prevStart, $prevEnd]);

        $currWindowTotal      = (clone $currWindowQuery)->count();
        $prevWindowTotal      = (clone $prevWindowQuery)->count();
        $currWindowResolved   = (clone $currWindowQuery)->where('status', 'resolved')->count();
        $prevWindowResolved   = (clone $prevWindowQuery)->where('status', 'resolved')->count();
        $currWindowUnresolved = (clone $currWindowQuery)->whereIn('status', $unresolvedStatuses)->count();
        $prevWindowUnresolved = (clone $prevWindowQuery)->whereIn('status', $unresolvedStatuses)->count();

        $previousPeriod = [
            'label'             => $periodLabel,
            'current_total'     => $currWindowTotal,
            'previous_total'    => $prevWindowTotal,
            'total_delta'       => $currWindowTotal - $prevWindowTotal,
            'current_resolved'  => $currWindowResolved,
            'previous_resolved' => $prevWindowResolved,
            'resolved_delta'    => $currWindowResolved - $prevWindowResolved,
            'current_unresolved'  => $currWindowUnresolved,
            'previous_unresolved' => $prevWindowUnresolved,
            'unresolved_delta'    => $currWindowUnresolved - $prevWindowUnresolved,
        ];

        $byStatus = (clone $base)
            ->select('status', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $byPriority = (clone $base)
            ->select('priority', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority');

        // Category breakdown (issue_category fallback to category, merged by normalized display name)
        $byCategoryRows = (clone $base)
            ->select(
                \Illuminate\Support\Facades\DB::raw("COALESCE(NULLIF(TRIM(issue_category), ''), NULLIF(TRIM(category), ''), 'Uncategorized') as category_label"),
                \Illuminate\Support\Facades\DB::raw('count(*) as count')
            )
            ->groupBy('category_label')
            ->get()
            ->map(function ($row) {
                $label = match ($row->category_label) {
                    'academic_units'          => 'Academic Concerns',
                    'operational_departments' => 'Operational Departments',
                    default                   => $row->category_label,
                };
                return [
                    'category' => $label,
                    'count'    => (int) $row->count,
                ];
            })
            ->groupBy('category')
            ->map(function ($group, $label) use ($totalConcerns) {
                $sum = (int) $group->sum('count');
                return [
                    'category'     => $label,
                    'raw_category' => $label,
                    'count'        => $sum,
                    'percentage'   => $totalConcerns > 0 ? round(($sum / $totalConcerns) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('count')
            ->values();

        // Daily submission trend (preserved for backward compatibility)
        $trendQuery = clone $base;
        if (empty($validated['date_from'])) {
            $trendQuery->where('submitted_at', '>=', now()->subDays(29)->startOfDay());
        }
        $trend = $trendQuery
            ->select(\Illuminate\Support\Facades\DB::raw('DATE(submitted_at) as date'), \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        // Combined Activity Trend: Submitted vs Resolved & Unresolved Backlog Change
        $resolvedTrendQuery = clone $base;
        $resolvedTrendQuery->whereNotNull('resolved_at')->where('status', 'resolved');
        if (empty($validated['date_from'])) {
            $resolvedTrendQuery->where('resolved_at', '>=', now()->subDays(29)->startOfDay());
        }
        $resolvedByDate = $resolvedTrendQuery
            ->select(\Illuminate\Support\Facades\DB::raw('DATE(resolved_at) as date'), \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('date')
            ->pluck('count', 'date');

        $submittedByDate = $trend->pluck('count', 'date');
        $allDates = $submittedByDate->keys()->merge($resolvedByDate->keys())->unique()->sort()->values();

        $runningBacklogDelta = 0;
        $periodSubmittedTotal = 0;
        $periodResolvedTotal  = 0;
        $activityTrend = [];

        foreach ($allDates as $d) {
            $subCount = (int) ($submittedByDate[$d] ?? 0);
            $resCount = (int) ($resolvedByDate[$d] ?? 0);
            $netDelta = $subCount - $resCount;
            $runningBacklogDelta += $netDelta;
            $periodSubmittedTotal += $subCount;
            $periodResolvedTotal  += $resCount;

            $activityTrend[] = [
                'date'                      => $d,
                'submitted'                 => $subCount,
                'resolved'                  => $resCount,
                'net_change'                => $netDelta,
                'cumulative_backlog_change' => $runningBacklogDelta,
            ];
        }

        if ($periodSubmittedTotal === 0 && $periodResolvedTotal === 0) {
            $activityExplanation = 'No concern submissions or resolutions were recorded during this reporting window.';
        } elseif ($periodSubmittedTotal > $periodResolvedTotal) {
            $diff = $periodSubmittedTotal - $periodResolvedTotal;
            $activityExplanation = "More concerns were submitted ({$periodSubmittedTotal}) than resolved ({$periodResolvedTotal}) during this period, increasing the unresolved backlog by {$diff}.";
        } elseif ($periodResolvedTotal > $periodSubmittedTotal) {
            $diff = $periodResolvedTotal - $periodSubmittedTotal;
            $activityExplanation = "More concerns were resolved ({$periodResolvedTotal}) than submitted ({$periodSubmittedTotal}) during this period, reducing the unresolved backlog by {$diff}.";
        } else {
            $activityExplanation = "Concern submissions ({$periodSubmittedTotal}) and resolutions ({$periodResolvedTotal}) were balanced during this period, keeping the backlog steady.";
        }

        $concerns = (clone $base)
            ->with(['student:id,name,student_id_number', 'academicUnit', 'department'])
            ->orderBy('submitted_at', 'desc')
            ->take(100)
            ->get()
            ->map(function ($c) {
                if ($c->is_anonymous && $c->student) {
                    $c->student->name              = 'Anonymous';
                    $c->student->student_id_number = '********';
                }
                return $c;
            });

        $resolutionAnalytics = app(\App\Services\ResolutionPredictionService::class)->buildAnalytics(
            function (array $row) use ($user, $validated) {
                $matchesScope = false;
                if ($user->dept_id && (int) ($row['dept_id'] ?? 0) === (int) $user->dept_id) {
                    $matchesScope = true;
                }
                if ($user->academic_unit_id && (int) ($row['academic_unit_id'] ?? 0) === (int) $user->academic_unit_id) {
                    $matchesScope = true;
                }
                if (!$matchesScope) {
                    return false;
                }
                if (!empty($validated['priority']) && ($row['priority'] ?? '') !== $validated['priority']) {
                    return false;
                }
                if (!empty($validated['category'])) {
                    $cat = $validated['category'];
                    if (($row['issue_category'] ?? '') !== $cat && ($row['org_category'] ?? '') !== $cat) {
                        return false;
                    }
                }
                return true;
            }
        );

        $medianHours = $resolutionAnalytics['summary']['median_resolution_hours'] ?? null;
        $meanHours   = $resolutionAnalytics['summary']['mean_resolution_hours'] ?? null;
        $sampleSize  = $resolutionAnalytics['summary']['sample_size'] ?? 0;

        // Generate data-backed plain-language insights
        $insights = [];
        if ($totalConcerns === 0) {
            $insights[] = [
                'type' => 'neutral',
                'text' => 'No concerns match the selected filters in your assigned department/unit scope.',
            ];
        } else {
            if ($periodSubmittedTotal > $periodResolvedTotal) {
                $insights[] = [
                    'type' => 'warning',
                    'text' => "Submissions ({$periodSubmittedTotal}) exceeded resolutions ({$periodResolvedTotal}) in the activity window, adding " . ($periodSubmittedTotal - $periodResolvedTotal) . " concern(s) to the active backlog.",
                ];
            } elseif ($periodResolvedTotal > $periodSubmittedTotal && $periodResolvedTotal > 0) {
                $insights[] = [
                    'type' => 'positive',
                    'text' => "Resolutions ({$periodResolvedTotal}) outpaced new submissions ({$periodSubmittedTotal}) in the activity window, reducing the active backlog.",
                ];
            }

            if ($byCategoryRows->isNotEmpty()) {
                $topCat = $byCategoryRows->first();
                $insights[] = [
                    'type' => 'info',
                    'text' => "{$topCat['category']} is the most common category in this report, accounting for {$topCat['count']} of {$totalConcerns} concern(s) ({$topCat['percentage']}%).",
                ];
            }

            if ($criticalUnresolvedCount > 0 || $overdueCount > 0) {
                $insights[] = [
                    'type' => 'critical',
                    'text' => "Immediate attention required: {$criticalUnresolvedCount} critical unresolved concern(s) and {$overdueCount} overdue concern(s) are currently open in your scope.",
                ];
            } else {
                $insights[] = [
                    'type' => 'positive',
                    'text' => 'There are currently 0 critical unresolved concerns and 0 overdue concerns in your assigned scope.',
                ];
            }

            if ($sampleSize > 0 && $medianHours !== null) {
                $caution = $sampleSize < 3 ? ' (limited sample size — interpret with caution)' : '';
                $insights[] = [
                    'type' => 'info',
                    'text' => "Based on {$sampleSize} completed historical case(s), the typical resolution time is {$medianHours}h (average {$meanHours}h){$caution}.",
                ];
            }
        }

        return response()->json([
            'summary' => [
                'total'                   => $totalConcerns,
                'pending'                 => $pendingCount,
                'unresolved'              => $unresolvedCount,
                'resolved'                => $resolvedCount,
                'escalated'               => $escalatedCount,
                'critical'                => $criticalCount,
                'high'                    => $highCount,
                'critical_unresolved'     => $criticalUnresolvedCount,
                'high_unresolved'         => $highUnresolvedCount,
                'overdue'                 => $overdueCount,
                'approaching_deadline'    => $approachingCount,
                'followup_sent'           => $followupSentCount,
                'resolution_rate'         => $resolutionRate,
                'median_resolution_hours' => $medianHours,
                'mean_resolution_hours'   => $meanHours,
                'previous_period'         => $previousPeriod,
            ],
            'by_status'            => $byStatus,
            'by_priority'          => $byPriority,
            'by_category'          => $byCategoryRows,
            'available_categories' => $availableCategories,
            'trend'                => $trend,
            'activity_trend'       => $activityTrend,
            'activity_summary'     => [
                'period_submitted'   => $periodSubmittedTotal,
                'period_resolved'    => $periodResolvedTotal,
                'net_backlog_change' => $periodSubmittedTotal - $periodResolvedTotal,
                'explanation'        => $activityExplanation,
            ],
            'attention_required'   => [
                'critical_unresolved_count' => $criticalUnresolvedCount,
                'high_unresolved_count'     => $highUnresolvedCount,
                'overdue_count'             => $overdueCount,
                'approaching_count'         => $approachingCount,
                'escalated_count'           => $escalatedCount,
                'items'                     => $attentionItems,
            ],
            'sla_summary'          => [
                'enabled'            => $slaService->isEnabled(),
                'targets_hours'      => (array) config('sla.targets_hours', []),
                'followup_threshold' => (float) config('sla.followup_threshold', 0.75),
                'overdue_count'      => $overdueCount,
                'approaching_count'  => $approachingCount,
                'followup_sent_count'=> $followupSentCount,
            ],
            'insights'             => $insights,
            'concerns'             => $concerns,
            'resolution_analytics' => $resolutionAnalytics,
            'generated_at'         => now()->toISOString(),
            'filters_applied'      => array_filter($validated),
        ]);
    }

    /**
     * Export the staff's report as a CSV download.
     */
    public function exportCsv(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date|after_or_equal:date_from',
            'status'    => 'nullable|in:pending,in_progress,escalated,approved,rejected,resolved,closed,withdrawn',
            'priority'  => 'nullable|in:critical,high,medium,low',
            'category'  => 'nullable|string|max:120',
        ]);

        $base = Concern::query();
        $this->scopeForStaff($base, $user);

        if (!empty($validated['date_from'])) {
            $base->where('submitted_at', '>=', $validated['date_from']);
        }
        if (!empty($validated['date_to'])) {
            $base->where('submitted_at', '<=', $validated['date_to'] . ' 23:59:59');
        }
        if (!empty($validated['status'])) {
            $base->where('status', $validated['status']);
        }
        if (!empty($validated['priority'])) {
            $base->where('priority', $validated['priority']);
        }
        if (!empty($validated['category'])) {
            $catFilter = $validated['category'];
            $base->where(function ($q) use ($catFilter) {
                $q->where('issue_category', $catFilter)
                  ->orWhere('category', $catFilter);
            });
        }

        $concerns = $base
            ->with(['student:id,name,student_id_number', 'academicUnit', 'department'])
            ->orderBy('submitted_at', 'desc')
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="staff_report_' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($concerns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Concern ID', 'Title', 'Status', 'Priority', 'Workflow Type',
                'Sentiment', 'Student', 'Department / Unit', 'Submitted At', 'Resolved At',
            ]);
            foreach ($concerns as $c) {
                $studentName = $c->is_anonymous ? 'Anonymous' : ($c->student->name ?? 'N/A');
                $deptName    = $c->department->name ?? $c->academicUnit->name ?? 'N/A';
                fputcsv($handle, [
                    $c->concern_id,
                    $c->title,
                    $c->status,
                    $c->priority ?? 'N/A',
                    $c->workflow_type ?? 'N/A',
                    $c->sentiment_label ?? 'N/A',
                    $studentName,
                    $deptName,
                    $c->submitted_at,
                    $c->resolved_at ?? '',
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
