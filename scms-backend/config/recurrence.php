<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rolling Window Configuration
    |--------------------------------------------------------------------------
    | The number of days used for active recurrence counting. Only reports
    | submitted within this rolling window count toward tier escalation.
    */
    'window_days' => 7,

    /*
    |--------------------------------------------------------------------------
    | Recurrence Escalation Thresholds (Unique Student Reports)
    |--------------------------------------------------------------------------
    | Distinct students submitting similar reports within the rolling window.
    */
    'thresholds' => [
        'high'           => 10,  // 10–19 unique students => High Priority
        'critical'       => 20,  // 20–29 unique students => Critical Escalation
        'major_critical' => 30,  // 30+ unique students    => Major Critical Tier
    ],

    /*
    |--------------------------------------------------------------------------
    | Similarity Matching Configuration
    |--------------------------------------------------------------------------
    | Minimum Jaccard token similarity required for candidate clustering.
    */
    'jaccard_threshold' => 0.60,

    /*
    |--------------------------------------------------------------------------
    | Generic University Stop Words
    |--------------------------------------------------------------------------
    | Stop words stripped during similarity tokenization to prevent false positives.
    */
    'stop_words' => [
        'student', 'students', 'concern', 'concerns', 'issue', 'issues', 'problem',
        'problems', 'department', 'departments', 'office', 'offices', 'system',
        'please', 'help', 'submitted', 'regarding', 'inquiry', 'request', 'assistance',
        'university', 'school', 'college', 'admin', 'dean', 'staff', 'faculty',
        'campus', 'goodday', 'hello', 'sir', 'maam', 'the', 'and', 'to', 'of',
        'a', 'in', 'that', 'is', 'was', 'for', 'it', 'with', 'as', 'on', 'at',
        'by', 'this', 'have', 'from', 'or', 'you', 'my', 'we', 'our', 'need', 'want',
    ],
];
