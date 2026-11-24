<?php

namespace App\Support;

class SummaryTextHelper
{
    private const PLACEHOLDER = "\u{E000}";

    private const ABBREVIATIONS = [
        'mr.', 'mrs.', 'ms.', 'dr.', 'prof.', 'engr.', 'atty.',
        'sr.', 'jr.', 'st.', 'no.', 'rm.', 'bldg.', 'dept.',
        'e.g.', 'i.e.', 'vs.', 'etc.', 'a.m.', 'p.m.',
    ];

    /**
     * Split a block of text into clean sentences while protecting common
     * abbreviations and decimal numbers from false sentence breaks.
     *
     * @return list<string>
     */
    public static function splitSentences(string $text): array
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $text));
        if ($normalized === '') {
            return [];
        }

        // Strip trailing artificial ellipsis before splitting
        $normalized = (string) preg_replace('/(?:\.\.\.|…)+$/u', '', $normalized);

        $protected = self::protectAbbreviationsAndDecimals($normalized);
        $rawParts = preg_split('/(?<=[.?!])\s+/u', $protected) ?: [];

        $sentences = [];
        foreach ($rawParts as $part) {
            $restored = str_replace(self::PLACEHOLDER, '.', trim($part));
            if ($restored !== '') {
                $sentences[] = $restored;
            }
        }

        return $sentences;
    }

    /**
     * Ensure a single sentence ends with terminal punctuation and never with an artificial ellipsis.
     */
    public static function ensureTerminalPunctuation(string $sentence): string
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', $sentence));
        $clean = (string) preg_replace('/(?:\.\.\.|…)+$/u', '', $clean);
        $clean = rtrim($clean, " \t\n\r\0\x0B,;:-");

        if ($clean === '') {
            return '';
        }

        if (!preg_match('/[.?!]$/u', $clean)) {
            $clean .= '.';
        }

        return $clean;
    }

    /**
     * Condense text strictly on complete sentence boundaries within the character budget.
     * Never cuts mid-word and never appends "...".
     */
    public static function condenseToSentenceBudget(string $text, int $maxLen): string
    {
        $maxLen = max(80, $maxLen);
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $text));
        if ($normalized === '') {
            return '';
        }

        $sentences = [];
        foreach (self::splitSentences($normalized) as $sentence) {
            $clean = self::ensureTerminalPunctuation($sentence);
            if ($clean !== '') {
                $sentences[] = $clean;
            }
        }

        if (empty($sentences)) {
            $fallback = self::ensureTerminalPunctuation($normalized);
            if ($fallback === '' || mb_strlen($fallback) <= $maxLen) {
                return $fallback;
            }

            return self::condenseSingleLongSentence($fallback, $maxLen);
        }

        $kept = [];
        $currentLength = 0;

        foreach ($sentences as $sentence) {
            $additionLength = $currentLength === 0
                ? mb_strlen($sentence)
                : 1 + mb_strlen($sentence);

            if ($currentLength + $additionLength <= $maxLen) {
                $kept[] = $sentence;
                $currentLength += $additionLength;
            } else {
                break;
            }
        }

        if (!empty($kept)) {
            return implode(' ', $kept);
        }

        // Rare edge case: even the very first sentence exceeds $maxLen
        return self::condenseSingleLongSentence($sentences[0], $maxLen);
    }

    /**
     * Compute Jaccard token similarity (0.0 - 1.0) between two sentences
     * using content tokens >= 3 chars to avoid selecting duplicate sentences.
     */
    public static function tokenSimilarity(string $a, string $b): float
    {
        $tokensA = self::contentTokens($a);
        $tokensB = self::contentTokens($b);

        if (empty($tokensA) || empty($tokensB)) {
            return 0.0;
        }

        $intersection = count(array_intersect($tokensA, $tokensB));
        $union = count(array_unique(array_merge($tokensA, $tokensB)));

        return $union > 0 ? $intersection / $union : 0.0;
    }

    /**
     * Cleanly condense a single unsplit sentence onto the nearest clause or word boundary
     * within budget, never cutting a word and never adding "...".
     */
    private static function condenseSingleLongSentence(string $sentence, int $maxLen): string
    {
        $budget = max(20, $maxLen - 1);
        $slice = mb_substr($sentence, 0, $budget);

        // Prefer a natural clause delimiter in the second half of the budget
        $bestClausePos = null;
        foreach ([';', ':', ','] as $delimiter) {
            $pos = mb_strrpos($slice, $delimiter);
            if ($pos !== false && $pos >= (int) floor($budget * 0.55)) {
                if ($bestClausePos === null || $pos > $bestClausePos) {
                    $bestClausePos = $pos;
                }
            }
        }

        if ($bestClausePos !== null) {
            $condensed = mb_substr($slice, 0, $bestClausePos);
        } else {
            $lastSpace = mb_strrpos($slice, ' ');
            $condensed = $lastSpace !== false && $lastSpace > 10
                ? mb_substr($slice, 0, $lastSpace)
                : $slice;
        }

        $condensed = (string) preg_replace(
            '/(?:\s+(?:and|or|but|to|for|with|in|on|at|of|the|a|an|that|which))?[,;:\s\-]*$/iu',
            '',
            $condensed
        );

        return self::ensureTerminalPunctuation($condensed);
    }

    /**
     * Protect decimal numbers and known abbreviations from false period splits.
     */
    private static function protectAbbreviationsAndDecimals(string $text): string
    {
        // Protect decimal numbers like 91.4 or 1.4
        $text = (string) preg_replace('/(?<=\d)\.(?=\d)/u', self::PLACEHOLDER, $text);

        // Protect known abbreviations followed by whitespace + lowercase or digit or uppercase continuation
        foreach (self::ABBREVIATIONS as $abbr) {
            $stem = substr($abbr, 0, -1);
            $pattern = '/\b(' . preg_quote($stem, '/') . ')\.(?=\s)/iu';
            $text = (string) preg_replace($pattern, '$1' . self::PLACEHOLDER, $text);
        }

        return $text;
    }

    /**
     * Tighten verbose boilerplate connectors inside an already extracted sentence
     * while preserving factual details, numbers, dates, and hedging verbatim.
     */
    public static function compressRedundantPhrasing(string $sentence): string
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', $sentence));
        if ($clean === '') {
            return '';
        }

        $clean = (string) preg_replace(
            '/^(?:however|furthermore|moreover|in\s+addition|additionally|also|meanwhile|consequently|therefore)\s*,\s*/iu',
            '',
            $clean
        );

        $replacements = [
            '/\bdue\s+to\s+the\s+fact\s+that\b/iu' => 'because',
            '/\bin\s+order\s+to\b/iu' => 'to',
            '/\bat\s+approximately\b/iu' => 'around',
            '/\bfor\s+the\s+purpose\s+of\b/iu' => 'for',
            '/\bwith\s+regard(?:s)?\s+to\b/iu' => 'regarding',
            '/\bis\s+currently\b/iu' => 'is',
            '/\bare\s+currently\b/iu' => 'are',
        ];
        $clean = (string) preg_replace(array_keys($replacements), array_values($replacements), $clean);
        $clean = trim((string) preg_replace('/\s+/u', ' ', $clean));

        if ($clean === '') {
            return '';
        }

        $firstChar = mb_substr($clean, 0, 1);
        $clean = mb_strtoupper($firstChar) . mb_substr($clean, 1);

        return self::ensureTerminalPunctuation($clean);
    }

    /**
     * Normalize full date strings (e.g., "November 28, 2026" -> "November 28") for concise summaries.
     */
    public static function normalizeConciseDate(string $rawDate): string
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', $rawDate));
        $clean = (string) preg_replace('/(?:,\s*|\s+)20\d{2}\b/u', '', $clean);
        $clean = trim($clean, " \t\n\r\0\x0B,.");

        if ($clean === '') {
            return '';
        }

        return ucfirst($clean);
    }

    /**
     * Determine whether any sentence pair in a list is near-duplicate (>= 0.78 token similarity).
     *
     * @param  list<string>  $sentences
     */
    public static function hasDuplicateSentence(array $sentences): bool
    {
        $count = count($sentences);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if (self::tokenSimilarity($sentences[$i], $sentences[$j]) >= 0.78) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Detect whether a candidate AI summary is a verbatim or near-verbatim copy
     * of the original student description so copied descriptions are never
     * falsely attributed to Gemini AI.
     */
    public static function isVerbatimOrNearCopy(string $candidateSummary, string $originalDescription): bool
    {
        $normSummary = strtolower(trim((string) preg_replace('/\s+/u', ' ', $candidateSummary), " \t\n\r\0\x0B.?!"));
        $normOriginal = strtolower(trim((string) preg_replace('/\s+/u', ' ', $originalDescription), " \t\n\r\0\x0B.?!"));

        if ($normSummary === '' || $normOriginal === '') {
            return false;
        }

        if ($normSummary === $normOriginal) {
            return true;
        }

        $origLen = mb_strlen($normOriginal);
        $sumLen = mb_strlen($normSummary);

        if ($origLen >= 100 && str_contains($normOriginal, $normSummary) && $sumLen >= (int) round($origLen * 0.82)) {
            return true;
        }

        return false;
    }

    /**
     * Apply modest, non-fabricating third-person and clause tightening to short descriptions
     * while preserving every factual noun, location, adjective, and action verb.
     */
    public static function condenseShortFirstPersonClauses(string $text): string
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', $text));
        if ($clean === '' || mb_strlen($clean) < 85) {
            return $clean;
        }

        $patterns = [
            '/\bThe\s+classroom\s+is\s+very\s+hot,\s+making\s+it\s+difficult\s+for\s+students\s+to\s+concentrate\./iu'
                => 'The hot classroom makes it difficult for students to concentrate.',
            '/\bWe\s+are\s+requesting\s+that\b/iu'
                => 'Students request that',
            '/\bI\s+am\s+requesting\s+that\b/iu'
                => 'The student requests that',
            '/\bI\s+am\s+requesting\s+assistance\s+(?:in|with|to)\b/iu'
                => 'The student requests assistance',
            '/\bWe\s+would\s+like\s+to\s+request\b/iu'
                => 'Students request',
            '/\bI\s+would\s+like\s+to\s+request\b/iu'
                => 'The student requests',
        ];

        $condensed = (string) preg_replace(array_keys($patterns), array_values($patterns), $clean);
        $condensed = trim((string) preg_replace('/\s+/u', ' ', $condensed));

        return self::ensureTerminalPunctuation($condensed);
    }

    /**
     * @return list<string>
     */
    private static function contentTokens(string $text): array
    {
        preg_match_all('/[a-z0-9]{3,}/u', strtolower($text), $matches);

        return array_values(array_unique($matches[0] ?? []));
    }
}
