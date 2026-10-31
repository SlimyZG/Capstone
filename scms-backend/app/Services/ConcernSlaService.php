<?php

namespace App\Services;

use App\Models\Concern;
use App\Models\ConcernRouting;
use App\Models\User;
use App\Notifications\ConcernWorkflowNotification;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Concern resolution-target (SLA) monitoring.
 *
 * Two distinct, configurable events (config/sla.php):
 *  1. FOLLOW-UP at the follow-up threshold (default 75%) of the resolution
 *     target: reminder notification to the assigned Department Staff only.
 *  2. DEADLINE at 100% of the resolution target: academic concerns are
 *     escalated to the Dean of their academic unit (existing workflow);
 *     otherwise an overdue notice is sent without changing the workflow.
 *
 * No extra tables/columns are used. Every event is written to the existing
 * concern_routing audit history, which also serves as the idempotency record
 * (an event is processed at most once per concern per clock cycle).
 */
class ConcernSlaService
{
    public const ACTION_FOLLOWUP = 'sla_followup_75';
    public const ACTION_DEADLINE = 'sla_deadline_reached';
    public const SYSTEM_ACTOR = 'System (Automatic SLA Monitor)';

    /** Internal audit actions that must never be shown to students. */
    public const INTERNAL_ACTIONS = [self::ACTION_FOLLOWUP, self::ACTION_DEADLINE];

    public function isEnabled(): bool
    {
        return (bool) config('sla.enabled', false);
    }

    /**
     * Priority used to look up the resolution target.
     */
    public function effectivePriority(Concern $concern): string
    {
        $targets = (array) config('sla.targets_hours', []);
        if ($concern->priority && array_key_exists($concern->priority, $targets)) {
            return $concern->priority;
        }

        return (string) config('sla.default_priority', 'medium');
    }

    /**
     * Resolution target in hours for the concern's current priority, or null.
     */
    public function targetHours(Concern $concern): ?float
    {
        $hours = config('sla.targets_hours.' . $this->effectivePriority($concern));

        return ($hours !== null && (float) $hours > 0) ? (float) $hours : null;
    }

    /**
     * Start of the SLA clock: submitted_at, or the latest student reopen.
     */
    public function clockStart(Concern $concern, ?Collection $records = null): ?Carbon
    {
        $start = $concern->submitted_at ? Carbon::parse($concern->submitted_at) : null;
        $records ??= $this->routingRecords($concern);

        $reopen = $records
            ->filter(fn ($r) => $r->action === 'status_changed'
                && str_starts_with((string) $r->notes, 'Concern reopened')
                && $r->created_at)
            ->sortByDesc('created_at')
            ->first();

        if ($reopen && (!$start || Carbon::parse($reopen->created_at)->gt($start))) {
            $start = Carbon::parse($reopen->created_at);
        }

        return $start;
    }

    /**
     * Build the SLA status payload for authorized staff/Dean/SAO views.
     *
     * @return array<string, mixed>
     */
    public function describe(Concern $concern, ?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : Carbon::now();
        $records = $this->routingRecords($concern);
        $start = $this->clockStart($concern, $records);
        $hours = $this->targetHours($concern);
        $followupThreshold = (float) config('sla.followup_threshold', 0.75);
        $approachingThreshold = (float) config('sla.approaching_threshold', 0.50);

        $data = [
            'enabled'              => $this->isEnabled(),
            'priority_basis'       => $this->effectivePriority($concern),
            'target_hours'         => $hours,
            'started_at'           => $start?->toIso8601String(),
            'deadline_at'          => null,
            'followup_due_at'      => null,
            'followup_threshold'   => $followupThreshold,
            'elapsed_seconds'      => null,
            'remaining_seconds'    => null,
            'elapsed_ratio'        => null,
            'followup_sent_at'     => null,
            'deadline_reached_at'  => null,
            'escalated_by_sla'     => false,
            'resolved_within_target' => null,
            'state'                => 'not_applicable',
        ];

        if ($start && $hours) {
            $targetSeconds = (int) round($hours * 3600);
            $deadline = $start->copy()->addSeconds($targetSeconds);
            $endPoint = $now;
            if ($concern->status === 'resolved' && $concern->resolved_at) {
                $endPoint = Carbon::parse($concern->resolved_at);
            }
            $elapsed = $endPoint->getTimestamp() - $start->getTimestamp();

            $followup = $this->eventSince($records, self::ACTION_FOLLOWUP, $start);
            $deadlineEvent = $this->eventSince($records, self::ACTION_DEADLINE, $start);
            $autoEscalation = $records->first(fn ($r) => $r->action === 'forwarded_to_dean'
                && $r->performed_by === null
                && $r->performed_by_name === self::SYSTEM_ACTOR
                && $r->created_at
                && Carbon::parse($r->created_at)->gte($start));

            $data = array_merge($data, [
                'deadline_at'         => $deadline->toIso8601String(),
                'followup_due_at'     => $start->copy()->addSeconds((int) round($targetSeconds * $followupThreshold))->toIso8601String(),
                'elapsed_seconds'     => max(0, $elapsed),
                'remaining_seconds'   => $deadline->getTimestamp() - $endPoint->getTimestamp(),
                'elapsed_ratio'       => round(max(0, $elapsed) / $targetSeconds, 4),
                'followup_sent_at'    => $followup ? Carbon::parse($followup->created_at)->toIso8601String() : null,
                'deadline_reached_at' => $deadlineEvent ? Carbon::parse($deadlineEvent->created_at)->toIso8601String() : null,
                'escalated_by_sla'    => (bool) $autoEscalation,
            ]);

            if ($concern->status === 'resolved' && $concern->resolved_at) {
                $data['resolved_within_target'] = Carbon::parse($concern->resolved_at)->lte($deadline);
            }

            $ratio = $data['elapsed_ratio'];
            if ($ratio >= 1) {
                $data['state'] = 'overdue';
            } elseif ($followup && $ratio >= $followupThreshold) {
                $data['state'] = 'followup_sent';
            } elseif ($ratio >= $approachingThreshold) {
                $data['state'] = 'approaching';
            } else {
                $data['state'] = 'normal';
            }
        }

        // Workflow state takes precedence over timing state.
        if ($concern->status === 'resolved') {
            $data['state'] = 'resolved';
        } elseif (in_array($concern->status, ['closed', 'rejected', 'withdrawn'], true)) {
            $data['state'] = 'closed';
        } elseif ($concern->status === 'escalated') {
            $data['state'] = 'escalated';
        }

        return $data;
    }

    /**
     * Scheduler entry point: evaluate every monitored, unresolved concern.
     *
     * @return array<string, int|bool>
     */
    public function process(?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : Carbon::now();
        $stats = ['enabled' => $this->isEnabled(), 'scanned' => 0, 'followups' => 0, 'escalations' => 0, 'overdue_notices' => 0];

        if (!$this->isEnabled()) {
            return $stats;
        }

        Concern::whereIn('status', (array) config('sla.monitored_statuses', []))
            ->whereNotNull('submitted_at')
            ->select('concern_id')
            ->chunkById(200, function ($concerns) use ($now, &$stats) {
                foreach ($concerns as $row) {
                    $stats['scanned']++;
                    try {
                        foreach ($this->processConcern((int) $row->concern_id, $now) as $action) {
                            $stats[$action]++;
                        }
                    } catch (\Throwable $e) {
                        Log::warning("SLA processing failed for concern #{$row->concern_id}: " . $e->getMessage());
                    }
                }
            }, 'concern_id');

        return $stats;
    }

    /**
     * Evaluate one concern under a row lock. Idempotent.
     *
     * @return array<int, string> Actions performed ('followups'|'escalations'|'overdue_notices')
     */
    public function processConcern(int $concernId, CarbonInterface $now): array
    {
        $now = Carbon::instance($now);

        return DB::transaction(function () use ($concernId, $now) {
            $concern = Concern::where('concern_id', $concernId)->lockForUpdate()->first();
            if (!$concern || !in_array($concern->status, (array) config('sla.monitored_statuses', []), true)) {
                return [];
            }

            $records = $concern->routingHistory()->get();
            $start = $this->clockStart($concern, $records);
            $hours = $this->targetHours($concern);
            if (!$start || !$hours) {
                return [];
            }

            $targetSeconds = (int) round($hours * 3600);
            $deadline = $start->copy()->addSeconds($targetSeconds);
            $elapsed = $now->getTimestamp() - $start->getTimestamp();
            $ratio = $elapsed / $targetSeconds;
            $followupThreshold = (float) config('sla.followup_threshold', 0.75);

            $performed = [];

            // 1. Deadline (100%) — takes precedence; a separate 75% reminder is
            //    not sent for a concern that is already past its deadline.
            if ($ratio >= 1) {
                if (!$this->eventSince($records, self::ACTION_DEADLINE, $start)) {
                    $performed[] = $this->handleDeadline($concern, $hours, $deadline, $elapsed, $now);
                }
                return $performed;
            }

            // 2. Follow-up (75%) — reminder only, never an escalation.
            if ($ratio >= $followupThreshold && !$this->eventSince($records, self::ACTION_FOLLOWUP, $start)) {
                $this->sendFollowup($concern, $hours, $deadline, $ratio, $now);
                $performed[] = 'followups';
            }

            return $performed;
        });
    }

    private function sendFollowup(Concern $concern, float $hours, Carbon $deadline, float $ratio, Carbon $now): void
    {
        $priority = $this->effectivePriority($concern);
        $remaining = $this->humanDuration($deadline->getTimestamp() - $now->getTimestamp());
        $deadlineText = $deadline->format('M j, Y g:i A');
        $staff = $this->assignedStaff($concern);
        $pct = (int) round(((float) config('sla.followup_threshold', 0.75)) * 100);

        ConcernRouting::create([
            'concern_id'        => $concern->concern_id,
            'action'            => self::ACTION_FOLLOWUP,
            'performed_by'      => null,
            'performed_by_name' => self::SYSTEM_ACTOR,
            'notes'             => sprintf(
                'Automatic %d%% follow-up: %d%% of the %s-hour %s resolution target has elapsed. Remaining time: %s (deadline %s). Notified %d assigned staff member(s).',
                $pct,
                (int) floor($ratio * 100),
                $this->formatHours($hours),
                $priority,
                $remaining,
                $deadlineText,
                $staff->count()
            ),
        ]);

        foreach ($staff as $member) {
            $this->safeNotify($member, new ConcernWorkflowNotification(
                (int) $concern->concern_id,
                "Follow-up: Concern #{$concern->concern_id} is nearing its resolution deadline",
                "Concern '{$concern->title}' has reached {$pct}% of its {$priority}-priority resolution target. Remaining time: {$remaining} (deadline {$deadlineText}).",
                'sla_followup',
                $concern->workflow_type ?? 'academic',
                "/dept-staff/concerns/{$concern->concern_id}",
                [
                    'deadline_at'       => $deadline->toIso8601String(),
                    'remaining_seconds' => $deadline->getTimestamp() - $now->getTimestamp(),
                    'priority'          => $priority,
                ]
            ));
        }
    }

    /**
     * @return string 'escalations'|'overdue_notices'
     */
    private function handleDeadline(Concern $concern, float $hours, Carbon $deadline, int $elapsed, Carbon $now): string
    {
        $priority = $this->effectivePriority($concern);
        $isAcademic = ($concern->workflow_type === 'academic' || $concern->category === 'academic_units');
        $action = (string) config('sla.deadline_action.' . ($isAcademic ? 'academic' : 'non_academic'), 'notify_only');
        $overdueBy = $this->humanDuration($now->getTimestamp() - $deadline->getTimestamp());
        $deadlineText = $deadline->format('M j, Y g:i A');
        $staff = $this->assignedStaff($concern);
        $baseNote = sprintf(
            'Resolution deadline reached: the %s-hour %s resolution target expired on %s (overdue by %s) while the concern was still %s.',
            $this->formatHours($hours),
            $priority,
            $deadlineText,
            $overdueBy,
            str_replace('_', ' ', $concern->status)
        );

        $deans = collect();
        $blockReason = null;
        if ($action !== 'escalate_to_dean') {
            $blockReason = $isAcademic
                ? 'Automatic escalation is disabled for academic concerns by configuration.'
                : 'Non-academic workflow is configured for overdue notification only; the workflow was not changed.';
        } elseif (!in_array($concern->status, (array) config('sla.escalatable_statuses', []), true)) {
            $blockReason = "Status '{$concern->status}' is not eligible for automatic escalation; the workflow was not changed.";
        } elseif (!$concern->academic_unit_id) {
            $blockReason = 'The concern has no academic unit, so no responsible Dean could be determined.';
        } else {
            $deans = User::where('role', 'dean')->where('academic_unit_id', $concern->academic_unit_id)->get();
            if ($deans->isEmpty()) {
                $blockReason = 'No Dean account is assigned to this academic unit; the workflow was not changed.';
            }
        }

        // ── Notify-only path ───────────────────────────────────────────────
        if ($blockReason !== null) {
            ConcernRouting::create([
                'concern_id'        => $concern->concern_id,
                'action'            => self::ACTION_DEADLINE,
                'performed_by'      => null,
                'performed_by_name' => self::SYSTEM_ACTOR,
                'notes'             => "{$baseNote} Overdue notice sent to {$staff->count()} assigned staff member(s). {$blockReason}",
            ]);

            foreach ($staff as $member) {
                $this->safeNotify($member, new ConcernWorkflowNotification(
                    (int) $concern->concern_id,
                    "Overdue: Concern #{$concern->concern_id} has passed its resolution deadline",
                    "Concern '{$concern->title}' passed its {$priority}-priority resolution deadline ({$deadlineText}) and is overdue by {$overdueBy}.",
                    'sla_overdue',
                    $concern->workflow_type ?? 'non_academic',
                    "/dept-staff/concerns/{$concern->concern_id}",
                    ['deadline_at' => $deadline->toIso8601String(), 'priority' => $priority]
                ));
            }

            return 'overdue_notices';
        }

        // ── Academic escalation to the Dean (existing workflow) ───────────
        $concern->update([
            'status'       => 'escalated',
            'last_updated' => $now,
        ]);

        ConcernRouting::create([
            'concern_id'        => $concern->concern_id,
            'action'            => self::ACTION_DEADLINE,
            'performed_by'      => null,
            'performed_by_name' => self::SYSTEM_ACTOR,
            'notes'             => "{$baseNote} Automatically escalated to the Dean of the academic unit; {$deans->count()} Dean(s) and {$staff->count()} staff member(s) notified.",
        ]);

        // Same action name as the manual workflow so existing Dean, student
        // timeline and notification logic recognise the escalation.
        ConcernRouting::create([
            'concern_id'        => $concern->concern_id,
            'action'            => 'forwarded_to_dean',
            'performed_by'      => null,
            'performed_by_name' => self::SYSTEM_ACTOR,
            'notes'             => 'Automatically forwarded to the Dean because the resolution target time elapsed without resolution.',
        ]);

        foreach ($deans as $dean) {
            $this->safeNotify($dean, new ConcernWorkflowNotification(
                (int) $concern->concern_id,
                "Automatic Escalation: Concern #{$concern->concern_id} passed its resolution deadline",
                "Concern '{$concern->title}' was not resolved within its {$priority}-priority resolution target ({$deadlineText}) and has been escalated to your office.",
                'sla_auto_escalated',
                $concern->workflow_type ?? 'academic',
                "/dean/concerns/{$concern->concern_id}",
                ['deadline_at' => $deadline->toIso8601String(), 'priority' => $priority]
            ));
        }

        foreach ($staff as $member) {
            $this->safeNotify($member, new ConcernWorkflowNotification(
                (int) $concern->concern_id,
                "Escalated to Dean: Concern #{$concern->concern_id} passed its resolution deadline",
                "Concern '{$concern->title}' was automatically escalated to the Dean after its resolution deadline ({$deadlineText}) passed.",
                'sla_auto_escalated',
                $concern->workflow_type ?? 'academic',
                "/dept-staff/concerns/{$concern->concern_id}",
                ['deadline_at' => $deadline->toIso8601String(), 'priority' => $priority]
            ));
        }

        // Student receives the same neutral update as a manual forward.
        if ($concern->student) {
            $this->safeNotify($concern->student, new ConcernWorkflowNotification(
                (int) $concern->concern_id,
                'Concern Forwarded to Dean',
                "Your concern '{$concern->title}' has been forwarded to the Dean for further review.",
                'forwarded_to_dean',
                $concern->workflow_type ?? 'academic',
                "/student/concerns/{$concern->concern_id}"
            ));
        }

        return 'escalations';
    }

    /**
     * Department Staff responsible for the concern's CURRENT assignment
     * (same recipient rule as the existing workflow notifications).
     */
    public function assignedStaff(Concern $concern): Collection
    {
        $isAcademic = ($concern->workflow_type === 'academic' || $concern->category === 'academic_units');
        $query = User::where('role', 'dept_staff');

        if ($isAcademic && $concern->academic_unit_id) {
            return $query->where('academic_unit_id', $concern->academic_unit_id)->get();
        }
        if (!$isAcademic && $concern->dept_id) {
            return $query->where('dept_id', $concern->dept_id)->get();
        }

        return collect();
    }

    private function routingRecords(Concern $concern): Collection
    {
        return $concern->relationLoaded('routingHistory')
            ? $concern->routingHistory
            : $concern->routingHistory()->get();
    }

    private function eventSince(Collection $records, string $action, Carbon $since): ?ConcernRouting
    {
        return $records
            ->filter(fn ($r) => $r->action === $action && $r->created_at && Carbon::parse($r->created_at)->gte($since))
            ->sortBy('created_at')
            ->first();
    }

    private function safeNotify(User $user, ConcernWorkflowNotification $notification): void
    {
        try {
            $user->notify($notification);
        } catch (\Throwable $e) {
            Log::warning("SLA notification failed for user #{$user->id}: " . $e->getMessage());
        }
    }

    private function formatHours(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');
    }

    public function humanDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($days > 0) {
            return "{$days}d {$hours}h";
        }
        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }
}
