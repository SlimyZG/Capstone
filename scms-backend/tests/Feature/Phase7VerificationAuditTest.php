<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Concern;
use App\Models\Department;
use App\Models\RecurringIncident;
use App\Models\User;
use App\Services\LocalSentimentService;
use App\Services\RecurrenceDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase7VerificationAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $dean;
    private User $deptStaff;
    private User $saoUser;
    private AcademicUnit $unitCcs;
    private Department $deptSao;
    private Department $deptRegistrar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitCcs = AcademicUnit::create(['name' => 'College of Computer Studies', 'code' => 'CCS']);
        $this->deptSao = Department::create(['name' => 'Student Affairs Office', 'code' => 'SAO']);
        $this->deptRegistrar = Department::create(['name' => 'Registrar Office', 'code' => 'REG']);

        $this->student = User::create([
            'name'              => 'Synthetic Student',
            'email'             => 'synthetic_student@test.local',
            'student_id_number' => 'SYNTH-001',
            'password'          => Hash::make('password'),
            'role'              => 'student',
            'academic_unit_id'  => $this->unitCcs->id,
        ]);

        $this->dean = User::create([
            'name'              => 'Synthetic Dean',
            'email'             => 'synthetic_dean@test.local',
            'student_id_number' => 'DEAN-001',
            'password'          => Hash::make('password'),
            'role'              => 'dean',
            'academic_unit_id'  => $this->unitCcs->id,
        ]);

        $this->deptStaff = User::create([
            'name'              => 'Synthetic Staff',
            'email'             => 'synthetic_staff@test.local',
            'password'          => Hash::make('password'),
            'role'              => 'dept_staff',
            'academic_unit_id'  => $this->unitCcs->id,
        ]);

        $this->saoUser = User::create([
            'name'              => 'Synthetic SAO',
            'email'             => 'synthetic_sao@test.local',
            'password'          => Hash::make('password'),
            'role'              => 'sao',
            'dept_id'           => $this->deptSao->id,
            'is_active'         => true,
        ]);
    }

    private function createConcern(array $attributes = []): Concern
    {
        $attrs = array_merge([
            'student_id'       => $this->student->id,
            'title'            => 'Synthetic Test Concern',
            'description'      => 'Synthetic test description for verification.',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'target_type'      => 'department',
            'academic_unit_id' => $this->unitCcs->id,
            'dept_id'          => null,
            'status'           => 'pending',
            'priority'         => 'medium',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ], $attributes);

        return Concern::create($attrs);
    }

    // ── 1. Database & Schema Verification ────────────────────────────────────

    public function test_v01_database_contains_all_seven_phase7_columns(): void
    {
        $concernColumns = [
            'issue_category',
            'issue_subcategory',
            'classification_explanation',
            'classification_confidence',
            'ai_priority_explanation',
        ];
        foreach ($concernColumns as $col) {
            $this->assertTrue(
                Schema::hasColumn('concerns', $col),
                "Missing approved column '{$col}' on table 'concerns'."
            );
        }

        $recurringColumns = [
            'issue_category',
            'issue_subcategory',
        ];
        foreach ($recurringColumns as $col) {
            $this->assertTrue(
                Schema::hasColumn('recurring_incidents', $col),
                "Missing approved column '{$col}' on table 'recurring_incidents'."
            );
        }
    }

    public function test_v02_model_fillable_and_casts_handle_phase7_fields(): void
    {
        $concern = $this->createConcern([
            'issue_category'             => 'IT and Technology',
            'issue_subcategory'          => 'Computer Hardware',
            'classification_explanation' => 'Diagnostic test explanation',
            'classification_confidence'  => 0.875,
            'ai_priority_explanation'    => 'Objective test priority rationale',
        ]);

        $fresh = $concern->fresh();
        $this->assertEquals('IT and Technology', $fresh->issue_category);
        $this->assertEquals('Computer Hardware', $fresh->issue_subcategory);
        $this->assertEquals('Diagnostic test explanation', $fresh->classification_explanation);
        $this->assertSame(0.875, $fresh->classification_confidence);
        $this->assertEquals('Objective test priority rationale', $fresh->ai_priority_explanation);
    }

    // ── 2. AI Classification Verification ────────────────────────────────────

    public function test_v03_classification_covers_all_required_problem_domains(): void
    {
        $service = app(LocalSentimentService::class);

        // 1. Computer hardware failure
        $c1 = $service->analyzeConcern('Desktop PC crashed', 'The computer powers off suddenly when booting Windows in Lab 1.');
        $this->assertEquals('IT and Technology', $c1['issue_category']);
        $this->assertEquals('Computer Hardware', $c1['issue_subcategory']);
        $this->assertGreaterThanOrEqual(0.60, $c1['classification_confidence']);

        // 2. Malware infection
        $c2 = $service->analyzeConcern('Malware infection detected', 'A ransomware virus is encrypting student assignment folders.');
        $this->assertEquals('IT and Technology', $c2['issue_category']);
        $this->assertEquals('Virus / Malware', $c2['issue_subcategory']);

        // 3. Network outage
        $c3 = $service->analyzeConcern('Wi-Fi connection dropped', 'The campus wifi network is disconnected and internet is unavailable.');
        $this->assertEquals('IT and Technology', $c3['issue_category']);
        $this->assertEquals('Network / Internet', $c3['issue_subcategory']);

        // 4. Software errors
        $c4 = $service->analyzeConcern('Compiler software bug', 'The IDE application throws operating system errors on launch.');
        $this->assertEquals('IT and Technology', $c4['issue_category']);
        $this->assertEquals('Software Problems', $c4['issue_subcategory']);

        // 5. Account access problems
        $c5 = $service->analyzeConcern('Student portal account locked', 'I cannot login to my student account because password reset fails.');
        $this->assertEquals('IT and Technology', $c5['issue_category']);
        $this->assertEquals('Account / Login Issues', $c5['issue_subcategory']);

        // 6. Classroom projector problems
        $c6 = $service->analyzeConcern('Projector not turning on', 'The lecture hall ceiling projector has no display signal and broken HDMI.');
        $this->assertEquals('Academic Concerns', $c6['issue_category']);
        $this->assertEquals('Classroom Equipment / AV', $c6['issue_subcategory']);

        // 7. Registrar and administrative concerns
        $c7 = $service->analyzeConcern('Transcript request delay', 'I requested my official transcript of records TOR from registrar two weeks ago.');
        $this->assertEquals('Non-Academic Concerns', $c7['issue_category']);
        $this->assertEquals('Registrar Services', $c7['issue_subcategory']);
    }

    public function test_v04_low_confidence_ambiguous_results_marked_for_human_review(): void
    {
        $service = app(LocalSentimentService::class);

        $result = $service->analyzeConcern(
            'General thoughts',
            'Just sharing a general perspective on university life with nothing specific broken.'
        );

        $this->assertEquals('Other / Needs Review', $result['issue_category']);
        $this->assertEquals('Uncategorized / Needs Review', $result['issue_subcategory']);
        $this->assertLessThanOrEqual(0.50, $result['classification_confidence']);
        $this->assertStringContainsString('Other / Needs Review', $result['classification_explanation']);
    }

    // ── 3. Content-Based Priority Assessment Verification ────────────────────

    public function test_v05_angry_student_with_minor_issue_does_not_get_critical(): void
    {
        $service = app(LocalSentimentService::class);

        $result = $service->analyzeConcern(
            'I AM FURIOUS AND DISGUSTED WITH THIS STUPID APP',
            'This is unacceptable, terrible, and horrible! The color on the student portal header is ugly and frustrating!'
        );

        // Highly negative emotional score
        $this->assertLessThan(-0.5, $result['sentiment_score']);
        // Must NOT receive critical priority
        $this->assertNotEquals('critical', $result['priority_suggestion']);
        $this->assertContains($result['priority_suggestion'], ['low', 'medium']);
    }

    public function test_v06_calm_student_reporting_spreading_malware_receives_critical(): void
    {
        $service = app(LocalSentimentService::class);

        $result = $service->analyzeConcern(
            'Observation regarding laboratory computers',
            'Good day. Please be informed that ransomware and malware is spreading across multiple lab computers encrypting data.'
        );

        // Neutral emotional tone
        $this->assertGreaterThanOrEqual(-0.2, $result['sentiment_score']);
        // Must receive critical priority due to objective hazard/threat
        $this->assertEquals('critical', $result['priority_suggestion']);
        $this->assertStringContainsString('Critical priority', $result['priority_explanation']);
    }

    public function test_v07_single_device_minor_error_does_not_become_critical(): void
    {
        $service = app(LocalSentimentService::class);

        $result = $service->analyzeConcern(
            'Mouse wheel unresponsive on PC 12',
            'The mouse scroll wheel on computer workstation 12 in Lab 3 is sticky. Other computers work fine.'
        );

        $this->assertNotEquals('critical', $result['priority_suggestion']);
        $this->assertNotEquals('high', $result['priority_suggestion']);
        $this->assertEquals('medium', $result['priority_suggestion']);
    }

    public function test_v08_campus_wide_essential_network_outage_receives_critical(): void
    {
        $service = app(LocalSentimentService::class);

        $result = $service->analyzeConcern(
            'Total network blackout',
            'The entire campus network is down and all buildings are without internet or server access.'
        );

        $this->assertEquals('critical', $result['priority_suggestion']);
        $this->assertStringContainsString('Institution-wide', $result['priority_explanation']);
    }

    // ── 4. AI Summary Verification & Fallback ────────────────────────────────

    public function test_v09_summary_is_concise_under_300_chars_and_identifies_problem_and_location(): void
    {
        $service = app(LocalSentimentService::class);

        $veryLongDescription = 'Greetings to the university support team. I am a third-year computer science student enrolled in Section 3A. During our scheduled practical examination session this afternoon at approximately 2:30 PM inside Computer Laboratory Room 302, workstation PC-18 experienced a sudden unexpected shutdown. The machine failed to restart when the power button was pressed and emitted a burning smell, causing our programming test progress to be interrupted completely. We attempted to inform the lab proctor but were advised to submit a formal incident report here for technical maintenance inspection.';

        $result = $service->analyzeConcern(
            'Workstation PC-18 power failure with odor in Room 302',
            $veryLongDescription
        );

        $summary = $result['summary'];

        // Capped at 300 chars
        $this->assertLessThanOrEqual(300, mb_strlen($summary));
        // Meaningfully shorter than original (~550 chars)
        $this->assertLessThan(mb_strlen($veryLongDescription), mb_strlen($summary));
        // Preserves key location and equipment
        $this->assertStringContainsString('Room 302', $summary);
        // Does not include conversational pleasantries
        $this->assertStringNotContainsString('Greetings to the university support team', $summary);
    }

    // ── 5. Recurrence Incident Clustering & Device Differentiation ───────────

    public function test_v10_distinct_devices_in_same_room_create_separate_incidents(): void
    {
        $recurrence = app(RecurrenceDetectionService::class);

        // Concern 1: PC issue in Room 401
        $concern1 = $this->createConcern([
            'student_id'        => $this->student->id,
            'title'             => 'Computer shutting down in Room 401',
            'description'       => 'Workstation PC 5 powers off during class in Room 401.',
            'issue_category'    => 'IT and Technology',
            'issue_subcategory' => 'Computer Hardware',
        ]);
        $recurrence->processConcern($concern1);

        $student2 = User::create([
            'name'              => 'Student Two',
            'email'             => 'student2_synth@test.local',
            'student_id_number' => 'SYNTH-002',
            'password'          => Hash::make('password'),
            'role'              => 'student',
            'academic_unit_id'  => $this->unitCcs->id,
        ]);

        // Concern 2: Projector issue in Room 401
        $concern2 = $this->createConcern([
            'student_id'        => $student2->id,
            'title'             => 'Projector display black in Room 401',
            'description'       => 'The ceiling projector HDMI input is black in Room 401.',
            'issue_category'    => 'Academic Concerns',
            'issue_subcategory' => 'Classroom Equipment / AV',
        ]);
        $recurrence->processConcern($concern2);

        $this->assertNotNull($concern1->fresh()->recurring_incident_id);
        $this->assertNotNull($concern2->fresh()->recurring_incident_id);
        $this->assertNotEquals(
            $concern1->fresh()->recurring_incident_id,
            $concern2->fresh()->recurring_incident_id,
            'Different devices in same room must NOT be clustered into the same incident.'
        );
    }

    // ── 6. Role Scoping, Visibility & SAO Option A Verification ──────────────

    public function test_v11_student_api_response_hides_sensitive_diagnostic_explanations(): void
    {
        $concern = $this->createConcern([
            'student_id'                  => $this->student->id,
            'title'                       => 'Login failed on portal',
            'description'                 => 'Cannot sign in to student portal.',
            'issue_category'              => 'IT and Technology',
            'issue_subcategory'           => 'Account / Login Issues',
            'classification_explanation'  => 'Sensitive diagnostic classification rule #94',
            'ai_priority_explanation'      => 'Sensitive priority algorithm score 0.42',
            'ai_summary'                  => 'Portal: Account login failed.',
        ]);

        $response = $this->actingAs($this->student, 'sanctum')->getJson("/api/concerns/{$concern->concern_id}");

        $response->assertStatus(200);
        $json = $response->json('concern');

        // Student can see public issue fields
        $this->assertEquals('IT and Technology', $json['issue_category']);
        $this->assertEquals('Account / Login Issues', $json['issue_subcategory']);
        $this->assertEquals('Portal: Account login failed.', $json['ai_summary']);

        // Sensitive internal explanations must NOT be exposed
        $this->assertArrayNotHasKey('classification_explanation', $json);
        $this->assertArrayNotHasKey('ai_priority_explanation', $json);
        $this->assertArrayNotHasKey('priority_override_reason', $json);
    }

    public function test_v12_sao_oversight_provides_dual_distribution_without_violating_option_a(): void
    {
        $this->createConcern([
            'issue_category'    => 'IT and Technology',
            'issue_subcategory' => 'Computer Hardware',
            'priority'          => 'medium',
        ]);

        $response = $this->actingAs($this->saoUser, 'sanctum')->getJson('/api/sao/oversight');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'summary',
            'academic_units',
            'departments',
            'categories',
            'issue_categories',
            'issue_subcategories',
            'dual_distribution' => [
                'by_organization',
                'by_issue_category',
            ],
        ]);

        // SAO cannot access individual academic concern detail (Option A)
        $academicConcern = $this->createConcern();
        $detailResponse = $this->actingAs($this->saoUser, 'sanctum')->getJson("/api/sao/concerns/{$academicConcern->concern_id}");
        $detailResponse->assertStatus(403);
    }
}
