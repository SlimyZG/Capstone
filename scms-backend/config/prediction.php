<?php

/*
|--------------------------------------------------------------------------
| Predictive Resolution-Time Estimation Configuration (Phase 9)
|--------------------------------------------------------------------------
|
| Configures the explainable statistical resolution-time estimator.
| Estimates are derived from historical resolved concerns (median duration
| and interquartile range) using a multi-tier fallback hierarchy.
|
| IMPORTANT: Predictions are informational estimates based on historical
| patterns and do NOT replace the official SLA targets in config/sla.php.
|
*/

return [

    // Master switch for predictive resolution-time estimation.
    'enabled' => (bool) env('PREDICTION_ENABLED', true),

    // Minimum number of resolved historical concerns required in a specific
    // cohort tier (Tiers 1–4) before that tier's median is used.
    'min_sample_size' => (int) env('PREDICTION_MIN_SAMPLE_SIZE', 3),

    // Minimum number of resolved concerns required for the institution-wide
    // fallback baseline (Tier 5). Below this, the system reports insufficient
    // historical data rather than fabricating an estimate.
    'min_baseline_sample_size' => (int) env('PREDICTION_MIN_BASELINE_SAMPLE_SIZE', 2),

    // Exclude identifiable synthetic/test records (e.g. "SYNTH:" prefix or
    // "@scms.test" emails) from historical training calculations.
    'exclude_synthetic' => (bool) env('PREDICTION_EXCLUDE_SYNTHETIC', true),

    // Upper sanity bound (in hours) for a valid historical resolution duration
    // (default 8760 hours = 365 days). Negative, zero, or extreme durations
    // above this bound are excluded as invalid data.
    'max_valid_duration_hours' => (float) env('PREDICTION_MAX_VALID_HOURS', 8760),

    // Cache TTL (in seconds) for precomputed historical cohort records.
    'cache_ttl_seconds' => (int) env('PREDICTION_CACHE_TTL', 300),
];
