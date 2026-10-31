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
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IntelligentClassificationAndSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $dean;
    private User $saoUser;
    private AcademicUnit $unitCcs;
    private Department $deptSao;
    private Department $deptAccounting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitCcs = AcademicUnit::create(['name' => 'College of Computer Studies', 'code' => 'CCS']);
        $this->deptSao = Department::create(['name' => 'Student Affairs Office', 'code' => 'SAO']);
        $this->deptAccounting = Department::create(['name' => 'Accounting Department', 'code' => 'ACCT']);

        $this->student = User::create([
            'name'              => 'Student Tester',
            'email'             => 'student@uclm.edu.ph',
            'student_id_number' => '2024-0001',
            'password'          => Hash::make('password'),
            'role'              => 'student',
            'academic_unit_id'  => $this->unitCcs->id,
        ]);

        $this->dean = User::create([
            'name'              => 'Dean CCS',
            'email'             => 'dean@uclm.edu.ph',
            'student_id_number' => '1000-0001',
            'password'          => Hash::make('password'),
            'role'              => 'dean',
            'academic_unit_id'  => $this->unitCcs->id,
        ]);

        $this->saoUser = User::create([
            'name'              => 'SAO Officer',
            'email'             => 'sao@uclm.edu.ph',
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
            'title'            => 'Sample title',
            'description'      => 'Sample description',
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

    public function test_01_classification_categorizes_hardware_and_malware_accurately(): void
    {
        $service = app(LocalSentimentService::class);

        // Hardware test
        $hwResult = $service->analyzeConcern(
            'Computer shutting down in Lab 2',
            'The desktop PC keeps powering off unexpectedly while booting Windows.'
        );
        $this->assertEquals('IT and Technology', $hwResult['issue_category']);
        $this->assertEquals('Computer Hardware', $hwResult['issue_subcategory']);
        $this->assertGreaterThanOrEqual(0.65, $hwResult['classification_confidence']);
        $this->assertStringContainsString('Computer Hardware', $hwResult['classification_explanation']);

        // Malware test
        $malwareResult = $service->analyzeConcern(
            'Suspicious ransomware files detected',
            'A virus infection is locking student files and popping up warning screens.'
        );
        $this->assertEquals('IT and Technology', $malwareResult['issue_category']);
        $this->assertEquals('Virus / Malware', $malwareResult['issue_subcategory']);
    }

    public function test_02_classification_categorizes_non_academic_and_academic_domains(): void
    {
        $service = app(LocalSentimentService::class);

        // Cashier/Payment test
        $cashierResult = $service->analyzeConcern(
            'Tuition fee official receipt discrepancy',
            'My payment was processed at the cashier counter but the balance is still showing unpaid.'
        );
        $this->assertEquals('Non-Academic Concerns', $cashierResult['issue_category']);
        $this->assertEquals('Cashier / Payment', $cashierResult['issue_subcategory']);

        // Registrar test
        $regResult = $service->analyzeConcern(
            'Delayed transcript of records TOR request',
            'I submitted a request at the registrar for my official transcript and diploma copy.'
        );
        $this->assertEquals('Non-Academic Concerns', $regResult['issue_category']);
        $this->assertEquals('Registrar Services', $regResult['issue_subcategory']);

        // Classroom test
        $classResult = $service->analyzeConcern(
            'Broken whiteboard and chairs in classroom 402',
            'The lecture room whiteboard is stained and several chairs have missing desks.'
        );
        $this->assertEquals('Academic Concerns', $classResult['issue_category']);
        $this->assertEquals('Classroom Issues', $classResult['issue_subcategory']);
    }

    public function test_03_urgent_wording_alone_does_not_trigger_critical_priority(): void
    {
        $service = app(LocalSentimentService::class);

        $result = $service->analyzeConcern(
            'URGENT ASAP PLEASE HELP!!!',
            'I have a quick general question about how to apply for clearance next semester.'
        );

        $this->assertNotEquals('critical', $result['priority_suggestion']);
        $this->assertEquals('low', $result['priority_suggestion']);
        $this->assertStringContainsString('Low priority', $result['priority_explanation']);
    }

    public function test_04_angry_tone_alone_does_not_trigger_critical_priority(): void
    {
        $service = app(LocalSentimentService::class);

        $result = $service->analyzeConcern(
            'Extremely frustrated and furious about the air conditioning',
            'I am absolutely disgusted and angry. The aircon is lukewarm in room 201 and it is very uncomfortable.'
        );

        $this->assertNotEquals('critical', $result['priority_suggestion']);
        $this->assertEquals('medium', $result['priority_suggestion']);
        $this->assertLessThan(0, $result['sentiment_score']);
    }

    public function test_05_safety_hazard_without_urgent_keywords_triggers_critical_priority(): void
    {
        $service = app(LocalSentimentService::class);

        $result = $service->analyzeConcern(
            'Exposed live electrical wiring in Science Lab 3',
            'There is an exposed wire near the water sink that produces visible electrical sparks during class.'
        );

        $this->assertEquals('critical', $result['priority_suggestion']);
        $this->assertStringContainsString('Immediate safety hazard', $result['priority_explanation']);
    }

    public function test_06_department_wide_disruption_triggers_high_priority(): void
    {
        $service = app(LocalSentimentService::class);

        $result = $service->analyzeConcern(
            'All computers in lab failing to boot',
            'The entire lab is down and all computers in lab 1 cannot start before our scheduled lab exam.'
        );

        $this->assertEquals('high', $result['priority_suggestion']);
        $this->assertStringContainsString('High priority', $result['priority_explanation']);
    }

    public function test_07_summary_is_concise_under_300_chars_and_identifies_location_and_problem(): void
    {
        $service = app(LocalSentimentService::class);

        $longDescription = 'Good morning dear university administration, I am writing this to formally inform you that inside Lab 3, computer workstation PC 14 is continually crashing whenever students launch compilers. We have been experiencing this for two days and it hampers our programming assignments significantly.';

        $result = $service->analyzeConcern(
            'Computer crashing repeatedly',
            $longDescription
        );

        $this->assertLessThanOrEqual(300, mb_strlen($result['summary']));
        $this->assertGreaterThan(10, mb_strlen($result['summary']));
        $this->assertStringContainsString('Lab 3', $result['summary']);
        $this->assertStringNotContainsString('Good morning dear university administration', $result['summary']);
    }

    public function test_08_recurrence_clustering_separates_different_devices_in_same_room(): void
    {
        $recurrence = app(RecurrenceDetectionService::class);

        // Concern 1: Computer issue in Room 301
        $concern1 = $this->createConcern([
            'student_id'        => $this->student->id,
            'title'             => 'Computer shutting down in Room 301',
            'description'       => 'The desktop PC workstation in Room 301 turns off when opening applications.',
            'issue_category'    => 'IT and Technology',
            'issue_subcategory' => 'Computer Hardware',
        ]);
        $recurrence->processConcern($concern1);

        // Concern 2: Projector issue in Room 301
        $student2 = User::create([
            'name'              => 'Student Two',
            'email'             => 'student2@uclm.edu.ph',
            'student_id_number' => '2024-0002',
            'password'          => Hash::make('password'),
            'role'              => 'student',
            'academic_unit_id'  => $this->unitCcs->id,
        ]);

        $concern2 = $this->createConcern([
            'student_id'        => $student2->id,
            'title'             => 'Projector display failing in Room 301',
            'description'       => 'The ceiling projector HDMI display is flickering and not projecting in Room 301.',
            'issue_category'    => 'Academic Concerns',
            'issue_subcategory' => 'Classroom Equipment / AV',
        ]);
        $recurrence->processConcern($concern2);

        $this->assertNotNull($concern1->fresh()->recurring_incident_id);
        $this->assertNotNull($concern2->fresh()->recurring_incident_id);
        $this->assertNotEquals(
            $concern1->fresh()->recurring_incident_id,
            $concern2->fresh()->recurring_incident_id,
            'Different devices and subcategories in the same room must create separate recurring incidents.'
        );

        $incident1 = RecurringIncident::find($concern1->fresh()->recurring_incident_id);
        $this->assertEquals('Computer Hardware', $incident1->issue_subcategory);

        $incident2 = RecurringIncident::find($concern2->fresh()->recurring_incident_id);
        $this->assertEquals('Classroom Equipment / AV', $incident2->issue_subcategory);
    }

    public function test_09_student_submission_populates_phase7_classification_fields(): void
    {
        $response = $this->actingAs($this->student, 'sanctum')->postJson('/api/concerns', [
            'title'         => 'Broken keyboard and mouse in Lab 2',
            'description'   => 'The computer keyboard has stuck keys and the mouse does not move.',
            'workflow_type' => 'academic',
        ]);

        $response->assertStatus(201);
        $concernId = $response->json('concern.concern_id');

        $concern = Concern::find($concernId);
        $this->assertNotNull($concern);
        $this->assertEquals('IT and Technology', $concern->issue_category);
        $this->assertEquals('Computer Hardware', $concern->issue_subcategory);
        $this->assertNotNull($concern->classification_explanation);
        $this->assertNotNull($concern->ai_priority_explanation);
        $this->assertNotNull($concern->ai_summary);
        $this->assertLessThanOrEqual(300, mb_strlen($concern->ai_summary));
    }

    public function test_10_student_show_exposes_classification_and_summary_but_hides_internal_explanations(): void
    {
        $concern = $this->createConcern([
            'student_id'                  => $this->student->id,
            'title'                       => 'Network connectivity lost in Lab 4',
            'description'                 => 'The internet and wifi connection dropped across the room.',
            'issue_category'              => 'IT and Technology',
            'issue_subcategory'           => 'Network / Internet',
            'classification_explanation'  => 'Sensitive internal keyword diagnostic',
            'ai_priority_explanation'      => 'Sensitive internal priority calculation formula',
            'ai_summary'                  => 'Lab 4: Internet dropped across room.',
        ]);

        $response = $this->actingAs($this->student, 'sanctum')->getJson("/api/concerns/{$concern->concern_id}");

        $response->assertStatus(200);
        $response->assertJsonPath('concern.issue_category', 'IT and Technology');
        $response->assertJsonPath('concern.issue_subcategory', 'Network / Internet');
        $response->assertJsonPath('concern.ai_summary', 'Lab 4: Internet dropped across room.');

        // Internal sensitive explanation fields must be hidden from student
        $this->assertArrayNotHasKey('classification_explanation', $response->json('concern'));
        $this->assertArrayNotHasKey('ai_priority_explanation', $response->json('concern'));
        $this->assertArrayNotHasKey('priority_override_reason', $response->json('concern'));
    }

    public function test_11_sao_oversight_provides_dual_distribution_breakdown(): void
    {
        $this->createConcern([
            'student_id'        => $this->student->id,
            'title'             => 'Lab computer blue screen',
            'description'       => 'PC crashes on boot in Lab 1.',
            'issue_category'    => 'IT and Technology',
            'issue_subcategory' => 'Computer Hardware',
        ]);

        $response = $this->actingAs($this->saoUser, 'sanctum')->getJson('/api/sao/oversight');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'summary',
            'categories',
            'issue_categories',
            'issue_subcategories',
            'dual_distribution' => [
                'by_organization',
                'by_issue_category',
            ],
        ]);

        $issueCats = collect($response->json('issue_categories'));
        $this->assertTrue($issueCats->contains('category', 'IT and Technology'));
    }

    public function test_12_manual_priority_override_precedence_preserved_on_student_edit(): void
    {
        $concern = $this->createConcern([
            'student_id'                 => $this->student->id,
            'title'                      => 'Initial minor concern',
            'description'                => 'Initial minor details.',
            'priority'                   => 'critical', // Overridden by Dean
            'ai_priority'                => 'low',
            'is_priority_overridden'     => true,
            'priority_override_reason'   => 'Dean escalated for audit compliance.',
            'priority_overridden_by'     => $this->dean->id,
            'priority_overridden_at'     => now(),
        ]);

        // Student edits the concern
        $response = $this->actingAs($this->student, 'sanctum')->putJson("/api/concerns/{$concern->concern_id}", [
            'title'         => 'Minor question about schedule',
            'description'   => 'How to check my class schedule please?',
            'workflow_type' => 'academic',
        ]);

        $response->assertStatus(200);

        $fresh = $concern->fresh();
        // Effective priority remains manually overridden critical
        $this->assertEquals('critical', $fresh->priority);
        $this->assertTrue($fresh->is_priority_overridden);
        // AI priority was updated to low
        $this->assertEquals('low', $fresh->ai_priority);
    }
}
