<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * A local, keyword- and taxonomy-based sentiment, classification, and priority analyzer.
 * Works entirely offline without requiring external API access.
 */
class LocalSentimentService
{
    /**
     * Negative keywords with weights.
     */
    protected array $negativeWords = [
        // Urgent / Threat / Safety
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
     * @return array{
     *     sentiment_score: float,
     *     sentiment_label: string,
     *     priority_suggestion: string,
     *     priority_explanation: string,
     *     issue_category: string,
     *     issue_subcategory: string,
     *     classification_explanation: string,
     *     classification_confidence: float,
     *     summary: string,
     *     api_tokens_used: int
     * }
     */
    public function analyzeConcern(string $title, string $description): array
    {
        $text = strtolower($title . ' ' . $description);

        // 1. Calculate Sentiment Score
        $score = 0.0;
        $matchCount = 0;

        foreach ($this->negativeWords as $word => $weight) {
            $count = substr_count($text, $word);
            if ($count > 0) {
                $score += ($weight * $count);
                $matchCount += $count;
            }
        }

        foreach ($this->positiveWords as $word => $weight) {
            $count = substr_count($text, $word);
            if ($count > 0) {
                $score += ($weight * $count);
                $matchCount += $count;
            }
        }

        if ($matchCount > 0) {
            $score = $score / $matchCount;
        }
        $score = max(-1.0, min(1.0, round($score, 3)));

        // Determine emotional tone label
        $sentimentLabel = $this->getSentimentLabel($score, $text);

        // 2. Classify Issue Category & Subcategory
        $classification = $this->determineClassification($title, $description);

        // 3. Determine Content-Based Priority (independent of urgent wording or mere anger)
        $priorityAssessment = $this->determinePriority(
            $title,
            $description,
            $classification['issue_category'],
            $classification['issue_subcategory'],
            $score
        );

        // 4. Generate Professional Concise Summary (max 300 chars)
        $summary = $this->generateSummary(
            $title,
            $description,
            $classification['issue_category'],
            $classification['issue_subcategory'],
            $priorityAssessment['priority']
        );

        return [
            'sentiment_score'            => $score,
            'sentiment_label'            => $sentimentLabel,
            'priority_suggestion'        => $priorityAssessment['priority'],
            'priority_explanation'       => $priorityAssessment['explanation'],
            'issue_category'             => $classification['issue_category'],
            'issue_subcategory'          => $classification['issue_subcategory'],
            'classification_explanation' => $classification['explanation'],
            'classification_confidence'  => $classification['confidence'],
            'summary'                    => $summary,
            'api_tokens_used'            => 0,
        ];
    }

    /**
     * Determine emotional tone label.
     */
    protected function getSentimentLabel(float $score, string $text): string
    {
        // High severity safety/threat words can flag urgent emotional tone
        $severeThreatKeywords = [
            'suicide', 'kill', 'threat', 'harassment', 'violence', 'danger', 'unsafe'
        ];

        foreach ($severeThreatKeywords as $kw) {
            if (str_contains($text, $kw)) {
                return 'urgent';
            }
        }

        if ($score <= -0.40) {
            return 'negative';
        }
        if ($score >= 0.25) {
            return 'positive';
        }
        return 'neutral';
    }

    /**
     * Classify issue category and subcategory based on taxonomy keywords.
     */
    public function determineClassification(string $title, string $description): array
    {
        $taxonomy = config('classification.categories', []);
        $text = strtolower($title . ' ' . $description);

        $bestCategory = 'Other / Needs Review';
        $bestSubcategory = 'Uncategorized / Needs Review';
        $highestScore = 0;
        $matchedKeywords = [];

        foreach ($taxonomy as $category => $subcategories) {
            if ($category === 'Other / Needs Review') {
                continue;
            }

            foreach ($subcategories as $subcategory => $keywords) {
                $subScore = 0;
                $currentMatches = [];

                foreach ($keywords as $kw) {
                    $kwLower = strtolower($kw);
                    // Match whole word or exact substring
                    if (str_contains($text, $kwLower)) {
                        // Longer terms give higher weight
                        $weight = str_contains($kwLower, ' ') ? 3 : 1;
                        // Title matches give 2x weight
                        if (str_contains(strtolower($title), $kwLower)) {
                            $weight *= 2;
                        }
                        $subScore += $weight;
                        $currentMatches[] = $kw;
                    }
                }

                if ($subScore > $highestScore) {
                    $highestScore = $subScore;
                    $bestCategory = $category;
                    $bestSubcategory = $subcategory;
                    $matchedKeywords = $currentMatches;
                }
            }
        }

        if ($highestScore > 0) {
            $confidence = min(0.95, round(0.65 + ($highestScore * 0.05), 2));
            $matchedList = implode(', ', array_unique(array_slice($matchedKeywords, 0, 4)));
            $explanation = "Classified as {$bestCategory} > {$bestSubcategory} based on keywords: {$matchedList}.";
        } else {
            // Check for general inquiries
            $inquiryWords = ['how to', 'inquiry', 'question', 'ask', 'info', 'where can i', 'schedule of'];
            $isInquiry = false;
            foreach ($inquiryWords as $iw) {
                if (str_contains($text, $iw)) {
                    $isInquiry = true;
                    break;
                }
            }

            if ($isInquiry) {
                $bestCategory = 'Other / Needs Review';
                $bestSubcategory = 'General Inquiries';
                $confidence = 0.60;
                $explanation = "Classified as General Inquiries due to informational question patterns.";
            } else {
                $confidence = 0.40;
                $explanation = "Defaulted to Other / Needs Review due to general or ambiguous concern description.";
            }
        }

        return [
            'issue_category'    => $bestCategory,
            'issue_subcategory' => $bestSubcategory,
            'explanation'       => $explanation,
            'confidence'        => $confidence,
        ];
    }

    /**
     * Determine content-based priority without falling for superficial urgency adjectives.
     */
    public function determinePriority(
        string $title,
        string $description,
        string $category,
        string $subcategory,
        float $sentimentScore
    ): array {
        $text = strtolower($title . ' ' . $description);

        // 1. Critical Priority Assessment (Immediate hazard, safety risk, security breach, total outage)
        $safetyHazardPatterns = [
            'fire', 'electrical spark', 'sparking', 'exposed wire', 'gas leak', 'structural damage',
            'hazard', 'physical threat', 'harassment', 'violence', 'suicide', 'weapon', 'injury', 'collapse'
        ];
        foreach ($safetyHazardPatterns as $pattern) {
            if (str_contains($text, $pattern)) {
                return [
                    'priority'    => 'critical',
                    'explanation' => "Critical priority: Immediate safety hazard or physical risk detected ('{$pattern}').",
                ];
            }
        }

        $securityBreachPatterns = [
            'ransomware', 'malware spreading', 'virus spreading', 'data breach',
            'hacked database', 'confidential leak', 'credentials leaked', 'mass infection'
        ];
        foreach ($securityBreachPatterns as $pattern) {
            if (str_contains($text, $pattern)) {
                return [
                    'priority'    => 'critical',
                    'explanation' => "Critical priority: Active security outbreak or data breach detected ('{$pattern}').",
                ];
            }
        }

        $totalOutagePatterns = [
            'entire campus', 'campus-wide', 'whole university', 'total blackout', 'all buildings without power',
            'campus network blackout', 'all servers down'
        ];
        foreach ($totalOutagePatterns as $pattern) {
            if (str_contains($text, $pattern)) {
                return [
                    'priority'    => 'critical',
                    'explanation' => "Critical priority: Institution-wide service outage or blackout ('{$pattern}').",
                ];
            }
        }

        // 2. High Priority Assessment (Department/lab wide disruption, essential service down, exam blocker)
        $labDisruptionPatterns = [
            'entire lab', 'whole lab', 'all computers in lab', 'all pcs in lab', 'all workstations',
            'department-wide', 'multiple classrooms', 'cannot hold class', 'class cancelled', 'class suspended'
        ];
        foreach ($labDisruptionPatterns as $pattern) {
            if (str_contains($text, $pattern)) {
                return [
                    'priority'    => 'high',
                    'explanation' => "High priority: Department or laboratory-wide operational disruption ('{$pattern}').",
                ];
            }
        }

        $essentialServicePatterns = [
            'enrollment system down', 'enrollment portal offline', 'portal is down', 'portal offline',
            'lms is down', 'grading system down', 'cashier offline', 'cashier system down',
            'cannot take exam', 'ongoing exam', 'midterm exam', 'final exam', 'exam blocked'
        ];
        foreach ($essentialServicePatterns as $pattern) {
            if (str_contains($text, $pattern)) {
                return [
                    'priority'    => 'high',
                    'explanation' => "High priority: Disruption to essential institutional systems or active examinations ('{$pattern}').",
                ];
            }
        }

        // 3. Low Priority Assessment (General questions, minor info, routine requests)
        if ($category === 'Other / Needs Review' || $subcategory === 'General Inquiries') {
            return [
                'priority'    => 'low',
                'explanation' => "Low priority: Routine informational inquiry or general feedback with minimal operational impact.",
            ];
        }

        $minorInquiryPatterns = [
            'how to', 'inquiry', 'general question', 'where can i', 'lost and found', 'id renewal',
            'clearance procedure', 'schedule inquiry'
        ];
        foreach ($minorInquiryPatterns as $pattern) {
            if (str_contains($text, $pattern) && !str_contains($text, 'exam') && !str_contains($text, 'broken')) {
                return [
                    'priority'    => 'low',
                    'explanation' => "Low priority: Routine administrative question or request for information.",
                ];
            }
        }

        // 4. Medium Priority (Default for localized issues, single machine, classroom fixture)
        return [
            'priority'    => 'medium',
            'explanation' => "Medium priority: Localized issue affecting an individual workstation or facility fixture.",
        ];
    }

    /**
     * Generate a concise, professional summary up to 300 characters.
     */
    public function generateSummary(
        string $title,
        string $description,
        string $category,
        string $subcategory,
        string $priority
    ): string {
        $maxLen = (int) config('classification.max_summary_length', 300);

        // Detect location or room
        $location = '';
        if (preg_match('/\b(?:room|rm|lab|bldg|building|ccs|library|cashier|registrar)\s*([a-z0-9\-]+)?\b/i', $title . ' ' . $description, $m)) {
            $location = trim($m[0]);
        }

        // Detect equipment or subject
        $equipment = '';
        if (preg_match('/\b(?:pc\s*\d+|computer\s*\d+|projector|printer|monitor|wifi|network|portal|aircon|ac)\b/i', $title . ' ' . $description, $em)) {
            $equipment = trim($em[0]);
        }

        // Extract core problem from description or title
        // Clean off conversational intros and greetings
        $cleanedDesc = preg_replace('/^(?:hello|hi|greetings[^.!?]*|good day|good morning|good afternoon|dear [^.!?]*|i would like to report that|i am writing this to [^.!?]*|i am reporting that)\s*[,:\-.]?\s*/i', '', trim($description));
        
        $sentences = preg_split('/(?<=[.?!])\s+/', $cleanedDesc);
        $coreSentence = $sentences[0] ?? $cleanedDesc;
        // Skip purely personal introductions like "I am a third-year student enrolled in Section 3A"
        foreach ($sentences as $s) {
            $sTrim = trim($s);
            if (preg_match('/^(?:i am a|i am currently enrolled|my name is|as a student)\b/i', $sTrim)) {
                continue;
            }
            if (mb_strlen($sTrim) > 10) {
                $coreSentence = $sTrim;
                break;
            }
        }
        $coreProblem = rtrim($coreSentence, '.');

        // Build compact summary
        $prefix = '';
        if ($location && $equipment) {
            $prefix = ucfirst($location) . " ({$equipment}): ";
        } elseif ($location) {
            $prefix = ucfirst($location) . ": ";
        } elseif ($equipment) {
            $prefix = ucfirst($equipment) . ": ";
        }

        // Avoid repeating location if title already prominently includes it
        if ($location && str_contains(strtolower($title), strtolower($location))) {
            $prefix = '';
        }

        $summary = $prefix . $title . ' — ' . $coreProblem . '.';

        // If exceeds max length, truncate gracefully
        if (mb_strlen($summary) > $maxLen) {
            $truncated = mb_substr($summary, 0, $maxLen - 3);
            $lastSpace = mb_strrpos($truncated, ' ');
            if ($lastSpace !== false && $lastSpace > ($maxLen / 2)) {
                $truncated = mb_substr($truncated, 0, $lastSpace);
            }
            $summary = rtrim($truncated, '.,- ') . '...';
        }

        return $summary;
    }
}
