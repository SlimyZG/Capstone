<?php

/*
|--------------------------------------------------------------------------
| Concern Resolution Targets (SLA) — Automatic Follow-up & Escalation
|--------------------------------------------------------------------------
|
| IMPORTANT: The default target durations below are PLACEHOLDER values used
| for development and testing only. They are NOT approved institutional
| policy. The responsible office must confirm the official values, which can
| then be set through the .env file without code changes:
|
|   SLA_ENABLED=true
|   SLA_TARGET_CRITICAL_HOURS=24
|   SLA_TARGET_HIGH_HOURS=72
|   SLA_TARGET_MEDIUM_HOURS=120
|   SLA_TARGET_LOW_HOURS=168
|   SLA_FOLLOWUP_THRESHOLD=0.75
|
| Deadline = clock start (submitted_at, or the latest student reopen) +
| target hours for the concern's CURRENT effective priority. Because the
| deadline is derived, priority changes/overrides are reflected automatically.
|
*/

return [

    // Master switch for the scheduled follow-up / escalation processor.
    'enabled' => (bool) env('SLA_ENABLED', true),

    // Resolution target (in hours) per effective priority level.
    'targets_hours' => [
        'critical' => (float) env('SLA_TARGET_CRITICAL_HOURS', 24),
        'high'     => (float) env('SLA_TARGET_HIGH_HOURS', 72),
        'medium'   => (float) env('SLA_TARGET_MEDIUM_HOURS', 120),
        'low'      => (float) env('SLA_TARGET_LOW_HOURS', 168),
    ],

    // Priority used when a concern has no priority yet (e.g. AI analysis failed).
    'default_priority' => env('SLA_DEFAULT_PRIORITY', 'medium'),

    // Panel requirement: automatic follow-up at 75% of the allocated time.
    // This is a FOLLOW-UP (reminder) event only — it is NOT an escalation.
    'followup_threshold' => (float) env('SLA_FOLLOWUP_THRESHOLD', 0.75),

    // UI-only band: elapsed ratio at which a concern is shown as "Approaching
    // deadline". Has no workflow effect.
    'approaching_threshold' => (float) env('SLA_APPROACHING_THRESHOLD', 0.50),

    // Statuses in which the assigned Department Staff still owe a resolution.
    // Escalated concerns are with the Dean and are excluded from staff follow-up.
    'monitored_statuses' => ['pending', 'in_progress', 'returned', 'approved'],

    // Statuses from which an academic concern may be automatically escalated
    // to the Dean (mirrors the existing manual escalation endpoint).
    'escalatable_statuses' => ['pending', 'in_progress', 'returned'],

    /*
    | Action taken when the resolution deadline (100%) passes while unresolved:
    |   'escalate_to_dean' — move the concern to the Dean of its academic unit
    |                        (status=escalated), same as the manual workflow.
    |   'notify_only'      — record an overdue event and notify the assigned
    |                        Department Staff; the workflow is not changed.
    |
    | Non-academic concerns default to 'notify_only' so the existing
    | non-academic workflow is preserved (pending adviser decision).
    */
    'deadline_action' => [
        'academic'     => env('SLA_DEADLINE_ACTION_ACADEMIC', 'escalate_to_dean'),
        'non_academic' => env('SLA_DEADLINE_ACTION_NON_ACADEMIC', 'notify_only'),
    ],

    // How often the Laravel scheduler runs the processor (cron expression).
    'schedule_cron' => env('SLA_SCHEDULE_CRON', '*/15 * * * *'),
];
