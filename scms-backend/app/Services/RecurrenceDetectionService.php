<?php

namespace App\Services;

use App\Models\Concern;
use App\Models\RecurringIncident;
use App\Models\RecurringIncidentHistory;
use App\Notifications\ConcernWorkflowNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecurrenceDetectionService
{
    protected ?GeminiSentimentService $gemini;

    public function __construct(?GeminiSentimentService $gemini = null)
    {
        $this->gemini = $gemini;
    }

    /**
     * Process a concern to detect recurrence, group into an existing incident,
     * or create a candidate incident, and evaluate escalation tiers.
     */
    public function processConcern(Concern $concern): ?RecurringIncident
    {
        return DB::transaction(function () use ($concern) {
            // Lock the concern for update to avoid race conditions
            $lockedConcern = Concern::where('concern_id', $concern->concern_id)->lockForUpdate()->first();
            if (!$lockedConcern) {
                return null;
            }

            // If already linked, just recalculate escalation
            if ($lockedConcern->recurring_incident_id) {
                $incident = RecurringIncident::where('id', $lockedConcern->recurring_incident_id)->lockForUpdate()->first();
                if ($incident) {
                    $this->updateIncidentMetrics($incident);
                    return $incident;
                }
            }

            // Extract tokens and domain entities from current concern
            $concernText = $lockedConcern->title . ' ' . $lockedConcern->description;
            $tokens = $this->extractTokens($concernText);
            $entities = $this->extractEntities($concernText);

            // Stage 1: Context Isolation
            // Query open/monitoring candidate incidents matching the exact operational scope
            $query = RecurringIncident::whereIn('status', ['open', 'monitoring', 'investigating'])
                ->where('workflow_type', $lockedConcern->workflow_type)
                ->lockForUpdate();

            if ($lockedConcern->workflow_type === 'academic') {
                $query->where('academic_unit_id', $lockedConcern->academic_unit_id);
            } else {
                $query->where('dept_id', $lockedConcern->dept_id);
            }

            $candidateIncidents = $query->get();
            $jaccardThreshold = (float) config('recurrence.jaccard_threshold', 0.60);

            $matchedIncident = null;

            foreach ($candidateIncidents as $candidate) {
                // Check category and subcategory compatibility
                if (!empty($candidate->issue_category) && !empty($lockedConcern->issue_category)) {
                    if ($candidate->issue_category !== $lockedConcern->issue_category) {
                        continue;
                    }
                }
                if (!empty($candidate->issue_subcategory) && !empty($lockedConcern->issue_subcategory)) {
                    if ($candidate->issue_subcategory !== $lockedConcern->issue_subcategory) {
                        continue;
                    }
                }

                $incidentText = $candidate->title . ' ' . ($candidate->summary ?? '');
                $candidateTokens = $this->extractTokens($incidentText);
                $candidateEntities = $this->extractEntities($incidentText);

                // Check for hard entity conflict (e.g., distinct room numbers, course codes, or devices)
                if ($this->hasEntityConflict($entities, $candidateEntities)) {
                    continue;
                }

                // Stage 2: Token Similarity
                $similarity = $this->calculateJaccardSimilarity($tokens, $candidateTokens);
                if ($similarity < $jaccardThreshold) {
                    continue;
                }

                // Stage 3: Semantic Verification
                $isVerified = true;
                if ($this->gemini && !empty(config('services.gemini.api_key'))) {
                    $verified = $this->gemini->verifyIncidentSimilarity(
                        $candidate->title,
                        $candidate->summary ?? '',
                        $lockedConcern->title,
                        $lockedConcern->description
                    );
                    if ($verified === false) {
                        $isVerified = false;
                    }
                }

                if ($isVerified) {
                    $matchedIncident = $candidate;
                    break;
                }
            }

            if ($matchedIncident) {
                // Link concern to existing incident
                $lockedConcern->update(['recurring_incident_id' => $matchedIncident->id]);

                // Update metrics & evaluate escalation
                $this->updateIncidentMetrics($matchedIncident, "Concern #{$lockedConcern->concern_id} linked to incident.");
                return $matchedIncident->fresh();
            }

            // No match found — create a candidate recurring incident
            $code = $this->generateIncidentCode();

            $newIncident = RecurringIncident::create([
                'incident_code'          => $code,
                'title'                  => $lockedConcern->title,
                'summary'                => $lockedConcern->ai_summary ?: $lockedConcern->description,
                'issue_category'         => $lockedConcern->issue_category,
                'issue_subcategory'      => $lockedConcern->issue_subcategory,
                'workflow_type'          => $lockedConcern->workflow_type,
                'academic_unit_id'       => $lockedConcern->academic_unit_id,
                'dept_id'                => $lockedConcern->dept_id,
                'status'                 => 'open',
                'priority'               => $lockedConcern->priority ?? 'medium',
                'recurrence_tier'        => 'normal',
                'unique_students_count'  => 1,
                'total_reports_count'    => 1,
                'cluster_signature'      => md5(implode(' ', array_slice($tokens, 0, 10))),
                'first_reported_at'      => $lockedConcern->submitted_at ?? now(),
                'latest_reported_at'     => $lockedConcern->submitted_at ?? now(),
            ]);

            $lockedConcern->update(['recurring_incident_id' => $newIncident->id]);

            RecurringIncidentHistory::create([
                'recurring_incident_id'    => $newIncident->id,
                'action'                   => 'incident_created',
                'performed_by'             => null,
                'performed_by_name'        => 'System/Automated',
                'notes'                    => "Incident created from initial report #{$lockedConcern->concern_id}.",
                'unique_students_snapshot' => 1,
                'created_at'               => now(),
            ]);

            return $newIncident;
        });
    }

    /**
     * Recalculate unique students, total reports, and evaluate escalation tiers.
     */
    public function updateIncidentMetrics(RecurringIncident $incident, ?string $reason = null): void
    {
        $windowDays = (int) config('recurrence.window_days', 7);
        $windowStart = now()->subDays($windowDays);

        $distinctStudents = $incident->concerns()
            ->where('submitted_at', '>=', $windowStart)
            ->distinct('student_id')
            ->count('student_id');

        $totalReports = $incident->concerns()->count();
        $latestReport = $incident->concerns()->max('submitted_at') ?? now();

        $previousTier = $incident->recurrence_tier;
        $targetTier = $this->determineTier($distinctStudents);

        $thresholds = config('recurrence.thresholds');
        $highThreshold = (int) ($thresholds['high'] ?? 10);
        $criticalThreshold = (int) ($thresholds['critical'] ?? 20);

        $updateData = [
            'unique_students_count' => $distinctStudents,
            'total_reports_count'   => $totalReports,
            'latest_reported_at'    => $latestReport,
        ];

        $escalated = false;

        // Determine if tier progressed forward
        if ($this->tierRank($targetTier) > $this->tierRank($previousTier)) {
            $updateData['recurrence_tier'] = $targetTier;
            $escalated = true;

            // Update priority according to escalation rules
            if ($distinctStudents >= $criticalThreshold) {
                // At 20+ students: escalate to Critical
                // Policy: A manually overridden High incident may escalate to Critical at 20-student threshold
                if (!$incident->is_priority_overridden || $incident->priority === 'high') {
                    $updateData['priority'] = 'critical';
                    $updateData['escalated_at'] = now();
                }

                // 1. Escalate linked open non-overridden concerns to Critical
                $openConcerns = Concern::where('recurring_incident_id', $incident->id)
                    ->whereNotIn('status', ['resolved', 'rejected'])
                    ->where('is_priority_overridden', false)
                    ->where('priority', '!=', 'critical')
                    ->get();

                foreach ($openConcerns as $c) {
                    $c->update(['priority' => 'critical', 'last_updated' => now()]);
                    ConcernWorkflowNotification::sendCriticalConcernNotification($c, 'Recurring Incident Escalation');
                }

                // 2. Phase 6.1 Policy: A manually overridden High concern may escalate to Critical at 20-student threshold
                $overriddenHighConcerns = Concern::where('recurring_incident_id', $incident->id)
                    ->whereNotIn('status', ['resolved', 'rejected'])
                    ->where('is_priority_overridden', true)
                    ->where('priority', 'high')
                    ->get();

                foreach ($overriddenHighConcerns as $c) {
                    $c->update(['priority' => 'critical', 'last_updated' => now()]);
                    \App\Models\ConcernRouting::create([
                        'concern_id'        => $c->concern_id,
                        'action'            => 'priority_escalated',
                        'performed_by'      => null,
                        'performed_by_name' => 'System/Automated',
                        'notes'             => "Priority escalated from manual 'high' to 'critical' due to recurring incident {$incident->incident_code} reaching 20+ unique students.",
                    ]);
                    ConcernWorkflowNotification::sendCriticalConcernNotification($c, 'Recurring Incident Escalation');
                }
            } elseif ($distinctStudents >= $highThreshold) {
                // At 10+ students: escalate to High only if not manually overridden and not already critical
                if ($incident->priority !== 'critical' && !$incident->is_priority_overridden) {
                    $updateData['priority'] = 'high';
                    $updateData['escalated_at'] = now();

                    Concern::where('recurring_incident_id', $incident->id)
                        ->whereNotIn('status', ['resolved', 'rejected'])
                        ->where('is_priority_overridden', false)
                        ->whereIn('priority', ['low', 'medium'])
                        ->update(['priority' => 'high', 'last_updated' => now()]);
                }
            }
        } elseif ($this->tierRank($targetTier) < $this->tierRank($previousTier)) {
            $updateData['recurrence_tier'] = $targetTier;

            // Safely recalculate automated priority if not manually overridden
            if (!$incident->is_priority_overridden) {
                if ($distinctStudents < $highThreshold && $incident->priority === 'high') {
                    $highestRemaining = $incident->concerns()->max('priority');
                    $updateData['priority'] = in_array($highestRemaining, ['low', 'medium', 'high']) ? $highestRemaining : 'medium';
                }
            }
        }

        $incident->update($updateData);

        if ($reason) {
            RecurringIncidentHistory::create([
                'recurring_incident_id'    => $incident->id,
                'action'                   => 'concern_linked',
                'performed_by'             => null,
                'performed_by_name'        => 'System/Automated',
                'notes'                    => "{$reason} Current unique students: {$distinctStudents}.",
                'unique_students_snapshot' => $distinctStudents,
                'created_at'               => now(),
            ]);
        }

        if ($escalated) {
            RecurringIncidentHistory::create([
                'recurring_incident_id'    => $incident->id,
                'action'                   => 'tier_escalated',
                'performed_by'             => null,
                'performed_by_name'        => 'System/Automated',
                'notes'                    => "Escalated from {$previousTier} to {$targetTier} tier ({$distinctStudents} unique students in rolling 7-day window).",
                'unique_students_snapshot' => $distinctStudents,
                'created_at'               => now(),
            ]);

            // Dispatch tier transition notification (Part 4)
            ConcernWorkflowNotification::sendIncidentTierNotification($incident->fresh(), $previousTier, $targetTier);
        }
    }

    /**
     * Unlink a concern from its recurring incident.
     */
    public function unlinkConcern(Concern $concern, ?int $performedById = null): void
    {
        DB::transaction(function () use ($concern, $performedById) {
            $incidentId = $concern->recurring_incident_id;
            if (!$incidentId) {
                return;
            }

            $concern->update(['recurring_incident_id' => null]);

            $incident = RecurringIncident::where('id', $incidentId)->lockForUpdate()->first();
            if (!$incident) {
                return;
            }

            $performerName = 'System/Automated';
            if ($performedById) {
                $performerName = \App\Models\User::find($performedById)?->name ?? 'Staff';
            }

            RecurringIncidentHistory::create([
                'recurring_incident_id'    => $incident->id,
                'action'                   => 'concern_unlinked',
                'performed_by'             => $performedById,
                'performed_by_name'        => $performerName,
                'notes'                    => "Concern #{$concern->concern_id} unlinked from incident.",
                'unique_students_snapshot' => $incident->unique_students_count,
                'created_at'               => now(),
            ]);

            $this->updateIncidentMetrics($incident);
        });
    }

    /**
     * Determine recurrence tier from distinct student count.
     */
    public function determineTier(int $count): string
    {
        $thresholds = config('recurrence.thresholds');
        $high = (int) ($thresholds['high'] ?? 10);
        $critical = (int) ($thresholds['critical'] ?? 20);
        $major = (int) ($thresholds['major_critical'] ?? 30);

        if ($count >= $major) {
            return 'major_critical';
        }
        if ($count >= $critical) {
            return 'critical';
        }
        if ($count >= $high) {
            return 'high';
        }
        return 'normal';
    }

    protected function tierRank(string $tier): int
    {
        return match ($tier) {
            'major_critical' => 4,
            'critical'       => 3,
            'high'           => 2,
            default          => 1,
        };
    }

    /**
     * Compute Jaccard token similarity: |A ∩ B| / |A ∪ B|.
     */
    public function calculateJaccardSimilarity(array $tokensA, array $tokensB): float
    {
        if (empty($tokensA) || empty($tokensB)) {
            return 0.0;
        }

        $intersection = array_intersect($tokensA, $tokensB);
        $union = array_unique(array_merge($tokensA, $tokensB));

        if (count($union) === 0) {
            return 0.0;
        }

        return round(count($intersection) / count($union), 3);
    }

    /**
     * Extract normalized tokens, stripping stop words while preserving key domain entities.
     */
    public function extractTokens(string $text): array
    {
        $cleaned = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', ' ', $text));
        $words = preg_split('/\s+/', $cleaned, -1, PREG_SPLIT_NO_EMPTY);

        $stopWords = array_flip(config('recurrence.stop_words', []));

        $filtered = [];
        foreach ($words as $word) {
            if (strlen($word) < 2) {
                continue;
            }
            if (isset($stopWords[$word])) {
                continue;
            }
            $filtered[] = $word;
        }

        return array_values(array_unique($filtered));
    }

    /**
     * Extract specific locations, room numbers, course codes, or hardware entities.
     */
    public function extractEntities(string $text): array
    {
        $entities = [
            'rooms'   => [],
            'courses' => [],
            'devices' => [],
        ];

        // Match room numbers like 'Room 301', 'Rm 402', 'Lab 2', 'Bldg B'
        if (preg_match_all('/\b(?:room|rm|lab|bldg|building)\s*([a-z0-9\-]+)\b/i', $text, $matches)) {
            $entities['rooms'] = array_map('strtolower', $matches[1]);
        }

        // Match course codes like 'CS101', 'ITE 204', 'ENG-102'
        if (preg_match_all('/\b([a-z]{2,4})\s*[\-_]?\s*([0-9]{3}[a-z]?)\b/i', $text, $matches)) {
            foreach ($matches[0] as $course) {
                $entities['courses'][] = strtolower(preg_replace('/[\s\-_]/', '', $course));
            }
        }

        // Match specific devices / hardware
        $devicePatterns = [
            'projector' => ['projector', 'hdmi display', 'display projector'],
            'computer'  => ['computer', 'pc', 'desktop', 'workstation', 'laptop'],
            'printer'   => ['printer', 'scanner', 'photocopier'],
            'aircon'    => ['aircon', 'air conditioning', 'ac unit'],
            'wifi'      => ['wifi', 'wi-fi', 'router', 'access point'],
        ];

        $lowerText = strtolower($text);
        foreach ($devicePatterns as $deviceType => $terms) {
            foreach ($terms as $term) {
                if (str_contains($lowerText, $term)) {
                    $entities['devices'][] = $deviceType;
                    break;
                }
            }
        }
        $entities['devices'] = array_values(array_unique($entities['devices']));

        return $entities;
    }

    /**
     * Detect if two texts explicitly reference contradictory physical entities (e.g. Room 301 vs Room 502, or Computer vs Projector).
     */
    public function hasEntityConflict(array $entitiesA, array $entitiesB): bool
    {
        if (!empty($entitiesA['rooms']) && !empty($entitiesB['rooms'])) {
            $shared = array_intersect($entitiesA['rooms'], $entitiesB['rooms']);
            if (empty($shared)) {
                return true; // Different rooms specified
            }
        }

        if (!empty($entitiesA['courses']) && !empty($entitiesB['courses'])) {
            $shared = array_intersect($entitiesA['courses'], $entitiesB['courses']);
            if (empty($shared)) {
                return true; // Different courses specified
            }
        }

        if (!empty($entitiesA['devices']) && !empty($entitiesB['devices'])) {
            $shared = array_intersect($entitiesA['devices'], $entitiesB['devices']);
            if (empty($shared)) {
                return true; // Different devices specified (e.g. computer vs projector)
            }
        }

        return false;
    }

    /**
     * Generate unique incident code: INC-YYYY-XXXX.
     */
    protected function generateIncidentCode(): string
    {
        $year = date('Y');
        $count = RecurringIncident::whereYear('created_at', $year)->count() + 1;
        $candidate = sprintf('INC-%s-%04d', $year, $count);

        while (RecurringIncident::where('incident_code', $candidate)->exists()) {
            $count++;
            $candidate = sprintf('INC-%s-%04d', $year, $count);
        }

        return $candidate;
    }
}
