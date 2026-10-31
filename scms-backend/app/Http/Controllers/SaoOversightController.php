<?php

namespace App\Http\Controllers;

use App\Models\AcademicUnit;
use App\Models\Concern;
use App\Models\ConcernRouting;
use App\Models\Department;
use App\Models\Message;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaoOversightController extends Controller
{
    /**
     * Get institution-wide concern oversight analytics and aggregates.
     * Accessible only by SAO role.
     * Enforces aggregate-only data without exposing unauthorized individual records.
     */
    public function getOversight(Request $request)
    {
        $validated = $request->validate([
            'date_from'        => 'nullable|date',
            'date_to'          => 'nullable|date|after_or_equal:date_from',
            'academic_unit_id' => 'nullable|exists:academic_units,id',
            'dept_id'          => 'nullable|exists:departments,id',
            'status'           => 'nullable|string',
            'priority'         => 'nullable|string',
        ]);

        $query = Concern::query();

        // Apply filters
        if (!empty($validated['date_from'])) {
            $query->whereDate('submitted_at', '>=', $validated['date_from']);
        }
        if (!empty($validated['date_to'])) {
            $query->whereDate('submitted_at', '<=', $validated['date_to']);
        }
        if (!empty($validated['academic_unit_id'])) {
            $query->where('academic_unit_id', $validated['academic_unit_id']);
        }
        if (!empty($validated['dept_id'])) {
            $query->where('dept_id', $validated['dept_id']);
        }
        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (!empty($validated['priority'])) {
            $query->where('priority', $validated['priority']);
        }

        // 1. Overall Aggregate KPIs
        $totalSubmitted = (clone $query)->count();
        $pending        = (clone $query)->where('status', 'pending')->count();
        $inProgress     = (clone $query)->where('status', 'in_progress')->count();
        $resolved       = (clone $query)->where('status', 'resolved')->count();
        $rejected       = (clone $query)->whereIn('status', ['rejected', 'returned'])->count();
        $escalated      = (clone $query)->where('status', 'escalated')->count();
        $critical       = (clone $query)->where('priority', 'critical')->count();
        $highPriority   = (clone $query)->where('priority', 'high')->count();

        // 2. Critical Concern Monitoring
        $activeCritical = (clone $query)
            ->where('priority', 'critical')
            ->whereNotIn('status', ['resolved', 'closed', 'withdrawn'])
            ->count();

        $recentCritical = (clone $query)
            ->where('priority', 'critical')
            ->where('submitted_at', '>=', now()->subDays(7))
            ->count();

        // Group active critical by unit / department (Aggregate Only)
        $criticalByUnit = (clone $query)
            ->where('priority', 'critical')
            ->whereNotIn('status', ['resolved', 'closed', 'withdrawn'])
            ->whereNotNull('academic_unit_id')
            ->select('academic_unit_id', DB::raw('count(*) as count'), DB::raw('MAX(submitted_at) as latest_submission'))
            ->groupBy('academic_unit_id')
            ->with('academicUnit:id,name')
            ->get()
            ->map(fn ($r) => [
                'type'              => 'academic_unit',
                'name'              => $r->academicUnit?->name ?? 'Unknown Unit',
                'count'             => (int) $r->count,
                'latest_submission' => $r->latest_submission,
            ]);

        $criticalByDept = (clone $query)
            ->where('priority', 'critical')
            ->whereNotIn('status', ['resolved', 'closed', 'withdrawn'])
            ->whereNotNull('dept_id')
            ->select('dept_id', DB::raw('count(*) as count'), DB::raw('MAX(submitted_at) as latest_submission'))
            ->groupBy('dept_id')
            ->with('department:id,name')
            ->get()
            ->map(fn ($r) => [
                'type'              => 'department',
                'name'              => $r->department?->name ?? 'Unknown Department',
                'count'             => (int) $r->count,
                'latest_submission' => $r->latest_submission,
            ]);

        $criticalBreakdown = $criticalByUnit->concat($criticalByDept);

        // 3. Escalation & Workflow Monitoring
        $awaitingDean = (clone $query)
            ->where('status', 'escalated')
            ->where('workflow_type', 'academic')
            ->count();

        $awaitingCad = (clone $query)
            ->where('status', 'escalated')
            ->whereNotNull('forwarded_to_cad_at')
            ->count();

        // Average Resolution Time Calculation (real DB calculation)
        $resolvedConcerns = (clone $query)
            ->whereNotNull('submitted_at')
            ->whereNotNull('resolved_at')
            ->where('status', 'resolved')
            ->select('submitted_at', 'resolved_at')
            ->get();

        $avgResolutionHours = null;
        $avgResolutionDays = null;
        if ($resolvedConcerns->isNotEmpty()) {
            $totalHours = $resolvedConcerns->reduce(function ($carry, $c) {
                $sub = Carbon::parse($c->submitted_at);
                $res = Carbon::parse($c->resolved_at);
                return $carry + $sub->diffInHours($res);
            }, 0);
            $avgResolutionHours = round($totalHours / $resolvedConcerns->count(), 1);
            $avgResolutionDays = round($avgResolutionHours / 24, 1);
        }

        // 4. Monitoring by Academic Unit
        $academicUnits = AcademicUnit::select('id', 'name')->get();
        $unitStats = [];
        foreach ($academicUnits as $unit) {
            $unitQuery = (clone $query)->where('academic_unit_id', $unit->id);
            $unitCount = (clone $unitQuery)->count();
            if ($unitCount > 0 || empty($validated['academic_unit_id'])) {
                $unitStats[] = [
                    'id'          => $unit->id,
                    'name'        => $unit->name,
                    'total'       => $unitCount,
                    'pending'     => (clone $unitQuery)->where('status', 'pending')->count(),
                    'in_progress' => (clone $unitQuery)->where('status', 'in_progress')->count(),
                    'escalated'   => (clone $unitQuery)->where('status', 'escalated')->count(),
                    'resolved'    => (clone $unitQuery)->where('status', 'resolved')->count(),
                    'critical'    => (clone $unitQuery)->where('priority', 'critical')->count(),
                ];
            }
        }

        // 5. Monitoring by Department
        $departments = Department::select('id', 'name')->get();
        $deptStats = [];
        foreach ($departments as $dept) {
            $deptQuery = (clone $query)->where('dept_id', $dept->id);
            $deptCount = (clone $deptQuery)->count();
            if ($deptCount > 0 || empty($validated['dept_id'])) {
                $deptStats[] = [
                    'id'          => $dept->id,
                    'name'        => $dept->name,
                    'total'       => $deptCount,
                    'pending'     => (clone $deptQuery)->where('status', 'pending')->count(),
                    'in_progress' => (clone $deptQuery)->where('status', 'in_progress')->count(),
                    'escalated'   => (clone $deptQuery)->where('status', 'escalated')->count(),
                    'resolved'    => (clone $deptQuery)->where('status', 'resolved')->count(),
                    'critical'    => (clone $deptQuery)->where('priority', 'critical')->count(),
                ];
            }
        }

        // 6. Category Breakdown (Organizational Routing)
        $categories = (clone $query)
            ->whereNotNull('category')
            ->select('category', DB::raw('count(*) as count'))
            ->groupBy('category')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => [
                'category'   => $r->category,
                'count'      => (int) $r->count,
                'percentage' => $totalSubmitted > 0 ? round(((int) $r->count / $totalSubmitted) * 100, 1) : 0,
            ]);

        // 6b. AI Issue Category Breakdown (Problem Domain)
        $issueCategories = (clone $query)
            ->whereNotNull('issue_category')
            ->select('issue_category', DB::raw('count(*) as count'))
            ->groupBy('issue_category')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => [
                'category'   => $r->issue_category,
                'count'      => (int) $r->count,
                'percentage' => $totalSubmitted > 0 ? round(((int) $r->count / $totalSubmitted) * 100, 1) : 0,
            ]);

        $issueSubcategories = (clone $query)
            ->whereNotNull('issue_subcategory')
            ->select('issue_category', 'issue_subcategory', DB::raw('count(*) as count'))
            ->groupBy('issue_category', 'issue_subcategory')
            ->orderByDesc('count')
            ->take(15)
            ->get()
            ->map(fn ($r) => [
                'category'    => $r->issue_category,
                'subcategory' => $r->issue_subcategory,
                'count'       => (int) $r->count,
            ]);

        // 7. Status Distribution
        $statusDistribution = (clone $query)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        // 8. Priority Distribution
        $priorityDistribution = (clone $query)
            ->select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority');

        // 9. Recurring Issue Patterns
        // Top categories per department/academic unit
        $recurringByDept = (clone $query)
            ->whereNotNull('dept_id')
            ->whereNotNull('category')
            ->select('dept_id', 'category', DB::raw('count(*) as occurrences'))
            ->groupBy('dept_id', 'category')
            ->having('occurrences', '>=', 2)
            ->orderByDesc('occurrences')
            ->with('department:id,name')
            ->take(10)
            ->get()
            ->map(fn ($r) => [
                'department'  => $r->department?->name ?? 'Unknown',
                'category'    => $r->category,
                'occurrences' => (int) $r->occurrences,
            ]);

        $recurringByUnit = (clone $query)
            ->whereNotNull('academic_unit_id')
            ->whereNotNull('category')
            ->select('academic_unit_id', 'category', DB::raw('count(*) as occurrences'))
            ->groupBy('academic_unit_id', 'category')
            ->having('occurrences', '>=', 2)
            ->orderByDesc('occurrences')
            ->with('academicUnit:id,name')
            ->take(10)
            ->get()
            ->map(fn ($r) => [
                'academic_unit' => $r->academicUnit?->name ?? 'Unknown',
                'category'      => $r->category,
                'occurrences'   => (int) $r->occurrences,
            ]);

        // 10. Volume Trend Over Time (Last 6 Months)
        $sixMonthsAgo = now()->subMonths(6)->startOfMonth();
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $monthExpr = $isSqlite ? "strftime('%Y-%m', submitted_at)" : "DATE_FORMAT(submitted_at, '%Y-%m')";

        $monthlyTrend = (clone $query)
            ->where('submitted_at', '>=', $sixMonthsAgo)
            ->select(
                DB::raw("{$monthExpr} as month"),
                DB::raw('count(*) as total'),
                DB::raw("SUM(CASE WHEN priority = 'critical' THEN 1 ELSE 0 END) as critical"),
                DB::raw("SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved")
            )
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get()
            ->map(fn ($r) => [
                'month'    => (string) $r->month,
                'total'    => (int) $r->total,
                'critical' => (int) $r->critical,
                'resolved' => (int) $r->resolved,
            ]);

        $resolutionAnalytics = app(\App\Services\ResolutionPredictionService::class)->buildAnalytics(
            function (array $row) use ($validated) {
                if (!empty($validated['academic_unit_id']) && (int) ($row['academic_unit_id'] ?? 0) !== (int) $validated['academic_unit_id']) {
                    return false;
                }
                if (!empty($validated['dept_id']) && (int) ($row['dept_id'] ?? 0) !== (int) $validated['dept_id']) {
                    return false;
                }
                if (!empty($validated['priority']) && ($row['priority'] ?? '') !== $validated['priority']) {
                    return false;
                }
                return true;
            }
        );

        return response()->json([
            'summary' => [
                'total_submitted'         => $totalSubmitted,
                'pending'                 => $pending,
                'in_progress'             => $inProgress,
                'resolved'                => $resolved,
                'rejected'                => $rejected,
                'escalated'               => $escalated,
                'critical'                => $critical,
                'high_priority'           => $highPriority,
                'active_critical'         => $activeCritical,
                'recent_critical'         => $recentCritical,
                'awaiting_dean_review'    => $awaitingDean,
                'awaiting_cad_approval'   => $awaitingCad,
                'avg_resolution_hours'    => $avgResolutionHours,
                'avg_resolution_days'     => $avgResolutionDays,
                'median_resolution_hours' => $resolutionAnalytics['summary']['median_resolution_hours'] ?? null,
                'median_resolution_days'  => $resolutionAnalytics['summary']['median_resolution_days'] ?? null,
            ],
            'sla_monitoring' => $this->buildSlaMonitoring(clone $query),
            'resolution_analytics'           => $resolutionAnalytics,
            'critical_breakdown'             => $criticalBreakdown,
            'academic_units'                 => $unitStats,
            'departments'                    => $deptStats,
            'categories'                     => $categories,
            'issue_categories'               => $issueCategories,
            'issue_subcategories'            => $issueSubcategories,
            'dual_distribution'              => [
                'by_organization' => [
                    'academic_units' => $unitStats,
                    'departments'    => $deptStats,
                ],
                'by_issue_category' => $issueCategories,
            ],
            'status_distribution'            => $statusDistribution,
            'priority_distribution'          => $priorityDistribution,
            'recurring_issues'           => [
                'by_department'          => $recurringByDept,
                'by_academic_unit'       => $recurringByUnit,
            ],
            'monthly_trend'              => $monthlyTrend,
            'filters_applied'            => array_filter($validated),
        ]);
    }

    /**
     * Institution-wide resolution-target aggregates (counts only — no
     * individual concern identifiers are returned, preserving Option A).
     *
     * @return array<string, mixed>
     */
    private function buildSlaMonitoring($query): array
    {
        $sla = app(\App\Services\ConcernSlaService::class);

        if (!$sla->isEnabled()) {
            return [
                'supported'         => false,
                'overdue_count'     => 0,
                'approaching_count' => 0,
                'followup_sent_count'    => 0,
                'auto_escalated_count'   => 0,
                'notice'            => 'Resolution-target monitoring is disabled (SLA_ENABLED=false).',
            ];
        }

        $open = (clone $query)
            ->whereIn('status', (array) config('sla.monitored_statuses', []))
            ->with('routingHistory')
            ->get();

        $overdue = 0;
        $approaching = 0;
        $followupSent = 0;
        foreach ($open as $concern) {
            $state = $sla->describe($concern)['state'];
            if ($state === 'overdue') {
                $overdue++;
            } elseif ($state === 'followup_sent') {
                $followupSent++;
                $approaching++;
            } elseif ($state === 'approaching') {
                $approaching++;
            }
        }

        $concernIds = (clone $query)->pluck('concern_id');
        $autoEscalated = ConcernRouting::whereIn('concern_id', $concernIds)
            ->where('action', 'forwarded_to_dean')
            ->whereNull('performed_by')
            ->where('performed_by_name', \App\Services\ConcernSlaService::SYSTEM_ACTOR)
            ->distinct()
            ->count('concern_id');

        $targets = collect((array) config('sla.targets_hours', []))
            ->map(fn ($h, $p) => ucfirst($p) . ' ' . rtrim(rtrim(number_format((float) $h, 2, '.', ''), '0'), '.') . 'h')
            ->implode(', ');

        return [
            'supported'            => true,
            'overdue_count'        => $overdue,
            'approaching_count'    => $approaching,
            'followup_sent_count'  => $followupSent,
            'auto_escalated_count' => $autoEscalated,
            'targets_hours'        => (array) config('sla.targets_hours', []),
            'followup_threshold'   => (float) config('sla.followup_threshold', 0.75),
            'notice'               => "Resolution targets in effect: {$targets}. Follow-up is sent at "
                . (int) round(((float) config('sla.followup_threshold', 0.75)) * 100)
                . '% of the target. Target values are configurable and pending institutional approval.',
        ];
    }

    /**
     * Check if a concern is assigned to or involves the Student Affairs Office (SAO).
     */
    private function isAssignedToSao(Concern $concern): bool
    {
        $saoDept = Department::where('name', 'like', '%SAO%')
            ->orWhere('name', 'like', '%Student Affairs%')
            ->first();

        $saoDeptId = $saoDept ? $saoDept->id : null;

        if ($saoDeptId && (int) $concern->dept_id === (int) $saoDeptId) {
            return true;
        }

        if ($saoDept) {
            return ConcernRouting::where('concern_id', $concern->concern_id)
                ->where(function ($q) use ($saoDept) {
                    $q->where('notes', 'like', '%SAO%')
                      ->orWhere('notes', 'like', '%Student Affairs%');
                })
                ->exists();
        }

        return false;
    }

    /**
     * Get list of concerns explicitly assigned to the Student Affairs Office (SAO).
     * Strictly restricted to concerns assigned to SAO or routed to SAO in workflow.
     */
    public function assignedConcerns(Request $request)
    {
        $saoDept = Department::where('name', 'like', '%SAO%')
            ->orWhere('name', 'like', '%Student Affairs%')
            ->first();

        $saoDeptId = $saoDept ? $saoDept->id : null;

        $query = Concern::query();

        if ($saoDeptId) {
            $query->where(function ($q) use ($saoDeptId, $saoDept) {
                $q->where('dept_id', $saoDeptId)
                  ->orWhereHas('routingHistory', function ($rh) use ($saoDept) {
                      $rh->where('notes', 'like', '%SAO%')
                         ->orWhere('notes', 'like', '%Student Affairs%');
                  });
            });
        } else {
            $query->whereRaw('1 = 0');
        }

        // Optional filtering
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->query('priority'));
        }
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $concerns = $query->with([
            'student' => function ($q) {
                $q->select('id', 'name', 'student_id_number', 'email');
            },
            'department:id,name',
            'academicUnit:id,name',
            'priorityOverriddenBy:id,name,role',
            'routingHistory',
        ])
        ->orderBy('submitted_at', 'desc')
        ->paginate(15);

        $sla = app(\App\Services\ConcernSlaService::class);
        $predictor = app(\App\Services\ResolutionPredictionService::class);
        $dataset = $predictor->getCleanHistoricalDataset();

        // Mask student identity if anonymous
        $concerns->getCollection()->transform(function ($concern) use ($sla, $predictor, $dataset) {
            if ($concern->is_anonymous && $concern->student) {
                $concern->student->name = 'Anonymous Student';
                $concern->student->student_id_number = '********';
                $concern->student->email = 'anonymous@scms.local';
            }
            $concern->sla = $sla->describe($concern);
            $concern->prediction = $predictor->predict($concern, $dataset);
            return $concern;
        });

        return response()->json([
            'concerns' => $concerns,
            'sao_department' => $saoDept,
        ]);
    }

    /**
     * Option A — Strict Individual Concern Access Guard for SAO.
     * SAO can view individual concern details ONLY when:
     * 1. The concern is assigned to the Student Affairs Office (dept_id = SAO department), OR
     * 2. The workflow explicitly involved/assigned SAO in routing history.
     *
     * Otherwise returns HTTP 403 Forbidden.
     */
    public function showConcern(Request $request, int $id)
    {
        $concern = Concern::with(['academicUnit', 'department', 'student'])->find($id);
        if (!$concern) {
            return response()->json(['message' => 'Concern not found.'], 404);
        }

        if (!$this->isAssignedToSao($concern)) {
            return response()->json([
                'message' => 'Unauthorized. In accordance with strict SAO oversight boundaries (Option A), SAO administrative oversight is restricted to institution-wide aggregate monitoring. Individual concern details and internal communications may only be viewed when the concern is explicitly assigned to the Student Affairs Office.',
            ], 403);
        }

        // If assigned to SAO, load routing and messages
        $concern->load([
            'routingHistory.performer',
            'priorityOverriddenBy:id,name,role',
            'messages.sender:id,name,role',
        ]);

        // Mask student identity if anonymous
        if ($concern->is_anonymous && $concern->student) {
            $concern->student->name = 'Anonymous Student';
            $concern->student->student_id_number = '********';
            $concern->student->email = 'anonymous@scms.local';
        }

        // Resolution-target status and historical prediction (only reached for SAO-assigned concerns)
        $concern->sla = app(\App\Services\ConcernSlaService::class)->describe($concern);
        $concern->prediction = app(\App\Services\ResolutionPredictionService::class)->predict($concern);

        return response()->json([
            'concern' => $concern,
        ]);
    }

    /**
     * Reply to an assigned concern (send a message as SAO).
     */
    public function reply(Request $request, int $id)
    {
        $concern = Concern::find($id);
        if (!$concern) {
            return response()->json(['message' => 'Concern not found.'], 404);
        }

        if (!$this->isAssignedToSao($concern)) {
            return response()->json([
                'message' => 'Unauthorized action for this concern.',
            ], 403);
        }

        $validated = $request->validate([
            'body' => 'required|string',
            'is_internal' => 'boolean',
        ]);

        $message = Message::create([
            'concern_id'  => $concern->concern_id,
            'sender_id'   => $request->user()->id,
            'body'        => $validated['body'],
            'is_internal' => $validated['is_internal'] ?? false,
        ]);

        $concern->update(['last_updated' => now()]);

        return response()->json([
            'message' => 'Message sent successfully.',
            'data'    => $message->load('sender:id,name,role'),
        ], 201);
    }

    /**
     * Override priority for a concern assigned to SAO.
     */
    public function priorityOverride(Request $request, int $id)
    {
        $concern = Concern::find($id);
        if (!$concern) {
            return response()->json(['message' => 'Concern not found.'], 404);
        }

        if (!$this->isAssignedToSao($concern)) {
            return response()->json([
                'message' => 'Unauthorized. SAO priority override is restricted to concerns explicitly assigned to the Student Affairs Office.',
            ], 403);
        }

        $validated = $request->validate([
            'priority' => 'required|in:low,medium,high,critical',
            'reason'   => 'required|string|min:5|max:1000',
        ]);

        $user = $request->user();

        DB::transaction(function () use ($concern, $validated, $user) {
            $oldPriority = $concern->priority;

            $concern->update([
                'priority'                 => $validated['priority'],
                'is_priority_overridden'   => true,
                'priority_override_reason' => $validated['reason'],
                'priority_overridden_by'   => $user->id,
                'priority_overridden_at'   => now(),
                'last_updated'             => now(),
            ]);

            ConcernRouting::create([
                'concern_id'   => $concern->concern_id,
                'action'       => 'priority_overridden',
                'performed_by' => $user->id,
                'notes'        => "Priority overridden from '{$oldPriority}' to '{$validated['priority']}'. Reason: {$validated['reason']}",
            ]);
        });

        if ($validated['priority'] === 'critical') {
            try {
                \App\Notifications\ConcernWorkflowNotification::sendCriticalConcernNotification(
                    $concern->fresh(),
                    'SAO Priority Override'
                );
            } catch (\Throwable $ce) {
                \Illuminate\Support\Facades\Log::warning('Critical concern notification failed on SAO override: ' . $ce->getMessage());
            }
        }

        $freshConcern = $concern->fresh([
            'academicUnit',
            'department',
            'priorityOverriddenBy:id,name,role',
            'routingHistory.performer:id,name,role',
        ]);
        $freshConcern->sla = app(\App\Services\ConcernSlaService::class)->describe($freshConcern);
        $freshConcern->prediction = app(\App\Services\ResolutionPredictionService::class)->predict($freshConcern);

        return response()->json([
            'message' => 'Priority overridden successfully.',
            'concern' => $freshConcern,
        ]);
    }
}
