<?php

namespace App\Http\Controllers;

use App\Models\Concern;
use App\Models\User;
use App\Services\AnthropicService;
use App\Services\GeminiSentimentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Get sentiment statistics for the dashboard.
     */
    public function getSentiment(Request $request)
    {
        $user = $request->user();
        $query = Concern::query();

        // Scope data for deans and department staff
        if ($user->role === 'dean') {
            if ($user->academic_unit_id) {
                $query->where('academic_unit_id', $user->academic_unit_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->role === 'dept_staff') {
            $query->where(function ($q) use ($user) {
                $hasCondition = false;
                if ($user->dept_id) {
                    $q->where('dept_id', $user->dept_id);
                    $hasCondition = true;
                }
                if ($user->academic_unit_id) {
                    $acadClause = function ($sub) use ($user) {
                        $sub->where('academic_unit_id', $user->academic_unit_id)
                            ->where(function ($w) {
                                $w->where('workflow_type', 'academic')
                                  ->orWhere(function ($w2) {
                                      $w2->where('category', 'academic_units')
                                         ->whereNull('dept_id');
                                  });
                            });
                    };

                    if ($hasCondition) {
                        $q->orWhere($acadClause);
                    } else {
                        $q->where($acadClause);
                    }
                    $hasCondition = true;
                }
                if (!$hasCondition) {
                    $q->whereRaw('1 = 0');
                }
            });
        }

        $roleScopedBase = clone $query;

        // Available categories and subcategories within the user's role scope
        $availableCategories = (clone $roleScopedBase)
            ->whereNotNull('issue_category')
            ->select('issue_category')
            ->distinct()
            ->orderBy('issue_category')
            ->pluck('issue_category')
            ->values();

        $availableSubcategories = (clone $roleScopedBase)
            ->whereNotNull('issue_subcategory')
            ->select('issue_category', 'issue_subcategory')
            ->distinct()
            ->orderBy('issue_category')
            ->orderBy('issue_subcategory')
            ->get()
            ->map(fn ($r) => [
                'category'    => $r->issue_category,
                'subcategory' => $r->issue_subcategory,
            ])
            ->values();

        // Apply optional dashboard-wide filters
        if ($request->filled('date_from')) {
            $query->whereDate('submitted_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('submitted_at', '<=', $request->date_to);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('issue_category')) {
            $query->where('issue_category', $request->issue_category);
        }
        if ($request->filled('issue_subcategory')) {
            $query->where('issue_subcategory', $request->issue_subcategory);
        }
        if ($request->filled('sentiment') && $request->sentiment !== 'all') {
            $query->where('sentiment_label', $request->sentiment);
        }

        $distribution = (clone $query)
            ->whereNotNull('sentiment_label')
            ->select('sentiment_label', DB::raw('count(*) as count'))
            ->groupBy('sentiment_label')
            ->pluck('count', 'sentiment_label');

        $flaggedBaseQuery = (clone $query)
            ->where(function ($q) {
                $q->where('sentiment_score', '<', -0.7)
                  ->orWhere('sentiment_label', 'urgent')
                  ->orWhere('priority', 'critical');
            });

        $totalScopeFlaggedCount = (clone $flaggedBaseQuery)->count();

        // Option A Privacy Rule: SAO sees institution-wide aggregates, but individual concern records only when assigned/routed to SAO
        $flaggedIndividualQuery = clone $flaggedBaseQuery;
        if ($user->role === 'sao') {
            $saoDept = \App\Models\Department::where('name', 'like', '%SAO%')
                ->orWhere('name', 'like', '%Student Affairs%')
                ->first();
            $saoDeptId = $saoDept ? $saoDept->id : null;

            if ($saoDeptId) {
                $flaggedIndividualQuery->where(function ($q) use ($saoDeptId) {
                    $q->where('dept_id', $saoDeptId)
                      ->orWhereHas('routingHistory', function ($rh) {
                          $rh->where('notes', 'like', '%SAO%')
                             ->orWhere('notes', 'like', '%Student Affairs%');
                      });
                });
            } else {
                $flaggedIndividualQuery->whereRaw('1 = 0');
            }
        }

        $flaggedConcerns = $flaggedIndividualQuery
            ->with(['student:id,name,student_id_number', 'academicUnit', 'department'])
            ->orderBy('submitted_at', 'desc')
            ->take(50)
            ->get()
            ->map(function ($concern) {
                if ($concern->is_anonymous && $concern->student) {
                    $concern->student->name = 'Anonymous';
                    $concern->student->student_id_number = '********';
                }
                return $concern;
            });
            
        // Trend over time (last 30 days) — only days with actual sentiment scores (never fabricated or zero-filled)
        $trendOverTime = (clone $query)
            ->where('submitted_at', '>=', now()->subDays(30))
            ->whereNotNull('sentiment_score')
            ->select(DB::raw('DATE(submitted_at) as date'), DB::raw('AVG(sentiment_score) as avg_score'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($row) {
                return [
                    'date'      => (string) $row->date,
                    'avg_score' => round((float) $row->avg_score, 2),
                    'count'     => (int) $row->count,
                ];
            });

        // Build dedicated Text Analytics query (supports section-level filtering by sentiment, category, subcategory)
        $textQuery = clone $query;
        $textSentimentFilter   = $request->input('text_sentiment', $request->input('sentiment', ''));
        $textCategoryFilter    = $request->input('text_category', $request->input('issue_category', ''));
        $textSubcategoryFilter = $request->input('text_subcategory', $request->input('issue_subcategory', ''));

        if (!empty($textSentimentFilter) && $textSentimentFilter !== 'all') {
            $textQuery->where('sentiment_label', $textSentimentFilter);
        }
        if (!empty($textCategoryFilter)) {
            $textQuery->where('issue_category', $textCategoryFilter);
        }
        if (!empty($textSubcategoryFilter)) {
            $textQuery->where('issue_subcategory', $textSubcategoryFilter);
        }

        // Extract real themes/keywords from concern text, excluding stop words and user names
        $concernsForThemes = (clone $textQuery)
            ->select('title', 'description', 'sentiment_label', 'issue_category', 'issue_subcategory')
            ->latest('submitted_at')
            ->take(200)
            ->get();

        $themes = $this->extractCommonThemes($concernsForThemes);
        $textAnalytics = $this->buildTextAnalytics($concernsForThemes, [
            'sentiment'         => $textSentimentFilter ?: 'all',
            'issue_category'    => $textCategoryFilter ?: '',
            'issue_subcategory' => $textSubcategoryFilter ?: '',
        ]);

        $totalScopedConcerns = (clone $query)->count();

        $issueCategories = (clone $query)
            ->whereNotNull('issue_category')
            ->select('issue_category', DB::raw('count(*) as count'))
            ->groupBy('issue_category')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => [
                'category'   => $r->issue_category,
                'count'      => (int) $r->count,
                'percentage' => $totalScopedConcerns > 0 ? round(((int) $r->count / $totalScopedConcerns) * 100, 1) : 0.0,
            ]);

        $issueSubcategories = (clone $query)
            ->whereNotNull('issue_subcategory')
            ->select('issue_category', 'issue_subcategory', DB::raw('count(*) as count'))
            ->groupBy('issue_category', 'issue_subcategory')
            ->orderByDesc('count')
            ->take(12)
            ->get()
            ->map(fn ($r) => [
                'category'    => $r->issue_category,
                'subcategory' => $r->issue_subcategory,
                'count'       => (int) $r->count,
                'percentage'  => $totalScopedConcerns > 0 ? round(((int) $r->count / $totalScopedConcerns) * 100, 1) : 0.0,
            ]);

        $overviewAnalytics = $this->buildOverviewAnalytics($query);

        $resolutionAnalytics = app(\App\Services\ResolutionPredictionService::class)->buildAnalytics(
            function (array $row) use ($user, $request) {
                if ($user->role === 'dean') {
                    if ((int) ($row['academic_unit_id'] ?? 0) !== (int) $user->academic_unit_id) {
                        return false;
                    }
                } elseif ($user->role === 'dept_staff') {
                    $isAcademic = ($row['workflow_type'] ?? '') === 'academic';
                    if ($isAcademic) {
                        if (!$user->academic_unit_id || (int) ($row['academic_unit_id'] ?? 0) !== (int) $user->academic_unit_id) {
                            return false;
                        }
                    } else {
                        if (!$user->dept_id || (int) ($row['dept_id'] ?? 0) !== (int) $user->dept_id) {
                            return false;
                        }
                    }
                }
                if ($request->filled('priority') && ($row['priority'] ?? '') !== $request->priority) {
                    return false;
                }
                if ($request->filled('issue_category') && ($row['issue_category'] ?? '') !== $request->issue_category) {
                    return false;
                }
                return true;
            }
        );

        return response()->json([
            'distribution' => [
                'positive' => $distribution['positive'] ?? 0,
                'neutral'  => $distribution['neutral'] ?? 0,
                'negative' => $distribution['negative'] ?? 0,
                'urgent'   => $distribution['urgent'] ?? 0,
            ],
            'flagged'             => $flaggedConcerns,
            'flagged_summary'     => [
                'shown_count'            => $flaggedConcerns->count(),
                'total_scope_flagged'    => $totalScopeFlaggedCount,
                'sao_privacy_restricted' => $user->role === 'sao',
            ],
            'trend'                   => $trendOverTime,
            'themes'                  => $themes,
            'text_analytics'          => $textAnalytics,
            'available_categories'    => $availableCategories,
            'available_subcategories' => $availableSubcategories,
            'issue_categories'        => $issueCategories,
            'issue_subcategories'     => $issueSubcategories,
            'overview_analytics'      => $overviewAnalytics,
            'resolution_analytics'    => $resolutionAnalytics,
        ]);
    }

    /**
     * Get trend analysis narrative and stats.
     * Uses Gemini if configured; falls back to Anthropic if available.
     * Keeps analytics functional even if AI narrative generation fails.
     */
    public function getTrends(Request $request, AnthropicService $anthropic, GeminiSentimentService $gemini)
    {
        $user = $request->user();
        $query = Concern::query();

        if ($user->role === 'dean') {
            if ($user->academic_unit_id) {
                $query->where('academic_unit_id', $user->academic_unit_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->role === 'dept_staff') {
            $query->where(function ($q) use ($user) {
                $hasCondition = false;
                if ($user->dept_id) {
                    $q->where('dept_id', $user->dept_id);
                    $hasCondition = true;
                }
                if ($user->academic_unit_id) {
                    $acadClause = function ($sub) use ($user) {
                        $sub->where('academic_unit_id', $user->academic_unit_id)
                            ->where(function ($w) {
                                $w->where('workflow_type', 'academic')
                                  ->orWhere(function ($w2) {
                                      $w2->where('category', 'academic_units')
                                         ->whereNull('dept_id');
                                  });
                            });
                    };

                    if ($hasCondition) {
                        $q->orWhere($acadClause);
                    } else {
                        $q->where($acadClause);
                    }
                    $hasCondition = true;
                }
                if (!$hasCondition) {
                    $q->whereRaw('1 = 0');
                }
            });
        }

        // Gather basic stats for the narrative prompt
        $stats = [
            'total_concerns_last_30_days' => (clone $query)->where('submitted_at', '>=', now()->subDays(30))->count(),
            'total_resolved'              => (clone $query)->where('status', 'resolved')->where('submitted_at', '>=', now()->subDays(30))->count(),
            'priority_breakdown'          => (clone $query)->where('submitted_at', '>=', now()->subDays(30))
                ->select('priority', DB::raw('count(*) as count'))
                ->groupBy('priority')
                ->pluck('count', 'priority'),
            'sentiment_breakdown'         => (clone $query)->where('submitted_at', '>=', now()->subDays(30))
                ->select('sentiment_label', DB::raw('count(*) as count'))
                ->groupBy('sentiment_label')
                ->pluck('count', 'sentiment_label'),
        ];

        $narrative = null;
        $aiProvider = 'none';

        // 1. Try Gemini first (active verified AI integration)
        if (!empty(config('services.gemini.api_key'))) {
            $narrative = $gemini->generateTrendNarrative($stats);
            if (!empty($narrative)) {
                $aiProvider = 'gemini';
            }
        }

        // 2. Fall back to Anthropic if Gemini failed or is unconfigured
        if (empty($narrative) && !empty(env('ANTHROPIC_API_KEY')) && env('ANTHROPIC_API_KEY') !== 'dummy_key_for_now') {
            $result = $anthropic->generateTrendNarrative(json_encode($stats));
            if (!empty($result['narrative'])) {
                $narrative = $result['narrative'];
                $aiProvider = 'anthropic';
            }
        }

        return response()->json([
            'stats'       => $stats,
            'narrative'   => $narrative,
            'ai_provider' => $aiProvider,
        ]);
    }

    /**
     * Extract common themes/keywords from concern text.
     * Excludes common stop words, numbers, short words, and user names for privacy.
     *
     * @param  \Illuminate\Support\Collection  $concerns
     * @return array<int, array{text: string, value: int}>
     */
    private function extractCommonThemes($concerns): array
    {
        if ($concerns->isEmpty()) {
            return [];
        }

        // Gather all user names to ensure no student/staff names are exposed as themes
        $names = User::pluck('name')
            ->flatMap(function ($name) {
                return preg_split('/\s+/', strtolower((string) $name));
            })
            ->filter(fn ($w) => strlen($w) > 2)
            ->flip()
            ->toArray();

        $stopWords = array_flip([
            'the', 'and', 'to', 'of', 'a', 'in', 'that', 'is', 'was', 'for', 'it', 'with', 'as',
            'his', 'on', 'be', 'at', 'by', 'i', 'this', 'had', 'not', 'are', 'but', 'from', 'or',
            'have', 'an', 'they', 'which', 'one', 'you', 'were', 'her', 'all', 'she', 'there',
            'would', 'their', 'we', 'him', 'been', 'has', 'when', 'who', 'will', 'more', 'no',
            'if', 'out', 'so', 'said', 'what', 'up', 'its', 'about', 'into', 'than', 'them',
            'can', 'only', 'other', 'new', 'some', 'could', 'time', 'these', 'two', 'may', 'then',
            'do', 'first', 'any', 'my', 'now', 'such', 'like', 'our', 'over', 'me', 'even', 'most',
            'made', 'after', 'also', 'did', 'many', 'before', 'must', 'through', 'back', 'where',
            'much', 'your', 'way', 'well', 'down', 'should', 'because', 'each', 'just', 'those',
            'people', 'mr', 'mrs', 'ms', 'how', 'too', 'little', 'state', 'good', 'very', 'make',
            'world', 'still', 'own', 'see', 'men', 'work', 'long', 'get', 'here', 'between', 'both',
            'life', 'being', 'under', 'never', 'day', 'same', 'another', 'know', 'while', 'last',
            'might', 'us', 'great', 'year', 'come', 'since', 'against', 'go', 'came', 'right',
            'used', 'take', 'three', 'himself', 'look', 'few', 'general', 'hand', 'school', 'part',
            'small', 'number', 'give', 'found', 'tell', 'thought', 'course', 'concern', 'issue',
            'problem', 'student', 'students', 'please', 'help', 'subject', 'dear', 'maam', 'sir',
            'hello', 'goodday', 'regarding', 'scms', 'department', 'admin', 'dean', 'staff', 'office',
            'system', 'submitted', 'received', 'status', 'email', 'message', 'cannot', 'could',
            'would', 'really', 'already', 'always', 'want', 'need', 'trying', 'tried', 'done',
            'got', 'going', 'went', 'say', 'says', 'said', 'telling', 'told', 'ask', 'asked',
            'inquiry', 'request', 'assistance', 'matter', 'regards', 'kindly', 'attached', 'file'
        ]);

        $frequencies = [];

        foreach ($concerns as $concern) {
            $text = strtolower(($concern->title ?? '') . ' ' . ($concern->description ?? ''));
            // Remove URLs, emails, numbers
            $text = preg_replace('/https?:\/\/\S+|[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/', ' ', $text);
            $text = preg_replace('/[^a-z\s]/', ' ', $text);
            $words = preg_split('/\s+/', (string) $text, -1, PREG_SPLIT_NO_EMPTY);

            $seenInConcern = [];
            foreach ($words as $word) {
                if (strlen($word) < 3 || strlen($word) > 25) {
                    continue;
                }
                if (isset($stopWords[$word]) || isset($names[$word])) {
                    continue;
                }
                if (!isset($seenInConcern[$word])) {
                    $seenInConcern[$word] = true;
                    $frequencies[$word] = ($frequencies[$word] ?? 0) + 1;
                }
            }
        }

        arsort($frequencies);

        $result = [];
        foreach (array_slice($frequencies, 0, 25, true) as $word => $count) {
            $result[] = [
                'text'  => $word,
                'value' => (int) $count,
            ];
        }

        return $result;
    }

    /**
     * Build comprehensive Word Frequency and Phrase (2-word & 3-word) Text Analytics
     * from real concern titles and descriptions, with strict PII stripping.
     *
     * @param  \Illuminate\Support\Collection  $concerns
     * @param  array<string, string>           $filtersApplied
     * @return array<string, mixed>
     */
    private function buildTextAnalytics($concerns, array $filtersApplied = []): array
    {
        $totalConcerns = $concerns->count();
        if ($totalConcerns === 0) {
            return [
                'total_concerns_analyzed' => 0,
                'total_meaningful_words'  => 0,
                'unique_meaningful_words' => 0,
                'word_cloud'              => [],
                'top_words'               => [],
                'top_phrases'             => [],
                'filters_applied'         => $filtersApplied,
            ];
        }

        // Gather all user names, emails, and IDs so no identifying details ever appear
        $privateTokens = [];
        $users = User::select('name', 'email', 'student_id_number')->get();
        foreach ($users as $u) {
            if (!empty($u->name)) {
                foreach (preg_split('/[^a-z]+/', strtolower((string) $u->name), -1, PREG_SPLIT_NO_EMPTY) as $part) {
                    if (strlen($part) >= 2) {
                        $privateTokens[$part] = true;
                    }
                }
            }
            if (!empty($u->email)) {
                $local = strtolower((string) explode('@', $u->email)[0]);
                foreach (preg_split('/[^a-z]+/', $local, -1, PREG_SPLIT_NO_EMPTY) as $part) {
                    if (strlen($part) >= 3) {
                        $privateTokens[$part] = true;
                    }
                }
            }
            if (!empty($u->student_id_number)) {
                $privateTokens[strtolower((string) $u->student_id_number)] = true;
            }
        }

        $stopWords = array_flip([
            'the', 'and', 'to', 'of', 'a', 'in', 'that', 'is', 'was', 'for', 'it', 'with', 'as',
            'his', 'on', 'be', 'at', 'by', 'i', 'this', 'had', 'not', 'are', 'but', 'from', 'or',
            'have', 'an', 'they', 'which', 'one', 'you', 'were', 'her', 'all', 'she', 'there',
            'would', 'their', 'we', 'him', 'been', 'has', 'when', 'who', 'will', 'more', 'no',
            'if', 'out', 'so', 'said', 'what', 'up', 'its', 'about', 'into', 'than', 'them',
            'can', 'only', 'other', 'new', 'some', 'could', 'time', 'these', 'two', 'may', 'then',
            'do', 'first', 'any', 'my', 'now', 'such', 'like', 'our', 'over', 'me', 'even', 'most',
            'made', 'after', 'also', 'did', 'many', 'before', 'must', 'through', 'back', 'where',
            'much', 'your', 'way', 'well', 'down', 'should', 'because', 'each', 'just', 'those',
            'people', 'mr', 'mrs', 'ms', 'how', 'too', 'little', 'state', 'good', 'very', 'make',
            'world', 'still', 'own', 'see', 'men', 'work', 'long', 'get', 'here', 'between', 'both',
            'life', 'being', 'under', 'never', 'day', 'same', 'another', 'know', 'while', 'last',
            'might', 'us', 'great', 'year', 'come', 'since', 'against', 'go', 'came', 'right',
            'used', 'take', 'three', 'himself', 'look', 'few', 'general', 'hand', 'school', 'part',
            'small', 'number', 'give', 'found', 'tell', 'thought', 'student', 'students', 'please',
            'help', 'dear', 'maam', 'sir', 'hello', 'goodday', 'regarding', 'scms', 'admin', 'dean',
            'staff', 'submitted', 'received', 'cannot', 'really', 'already', 'always', 'want', 'need',
            'trying', 'tried', 'done', 'got', 'going', 'went', 'say', 'says', 'telling', 'told',
            'ask', 'asked', 'assistance', 'matter', 'regards', 'kindly', 'attached', 'file',
            'synth', 'synthetic', 'test', 'testing', 'uclm', 'across', 'multiple', 'immediate',
            'immediately', 'repeatedly', 'continuously', 'currently', 'recently', 'affected',
            'scheduled', 'several', 'various', 'during', 'every', 'within', 'without'
        ]);

        // Generic nouns excluded from single-word rankings, but allowed inside multi-word phrases
        $genericSingleWords = array_flip([
            'concern', 'issue', 'problem', 'inquiry', 'request', 'status', 'system',
            'office', 'department', 'course', 'subject', 'email', 'message'
        ]);

        $allowedShortWords = array_flip(['pc', 'av', 'ac', 'os', 'tv', 'id', 'ui']);

        $normalizationMap = [
            'computers'    => 'computer',
            'pcs'          => 'pc',
            'laptops'      => 'laptop',
            'monitors'     => 'monitor',
            'keyboards'    => 'keyboard',
            'printers'     => 'printer',
            'projectors'   => 'projector',
            'laboratories' => 'laboratory',
            'labs'         => 'lab',
            'classrooms'   => 'classroom',
            'rooms'        => 'room',
            'connections'  => 'connection',
            'networks'     => 'network',
            'grades'       => 'grade',
            'payments'     => 'payment',
            'tuitions'     => 'tuition',
            'fees'         => 'fee',
            'instructors'  => 'instructor',
            'professors'   => 'professor',
            'teachers'     => 'teacher',
            'schedules'    => 'schedule',
            'accounts'     => 'account',
            'passwords'    => 'password',
            'portals'      => 'portal',
            'wires'        => 'wire',
            'cables'       => 'cable',
            'problems'     => 'problem',
            'issues'       => 'issue',
            'concerns'     => 'concern',
            'inquiries'    => 'inquiry',
            'requests'     => 'request',
            'errors'       => 'error',
            'outages'      => 'outage',
        ];

        $wordOccurrences   = [];
        $wordConcernCounts = [];
        $phraseOccurrences = [];
        $phraseConcernCnts = [];
        $phraseWordLengths = [];
        $totalMeaningfulWords = 0;
        $seenExactTexts    = [];

        foreach ($concerns as $concern) {
            $rawText = strtolower(trim(($concern->title ?? '') . '. ' . ($concern->description ?? '')));
            // Collapse exact duplicate clone descriptions so synthetic stress-test clones do not skew phrase rankings
            $textHash = md5($rawText);
            $isDuplicateClone = isset($seenExactTexts[$textHash]);
            $seenExactTexts[$textHash] = true;
            if ($isDuplicateClone) {
                continue;
            }
            // Normalize wi-fi before stripping punctuation
            $rawText = str_replace(['wi-fi', 'e-mail', 'on-line', 'log-in'], ['wifi', 'email', 'online', 'login'], $rawText);
            // Strip URLs, email addresses, and numbers
            $rawText = preg_replace('/https?:\/\/\S+|[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/', ' ', $rawText);
            $rawText = preg_replace('/\b\d+\b/', ' ', $rawText);

            $clauses = preg_split('/[\.\!\?\;\,\:\n\r\(\)\[\]—–\/]+/', (string) $rawText, -1, PREG_SPLIT_NO_EMPTY);

            $seenWordInConcern   = [];
            $seenPhraseInConcern = [];

            foreach ($clauses as $clause) {
                $cleanClause = preg_replace('/[^a-z\s]/', ' ', $clause);
                $rawWords = preg_split('/\s+/', (string) $cleanClause, -1, PREG_SPLIT_NO_EMPTY);

                $clausePhraseTokens = [];

                foreach ($rawWords as $rawWord) {
                    $word = $normalizationMap[$rawWord] ?? $rawWord;

                    $isValidLength = (strlen($word) >= 3 && strlen($word) <= 25) || isset($allowedShortWords[$word]);
                    if (!$isValidLength || isset($privateTokens[$word]) || isset($stopWords[$word])) {
                        // Break phrase adjacency when hitting a stopword or private token
                        $clausePhraseTokens[] = null;
                        continue;
                    }

                    // Valid token for phrase construction
                    $clausePhraseTokens[] = $word;

                    // Single-word counting (excludes generic boilerplate nouns like "concern", "problem", "issue")
                    if (!isset($genericSingleWords[$word])) {
                        $wordOccurrences[$word] = ($wordOccurrences[$word] ?? 0) + 1;
                        $totalMeaningfulWords++;
                        if (!isset($seenWordInConcern[$word])) {
                            $seenWordInConcern[$word] = true;
                            $wordConcernCounts[$word] = ($wordConcernCounts[$word] ?? 0) + 1;
                        }
                    }
                }

                // Extract 2-word (bigram) and 3-word (trigram) adjacent meaningful phrases within the clause
                $tokenCount = count($clausePhraseTokens);
                for ($i = 0; $i < $tokenCount; $i++) {
                    $w1 = $clausePhraseTokens[$i];
                    if ($w1 === null) {
                        continue;
                    }

                    // 2-word phrase
                    if ($i + 1 < $tokenCount && $clausePhraseTokens[$i + 1] !== null) {
                        $w2 = $clausePhraseTokens[$i + 1];
                        if ($w1 !== $w2 && (!isset($genericSingleWords[$w1]) || !isset($genericSingleWords[$w2]))) {
                            $bigram = "{$w1} {$w2}";
                            $phraseOccurrences[$bigram] = ($phraseOccurrences[$bigram] ?? 0) + 1;
                            $phraseWordLengths[$bigram] = 2;
                            if (!isset($seenPhraseInConcern[$bigram])) {
                                $seenPhraseInConcern[$bigram] = true;
                                $phraseConcernCnts[$bigram] = ($phraseConcernCnts[$bigram] ?? 0) + 1;
                            }
                        }

                        // 3-word phrase
                        if ($i + 2 < $tokenCount && $clausePhraseTokens[$i + 2] !== null) {
                            $w3 = $clausePhraseTokens[$i + 2];
                            if ($w2 !== $w3 && $w1 !== $w3 && (!isset($genericSingleWords[$w1]) || !isset($genericSingleWords[$w2]) || !isset($genericSingleWords[$w3]))) {
                                $trigram = "{$w1} {$w2} {$w3}";
                                $phraseOccurrences[$trigram] = ($phraseOccurrences[$trigram] ?? 0) + 1;
                                $phraseWordLengths[$trigram] = 3;
                                if (!isset($seenPhraseInConcern[$trigram])) {
                                    $seenPhraseInConcern[$trigram] = true;
                                    $phraseConcernCnts[$trigram] = ($phraseConcernCnts[$trigram] ?? 0) + 1;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Sort single words by concern frequency then total occurrences
        uksort($wordOccurrences, function ($a, $b) use ($wordOccurrences, $wordConcernCounts) {
            $cmp = ($wordOccurrences[$b] ?? 0) <=> ($wordOccurrences[$a] ?? 0);
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmpConcern = ($wordConcernCounts[$b] ?? 0) <=> ($wordConcernCounts[$a] ?? 0);
            return $cmpConcern !== 0 ? $cmpConcern : strcmp($a, $b);
        });

        $wordCloud = [];
        $topWords  = [];
        $rank      = 1;

        foreach (array_slice($wordOccurrences, 0, 30, true) as $word => $occ) {
            $cCount   = (int) ($wordConcernCounts[$word] ?? 1);
            $pct      = round(($cCount / max(1, $totalConcerns)) * 100, 1);
            $sharePct = $totalMeaningfulWords > 0 ? round(($occ / $totalMeaningfulWords) * 100, 1) : 0.0;

            $item = [
                'rank'          => $rank,
                'word'          => $word,
                'text'          => $word,
                'count'         => (int) $occ,
                'value'         => (int) $occ,
                'concern_count' => $cCount,
                'percentage'    => $pct,
                'share_pct'     => $sharePct,
            ];

            $wordCloud[] = $item;
            if ($rank <= 20) {
                $topWords[] = $item;
            }
            $rank++;
        }

        // Sort phrases by occurrence count descending, then concern count descending
        uksort($phraseOccurrences, function ($a, $b) use ($phraseOccurrences, $phraseConcernCnts) {
            $cmp = ($phraseOccurrences[$b] ?? 0) <=> ($phraseOccurrences[$a] ?? 0);
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmpConcern = ($phraseConcernCnts[$b] ?? 0) <=> ($phraseConcernCnts[$a] ?? 0);
            return $cmpConcern !== 0 ? $cmpConcern : strcmp($a, $b);
        });

        // Prefer recurring phrases (count >= 2); if dataset is small and has < 5 recurring phrases, include top phrases (count >= 1)
        $recurringPhrases = array_filter($phraseOccurrences, fn ($cnt) => $cnt >= 2);
        $selectedPhrases  = count($recurringPhrases) >= 5
            ? array_slice($recurringPhrases, 0, 15, true)
            : array_slice($phraseOccurrences, 0, 12, true);

        $topPhrases = [];
        $pRank      = 1;
        foreach ($selectedPhrases as $phrase => $occ) {
            $cCount = (int) ($phraseConcernCnts[$phrase] ?? 1);
            $topPhrases[] = [
                'rank'          => $pRank++,
                'phrase'        => $phrase,
                'word_count'    => (int) ($phraseWordLengths[$phrase] ?? 2),
                'count'         => (int) $occ,
                'concern_count' => $cCount,
                'percentage'    => round(($cCount / max(1, $totalConcerns)) * 100, 1),
            ];
        }

        return [
            'total_concerns_analyzed' => $totalConcerns,
            'total_meaningful_words'  => $totalMeaningfulWords,
            'unique_meaningful_words' => count($wordOccurrences),
            'word_cloud'              => $wordCloud,
            'top_words'               => $topWords,
            'top_phrases'             => $topPhrases,
            'filters_applied'         => $filtersApplied,
        ];
    }

    /**
     * Build overview analytics for the unified Analytics & Sentiment Dashboard:
     * volume & resolution summary, priority/status breakdowns, organizational unit comparisons,
     * unresolved concern analytics, and daily submission vs resolution trends.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return array<string, mixed>
     */
    private function buildOverviewAnalytics($query): array
    {
        $totalConcerns      = (clone $query)->count();
        $resolvedConcerns   = (clone $query)->whereIn('status', ['resolved', 'closed'])->count();
        $unresolvedConcerns = (clone $query)->whereNotIn('status', ['resolved', 'closed', 'withdrawn', 'rejected'])->count();
        $pendingConcerns    = (clone $query)->where('status', 'pending')->count();
        $inProgressConcerns = (clone $query)->whereIn('status', ['in_progress', 'assigned', 'approved'])->count();
        $escalatedConcerns  = (clone $query)->where('status', 'escalated')->count();
        $criticalConcerns   = (clone $query)->where('priority', 'critical')->count();
        $resolutionRate     = $totalConcerns > 0 ? round(($resolvedConcerns / $totalConcerns) * 100, 1) : 0.0;

        // SLA Overdue & Approaching counts
        $slaService       = app(\App\Services\ConcernSlaService::class);
        $overdueCount     = 0;
        $approachingCount = 0;

        if ($slaService->isEnabled()) {
            $openConcerns = (clone $query)
                ->whereIn('status', (array) config('sla.monitored_statuses', ['pending', 'assigned', 'in_progress', 'returned']))
                ->with('routingHistory')
                ->get();

            foreach ($openConcerns as $c) {
                $state = $slaService->describe($c)['state'] ?? 'normal';
                if ($state === 'overdue') {
                    $overdueCount++;
                } elseif (in_array($state, ['approaching', 'followup_sent'], true)) {
                    $approachingCount++;
                }
            }
        }

        // Priority Breakdown
        $priorityPluck = (clone $query)
            ->whereNotNull('priority')
            ->select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority');

        $byPriority = [];
        foreach (['critical', 'high', 'medium', 'low'] as $prio) {
            $cnt = (int) ($priorityPluck[$prio] ?? 0);
            $byPriority[] = [
                'priority'   => $prio,
                'count'      => $cnt,
                'percentage' => $totalConcerns > 0 ? round(($cnt / $totalConcerns) * 100, 1) : 0.0,
            ];
        }

        // Status Breakdown
        $statusPluck = (clone $query)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $byStatus = [];
        foreach (['pending', 'in_progress', 'escalated', 'resolved', 'closed'] as $st) {
            $cnt = (int) ($statusPluck[$st] ?? 0);
            if ($cnt > 0 || in_array($st, ['pending', 'in_progress', 'escalated', 'resolved'], true)) {
                $byStatus[] = [
                    'status'     => $st,
                    'count'      => $cnt,
                    'percentage' => $totalConcerns > 0 ? round(($cnt / $totalConcerns) * 100, 1) : 0.0,
                ];
            }
        }

        // Department / Academic Unit Comparison
        $unitRows = (clone $query)
            ->whereNotNull('academic_unit_id')
            ->select(
                'academic_unit_id',
                DB::raw('count(*) as total'),
                DB::raw("SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved"),
                DB::raw("SUM(CASE WHEN status NOT IN ('resolved', 'closed', 'withdrawn', 'rejected') THEN 1 ELSE 0 END) as unresolved"),
                DB::raw("SUM(CASE WHEN priority = 'critical' THEN 1 ELSE 0 END) as critical"),
                DB::raw("SUM(CASE WHEN sentiment_label IN ('negative', 'urgent') THEN 1 ELSE 0 END) as negative_or_urgent")
            )
            ->groupBy('academic_unit_id')
            ->with('academicUnit:id,name')
            ->get()
            ->map(fn ($r) => [
                'name'               => $r->academicUnit?->name ?? 'Academic Unit',
                'type'               => 'Academic Unit',
                'total'              => (int) $r->total,
                'resolved'           => (int) $r->resolved,
                'unresolved'         => (int) $r->unresolved,
                'critical'           => (int) $r->critical,
                'negative_or_urgent' => (int) $r->negative_or_urgent,
            ]);

        $deptRows = (clone $query)
            ->whereNotNull('dept_id')
            ->select(
                'dept_id',
                DB::raw('count(*) as total'),
                DB::raw("SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved"),
                DB::raw("SUM(CASE WHEN status NOT IN ('resolved', 'closed', 'withdrawn', 'rejected') THEN 1 ELSE 0 END) as unresolved"),
                DB::raw("SUM(CASE WHEN priority = 'critical' THEN 1 ELSE 0 END) as critical"),
                DB::raw("SUM(CASE WHEN sentiment_label IN ('negative', 'urgent') THEN 1 ELSE 0 END) as negative_or_urgent")
            )
            ->groupBy('dept_id')
            ->with('department:id,name')
            ->get()
            ->map(fn ($r) => [
                'name'               => $r->department?->name ?? 'Department',
                'type'               => 'Department',
                'total'              => (int) $r->total,
                'resolved'           => (int) $r->resolved,
                'unresolved'         => (int) $r->unresolved,
                'critical'           => (int) $r->critical,
                'negative_or_urgent' => (int) $r->negative_or_urgent,
            ]);

        $byOrganization = $unitRows->concat($deptRows)
            ->sortByDesc('total')
            ->take(10)
            ->values();

        // Submission & Resolution Volume Trend (Last 30 Days with activity)
        $submittedByDate = (clone $query)
            ->where('submitted_at', '>=', now()->subDays(30))
            ->select(DB::raw('DATE(submitted_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->pluck('count', 'date');

        $resolvedByDate = (clone $query)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', now()->subDays(30))
            ->select(DB::raw('DATE(resolved_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->pluck('count', 'date');

        $allDates = collect($submittedByDate->keys())
            ->merge($resolvedByDate->keys())
            ->unique()
            ->sort()
            ->values();

        $volumeTrend = $allDates->map(fn ($d) => [
            'date'      => (string) $d,
            'submitted' => (int) ($submittedByDate[$d] ?? 0),
            'resolved'  => (int) ($resolvedByDate[$d] ?? 0),
        ])->values();

        return [
            'summary' => [
                'total_concerns'                => $totalConcerns,
                'resolved_concerns'             => $resolvedConcerns,
                'unresolved_concerns'           => $unresolvedConcerns,
                'pending_concerns'              => $pendingConcerns,
                'in_progress_concerns'          => $inProgressConcerns,
                'escalated_concerns'            => $escalatedConcerns,
                'critical_concerns'             => $criticalConcerns,
                'overdue_concerns'              => $overdueCount,
                'approaching_deadline_concerns' => $approachingCount,
                'resolution_rate'               => $resolutionRate,
            ],
            'by_priority'     => $byPriority,
            'by_status'       => $byStatus,
            'by_organization' => $byOrganization,
            'volume_trend'    => $volumeTrend,
        ];
    }

    // ── Dean Reports ─────────────────────────────────────────────────────────

    /**
     * Return concern-management report data for the Dean.
     * Scoped to concerns accessible by the Dean's academic unit and workflow.
     */
    public function getDeanReport(Request $request)
    {
        $user           = $request->user();
        $academicUnitId = $user->academic_unit_id;

        // Validate filters
        $validated = $request->validate([
            'date_from'     => 'nullable|date',
            'date_to'       => 'nullable|date|after_or_equal:date_from',
            'status'        => 'nullable|in:pending,in_progress,escalated,approved,rejected,resolved,closed,withdrawn',
            'priority'      => 'nullable|in:critical,high,medium,low',
            'workflow_type' => 'nullable|in:academic,non_academic',
            'category'      => 'nullable|string|max:120',
        ]);

        // Base query: Dean-accessible concerns (strictly scoped to Dean's academic unit)
        if (!$academicUnitId) {
            $scopeBase = Concern::whereRaw('1 = 0');
        } else {
            $scopeBase = Concern::where('academic_unit_id', $academicUnitId)
                ->where(function ($w) {
                    $w->where('workflow_type', 'academic')
                      ->orWhere('category', 'academic_units');
                })
                ->where(function ($inner) {
                    $inner->where('target_type', 'dean')
                          ->orWhere('status', 'escalated')
                          ->orWhereIn('status', ['approved', 'returned', 'rejected', 'resolved']);
                });
        }

        $availableCategories = (clone $scopeBase)
            ->select(DB::raw("COALESCE(NULLIF(TRIM(issue_category), ''), NULLIF(TRIM(category), '')) as cat"))
            ->whereRaw("COALESCE(NULLIF(TRIM(issue_category), ''), NULLIF(TRIM(category), '')) IS NOT NULL")
            ->distinct()
            ->pluck('cat')
            ->filter()
            ->map(fn ($cat) => match ($cat) {
                'academic_units'          => 'Academic Concerns',
                'operational_departments' => 'Operational Departments',
                default                   => $cat,
            })
            ->unique()
            ->values();

        $base = clone $scopeBase;

        // Apply filters
        if (!empty($validated['date_from'])) {
            $base->where('submitted_at', '>=', $validated['date_from']);
        }
        if (!empty($validated['date_to'])) {
            $base->where('submitted_at', '<=', $validated['date_to'] . ' 23:59:59');
        }
        if (!empty($validated['status'])) {
            $base->where('status', $validated['status']);
        }
        if (!empty($validated['priority'])) {
            $base->where('priority', $validated['priority']);
        }
        if (!empty($validated['workflow_type'])) {
            $base->where('workflow_type', $validated['workflow_type']);
        }
        if (!empty($validated['category'])) {
            $catFilter = $validated['category'];
            $rawAliases = [$catFilter];
            if ($catFilter === 'Academic Concerns') {
                $rawAliases[] = 'academic_units';
            } elseif ($catFilter === 'Operational Departments') {
                $rawAliases[] = 'operational_departments';
            }
            $base->where(function ($q) use ($rawAliases) {
                $q->whereIn('issue_category', $rawAliases)
                  ->orWhereIn('category', $rawAliases);
            });
        }

        $unresolvedStatuses = ['pending', 'in_progress', 'escalated', 'approved', 'returned'];

        // Summary counts
        $totalConcerns   = (clone $base)->count();
        $pendingCount    = (clone $base)->whereIn('status', ['pending', 'in_progress'])->count();
        $unresolvedCount = (clone $base)->whereIn('status', $unresolvedStatuses)->count();
        $resolvedCount   = (clone $base)->where('status', 'resolved')->count();
        $escalatedCount  = (clone $base)->where('status', 'escalated')->count();
        $criticalCount   = (clone $base)->where('priority', 'critical')->count();
        $highCount       = (clone $base)->where('priority', 'high')->count();

        $criticalUnresolvedCount = (clone $base)
            ->where('priority', 'critical')
            ->whereIn('status', $unresolvedStatuses)
            ->count();
        $highUnresolvedCount = (clone $base)
            ->where('priority', 'high')
            ->whereIn('status', $unresolvedStatuses)
            ->count();

        // Resolution rate
        $resolutionRate = $totalConcerns > 0
            ? round(($resolvedCount / $totalConcerns) * 100, 1)
            : 0;

        // SLA evaluation across open concerns in filtered scope
        $slaService = app(\App\Services\ConcernSlaService::class);
        $openConcernsForSla = (clone $base)
            ->whereIn('status', $unresolvedStatuses)
            ->with(['routingHistory', 'student:id,name,student_id_number', 'academicUnit:id,name', 'department:id,name'])
            ->orderBy('submitted_at', 'asc')
            ->get();

        $overdueCount      = 0;
        $approachingCount  = 0;
        $followupSentCount = 0;
        $attentionItems    = [];

        foreach ($openConcernsForSla as $openConcern) {
            $slaDesc = $slaService->describe($openConcern);
            $state   = $slaDesc['state'] ?? 'normal';
            if ($state === 'overdue') {
                $overdueCount++;
            } elseif ($state === 'followup_sent') {
                $followupSentCount++;
                $approachingCount++;
            } elseif ($state === 'approaching') {
                $approachingCount++;
            }

            $reasons = [];
            if ($openConcern->priority === 'critical') {
                $reasons[] = 'Critical Priority';
            } elseif ($openConcern->priority === 'high') {
                $reasons[] = 'High Priority';
            }
            if ($state === 'overdue') {
                $reasons[] = 'Overdue Target';
            } elseif ($state === 'followup_sent' || $state === 'approaching') {
                $reasons[] = 'Approaching Target (≥75%)';
            }
            if ($openConcern->status === 'escalated') {
                $reasons[] = 'Escalated to Dean/CAD';
            }

            if (!empty($reasons)) {
                $attentionItems[] = [
                    'concern_id'    => $openConcern->concern_id,
                    'title'         => $openConcern->title,
                    'status'        => $openConcern->status,
                    'priority'      => $openConcern->priority,
                    'workflow_type' => $openConcern->workflow_type,
                    'category'      => $openConcern->issue_category ?: ($openConcern->category ?: 'General'),
                    'unit_name'     => $openConcern->academicUnit->name ?? $openConcern->department->name ?? 'Academic Unit',
                    'student_name'  => $openConcern->is_anonymous ? 'Anonymous' : ($openConcern->student->name ?? '—'),
                    'submitted_at'  => $openConcern->submitted_at,
                    'sla_state'     => $state,
                    'target_hours'  => $slaDesc['target_hours'] ?? null,
                    'elapsed_hours' => isset($slaDesc['elapsed_seconds']) ? round($slaDesc['elapsed_seconds'] / 3600, 1) : null,
                    'reasons'       => $reasons,
                    'urgency_rank'  => ($openConcern->priority === 'critical' ? 100 : ($openConcern->priority === 'high' ? 50 : 10))
                        + ($state === 'overdue' ? 80 : ($state === 'followup_sent' ? 30 : 0))
                        + ($openConcern->status === 'escalated' ? 20 : 0),
                ];
            }
        }

        usort($attentionItems, fn ($a, $b) => $b['urgency_rank'] <=> $a['urgency_rank']);
        $attentionItems = array_slice($attentionItems, 0, 10);

        // Previous period comparison
        if (!empty($validated['date_from']) && !empty($validated['date_to'])) {
            $currStart  = \Carbon\Carbon::parse($validated['date_from'])->startOfDay();
            $currEnd    = \Carbon\Carbon::parse($validated['date_to'])->endOfDay();
            $periodDays = max(1, (int) $currStart->diffInDays($currEnd) + 1);
            $prevEnd    = $currStart->copy()->subSecond();
            $prevStart  = $prevEnd->copy()->subDays($periodDays - 1)->startOfDay();
            $periodLabel = 'vs. previous ' . $periodDays . '-day period';
        } else {
            $currEnd     = now()->endOfDay();
            $currStart   = now()->subDays(29)->startOfDay();
            $prevEnd     = $currStart->copy()->subSecond();
            $prevStart   = $prevEnd->copy()->subDays(29)->startOfDay();
            $periodLabel = 'vs. previous 30 days';
        }

        $prevQuery = clone $scopeBase;
        if (!empty($validated['status'])) {
            $prevQuery->where('status', $validated['status']);
        }
        if (!empty($validated['priority'])) {
            $prevQuery->where('priority', $validated['priority']);
        }
        if (!empty($validated['workflow_type'])) {
            $prevQuery->where('workflow_type', $validated['workflow_type']);
        }
        if (!empty($validated['category'])) {
            $catFilter = $validated['category'];
            $prevQuery->where(function ($q) use ($catFilter) {
                $q->where('issue_category', $catFilter)->orWhere('category', $catFilter);
            });
        }

        $currWindowQuery = (clone $prevQuery)->whereBetween('submitted_at', [$currStart, $currEnd]);
        $prevWindowQuery = (clone $prevQuery)->whereBetween('submitted_at', [$prevStart, $prevEnd]);

        $currWindowTotal      = (clone $currWindowQuery)->count();
        $prevWindowTotal      = (clone $prevWindowQuery)->count();
        $currWindowResolved   = (clone $currWindowQuery)->where('status', 'resolved')->count();
        $prevWindowResolved   = (clone $prevWindowQuery)->where('status', 'resolved')->count();
        $currWindowUnresolved = (clone $currWindowQuery)->whereIn('status', $unresolvedStatuses)->count();
        $prevWindowUnresolved = (clone $prevWindowQuery)->whereIn('status', $unresolvedStatuses)->count();

        $previousPeriod = [
            'label'               => $periodLabel,
            'current_total'       => $currWindowTotal,
            'previous_total'      => $prevWindowTotal,
            'total_delta'         => $currWindowTotal - $prevWindowTotal,
            'current_resolved'    => $currWindowResolved,
            'previous_resolved'   => $prevWindowResolved,
            'resolved_delta'      => $currWindowResolved - $prevWindowResolved,
            'current_unresolved'  => $currWindowUnresolved,
            'previous_unresolved' => $prevWindowUnresolved,
            'unresolved_delta'    => $currWindowUnresolved - $prevWindowUnresolved,
        ];

        // By status breakdown
        $byStatus = (clone $base)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        // By priority breakdown
        $byPriority = (clone $base)
            ->select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority');

        // By workflow type (academic vs non-academic)
        $byWorkflow = (clone $base)
            ->select('workflow_type', DB::raw('count(*) as count'))
            ->groupBy('workflow_type')
            ->pluck('count', 'workflow_type');

        // Category breakdown (merged by normalized display name)
        $byCategoryRows = (clone $base)
            ->select(
                DB::raw("COALESCE(NULLIF(TRIM(issue_category), ''), NULLIF(TRIM(category), ''), 'Uncategorized') as category_label"),
                DB::raw('count(*) as count')
            )
            ->groupBy('category_label')
            ->get()
            ->map(function ($row) {
                $label = match ($row->category_label) {
                    'academic_units'          => 'Academic Concerns',
                    'operational_departments' => 'Operational Departments',
                    default                   => $row->category_label,
                };
                return [
                    'category' => $label,
                    'count'    => (int) $row->count,
                ];
            })
            ->groupBy('category')
            ->map(function ($group, $label) use ($totalConcerns) {
                $sum = (int) $group->sum('count');
                return [
                    'category'     => $label,
                    'raw_category' => $label,
                    'count'        => $sum,
                    'percentage'   => $totalConcerns > 0 ? round(($sum / $totalConcerns) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('count')
            ->values();

        // Trend: concerns submitted per day (last 30 days or filtered range)
        $trendQuery = clone $base;
        if (empty($validated['date_from'])) {
            $trendQuery->where('submitted_at', '>=', now()->subDays(29)->startOfDay());
        }
        $trend = $trendQuery
            ->select(DB::raw('DATE(submitted_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        // Combined Activity Trend: Submitted vs Resolved & Unresolved Backlog Change
        $resolvedTrendQuery = clone $base;
        $resolvedTrendQuery->whereNotNull('resolved_at')->where('status', 'resolved');
        if (empty($validated['date_from'])) {
            $resolvedTrendQuery->where('resolved_at', '>=', now()->subDays(29)->startOfDay());
        }
        $resolvedByDate = $resolvedTrendQuery
            ->select(DB::raw('DATE(resolved_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->pluck('count', 'date');

        $submittedByDate = $trend->pluck('count', 'date');
        $allDates = $submittedByDate->keys()->merge($resolvedByDate->keys())->unique()->sort()->values();

        $runningBacklogDelta  = 0;
        $periodSubmittedTotal = 0;
        $periodResolvedTotal  = 0;
        $activityTrend        = [];

        foreach ($allDates as $d) {
            $subCount = (int) ($submittedByDate[$d] ?? 0);
            $resCount = (int) ($resolvedByDate[$d] ?? 0);
            $netDelta = $subCount - $resCount;
            $runningBacklogDelta  += $netDelta;
            $periodSubmittedTotal += $subCount;
            $periodResolvedTotal  += $resCount;

            $activityTrend[] = [
                'date'                      => $d,
                'submitted'                 => $subCount,
                'resolved'                  => $resCount,
                'net_change'                => $netDelta,
                'cumulative_backlog_change' => $runningBacklogDelta,
            ];
        }

        if ($periodSubmittedTotal === 0 && $periodResolvedTotal === 0) {
            $activityExplanation = 'No concern submissions or resolutions were recorded during this reporting window.';
        } elseif ($periodSubmittedTotal > $periodResolvedTotal) {
            $diff = $periodSubmittedTotal - $periodResolvedTotal;
            $activityExplanation = "More concerns were submitted ({$periodSubmittedTotal}) than resolved ({$periodResolvedTotal}) during this period, increasing the unresolved backlog by {$diff}.";
        } elseif ($periodResolvedTotal > $periodSubmittedTotal) {
            $diff = $periodResolvedTotal - $periodSubmittedTotal;
            $activityExplanation = "More concerns were resolved ({$periodResolvedTotal}) than submitted ({$periodSubmittedTotal}) during this period, reducing the unresolved backlog by {$diff}.";
        } else {
            $activityExplanation = "Concern submissions ({$periodSubmittedTotal}) and resolutions ({$periodResolvedTotal}) were balanced during this period, keeping the backlog steady.";
        }

        // Sentiment summary (only when data exists)
        $sentimentBreakdown = (clone $base)
            ->whereNotNull('sentiment_label')
            ->select('sentiment_label', DB::raw('count(*) as count'))
            ->groupBy('sentiment_label')
            ->pluck('count', 'sentiment_label');

        $hasSentimentData = $sentimentBreakdown->isNotEmpty();

        // Recent concerns list (latest 50 for the report table)
        $concerns = (clone $base)
            ->with(['student:id,name,student_id_number', 'academicUnit', 'department'])
            ->orderBy('submitted_at', 'desc')
            ->take(50)
            ->get()
            ->map(function ($c) {
                if ($c->is_anonymous && $c->student) {
                    $c->student->name              = 'Anonymous';
                    $c->student->student_id_number = '********';
                }
                return $c;
            });

        $resolutionAnalytics = app(\App\Services\ResolutionPredictionService::class)->buildAnalytics(
            function (array $row) use ($academicUnitId, $validated) {
                if ($academicUnitId && (int) ($row['academic_unit_id'] ?? 0) !== (int) $academicUnitId) {
                    return false;
                }
                if (empty($validated['workflow_type']) && !empty($row['dept_id']) && ($row['workflow_type'] ?? '') === 'non_academic') {
                    return false;
                }
                if (!empty($validated['priority']) && ($row['priority'] ?? '') !== $validated['priority']) {
                    return false;
                }
                if (!empty($validated['workflow_type']) && ($row['workflow_type'] ?? '') !== $validated['workflow_type']) {
                    return false;
                }
                if (!empty($validated['category'])) {
                    $cat = $validated['category'];
                    if (($row['issue_category'] ?? '') !== $cat && ($row['org_category'] ?? '') !== $cat) {
                        return false;
                    }
                }
                return true;
            }
        );

        $medianHours = $resolutionAnalytics['summary']['median_resolution_hours'] ?? null;
        $meanHours   = $resolutionAnalytics['summary']['mean_resolution_hours'] ?? null;
        $sampleSize  = $resolutionAnalytics['summary']['sample_size'] ?? 0;

        $insights = [];
        if ($totalConcerns === 0) {
            $insights[] = [
                'type' => 'neutral',
                'text' => 'No concerns match the selected filters in your academic unit scope.',
            ];
        } else {
            if ($periodSubmittedTotal > $periodResolvedTotal) {
                $insights[] = [
                    'type' => 'warning',
                    'text' => "Submissions ({$periodSubmittedTotal}) exceeded resolutions ({$periodResolvedTotal}) in the activity window, adding " . ($periodSubmittedTotal - $periodResolvedTotal) . " concern(s) to the active backlog.",
                ];
            } elseif ($periodResolvedTotal > $periodSubmittedTotal && $periodResolvedTotal > 0) {
                $insights[] = [
                    'type' => 'positive',
                    'text' => "Resolutions ({$periodResolvedTotal}) outpaced new submissions ({$periodSubmittedTotal}) in the activity window, reducing the active backlog.",
                ];
            }

            if ($byCategoryRows->isNotEmpty()) {
                $topCat = $byCategoryRows->first();
                $insights[] = [
                    'type' => 'info',
                    'text' => "{$topCat['category']} is the most common category in this report, accounting for {$topCat['count']} of {$totalConcerns} concern(s) ({$topCat['percentage']}%).",
                ];
            }

            if ($criticalUnresolvedCount > 0 || $overdueCount > 0 || $escalatedCount > 0) {
                $insights[] = [
                    'type' => $criticalUnresolvedCount > 0 || $overdueCount > 0 ? 'critical' : 'warning',
                    'text' => "Attention summary: {$criticalUnresolvedCount} critical unresolved, {$overdueCount} overdue, and {$escalatedCount} escalated concern(s) in your academic unit scope.",
                ];
            } else {
                $insights[] = [
                    'type' => 'positive',
                    'text' => 'There are currently 0 critical unresolved concerns and 0 overdue concerns in your academic unit scope.',
                ];
            }

            if ($sampleSize > 0 && $medianHours !== null) {
                $caution = $sampleSize < 3 ? ' (limited sample size — interpret with caution)' : '';
                $insights[] = [
                    'type' => 'info',
                    'text' => "Based on {$sampleSize} completed historical case(s), the typical resolution time in your unit is {$medianHours}h (average {$meanHours}h){$caution}.",
                ];
            }
        }

        return response()->json([
            'summary' => [
                'total'                   => $totalConcerns,
                'pending'                 => $pendingCount,
                'unresolved'              => $unresolvedCount,
                'resolved'                => $resolvedCount,
                'escalated'               => $escalatedCount,
                'critical'                => $criticalCount,
                'high'                    => $highCount,
                'critical_unresolved'     => $criticalUnresolvedCount,
                'high_unresolved'         => $highUnresolvedCount,
                'overdue'                 => $overdueCount,
                'approaching_deadline'    => $approachingCount,
                'followup_sent'           => $followupSentCount,
                'resolution_rate'         => $resolutionRate,
                'median_resolution_hours' => $medianHours,
                'mean_resolution_hours'   => $meanHours,
                'previous_period'         => $previousPeriod,
            ],
            'by_status'            => $byStatus,
            'by_priority'          => $byPriority,
            'by_workflow'          => $byWorkflow,
            'by_category'          => $byCategoryRows,
            'available_categories' => $availableCategories,
            'trend'                => $trend,
            'activity_trend'       => $activityTrend,
            'activity_summary'     => [
                'period_submitted'   => $periodSubmittedTotal,
                'period_resolved'    => $periodResolvedTotal,
                'net_backlog_change' => $periodSubmittedTotal - $periodResolvedTotal,
                'explanation'        => $activityExplanation,
            ],
            'attention_required'   => [
                'critical_unresolved_count' => $criticalUnresolvedCount,
                'high_unresolved_count'     => $highUnresolvedCount,
                'overdue_count'             => $overdueCount,
                'approaching_count'         => $approachingCount,
                'escalated_count'           => $escalatedCount,
                'items'                     => $attentionItems,
            ],
            'sla_summary'          => [
                'enabled'             => $slaService->isEnabled(),
                'targets_hours'       => (array) config('sla.targets_hours', []),
                'followup_threshold'  => (float) config('sla.followup_threshold', 0.75),
                'overdue_count'       => $overdueCount,
                'approaching_count'   => $approachingCount,
                'followup_sent_count' => $followupSentCount,
            ],
            'insights'             => $insights,
            'sentiment'            => $hasSentimentData ? [
                'positive' => $sentimentBreakdown['positive'] ?? 0,
                'neutral'  => $sentimentBreakdown['neutral'] ?? 0,
                'negative' => $sentimentBreakdown['negative'] ?? 0,
            ] : null,
            'concerns'             => $concerns,
            'resolution_analytics' => $resolutionAnalytics,
            'generated_at'         => now()->toISOString(),
            'filters_applied'      => array_filter($validated),
        ]);
    }

    /**
     * Export the Dean's report as a CSV download.
     */
    public function exportDeanCsv(Request $request)
    {
        $user           = $request->user();
        $academicUnitId = $user->academic_unit_id;

        $validated = $request->validate([
            'date_from'     => 'nullable|date',
            'date_to'       => 'nullable|date|after_or_equal:date_from',
            'status'        => 'nullable|in:pending,in_progress,escalated,approved,rejected,resolved,closed,withdrawn',
            'priority'      => 'nullable|in:critical,high,medium,low',
            'workflow_type' => 'nullable|in:academic,non_academic',
            'category'      => 'nullable|string|max:120',
        ]);

        if (!$academicUnitId) {
            $base = Concern::whereRaw('1 = 0');
        } else {
            $base = Concern::where('academic_unit_id', $academicUnitId)
                ->where(function ($w) {
                    $w->where('workflow_type', 'academic')
                      ->orWhere('category', 'academic_units');
                })
                ->where(function ($inner) {
                    $inner->where('target_type', 'dean')
                          ->orWhere('status', 'escalated')
                          ->orWhereIn('status', ['approved', 'returned', 'rejected', 'resolved']);
                });
        }

        if (!empty($validated['date_from'])) {
            $base->where('submitted_at', '>=', $validated['date_from']);
        }
        if (!empty($validated['date_to'])) {
            $base->where('submitted_at', '<=', $validated['date_to'] . ' 23:59:59');
        }
        if (!empty($validated['status'])) {
            $base->where('status', $validated['status']);
        }
        if (!empty($validated['priority'])) {
            $base->where('priority', $validated['priority']);
        }
        if (!empty($validated['workflow_type'])) {
            $base->where('workflow_type', $validated['workflow_type']);
        }
        if (!empty($validated['category'])) {
            $catFilter = $validated['category'];
            $base->where(function ($q) use ($catFilter) {
                $q->where('issue_category', $catFilter)
                  ->orWhere('category', $catFilter);
            });
        }

        $concerns = $base
            ->with(['student:id,name,student_id_number', 'academicUnit', 'department'])
            ->orderBy('submitted_at', 'desc')
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="dean_report_' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($concerns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Concern ID', 'Title', 'Status', 'Priority', 'Workflow Type',
                'Sentiment', 'Student', 'Department / Unit', 'Submitted At', 'Resolved At',
            ]);
            foreach ($concerns as $c) {
                $studentName = $c->is_anonymous ? 'Anonymous' : ($c->student->name ?? 'N/A');
                $deptName    = $c->department->name ?? $c->academicUnit->name ?? 'N/A';
                fputcsv($handle, [
                    $c->concern_id,
                    $c->title,
                    $c->status,
                    $c->priority ?? 'N/A',
                    $c->workflow_type ?? 'N/A',
                    $c->sentiment_label ?? 'N/A',
                    $studentName,
                    $deptName,
                    $c->submitted_at,
                    $c->resolved_at ?? '',
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
