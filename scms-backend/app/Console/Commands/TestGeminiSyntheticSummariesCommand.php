<?php

namespace App\Console\Commands;

use App\Services\GeminiSentimentService;
use App\Services\LocalSentimentService;
use App\Support\SummaryTextHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Controlled development-only testing procedure for comparing Gemini 3.8 Flash
 * abstractive summarization against the LocalSentimentService fallback using
 * 100% synthetic, fabricated student concerns.
 *
 * Never reads the concerns database table, never sends real student data externally,
 * never exposes API keys, and refuses to run in production.
 */
class TestGeminiSyntheticSummariesCommand extends Command
{
    protected $signature = 'scms:test-gemini-summaries
                            {--status : Inspect sanitized runtime Gemini activation status without making API calls}
                            {--live : Temporarily enable Gemini in-memory for synthetic scenarios only}
                            {--simulated : Use Http::fake() with representative Gemini 3.8 Flash abstractive responses}
                            {--scenario= : Optional scenario key (e.g. personal_crisis, medium_financial, long_safety_facility)}
                            {--json : Output comparison results as JSON}';

    protected $description = 'Inspect Gemini runtime status or run controlled synthetic-only summary comparison (non-production only for test runs)';

    public function handle(GeminiSentimentService $geminiService, LocalSentimentService $localService): int
    {
        if ($this->option('status')) {
            $status = $geminiService->runtimeStatus();
            if ($this->option('json')) {
                $this->line((string) json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return self::SUCCESS;
            }

            $this->info('SCMS Gemini Runtime Activation Status');
            $this->line('Environment        : ' . $status['environment']);
            $this->line('GEMINI_ENABLED Flag: ' . ($status['enabled_flag'] ? 'true' : 'false'));
            $this->line('API Key Configured : ' . ($status['api_key_configured'] ? 'true' : 'false'));
            $this->line('Service Available  : ' . ($status['is_available'] ? 'true' : 'false'));
            $this->line('Active Engine      : ' . $status['active_engine']);
            $this->line('Configured Model   : ' . $status['model']);
            $this->line('Timeout / Retries  : ' . $status['timeout_seconds'] . 's / ' . $status['max_retries']);
            $this->warn($status['external_data_warning']);

            return self::SUCCESS;
        }

        if (app()->environment('production')) {
            $this->error('Aborted: scms:test-gemini-summaries is strictly disabled in production.');
            return self::FAILURE;
        }

        $scenarios = self::syntheticCatalog();
        $filterKey = trim((string) $this->option('scenario'));
        if ($filterKey !== '') {
            if (!isset($scenarios[$filterKey])) {
                $this->error("Unknown synthetic scenario key: {$filterKey}");
                return self::FAILURE;
            }
            $scenarios = [$filterKey => $scenarios[$filterKey]];
        }
        $mode = 'local_only';

        if ($this->option('simulated')) {
            $mode = 'simulated_gemini';
            config([
                'services.gemini.enabled' => true,
                'services.gemini.api_key' => 'synthetic-dev-simulation-key',
            ]);

            $model = trim((string) config('services.gemini.model', 'gemini-3.8-flash')) ?: 'gemini-3.8-flash';
            $sequence = Http::sequence();
            foreach ($scenarios as $scenario) {
                $sequence->push([
                    'candidates' => [[
                        'content' => ['parts' => [[
                            'text' => json_encode($scenario['simulated_gemini_payload']),
                        ]]],
                    ]],
                    'usageMetadata' => ['totalTokenCount' => 240],
                ], 200);
            }

            Http::fake([
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent" => $sequence,
            ]);
        } elseif ($this->option('live')) {
            $mode = 'live_gemini_synthetic_only';
            config([
                'services.gemini.enabled' => true,
                'services.gemini.timeout' => max(45, (int) config('services.gemini.timeout', 20)),
            ]);
        }

        $modelConfigured = (string) config('services.gemini.model', 'gemini-3.8-flash');
        $results = [];

        foreach ($scenarios as $key => $scenario) {
            $title = $scenario['title'];
            $description = $scenario['description'];
            $workflow = $scenario['workflow_type'];
            $origLen = mb_strlen($description);

            $localResult = $localService->analyzeConcern($title, $description, $workflow);
            $localSummary = (string) ($localResult['summary'] ?? '');
            $localLen = mb_strlen($localSummary);

            $geminiResult = null;
            if ($mode !== 'local_only') {
                $geminiResult = $geminiService->analyzeConcern($title, $description, $workflow);
            }

            $geminiSummary = isset($geminiResult['summary']) && is_string($geminiResult['summary'])
                ? trim($geminiResult['summary'])
                : null;

            $isGenuineGemini = $geminiSummary !== null
                && $geminiSummary !== ''
                && !SummaryTextHelper::isVerbatimOrNearCopy($geminiSummary, $description);

            $effectiveSummary = $isGenuineGemini ? $geminiSummary : $localSummary;
            $effectiveSource = $isGenuineGemini ? 'Gemini AI' : 'Rule-based Engine';
            $geminiLen = $isGenuineGemini ? mb_strlen((string) $geminiSummary) : null;

            $results[] = [
                'scenario_key'               => $key,
                'category_type'              => $scenario['category_type'],
                'title'                      => $title,
                'original_chars'             => $origLen,
                'original_description'       => $description,
                'gemini_summary'             => $isGenuineGemini ? $geminiSummary : null,
                'gemini_chars'               => $geminiLen,
                'gemini_reduction_pct'       => $geminiLen !== null ? round((1 - ($geminiLen / max(1, $origLen))) * 100, 1) : null,
                'local_summary'              => $localSummary,
                'local_chars'                => $localLen,
                'local_reduction_pct'        => round((1 - ($localLen / max(1, $origLen))) * 100, 1),
                'effective_source_badge'     => $effectiveSource,
                'effective_summary'          => $effectiveSummary,
                'sentiment_label'            => $geminiResult['sentiment_label'] ?? $localResult['sentiment_label'],
                'priority_suggestion'        => $geminiResult['priority_suggestion'] ?? $localResult['priority_suggestion'],
                'issue_category'             => $geminiResult['issue_category'] ?? $localResult['issue_category'],
                'issue_subcategory'          => $geminiResult['issue_subcategory'] ?? $localResult['issue_subcategory'],
                'tokens_recorded'            => $isGenuineGemini ? (int) ($geminiResult['api_tokens_used'] ?? 0) : 0,
            ];
        }

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'mode'             => $mode,
                'configured_model' => $modelConfigured,
                'results'          => $results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info("SCMS Controlled Synthetic Summary Benchmark (mode={$mode}, model={$modelConfigured})");
        foreach ($results as $r) {
            $this->newLine();
            $this->line("=== [{$r['scenario_key']}] {$r['category_type']} ({$r['original_chars']} chars) ===");
            $this->line("Active Source Badge : {$r['effective_source_badge']} (tokens={$r['tokens_recorded']})");
            $this->line("Sentiment / Priority: {$r['sentiment_label']} / {$r['priority_suggestion']}");
            if ($r['gemini_summary'] !== null) {
                $this->line("Gemini Summary ({$r['gemini_chars']} chars, -{$r['gemini_reduction_pct']}%): {$r['gemini_summary']}");
            } else {
                $this->line('Gemini Summary      : [Unavailable or skipped — fell back to Rule-based Engine]');
            }
            $this->line("Local Summary  ({$r['local_chars']} chars, -{$r['local_reduction_pct']}%): {$r['local_summary']}");
        }

        return self::SUCCESS;
    }

    /**
     * 100% synthetic, fabricated concern catalog covering all required test categories.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function syntheticCatalog(): array
    {
        return [
            'short_facility' => [
                'category_type' => 'Short / Facility',
                'title'         => '[SYNTHETIC] Broken Ceiling Fan in Room 204',
                'workflow_type' => 'non_academic',
                'description'   => 'The ceiling fan in Room 204 stopped working this morning. The classroom is very hot, making it difficult for students to concentrate. We are requesting that the fan be inspected and repaired.',
                'simulated_gemini_payload' => [
                    'sentiment_label'            => 'negative',
                    'sentiment_score'            => -0.45,
                    'issue_category'             => 'Non-Academic Concerns',
                    'issue_subcategory'          => 'Maintenance',
                    'classification_explanation' => 'Classroom ventilation fixture failure requiring facility maintenance.',
                    'classification_confidence'  => 0.94,
                    'priority_suggestion'        => 'medium',
                    'priority_explanation'       => 'Localized classroom comfort issue affecting concentration in Room 204.',
                    'summary'                    => 'The student reports a non-functioning ceiling fan in Room 204 that has left the classroom uncomfortably hot and disrupted student concentration. They request an inspection and repair of the fan.',
                ],
            ],
            'personal_crisis' => [
                'category_type' => 'Medium / Personal & Family Crisis',
                'title'         => '[SYNTHETIC] Request for Urgent Guidance Counseling Endorsement',
                'workflow_type' => 'non_academic',
                'description'   => 'I am dealing with an acute family and personal crisis that has severely impacted my attendance and exam performance at UCLM this week. Because my academic standing and subject retention are at risk before the upcoming grading cutoff, I need a consultation and counseling endorsement from the UCLM Guidance Center today.',
                'simulated_gemini_payload' => [
                    'sentiment_label'            => 'negative',
                    'sentiment_score'            => -0.65,
                    'issue_category'             => 'Non-Academic Concerns',
                    'issue_subcategory'          => 'Student Affairs',
                    'classification_explanation' => 'Student experiencing a personal and family crisis seeking Guidance Center counseling support.',
                    'classification_confidence'  => 0.95,
                    'priority_suggestion'        => 'high',
                    'priority_explanation'       => 'Time-sensitive student well-being and academic retention risk ahead of the grading cutoff.',
                    'summary'                    => 'The student reports a family and personal crisis that is affecting attendance and academic performance. They urgently request counseling assistance from the Guidance Center to address potential academic consequences before the grading deadline.',
                ],
            ],
            'medium_financial' => [
                'category_type' => 'Medium / Financial & Cashier',
                'title'         => '[SYNTHETIC] Delayed Official Receipt for Tuition Payment',
                'workflow_type' => 'non_academic',
                'description'   => 'I paid my tuition fee of 8,500 pesos on November 18, 2026, at Cashier Window 2. I submitted my payment confirmation and deposit slip, but I still have not received my official receipt. The examination permit deadline is Friday, and I am worried that I will not be allowed to take my examination without the receipt. I am requesting assistance in verifying my payment and issuing the official receipt.',
                'simulated_gemini_payload' => [
                    'sentiment_label'            => 'neutral',
                    'sentiment_score'            => -0.15,
                    'issue_category'             => 'Non-Academic Concerns',
                    'issue_subcategory'          => 'Cashier / Payment',
                    'classification_explanation' => 'Tuition payment verification and official receipt issuance inquiry.',
                    'classification_confidence'  => 0.96,
                    'priority_suggestion'        => 'high',
                    'priority_explanation'       => 'Unissued receipt risks blocking the student from meeting Friday\'s examination permit deadline.',
                    'summary'                    => 'The student reports not receiving an official receipt after paying 8,500 pesos in tuition at Cashier Window 2 on November 18 and submitting proof of payment. They request payment verification and receipt issuance so they are not prevented from taking their exams after Friday\'s permit deadline.',
                ],
            ],
            'long_safety_facility' => [
                'category_type' => 'Long Multi-Paragraph / Safety & Facility',
                'title'         => '[SYNTHETIC] Computer Laboratory Cooling Failure and Water Leak',
                'workflow_type' => 'non_academic',
                'description'   => "Since November 16, 2026, the air-conditioning unit at the back of Computer Laboratory Room 303 has been leaking water continuously. The laboratory also becomes increasingly hot because the cooling system is not functioning properly.\n\nOn November 20, students noticed that water was spreading near workstations PC-12 through PC-15. By November 22, the floor was slippery and students raised concerns about possible electrical hazards. Four workstations were temporarily unplugged as a precaution.\n\nApproximately 40 students use this laboratory for their scheduled practical activities. The upcoming examination is on November 25, 2026, and the continuing problem is affecting our preparation and laboratory sessions.\n\nWe are requesting an inspection of the air-conditioning unit, electrical safety assessment, and repairs before the November 25 examination.",
                'simulated_gemini_payload' => [
                    'sentiment_label'            => 'neutral',
                    'sentiment_score'            => -0.20,
                    'issue_category'             => 'Non-Academic Concerns',
                    'issue_subcategory'          => 'Maintenance',
                    'classification_explanation' => 'Laboratory air-conditioning water leak creating slip and possible electrical hazards.',
                    'classification_confidence'  => 0.97,
                    'priority_suggestion'        => 'critical',
                    'priority_explanation'       => 'Water pooling near computer workstations presents possible electrical and slip hazards affecting 40 students.',
                    'summary'                    => 'The student reports a persistent air-conditioning water leak and cooling failure in Computer Laboratory Room 303 since November 16 that spread near workstations PC-12 through PC-15 by November 20. By November 22, slippery floors and possible electrical hazards required unplugging four workstations, disrupting lab preparation for about 40 students ahead of a November 25 exam. They request an HVAC inspection, electrical safety assessment, and repairs before November 25.',
                ],
            ],
            'long_academic' => [
                'category_type' => 'Very Long Multi-Paragraph / Academic Grade Discrepancy',
                'title'         => '[SYNTHETIC] IT-302 Grade Encoding Discrepancy',
                'workflow_type' => 'academic',
                'description'   => "I am requesting assistance regarding an incorrect final grade for IT-302 Software Engineering during the First Semester of AY 2026-2027. I completed the course requirements and received a final grade of 91.4% on October 14, 2026.\n\nHowever, on October 28, 2026, the student portal showed INC instead of my expected final grade. I immediately contacted the instructor regarding the discrepancy.\n\nOn November 10, 2026, the instructor verbally acknowledged that a row may have been skipped during the encoding process. I was advised to wait for further checking, but the portal still shows INC.\n\nThis issue is now affecting my eligibility for scholarship renewal under the fictional CHED UniFAST test scenario. The scholarship documentation deadline is November 28, 2026.\n\nI need the grade discrepancy reviewed and corrected before November 27, 2026, so I can submit the necessary academic documents on time. I am requesting a formal review of the grade records and completion of the required Change of Grade process.",
                'simulated_gemini_payload' => [
                    'sentiment_label'            => 'neutral',
                    'sentiment_score'            => -0.10,
                    'issue_category'             => 'Academic Concerns',
                    'issue_subcategory'          => 'Assessment and Examination',
                    'classification_explanation' => 'Encoded portal grade discrepancy requiring Change of Grade verification.',
                    'classification_confidence'  => 0.96,
                    'priority_suggestion'        => 'high',
                    'priority_explanation'       => 'Unresolved INC mark threatens the student\'s November 28 scholarship renewal deadline.',
                    'summary'                    => 'The student reports an incorrect INC grade posted on October 28 for IT-302 Software Engineering despite earning 91.4% on October 14. The instructor acknowledged a possible encoding omission on November 10, and the unresolved mark threatens scholarship renewal before the November 28 deadline. The student requests a formal grade review and Change of Grade completion by November 27.',
                ],
            ],
        ];
    }
}
