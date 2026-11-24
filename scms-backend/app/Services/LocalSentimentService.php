<?php

namespace App\Services;

use App\Support\SummaryTextHelper;
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
    public function analyzeConcern(string $title, string $description, ?string $workflowType = null): array
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
        $classification = $this->determineClassification($title, $description, $workflowType);

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
            'ai_source'                  => 'local_fallback',
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
    public function determineClassification(string $title, string $description, ?string $workflowType = null): array
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

            // Exclude mismatched academic / non-academic top-level categories based on workflow
            if ($workflowType === 'non_academic' && $category === 'Academic Concerns') {
                continue;
            }
            if ($workflowType === 'academic' && $category === 'Non-Academic Concerns') {
                continue;
            }

            foreach ($subcategories as $subcategory => $keywords) {
                $subScore = 0;
                $currentMatches = [];

                foreach ($keywords as $kw) {
                    $kwLower = strtolower($kw);
                    // Very short keywords (e.g. "or", "ac", "cr") must match as whole words,
                    // otherwise they match inside unrelated words such as "counselor".
                    $matched = strlen($kwLower) <= 3
                        ? (bool) preg_match('/\b' . preg_quote($kwLower, '/') . '\b/u', $text)
                        : str_contains($text, $kwLower);
                    if ($matched) {
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

                // Align scoring with student's explicitly selected workflow
                if ($workflowType === 'academic' && $category === 'Academic Concerns' && $subScore > 0) {
                    $subScore += 2;
                } elseif ($workflowType === 'non_academic' && $category === 'Non-Academic Concerns' && $subScore > 0) {
                    $subScore += 2;
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
            'hazard', 'physical threat', 'harassment', 'violence', 'suicide', 'weapon', 'injury', 'collapse',
            'punching', 'punched', 'punching my face', 'assault', 'physical assault', 'swollen', 'bleeding', 'beaten', 'attack', 'physical fight'
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
            'cannot take exam', 'ongoing exam', 'midterm exam', 'final exam', 'exam blocked',
            'auto-submitted', 'auto-submit', 'auto submit', 'quiz', 'examination', 'test disruption', 'unanswered questions'
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
            'explanation' => "Medium priority: Localized issue affecting an individual student, workstation, or campus facility.",
        ];
    }

    /**
     * Generate a concise, factual 2-3 sentence issue summary within the configured
     * maximum budget (default 800 characters, preferred target 200-450 characters
     * for medium/long concerns). Never slices mid-word, never appends "...",
     * and never invents unverified facts.
     */
    public function generateSummary(
        string $title,
        string $description,
        string $category,
        string $subcategory,
        string $priority
    ): string {
        $maxLen = (int) config('classification.max_summary_length', 800);
        $candidates = $this->extractSummaryCandidates($description);

        if (empty($candidates)) {
            $fallback = trim($description) !== '' ? trim($description) : trim($title);
            return SummaryTextHelper::condenseToSentenceBudget($fallback, $maxLen);
        }

        // Tier 1: Short, already clear concerns receive modest deterministic clause condensation without forced truncation.
        $joinedAll = implode(' ', array_column($candidates, 'text'));
        $sourceLen = mb_strlen($joinedAll);
        if ($sourceLen <= min(240, $maxLen) || (count($candidates) <= 2 && $sourceLen <= min(280, $maxLen))) {
            $condensedShort = SummaryTextHelper::condenseShortFirstPersonClauses($joinedAll);
            return SummaryTextHelper::condenseToSentenceBudget($condensedShort, $maxLen);
        }

        // Tier 2: Controlled fact-verified synthesis for structured administrative/academic workflows.
        $synthesized = $this->attemptStructuredSynthesis($description, $sourceLen, $maxLen);
        if ($synthesized !== null) {
            return $synthesized;
        }

        // Tier 3: Concise 2-3 sentence extractive fallback (never pads greedily to 800 chars).
        $titleKeywords = $this->extractTitleKeywords($title);
        $totalCandidates = count($candidates);

        foreach ($candidates as $idx => &$cand) {
            $compressed = SummaryTextHelper::compressRedundantPhrasing($cand['text']);
            if ($compressed !== '') {
                $cand['text'] = $compressed;
                $cand['len'] = mb_strlen($compressed);
            }
            $cand['scores'] = $this->scoreSummaryCandidate($cand['text'], $idx, $totalCandidates, $titleKeywords);
        }
        unset($cand);

        $selectedIndices = $this->selectSummarySentenceIndices($candidates, $maxLen, $sourceLen);

        sort($selectedIndices);
        $orderedSentences = array_map(fn (int $i): string => $candidates[$i]['text'], $selectedIndices);

        return SummaryTextHelper::condenseToSentenceBudget(implode(' ', $orderedSentences), $maxLen);
    }

    /**
     * Attempt controlled deterministic synthesis ONLY when all facts used in a sentence
     * template are directly verified in the source description. Returns null to fall back
     * safely whenever reliable fact extraction is incomplete or longer than extractive input.
     */
    private function attemptStructuredSynthesis(string $description, int $sourceLen, int $maxLen): ?string
    {
        $synthesizers = [
            fn (string $text): ?string => $this->synthesizeGradeDiscrepancy($text),
            fn (string $text): ?string => $this->synthesizePaymentReceiptDelay($text),
            fn (string $text): ?string => $this->synthesizeFacilityLabIssue($text),
            fn (string $text): ?string => $this->synthesizeClassroomEquipmentIssue($text),
            fn (string $text): ?string => $this->synthesizeGuidanceCrisis($text),
        ];

        foreach ($synthesizers as $synthesizer) {
            $raw = $synthesizer($description);
            if ($raw === null || trim($raw) === '') {
                continue;
            }

            $sentences = SummaryTextHelper::splitSentences($raw);
            if (empty($sentences) || SummaryTextHelper::hasDuplicateSentence($sentences)) {
                continue;
            }

            $bounded = SummaryTextHelper::condenseToSentenceBudget($raw, $maxLen);
            if ($bounded !== '' && mb_strlen($bounded) < $sourceLen) {
                return $bounded;
            }
        }

        return null;
    }

    private function synthesizeClassroomEquipmentIssue(string $text): ?string
    {
        if (!preg_match('/\b(projector|smart\s+tv|whiteboard|microphone|speaker\s+system)\b/iu', $text, $eqMatch)) {
            return null;
        }
        if (!preg_match('/\b(Room\s+\d+[A-Z]?|Lab(?:oratory)?\s+\d+)\b/iu', $text, $roomMatch)) {
            return null;
        }
        if (!preg_match('/\b(repeatedly\s+shuts\s+down|shuts\s+down|shutting\s+down|stopped\s+working|not\s+turning\s+on|flickering)\b/iu', $text, $failMatch)) {
            return null;
        }

        $equipment = strtolower(trim($eqMatch[1]));
        $room = trim((string) preg_replace('/\s+/u', ' ', $roomMatch[1]));
        $failure = strtolower(trim((string) preg_replace('/\s+/u', ' ', $failMatch[1])));

        $datePattern = '(?:(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2}(?:,\s*20\d{2})?)';

        $incidentDate = null;
        if (preg_match('/\bon\s+(' . $datePattern . ')/iu', $text, $dm)) {
            $incidentDate = SummaryTextHelper::normalizeConciseDate($dm[1]);
        }

        $hasPresentationInterruption = (bool) preg_match('/\binterrupt(?:ing|ed)\s+(?:the\s+|our\s+)?presentation\b/iu', $text);
        $studentCount = null;
        if (preg_match('/\b(\d+)\s+students\b/iu', $text, $sm)) {
            $studentCount = $sm[1] . ' students';
        }

        $s1 = "The student reports that the {$equipment} in {$room} {$failure}";
        if ($incidentDate !== null) {
            $s1 .= " on {$incidentDate}";
        }
        $tailParts = [];
        if ($hasPresentationInterruption) {
            $tailParts[] = 'interrupting a presentation';
        }
        if ($studentCount !== null) {
            $tailParts[] = "affecting around {$studentCount}";
        }
        if (!empty($tailParts)) {
            $s1 .= ', ' . implode(' and ', $tailParts);
        }
        $s1 .= '.';

        $hasInspect = (bool) preg_match('/\binspect(?:ed|ion)?\b/iu', $text);
        $hasRepair = (bool) preg_match('/\brepair(?:ed|s)?\b/iu', $text);
        if (!$hasInspect && !$hasRepair) {
            return null;
        }

        $reqAction = ($hasInspect && $hasRepair)
            ? 'an inspection and repair'
            : ($hasInspect ? 'an inspection' : 'repairs');

        $nextDate = null;
        if (preg_match('/\b(?:scheduled\s+presentation|next\s+class|examination)\s+on\s+(' . $datePattern . ')/iu', $text, $nm)) {
            $nextDate = SummaryTextHelper::normalizeConciseDate($nm[1]);
        } elseif (preg_match('/\bbefore\s+(' . $datePattern . ')/iu', $text, $bm)) {
            $nextDate = SummaryTextHelper::normalizeConciseDate($bm[1]);
        }

        $s2 = "They request {$reqAction}";
        if ($nextDate !== null) {
            $hasNextPresentation = (bool) preg_match('/\bscheduled\s+presentation\s+on\b/iu', $text);
            $s2 .= $hasNextPresentation
                ? " before the next scheduled presentation on {$nextDate}"
                : " before {$nextDate}";
        }
        $s2 .= '.';

        return "{$s1} {$s2}";
    }

    private function synthesizeGuidanceCrisis(string $text): ?string
    {
        if (!preg_match('/\b(?:family\s+(?:and\s+personal\s+)?crisis|personal\s+crisis|family\s+emergency|emotional\s+distress|mental\s+health\s+crisis)\b/iu', $text)) {
            return null;
        }
        if (!preg_match('/\b(?:guidance\s+center|counseling|counselor|consultation)\b/iu', $text)) {
            return null;
        }

        $crisisLabel = 'a personal crisis';
        if (preg_match('/\bfamily\s+and\s+personal\s+crisis\b/iu', $text)) {
            $crisisLabel = 'a family and personal crisis';
        } elseif (preg_match('/\bfamily\s+(?:crisis|emergency)\b/iu', $text)) {
            $crisisLabel = 'a family emergency';
        } elseif (preg_match('/\b(?:emotional\s+distress|mental\s+health\s+crisis)\b/iu', $text)) {
            $crisisLabel = 'emotional distress';
        }

        $impacts = [];
        if (preg_match('/\battendance\b/iu', $text)) {
            $impacts[] = 'attendance';
        }
        if (preg_match('/\b(?:exam\s+performance|academic\s+performance)\b/iu', $text)) {
            $impacts[] = 'academic performance';
        }

        $s1 = "The student reports {$crisisLabel}";
        if (!empty($impacts)) {
            $impactStr = count($impacts) === 2 ? "{$impacts[0]} and {$impacts[1]}" : $impacts[0];
            $s1 .= " that is affecting {$impactStr}";
        }
        $s1 .= '.';

        $isUrgent = (bool) preg_match('/\b(?:today|urgent\w*|immediately)\b/iu', $text);
        $hasEndorsement = (bool) preg_match('/\bendorsement\b/iu', $text);
        $hasGuidanceCenter = (bool) preg_match('/\bguidance\s+center\b/iu', $text);
        $hasAcademicRisk = (bool) preg_match('/\b(?:academic\s+standing|subject\s+retention)\b/iu', $text);
        $hasGradingCutoff = (bool) preg_match('/\b(?:grading\s+cutoff|grading\s+deadline)\b/iu', $text);

        $reqTarget = $hasEndorsement ? 'counseling assistance and an endorsement' : 'counseling assistance';
        $s2 = 'They ' . ($isUrgent ? 'urgently request ' : 'request ') . $reqTarget;
        if ($hasGuidanceCenter) {
            $s2 .= ' from the Guidance Center';
        }
        if ($hasAcademicRisk) {
            $s2 .= ' to address potential academic consequences';
        }
        if ($hasGradingCutoff) {
            $s2 .= ' before the grading deadline';
        }
        $s2 .= '.';

        return "{$s1} {$s2}";
    }

    private function synthesizeGradeDiscrepancy(string $text): ?string
    {
        if (!preg_match('/\b(?:inc\b|incomplete|incorrect\s+(?:final\s+)?grade|grade\s+(?:encoding\s+)?discrepancy|wrong\s+(?:final\s+)?grade)\b/iu', $text)) {
            return null;
        }

        $datePattern = '(?:(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2}(?:,\s*20\d{2})?)';

        $course = null;
        if (preg_match('/\b([A-Z]{2,5}-\d{3}(?:\s+[A-Z][A-Za-z]+){0,3})(?=\s+(?:during|under|final|grade|class|first|second|semester|for|on|in)\b|[,.?!]|$)/u', $text, $m)) {
            $course = trim($m[1]);
        }

        $expectedGrade = null;
        $earnedDate = null;
        if (preg_match('/(?:final\s+grade\s+of|score\s+(?:totaled|of)|earned|received)\s+(\d{1,3}(?:\.\d+)?%)(?:[^.\n]{0,40}?\bon\s+(' . $datePattern . '))?/iu', $text, $m)) {
            $expectedGrade = $m[1];
            if (!empty($m[2])) {
                $earnedDate = SummaryTextHelper::normalizeConciseDate($m[2]);
            }
        } elseif (preg_match('/\b(\d{1,3}(?:\.\d+)?%)\b/u', $text, $m)) {
            $expectedGrade = $m[1];
        }
        if ($expectedGrade !== null && $earnedDate === null && preg_match('/\bon\s+(' . $datePattern . ')(?:(?!\bon\s+' . $datePattern . ')[^.\n]){0,140}?' . preg_quote($expectedGrade, '/') . '/iu', $text, $em)) {
            $earnedDate = SummaryTextHelper::normalizeConciseDate($em[1]);
        }

        $hasInc = (bool) preg_match('/\bINC\b/u', $text);
        $portalLabel = $hasInc ? 'incorrect INC grade' : 'incorrect grade';
        $postedDate = null;
        if (preg_match('/(?:on\s+(' . $datePattern . ')[^.\n]{0,45}?(?:portal|grades?\s+were\s+released|displayed\s+INC|showed\s+INC|posted)|(?:displayed|showed|posted)[^.\n]{0,30}?\bINC\b[^.\n]{0,30}?\bon\s+(' . $datePattern . '))/iu', $text, $m)) {
            $rawPosted = !empty($m[1]) ? $m[1] : ($m[2] ?? '');
            if ($rawPosted !== '') {
                $postedDate = SummaryTextHelper::normalizeConciseDate($rawPosted);
            }
        }

        $ackClause = null;
        if (preg_match('/(?:instructor|professor)\b[^.\n]{0,80}?\b(?:acknowledged|confirmed|admitted)\b[^.\n]{0,80}?\b(?:skipped|omitted|encoding)\b/iu', $text)) {
            $ackDate = null;
            if (preg_match('/\bon\s+(' . $datePattern . ')(?:(?!\bon\s+' . $datePattern . ')[^.\n]){0,60}?\b(?:acknowledged|confirmed|admitted)\b/iu', $text, $dm)) {
                $ackDate = SummaryTextHelper::normalizeConciseDate($dm[1]);
            }
            $isHedged = (bool) preg_match('/\b(?:may\s+have|might\s+have|possibly|possible|could\s+have|suspected)\b/iu', $text);
            $omissionPhrase = $isHedged ? 'a possible encoding omission' : 'an encoding omission';
            $ackClause = ($ackDate ? "On {$ackDate}, the" : 'The') . " instructor acknowledged {$omissionPhrase}";
        }

        $scholarshipImpact = null;
        if (preg_match('/\bscholarship\b/iu', $text)) {
            $deadlineDate = null;
            if (preg_match('/(?:deadline|forfeited|cleared)[^.\n]{0,35}?\b(?:is|by|on)\s+(' . $datePattern . ')/iu', $text, $sm)) {
                $deadlineDate = SummaryTextHelper::normalizeConciseDate($sm[1]);
            }
            $renewalPhrase = preg_match('/\brenewal\b/iu', $text) ? 'scholarship renewal' : 'scholarship eligibility';
            $scholarshipImpact = $deadlineDate
                ? "may affect {$renewalPhrase} before the {$deadlineDate} deadline"
                : "may affect {$renewalPhrase}";
        }

        $hasFormalReview = (bool) preg_match('/\b(?:formal\s+review|review(?:ed)?\s+(?:of\s+)?(?:the\s+)?grade\s+records?|verify\s+my\s+class\s+record)\b/iu', $text);
        $hasChangeOfGrade = (bool) preg_match('/\bChange\s+of\s+Grade\b/iu', $text);
        $hasTempCert = (bool) preg_match('/\btemporary\s+certification\b/iu', $text);
        $requestDeadline = null;
        if (preg_match('/(?:corrected|reviewed|change\s+of\s+grade|documents)[^.\n]{0,35}?\b(?:before|by)\s+(' . $datePattern . ')/iu', $text, $rm)) {
            $requestDeadline = SummaryTextHelper::normalizeConciseDate($rm[1]);
        }

        $factSignals = ($course !== null ? 1 : 0)
            + ($expectedGrade !== null ? 1 : 0)
            + ($hasInc ? 1 : 0)
            + ($ackClause !== null ? 1 : 0)
            + ($scholarshipImpact !== null ? 1 : 0)
            + ($hasChangeOfGrade ? 1 : 0);
        if ($factSignals < 3) {
            return null;
        }

        // Sentence 1: Core grade discrepancy
        $s1 = "The student reports an {$portalLabel}";
        if ($postedDate !== null) {
            $s1 .= " posted on {$postedDate}";
        }
        if ($course !== null) {
            $s1 .= " for {$course}";
        }
        if ($expectedGrade !== null) {
            $s1 .= " despite earning {$expectedGrade}";
            if ($earnedDate !== null) {
                $s1 .= " on {$earnedDate}";
            }
        }
        $s1 .= '.';

        $sentences = [$s1];

        // Sentence 2: Instructor follow-up and/or scholarship deadline impact
        if ($ackClause !== null && $scholarshipImpact !== null) {
            $sentences[] = "{$ackClause}, which {$scholarshipImpact}.";
        } elseif ($ackClause !== null) {
            $sentences[] = "{$ackClause}.";
        } elseif ($scholarshipImpact !== null) {
            $sentences[] = 'The unresolved grade ' . $scholarshipImpact . '.';
        }

        // Sentence 3: Requested actions
        $actions = [];
        if ($hasFormalReview) {
            $actions[] = 'a formal review';
        }
        if ($hasChangeOfGrade) {
            $actions[] = 'a Change of Grade process';
        }
        if ($hasTempCert) {
            $actions[] = 'temporary certification';
        }
        if (empty($actions) && preg_match('/\b(?:corrected|correction|update|clear\w*)\b/iu', $text)) {
            $actions[] = 'grade correction';
        }

        if (!empty($actions)) {
            $actionText = count($actions) === 1
                ? $actions[0]
                : (count($actions) === 2
                    ? "{$actions[0]} and {$actions[1]}"
                    : "{$actions[0]}, {$actions[1]}, and {$actions[2]}");
            $s3 = "The student requests {$actionText}";
            if ($requestDeadline !== null) {
                $s3 .= " before {$requestDeadline}";
            }
            $s3 .= '.';
            $sentences[] = $s3;
        }

        return implode(' ', $sentences);
    }

    private function synthesizePaymentReceiptDelay(string $text): ?string
    {
        if (!preg_match('/\b(?:paid|payment|tuition)\b/iu', $text)) {
            return null;
        }
        if (!preg_match('/\b(?:official\s+receipt|receipt|ledger\s+still\s+shows\s+unpaid|shows\s+unpaid)\b/iu', $text)) {
            return null;
        }

        $datePattern = '(?:(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2}(?:,\s*20\d{2})?)';

        $amount = null;
        if (preg_match('/\b(\d{1,3}(?:,\d{3})*\s*(?:pesos|php))\b/iu', $text, $m)) {
            $amount = preg_replace('/\s+/u', ' ', trim($m[1]));
        }

        $location = null;
        if (preg_match('/\b(Cashier(?:\s+Window\s+\d+)?)\b/iu', $text, $m)) {
            $location = trim($m[1]);
        }

        $paidDate = null;
        if (preg_match('/\bon\s+(' . $datePattern . ')/iu', $text, $m)) {
            $paidDate = SummaryTextHelper::normalizeConciseDate($m[1]);
        }

        if ($amount === null && $location === null) {
            return null;
        }

        $hasLedgerUnpaid = (bool) preg_match('/\bledger\s+still\s+shows\s+unpaid\b/iu', $text);
        $hasNoReceipt = (bool) preg_match('/\b(?:not\s+received\s+(?:my\s+|the\s+)?official\s+receipt|delayed\s+official\s+receipt|still\s+have\s+not\s+received)\b/iu', $text);
        if (!$hasLedgerUnpaid && !$hasNoReceipt) {
            return null;
        }

        $s1 = 'The student paid ' . ($amount ?? 'tuition');
        if ($location !== null) {
            $s1 .= " at {$location}";
        }
        if ($paidDate !== null) {
            $s1 .= " on {$paidDate}";
        }
        if ($hasNoReceipt && $hasLedgerUnpaid) {
            $s1 .= ', yet the ledger remains unpaid and no official receipt has been issued.';
        } elseif ($hasLedgerUnpaid) {
            $s1 .= ', yet the ledger still shows unpaid.';
        } else {
            $s1 .= ' but has not received the official receipt.';
        }

        $sentences = [$s1];

        $examDeadlineDay = null;
        if (preg_match('/\b(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday)\b/iu', $text, $m)) {
            $examDeadlineDay = ucfirst(strtolower($m[1]));
        }
        $hasExamPermit = (bool) preg_match('/\b(?:examination\s+permit|exam\s+permit)\b/iu', $text);
        $isExplicitlyBlocking = (bool) preg_match('/\bblocking\b/iu', $text);

        $impactClause = null;
        if ($hasExamPermit) {
            $permitLabel = $examDeadlineDay ? "{$examDeadlineDay} examination permit" : 'examination permit';
            if ($isExplicitlyBlocking) {
                $impactClause = "This delay is blocking the {$permitLabel}";
            } else {
                $impactClause = "Without it, the student may miss the {$permitLabel} deadline";
            }
        }

        $hasVerify = (bool) preg_match('/\bverif(?:y|ying|ication)\b/iu', $text);
        $hasIssueReceipt = (bool) preg_match('/\b(?:issu(?:e|ing)\s+(?:my\s+|the\s+)?official\s+receipt|official\s+receipt)\b/iu', $text);

        $requestParts = [];
        if ($hasVerify) {
            $requestParts[] = 'verifying the payment';
        }
        if ($hasIssueReceipt) {
            $requestParts[] = 'issuing the official receipt';
        }

        $requestClause = null;
        if (!empty($requestParts)) {
            $joinedReq = count($requestParts) === 2
                ? "{$requestParts[0]} and {$requestParts[1]}"
                : $requestParts[0];
            $requestClause = "requests assistance {$joinedReq}";
            if ($examDeadlineDay !== null && preg_match('/\bbefore\s+' . preg_quote($examDeadlineDay, '/') . '\b/iu', $text)) {
                $requestClause .= " before {$examDeadlineDay}";
            }
        }

        if ($impactClause !== null && $requestClause !== null) {
            $sentences[] = "{$impactClause} and {$requestClause}.";
        } elseif ($impactClause !== null) {
            $sentences[] = "{$impactClause}.";
        } elseif ($requestClause !== null) {
            $sentences[] = 'The student ' . $requestClause . '.';
        }

        return implode(' ', $sentences);
    }

    private function synthesizeFacilityLabIssue(string $text): ?string
    {
        if (mb_strlen($text) <= 320) {
            return null;
        }
        if (!preg_match('/\b(?:air[-\s]?conditioning|aircon|cooling)\b/iu', $text) || !preg_match('/\b(?:leak(?:ing|age)?|water)\b/iu', $text)) {
            return null;
        }

        $room = null;
        if (preg_match('/\b((?:Computer\s+Laboratory\s+|Science\s+Lab\s+|Lab\s+)?Room\s+\d+[A-Z]?)\b/iu', $text, $m)) {
            $room = trim((string) preg_replace('/\s+/u', ' ', $m[1]));
        } elseif (preg_match('/\b(Lab(?:oratory)?\s+\d+)\b/iu', $text, $m)) {
            $room = trim($m[1]);
        }
        if ($room === null) {
            return null;
        }

        $datePattern = '(?:(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2}(?:,\s*20\d{2})?)';

        $startDate = null;
        if (preg_match('/\bsince\s+(?:Monday,\s+|Tuesday,\s+|Wednesday,\s+|Thursday,\s+|Friday,\s+)?(' . $datePattern . ')/iu', $text, $m)) {
            $startDate = SummaryTextHelper::normalizeConciseDate($m[1]);
        }

        $hasCoolingFailure = (bool) preg_match('/\b(?:cooling\s+system\s+is\s+not\s+functioning|cooling\s+stopped|not\s+functioning|hot)\b/iu', $text);
        $issuePhrase = $hasCoolingFailure ? 'an air-conditioning water leak and cooling failure' : 'an air-conditioning water leak';
        $verb = $hasCoolingFailure ? 'have' : 'has';

        $wsRange = null;
        if (preg_match('/\b(PC-\d+\s+(?:through|to)\s+PC-\d+)\b/iu', $text, $m)) {
            $wsRange = (string) preg_replace_callback(
                '/\b(pc)-(\d+)\b|\b(through|to)\b/iu',
                fn (array $part): string => !empty($part[1]) ? ('PC-' . $part[2]) : strtolower($part[0]),
                trim((string) preg_replace('/\s+/u', ' ', $m[1]))
            );
        }
        $spreadDate = null;
        if ($wsRange !== null && preg_match('/\bon\s+(' . $datePattern . ')[^.\n]{0,65}?' . preg_quote($wsRange, '/') . '/iu', $text, $m)) {
            $spreadDate = SummaryTextHelper::normalizeConciseDate($m[1]);
        }

        $s1 = $startDate ? "Since {$startDate}, {$issuePhrase} in {$room}" : "An {$issuePhrase} in {$room}";
        if ($wsRange !== null) {
            $s1 .= " {$verb} spread water near {$wsRange}";
            if ($spreadDate !== null) {
                $s1 .= " as of {$spreadDate}";
            }
        }
        $s1 .= '.';

        $sentences = [$s1];

        // Sentence 2: Verified hazards, unplugged workstations, student headcount, exam impact
        $hazardDate = null;
        if (preg_match('/\b(?:by|on)\s+(' . $datePattern . ')[^.\n]{0,75}?\b(?:slip\w*|electrical|unplugged)\b/iu', $text, $m)) {
            $hazardDate = SummaryTextHelper::normalizeConciseDate($m[1]);
        }

        $hazardItems = [];
        if (preg_match('/\bslippery\b/iu', $text)) {
            $hazardItems[] = 'slippery floors';
        } elseif (preg_match('/\bslipped\b/iu', $text)) {
            $hazardItems[] = 'slip incidents';
        }
        if (preg_match('/\b(?:possible|concerns\s+about(?:\s+possible)?)\s+electrical\s+hazard/iu', $text)) {
            $hazardItems[] = 'possible electrical hazards';
        } elseif (preg_match('/\belectrical\s+(?:short-circuit\s+)?hazard/iu', $text)) {
            $hazardItems[] = 'electrical hazards';
        }

        $unpluggedAction = null;
        $unpluggedSentence = null;
        if (preg_match('/\b(four|\d+)\s+workstations\b[^.\n]{0,35}?\bunplugged\b/iu', $text, $m)) {
            $countWord = strtolower($m[1]);
            $unpluggedAction = "unplugging {$countWord} workstations";
            $unpluggedSentence = "{$countWord} workstations were unplugged";
        }

        $studentCount = null;
        if (preg_match('/\b(\d+)(?:\s+students|\s*-student)\b/iu', $text, $m)) {
            $studentCount = $m[1] . ' students';
        }

        $examDate = null;
        if (preg_match('/(?:practical\s+)?exam(?:ination)?\s+(?:is\s+)?on\s+(' . $datePattern . ')/iu', $text, $m)) {
            $examDate = SummaryTextHelper::normalizeConciseDate($m[1]);
        }

        $s2Parts = [];
        if (!empty($hazardItems)) {
            $hazardText = count($hazardItems) === 2
                ? "{$hazardItems[0]} and {$hazardItems[1]}"
                : $hazardItems[0];
            $prefix = $hazardDate ? "By {$hazardDate}, " : '';
            if ($unpluggedAction !== null) {
                $s2Parts[] = "{$prefix}{$hazardText} led to {$unpluggedAction}";
            } else {
                $s2Parts[] = "{$prefix}{$hazardText} were reported";
            }
        } elseif ($unpluggedSentence !== null) {
            $prefix = $hazardDate ? "By {$hazardDate}, " : '';
            $s2Parts[] = "{$prefix}{$unpluggedSentence}";
        }

        $impactTail = null;
        if ($studentCount !== null && $examDate !== null) {
            $impactTail = "affecting around {$studentCount} ahead of a {$examDate} examination";
        } elseif ($studentCount !== null) {
            $impactTail = "affecting {$studentCount}";
        } elseif ($examDate !== null) {
            $impactTail = "ahead of a {$examDate} examination";
        }

        if (!empty($s2Parts) && $impactTail !== null) {
            $sentences[] = $s2Parts[0] . ', ' . $impactTail . '.';
        } elseif (!empty($s2Parts)) {
            $sentences[] = ucfirst($s2Parts[0]) . '.';
        } elseif ($impactTail !== null) {
            $sentences[] = 'The issue is ' . $impactTail . '.';
        }

        // Sentence 3: Verified requested actions
        $reqActions = [];
        if (preg_match('/\binspection\s+of\s+the\s+air-conditioning|\bair-conditioning\s+inspection\b/iu', $text)) {
            $reqActions[] = 'an air-conditioning inspection';
        } elseif (preg_match('/\binspect\b/iu', $text) && !preg_match('/\bdrainage\s+line\b/iu', $text)) {
            $reqActions[] = 'inspection';
        }
        if (preg_match('/\bdrainage\s+line\b/iu', $text)) {
            $reqActions[] = 'drainage repair';
        }
        if (preg_match('/\belectrical\s+safety\s+assessment\b/iu', $text)) {
            $reqActions[] = 'electrical safety assessment';
        } elseif (preg_match('/\bcertify\s+electrical\s+safety\b/iu', $text)) {
            $reqActions[] = 'electrical safety certification';
        }
        if (preg_match('/\brepairs\b/iu', $text)) {
            $reqActions[] = 'repairs';
        } elseif (preg_match('/\brestore\s+cooling\b/iu', $text)) {
            $reqActions[] = 'cooling restoration';
        }

        if (!empty($reqActions)) {
            if (count($reqActions) === 1) {
                $reqStr = $reqActions[0];
            } elseif (count($reqActions) === 2) {
                $reqStr = "{$reqActions[0]} and {$reqActions[1]}";
            } else {
                $last = array_pop($reqActions);
                $reqStr = implode(', ', $reqActions) . ", and {$last}";
            }

            $reqDeadline = null;
            if (preg_match('/\bbefore\s+(?:our|the)\s+(?:practical\s+examination\s+on\s+)?(' . $datePattern . ')/iu', $text, $m)) {
                $reqDeadline = SummaryTextHelper::normalizeConciseDate($m[1]);
            } elseif (preg_match('/\bbefore\s+the\s+(' . $datePattern . ')\s+examination/iu', $text, $m)) {
                $reqDeadline = SummaryTextHelper::normalizeConciseDate($m[1]);
            }

            $s3 = "The student requests {$reqStr}";
            if ($reqDeadline !== null) {
                $s3 .= " before {$reqDeadline}";
            }
            $s3 .= '.';
            $sentences[] = $s3;
        }

        return count($sentences) >= 2 ? implode(' ', $sentences) : null;
    }

    /**
     * Parse the description paragraph-by-paragraph into deduplicated, complete sentences,
     * removing pure salutations, sign-offs, and grammar-safe polite lead-ins.
     *
     * @return list<array{paragraph: int, index: int, text: string, len: int}>
     */
    private function extractSummaryCandidates(string $description): array
    {
        $paragraphs = preg_split('/\R+/u', trim($description)) ?: [];
        $candidates = [];
        $sentenceIndex = 0;

        foreach ($paragraphs as $pIdx => $paragraph) {
            $line = trim($paragraph);
            if ($line === '') {
                continue;
            }

            if ($this->isPureSalutationOrSignoffLine($line)) {
                continue;
            }

            foreach (SummaryTextHelper::splitSentences($line) as $rawSentence) {
                $cleaned = $this->cleanSentenceBoilerplate($rawSentence);
                if ($cleaned === '' || $this->isPureSalutationOrSignoffLine($cleaned) || $this->isPureIntroductoryFiller($cleaned)) {
                    continue;
                }

                $formatted = SummaryTextHelper::ensureTerminalPunctuation($cleaned);
                if ($formatted === '' || mb_strlen($formatted) < 10) {
                    continue;
                }

                $isDuplicate = false;
                foreach ($candidates as $existing) {
                    if (SummaryTextHelper::tokenSimilarity($formatted, $existing['text']) >= 0.80) {
                        $isDuplicate = true;
                        break;
                    }
                }

                if ($isDuplicate) {
                    continue;
                }

                $candidates[] = [
                    'paragraph' => $pIdx,
                    'index'     => $sentenceIndex++,
                    'text'      => $formatted,
                    'len'       => mb_strlen($formatted),
                ];
            }
        }

        return $candidates;
    }

    /**
     * Check whether a line or sentence is purely a salutation/sign-off with no factual action.
     */
    private function isPureSalutationOrSignoffLine(string $text): bool
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return true;
        }

        // Never drop a sentence containing substantive concern verbs/nouns
        if (preg_match('/\b(?:broken|leak\w*|stopped|failed|error|crash\w*|discrepancy|unpaid|delayed|missing|lost|inc\b|incomplete|forfeit\w*|repair|verify|issue|endorse|correct\w*|inspect\w*|refund|restore|replace\w*|hazard|blocked|blocking|slipped|unplugged|panick\w*|punch\w*|assault\w*|harass\w*)\b/iu', $trimmed)) {
            return false;
        }

        if (preg_match('/^(?:(?:good\s+(?:morning|afternoon|day|evening)|greetings|hello|hi|dear|to\s+whom\s+it\s+may\s+concern)\b[^.?!]{0,120})[,:\-.!]?$/iu', $trimmed)) {
            return true;
        }

        if (preg_match('/^(?:thank\s+you|thanks(?:\s+(?:so\s+much|in\s+advance))?|sincerely|respectfully(?:\s+yours)?|best\s+regards|kind\s+regards|warm\s+regards|regards|hoping\s+for\s+your)[^.?!]{0,100}[,:\-.!]?$/iu', $trimmed)) {
            return true;
        }

        return false;
    }

    /**
     * Strip leading greeting prefixes and grammar-safe "... that" lead-in wrappers cleanly.
     */
    private function cleanSentenceBoilerplate(string $sentence): string
    {
        $clean = trim($sentence);

        // Strip opening greeting before a comma (e.g., "Good morning dear administration, ...")
        $clean = (string) preg_replace(
            '/^(?:good\s+(?:morning|afternoon|day|evening)|greetings|hello|hi)\b[^,]{0,75},\s*/iu',
            '',
            $clean
        );

        // Strip opening "With due respect," / "Respectfully,"
        $clean = (string) preg_replace('/^(?:with\s+due\s+respect|respectfully)\s*,\s*/iu', '', $clean);

        // Strip ONLY boilerplate lead-ins ending explicitly in "that " so clauses stay grammatically intact
        $clean = (string) preg_replace(
            '/^(?:i\s+am\s+writing\s+(?:this\s+)?to\s+(?:formally\s+)?(?:inform|report|notify|bring\s+to\s+your\s+attention|state|let\s+you\s+know)\s+(?:you\s+)?that|i\s+would\s+like\s+to\s+(?:inform|report|notify)\s+(?:you\s+)?that|i\s+am\s+reporting\s+that|please\s+be\s+informed\s+that)\s+/iu',
            '',
            $clean
        );

        $clean = trim($clean);
        if ($clean === '') {
            return '';
        }

        $firstChar = mb_substr($clean, 0, 1);
        return mb_strtoupper($firstChar) . mb_substr($clean, 1);
    }

    /**
     * Drop self-introductory student profile filler that carries no issue, event, impact, or request.
     */
    private function isPureIntroductoryFiller(string $sentence): bool
    {
        if (!preg_match('/^i\s+am\s+(?:a|an)\s+[\w\-\s]{1,50}\bstudent\b/iu', $sentence)) {
            return false;
        }

        return !preg_match('/\b(?:broken|leak\w*|stopped|failed|error|crash\w*|discrepancy|unpaid|delayed|missing|lost|inc\b|incomplete|forfeit\w*|repair|verify|issue|endorse|correct\w*|inspect\w*|refund|restore|replace\w*|hazard|blocked|blocking|unable|cannot|forfeited)\b/iu', $sentence);
    }

    /**
     * Extract meaningful content words from the concern title.
     *
     * @return list<string>
     */
    private function extractTitleKeywords(string $title): array
    {
        $stop = ['with', 'from', 'that', 'this', 'your', 'about', 'after', 'before', 'during', 'affecting', 'concern', 'issue', 'problem', 'request', 'report', 'repeated'];
        preg_match_all('/[a-z0-9]{4,}/u', strtolower($title), $matches);

        return array_values(array_diff(array_unique($matches[0] ?? []), $stop));
    }

    /**
     * Score one sentence across Issue, Events/Dates/Locations, Student Impact, and Requested Assistance.
     *
     * @param  list<string>  $titleKeywords
     * @return array{issue: float, events: float, impact: float, request: float, total: float, is_attachment: bool, has_concrete_date: bool}
     */
    private function scoreSummaryCandidate(string $sentence, int $index, int $total, array $titleKeywords): array
    {
        $lower = strtolower($sentence);
        $isEarly = $index <= 1 ? 1.2 : 0.0;
        $isLate = $index >= max(1, (int) floor($total * 0.6)) ? 1.5 : 0.0;

        // 1. Main issue score (title overlap + factual problem signals)
        $titleOverlap = 0.0;
        foreach ($titleKeywords as $kw) {
            if (str_contains($lower, $kw)) {
                $titleOverlap += 1.5;
            }
        }
        $issueHits = $this->countDistinctPatternMatches(
            $sentence,
            '/\b(?:broken|leaking|leak|stopped|failed|error|crash\w*|shutdown|burning|smoke|spark\w*|flickering|offline|down|delayed|missing|lost|discrepancy|unpaid|incorrect|incomplete|inc\b|skipped|unanswered|auto-submitted|punched|assault\w*|harass\w*|bully\w*|threat\w*|unusable|hazard\w*|unplugged|slipped|slippery|electrical|drainage|cooling|clerical|blocking|forfeit\w*|unresponsive)\b/iu'
        );
        $issueScore = min(6.0, $titleOverlap) + ($issueHits * 2.0) + $isEarly;

        // 2. Events, dates, locations, equipment, numeric evidence
        $dateHits = $this->countDistinctPatternMatches(
            $sentence,
            '/\b(?:january|february|march|april|may|june|july|august|september|october|november|december|monday|tuesday|wednesday|thursday|friday|saturday|sunday|yesterday|today|\d{1,2}:\d{2}|20\d{2})\b/iu'
        );
        $locationEquipHits = $this->countDistinctPatternMatches(
            $sentence,
            '/\b(?:room\s*\d+[a-z]?|laboratory|lab\s*\d+|pc[-\s]*\d+|workstation\w*|window\s*\d+|cashier|registrar|scholarship\s+office|student\s+portal|faculty\s+room|canteen|hallway|[a-z]{2,4}-\d{3})\b/iu'
        );
        $metricHits = $this->countDistinctPatternMatches(
            $sentence,
            '/\b\d+(?:\.\d+)?%|\b\d+(?:,\d{3})*\s*(?:pesos|php|students|learners|workstations|computers|questions|days|hours|minutes|semesters?)\b/iu'
        );
        $timelineHits = $this->countDistinctPatternMatches(
            $sentence,
            '/\b(?:visited|emailed|acknowledged|notified|released|appeared|slipped|unplugged|paid|submitted|completed|filed|verbally|skipped|posted|received|contacted|noticed|raised|corrected|reviewed|advised)\b/iu'
        );
        $eventsScore = (min(3, $dateHits) * 1.5) + (min(3, $locationEquipHits) * 1.5) + (min(2, $metricHits) * 2.2) + (min(3, $timelineHits) * 1.2);

        // 3. Impact on the student or affected people / safety
        $impactHits = $this->countDistinctPatternMatches(
            $sentence,
            '/\b(?:affect\w*|impact\w*|blocking|blocked|prevent\w*|cannot|unable|forced|forfeit\w*|consequence\w*|hazard\w*|safety|risk|injur\w*|swollen|bleeding|slipped|slippery|electrical|precaution|short-circuit|heat|humidity|concentrate|eligibility|worried|interrupt\w*|disrupt\w*|hamper\w*|unusable|scholarship|renewal|enrollment|pre-register\w*|permit|exam\w*|deadline|progress|finances|share\s+terminals|\d+\s+(?:students|learners))\b/iu'
        );
        $impactScore = $impactHits * 2.2;

        // 4. Requested assistance / action
        $isAttachmentOnly = (bool) preg_match('/^(?:i\s+am\s+attaching|attached\s+(?:here|are|is))\b/iu', $sentence)
            && !preg_match('/\b(?:request\w*|please|repair|verify|issue|endorse|inspect\w*|certify|restore|expedite)\b/iu', $sentence);

        $requestHits = $this->countDistinctPatternMatches(
            $sentence,
            '/\b(?:respectfully\s+request|request\w*|urge|please|asking|need|require\w*|assist\w*|verify|verification|issue|repair|inspect\w*|certify|restore|endorse|expedite|change\s+of\s+grade|correction|replace\w*|retriev\w*|refund|clear\w*|approve|investigat\w*|temporary\s+certification|maintenance)\b/iu'
        );
        $requestScore = $isAttachmentOnly
            ? 0.0
            : ($requestHits * 2.4) + (preg_match('/\b(?:before|by)\s+(?:next\s+)?(?:monday|tuesday|wednesday|thursday|friday|january|february|march|april|may|june|july|august|september|october|november|december)/iu', $sentence) ? 1.5 : 0.0) + ($requestHits > 0 ? $isLate : 0.0);

        return [
            'issue'             => round($issueScore, 2),
            'events'            => round($eventsScore, 2),
            'impact'            => round($impactScore, 2),
            'request'           => round($requestScore, 2),
            'total'             => round($issueScore + $eventsScore + $impactScore + $requestScore - ($isAttachmentOnly ? 5.0 : 0.0), 2),
            'is_attachment'     => $isAttachmentOnly,
            'has_concrete_date' => $dateHits > 0,
        ];
    }

    private function countDistinctPatternMatches(string $text, string $pattern): int
    {
        if (!preg_match_all($pattern, $text, $matches)) {
            return 0;
        }

        $normalized = array_map(fn (string $m): string => strtolower(trim($m)), $matches[0]);

        return count(array_unique($normalized));
    }

    /**
     * Select 2-3 concise, non-redundant complete sentences across main issue, request,
     * student impact, and high-evidence uncovered events without greedily padding to 800 chars.
     *
     * @param  list<array{paragraph: int, index: int, text: string, len: int, scores: array<string, mixed>}>  $candidates
     * @return list<int>
     */
    private function selectSummarySentenceIndices(array $candidates, int $maxLen, int $sourceLen): array
    {
        $selectedBySlot = [];

        $pickBest = function (array $excluded, callable $scorer) use ($candidates): ?int {
            $bestIdx = null;
            $bestScore = 0.0;

            foreach ($candidates as $idx => $cand) {
                if (in_array($idx, $excluded, true)) {
                    continue;
                }

                $redundant = false;
                foreach ($excluded as $exIdx) {
                    if (SummaryTextHelper::tokenSimilarity($cand['text'], $candidates[$exIdx]['text']) >= 0.55) {
                        $redundant = true;
                        break;
                    }
                }
                if ($redundant) {
                    continue;
                }

                $score = (float) $scorer($cand, $idx);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestIdx = $idx;
                }
            }

            return $bestIdx;
        };

        $usedParagraphs = static function (array $indices) use ($candidates): array {
            return array_values(array_unique(array_map(fn (int $i): int => $candidates[$i]['paragraph'], $indices)));
        };

        $calcLen = static function (array $indices) use ($candidates): int {
            if (empty($indices)) {
                return 0;
            }
            $sum = array_sum(array_map(fn (int $i): int => $candidates[$i]['len'], $indices));
            return $sum + max(0, count($indices) - 1);
        };

        $totalCandidates = count($candidates);
        $softTargetLen = min($maxLen, max(240, min(450, (int) round($sourceLen * 0.55))));

        // Slot 1: Main Issue anchor (favoring early / first-half problem statements with concrete location/equipment)
        $issueIdx = $pickBest([], function ($c, int $idx) use ($totalCandidates) {
            if ($c['scores']['is_attachment']) {
                return 0.0;
            }
            $firstHalfBonus = $idx <= (int) floor($totalCandidates / 2) ? 2.5 : 0.0;
            return $c['scores']['issue'] + (0.6 * $c['scores']['events']) + $firstHalfBonus;
        });
        if ($issueIdx !== null) {
            $selectedBySlot['issue'] = $issueIdx;
        }

        // Slot 2: Requested assistance / resolution
        $requestIdx = $pickBest(array_values($selectedBySlot), function ($c) {
            if ($c['scores']['request'] < 2.4) {
                return 0.0;
            }
            return $c['scores']['request'] + (0.3 * $c['scores']['events']);
        });
        if ($requestIdx !== null) {
            $selectedBySlot['request'] = $requestIdx;
        }

        // Slot 3: Impact on student / safety / consequences
        $impactIdx = $pickBest(array_values($selectedBySlot), function ($c) {
            if ($c['scores']['impact'] < 2.0) {
                return 0.0;
            }
            return $c['scores']['impact']
                + (0.9 * $c['scores']['events'])
                + ($c['scores']['has_concrete_date'] ? 2.0 : 0.0)
                - ($c['scores']['is_attachment'] ? 6.0 : 0.0);
        });
        if ($impactIdx !== null) {
            $selectedBySlot['impact'] = $impactIdx;
        }

        // Slot 4 (Optional Event): Only when < 3 sentences selected OR an uncovered high-evidence event fits softTargetLen
        $coveredParagraphs = $usedParagraphs(array_values($selectedBySlot));
        $eventIdx = $pickBest(array_values($selectedBySlot), function ($c) use ($coveredParagraphs, $selectedBySlot) {
            if ($c['scores']['is_attachment']) {
                return 0.0;
            }
            $isUncovered = !in_array($c['paragraph'], $coveredParagraphs, true);
            if (count($selectedBySlot) >= 3 && (!$isUncovered || $c['scores']['events'] < 3.0)) {
                return 0.0;
            }
            if (($c['scores']['events'] + $c['scores']['issue']) <= 0.5) {
                return 0.0;
            }
            return $c['scores']['events'] + (0.5 * $c['scores']['issue']) + ($isUncovered ? 4.0 : 0.0);
        });
        if ($eventIdx !== null) {
            $withEvent = array_merge(array_values($selectedBySlot), [$eventIdx]);
            if (count($selectedBySlot) < 3 || $calcLen($withEvent) <= $softTargetLen) {
                $selectedBySlot['event'] = $eventIdx;
            }
        }

        // Ensure multi-sentence concerns get at least 2 distinct sentences when a non-redundant second exists within maxLen
        if (count($selectedBySlot) === 1 && $totalCandidates >= 2) {
            $secondIdx = $pickBest(array_values($selectedBySlot), function ($c) use ($calcLen, $selectedBySlot, $maxLen) {
                if ($c['scores']['is_attachment']) {
                    return 0.0;
                }
                if ($calcLen(array_merge(array_values($selectedBySlot), [$c['index']])) > $maxLen) {
                    return 0.0;
                }
                return max(0.1, $c['scores']['total']);
            });
            if ($secondIdx !== null) {
                $selectedBySlot['secondary'] = $secondIdx;
            }
        }

        // If selected slots exceed configured $maxLen, swap long optional slots or drop lowest priority complete sentences
        $dropOrder = ['secondary', 'event', 'impact', 'request', 'issue'];
        while ($calcLen(array_values($selectedBySlot)) > $maxLen && count($selectedBySlot) > 1) {
            $swapped = false;
            foreach ($dropOrder as $slot) {
                if (!isset($selectedBySlot[$slot])) {
                    continue;
                }
                $currentSlotIdx = $selectedBySlot[$slot];
                $currentLen = $candidates[$currentSlotIdx]['len'];
                $otherIndices = array_values(array_diff(array_values($selectedBySlot), [$currentSlotIdx]));

                $shorterAlt = $pickBest(array_merge($otherIndices, [$currentSlotIdx]), function ($c) use ($slot, $currentLen, $calcLen, $otherIndices, $maxLen) {
                    if ($c['len'] >= $currentLen) {
                        return 0.0;
                    }
                    if ($calcLen(array_merge($otherIndices, [$c['index']])) > $maxLen) {
                        return 0.0;
                    }
                    if ($slot === 'event') {
                        return $c['scores']['events'];
                    }
                    if ($slot === 'secondary') {
                        return $c['scores']['total'];
                    }
                    return $c['scores'][$slot] ?? 0.0;
                });

                if ($shorterAlt !== null) {
                    $selectedBySlot[$slot] = $shorterAlt;
                    $swapped = true;
                    break;
                }
            }

            if ($swapped) {
                continue;
            }

            foreach ($dropOrder as $slot) {
                if (isset($selectedBySlot[$slot])) {
                    unset($selectedBySlot[$slot]);
                    break;
                }
            }
        }

        $selected = array_values($selectedBySlot);
        if (empty($selected)) {
            return [0];
        }

        return $selected;
    }

    /**
     * Fallback keyword-based operational department recommender.
     *
     * @param  string  $title
     * @param  string  $description
     * @param  \Illuminate\Support\Collection|array  $departments
     * @return array{
     *     recommended_department_id: ?int,
     *     recommended_department_name: ?string,
     *     confidence: float,
     *     explanation: string,
     *     requires_manual_selection: bool,
     *     source: string
     * }
     */
    public function recommendOperationalDepartment(string $title, string $description, $departments): array
    {
        $text = strtolower($title . ' ' . $description);

        $departmentKeywords = [
            'cashier' => [
                'keywords' => ['cashier', 'tuition', 'payment', 'pay', 'receipt', 'bank transfer', 'overpayment', 'assessment', 'counter', 'downpayment', 'installment', 'fee balance', 'partial payment', 'billing'],
                'explanation' => 'Concern involves tuition payment, official receipts, or cashier window transactions.',
            ],
            'accounting' => [
                'keywords' => ['accounting', 'ledger', 'promissory note', 'statement of account', 'soa', 'clearance fee', 'financial hold', 'account balance', 'unsettled account'],
                'explanation' => 'Concern involves student financial ledger records, promissory notes, or clearance balances.',
            ],
            'registrar' => [
                'keywords' => ['registrar', 'transcript', 'tor', 'diploma', 'form 137', 'certificate of registration', 'cor', 'honorable dismissal', 'cross-enrollment', 'credentials', 'release of records', 'shifting', 'graduation clearance', 'cav'],
                'explanation' => 'Concern involves academic credentials, registration certificates, official transcripts, or enrollment records.',
            ],
            'edp' => [
                'keywords' => ['edp', 'portal', 'student portal', 'wifi', 'internet', 'canvas', 'lms', 'password reset', 'locked account', 'login error', 'system error', 'server', 'network', 'connection', 'website', 'it infrastructure'],
                'explanation' => 'Concern involves student portal access, campus Wi-Fi/network connectivity, or electronic data processing systems.',
            ],
            'clinic' => [
                'keywords' => ['clinic', 'medical', 'doctor', 'nurse', 'medicine', 'sick', 'medical certificate', 'med cert', 'first aid', 'injury', 'physical exam', 'fever', 'dental', 'health', 'swollen', 'bleeding', 'punched', 'physical trauma', 'facial injury'],
                'explanation' => 'Concern involves student health services, clinic checkups, medical certificates, or campus first-aid.',
            ],
            'guidance' => [
                'keywords' => ['guidance', 'counselor', 'counseling', 'mental health', 'emotional', 'stress', 'anxiety', 'depression', 'psychological', 'bullying support', 'well-being', 'personal crisis', 'harassment', 'harass', 'bully', 'bullying', 'bullied', 'cyberbully', 'cyberbullying', 'peer conflict', 'peer harassment', 'threatens me', 'threatened', 'punching', 'punched', 'assault', 'physical assault', 'beaten', 'physical fight'],
                'explanation' => 'Concern involves student mental health, counseling consultations, psychological support, peer conflict, or student-to-student harassment.',
            ],
            'scholarship' => [
                'keywords' => ['scholarship', 'scholar', 'ched', 'unifast', 'financial aid', 'grant', 'discount', 'voucher', 'stipend', 'subsidy'],
                'explanation' => 'Concern involves institutional scholarships, government educational subsidies, or tuition discounts.',
            ],
            'security' => [
                'keywords' => ['csu', 'security', 'guard', 'lost and found', 'lost id', 'school id', 'missing id', 'missing', 'id card', 'lost school id', 'stolen', 'theft', 'gate', 'campus entrance', 'parking', 'safety', 'vehicle', 'confiscated', 'trespassing', 'assault', 'physical assault', 'violence', 'physical fight'],
                'explanation' => 'Concern involves campus security, gate entry, lost-and-found items, parking, or civil security.',
            ],
            'sao' => [
                'keywords' => ['sao', 'student affairs', 'student council', 'organization', 'student club', 'activity permit', 'student handbook', 'disciplinary', 'uniform policy', 'id validation', 'student governance', 'school id', 'missing id', 'missing school id', 'lost id', 'id replacement', 'missing item'],
                'explanation' => 'Concern involves student organization permits, campus activities, student handbook policies, or student affairs governance.',
            ],
            'sto' => [
                'keywords' => ['sto', 'seagoing', 'shipboard', 'onboard training', 'cadetship', 'vessel', 'apprenticeship', 'shipping company', 'embarkation', 'sea training'],
                'explanation' => 'Concern involves seagoing shipboard training requirements, cadet apprenticeships, or maritime vessel assignments.',
            ],
            'superintendent' => [
                'keywords' => ['maritime superintendent', 'superintendent', 'maritime compliance', 'maritime training simulator', 'deck department', 'engine cadet'],
                'explanation' => 'Concern involves maritime program compliance, superintendent oversight, or training simulators.',
            ],
            'director' => [
                'keywords' => ['campus director', 'director', 'executive', 'institutional appeal', 'administrative escalation', "director's office"],
                'explanation' => 'Concern involves university executive escalation, institutional appeals, or director office inquiries.',
            ],
            'hrd' => [
                'keywords' => ['hrd', 'human resource', 'staff conduct', 'faculty complaint', 'employee behavior', 'personnel', 'instructor complaint'],
                'explanation' => 'Concern involves university personnel conduct, administrative staff grievances, or human resources.',
            ],
        ];

        $deptCollection = collect($departments);
        $bestMatch = null;
        $highestScore = 0;
        $matchedExplanation = '';

        foreach ($departmentKeywords as $profileKey => $data) {
            // Find corresponding department in actual DB records
            $deptModel = $deptCollection->first(function ($d) use ($profileKey) {
                $name = strtolower($d->name);
                switch ($profileKey) {
                    case 'cashier': return str_contains($name, 'cashier');
                    case 'accounting': return str_contains($name, 'accounting');
                    case 'registrar': return str_contains($name, 'registrar');
                    case 'edp': return str_contains($name, 'edp') || str_contains($name, 'data processing');
                    case 'clinic': return str_contains($name, 'clinic') || str_contains($name, 'medical');
                    case 'guidance': return str_contains($name, 'guidance');
                    case 'scholarship': return str_contains($name, 'scholarship');
                    case 'security': return str_contains($name, 'csu') || str_contains($name, 'security');
                    case 'sao': return str_contains($name, 'sao') || str_contains($name, 'student affairs');
                    case 'sto': return str_contains($name, 'sto') || str_contains($name, 'seagoing');
                    case 'superintendent': return str_contains($name, 'superintendent');
                    case 'director': return str_contains($name, 'director');
                    case 'hrd': return str_contains($name, 'hrd') || str_contains($name, 'human resource');
                    default: return false;
                }
            });

            if (!$deptModel) {
                continue;
            }

            $score = 0;
            foreach ($data['keywords'] as $kw) {
                if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $text)) {
                    $score += (strlen($kw) > 6 ? 2 : 1);
                }
            }

            if ($score > $highestScore) {
                $highestScore = $score;
                $bestMatch = $deptModel;
                $matchedExplanation = $data['explanation'];
            }
        }

        if ($bestMatch && $highestScore >= 1) {
            $confidence = $highestScore >= 3 ? 0.85 : ($highestScore === 2 ? 0.70 : 0.55);
            return [
                'recommended_department_id' => $bestMatch->id,
                'recommended_department_name' => $bestMatch->name,
                'confidence' => $confidence,
                'explanation' => $matchedExplanation,
                'requires_manual_selection' => $confidence < 0.60,
                'source' => 'fallback',
            ];
        }

        return [
            'recommended_department_id' => null,
            'recommended_department_name' => null,
            'confidence' => 0.0,
            'explanation' => 'No specific operational department could be confidently matched. Please select the appropriate department manually.',
            'requires_manual_selection' => true,
            'source' => 'fallback',
        ];
    }
}
