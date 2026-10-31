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

class TaxonomyRefinementTest extends TestCase
{
    use RefreshDatabase;

    private User $student1;
    private User $student2;
    private AcademicUnit $unitCcs;
    private Department $deptSao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitCcs = AcademicUnit::create(['name' => 'College of Computer Studies', 'code' => 'CCS']);
        $this->deptSao = Department::create(['name' => 'Student Affairs Office', 'code' => 'SAO']);

        $this->student1 = User::create([
            'name'              => 'Student One',
            'email'             => 'student1_tax@test.local',
            'student_id_number' => 'TAX-001',
            'password'          => Hash::make('password'),
            'role'              => 'student',
            'academic_unit_id'  => $this->unitCcs->id,
        ]);

        $this->student2 = User::create([
            'name'              => 'Student Two',
            'email'             => 'student2_tax@test.local',
            'student_id_number' => 'TAX-002',
            'password'          => Hash::make('password'),
            'role'              => 'student',
            'academic_unit_id'  => $this->unitCcs->id,
        ]);
    }

    private function createConcern(array $attributes = []): Concern
    {
        $attrs = array_merge([
            'student_id'       => $this->student1->id,
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

    // ── 1. Taxonomy Classification Scenario Tests ────────────────────────────

    public function test_01_classroom_projector_has_no_display(): void
    {
        $service = app(LocalSentimentService::class);
        $result = $service->analyzeConcern(
            'Classroom projector has no display',
            'The ceiling projector in classroom 204 has no display signal and broken HDMI connection.'
        );

        $this->assertEquals('Academic Concerns', $result['issue_category']);
        $this->assertEquals('Classroom Equipment / AV', $result['issue_subcategory']);
    }

    public function test_02_laboratory_projector_is_flickering(): void
    {
        $service = app(LocalSentimentService::class);
        $result = $service->analyzeConcern(
            'Laboratory projector is flickering',
            'The projector in computer laboratory 3 is flickering on and off during class lectures.'
        );

        $this->assertEquals('Academic Concerns', $result['issue_category']);
        $this->assertEquals('Classroom Equipment / AV', $result['issue_subcategory']);
    }

    public function test_03_computer_monitor_is_broken(): void
    {
        $service = app(LocalSentimentService::class);
        $result = $service->analyzeConcern(
            'Computer monitor is broken',
            'The desktop PC monitor has a cracked screen and will not turn on.'
        );

        $this->assertEquals('IT and Technology', $result['issue_category']);
        $this->assertEquals('Computer Hardware', $result['issue_subcategory']);
    }

    public function test_04_printer_is_not_printing(): void
    {
        $service = app(LocalSentimentService::class);
        $result = $service->analyzeConcern(
            'Printer is not printing',
            'The office network printer is not printing any submitted documents and has paper jam.'
        );

        $this->assertEquals('IT and Technology', $result['issue_category']);
        $this->assertEquals('Printer / Peripheral Problems', $result['issue_subcategory']);
    }

    public function test_05_classroom_computer_has_malware(): void
    {
        $service = app(LocalSentimentService::class);
        $result = $service->analyzeConcern(
            'Classroom computer has malware',
            'The desktop computer in classroom 102 is infected with computer malware and virus popups.'
        );

        $this->assertEquals('IT and Technology', $result['issue_category']);
        $this->assertEquals('Virus / Malware', $result['issue_subcategory']);
    }

    public function test_06_classroom_internet_is_unavailable(): void
    {
        $service = app(LocalSentimentService::class);
        $result = $service->analyzeConcern(
            'Classroom internet is unavailable',
            'The classroom wifi and internet connection is completely down and disconnected.'
        );

        $this->assertEquals('IT and Technology', $result['issue_category']);
        $this->assertEquals('Network / Internet', $result['issue_subcategory']);
    }

    // ── 2. Recurring Incident Grouping Compatibility ─────────────────────────

    public function test_07_two_projector_concerns_in_same_classroom_are_grouped(): void
    {
        $recurrence = app(RecurrenceDetectionService::class);

        // Concern 1
        $concern1 = $this->createConcern([
            'student_id'        => $this->student1->id,
            'title'             => 'Classroom projector broken in Room 204',
            'description'       => 'The ceiling projector HDMI display is malfunctioning in Room 204.',
            'issue_category'    => 'Academic Concerns',
            'issue_subcategory' => 'Classroom Equipment / AV',
        ]);
        $recurrence->processConcern($concern1);

        // Concern 2
        $concern2 = $this->createConcern([
            'student_id'        => $this->student2->id,
            'title'             => 'Classroom projector broken in Room 204',
            'description'       => 'The ceiling projector HDMI display is malfunctioning in Room 204.',
            'issue_category'    => 'Academic Concerns',
            'issue_subcategory' => 'Classroom Equipment / AV',
        ]);
        $recurrence->processConcern($concern2);

        $this->assertNotNull($concern1->fresh()->recurring_incident_id);
        $this->assertNotNull($concern2->fresh()->recurring_incident_id);
        $this->assertEquals(
            $concern1->fresh()->recurring_incident_id,
            $concern2->fresh()->recurring_incident_id,
            'Two similar projector concerns in the same room must be grouped together.'
        );

        $incident = RecurringIncident::find($concern1->fresh()->recurring_incident_id);
        $this->assertEquals('Academic Concerns', $incident->issue_category);
        $this->assertEquals('Classroom Equipment / AV', $incident->issue_subcategory);
    }

    public function test_08_projector_issue_and_computer_hardware_issue_are_not_grouped(): void
    {
        $recurrence = app(RecurrenceDetectionService::class);

        // Projector concern in Room 204
        $concern1 = $this->createConcern([
            'student_id'        => $this->student1->id,
            'title'             => 'Classroom projector broken in Room 204',
            'description'       => 'The ceiling projector HDMI display is malfunctioning in Room 204.',
            'issue_category'    => 'Academic Concerns',
            'issue_subcategory' => 'Classroom Equipment / AV',
        ]);
        $recurrence->processConcern($concern1);

        // Computer hardware concern in Room 204
        $concern2 = $this->createConcern([
            'student_id'        => $this->student2->id,
            'title'             => 'Computer shutting down in Room 204',
            'description'       => 'Workstation PC 5 powers off during class in Room 204.',
            'issue_category'    => 'IT and Technology',
            'issue_subcategory' => 'Computer Hardware',
        ]);
        $recurrence->processConcern($concern2);

        $this->assertNotNull($concern1->fresh()->recurring_incident_id);
        $this->assertNotNull($concern2->fresh()->recurring_incident_id);
        $this->assertNotEquals(
            $concern1->fresh()->recurring_incident_id,
            $concern2->fresh()->recurring_incident_id,
            'A projector issue and a computer hardware issue in the same room must NOT be grouped.'
        );
    }
}
