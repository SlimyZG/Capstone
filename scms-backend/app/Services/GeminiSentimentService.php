<?php

namespace App\Services;

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
     *         Returns null when the API key is absent, the call fails, or the response is invalid.
     */
    public function analyzeConcern(string $title, string $description): ?array
    {
        $apiKey = config('services.gemini.api_key', '');
        $model  = config('services.gemini.model', 'gemini-3.5-flash');
        $timeout = (int) config('services.gemini.timeout', 20);

        // Guard: skip API call when key is not configured
        if (empty($apiKey)) {
            Log::debug('GeminiSentimentService: API key not configured, skipping.');
            return null;
        }

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $prompt = $this->buildPrompt($title, $description);

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

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type'   => 'application/json',
            ])
                ->timeout($timeout)
                ->post($endpoint, $payload);

            if (!$response->successful()) {
                // Log status code only — no key, no concern text
                Log::warning(
                    'GeminiSentimentService: API returned HTTP ' . $response->status() . '.'
                );
                return null;
            }

            return $this->parseResponse($response->json());
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('GeminiSentimentService: Connection timeout or network error.');
            return null;
        } catch (\Exception $e) {
            Log::warning('GeminiSentimentService: Unexpected error — ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Build the structured prompt sent to Gemini.
     *
     * The prompt is deliberately brief; it does not include the raw concern text
     * in the log — only in the API payload, which is not stored.
     */
    private function buildPrompt(string $title, string $description): string
    {
        return <<<PROMPT
Analyze the following student concern submitted to a university management system.

Title: {$title}
Description: {$description}

Instructions:
1. Sentiment:
   - Evaluate overall emotional tone ("positive", "neutral", "negative").
   - Normalized score between -1.0 (most negative) and 1.0 (most positive).
   - Do NOT equate urgency or seriousness with negative sentiment.
2. Issue Classification:
   - "issue_category": choose from "IT and Technology", "Academic Concerns", "Non-Academic Concerns", "Other / Needs Review".
   - "issue_subcategory": specific problem area (e.g. "Computer Hardware", "Network / Internet", "Classroom Issues", "Laboratory Issues", "Cashier / Payment", "Campus Facilities", "General Inquiries").
   - "classification_explanation": 1 concise sentence explaining the classification.
   - "classification_confidence": float between 0.0 and 1.0.
3. Priority Assessment:
   - "priority_suggestion": choose from "critical", "high", "medium", "low".
   - Evaluate objective scope (campus-wide, department/lab-wide, individual) and safety/operational impact.
   - Do NOT mark critical solely for urgent words ("asap", "urgent") or anger.
   - "priority_explanation": 1 concise sentence explaining the priority rationale.
4. Summary:
   - "summary": concise, factual summary (maximum 300 characters) capturing problem, location/equipment, and impact without repeating polite intros.

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
  "summary": "Lab 2: Computer failed to boot with operating system error, interrupting student coursework."
}
PROMPT;
    }

    /**
     * Parse and validate the Gemini API JSON response.
     *
     * @param  array<mixed>|null  $responseBody
     * @return array<string, mixed>|null
     */
    private function parseResponse(?array $responseBody): ?array
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

        // Validate sentiment_label
        $label = $decoded['sentiment_label'] ?? null;
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
        ];

        // Optional Phase 7 classification fields if provided by model
        if (!empty($decoded['issue_category']) && in_array($decoded['issue_category'], self::ALLOWED_CATEGORIES, true)) {
            $result['issue_category'] = $decoded['issue_category'];
        }
        if (!empty($decoded['issue_subcategory']) && is_string($decoded['issue_subcategory'])) {
            $result['issue_subcategory'] = substr(trim($decoded['issue_subcategory']), 0, 100);
        }
        if (!empty($decoded['classification_explanation']) && is_string($decoded['classification_explanation'])) {
            $result['classification_explanation'] = trim($decoded['classification_explanation']);
        }
        if (isset($decoded['classification_confidence']) && is_numeric($decoded['classification_confidence'])) {
            $result['classification_confidence'] = max(0.0, min(1.0, round((float) $decoded['classification_confidence'], 3)));
        }
        if (!empty($decoded['priority_suggestion']) && in_array($decoded['priority_suggestion'], self::ALLOWED_PRIORITIES, true)) {
            $result['priority_suggestion'] = $decoded['priority_suggestion'];
        }
        if (!empty($decoded['priority_explanation']) && is_string($decoded['priority_explanation'])) {
            $result['priority_explanation'] = trim($decoded['priority_explanation']);
        }
        if (!empty($decoded['summary']) && is_string($decoded['summary'])) {
            $maxLen = (int) config('classification.max_summary_length', 300);
            $summary = trim($decoded['summary']);
            if (mb_strlen($summary) > $maxLen) {
                $summary = mb_substr($summary, 0, $maxLen - 3) . '...';
            }
            $result['summary'] = $summary;
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
        $apiKey = config('services.gemini.api_key', '');
        $model  = config('services.gemini.model', 'gemini-3.5-flash');
        $timeout = (int) config('services.gemini.timeout', 20);

        if (empty($apiKey)) {
            Log::debug('GeminiSentimentService: API key not configured for trend narrative, skipping.');
            return null;
        }

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

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

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type'   => 'application/json',
            ])
                ->timeout($timeout)
                ->post($endpoint, $payload);

            if (!$response->successful()) {
                Log::warning('GeminiSentimentService: Trend narrative API returned HTTP ' . $response->status() . '.');
                return null;
            }

            $body = $response->json();
            $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if (!is_string($text) || trim($text) === '') {
                return null;
            }

            $decoded = json_decode(trim($text), true);
            if (!is_array($decoded) || empty($decoded['narrative']) || !is_string($decoded['narrative'])) {
                return null;
            }

            return trim($decoded['narrative']);
        } catch (\Exception $e) {
            Log::warning('GeminiSentimentService: Trend narrative exception — ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Determine whether two concerns describe the exact same underlying incident.
     */
    public function verifyIncidentSimilarity(string $titleA, string $descA, string $titleB, string $descB): ?bool
    {
        $apiKey = config('services.gemini.api_key', '');
        if (empty($apiKey)) {
            return null;
        }

        $model   = config('services.gemini.model', 'gemini-3.5-flash');
        $timeout = (int) config('services.gemini.timeout', 20);
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

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

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type'   => 'application/json',
            ])->timeout($timeout)->post($endpoint, $payload);

            if (!$response->successful()) {
                return null;
            }

            $body = $response->json();
            $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if (!$text) {
                return null;
            }

            $decoded = json_decode(trim($text), true);
            if (!is_array($decoded) || !isset($decoded['same_incident'])) {
                return null;
            }

            return (bool) $decoded['same_incident'];
        } catch (\Throwable $e) {
            Log::warning('Gemini verifyIncidentSimilarity failed: ' . $e->getMessage());
            return null;
        }
    }
}
