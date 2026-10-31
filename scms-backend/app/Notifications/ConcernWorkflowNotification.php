<?php

namespace App\Notifications;

use App\Models\Concern;
use App\Models\Message;
use App\Models\RecurringIncident;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConcernWorkflowNotification extends Notification
{
    use Queueable;

    public int $concernId;
    public string $title;
    public string $message;
    public string $event;
    public string $workflowType;
    public ?string $actionUrl;
    public array $extraData;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        int $concernId,
        string $title,
        string $message,
        string $event,
        string $workflowType = 'academic',
        ?string $actionUrl = null,
        array $extraData = []
    ) {
        $this->concernId = $concernId;
        $this->title = $title;
        $this->message = $message;
        $this->event = $event;
        $this->workflowType = $workflowType;
        $this->actionUrl = $actionUrl;
        $this->extraData = $extraData;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification stored in database.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $role = $notifiable->role ?? null;
        $url = $this->actionUrl;

        // Auto-generate correct role-based URL if not explicitly provided
        if (!$url && $this->concernId) {
            $hash = ($this->event === 'new_message') ? '#messages' : '';
            if ($role === 'student') {
                $url = "/student/concerns/{$this->concernId}{$hash}";
            } elseif ($role === 'dept_staff') {
                $url = "/dept-staff/concerns/{$this->concernId}{$hash}";
            } elseif ($role === 'dean') {
                $url = "/dean/concerns/{$this->concernId}{$hash}";
            } elseif ($role === 'sao') {
                $url = "/sao/assigned-concerns";
            }
        } elseif ($url && $this->event === 'new_message' && !str_contains($url, '#messages')) {
            $url .= '#messages';
        }

        $data = [
            'concern_id' => $this->concernId,
            'title' => $this->title,
            'message' => $this->message,
            'event' => $this->event,
            'workflow_type' => $this->workflowType,
            'action_url' => $url,
            'created_at' => now()->toIso8601String(),
        ];

        if (!empty($this->extraData)) {
            $data = array_merge($data, $this->extraData);
        }

        return $data;
    }

    /**
     * Dispatch in-app notifications for a newly created message.
     *
     * Rules:
     * 1. Exclude the sender ($sender->id !== $recipient->id).
     * 2. Notify student if not the sender AND message is not internal.
     * 3. Notify assigned department staff for the academic unit / department.
     * 4. Notify Dean ONLY if the concern has entered Dean's workflow:
     *    - status = 'escalated'
     *    - target_type = 'dean'
     *    - forwarded_to_cad_at is not null
     *    - post-action status (approved/returned/rejected/resolved) with dean/cad routing
     * 5. Never notify unassigned staff.
     * 6. Deduplicate recipients so each user receives at most one notification.
     *
     * @param Concern $concern
     * @param Message $message
     * @param User $sender
     * @return int Number of notifications dispatched
     */
    public static function sendNewMessageNotification(Concern $concern, Message $message, User $sender): int
    {
        try {
            if (!$concern->relationLoaded('student')) {
                $concern->load('student');
            }

            $recipients = collect();

            // 1. Student recipient:
            // Only notify student if message is public and student is not the sender
            if (!$message->is_internal && $concern->student && (int)$concern->student->id !== (int)$sender->id) {
                $recipients->push($concern->student);
            }

            // 2. Department Staff / Chairperson review:
            $isAcademic = ($concern->workflow_type === 'academic' || $concern->category === 'academic_units');
            if ($isAcademic && $concern->academic_unit_id) {
                $staff = User::where('role', 'dept_staff')
                    ->where('academic_unit_id', $concern->academic_unit_id)
                    ->get();
                $recipients = $recipients->merge($staff);
            } elseif (!$isAcademic && $concern->dept_id) {
                $staff = User::where('role', 'dept_staff')
                    ->where('dept_id', $concern->dept_id)
                    ->get();
                $recipients = $recipients->merge($staff);
            }

            // 3. Dean:
            // Dean is only notified if authorized to access this concern in its current stage
            $isEscalated = ($concern->status === 'escalated');
            $isDirectDean = ($concern->target_type === 'dean');
            $isCad = !empty($concern->forwarded_to_cad_at);

            $hasDeanAction = false;
            if (in_array($concern->status, ['approved', 'returned', 'rejected', 'resolved'])) {
                $hasDeanAction = $concern->routingHistory()
                    ->whereIn('action', [
                        'forwarded_to_dean',
                        'escalated_to_dean',
                        'dean_approved',
                        'dean_returned',
                        'dean_rejected',
                        'cad_approved',
                        'cad_returned',
                    ])
                    ->exists();
            }

            $deanInvolved = $isEscalated || $isDirectDean || $isCad || $hasDeanAction;

            if ($deanInvolved) {
                if ($isAcademic && $concern->academic_unit_id) {
                    $deans = User::where('role', 'dean')
                        ->where('academic_unit_id', $concern->academic_unit_id)
                        ->get();
                    $recipients = $recipients->merge($deans);
                } elseif ($isCad) {
                    $cadDeans = User::where('role', 'dean')->get();
                    $recipients = $recipients->merge($cadDeans);
                }
            }

            // 4. Exclude sender, exclude unassigned staff, and deduplicate
            $filtered = $recipients
                ->filter(function ($u) use ($sender) {
                    if (!$u || !($u instanceof User) || !$u->id) {
                        return false;
                    }
                    if ((int)$u->id === (int)$sender->id) {
                        return false;
                    }
                    if ($u->role === 'dept_staff' && !$u->dept_id && !$u->academic_unit_id) {
                        return false;
                    }
                    return true;
                })
                ->unique('id');

            if ($filtered->isEmpty()) {
                return 0;
            }

            // Prepare notification text
            $senderLabel = $sender->name ?: 'Staff';
            if ($sender->role === 'student') {
                $senderLabel = $concern->is_anonymous ? 'Student (Anonymous)' : ($sender->name ?: 'Student');
            } elseif ($sender->role === 'dept_staff') {
                $senderLabel = ($sender->name ?: 'Department Staff') . ' (Staff)';
            } elseif ($sender->role === 'dean') {
                $senderLabel = ($sender->name ?: 'Dean') . ' (Dean)';
            }

            $snippet = Str::limit(strip_tags($message->body), 75);
            $title = "New Message: {$concern->title}";
            $notificationMessage = "{$senderLabel}: {$snippet}";

            $count = 0;
            foreach ($filtered as $recipient) {
                $role = $recipient->role;
                $url = "/student/concerns/{$concern->concern_id}#messages";
                if ($role === 'dept_staff') {
                    $url = "/dept-staff/concerns/{$concern->concern_id}#messages";
                } elseif ($role === 'dean') {
                    $url = "/dean/concerns/{$concern->concern_id}#messages";
                }

                $recipient->notify(new self(
                    (int)$concern->concern_id,
                    $title,
                    $notificationMessage,
                    'new_message',
                    $concern->workflow_type ?? 'academic',
                    $url
                ));
                $count++;
            }

            return $count;
        } catch (\Throwable $e) {
            Log::warning('Failed sending new message notification: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Dispatch notifications for an individual concern reaching Critical priority.
     * Guaranteed deduplication via critical_notified_at timestamp.
     */
    public static function sendCriticalConcernNotification(Concern $concern, string $triggerSource = 'system'): int
    {
        try {
            // Guard: Deduplication
            if ($concern->critical_notified_at !== null) {
                return 0;
            }

            // Mark as notified in database atomically
            $concern->update(['critical_notified_at' => now()]);

            $recipients = collect();
            $isAcademic = ($concern->workflow_type === 'academic' || $concern->category === 'academic_units');

            // 1. Assigned staff
            if ($isAcademic && $concern->academic_unit_id) {
                $staff = User::where('role', 'dept_staff')
                    ->where('academic_unit_id', $concern->academic_unit_id)
                    ->get();
                $recipients = $recipients->merge($staff);
            } elseif (!$isAcademic && $concern->dept_id) {
                $staff = User::where('role', 'dept_staff')
                    ->where('dept_id', $concern->dept_id)
                    ->get();
                $recipients = $recipients->merge($staff);
            }

            // 2. Academic Dean (if academic workflow)
            if ($isAcademic && $concern->academic_unit_id) {
                $deans = User::where('role', 'dean')
                    ->where('academic_unit_id', $concern->academic_unit_id)
                    ->get();
                $recipients = $recipients->merge($deans);
            }

            // 3. All active SAO users
            $saoQuery = User::where('role', 'sao');
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active')) {
                $saoQuery->where('is_active', true);
            }
            $saoUsers = $saoQuery->get();
            $recipients = $recipients->merge($saoUsers);

            // Deduplicate recipients
            $filtered = $recipients->unique('id');

            if ($filtered->isEmpty()) {
                return 0;
            }

            $title = "[CRITICAL CONCERN] Immediate Action Required: {$concern->title}";
            $notificationMessage = "Concern #{$concern->concern_id} has been classified as Critical priority ({$triggerSource}). Immediate review required.";

            // Check if concern is assigned to SAO for SAO URL resolution
            $saoDept = \App\Models\Department::where('name', 'like', '%SAO%')
                ->orWhere('name', 'like', '%Student Affairs%')
                ->first();
            $isAssignedToSao = ($saoDept && (int)$concern->dept_id === (int)$saoDept->id);

            $count = 0;
            foreach ($filtered as $recipient) {
                $role = $recipient->role;
                $url = "/dept-staff/concerns/{$concern->concern_id}";
                if ($role === 'dean') {
                    $url = "/dean/concerns/{$concern->concern_id}";
                } elseif ($role === 'sao') {
                    $url = $isAssignedToSao ? "/sao/concerns/{$concern->concern_id}" : "/sao/oversight";
                }

                $recipient->notify(new self(
                    (int)$concern->concern_id,
                    $title,
                    $notificationMessage,
                    'critical_concern_alert',
                    $concern->workflow_type ?? 'academic',
                    $url,
                    [
                        'priority' => 'critical',
                        'target_scope' => $isAcademic ? 'Academic Unit' : 'Operational Department',
                    ]
                ));
                $count++;
            }

            return $count;
        } catch (\Throwable $e) {
            Log::warning('Failed sending critical concern notification: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Dispatch notification when a recurring incident transitions to a higher tier.
     */
    public static function sendIncidentTierNotification(RecurringIncident $incident, string $previousTier, string $newTier): int
    {
        try {
            $recipients = collect();
            $isAcademic = ($incident->workflow_type === 'academic');

            // 1. Responsible Department Staff
            if ($isAcademic && $incident->academic_unit_id) {
                $staff = User::where('role', 'dept_staff')
                    ->where('academic_unit_id', $incident->academic_unit_id)
                    ->get();
                $recipients = $recipients->merge($staff);
            } elseif (!$isAcademic && $incident->dept_id) {
                $staff = User::where('role', 'dept_staff')
                    ->where('dept_id', $incident->dept_id)
                    ->get();
                $recipients = $recipients->merge($staff);
            }

            // 2. Relevant Dean (for academic incidents)
            if ($isAcademic && $incident->academic_unit_id) {
                $deans = User::where('role', 'dean')
                    ->where('academic_unit_id', $incident->academic_unit_id)
                    ->get();
                $recipients = $recipients->merge($deans);
            }

            // 3. SAO: Notify SAO if tier is 'critical' or 'major_critical'
            if (in_array($newTier, ['critical', 'major_critical'])) {
                $saoQuery = User::where('role', 'sao');
                if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active')) {
                    $saoQuery->where('is_active', true);
                }
                $saoUsers = $saoQuery->get();
                $recipients = $recipients->merge($saoUsers);
            }

            // Deduplicate recipients
            $filtered = $recipients->unique('id');
            if ($filtered->isEmpty()) {
                return 0;
            }

            $event = match ($newTier) {
                'high'           => 'recurring_incident_high',
                'critical'       => 'recurring_incident_critical',
                'major_critical' => 'recurring_incident_major_critical',
                default          => 'recurring_incident_alert',
            };

            $title = match ($newTier) {
                'high'           => "[HIGH RECURRENCE] 10+ Students Reported: {$incident->title}",
                'critical'       => "[CRITICAL INCIDENT ESCALATION] 20+ Students Reported: {$incident->title}",
                'major_critical' => "[MAJOR CRITICAL INCIDENT] 30+ Students Reported: {$incident->title}",
                default          => "[RECURRENCE ALERT] {$incident->title}",
            };

            $message = "Incident {$incident->incident_code} reached {$newTier} tier with {$incident->unique_students_count} unique student reports in rolling 7 days.";

            $count = 0;
            foreach ($filtered as $recipient) {
                $role = $recipient->role;
                $url = "/dept-staff/recurring-incidents/{$incident->id}";
                if ($role === 'dean') {
                    $url = "/dean/recurring-incidents/{$incident->id}";
                } elseif ($role === 'sao') {
                    $url = "/sao/recurring-incidents/{$incident->id}";
                }

                $recipient->notify(new self(
                    0, // 0 for incident-level
                    $title,
                    $message,
                    $event,
                    $incident->workflow_type ?? 'academic',
                    $url,
                    [
                        'incident_id'           => $incident->id,
                        'incident_code'         => $incident->incident_code,
                        'recurrence_tier'       => $newTier,
                        'unique_students_count' => $incident->unique_students_count,
                        'priority'              => $incident->priority,
                    ]
                ));
                $count++;
            }

            return $count;
        } catch (\Throwable $e) {
            Log::warning('Failed sending incident tier notification: ' . $e->getMessage());
            return 0;
        }
    }
}
