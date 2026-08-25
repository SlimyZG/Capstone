<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * A local, keyword-based sentiment analyzer.
 * No external API required — works entirely offline using keyword scoring.
 */
class LocalSentimentService
{
    /**
     * Negative keywords with weights.
     */
    protected array $negativeWords = [
        // Urgent / Critical
        'urgent' => -0.9, 'emergency' => -0.95, 'immediately' => -0.8, 'asap' => -0.8,
        'danger' => -0.95, 'dangerous' => -0.95, 'threat' => -0.9, 'threatening' => -0.9,
        'harassment' => -0.95, 'harassed' => -0.9, 'bullying' => -0.9, 'bullied' => -0.9,
        'unsafe' => -0.85, 'safety' => -0.5, 'violence' => -0.95, 'violent' => -0.95,
        'suicide' => -1.0, 'kill' => -1.0, 'die' => -0.9, 'abuse' => -0.95,

        // Strong Negative
        'terrible' => -0.8, 'horrible' => -0.8, 'awful' => -0.8, 'worst' => -0.8,
        'unacceptable' => -0.75, 'disgusting' => -0.8, 'outrageous' => -0.75,
        'furious' => -0.8, 'angry' => -0.6, 'frustrated' => -0.6, 'frustrating' => -0.6,
        'disappointed' => -0.5, 'disappointing' => -0.5, 'unfair' => -0.6,
        'incompetent' => -0.7, 'useless' => -0.7, 'pathetic' => -0.8,

        // Moderate Negative
        'broken' => -0.5, 'not working' => -0.5, 'malfunction' => -0.5,
        'problem' => -0.3, 'issue' => -0.2, 'concern' => -0.15,
        'failed' => -0.5, 'failure' => -0.5, 'error' => -0.4,
        'wrong' => -0.3, 'bad' => -0.4, 'poor' => -0.4,
        'slow' => -0.3, 'delay' => -0.3, 'delayed' => -0.35,
        'missing' => -0.4, 'lost' => -0.4, 'unavailable' => -0.35,
        'complaint' => -0.4, 'complain' => -0.4,
        'difficult' => -0.3, 'confusing' => -0.35, 'confused' => -0.3,
        'rude' => -0.6, 'disrespectful' => -0.6, 'ignored' => -0.5,
        'neglected' => -0.5, 'unprofessional' => -0.6,
    ];

    /**
     * Positive keywords with weights.
     */
    protected array $positiveWords = [
        'thank' => 0.4, 'thanks' => 0.4, 'grateful' => 0.5, 'appreciate' => 0.5,
        'good' => 0.3, 'great' => 0.5, 'excellent' => 0.6, 'amazing' => 0.6,
        'wonderful' => 0.6, 'helpful' => 0.5, 'satisfied' => 0.4, 'pleased' => 0.4,
        'happy' => 0.5, 'love' => 0.4, 'best' => 0.5, 'perfect' => 0.6,
        'resolved' => 0.3, 'fixed' => 0.3, 'improved' => 0.4, 'improvement' => 0.3,
        'well' => 0.2, 'nice' => 0.3, 'kind' => 0.3, 'supportive' => 0.4,
        'please' => 0.1,
    ];

    /**
     * Analyze a concern's title and description.
     *
     * @return array{sentiment_score: float, sentiment_label: string, priority_suggestion: string, summary: string, api_tokens_used: int}
     */
    public function analyzeConcern(string $title, string $description): array
    {
        $text = strtolower($title . ' ' . $description);

        $score = 0.0;
        $matchCount = 0;

        // Score negative words
        foreach ($this->negativeWords as $word => $weight) {
            $count = substr_count($text, $word);
            if ($count > 0) {
                $score += ($weight * $count);
                $matchCount += $count;
            }
        }

        // Score positive words
        foreach ($this->positiveWords as $word => $weight) {
            $count = substr_count($text, $word);
            if ($count > 0) {
                $score += ($weight * $count);
                $matchCount += $count;
            }
        }

        // Normalize score to -1.0 to 1.0 range
        if ($matchCount > 0) {
            $score = $score / $matchCount;
        }
        $score = max(-1.0, min(1.0, round($score, 3)));

        // Determine sentiment label
        $label = $this->getLabel($score, $text);

        // Determine priority
        $priority = $this->getPriority($score, $label, $text);

        // Generate a basic summary
        $summary = $this->generateSummary($title, $description, $label, $priority);

        return [
            'sentiment_score' => $score,
            'sentiment_label' => $label,
            'priority_suggestion' => $priority,
            'summary' => $summary,
            'api_tokens_used' => 0,
        ];
    }

    protected function getLabel(float $score, string $text): string
    {
        // Check for urgent keywords first
        $urgentKeywords = ['urgent', 'emergency', 'immediately', 'asap', 'danger', 'dangerous',
            'threat', 'harassment', 'suicide', 'violence', 'unsafe', 'kill'];

        foreach ($urgentKeywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return 'urgent';
            }
        }

        if ($score <= -0.5) return 'negative';
        if ($score >= 0.3) return 'positive';
        return 'neutral';
    }

    protected function getPriority(float $score, string $label, string $text): string
    {
        if ($label === 'urgent') return 'critical';
        if ($score <= -0.6) return 'high';
        if ($score <= -0.3) return 'medium';
        return 'low';
    }

    protected function generateSummary(string $title, string $description, string $label, string $priority): string
    {
        $desc = strlen($description) > 120 ? substr($description, 0, 120) . '...' : $description;

        $sentimentText = match ($label) {
            'urgent' => 'This concern has been flagged as urgent and requires immediate attention.',
            'negative' => 'The student has expressed dissatisfaction or frustration regarding this issue.',
            'positive' => 'The student has a generally positive tone in this submission.',
            default => 'The student has submitted a neutral inquiry or request.',
        };

        $priorityText = match ($priority) {
            'critical' => 'This has been classified as critical priority.',
            'high' => 'This has been classified as high priority.',
            'medium' => 'This has been classified as medium priority.',
            default => 'This has been classified as low priority.',
        };

        return "{$sentimentText} {$priorityText} Summary: {$desc}";
    }
}
