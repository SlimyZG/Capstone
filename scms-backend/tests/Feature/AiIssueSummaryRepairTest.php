<?php

namespace Tests\Feature;

use App\Jobs\GenerateResolutionSummary;
use App\Models\AcademicUnit;
use App\Models\Concern;
use App\Models\Department;
use App\Models\User;
use App\Services\GeminiSentimentService;
use App\Services\LocalSentimentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AiIssueSummaryRepairTest extends TestCase
{
    use RefreshDatabase;

    private AcademicUnit $unitCcs;
    private Department $deptCashier;
    private Department $deptMaintenance;
    private Department $deptSao;
    private User $student;
    private User $cashierStaff;
    private User $dean;
    private User $saoUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitCcs = AcademicUnit::create(['name' => 'College of Computer Studies', 'code' => 'CCS']);
        $this->deptCashier = Department::create(['name' => 'Cashier', 'code' => 'CASHIER']);
        $this->deptMaintenance = Department::create(['name' => 'Building Administration and Maintenance', 'code' => 'BAM']);
        $this->deptSao = Department::create(['name' => 'Student Affairs Office (SAO)', 'code' => 'SAO']);

        $this->student = User::create([
            'name'              => 'Synthetic Audit Student',
            'email'             => 'synth.audit.student@test.local',
            'student_id_number' => '20261111',
            'password'          => Hash::make('password123'),
            'role'              => 'student',
            'academic_unit_id'  => $this->unitCcs->id,
        ]);

        $this->cashierStaff = User::create([
            'name'      => 'Synthetic Cashier Staff',
            'email'     => 'synth.cashier.staff@test.local',
            'password'  => Hash::make('password123'),
            'role'      => 'dept_staff',
            'dept_id'   => $this->deptCashier->id,
            'is_active' => true,
        ]);

        $this->dean = User::create([
            'name'             => 'Synthetic CCS Dean',
            'email'            => 'synth.ccs.dean@test.local',
            'password'         => Hash::make('password123'),
            'role'             => 'dean',
            'academic_unit_id' => $this->unitCcs->id,
            'is_active'        => true,
        ]);

        $this->saoUser = User::create([
            'name'      => 'Synthetic SAO Officer',
            'email'     => 'synth.sao.officer@test.local',
            'password'  => Hash::make('password123'),
            'role'      => 'sao',
            'dept_id'   => $this->deptSao->id,
            'is_active' => true,
        ]);
    }

    /**
     * Synthetic A-D scenarios (100% fabricated inputs, never real student data).
     *
     * @return array<string, array{title: string, description: string, workflow_type: string, dept_id: ?int, gemini_summary: string, expected_terms: list<string>, forbidden_terms: list<string>}>
     */
    private function syntheticScenarios(): array
    {
        return [
            'A_short' => [
                'title'          => 'Broken ceiling fan in Room 204',
                'description'    => 'The ceiling fan in Room 204 stopped working this morning and the room is very hot.',
                'workflow_type'  => 'non_academic',
                'dept_id'        => $this->deptMaintenance->id,
                'gemini_summary' => 'The ceiling fan in Room 204 stopped working this morning, leaving the classroom uncomfortably warm. Maintenance repair is needed for the affected room.',
                'expected_terms' => ['ceiling fan', 'Room 204', 'stopped working'],
                'forbidden_terms' => ['LMS', 'quiz', 'scholarship'],
            ],
            'B_medium_tuition_receipt' => [
                'title'           => 'Delayed Official Receipt for Tuition Installment',
                'description'     => 'Good morning. On November 18, 2026, I paid 8,500 pesos at Cashier Window 2 and submitted my bank deposit slip. Six days later my ledger still shows unpaid, blocking my Friday examination permit. Please verify the payment and issue my official receipt before Friday.',
                'workflow_type'   => 'non_academic',
                'dept_id'         => $this->deptCashier->id,
                'gemini_summary'  => 'After paying 8,500 pesos at Cashier Window 2 and submitting a deposit slip on November 18, 2026, the student ledger remains marked unpaid. This delay is blocking the Friday examination permit, and receipt verification is requested before Friday.',
                'expected_terms'  => ['November 18', '8,500 pesos', 'Cashier Window 2', 'examination permit', 'official receipt'],
                'forbidden_terms' => ['LMS', 'Canvas', 'quiz', 'auto-submitted', 'Academic assessment disruption'],
            ],
            'C_long_multiparagraph_lab_leak' => [
                'title'          => 'Repeated Air Conditioning Breakdown and Water Leakage in Lab 303',
                'description'    => "Good afternoon to the Building Administration Office.\n\nSince Monday, November 16, 2026, the rear air conditioning unit in Computer Laboratory Room 303 has been leaking water near Workstations PC-12 through PC-15. By Wednesday afternoon cooling stopped completely during our 40-student programming session.\n\nOn November 22, 2026, two students nearly slipped on the pooled water and four workstations had to be unplugged to prevent electrical short-circuit hazards. Because those computers are unusable, eight students must share terminals.\n\nPlease inspect and repair the drainage line, restore cooling, and certify electrical safety around PC-12 to PC-15 before our practical examination on November 25, 2026.",
                'workflow_type'  => 'non_academic',
                'dept_id'        => $this->deptMaintenance->id,
                'gemini_summary' => 'Since November 16, 2026, an air conditioner leak in Computer Laboratory Room 303 spread water near Workstations PC-12 through PC-15, and cooling later stopped for 40 students. On November 22, two students nearly slipped and four workstations were unplugged for electrical safety, forcing students to share terminals. Repair of the drainage line and safety certification are requested before the November 25 practical examination.',
                'expected_terms' => ['November 16', 'Room 303', 'PC-12', 'November 22', 'electrical', 'November 25'],
                'forbidden_terms' => ['CCS:', 'Good afternoon'],
            ],
            'D_very_long_grade_discrepancy' => [
                'title'          => 'Unresolved Grade Encoding Discrepancy Affecting Scholarship Renewal',
                'description'    => "Dear College of Computer Studies Chairperson and Dean,\n\nI am writing to formally request a review of my IT-302 Software Engineering final grade under Instructor Cruz in Room 405. On October 14, 2026, our class submitted the capstone defense and written exam, where my documented raw score totaled 91.4% (equivalent to 1.4).\n\nWhen grades were released on October 28, 2026, my portal record displayed INC without notice. I emailed the instructor on November 2 and visited the faculty room on November 10, 2026, when Instructor Cruz verbally confirmed my row was skipped during encoding.\n\nNo grade correction has reached the Registrar yet. The Scholarship Office warned that my CHED UniFAST renewal will be forfeited unless the INC is cleared by November 28, 2026.\n\nPlease verify my class record with Instructor Cruz, file a Change of Grade before November 27, 2026, and issue a temporary certification for the Scholarship Office. Thank you.",
                'workflow_type'  => 'academic',
                'dept_id'        => null,
                'gemini_summary' => 'For IT-302 Software Engineering under Instructor Cruz, the student earned 91.4% (1.4) on October 14, 2026, yet received an INC mark when grades posted on October 28. During follow-up on November 10, the instructor confirmed the student record was skipped during batch encoding, while CHED UniFAST scholarship renewal faces forfeiture on November 28. Verification and a Change of Grade filing before November 27 are requested.',
                'expected_terms' => ['October 14', '91.4%', 'October 28', 'INC', 'November 10', 'November 28', 'Change of Grade', 'November 27'],
                'forbidden_terms' => ['Dear College of Computer Studies', 'Thank you.'],
            ],
        ];
    }

    public function test_scenarios_a_through_d_local_fallback_preserves_complete_facts_without_truncation(): void
    {
        $maxLen = (int) config('classification.max_summary_length', 800);

        foreach ($this->syntheticScenarios() as $name => $scenario) {
            $payload = [
                'title'         => $scenario['title'],
                'description'   => $scenario['description'],
                'workflow_type' => $scenario['workflow_type'],
            ];
            if ($scenario['dept_id'] !== null) {
                $payload['dept_id'] = $scenario['dept_id'];
            }

            $response = $this->actingAs($this->student, 'sanctum')
                ->postJson('/api/concerns', $payload);

            $response->assertStatus(201);
            $concernData = $response->json('concern');
            $summary = (string) ($concernData['ai_summary'] ?? '');

            $this->assertNotEmpty($summary, "Scenario {$name} produced empty local summary.");
            $this->assertLessThanOrEqual($maxLen, mb_strlen($summary), "Scenario {$name} exceeded budget.");
            $this->assertMatchesRegularExpression('/[.?!]$/u', $summary, "Scenario {$name} did not end cleanly.");
            $this->assertStringNotContainsString('...', $summary, "Scenario {$name} contained mid-word ellipsis.");
            $this->assertStringNotContainsString('…', $summary, "Scenario {$name} contained unicode ellipsis.");

            // Original description must remain completely unmodified in DB
            $dbConcern = Concern::findOrFail($concernData['concern_id']);
            $this->assertSame($scenario['description'], $dbConcern->description);

            // Accurate source metadata for local fallback
            $this->assertSame('local_fallback', $concernData['ai_metadata']['source']);
            $this->assertSame('Rule-based Engine', $concernData['ai_metadata']['label']);
            $this->assertTrue($concernData['ai_metadata']['is_fallback']);

            foreach ($scenario['expected_terms'] as $term) {
                $this->assertStringContainsString(
                    strtolower($term),
                    strtolower($summary),
                    "Scenario {$name} local summary omitted expected detail '{$term}'. Actual: {$summary}"
                );
            }

            foreach ($scenario['forbidden_terms'] as $forbidden) {
                $this->assertStringNotContainsString(
                    strtolower($forbidden),
                    strtolower($summary),
                    "Scenario {$name} local summary contained forbidden/hallucinated term '{$forbidden}'. Actual: {$summary}"
                );
            }
        }
    }

    public function test_scenarios_a_through_d_mocked_gemini_and_consistent_role_retrieval(): void
    {
        $maxLen = (int) config('classification.max_summary_length', 800);
        config([
            'services.gemini.enabled' => true,
            'services.gemini.api_key' => 'test-synthetic-gemini-key',
            'services.gemini.model'   => 'gemini-3.8-flash',
        ]);

        $scenarios = $this->syntheticScenarios();

        $sequence = Http::sequence();
        foreach ($scenarios as $scenario) {
            $sequence->push([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'sentiment_label'            => 'neutral',
                            'sentiment_score'            => 0.0,
                            'issue_category'             => $scenario['workflow_type'] === 'academic' ? 'Academic Concerns' : 'Non-Academic Concerns',
                            'issue_subcategory'          => $scenario['workflow_type'] === 'academic' ? 'Instructor / Teaching Concerns' : 'Maintenance',
                            'classification_explanation' => 'Synthetic mocked classification.',
                            'classification_confidence'  => 0.92,
                            'priority_suggestion'        => 'medium',
                            'priority_explanation'       => 'Synthetic mocked priority.',
                            'summary'                    => $scenario['gemini_summary'],
                        ]),
                    ]]],
                ]],
                'usageMetadata' => ['totalTokenCount' => 310],
            ], 200);
        }

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent' => $sequence,
        ]);

        foreach ($scenarios as $name => $scenario) {

            $payload = [
                'title'         => $scenario['title'],
                'description'   => $scenario['description'],
                'workflow_type' => $scenario['workflow_type'],
            ];
            if ($scenario['dept_id'] !== null) {
                $payload['dept_id'] = $scenario['dept_id'];
            }

            $response = $this->actingAs($this->student, 'sanctum')
                ->postJson('/api/concerns', $payload);

            $response->assertStatus(201);
            $concernId = (int) $response->json('concern.concern_id');
            $summary = (string) $response->json('concern.ai_summary');

            $this->assertSame($scenario['gemini_summary'], $summary, "Scenario {$name} mismatch.");
            $this->assertLessThanOrEqual($maxLen, mb_strlen($summary));
            $this->assertMatchesRegularExpression('/[.?!]$/u', $summary);
            $this->assertStringNotContainsString('...', $summary);
            $this->assertSame('gemini', $response->json('concern.ai_metadata.source'));
            $this->assertSame('Gemini AI', $response->json('concern.ai_metadata.label'));
            $this->assertFalse($response->json('concern.ai_metadata.is_fallback'));

            // Verify authorized role detail endpoint returns exact same full ai_summary
            if ($scenario['workflow_type'] === 'non_academic' && $scenario['dept_id'] === $this->deptCashier->id) {
                $staffShow = $this->actingAs($this->cashierStaff, 'sanctum')
                    ->getJson("/api/dept-staff/concerns/{$concernId}");
                $staffShow->assertOk();
                $staffShow->assertJsonPath('concern.ai_summary', $summary);
                $staffShow->assertJsonPath('concern.ai_metadata.source', 'gemini');
            } elseif ($scenario['workflow_type'] === 'academic') {
                $concernModel = Concern::findOrFail($concernId);
                $concernModel->update(['status' => 'escalated']);
                $deanShow = $this->actingAs($this->dean, 'sanctum')
                    ->getJson("/api/dean/concerns/{$concernId}");
                $deanShow->assertOk();
                $deanShow->assertJsonPath('concern.ai_summary', $summary);
                $deanShow->assertJsonPath('concern.ai_metadata.source', 'gemini');
            }
        }
    }

    public function test_gemini_http_429_does_not_retry_aggressively_and_falls_back_with_sanitized_logs(): void
    {
        config([
            'services.gemini.enabled'     => true,
            'services.gemini.api_key'     => 'secret-gemini-api-key-999',
            'services.gemini.model'       => 'gemini-3.8-flash',
            'services.gemini.max_retries' => 2,
        ]);

        Log::spy();

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code'    => 429,
                    'status'  => 'RESOURCE_EXHAUSTED',
                    'message' => 'Quota exceeded',
                ],
            ], 429),
        ]);

        $scenario = $this->syntheticScenarios()['B_medium_tuition_receipt'];

        $response = $this->actingAs($this->student, 'sanctum')->postJson('/api/concerns', [
            'title'         => $scenario['title'],
            'description'   => $scenario['description'],
            'workflow_type' => 'non_academic',
            'dept_id'       => $this->deptCashier->id,
        ]);

        $response->assertStatus(201);
        $concernId = (int) $response->json('concern.concern_id');

        // Bounded retry contract: HTTP 429 must NOT be retried
        Http::assertSentCount(1);

        $response->assertJsonPath('concern.ai_status', 'complete');
        $response->assertJsonPath('concern.ai_metadata.source', 'local_fallback');
        $response->assertJsonPath('concern.ai_metadata.label', 'Rule-based Engine');
        $response->assertJsonPath('concern.ai_metadata.is_fallback', true);
        $this->assertNotEmpty($response->json('concern.ai_summary'));

        $latestLog = DB::table('sentiment_logs')
            ->where('concern_id', $concernId)
            ->orderByDesc('id')
            ->first();
        $this->assertSame(0, (int) $latestLog->api_tokens_used);

        Log::shouldHaveReceived('warning')
            ->atLeast()
            ->once()
            ->withArgs(function (string $message, array $context = []) use ($scenario) {
                if (!str_contains($message, 'analyzeConcern')) {
                    return false;
                }
                $serialized = (string) json_encode($context);
                $this->assertSame(429, $context['http_status'] ?? null);
                $this->assertSame('RESOURCE_EXHAUSTED', $context['provider_status'] ?? null);
                $this->assertStringNotContainsString('secret-gemini-api-key-999', $serialized);
                $this->assertStringNotContainsString($scenario['description'], $serialized);
                return true;
            });
    }

    public function test_gemini_transient_503_retries_bounded_once_and_recovers(): void
    {
        config([
            'services.gemini.enabled'     => true,
            'services.gemini.api_key'     => 'test-synthetic-gemini-key',
            'services.gemini.model'       => 'gemini-3.8-flash',
            'services.gemini.max_retries' => 1,
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['code' => 503, 'status' => 'UNAVAILABLE']], 503)
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [[
                            'text' => json_encode([
                                'sentiment_label'            => 'neutral',
                                'sentiment_score'            => 0.0,
                                'issue_category'             => 'Non-Academic Concerns',
                                'issue_subcategory'          => 'Maintenance',
                                'classification_explanation' => 'Maintenance issue.',
                                'classification_confidence'  => 0.9,
                                'priority_suggestion'        => 'medium',
                                'priority_explanation'       => 'Localized fixture issue.',
                                'summary'                    => 'Ceiling fan in Room 204 stopped functioning, causing heat discomfort. Maintenance repair requested.',
                            ]),
                        ]]],
                    ]],
                    'usageMetadata' => ['totalTokenCount' => 120],
                ], 200),
        ]);

        $response = $this->actingAs($this->student, 'sanctum')->postJson('/api/concerns', [
            'title'         => 'Broken ceiling fan in Room 204',
            'description'   => 'The ceiling fan in Room 204 stopped working this morning and the room is very hot.',
            'workflow_type' => 'non_academic',
            'dept_id'       => $this->deptMaintenance->id,
        ]);

        $response->assertStatus(201);
        Http::assertSentCount(2);
        $response->assertJsonPath('concern.ai_metadata.source', 'gemini');
        $response->assertJsonPath('concern.ai_metadata.label', 'Gemini AI');
    }

    public function test_missing_api_key_or_disabled_flag_skips_external_requests_and_uses_local_fallback(): void
    {
        // Case 1: Blank API key with enabled = true
        config([
            'services.gemini.enabled' => true,
            'services.gemini.api_key' => '',
        ]);

        $res1 = $this->actingAs($this->student, 'sanctum')->postJson('/api/concerns', [
            'title'         => 'Broken door lock in Room 204',
            'description'   => 'The classroom door lock in Room 204 is jammed and cannot close properly.',
            'workflow_type' => 'non_academic',
            'dept_id'       => $this->deptMaintenance->id,
        ]);
        $res1->assertStatus(201);
        $res1->assertJsonPath('concern.ai_metadata.source', 'local_fallback');

        // Case 2: Production privacy gate (enabled = false even if key is set)
        config([
            'services.gemini.enabled' => false,
            'services.gemini.api_key' => 'present-but-disabled-key',
        ]);

        $res2 = $this->actingAs($this->student, 'sanctum')->postJson('/api/concerns', [
            'title'         => 'Window latch broken in Room 205',
            'description'   => 'Window latch in Room 205 broke during wind storm; please inspect.',
            'workflow_type' => 'non_academic',
            'dept_id'       => $this->deptMaintenance->id,
        ]);
        $res2->assertStatus(201);
        $res2->assertJsonPath('concern.ai_metadata.source', 'local_fallback');

        Http::assertNothingSent();
    }

    public function test_malformed_gemini_json_and_over_budget_summary_are_handled_safely(): void
    {
        config([
            'services.gemini.enabled' => true,
            'services.gemini.api_key' => 'test-synthetic-gemini-key',
            'services.gemini.model'   => 'gemini-3.8-flash',
        ]);

        // 1. Malformed JSON response -> safe fallback to Rule-based Engine
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => '{broken json response']]],
                ]],
            ], 200),
        ]);

        $res = $this->actingAs($this->student, 'sanctum')->postJson('/api/concerns', [
            'title'         => 'Cashier queue ticket printer offline',
            'description'   => 'The ticket numbering printer at the Cashier lobby stopped printing this morning, delaying tuition queues.',
            'workflow_type' => 'non_academic',
            'dept_id'       => $this->deptCashier->id,
        ]);
        $res->assertStatus(201);
        $res->assertJsonPath('concern.ai_metadata.source', 'local_fallback');
        $this->assertNotEmpty($res->json('concern.ai_summary'));

        // 2. Over-budget summary from Gemini (> 800 chars, 5 complete sentences) -> condensed on sentence boundaries
        $service = app(GeminiSentimentService::class);
        $reflection = new \ReflectionClass($service);
        $parse = $reflection->getMethod('parseResponse');
        $parse->setAccessible(true);

        $fiveLongSentences = implode(' ', [
            'Sentence one describes a recurring water leak inside Computer Laboratory Room 303 that began on Monday November 16 near student desks and hardware outlets.',
            'Sentence two notes that laboratory cooling stopped completely on Wednesday afternoon while forty programming students attended a graded three-hour laboratory session.',
            'Sentence three explains that two students nearly slipped on November 22 and four workstations had to be disconnected immediately for electrical short-circuit safety.',
            'Sentence four highlights that eight learners must share the remaining operational machines ahead of their scheduled practical examination on November 25.',
            'Sentence five adds an extensive closing administrative summary paragraph pushing the full combined text comfortably above the eight-hundred character ceiling so sentence-boundary condensation is strictly exercised.',
        ]);
        $this->assertGreaterThan(800, mb_strlen($fiveLongSentences));

        $parsed = $parse->invoke($service, [
            'candidates' => [[
                'content' => ['parts' => [[
                    'text' => json_encode([
                        'sentiment_label'     => 'neutral',
                        'sentiment_score'     => 0.0,
                        'priority_suggestion' => 'high',
                        'summary'             => $fiveLongSentences,
                    ]),
                ]]],
            ]],
            'usageMetadata' => ['totalTokenCount' => 250],
        ], 'non_academic');

        $this->assertNotNull($parsed);
        $this->assertLessThanOrEqual(800, mb_strlen($parsed['summary']));
        $this->assertMatchesRegularExpression('/[.?!]$/u', $parsed['summary']);
        $this->assertStringNotContainsString('...', $parsed['summary']);
    }

    public function test_neutral_sentiment_and_manual_priority_override_preserve_summary_and_override(): void
    {
        $res = $this->actingAs($this->student, 'sanctum')->postJson('/api/concerns', [
            'title'         => 'Request for schedule clarification on laboratory room swap',
            'description'   => 'Could you please confirm whether IT-201 moved from Room 301 to Room 305 starting next Monday? We need confirmation before Monday morning class.',
            'workflow_type' => 'academic',
        ]);
        $res->assertStatus(201);
        $res->assertJsonPath('concern.sentiment_label', 'neutral');
        $this->assertNotEmpty($res->json('concern.ai_summary'));

        $concernId = (int) $res->json('concern.concern_id');
        Concern::where('concern_id', $concernId)->update([
            'priority'               => 'critical',
            'is_priority_overridden' => true,
        ]);

        // Editing concern regenerates summary without overwriting manual priority override
        $editRes = $this->actingAs($this->student, 'sanctum')->putJson("/api/concerns/{$concernId}", [
            'title'         => 'Schedule confirmation for IT-201 Room 305 transfer',
            'description'   => 'Updated inquiry: Please confirm whether IT-201 Section 2B moved to Room 305 for Monday and Wednesday morning sessions.',
            'workflow_type' => 'academic',
        ]);
        $editRes->assertOk();

        $fresh = Concern::findOrFail($concernId);
        $this->assertSame('critical', $fresh->priority);
        $this->assertTrue((bool) $fresh->is_priority_overridden);
        $this->assertStringContainsString('Room 305', (string) $fresh->ai_summary);
    }

    public function test_student_privacy_hides_internal_ai_explanations_across_all_student_endpoints(): void
    {
        // 1. Store endpoint
        $storeRes = $this->actingAs($this->student, 'sanctum')->postJson('/api/concerns', [
            'title'         => 'Cashier queue receipt validation request',
            'description'   => 'Paid tuition at Cashier Window 1 yesterday and need official receipt validation on my portal ledger.',
            'workflow_type' => 'non_academic',
            'dept_id'       => $this->deptCashier->id,
        ]);
        $storeRes->assertStatus(201);
        $storedConcern = $storeRes->json('concern');
        $concernId = (int) $storedConcern['concern_id'];

        $this->assertStudentPrivacyFieldsHidden($storedConcern);

        // 2. Index endpoint
        $indexRes = $this->actingAs($this->student, 'sanctum')->getJson('/api/concerns');
        $indexRes->assertOk();
        $indexItem = collect($indexRes->json('concerns'))->firstWhere('concern_id', $concernId);
        $this->assertNotNull($indexItem);
        $this->assertStudentPrivacyFieldsHidden($indexItem);

        // 3. Show endpoint
        $showRes = $this->actingAs($this->student, 'sanctum')->getJson("/api/concerns/{$concernId}");
        $showRes->assertOk();
        $this->assertStudentPrivacyFieldsHidden($showRes->json('concern'));

        // 4. Update endpoint
        $updateRes = $this->actingAs($this->student, 'sanctum')->putJson("/api/concerns/{$concernId}", [
            'title'         => 'Updated Cashier receipt validation request',
            'description'   => 'Paid tuition at Cashier Window 1 yesterday and still waiting for official receipt posting on student ledger.',
            'workflow_type' => 'non_academic',
            'dept_id'       => $this->deptCashier->id,
        ]);
        $updateRes->assertOk();
        $this->assertStudentPrivacyFieldsHidden($updateRes->json('concern'));

        // 5. Withdraw & Reopen endpoints
        $withdrawRes = $this->actingAs($this->student, 'sanctum')->postJson("/api/concerns/{$concernId}/withdraw");
        $withdrawRes->assertOk();
        $this->assertStudentPrivacyFieldsHidden($withdrawRes->json('concern'));

        $reopenRes = $this->actingAs($this->student, 'sanctum')->postJson("/api/concerns/{$concernId}/reopen");
        $reopenRes->assertOk();
        $this->assertStudentPrivacyFieldsHidden($reopenRes->json('concern'));

        // Authorized staff still sees internal explanations & token metadata
        $staffShow = $this->actingAs($this->cashierStaff, 'sanctum')->getJson("/api/dept-staff/concerns/{$concernId}");
        $staffShow->assertOk();
        $this->assertArrayHasKey('ai_priority_explanation', $staffShow->json('concern'));
        $this->assertArrayHasKey('classification_explanation', $staffShow->json('concern'));
        $this->assertArrayHasKey('tokens_used', $staffShow->json('concern.ai_metadata'));
    }

    public function test_phpunit_environment_has_blank_ai_keys_and_makes_zero_live_external_requests(): void
    {
        $this->assertSame('', (string) config('services.gemini.api_key'));
        $this->assertSame('', (string) config('services.anthropic.api_key'));
        $this->assertSame('gemini-3.8-flash', (string) config('services.gemini.model'));

        // Http::preventStrayRequests() is enabled in TestCase::setUp() with NO Http::fake() registered.
        // Any stray external HTTP attempt across submission, routing recommendation, analytics trends,
        // or resolution summary generation will immediately throw an exception and fail this test.
        $this->actingAs($this->student, 'sanctum')
            ->postJson('/api/concerns/recommend-routing', [
                'title'       => 'Cashier payment check',
                'description' => 'Official receipt validation inquiry for tuition balance.',
            ])->assertOk();

        $concernRes = $this->actingAs($this->student, 'sanctum')
            ->postJson('/api/concerns', [
                'title'         => 'Tuition receipt check',
                'description'   => 'Paid tuition at Cashier counter yesterday; requesting receipt confirmation.',
                'workflow_type' => 'non_academic',
                'dept_id'       => $this->deptCashier->id,
            ])->assertStatus(201);

        $this->actingAs($this->dean, 'sanctum')
            ->getJson('/api/analytics/trends')
            ->assertOk()
            ->assertJsonPath('ai_provider', 'none');

        $concernModel = Concern::findOrFail((int) $concernRes->json('concern.concern_id'));
        $job = new GenerateResolutionSummary($concernModel);
        $job->handle(app(\App\Services\AnthropicService::class));
        $this->assertNull($concernModel->fresh()->resolution_summary);
    }

    public function test_gemini_empty_responses_safely_fall_back_to_local_engine(): void
    {
        config([
            'services.gemini.enabled'     => true,
            'services.gemini.api_key'     => 'secret-empty-test-key',
            'services.gemini.model'       => 'gemini-3.8-flash',
            'services.gemini.max_retries' => 1,
        ]);

        Log::spy();

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::sequence()
                ->push('', 200)
                ->push(['candidates' => []], 200),
        ]);

        $resEmptyBody = $this->actingAs($this->student, 'sanctum')->postJson('/api/concerns', [
            'title'         => 'Empty response body test concern',
            'description'   => 'The ceiling fan in Room 204 stopped working this morning. Please inspect and repair the ceiling fan.',
            'workflow_type' => 'non_academic',
            'dept_id'       => $this->deptMaintenance->id,
        ]);

        $resEmptyBody->assertStatus(201);
        $resEmptyBody->assertJsonPath('concern.ai_status', 'complete');
        $resEmptyBody->assertJsonPath('concern.ai_metadata.source', 'local_fallback');
        $resEmptyBody->assertJsonPath('concern.ai_metadata.label', 'Rule-based Engine');
        $resEmptyBody->assertJsonPath('concern.ai_metadata.is_fallback', true);
        $summary1 = (string) $resEmptyBody->json('concern.ai_summary');
        $this->assertNotEmpty($summary1);
        $this->assertLessThanOrEqual(800, mb_strlen($summary1));
        $this->assertMatchesRegularExpression('/[.?!]$/u', $summary1);

        $resEmptyCandidates = $this->actingAs($this->student, 'sanctum')->postJson('/api/concerns', [
            'title'         => 'Empty candidates array test concern',
            'description'   => 'Cashier Window 2 receipt printer failed after I paid 8,500 pesos tuition on November 18, 2026. Requesting verification.',
            'workflow_type' => 'non_academic',
            'dept_id'       => $this->deptCashier->id,
        ]);

        $resEmptyCandidates->assertStatus(201);
        $resEmptyCandidates->assertJsonPath('concern.ai_status', 'complete');
        $resEmptyCandidates->assertJsonPath('concern.ai_metadata.source', 'local_fallback');
        $resEmptyCandidates->assertJsonPath('concern.ai_metadata.label', 'Rule-based Engine');
        $resEmptyCandidates->assertJsonPath('concern.ai_metadata.is_fallback', true);
        $summary2 = (string) $resEmptyCandidates->json('concern.ai_summary');
        $this->assertNotEmpty($summary2);
        $this->assertLessThanOrEqual(800, mb_strlen($summary2));
        $this->assertMatchesRegularExpression('/[.?!]$/u', $summary2);

        Http::assertSentCount(2);

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context = []) {
                $serialized = (string) json_encode($context);
                $this->assertStringNotContainsString('secret-empty-test-key', $serialized);
                $this->assertStringNotContainsString('The ceiling fan in Room 204', $serialized);
                $this->assertStringNotContainsString('Cashier Window 2 receipt printer failed', $serialized);
                return true;
            });
    }

    public function test_prompt_synthetic_scenarios_preserve_all_beginning_middle_end_facts_within_budget(): void
    {
        $service = app(LocalSentimentService::class);
        $maxLen = (int) config('classification.max_summary_length', 800);

        $cases = [
            'A' => [
                'title' => '[AI SUMMARY TEST] Broken Ceiling Fan',
                'description' => 'The ceiling fan in Room 204 stopped working this morning. The classroom is very hot, making it difficult for students to concentrate. We are requesting that the fan be inspected and repaired.',
                'expected' => ['ceiling fan', 'Room 204', 'hot', 'concentrate', 'repaired'],
                'forbidden' => ['LMS', 'quiz', 'scholarship'],
            ],
            'B' => [
                'title' => '[AI SUMMARY TEST] Delayed Official Receipt',
                'description' => 'I paid my tuition fee of 8,500 pesos on November 18, 2026, at Cashier Window 2. I submitted my payment confirmation and deposit slip, but I still have not received my official receipt. The examination permit deadline is Friday, and I am worried that I will not be allowed to take my examination without the receipt. I am requesting assistance in verifying my payment and issuing the official receipt.',
                'expected' => ['November 18', '8,500 pesos', 'Cashier Window 2', 'official receipt', 'Friday', 'verifying'],
                'forbidden' => ['LMS', 'quiz', 'auto-submitted'],
            ],
            'C' => [
                'title' => '[AI SUMMARY TEST] Computer Laboratory Cooling and Water Leak',
                'description' => "Since November 16, 2026, the air-conditioning unit at the back of Computer Laboratory Room 303 has been leaking water continuously. The laboratory also becomes increasingly hot because the cooling system is not functioning properly.\n\nOn November 20, students noticed that water was spreading near workstations PC-12 through PC-15. By November 22, the floor was slippery and students raised concerns about possible electrical hazards. Four workstations were temporarily unplugged as a precaution.\n\nApproximately 40 students use this laboratory for their scheduled practical activities. The upcoming examination is on November 25, 2026, and the continuing problem is affecting our preparation and laboratory sessions.\n\nWe are requesting an inspection of the air-conditioning unit, electrical safety assessment, and repairs before the November 25 examination.",
                'expected' => ['Room 303', 'November 16', 'November 20', 'November 22', 'November 25', 'PC-12 through PC-15', '40 students', 'water leak', 'cooling', 'possible electrical hazards', 'four workstations', 'inspection'],
                'forbidden' => ['LMS', 'quiz'],
            ],
            'D' => [
                'title' => '[AI SUMMARY TEST] Grade Encoding Discrepancy',
                'description' => "I am requesting assistance regarding an incorrect final grade for IT-302 Software Engineering during the First Semester of AY 2026-2027. I completed the course requirements and received a final grade of 91.4% on October 14, 2026.\n\nHowever, on October 28, 2026, the student portal showed INC instead of my expected final grade. I immediately contacted the instructor regarding the discrepancy.\n\nOn November 10, 2026, the instructor verbally acknowledged that a row may have been skipped during the encoding process. I was advised to wait for further checking, but the portal still shows INC.\n\nThis issue is now affecting my eligibility for scholarship renewal under the fictional CHED UniFAST test scenario. The scholarship documentation deadline is November 28, 2026.\n\nI need the grade discrepancy reviewed and corrected before November 27, 2026, so I can submit the necessary academic documents on time. I am requesting a formal review of the grade records and completion of the required Change of Grade process.",
                'expected' => ['IT-302 Software Engineering', '91.4%', 'October 14', 'October 28', 'November 10', 'INC', 'possible encoding omission', 'November 28', 'November 27', 'Change of Grade'],
                'forbidden' => ['LMS', 'quiz'],
            ],
        ];

        foreach ($cases as $label => $case) {
            $summary = $service->generateSummary($case['title'], $case['description'], 'General', 'Other', 'medium');
            $this->assertNotEmpty($summary, "Case {$label} summary was empty.");
            $this->assertLessThanOrEqual($maxLen, mb_strlen($summary), "Case {$label} exceeded {$maxLen} chars.");
            $this->assertMatchesRegularExpression('/[.?!]$/u', $summary, "Case {$label} lacked terminal punctuation.");
            $this->assertStringNotContainsString('...', $summary, "Case {$label} contained ellipsis.");
            $this->assertStringNotContainsString('…', $summary, "Case {$label} contained unicode ellipsis.");

            $summaryLen = mb_strlen($summary);
            foreach ($case['expected'] as $term) {
                $this->assertStringContainsString(
                    strtolower($term),
                    strtolower($summary),
                    "Case {$label} missing expected fact '{$term}'. Actual ({$summaryLen} chars): {$summary}"
                );
            }
            foreach ($case['forbidden'] as $forbidden) {
                $this->assertStringNotContainsString(
                    strtolower($forbidden),
                    strtolower($summary),
                    "Case {$label} contained forbidden term '{$forbidden}'."
                );
            }
        }
    }

    public function test_second_refinement_medium_and_long_summaries_are_concise_and_preserve_essential_facts(): void
    {
        $service = app(LocalSentimentService::class);
        $maxLen = (int) config('classification.max_summary_length', 800);

        // 1. Short concern (#58 equivalent) receives modest deterministic condensation while remaining clear
        $shortDesc = 'The ceiling fan in Room 204 stopped working this morning. The classroom is very hot, making it difficult for students to concentrate. We are requesting that the fan be inspected and repaired.';
        $shortSummary = $service->generateSummary('[AI SUMMARY TEST] Broken Ceiling Fan', $shortDesc, 'General', 'Other', 'low');
        $this->assertLessThan(mb_strlen($shortDesc), mb_strlen($shortSummary));
        $this->assertStringContainsString('Room 204', $shortSummary);
        $this->assertStringContainsString('ceiling fan', $shortSummary);
        $this->assertStringContainsString('concentrate', $shortSummary);
        $this->assertStringContainsString('repaired', $shortSummary);

        // 2. Medium concern (#59 equivalent: 400 chars) reduces concisely in 2 sentences
        $mediumDesc = 'I paid my tuition fee of 8,500 pesos on November 18, 2026, at Cashier Window 2. I submitted my payment confirmation and deposit slip, but I still have not received my official receipt. The examination permit deadline is Friday, and I am worried that I will not be allowed to take my examination without the receipt. I am requesting assistance in verifying my payment and issuing the official receipt.';
        $mediumSummary = $service->generateSummary('[AI SUMMARY TEST] Delayed Official Receipt', $mediumDesc, 'Non-Academic Concerns', 'Cashier / Payment', 'high');
        $this->assertLessThan((int) round(mb_strlen($mediumDesc) * 0.70), mb_strlen($mediumSummary));
        $this->assertCount(2, \App\Support\SummaryTextHelper::splitSentences($mediumSummary));
        $this->assertFalse(\App\Support\SummaryTextHelper::hasDuplicateSentence(\App\Support\SummaryTextHelper::splitSentences($mediumSummary)));

        // 3. Long multi-paragraph lab leak (#60 equivalent: 856 chars) stays concise <= 450 chars in 3 sentences
        $labDesc = "Since November 16, 2026, the air-conditioning unit at the back of Computer Laboratory Room 303 has been leaking water continuously. The laboratory also becomes increasingly hot because the cooling system is not functioning properly.\n\nOn November 20, students noticed that water was spreading near workstations PC-12 through PC-15. By November 22, the floor was slippery and students raised concerns about possible electrical hazards. Four workstations were temporarily unplugged as a precaution.\n\nApproximately 40 students use this laboratory for their scheduled practical activities. The upcoming examination is on November 25, 2026, and the continuing problem is affecting our preparation and laboratory sessions.\n\nWe are requesting an inspection of the air-conditioning unit, electrical safety assessment, and repairs before the November 25 examination.";
        $labSummary = $service->generateSummary('[AI SUMMARY TEST] Computer Laboratory Cooling and Water Leak', $labDesc, 'Non-Academic Concerns', 'Maintenance', 'critical');
        $this->assertLessThanOrEqual(455, mb_strlen($labSummary));
        $this->assertLessThan((int) round(mb_strlen($labDesc) * 0.56), mb_strlen($labSummary));
        $this->assertCount(3, \App\Support\SummaryTextHelper::splitSentences($labSummary));

        // 4. Very long grade discrepancy (#61 equivalent: 1013 chars) stays concise <= 400 chars in 3 sentences
        $gradeDesc = "I am requesting assistance regarding an incorrect final grade for IT-302 Software Engineering during the First Semester of AY 2026-2027. I completed the course requirements and received a final grade of 91.4% on October 14, 2026.\n\nHowever, on October 28, 2026, the student portal showed INC instead of my expected final grade. I immediately contacted the instructor regarding the discrepancy.\n\nOn November 10, 2026, the instructor verbally acknowledged that a row may have been skipped during the encoding process. I was advised to wait for further checking, but the portal still shows INC.\n\nThis issue is now affecting my eligibility for scholarship renewal under the fictional CHED UniFAST test scenario. The scholarship documentation deadline is November 28, 2026.\n\nI need the grade discrepancy reviewed and corrected before November 27, 2026, so I can submit the necessary academic documents on time. I am requesting a formal review of the grade records and completion of the required Change of Grade process.";
        $gradeSummary = $service->generateSummary('[AI SUMMARY TEST] Grade Encoding Discrepancy', $gradeDesc, 'Academic Concerns', 'Assessment and Examination', 'medium');
        $this->assertLessThanOrEqual(400, mb_strlen($gradeSummary));
        $this->assertLessThan((int) round(mb_strlen($gradeDesc) * 0.45), mb_strlen($gradeSummary));
        $this->assertCount(3, \App\Support\SummaryTextHelper::splitSentences($gradeSummary));

        // 5. Generic unseen multi-paragraph concern routes through Tier 3 Concise Extractive (< 65% length, 2-3 sentences)
        $genericLong = "Good morning dear administration. I am a fourth-year student enrolled in the Bachelor of Science in Information Technology program.\n\nDue to the fact that the Registrar counter printer experienced an outage on November 4, my official Transcript of Records and diploma certification request has remained pending for three weeks without any status update in the portal. I visited Window 4 twice last week and submitted my stamped claim stub.\n\nMoreover, the delay is blocking my pre-employment medical and onboarding clearance for a scheduled December 1 orientation. I also submitted a follow-up ticket yesterday morning.\n\nI am writing to formally request that the Registrar expedite the release of my Transcript of Records and diploma certification before November 29. Thank you very much for your help.";
        $genericSummary = $service->generateSummary('Delayed Transcript of Records Release at Registrar', $genericLong, 'Non-Academic Concerns', 'Registrar Services', 'high');
        $genericSentences = \App\Support\SummaryTextHelper::splitSentences($genericSummary);
        $this->assertLessThanOrEqual($maxLen, mb_strlen($genericSummary));
        $this->assertLessThan((int) round(mb_strlen($genericLong) * 0.65), mb_strlen($genericSummary));
        $this->assertGreaterThanOrEqual(2, count($genericSentences));
        $this->assertLessThanOrEqual(3, count($genericSentences));
        $this->assertFalse(\App\Support\SummaryTextHelper::hasDuplicateSentence($genericSentences));
        $this->assertStringNotContainsString('...', $genericSummary);
        $this->assertStringContainsString('Transcript of Records', $genericSummary);
        $this->assertStringContainsString('November 29', $genericSummary);
    }

    public function test_synthesizer_preserves_hedging_and_never_invents_omitted_facts(): void
    {
        $service = app(LocalSentimentService::class);
        $maxLen = (int) config('classification.max_summary_length', 800);

        // 1. Hedged instructor note ("may have been skipped") must NOT inflate into confirmed omission
        $hedgedGrade = "My IT-302 Software Engineering course shows an INC mark as of October 28 despite earning 91.4%. On November 10, the instructor acknowledged that a row may have been skipped during encoding. Please complete a Change of Grade before November 27.";
        $hedgedSummary = $service->generateSummary('IT-302 INC Grade Discrepancy', $hedgedGrade, 'Academic Concerns', 'Assessment and Examination', 'medium');
        $this->assertStringContainsString('possible', strtolower($hedgedSummary));
        $this->assertStringNotContainsString('confirmed', strtolower($hedgedSummary));
        $this->assertStringNotContainsString('scholarship', strtolower($hedgedSummary));
        $this->assertStringNotContainsString('ched', strtolower($hedgedSummary));

        // 2. Facility water leak without student count or exam must NOT invent students or examinations
        $leakNoStudents = "Since November 16, 2026, the air-conditioning unit in Room 303 has been leaking water continuously near PC-12 through PC-15. By November 22, the floor became slippery near the back wall outlet area. We are requesting an air-conditioning inspection and repairs as soon as possible.";
        $leakSummary = $service->generateSummary('Room 303 Aircon Water Leak', $leakNoStudents, 'Non-Academic Concerns', 'Maintenance', 'medium');
        $this->assertStringContainsString('Room 303', $leakSummary);
        $this->assertStringContainsString('PC-12 through PC-15', $leakSummary);
        $this->assertStringNotContainsString('students', strtolower($leakSummary));
        $this->assertStringNotContainsString('examination', strtolower($leakSummary));
        $this->assertStringNotContainsString('electrical', strtolower($leakSummary));

        // 3. Dense unstructured concern exceeding 450 chars is NOT chopped prematurely at 450
        $denseThreeSentences = 'During our capstone system audit inside Engineering Annex Lecture Hall Room 508 yesterday afternoon, the overhead ceiling conduit began dripping condensed fluid directly onto the main server distribution switch and smart podium controller. '
            . 'Because the continuous moisture accumulation caused the backup uninterruptible power supply alarm to chime intermittently and rendered the presentation display unusable across two consecutive senior project defenses, afternoon classes were relocated temporarily. '
            . 'We request urgent electrical isolation, ceiling conduit repair, and hardware testing before tomorrow morning senior project evaluations.';
        $denseSummary = $service->generateSummary('Podium conduit leak in Room 508', $denseThreeSentences, 'Non-Academic Concerns', 'Maintenance', 'critical');
        $this->assertGreaterThan(450, mb_strlen($denseSummary));
        $this->assertLessThanOrEqual($maxLen, mb_strlen($denseSummary));
        $this->assertMatchesRegularExpression('/[.?!]$/u', $denseSummary);
        $this->assertStringNotContainsString('...', $denseSummary);

        // 4. Original DB description is never altered when storing through API
        $res = $this->actingAs($this->student, 'sanctum')->postJson('/api/concerns', [
            'title'         => 'IT-302 INC Grade Review',
            'description'   => $hedgedGrade,
            'workflow_type' => 'academic',
        ]);
        $res->assertStatus(201);
        $saved = Concern::findOrFail((int) $res->json('concern.concern_id'));
        $this->assertSame($hedgedGrade, $saved->description);
    }

    public function test_gemini_abstractive_prompt_contract_verbatim_copy_rejection_and_synthetic_command(): void
    {
        // 1. Inspect GeminiSentimentService::buildPrompt() instructions
        $geminiService = app(GeminiSentimentService::class);
        $ref = new \ReflectionClass($geminiService);
        $buildPrompt = $ref->getMethod('buildPrompt');
        $buildPrompt->setAccessible(true);

        $promptText = (string) $buildPrompt->invoke(
            $geminiService,
            'Urgent Guidance Counseling Request',
            'I am dealing with an acute family and personal crisis that has severely impacted my attendance.',
            'non_academic'
        );

        $this->assertStringContainsString('genuine semantic condensation', $promptText);
        $this->assertStringContainsString('third-person language', $promptText);
        $this->assertStringContainsString('40-60% shorter', $promptText);
        $this->assertStringContainsString('Preserve uncertainty such as "may", "possible", or "reported"', $promptText);
        $this->assertStringContainsString('The student reports a family and personal crisis that is affecting attendance and academic performance.', $promptText);

        // 2. Local fallback synthesizes personal/family crisis scenario concisely in third-person
        $crisisDesc = 'I am dealing with an acute family and personal crisis that has severely impacted my attendance and exam performance at UCLM this week. Because my academic standing and subject retention are at risk before the upcoming grading cutoff, I need a consultation and counseling endorsement from the UCLM Guidance Center today.';
        $localService = app(LocalSentimentService::class);
        $localCrisisSummary = $localService->generateSummary('Guidance Counseling Endorsement', $crisisDesc, 'Non-Academic Concerns', 'Student Affairs', 'high');
        $this->assertLessThan(mb_strlen($crisisDesc), mb_strlen($localCrisisSummary));
        $this->assertStringContainsString('family and personal crisis', strtolower($localCrisisSummary));
        $this->assertStringContainsString('attendance', strtolower($localCrisisSummary));
        $this->assertStringContainsString('guidance center', strtolower($localCrisisSummary));
        $this->assertStringContainsString('grading deadline', strtolower($localCrisisSummary));

        // 3. When Gemini returns a verbatim copy of the description as "summary", it is NOT labeled as Gemini AI
        config([
            'services.gemini.enabled' => true,
            'services.gemini.api_key' => 'test-synthetic-gemini-key',
            'services.gemini.model'   => 'gemini-3.8-flash',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'sentiment_label'            => 'negative',
                            'sentiment_score'            => -0.6,
                            'issue_category'             => 'Non-Academic Concerns',
                            'issue_subcategory'          => 'Student Affairs',
                            'classification_explanation' => 'Counseling request.',
                            'classification_confidence'  => 0.91,
                            'priority_suggestion'        => 'high',
                            'priority_explanation'       => 'Academic standing risk.',
                            'summary'                    => $crisisDesc, // verbatim copy of description!
                        ]),
                    ]]],
                ]],
                'usageMetadata' => ['totalTokenCount' => 190],
            ], 200),
        ]);

        $copiedRes = $this->actingAs($this->student, 'sanctum')->postJson('/api/concerns', [
            'title'         => 'Urgent Guidance Counseling Request',
            'description'   => $crisisDesc,
            'workflow_type' => 'non_academic',
            'dept_id'       => $this->deptSao->id,
        ]);

        $copiedRes->assertStatus(201);
        $copiedRes->assertJsonPath('concern.ai_metadata.source', 'local_fallback');
        $copiedRes->assertJsonPath('concern.ai_metadata.label', 'Rule-based Engine');
        $copiedRes->assertJsonPath('concern.ai_metadata.is_fallback', true);
        $this->assertSame($localCrisisSummary, $copiedRes->json('concern.ai_summary'));

        // 4. Controlled development synthetic testing command (--simulated --json)
        $this->artisan('scms:test-gemini-summaries --simulated --json')
            ->assertExitCode(0);
    }

    public function test_controlled_gemini_activation_strategy_across_local_production_and_testing_environments(): void
    {
        $service = app(GeminiSentimentService::class);

        // 1. Default PHPUnit / Testing state: disabled by default and API key blank
        $defaultStatus = $service->runtimeStatus();
        $this->assertFalse($defaultStatus['enabled_flag']);
        $this->assertFalse($defaultStatus['api_key_configured']);
        $this->assertFalse($defaultStatus['is_available']);
        $this->assertSame('Rule-based Engine', $defaultStatus['active_engine']);
        $this->assertSame('gemini-3.8-flash', $defaultStatus['model']);
        $this->assertStringStartsWith('INACTIVE:', $defaultStatus['external_data_warning']);

        // 2. Never automatically enabled merely because an API key exists (local or production)
        config([
            'services.gemini.enabled' => false,
            'services.gemini.api_key' => 'secret-production-or-local-key-do-not-expose',
        ]);
        $keyOnlyStatus = $service->runtimeStatus();
        $this->assertFalse($service->isAvailable());
        $this->assertFalse($keyOnlyStatus['enabled_flag']);
        $this->assertTrue($keyOnlyStatus['api_key_configured']);
        $this->assertFalse($keyOnlyStatus['is_available']);
        $this->assertSame('Rule-based Engine', $keyOnlyStatus['active_engine']);
        $this->assertStringNotContainsString(
            'secret-production-or-local-key-do-not-expose',
            (string) json_encode($keyOnlyStatus)
        );

        // Inspect config/services.php file directly to confirm default is strictly false (never auto-enabled by APP_ENV)
        $servicesConfigSource = (string) file_get_contents(base_path('config/services.php'));
        $this->assertStringContainsString("env('GEMINI_ENABLED', false)", $servicesConfigSource);
        $this->assertStringContainsString('PRIVACY & DATA-HANDLING WARNING', $servicesConfigSource);

        // 3. Explicit activation (GEMINI_ENABLED=true + non-empty key) enables Gemini and emits ACTIVE warning
        config([
            'services.gemini.enabled' => true,
            'services.gemini.api_key' => 'secret-explicit-key-12345',
            'services.gemini.model'   => 'gemini-3.8-flash',
        ]);
        $activeStatus = $service->runtimeStatus();
        $this->assertTrue($service->isAvailable());
        $this->assertTrue($activeStatus['enabled_flag']);
        $this->assertTrue($activeStatus['api_key_configured']);
        $this->assertTrue($activeStatus['is_available']);
        $this->assertSame('Gemini AI', $activeStatus['active_engine']);
        $this->assertSame('gemini-3.8-flash', $activeStatus['model']);
        $this->assertStringStartsWith('ACTIVE:', $activeStatus['external_data_warning']);
        $this->assertStringNotContainsString(
            'secret-explicit-key-12345',
            (string) json_encode($activeStatus)
        );

        // 4. Artisan runtime status check (--status --json) makes zero HTTP calls
        $this->artisan('scms:test-gemini-summaries --status --json')
            ->assertExitCode(0);
        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $concernPayload
     */
    private function assertStudentPrivacyFieldsHidden(array $concernPayload): void
    {
        $this->assertArrayNotHasKey('ai_priority_explanation', $concernPayload);
        $this->assertArrayNotHasKey('classification_explanation', $concernPayload);
        $this->assertArrayNotHasKey('priority_override_reason', $concernPayload);
        $this->assertArrayNotHasKey('ai_priority', $concernPayload);
        $this->assertArrayNotHasKey('is_priority_overridden', $concernPayload);
        $this->assertArrayNotHasKey('tokens_used', $concernPayload['ai_metadata'] ?? []);
    }
}
