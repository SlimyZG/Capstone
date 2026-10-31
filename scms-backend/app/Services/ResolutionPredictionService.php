<?php

namespace App\Services;

use App\Models\Concern;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 9 — Predictive Resolution-Time Estimation Service.
 *
 * Uses an explainable statistical estimator (median resolution duration and
 * interquartile range across historical resolved concerns) with a 5-tier
 * fallback hierarchy:
 *   Tier 1: Same department/academic unit + issue category + subcategory + priority
 *   Tier 2: Same department/academic unit + category + priority
 *   Tier 3: Same department/academic unit + category
 *   Tier 4: Same category + priority (institution-wide)
 *   Tier 5: Same priority baseline or institution-wide historical baseline
 *
 * Strictly excludes:
 *   - Unresolved concerns
 *   - Invalid, zero, or negative resolution durations (resolved_at <= submitted_at)
 *   - Identifiable synthetic test records (e.g. "SYNTH:" prefix or synthetic test accounts)
 *   - Duplicate historical records that would distort averages/medians
 *
 * Never fabricates historical data or predictions, and never replaces official
 * SLA deadlines defined in config/sla.php.
 */
class ResolutionPredictionService
{
    public const CACHE_VERSION_KEY = 'scms:prediction:dataset_version';

    public const DISCLAIMER = 'Statistical estimate based on historical resolved concerns — not a guaranteed resolution date or official SLA commitment.';

    /**
     * Invalidate cached historical cohort data when concerns are resolved or updated.
     */
    public static function flushCache(): void
    {
        $dbName = (string) \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
        $key = self::CACHE_VERSION_KEY . ':' . $dbName;
        $version = (int) Cache::get($key, 1);
        Cache::put($key, $version + 1, 86400);
    }

    public function isEnabled(): bool
    {
        return (bool) config('prediction.enabled', true);
    }

    /**
     * Load and clean the historical resolved concerns dataset (cached).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getCleanHistoricalDataset(): Collection
    {
        $ttl = (int) config('prediction.cache_ttl_seconds', 300);
        $dbName = (string) \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
        $version = (int) Cache::get(self::CACHE_VERSION_KEY . ':' . $dbName, 1);
        $excludeSynth = (bool) config('prediction.exclude_synthetic', true);
        $maxHours = (float) config('prediction.max_valid_duration_hours', 8760);
        $cacheKey = "scms:prediction:cohort:{$dbName}:v{$version}:synth" . ($excludeSynth ? '1' : '0');

        if ($ttl <= 0) {
            return $this->buildCleanHistoricalDataset($excludeSynth, $maxHours);
        }

        return Cache::remember($cacheKey, $ttl, function () use ($excludeSynth, $maxHours) {
            return $this->buildCleanHistoricalDataset($excludeSynth, $maxHours);
        });
    }

    /**
     * Query and sanitize resolved concerns into a privacy-safe statistical dataset.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildCleanHistoricalDataset(bool $excludeSynthetic, float $maxValidHours): Collection
    {
        $maxSeconds = (int) round($maxValidHours * 3600);

        $rows = Concern::where('status', 'resolved')
            ->whereNotNull('submitted_at')
            ->whereNotNull('resolved_at')
            ->with([
                'student:id,name,email,student_id_number',
                'department:id,name',
                'academicUnit:id,name',
            ])
            ->orderBy('resolved_at', 'desc')
            ->get();

        $seenFingerprints = [];
        $cleaned = collect();

        foreach ($rows as $concern) {
            $submitted = Carbon::parse($concern->submitted_at);
            $resolved = Carbon::parse($concern->resolved_at);
            $durationSeconds = $resolved->getTimestamp() - $submitted->getTimestamp();

            // 1. Exclude invalid, zero, negative, or extreme outlier durations
            if ($durationSeconds <= 0 || $durationSeconds > $maxSeconds) {
                continue;
            }

            // 2. Exclude identifiable synthetic/test records
            if ($excludeSynthetic && $this->isSyntheticRecord($concern)) {
                continue;
            }

            // 3. Exclude duplicate records that would distort historical averages
            $fingerprint = implode('|', [
                (int) $concern->student_id,
                strtolower(trim((string) $concern->title)),
                $submitted->format('Y-m-d'),
                (int) round($durationSeconds / 60),
            ]);
            if (isset($seenFingerprints[$fingerprint])) {
                continue;
            }
            $seenFingerprints[$fingerprint] = true;

            $isAcademic = ($concern->workflow_type === 'academic' || ($concern->category === 'academic_units' && empty($concern->dept_id)));
            $unitKey = (!$isAcademic && $concern->dept_id)
                ? 'dept:' . (int) $concern->dept_id
                : 'unit:' . (int) ($concern->academic_unit_id ?? 0);

            $unitName = (!$isAcademic && $concern->department)
                ? $concern->department->name
                : ($concern->academicUnit->name ?? 'Unassigned Unit');

            $orgCategory = $concern->category ?: ($isAcademic ? 'academic_units' : 'operational_departments');
            $effectiveCategory = $concern->issue_category ?: $orgCategory;

            // Store only non-PII statistical attributes
            $cleaned->push([
                'concern_id'        => (int) $concern->concern_id,
                'workflow_type'     => $isAcademic ? 'academic' : 'non_academic',
                'unit_key'          => $unitKey,
                'unit_name'         => $unitName,
                'academic_unit_id'  => $concern->academic_unit_id ? (int) $concern->academic_unit_id : null,
                'dept_id'           => $concern->dept_id ? (int) $concern->dept_id : null,
                'org_category'      => $orgCategory,
                'issue_category'    => $concern->issue_category ?: null,
                'issue_subcategory' => $concern->issue_subcategory ?: null,
                'effective_category' => $effectiveCategory,
                'priority'          => strtolower((string) ($concern->priority ?: 'medium')),
                'is_recurring'      => !empty($concern->recurring_incident_id),
                'duration_seconds'  => $durationSeconds,
                'duration_hours'    => round($durationSeconds / 3600, 2),
                'submitted_at'      => $submitted->toIso8601String(),
                'resolved_at'       => $resolved->toIso8601String(),
            ]);
        }

        return $cleaned;
    }

    /**
     * Identify synthetic acceptance-testing records so they do not pollute
     * historical resolution-time predictions.
     */
    public function isSyntheticRecord(Concern $concern): bool
    {
        $title = strtoupper(trim((string) $concern->title));
        $desc = strtoupper(trim((string) $concern->description));

        if (
            str_starts_with($title, 'SYNTH:') ||
            str_contains($title, '[SYNTHETIC]') ||
            str_starts_with($desc, 'SYNTH:') ||
            str_contains($desc, '[SYNTHETIC]')
        ) {
            return true;
        }

        if ($concern->relationLoaded('student') && $concern->student) {
            $name = strtolower(trim((string) $concern->student->name));
            $email = strtolower(trim((string) $concern->student->email));
            $idNum = trim((string) $concern->student->student_id_number);

            if (
                str_starts_with($name, 'synthetic ') ||
                in_array($email, ['student.test@scms.test', 'student2.test@scms.test'], true) ||
                in_array($idNum, ['99999999', '88888888'], true)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compute a predictive resolution-time estimate for a concern.
     *
     * @return array<string, mixed>
     */
    public function predict(Concern $concern, ?Collection $dataset = null, ?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : Carbon::now();

        if (!$this->isEnabled()) {
            return [
                'enabled'      => false,
                'available'    => false,
                'method'       => 'historical_median',
                'explanation'  => 'Predictive resolution-time estimation is currently disabled.',
                'disclaimer'   => self::DISCLAIMER,
            ];
        }

        $pool = ($dataset ?? $this->getCleanHistoricalDataset())
            ->reject(fn ($row) => (int) $row['concern_id'] === (int) $concern->concern_id)
            ->values();

        $minSample = max(1, (int) config('prediction.min_sample_size', 3));
        $minBaseline = max(1, (int) config('prediction.min_baseline_sample_size', 2));

        $isAcademic = ($concern->workflow_type === 'academic' || ($concern->category === 'academic_units' && empty($concern->dept_id)));
        $unitKey = (!$isAcademic && $concern->dept_id)
            ? 'dept:' . (int) $concern->dept_id
            : 'unit:' . (int) ($concern->academic_unit_id ?? 0);

        $unitLabel = (!$isAcademic && $concern->department)
            ? $concern->department->name
            : ($concern->academicUnit->name ?? ($isAcademic ? 'Academic Unit' : 'Operational Department'));

        $orgCategory = $concern->category ?: ($isAcademic ? 'academic_units' : 'operational_departments');
        $issueCategory = $concern->issue_category ?: null;
        $issueSubcategory = $concern->issue_subcategory ?: null;
        $effectiveCategory = $issueCategory ?: $orgCategory;
        $priority = strtolower((string) ($concern->priority ?: config('sla.default_priority', 'medium')));
        $isRecurring = !empty($concern->recurring_incident_id);

        // Helper matcher for category compatibility
        $matchesCategory = function (array $row) use ($issueCategory, $orgCategory): bool {
            if ($issueCategory !== null) {
                return $row['issue_category'] === $issueCategory;
            }
            return $row['org_category'] === $orgCategory;
        };

        // ── Evaluate 5-Tier Fallback Hierarchy ─────────────────────────────
        $matchedTier = null;
        $matchedTierLabel = null;
        $tierSpecificity = 0.0;
        $matchedSamples = collect();
        $factorsUsed = [];

        // Tier 1: Same department/unit + issue_category + issue_subcategory + priority
        if ($issueCategory !== null && $issueSubcategory !== null) {
            $t1 = $pool->filter(fn ($r) => $r['unit_key'] === $unitKey
                && $r['issue_category'] === $issueCategory
                && $r['issue_subcategory'] === $issueSubcategory
                && $r['priority'] === $priority)->values();

            if ($t1->count() >= $minSample) {
                $matchedTier = 'dept_category_subcategory_priority';
                $matchedTierLabel = 'Department + Issue Category + Subcategory + Priority';
                $tierSpecificity = 1.00;
                $matchedSamples = $t1;
                $factorsUsed = [
                    "Department/Unit: {$unitLabel}",
                    "Issue Category: {$issueCategory}",
                    "Subcategory: {$issueSubcategory}",
                    'Priority: ' . ucfirst($priority),
                ];
            }
        }

        // Tier 2: Same department/unit + category + priority
        if ($matchedTier === null) {
            $t2 = $pool->filter(fn ($r) => $r['unit_key'] === $unitKey
                && $matchesCategory($r)
                && $r['priority'] === $priority)->values();

            if ($t2->count() >= $minSample) {
                $matchedTier = 'dept_category_priority';
                $matchedTierLabel = 'Department + Category + Priority';
                $tierSpecificity = 0.85;
                $matchedSamples = $t2;
                $factorsUsed = [
                    "Department/Unit: {$unitLabel}",
                    'Category: ' . $this->formatCategoryName($effectiveCategory),
                    'Priority: ' . ucfirst($priority),
                ];
            }
        }

        // Tier 3: Same department/unit + category
        if ($matchedTier === null) {
            $t3 = $pool->filter(fn ($r) => $r['unit_key'] === $unitKey
                && $matchesCategory($r))->values();

            if ($t3->count() >= $minSample) {
                $matchedTier = 'dept_category';
                $matchedTierLabel = 'Department + Category';
                $tierSpecificity = 0.70;
                $matchedSamples = $t3;
                $factorsUsed = [
                    "Department/Unit: {$unitLabel}",
                    'Category: ' . $this->formatCategoryName($effectiveCategory),
                ];
            }
        }

        // Tier 4: Same category + priority across the institution
        if ($matchedTier === null) {
            $t4 = $pool->filter(fn ($r) => $matchesCategory($r)
                && $r['priority'] === $priority)->values();

            if ($t4->count() >= $minSample) {
                $matchedTier = 'category_priority';
                $matchedTierLabel = 'Institution-Wide Category + Priority';
                $tierSpecificity = 0.65;
                $matchedSamples = $t4;
                $factorsUsed = [
                    'Category: ' . $this->formatCategoryName($effectiveCategory),
                    'Priority: ' . ucfirst($priority),
                    'Scope: Institution-wide historical cases',
                ];
            }
        }

        // Tier 5a: Same priority across the institution
        if ($matchedTier === null) {
            $t5a = $pool->filter(fn ($r) => $r['priority'] === $priority)->values();

            if ($t5a->count() >= $minSample) {
                $matchedTier = 'priority_baseline';
                $matchedTierLabel = 'Institution-Wide Priority Baseline';
                $tierSpecificity = 0.50;
                $matchedSamples = $t5a;
                $factorsUsed = [
                    'Priority: ' . ucfirst($priority),
                    'Scope: Institution-wide historical baseline',
                ];
            }
        }

        // Tier 5b: Institution-wide aggregate baseline
        if ($matchedTier === null && $pool->count() >= $minBaseline) {
            $matchedTier = 'institution_baseline';
            $matchedTierLabel = 'Institution-Wide Aggregate Baseline';
            $tierSpecificity = 0.40;
            $matchedSamples = $pool;
            $factorsUsed = [
                'Scope: All completed historical concerns across the institution',
            ];
        }

        if ($isRecurring && $matchedTier !== null) {
            $factorsUsed[] = 'Linked to Recurring Incident';
        }

        // Actual resolution summary when the concern itself is already resolved
        $actualResolution = null;
        if ($concern->status === 'resolved' && $concern->submitted_at && $concern->resolved_at) {
            $sub = Carbon::parse($concern->submitted_at);
            $res = Carbon::parse($concern->resolved_at);
            $actSec = max(0, $res->getTimestamp() - $sub->getTimestamp());
            $actualResolution = [
                'actual_duration_seconds' => $actSec,
                'actual_duration_hours'   => round($actSec / 3600, 2),
                'resolved_at'             => $res->toIso8601String(),
                'delta_hours'             => null,
                'within_predicted_range'  => null,
            ];
        }

        // ── Insufficient Historical Data ───────────────────────────────────
        if ($matchedTier === null || $matchedSamples->isEmpty()) {
            return [
                'enabled'                     => true,
                'available'                   => false,
                'method'                      => 'historical_median',
                'estimated_duration_seconds'  => null,
                'estimated_duration_hours'    => null,
                'estimated_hours'             => null,
                'estimated_label'             => null,
                'mean_duration_hours'         => null,
                'mean_hours'                  => null,
                'range_hours'                 => null,
                'estimated_resolved_at'       => null,
                'remaining_predicted_seconds' => null,
                'sample_size'                 => $pool->count(),
                'min_sample_size_required'    => $minSample,
                'total_historical_pool'       => $pool->count(),
                'matched_tier'                => 'insufficient_data',
                'tier'                        => 'insufficient_data',
                'matched_tier_label'          => 'Insufficient Historical Data',
                'tier_label'                  => 'Insufficient Historical Data',
                'factors_used'                => [],
                'confidence_level'            => 'insufficient_data',
                'confidence_score'            => null,
                'explanation'                 => sprintf(
                    'Insufficient historical resolved concerns (%d valid record%s available; minimum %d required for a specific cohort or %d for a baseline) to calculate a reliable statistical estimate.',
                    $pool->count(),
                    $pool->count() === 1 ? '' : 's',
                    $minSample,
                    $minBaseline
                ),
                'disclaimer'                  => self::DISCLAIMER,
                'actual_resolution'           => $actualResolution,
            ];
        }

        // ── Compute Statistical Estimate (Median & IQR) ────────────────────
        $durations = $matchedSamples->pluck('duration_seconds')->map(fn ($v) => (int) $v)->sort()->values();
        $n = $durations->count();
        $medianSeconds = (int) round($this->percentile($durations, 0.50));
        $meanSeconds = (int) round($durations->avg());
        $p25Seconds = (int) round($this->percentile($durations, 0.25));
        $p75Seconds = (int) round($this->percentile($durations, 0.75));
        $iqrSeconds = max(0, $p75Seconds - $p25Seconds);

        $estimatedHours = round($medianSeconds / 3600, 2);
        $meanHours = round($meanSeconds / 3600, 2);
        $p25Hours = round($p25Seconds / 3600, 2);
        $p75Hours = round($p75Seconds / 3600, 2);

        // Calculate Confidence Score & Level
        $sampleFactor = min(1.0, $n / 10.0);
        $spreadRatio = $medianSeconds > 0 ? ($iqrSeconds / (2.0 * $medianSeconds)) : 1.0;
        $spreadFactor = max(0.20, 1.0 - min(1.0, $spreadRatio));
        $confidenceScore = round((0.45 * $tierSpecificity) + (0.35 * $sampleFactor) + (0.20 * $spreadFactor), 2);

        if ($confidenceScore >= 0.75 && $n >= 5 && $tierSpecificity >= 0.70) {
            $confidenceLevel = 'high';
        } elseif ($confidenceScore >= 0.50 && $n >= $minSample && $tierSpecificity >= 0.60) {
            $confidenceLevel = 'moderate';
        } else {
            $confidenceLevel = 'low';
        }

        $start = $concern->submitted_at ? Carbon::parse($concern->submitted_at) : null;
        $estimatedResolvedAt = $start ? $start->copy()->addSeconds($medianSeconds) : null;
        $remainingSeconds = $estimatedResolvedAt
            ? ($estimatedResolvedAt->getTimestamp() - $now->getTimestamp())
            : null;

        if ($actualResolution !== null) {
            $actualResolution['actual_hours'] = $actualResolution['actual_duration_hours'];
            $actualResolution['delta_hours'] = round($actualResolution['actual_duration_hours'] - $estimatedHours, 2);
            $actualResolution['difference_hours'] = $actualResolution['delta_hours'];
            $actualResolution['within_predicted_range'] = (
                $actualResolution['actual_duration_seconds'] >= $p25Seconds &&
                $actualResolution['actual_duration_seconds'] <= $p75Seconds
            );
            $actualResolution['within_predicted_iqr'] = $actualResolution['within_predicted_range'];
        }

        $explanation = sprintf(
            'Estimated from the median resolution time of %d similar resolved concern%s (%s). Typical historical range: %sh–%sh (average %sh).',
            $n,
            $n === 1 ? '' : 's',
            $matchedTierLabel,
            $this->formatHours($p25Hours),
            $this->formatHours($p75Hours),
            $this->formatHours($meanHours)
        );

        return [
            'enabled'                     => true,
            'available'                   => true,
            'method'                      => 'historical_median',
            'estimated_duration_seconds'  => $medianSeconds,
            'estimated_duration_hours'    => $estimatedHours,
            'estimated_hours'             => $estimatedHours,
            'estimated_label'             => $this->formatHours($estimatedHours) . ' hours',
            'mean_duration_hours'         => $meanHours,
            'mean_hours'                  => $meanHours,
            'range_hours'                 => [
                'p25' => $p25Hours,
                'p75' => $p75Hours,
            ],
            'estimated_resolved_at'       => $estimatedResolvedAt?->toIso8601String(),
            'remaining_predicted_seconds' => $remainingSeconds,
            'sample_size'                 => $n,
            'min_sample_size_required'    => $minSample,
            'total_historical_pool'       => $pool->count(),
            'matched_tier'                => $matchedTier,
            'tier'                        => $matchedTier,
            'matched_tier_label'          => $matchedTierLabel,
            'tier_label'                  => $matchedTierLabel,
            'factors_used'                => $factorsUsed,
            'confidence_level'            => $confidenceLevel,
            'confidence_score'            => $confidenceScore,
            'explanation'                 => $explanation,
            'disclaimer'                  => self::DISCLAIMER,
            'actual_resolution'           => $actualResolution,
        ];
    }

    /**
     * Sanitized prediction payload safe for Student concern detail views
     * (omits internal priority override / staff-only diagnostics).
     *
     * @return array<string, mixed>
     */
    public function predictForStudent(Concern $concern, ?Collection $dataset = null, ?CarbonInterface $now = null): array
    {
        $full = $this->predict($concern, $dataset, $now);

        return [
            'enabled'                    => $full['enabled'] ?? true,
            'available'                  => $full['available'] ?? false,
            'estimated_duration_seconds' => $full['estimated_duration_seconds'] ?? null,
            'estimated_duration_hours'   => $full['estimated_duration_hours'] ?? null,
            'estimated_hours'            => $full['estimated_hours'] ?? null,
            'estimated_label'            => $full['estimated_label'] ?? null,
            'range_hours'                => $full['range_hours'] ?? null,
            'estimated_resolved_at'      => $full['estimated_resolved_at'] ?? null,
            'sample_size'                => $full['sample_size'] ?? 0,
            'confidence_level'           => $full['confidence_level'] ?? 'insufficient_data',
            'explanation'                => $full['available']
                ? sprintf(
                    'Based on %d previously resolved similar concern%s, similar cases typically take around %s hours (range: %s–%s hours).',
                    $full['sample_size'],
                    $full['sample_size'] === 1 ? '' : 's',
                    $this->formatHours((float) $full['estimated_duration_hours']),
                    $this->formatHours((float) ($full['range_hours']['p25'] ?? $full['estimated_duration_hours'])),
                    $this->formatHours((float) ($full['range_hours']['p75'] ?? $full['estimated_duration_hours']))
                )
                : 'There are not enough completed historical concerns of this type yet to display an estimated timeframe.',
            'disclaimer'                 => self::DISCLAIMER,
            'actual_resolution'          => $full['actual_resolution'] ?? null,
        ];
    }

    /**
     * Build aggregate resolution-time analytics (mean, median, by category,
     * by priority, by department/unit, and predicted vs. actual comparison).
     *
     * Optional $scopeFilter closure filters the cleaned historical dataset
     * for role-scoped views (Dean academic unit or Department Staff queue).
     *
     * @param  callable|null  $scopeFilter
     * @return array<string, mixed>
     */
    public function buildAnalytics(?callable $scopeFilter = null): array
    {
        $fullDataset = $this->getCleanHistoricalDataset();
        $scopedDataset = $scopeFilter ? $fullDataset->filter($scopeFilter)->values() : $fullDataset;

        $minSample = max(1, (int) config('prediction.min_sample_size', 3));
        $totalResolved = $scopedDataset->count();

        if ($totalResolved === 0) {
            $emptySummary = [
                'sample_size'             => 0,
                'low_sample_warning'      => true,
                'mean_resolution_hours'   => null,
                'median_resolution_hours' => null,
                'mean_resolution_days'    => null,
                'median_resolution_days'  => null,
                'iqr_hours'               => null,
            ];

            return [
                'enabled'                 => $this->isEnabled(),
                'supported'               => $this->isEnabled(),
                'summary'                 => $emptySummary,
                'sample_size'             => 0,
                'low_sample_warning'      => true,
                'mean_resolution_hours'   => null,
                'median_resolution_hours' => null,
                'mean_resolution_days'    => null,
                'median_resolution_days'  => null,
                'by_category'             => [],
                'by_priority'             => [],
                'by_department'           => [],
                'predicted_vs_actual'     => [
                    'evaluated_count'           => 0,
                    'mean_predicted_hours'      => null,
                    'mean_actual_hours'         => null,
                    'median_predicted_hours'    => null,
                    'median_actual_hours'       => null,
                    'mean_absolute_error_hours' => null,
                    'within_iqr_percentage'     => null,
                    'by_priority'               => [],
                ],
                'notice' => 'No valid historical resolved concerns are available for resolution-time analytics.',
            ];
        }

        $allSeconds = $scopedDataset->pluck('duration_seconds')->map(fn ($v) => (int) $v)->sort()->values();
        $meanHours = round($allSeconds->avg() / 3600, 1);
        $medianHours = round($this->percentile($allSeconds, 0.50) / 3600, 1);
        $p25AllHours = round($this->percentile($allSeconds, 0.25) / 3600, 1);
        $p75AllHours = round($this->percentile($allSeconds, 0.75) / 3600, 1);

        // 1. Resolution duration by category
        $byCategory = $scopedDataset
            ->groupBy('effective_category')
            ->map(function (Collection $items, string $cat) use ($minSample) {
                $secs = $items->pluck('duration_seconds')->map(fn ($v) => (int) $v)->sort()->values();
                $count = $secs->count();
                return [
                    'category'           => $this->formatCategoryName($cat),
                    'raw_category'       => $cat,
                    'count'              => $count,
                    'mean_hours'         => round($secs->avg() / 3600, 1),
                    'median_hours'       => round($this->percentile($secs, 0.50) / 3600, 1),
                    'p25_hours'          => round($this->percentile($secs, 0.25) / 3600, 1),
                    'p75_hours'          => round($this->percentile($secs, 0.75) / 3600, 1),
                    'low_sample_warning' => $count < $minSample,
                    'low_sample'         => $count < $minSample,
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();

        // 2. Resolution duration by priority
        $slaTargets = (array) config('sla.targets_hours', []);
        $priorityOrder = ['critical' => 1, 'high' => 2, 'medium' => 3, 'low' => 4];
        $byPriority = $scopedDataset
            ->groupBy('priority')
            ->map(function (Collection $items, string $prio) use ($minSample, $slaTargets) {
                $secs = $items->pluck('duration_seconds')->map(fn ($v) => (int) $v)->sort()->values();
                $count = $secs->count();
                return [
                    'priority'           => $prio,
                    'count'              => $count,
                    'mean_hours'         => round($secs->avg() / 3600, 1),
                    'median_hours'       => round($this->percentile($secs, 0.50) / 3600, 1),
                    'sla_target_hours'   => isset($slaTargets[$prio]) ? (float) $slaTargets[$prio] : null,
                    'low_sample_warning' => $count < $minSample,
                    'low_sample'         => $count < $minSample,
                ];
            })
            ->sortBy(fn ($row) => $priorityOrder[$row['priority']] ?? 99)
            ->values()
            ->all();

        // 3. Resolution duration by department / academic unit
        $byDepartment = $scopedDataset
            ->groupBy('unit_key')
            ->map(function (Collection $items) use ($minSample) {
                $first = $items->first();
                $secs = $items->pluck('duration_seconds')->map(fn ($v) => (int) $v)->sort()->values();
                $count = $secs->count();
                return [
                    'unit_key'           => $first['unit_key'],
                    'name'               => $first['unit_name'],
                    'workflow_type'      => $first['workflow_type'],
                    'count'              => $count,
                    'mean_hours'         => round($secs->avg() / 3600, 1),
                    'median_hours'       => round($this->percentile($secs, 0.50) / 3600, 1),
                    'low_sample_warning' => $count < $minSample,
                    'low_sample'         => $count < $minSample,
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();

        // 4. Leave-one-out comparison between predicted and actual resolution times
        $predictedHoursList = collect();
        $actualHoursList = collect();
        $absErrors = collect();
        $withinIqrCount = 0;
        $comparisonByPriority = [];

        foreach ($scopedDataset as $row) {
            $dummy = new Concern([
                'workflow_type'     => $row['workflow_type'],
                'academic_unit_id'  => $row['academic_unit_id'],
                'dept_id'           => $row['dept_id'],
                'category'          => $row['org_category'],
                'issue_category'    => $row['issue_category'],
                'issue_subcategory' => $row['issue_subcategory'],
                'priority'          => $row['priority'],
                'status'            => 'resolved',
                'submitted_at'      => $row['submitted_at'],
                'resolved_at'       => $row['resolved_at'],
            ]);
            $dummy->concern_id = $row['concern_id'];

            $pred = $this->predict($dummy, $fullDataset);
            if (!($pred['available'] ?? false) || $pred['estimated_duration_hours'] === null) {
                continue;
            }

            $predH = (float) $pred['estimated_duration_hours'];
            $actH = (float) $row['duration_hours'];
            $errH = abs($actH - $predH);

            $predictedHoursList->push($predH);
            $actualHoursList->push($actH);
            $absErrors->push($errH);

            $p25 = (float) ($pred['range_hours']['p25'] ?? $predH);
            $p75 = (float) ($pred['range_hours']['p75'] ?? $predH);
            if ($actH >= $p25 && $actH <= $p75) {
                $withinIqrCount++;
            }

            $prio = $row['priority'];
            $comparisonByPriority[$prio]['predicted'][] = $predH;
            $comparisonByPriority[$prio]['actual'][] = $actH;
            $comparisonByPriority[$prio]['errors'][] = $errH;
        }

        $evalCount = $predictedHoursList->count();
        $prioComparisonOut = [];
        foreach ($comparisonByPriority as $prio => $lists) {
            $pCol = collect($lists['predicted'])->sort()->values();
            $aCol = collect($lists['actual'])->sort()->values();
            $eCol = collect($lists['errors']);
            $prioComparisonOut[] = [
                'priority'                  => $prio,
                'count'                     => $pCol->count(),
                'median_predicted_hours'    => round($this->percentileFloat($pCol, 0.50), 1),
                'median_actual_hours'       => round($this->percentileFloat($aCol, 0.50), 1),
                'mean_predicted_hours'      => round($pCol->avg(), 1),
                'mean_actual_hours'         => round($aCol->avg(), 1),
                'avg_predicted_hours'       => round($pCol->avg(), 1),
                'avg_actual_hours'          => round($aCol->avg(), 1),
                'mean_absolute_error_hours' => round($eCol->avg(), 1),
                'mae_hours'                 => round($eCol->avg(), 1),
            ];
        }

        $sortedPred = $predictedHoursList->sort()->values();
        $sortedAct = $actualHoursList->sort()->values();

        $summaryPayload = [
            'sample_size'             => $totalResolved,
            'low_sample_warning'      => $totalResolved < $minSample,
            'mean_resolution_hours'   => $meanHours,
            'median_resolution_hours' => $medianHours,
            'mean_resolution_days'    => round($meanHours / 24, 1),
            'median_resolution_days'  => round($medianHours / 24, 1),
            'iqr_hours'               => [
                'p25' => $p25AllHours,
                'p75' => $p75AllHours,
            ],
        ];

        return [
            'enabled'                 => $this->isEnabled(),
            'supported'               => $this->isEnabled(),
            'summary'                 => $summaryPayload,
            'sample_size'             => $totalResolved,
            'low_sample_warning'      => $totalResolved < $minSample,
            'mean_resolution_hours'   => $meanHours,
            'median_resolution_hours' => $medianHours,
            'mean_resolution_days'    => round($meanHours / 24, 1),
            'median_resolution_days'  => round($medianHours / 24, 1),
            'by_category'             => $byCategory,
            'by_priority'             => $byPriority,
            'by_department'           => $byDepartment,
            'predicted_vs_actual'     => [
                'evaluated_count'           => $evalCount,
                'mean_predicted_hours'      => $evalCount > 0 ? round($sortedPred->avg(), 1) : null,
                'mean_actual_hours'         => $evalCount > 0 ? round($sortedAct->avg(), 1) : null,
                'median_predicted_hours'    => $evalCount > 0 ? round($this->percentileFloat($sortedPred, 0.50), 1) : null,
                'median_actual_hours'       => $evalCount > 0 ? round($this->percentileFloat($sortedAct, 0.50), 1) : null,
                'mean_absolute_error_hours' => $evalCount > 0 ? round($absErrors->avg(), 1) : null,
                'within_iqr_percentage'     => $evalCount > 0 ? round(($withinIqrCount / $evalCount) * 100, 1) : null,
                'by_priority'               => $prioComparisonOut,
            ],
            'notice' => $totalResolved < $minSample
                ? "Limited historical sample size ({$totalResolved} resolved concern(s)); interpret averages and medians with caution."
                : "Computed from {$totalResolved} validated historical resolved concern(s), excluding unresolved, invalid, duplicate, and synthetic test records.",
        ];
    }

    /**
     * Linear interpolation percentile over a sorted integer collection.
     *
     * @param  Collection<int, int>  $sorted
     */
    private function percentile(Collection $sorted, float $p): float
    {
        $n = $sorted->count();
        if ($n === 0) {
            return 0.0;
        }
        if ($n === 1) {
            return (float) $sorted->first();
        }

        $pos = $p * ($n - 1);
        $lower = (int) floor($pos);
        $upper = (int) ceil($pos);
        $weight = $pos - $lower;

        return ((1.0 - $weight) * (float) $sorted[$lower]) + ($weight * (float) $sorted[$upper]);
    }

    /**
     * @param  Collection<int, float>  $sorted
     */
    private function percentileFloat(Collection $sorted, float $p): float
    {
        $n = $sorted->count();
        if ($n === 0) {
            return 0.0;
        }
        if ($n === 1) {
            return (float) $sorted->first();
        }

        $pos = $p * ($n - 1);
        $lower = (int) floor($pos);
        $upper = (int) ceil($pos);
        $weight = $pos - $lower;

        return ((1.0 - $weight) * (float) $sorted[$lower]) + ($weight * (float) $sorted[$upper]);
    }

    private function formatCategoryName(string $category): string
    {
        return match ($category) {
            'academic_units'          => 'Academic Concerns',
            'operational_departments' => 'Operational Departments',
            default                   => $category,
        };
    }

    private function formatHours(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 1, '.', ''), '0'), '.');
    }
}
