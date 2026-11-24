<?php

namespace App\Services;

use App\Support\SummaryTextHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Calls the Google Gemini API to perform genuine AI-powered sentiment analysis.
 *
 * Returns a validated sentiment_label (positive | neutral | negative) and a
 * normalized sentiment_score from -1.0 to 1.0.  Falls back gracefully to null
 * so callers can apply LocalSentimentService as a fallback.
 *
 * The API key is read exclusively from config('services.gemini.api_key') and
 * is never logged, returned in responses, or exposed to the frontend.
 */
class GeminiSentimentService
{
    /** Allowed sentiment label values the model may return. */
    private const ALLOWED_LABELS = ['positive', 'neutral', 'negative'];

    /** Allowed issue categories. */
    private const ALLOWED_CATEGORIES = [
        'IT and Technology',
        'Academic Concerns',
        'Non-Academic Concerns',
        'Other / Needs Review',
    ];

    /** Allowed priority values. */
    private const ALLOWED_PRIORITIES = ['low', 'medium', 'high', 'critical'];

    /**
     * Determine whether live Gemini API requests are enabled and configured.
     * Gemini is never automatically enabled merely because an API key exists;
     * services.gemini.enabled must be explicitly true.
     */
    public function isAvailable(): bool
    {
        $enabled = filter_var(config('services.gemini.enabled', false), FILTER_VALIDATE_BOOLEAN);
        $apiKey = trim((string) config('services.gemini.api_key', ''));

        return $enabled && $apiKey !== '';
    }

    /**
     * Return a sanitized runtime verification snapshot of the Gemini integration
     * without exposing the API key or any sensitive configuration.
     *
     * @return array{
     *     environment: string,
     *     enabled_flag: bool,
     *     api_key_configured: bool,
     *     is_available: bool,
     *     active_engine: string,
     *     model: string,
     *     timeout_seconds: int,
     *     max_retries: int,
     *     external_data_warning: string
     * }
     */
    public function runtimeStatus(): array
    {
        $env = (string) app()->environment();
        $enabledFlag = filter_var(config('services.gemini.enabled', false), FILTER_VALIDATE_BOOLEAN);
        $apiKeyConfigured = trim((string) config('services.gemini.api_key', '')) !== '';
        $available = $this->isAvailable();

        return [
            'environment'           => $env,
            'enabled_flag'          => $enabledFlag,
            'api_key_configured'    => $apiKeyConfigured,
            'is_available'          => $available,
            'active_engine'         => $available ? 'Gemini AI' : 'Rule-based Engine',
            'model'                 => $this->resolveModel(),
            'timeout_seconds'       => max(1, (int) config('services.gemini.timeout', 35)),
            'max_retries'           => max(0, min(2, (int) config('services.gemini.max_retries', 1))),
            'external_data_warning' => $available
                ? 'ACTIVE: Student concern titles and descriptions will be sent to the external Google Gemini API. Use synthetic test data on Free-tier Gemini or ensure formal institutional approval in production.'
                : 'INACTIVE: External Gemini calls are disabled; all concerns are processed locally by the Rule-based Engine.',
        ];
    }

    /**
     * Resolve the configured Gemini model name from config('services.gemini.model').
     */
    private function resolveModel(): string
    {
        $model = trim((string) config('services.gemini.model', 'gemini-3.8-flash'));

        return $model !== '' ? $model : 'gemini-3.8-flash';
    }

    /**
     * Build the Gemini generateContent endpoint URL for the configured model.
     */
    private function endpoint(): string
    {
        return 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->resolveModel() . ':generateContent';
    }

    /**
     * Execute a POST request to Gemini with bounded retries ONLY on transient 5xx / network errors.
     * Never retries HTTP 429 or 4xx responses, and never logs keys, URLs, or concern text.
     *
     * @param  array<string, mixed>  $payload
     * @return array<mixed>|null
     */
    private function postToGemini(array $payload, string $operation): ?array
    {
        if (!$this->isAvailable()) {
            Log::debug("GeminiSentimentService: Disabled or API key missing for {$operation}, skipping.");
            return null;
        }

        $apiKey = trim((string) config('services.gemini.api_key', ''));
        $timeout = max(1, (int) config('services.gemini.timeout', 20));
        $maxRetries = max(0, min(2, (int) config('services.gemini.max_retries', 1)));
        $endpoint = $this->endpoint();
        $model = $this->resolveModel();

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'x-goog-api-key' => $apiKey,
                    'Content-Type'   => 'application/json',
                ])
                    ->timeout($timeout)
                    ->post($endpoint, $payload);

                if ($response->successful()) {
                    $json = $response->json();
                    return is_array($json) ? $json : null;
                }

                $status = $response->status();
                $body = $response->json();
                $providerStatus = is_array($body) ? ($body['error']['status'] ?? null) : null;
                $providerCode = is_array($body) ? ($body['error']['code'] ?? null) : null;

                Log::warning("GeminiSentimentService: {$operation} API returned HTTP {$status}.", [
                    'http_status'     => $status,
                    'provider_status' => is_string($providerStatus) ? $providerStatus : null,
                    'provider_code'   => is_scalar($providerCode) ? $providerCode : null,
                    'model'           => $model,
                ]);

                // Never retry rate-limits (429) or client errors (4xx); only retry transient 5xx.
                if ($status < 500 || $attempt >= $maxRetries) {
                    return null;
                }
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                Log::warning("GeminiSentimentService: {$operation} connection error.", [
                    'exception' => class_basename($e),
                    'model'     => $model,
                ]);

                if ($attempt >= $maxRetries) {
                    return null;
                }
            } catch (\Throwable $e) {
                Log::warning("GeminiSentimentService: {$operation} unexpected error.", [
                    'exception' => class_basename($e),
                    'model'     => $model,
                ]);
                return null;
            }

            if (!app()->runningUnitTests()) {
                usleep(250_000);
            }
        }

        return null;
    }

    /**
     * Analyze the sentiment, classification, priority, and summary of a concern using Gemini.
     *
     * @param  string  $title
     * @param  string  $description
     * @return array{
     *     sentiment_score: float,
     *     sentiment_label: string,
     *     issue_category?: string,
     *     issue_subcategory?: string,
     *     classification_explanation?: string,
     *     classification_confidence?: float,
     *     priority_suggestion?: string,
     *     priority_explanation?: string,
     *     summary?: string,
     *     api_tokens_used: int
     * }|null
     *         Returns null when disabled, the API key is absent, the call fails, or the response is invalid.
     */
    public function analyzeConcern(string $title, string $description, ?string $workflowType = null): ?array
    {
        $prompt = $this->buildPrompt($title, $description, $workflowType);

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature'      => 0.1,
                'responseMimeType' => 'application/json',
            ],
        ];

        $responseBody = $this->postToGemini($payload, 'analyzeConcern');
        if ($responseBody === null) {
            return null;
        }

        return $this->parseResponse($responseBody, $workflowType, $description);
    }

    /**
     * Build the structured prompt sent to Gemini.
     *
     * The prompt instructs Gemini to perform genuine semantic condensation (abstractive
     * third-person summarization) rather than copying or slightly trimming the student's text.
     */
    private function buildPrompt(string $title, string $description, ?string $workflowType = null): string
    {
        $maxLen = (int) config('classification.max_summary_length', 800);
        $workflowHint = match ($workflowType) {
            'non_academic' => "The student filed this under the NON-ACADEMIC workflow (operational/administrative offices). Use \"Non-Academic Concerns\" (or \"IT and Technology\"/\"Other / Needs Review\"), never \"Academic Concerns\".",
            'academic' => "The student filed this under the ACADEMIC workflow (their academic unit). Do not use \"Non-Academic Concerns\".",
            default => 'No workflow was specified.',
        };

        return <<<PROMPT
Analyze the following student concern submitted to a university management system.

Title: {$title}
Description: {$description}

Workflow context: {$workflowHint}

Instructions:
1. Sentiment:
   - Evaluate overall emotional tone ("positive", "neutral", "negative").
   - Normalized score between -1.0 (most negative) and 1.0 (most positive).
   - Do NOT equate urgency or seriousness with negative sentiment, and never confuse student sentiment with issue priority.
2. Issue Classification:
   - "issue_category": choose from "IT and Technology", "Academic Concerns", "Non-Academic Concerns", "Other / Needs Review".
   - "issue_subcategory": specific problem area (e.g. "Computer Hardware", "Network / Internet", "Classroom Issues", "Laboratory Issues", "Cashier / Payment", "Campus Facilities", "General Inquiries").
   - Student-to-student harassment, bullying or conflict, and requests for Guidance Center counseling, are Non-Academic Concerns (subcategories "Peer Conflict / Harassment" or "Student Affairs"). They are NOT "Personnel / Staff Grievance", which is only for complaints about school employees.
   - "Instructor / Teaching Concerns" is only for legitimate academic concerns about an instructor's teaching, grading or syllabus.
   - "classification_explanation": 1 concise sentence explaining the classification.
   - "classification_confidence": float between 0.0 and 1.0.
3. Priority Assessment:
   - "priority_suggestion": choose from "critical", "high", "medium", "low".
   - Evaluate objective scope (campus-wide, department/lab-wide, individual) and safety/operational impact.
   - Do NOT mark critical solely for urgent words ("asap", "urgent") or anger.
   - "priority_explanation": 1 concise sentence explaining the priority rationale.
4. Abstractive Issue Summary ("summary"):
   - Write a genuine semantic condensation of the entire concern, NOT an extractive copy or light edit of the student's sentences.
   - Use natural, professional, third-person language (e.g., "The student reports...", "They request...").
   - Length: usually 2-3 concise, complete sentences (1 sentence is acceptable for a simple concern; do not force extra sentences), preferring approximately 40-60% shorter than the original description when content allows, and never exceeding {$maxLen} characters (never cut off mid-sentence and never end with "...").
   - Content:
     a) Identify the primary issue across the whole description.
     b) Preserve important dates, locations, amounts, course/equipment codes, and reference numbers.
     c) Include how the situation affects the student or class.
     d) Include the requested assistance or resolution.
   - Faithfulness & Epistemic Accuracy:
     a) Preserve uncertainty such as "may", "possible", or "reported".
     b) Never turn an unverified allegation or possibility into a confirmed fact.
     c) Never introduce facts, names, dates, or incidents absent from the original description.
     d) Remove repetitive explanations, greetings, sign-offs, and introductory filler.
     e) Always generate a complete summary even when sentiment is neutral or positive.
   - Example of desired abstractive summarization:
     Original: "I am dealing with an acute family and personal crisis that has severely impacted my attendance and exam performance at UCLM this week. Because my academic standing and subject retention are at risk before the upcoming grading cutoff, I need a consultation and counseling endorsement from the UCLM Guidance Center today."
     Desired summary: "The student reports a family and personal crisis that is affecting attendance and academic performance. They urgently request counseling assistance from the Guidance Center to address potential academic consequences before the grading deadline."

Return ONLY a valid JSON object matching these keys:
{
  "sentiment_label": "neutral",
  "sentiment_score": 0.0,
  "issue_category": "IT and Technology",
  "issue_subcategory": "Computer Hardware",
  "classification_explanation": "Issue involves lab computer failing to boot during classes.",
  "classification_confidence": 0.90,
  "priority_suggestion": "medium",
  "priority_explanation": "Localized workstation failure in laboratory with alternative machines available.",
  "summary": "The student reports that workstation PC-02 in Computer Lab 2 failed to boot with an operating system error on November 12, preventing completion of a laboratory exercise. They request a hardware inspection and repair before the next scheduled lab session."
}
PROMPT;
    }

    /**
     * Parse and validate the Gemini API JSON response.
     *
     * @param  array<mixed>|null  $responseBody
     * @return array<string, mixed>|null
     */
    private function parseResponse(?array $responseBody, ?string $workflowType = null, string $originalDescription = ''): ?array
    {
        if (!is_array($responseBody)) {
            Log::warning('GeminiSentimentService: Empty or non-array response body.');
            return null;
        }

        // Extract generated text from candidates[0].content.parts[0].text
        $text = $responseBody['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!is_string($text) || trim($text) === '') {
            Log::warning('GeminiSentimentService: Missing or empty text in response candidates.');
            return null;
        }

        $decoded = json_decode(trim($text), true);

        if (!is_array($decoded)) {
            Log::warning('GeminiSentimentService: Could not JSON-decode model output.');
            return null;
        }

        // Validate sentiment_label (case-insensitive)
        $label = isset($decoded['sentiment_label']) && is_string($decoded['sentiment_label'])
            ? strtolower(trim($decoded['sentiment_label']))
            : null;
        if (!in_array($label, self::ALLOWED_LABELS, true)) {
            Log::warning(
                'GeminiSentimentService: Invalid sentiment_label "' . ($label ?? 'null') . '" in model output.'
            );
            return null;
        }

        // Validate and clamp sentiment_score
        if (!isset($decoded['sentiment_score']) || !is_numeric($decoded['sentiment_score'])) {
            Log::warning('GeminiSentimentService: Missing or non-numeric sentiment_score in model output.');
            return null;
        }

        $score = (float) $decoded['sentiment_score'];
        $score = max(-1.0, min(1.0, round($score, 3)));

        // Extract token usage (non-zero value distinguishes Gemini from local fallback in sentiment_logs)
        $usage      = $responseBody['usageMetadata'] ?? [];
        $tokensUsed = (int) ($usage['totalTokenCount']
            ?? (($usage['promptTokenCount'] ?? 0) + ($usage['candidatesTokenCount'] ?? 0)));

        $result = [
            'sentiment_score'  => $score,
            'sentiment_label'  => $label,
            'api_tokens_used'  => $tokensUsed > 0 ? $tokensUsed : 1, // at least 1 to mark as Gemini result
            'ai_source'        => 'gemini',
        ];

        // Optional Phase 7 classification fields if provided by model
        if (!empty($decoded['issue_category']) && in_array($decoded['issue_category'], self::ALLOWED_CATEGORIES, true)) {
            $cat = $decoded['issue_category'];
            if ($workflowType === 'non_academic' && $cat === 'Academic Concerns') {
                $cat = 'Non-Academic Concerns';
            } elseif ($workflowType === 'academic' && $cat === 'Non-Academic Concerns') {
                $cat = 'Academic Concerns';
            }
            $result['issue_category'] = $cat;
        }

        if (!empty($decoded['issue_subcategory']) && is_string($decoded['issue_subcategory'])) {
            $sub = substr(trim($decoded['issue_subcategory']), 0, 100);
            $targetCategory = $result['issue_category'] ?? null;
            $taxonomy = (array) config('classification.categories', []);

            // Check if subcategory exists directly in the target category
            $validInTarget = $targetCategory && isset($taxonomy[$targetCategory]) && array_key_exists($sub, $taxonomy[$targetCategory]);

            if ($validInTarget) {
                $result['issue_subcategory'] = $sub;
            } else {
                // Check if it strictly belongs to an incompatible opposing domain
                $isAcademicSub = isset($taxonomy['Academic Concerns']) && array_key_exists($sub, $taxonomy['Academic Concerns']);
                $isNonAcademicSub = isset($taxonomy['Non-Academic Concerns']) && array_key_exists($sub, $taxonomy['Non-Academic Concerns']);

                if ($targetCategory === 'Non-Academic Concerns' && $isAcademicSub) {
                    // Contradiction: academic subcategory under non-academic category -> omit
                } elseif ($targetCategory === 'Academic Concerns' && $isNonAcademicSub) {
                    // Contradiction: non-academic subcategory under academic category -> omit
                } else {
                    // Legitimate descriptive subcategory (e.g. "Assessment and Examination", "General Inquiries", etc.)
                    $result['issue_subcategory'] = $sub;
                }
            }
        }
        if (!empty($decoded['classification_explanation']) && is_string($decoded['classification_explanation'])) {
            $result['classification_explanation'] = trim($decoded['classification_explanation']);
        }
        if (isset($decoded['classification_confidence']) && is_numeric($decoded['classification_confidence'])) {
            $result['classification_confidence'] = max(0.0, min(1.0, round((float) $decoded['classification_confidence'], 3)));
        }
        if (!empty($decoded['priority_suggestion']) && in_array(strtolower(trim($decoded['priority_suggestion'])), self::ALLOWED_PRIORITIES, true)) {
            $result['priority_suggestion'] = strtolower(trim($decoded['priority_suggestion']));
        }
        if (!empty($decoded['priority_explanation']) && is_string($decoded['priority_explanation'])) {
            $result['priority_explanation'] = trim($decoded['priority_explanation']);
        }
        if (!empty($decoded['summary']) && is_string($decoded['summary'])) {
            $maxLen = (int) config('classification.max_summary_length', 800);
            $summary = SummaryTextHelper::condenseToSentenceBudget(trim($decoded['summary']), $maxLen);
            if ($summary !== '' && !SummaryTextHelper::isVerbatimOrNearCopy($summary, $originalDescription)) {
                $result['summary'] = $summary;
            }
        }

        return $result;
    }

    /**
     * Generate trend analysis narrative from aggregated concern statistics using Gemini.
     *
     * @param  array<string, mixed>  $stats
     * @return string|null
     */
    public function generateTrendNarrative(array $stats): ?string
    {
        $statsJson = json_encode($stats, JSON_PRETTY_PRINT);
        $prompt = <<<PROMPT
You are analyzing student concern data for a school administration report.
Given the following concern statistics for the past month, write a concise, professional 3-4 sentence trend analysis.
Highlight notable patterns, resolution rates, priority distribution, and any areas requiring administrative attention.
Return ONLY a JSON object with the key "narrative".

Stats:
{$statsJson}

Example output:
{"narrative": "Over the past 30 days, student concerns have shown..."}
PROMPT;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature'      => 0.2,
                'responseMimeType' => 'application/json',
            ],
        ];

        $body = $this->postToGemini($payload, 'generateTrendNarrative');
        if ($body === null) {
            return null;
        }

        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!is_string($text) || trim($text) === '') {
            return null;
        }

        $decoded = json_decode(trim($text), true);
        if (!is_array($decoded) || empty($decoded['narrative']) || !is_string($decoded['narrative'])) {
            return null;
        }

        return trim($decoded['narrative']);
    }

    /**
     * Determine whether two concerns describe the exact same underlying incident.
     */
    public function verifyIncidentSimilarity(string $titleA, string $descA, string $titleB, string $descB): ?bool
    {
        $prompt = <<<PROMPT
Compare the following two student concern reports from a university management system.
Determine whether they describe the EXACT SAME underlying physical issue, outage, equipment breakdown, or policy occurrence.
Do NOT group them if they simply share generic words but describe different rooms, different courses, or different events.

Report A:
Title: {$titleA}
Description: {$descA}

Report B:
Title: {$titleB}
Description: {$descB}

Return ONLY a JSON object:
{"same_incident": true, "confidence": 0.95}
PROMPT;

        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature' => 0.0,
                'responseMimeType' => 'application/json',
            ],
        ];

        $body = $this->postToGemini($payload, 'verifyIncidentSimilarity');
        if ($body === null) {
            return null;
        }

        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!is_string($text) || trim($text) === '') {
            return null;
        }

        $decoded = json_decode(trim($text), true);
        if (!is_array($decoded) || !isset($decoded['same_incident'])) {
            return null;
        }

        return (bool) $decoded['same_incident'];
    }

    /**
     * Recommend an operational department using Gemini for non-academic concerns.
     *
     * @param  string  $title
     * @param  string  $description
     * @param  \Illuminate\Support\Collection|array  $departments
     * @return array{
     *     recommended_department_id: int,
     *     recommended_department_name: string,
     *     confidence: float,
     *     explanation: string,
     *     requires_manual_selection: bool,
     *     source: string
     * }|null
     */
    public function recommendOperationalDepartment(string $title, string $description, $departments): ?array
    {
        $deptList = [];
        foreach ($departments as $dept) {
            $deptList[] = [
                'id' => (int) $dept->id,
                'name' => $dept->name,
            ];
        }

        if (empty($deptList)) {
            return null;
        }

        $deptJson = json_encode($deptList, JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
You are an intelligent concern routing engine for a university management system.
A student is submitting a non-academic concern.

Available operational departments:
{$deptJson}

Student Concern:
Title: {$title}
Description: {$description}

Task:
Analyze the concern title and description. Recommend the single most appropriate operational department from the available list above.
Rules:
1. ONLY select a department that exists in the available list above with its exact ID and Name. Never invent a department.
2. Provide a confidence score between 0.0 (uncertain) and 1.0 (highly confident).
3. If the concern does not clearly match any operational department or confidence is low, set confidence below 0.50.
4. Provide a 1-2 sentence factual explanation for your recommendation.
5. Student-to-student peer conflict, harassment, bullying, or counseling consultation requests should be routed to Guidance Center (or Student Affairs Office / SAO if Guidance Center is unavailable).

Return ONLY a JSON object:
{
  "department_id": <int>,
  "department_name": "<string>",
  "confidence": <float>,
  "explanation": "<string>"
}
PROMPT;

        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'responseMimeType' => 'application/json',
            ],
        ];

        $body = $this->postToGemini($payload, 'recommendOperationalDepartment');
        if ($body === null) {
            return null;
        }

        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!is_string($text) || trim($text) === '') {
            return null;
        }

        $decoded = json_decode(trim($text), true);
        if (!is_array($decoded) || !isset($decoded['department_id'])) {
            return null;
        }

        $deptId = (int) $decoded['department_id'];
        $confidence = isset($decoded['confidence']) && is_numeric($decoded['confidence'])
            ? (float) $decoded['confidence']
            : 0.5;
        $confidence = max(0.0, min(1.0, round($confidence, 2)));
        $explanation = !empty($decoded['explanation']) && is_string($decoded['explanation'])
            ? trim($decoded['explanation'])
            : 'Recommended based on concern content.';

        // Validate against actual departments collection
        $matched = collect($departments)->firstWhere('id', $deptId);
        if (!$matched && !empty($decoded['department_name'])) {
            $matched = collect($departments)->first(function ($d) use ($decoded) {
                return strcasecmp($d->name, trim($decoded['department_name'])) === 0;
            });
        }

        if (!$matched) {
            Log::warning('Gemini recommended non-existent department ID ' . $deptId);
            return null;
        }

        return [
            'recommended_department_id' => $matched->id,
            'recommended_department_name' => $matched->name,
            'confidence' => $confidence,
            'explanation' => $explanation,
            'requires_manual_selection' => $confidence < 0.60,
            'source' => 'gemini',
        ];
    }
}
