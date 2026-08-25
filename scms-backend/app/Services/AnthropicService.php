<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicService
{
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = env('ANTHROPIC_API_KEY', '');
        $this->model = 'claude-3-5-sonnet-20241022';
    }

    /**
     * Call the Anthropic API to analyze a concern.
     */
    public function analyzeConcern(string $title, string $description): ?array
    {
        $systemPrompt = "You are an AI assistant analyzing student concerns for a university. " .
            "Given the concern title and description, you must return a strictly valid JSON object with EXACTLY these keys: " .
            "1) \"sentiment_score\": A decimal between -1.0 (very negative) and 1.0 (very positive). " .
            "2) \"sentiment_label\": One of \"positive\", \"neutral\", \"negative\", \"urgent\". " .
            "3) \"priority_suggestion\": One of \"low\", \"medium\", \"high\", \"critical\". " .
            "4) \"summary\": A brief 2-3 sentence summary of the core issue. " .
            "Return ONLY the JSON. No markdown backticks, no conversational text.";

        $userPrompt = "Title: {$title}\nDescription: {$description}";

        return $this->makeRequest($systemPrompt, $userPrompt);
    }

    /**
     * Generate a resolution summary from a message thread.
     */
    public function generateResolutionSummary(string $threadContent): ?array
    {
        $systemPrompt = "Given this concern thread between a student and support staff, write a brief 2-3 sentence resolution summary. " .
            "State the issue, the action taken, and the outcome. Return only the summary text as a JSON object with the key \"summary\". " .
            "Return ONLY the JSON. No markdown backticks, no conversational text.";

        return $this->makeRequest($systemPrompt, $threadContent);
    }

    /**
     * Generate trend analysis narrative from aggregated stats.
     */
    public function generateTrendNarrative(string $statsJson): ?array
    {
        $systemPrompt = "You are analyzing student concern data for a school administration report. " .
            "Given the following concern statistics for the past month, write a 3-5 sentence trend analysis. " .
            "Highlight notable patterns, spikes, and any areas that need administrative attention. " .
            "Return only the narrative text as a JSON object with the key \"narrative\". " .
            "Return ONLY the JSON. No markdown backticks, no conversational text.";

        return $this->makeRequest($systemPrompt, $statsJson);
    }

    /**
     * Core request logic wrapper.
     */
    protected function makeRequest(string $systemPrompt, string $userContent): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('Anthropic API key is missing.');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(10)->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 1024,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userContent],
                ],
            ]);

            if ($response->failed()) {
                Log::error('Anthropic API request failed: ' . $response->status(), ['error' => $response->json()]);
                return null; // Return null to indicate failure (will trigger job retry or 'failed' status)
            }

            $data = $response->json();
            $content = $data['content'][0]['text'] ?? '';
            $usage = $data['usage'] ?? [];
            $tokensUsed = ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0);

            // Clean up content if it contains markdown JSON block
            $content = trim($content);
            if (str_starts_with($content, '```json')) {
                $content = substr($content, 7);
            }
            if (str_starts_with($content, '```')) {
                $content = substr($content, 3);
            }
            if (str_ends_with($content, '```')) {
                $content = substr($content, 0, -3);
            }
            $content = trim($content);

            $parsed = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Anthropic JSON decode failed: ' . json_last_error_msg());
                return null;
            }

            $parsed['api_tokens_used'] = $tokensUsed;

            return $parsed;

        } catch (\Exception $e) {
            Log::error('Anthropic API exception: ' . $e->getMessage());
            return null;
        }
    }
}
