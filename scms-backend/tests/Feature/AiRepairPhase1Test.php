<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Concern;
use App\Models\Department;
use App\Models\User;
use App\Services\GeminiSentimentService;
use App\Services\LocalSentimentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class AiRepairPhase1Test extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $deptStaff;
    private User $dean;
    private AcademicUnit $unitCcs;
    private Department $deptCsu;
    private Department $deptGuidance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitCcs = AcademicUnit::create(['name' => 'College of Computer Studies', 'code' => 'CCS']);
        $this->deptCsu = Department::create(['name' => 'Campus Security Unit', 'code' => 'CSU']);
        $this->deptGuidance = Department::create(['name' => 'Guidance Center', 'code' => 'GUIDANCE']);

        $this->student = User::create([
            'name'              => 'Student Tester',
            'email'             => 'student.tester@uclm.edu.ph',
            'student_id_number' => '2024-9999',
            'password'          => Hash::make('password'),
            'role'              => 'student',
            'academic_unit_id'  => $this->unitCcs->id,
        ]);

        $this->deptStaff = User::create([
            'name'              => 'CSU Staff',
            'email'             => 'csu.staff@uclm.edu.ph',
            'password'          => Hash::make('password'),
            'role'              => 'dept_staff',
            'dept_id'           => $this->deptCsu->id,
            'is_active'         => true,
        ]);

        $this->dean = User::create([
            'name'              => 'CCS Dean',
            'email'             => 'ccs.dean@uclm.edu.ph',
            'password'          => Hash::make('password'),
            'role'              => 'dean',
            'academic_unit_id'  => $this->unitCcs->id,
            'is_active'         => true,
        ]);
    }

    /**
     * Case A: Physical assault/bullying triggers critical priority and peer conflict category.
     */
    public function test_case_a_physical_assault_triggers_critical_priority_and_peer_conflict(): void
    {
        $service = app(LocalSentimentService::class);

        $result = $service->analyzeConcern(
            'Physical assault by classmate in hallway',
            'A classmate punched me in the face, my cheek is swollen and bleeding. I am terrified and need help.',
            'non_academic'
        );

        $this->assertEquals('critical', $result['priority_suggestion']);
        $this->assertEquals('Non-Academic Concerns', $result['issue_category']);
        $this->assertEquals('Peer Conflict / Harassment', $result['issue_subcategory']);
        $this->assertLessThanOrEqual(300, mb_strlen($result['summary']));
        $this->assertStringContainsString('assault', strtolower($result['summary']));
        $this->assertStringNotContainsString('Physical assault by classmate in hallway — A classmate punched me', $result['summary']);
    }

    /**
     * Case B: Missing school ID maps to Student Affairs and CSU routing.
     */
    public function test_case_b_missing_school_id_maps_to_student_affairs_and_csu(): void
    {
        $service = app(LocalSentimentService::class);

        $result = $service->analyzeConcern(
            'Missing school ID',
            'I lost my school ID card yesterday around the canteen. Need replacement or retrieval.',
            'non_academic'
        );

        $this->assertEquals('Non-Academic Concerns', $result['issue_category']);
        $this->assertEquals('Student Affairs', $result['issue_subcategory']);
        $this->assertLessThanOrEqual(300, mb_strlen($result['summary']));
        $this->assertStringContainsString('ID', $result['summary']);

        // Check department recommendation routes to Security/CSU
        $deptRec = $service->recommendOperationalDepartment(
            'Missing school ID',
            'I lost my school ID card yesterday around the canteen.',
            Department::all()
        );
        $this->assertNotNull($deptRec);
        $this->assertEquals($this->deptCsu->id, $deptRec['recommended_department_id']);
    }

    /**
     * Case C: Academic assessment issue maps to Assessment and Examination with high priority.
     */
    public function test_case_c_academic_assessment_disruption_maps_to_assessment_and_high_priority(): void
    {
        $service = app(LocalSentimentService::class);

        $result = $service->analyzeConcern(
            'LMS Midterm Quiz Auto-submitted prematurely',
            'The Canvas system auto-submitted my midterm quiz after 4 minutes with 25 questions unanswered due to a system glitch.',
            'academic'
        );

        $this->assertEquals('Academic Concerns', $result['issue_category']);
        $this->assertEquals('Assessment and Examination', $result['issue_subcategory']);
        $this->assertEquals('high', $result['priority_suggestion']);
        $this->assertLessThanOrEqual(300, mb_strlen($result['summary']));
        $this->assertStringContainsString('assessment', strtolower($result['summary']));
    }

    /**
     * Task 1: Summary generation produces concise summary without repeating title and within 300 chars.
     */
    public function test_summary_generation_handles_short_and_detailed_concerns(): void
    {
        $service = app(LocalSentimentService::class);

        // Short concern
        $short = $service->analyzeConcern('Aircon dripping', 'Aircon leaking water.', 'academic');
        $this->assertLessThanOrEqual(300, mb_strlen($short['summary']));
        $this->assertStringNotContainsString('Aircon dripping — Aircon leaking water.', $short['summary']);

        // Long concern
        $longDesc = str_repeat('The classroom projector lamp is burnt out and flickering during daily lectures. ', 10);
        $long = $service->analyzeConcern('Burnt projector lamp', $longDesc, 'academic');
        $this->assertLessThanOrEqual(300, mb_strlen($long['summary']));
        $this->assertGreaterThan(10, mb_strlen($long['summary']));
    }

    /**
     * Task 3: Gemini parser preserves legitimate subcategories and does not wipe them to null.
     */
    public function test_gemini_parser_preserves_legitimate_subcategories(): void
    {
        $gemini = app(GeminiSentimentService::class);

        // Call parseResponse with a reflection method or simulated Gemini output
        $reflection = new \ReflectionClass($gemini);
        $method = $reflection->getMethod('parseResponse');
        $method->setAccessible(true);

        $mockBody = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'sentiment_score'            => -0.8,
                                    'sentiment_label'            => 'Negative',
                                    'priority_suggestion'        => 'high',
                                    'priority_explanation'       => 'High exam impact',
                                    'issue_category'             => 'Academic Concerns',
                                    'issue_subcategory'          => 'Assessment and Examination',
                                    'classification_explanation' => 'Midterm exam auto-submission issue',
                                    'classification_confidence'  => 0.95,
                                    'summary'                    => 'Student midterm quiz auto-submitted after 4 minutes with 25 unanswered questions.',
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
            'usageMetadata' => [
                'totalTokenCount' => 185,
            ],
        ];

        $parsed = $method->invoke($gemini, $mockBody, 'academic');

        $this->assertNotNull($parsed);
        $this->assertEquals('Academic Concerns', $parsed['issue_category']);
        $this->assertEquals('Assessment and Examination', $parsed['issue_subcategory']);
        $this->assertEquals(185, $parsed['api_tokens_used']);
        $this->assertEquals('gemini', $parsed['ai_source']);
    }

    /**
     * Task 4: Concern update re-runs analysis with Gemini and logs token usage.
     */
    public function test_concern_update_invokes_gemini_and_logs_tokens(): void
    {
        // Mock Gemini to return a valid response
        $geminiMock = Mockery::mock(GeminiSentimentService::class);
        $geminiMock->shouldReceive('analyzeConcern')
            ->once()
            ->andReturn([
                'sentiment_score'            => -0.85,
                'sentiment_label'            => 'Negative',
                'priority_suggestion'        => 'high',
                'priority_explanation'       => 'Updated priority from Gemini',
                'issue_category'             => 'Academic Concerns',
                'issue_subcategory'          => 'Assessment and Examination',
                'classification_explanation' => 'Gemini classified as assessment issue',
                'classification_confidence'  => 0.92,
                'summary'                    => 'Midterm quiz auto-submitted prematurely on LMS.',
                'api_tokens_used'            => 195,
                'ai_source'                  => 'gemini',
            ]);
        $this->app->instance(GeminiSentimentService::class, $geminiMock);

        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'title'            => 'Initial title',
            'description'      => 'Initial description',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'target_type'      => 'dean',
            'academic_unit_id' => $this->unitCcs->id,
            'status'           => 'pending',
            'priority'         => 'medium',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        $response = $this->actingAs($this->student, 'sanctum')->putJson("/api/concerns/{$concern->concern_id}", [
            'title'         => 'LMS Quiz auto-submitted prematurely',
            'description'   => 'Canvas auto-submitted after four minutes with 25 questions unanswered.',
            'workflow_type' => 'academic',
        ]);

        $response->assertStatus(200);

        $fresh = $concern->fresh();
        $this->assertEquals('high', $fresh->priority);
        $this->assertEquals('Assessment and Examination', $fresh->issue_subcategory);
        $this->assertEquals('complete', $fresh->ai_status);

        // Check sentiment_logs recorded Gemini tokens
        $latestLog = DB::table('sentiment_logs')
            ->where('concern_id', $concern->concern_id)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($latestLog);
        $this->assertEquals(195, $latestLog->api_tokens_used);

        // Verify ai_metadata accessor for student: token usage is omitted
        $this->assertEquals('gemini', $fresh->ai_metadata['source']);
        $this->assertEquals('Gemini AI', $fresh->ai_metadata['label']);
        $this->assertFalse($fresh->ai_metadata['is_fallback']);
        $this->assertArrayNotHasKey('tokens_used', $fresh->ai_metadata);

        // Verify response payload to student does not expose tokens_used
        $response->assertJsonPath('concern.ai_metadata.source', 'gemini');
        $this->assertArrayNotHasKey('tokens_used', $response->json('concern.ai_metadata'));
    }

    /**
     * Task 5: Fallback metadata correctly identifies rule engine when tokens == 0.
     */
    public function test_ai_metadata_identifies_rule_engine_on_fallback(): void
    {
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'title'            => 'Water dispenser broken',
            'description'      => 'Water dispenser in 3rd floor hallway leaking.',
            'category'         => 'departments',
            'workflow_type'    => 'non_academic',
            'target_type'      => 'department',
            'academic_unit_id' => $this->unitCcs->id,
            'dept_id'          => $this->deptCsu->id,
            'status'           => 'pending',
            'priority'         => 'low',
            'ai_status'        => 'complete',
            'ai_summary'       => '3rd floor water dispenser is leaking.',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        // Insert log with 0 tokens (local fallback)
        DB::table('sentiment_logs')->insert([
            'concern_id'          => $concern->concern_id,
            'raw_text_analyzed'   => 'Sample text',
            'sentiment_score'     => -0.2,
            'sentiment_label'     => 'Neutral',
            'priority_suggestion' => 'low',
            'ai_summary'          => '3rd floor water dispenser is leaking.',
            'api_tokens_used'     => 0,
            'analyzed_at'         => now(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        // When accessed by authorized staff, tokens_used is included
        $this->actingAs($this->deptStaff, 'sanctum');
        $fresh = $concern->fresh();
        $this->assertEquals('local_fallback', $fresh->ai_metadata['source']);
        $this->assertEquals('Rule-based Engine', $fresh->ai_metadata['label']);
        $this->assertTrue($fresh->ai_metadata['is_fallback']);
        $this->assertEquals(0, $fresh->ai_metadata['tokens_used']);
    }

    /**
     * Privacy: Student show API response excludes tokens_used while retaining source and label.
     */
    public function test_student_api_response_excludes_tokens_used(): void
    {
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'title'            => 'Broken chair in lab',
            'description'      => 'Chair is unstable and missing screws.',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'target_type'      => 'dean',
            'academic_unit_id' => $this->unitCcs->id,
            'status'           => 'pending',
            'priority'         => 'low',
            'ai_status'        => 'complete',
            'ai_summary'       => 'Lab chair unstable and missing screws.',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        DB::table('sentiment_logs')->insert([
            'concern_id'          => $concern->concern_id,
            'raw_text_analyzed'   => 'Sample text',
            'sentiment_score'     => -0.3,
            'sentiment_label'     => 'Negative',
            'priority_suggestion' => 'low',
            'ai_summary'          => 'Lab chair unstable.',
            'api_tokens_used'     => 145,
            'analyzed_at'         => now(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $response = $this->actingAs($this->student, 'sanctum')
            ->getJson("/api/concerns/{$concern->concern_id}");

        $response->assertStatus(200);
        $response->assertJsonPath('concern.ai_metadata.status', 'complete');
        $response->assertJsonPath('concern.ai_metadata.source', 'gemini');
        $response->assertJsonPath('concern.ai_metadata.label', 'Gemini AI');
        $response->assertJsonPath('concern.ai_metadata.is_fallback', false);

        // Critical privacy verification: tokens_used must NOT be exposed to student
        $this->assertArrayNotHasKey('tokens_used', $response->json('concern.ai_metadata'));
    }

    /**
     * Authorization: Authorized staff and Dean API responses include tokens_used.
     */
    public function test_authorized_staff_and_dean_api_responses_include_tokens_used(): void
    {
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'title'            => 'Broken aircon in server room',
            'description'      => 'Aircon leaking water near server racks.',
            'category'         => 'departments',
            'workflow_type'    => 'non_academic',
            'target_type'      => 'department',
            'academic_unit_id' => $this->unitCcs->id,
            'dept_id'          => $this->deptCsu->id,
            'status'           => 'pending',
            'priority'         => 'high',
            'ai_status'        => 'complete',
            'ai_summary'       => 'Aircon leaking water near server racks.',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        DB::table('sentiment_logs')->insert([
            'concern_id'          => $concern->concern_id,
            'raw_text_analyzed'   => 'Sample text',
            'sentiment_score'     => -0.5,
            'sentiment_label'     => 'Negative',
            'priority_suggestion' => 'high',
            'ai_summary'          => 'Aircon leaking.',
            'api_tokens_used'     => 210,
            'analyzed_at'         => now(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        // Dept Staff check
        $staffResponse = $this->actingAs($this->deptStaff, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}");
        $staffResponse->assertStatus(200);
        $staffResponse->assertJsonPath('concern.ai_metadata.tokens_used', 210);

        // Dean check (academic concern in Dean's academic unit)
        $academicConcern = Concern::create([
            'student_id'       => $this->student->id,
            'title'            => 'Grading discrepancy on midterm exam',
            'description'      => 'Midterm exam grade computed incorrectly.',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'target_type'      => 'dean',
            'academic_unit_id' => $this->unitCcs->id,
            'status'           => 'pending',
            'priority'         => 'medium',
            'ai_status'        => 'complete',
            'ai_summary'       => 'Midterm grade computed incorrectly.',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        DB::table('sentiment_logs')->insert([
            'concern_id'          => $academicConcern->concern_id,
            'raw_text_analyzed'   => 'Sample text',
            'sentiment_score'     => -0.4,
            'sentiment_label'     => 'Negative',
            'priority_suggestion' => 'medium',
            'ai_summary'          => 'Midterm grade computed incorrectly.',
            'api_tokens_used'     => 175,
            'analyzed_at'         => now(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $deanResponse = $this->actingAs($this->dean, 'sanctum')
            ->getJson("/api/dean/concerns/{$academicConcern->concern_id}");
        $deanResponse->assertStatus(200);
        $deanResponse->assertJsonPath('concern.ai_metadata.tokens_used', 175);
    }

    /**
     * Privacy: Unauthenticated access or unknown role excludes tokens_used.
     */
    public function test_unauthenticated_or_unknown_role_excludes_tokens_used(): void
    {
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'title'            => 'General question',
            'description'      => 'Inquiry about campus hours.',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'target_type'      => 'dean',
            'academic_unit_id' => $this->unitCcs->id,
            'status'           => 'pending',
            'priority'         => 'low',
            'ai_status'        => 'complete',
            'ai_summary'       => 'Inquiry about campus hours.',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        DB::table('sentiment_logs')->insert([
            'concern_id'          => $concern->concern_id,
            'raw_text_analyzed'   => 'Sample text',
            'sentiment_score'     => 0.0,
            'sentiment_label'     => 'Neutral',
            'priority_suggestion' => 'low',
            'ai_summary'          => 'Inquiry about campus hours.',
            'api_tokens_used'     => 120,
            'analyzed_at'         => now(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        // Unauthenticated access
        $meta = $concern->fresh()->ai_metadata;
        $this->assertEquals('gemini', $meta['source']);
        $this->assertArrayNotHasKey('tokens_used', $meta);
    }

    /**
     * Task 5: ai_metadata correctly handles failed and pending states.
     */
    public function test_ai_metadata_handles_failed_and_pending(): void
    {
        $concernFailed = Concern::create([
            'student_id'       => $this->student->id,
            'title'            => 'Failed concern',
            'description'      => 'Something went wrong during analysis.',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'target_type'      => 'dean',
            'academic_unit_id' => $this->unitCcs->id,
            'status'           => 'pending',
            'priority'         => 'medium',
            'ai_status'        => 'failed',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        $this->assertEquals('failed', $concernFailed->ai_metadata['status']);
        $this->assertEquals('Analysis Failed', $concernFailed->ai_metadata['label']);

        $concernPending = Concern::create([
            'student_id'       => $this->student->id,
            'title'            => 'Pending concern',
            'description'      => 'Awaiting queue worker processing.',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'target_type'      => 'dean',
            'academic_unit_id' => $this->unitCcs->id,
            'status'           => 'pending',
            'priority'         => 'medium',
            'ai_status'        => 'pending',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        $this->assertEquals('pending', $concernPending->ai_metadata['status']);
        $this->assertEquals('Processing...', $concernPending->ai_metadata['label']);
    }
}
