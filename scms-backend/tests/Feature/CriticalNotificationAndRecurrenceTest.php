<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Concern;
use App\Models\Department;
use App\Models\RecurringIncident;
use App\Models\RecurringIncidentHistory;
use App\Models\User;
use App\Notifications\ConcernWorkflowNotification;
use App\Services\RecurrenceDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CriticalNotificationAndRecurrenceTest extends TestCase
{
    use RefreshDatabase;

    private User $sao1;
    private User $sao2;
    private User $inactiveSao;
    private User $admin;
    private User $deanA;
    private User $deanB;
    private User $staffA;
    private User $staffB;
    private User $staffNonAcademic;
    private User $student1;
    private User $student2;
    private AcademicUnit $unitA;
    private AcademicUnit $unitB;
    private Department $deptAccounting;
    private Department $deptRegistrar;
    private Department $deptSao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitA = AcademicUnit::create(['name' => 'College of Computer Studies', 'code' => 'CCS']);
        $this->unitB = AcademicUnit::create(['name' => 'College of Engineering', 'code' => 'COE']);

        $this->deptAccounting = Department::create(['name' => 'Accounting Department', 'code' => 'ACCT']);
        $this->deptRegistrar = Department::create(['name' => 'Registrar Office', 'code' => 'REG']);
        $this->deptSao = Department::create(['name' => 'Student Affairs Office', 'code' => 'SAO']);

        $this->sao1 = User::create([
            'name' => 'SAO Officer 1',
            'email' => 'sao1@test.com',
            'password' => Hash::make('password'),
            'role' => 'sao',
            'dept_id' => $this->deptSao->id,
            'is_active' => true,
        ]);

        $this->sao2 = User::create([
            'name' => 'SAO Officer 2',
            'email' => 'sao2@test.com',
            'password' => Hash::make('password'),
            'role' => 'sao',
            'dept_id' => $this->deptSao->id,
            'is_active' => true,
        ]);

        $this->inactiveSao = User::create([
            'name' => 'Inactive SAO',
            'email' => 'inactivesao@test.com',
            'password' => Hash::make('password'),
            'role' => 'sao',
            'dept_id' => $this->deptSao->id,
            'is_active' => false,
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $this->deanA = User::create([
            'name' => 'Dean CCS',
            'email' => 'dean_ccs@test.com',
            'password' => Hash::make('password'),
            'role' => 'dean',
            'academic_unit_id' => $this->unitA->id,
        ]);

        $this->deanB = User::create([
            'name' => 'Dean COE',
            'email' => 'dean_coe@test.com',
            'password' => Hash::make('password'),
            'role' => 'dean',
            'academic_unit_id' => $this->unitB->id,
        ]);

        $this->staffA = User::create([
            'name' => 'Staff CCS',
            'email' => 'staff_ccs@test.com',
            'password' => Hash::make('password'),
            'role' => 'dept_staff',
            'academic_unit_id' => $this->unitA->id,
        ]);

        $this->staffB = User::create([
            'name' => 'Staff COE',
            'email' => 'staff_coe@test.com',
            'password' => Hash::make('password'),
            'role' => 'dept_staff',
            'academic_unit_id' => $this->unitB->id,
        ]);

        $this->staffNonAcademic = User::create([
            'name' => 'Staff Accounting',
            'email' => 'staff_acct@test.com',
            'password' => Hash::make('password'),
            'role' => 'dept_staff',
            'dept_id' => $this->deptAccounting->id,
        ]);

        $this->student1 = User::create([
            'name' => 'Student Alice',
            'email' => 'alice@test.com',
            'password' => Hash::make('password'),
            'role' => 'student',
            'academic_unit_id' => $this->unitA->id,
            'student_id_number' => 'STU-001',
        ]);

        $this->student2 = User::create([
            'name' => 'Student Bob',
            'email' => 'bob@test.com',
            'password' => Hash::make('password'),
            'role' => 'student',
            'academic_unit_id' => $this->unitA->id,
            'student_id_number' => 'STU-002',
        ]);
    }

    /**
     * Helper to create student users
     */
    private function createStudent(int $index, ?int $unitId = null): User
    {
        return User::create([
            'name' => "Student {$index}",
            'email' => "student{$index}_" . uniqid() . "@test.com",
            'password' => Hash::make('password'),
            'role' => 'student',
            'academic_unit_id' => $unitId ?? $this->unitA->id,
            'student_id_number' => "STU-" . str_pad((string)$index, 4, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * Helper to create concern
     */
    private function createConcern(array $attributes = []): Concern
    {
        $attrs = array_merge([
            'student_id' => $this->student1->id,
            'title' => 'Aircon malfunction in Laboratory Room 301',
            'description' => 'The aircon is broken and blowing hot air during class in Room 301.',
            'category' => 'academic_units',
            'workflow_type' => 'academic',
            'target_type' => 'department',
            'academic_unit_id' => $this->unitA->id,
            'dept_id' => null,
            'status' => 'pending',
            'priority' => 'medium',
            'submitted_at' => now(),
            'last_updated' => now(),
        ], $attributes);

        // SQLite in-memory test database has a NOT NULL constraint on concerns.academic_unit_id
        if (empty($attrs['academic_unit_id'])) {
            $attrs['academic_unit_id'] = $this->unitA->id;
        }

        return Concern::create($attrs);
    }

    // ── Similarity & Grouping Tests (1 - 8) ──────────────────────────────────

    public function test_01_two_concerns_with_identical_keywords_in_same_unit_are_grouped()
    {
        $service = app(RecurrenceDetectionService::class);

        $concern1 = $this->createConcern([
            'title' => 'Projector broken in Lecture Hall 204',
            'description' => 'The ceiling projector HDMI port is malfunctioning in Lecture Hall 204.',
        ]);
        $service->processConcern($concern1);

        $concern2 = $this->createConcern([
            'student_id' => $this->student2->id,
            'title' => 'Projector broken in Lecture Hall 204',
            'description' => 'The ceiling projector HDMI port is malfunctioning in Lecture Hall 204.',
        ]);
        $service->processConcern($concern2);

        $this->assertNotNull($concern1->fresh()->recurring_incident_id);
        $this->assertNotNull($concern2->fresh()->recurring_incident_id);
        $this->assertEquals($concern1->fresh()->recurring_incident_id, $concern2->fresh()->recurring_incident_id);
    }

    public function test_02_two_concerns_with_different_keywords_not_grouped()
    {
        $service = app(RecurrenceDetectionService::class);

        $concern1 = $this->createConcern([
            'title' => 'Broken air conditioning in lab',
            'description' => 'The room temperature is excessively hot and aircon is not cooling.',
        ]);
        $service->processConcern($concern1);

        $concern2 = $this->createConcern([
            'student_id' => $this->student2->id,
            'title' => 'Missing textbook in college library reserve',
            'description' => 'The required algorithm textbook is unavailable for checkout.',
        ]);
        $service->processConcern($concern2);

        $this->assertNotEquals(
            $concern1->fresh()->recurring_incident_id,
            $concern2->fresh()->recurring_incident_id
        );
    }

    public function test_03_two_concerns_with_similar_text_in_different_departments_not_grouped()
    {
        $service = app(RecurrenceDetectionService::class);

        $concern1 = $this->createConcern([
            'workflow_type' => 'non_academic',
            'category' => 'operational_departments',
            'academic_unit_id' => null,
            'dept_id' => $this->deptAccounting->id,
            'title' => 'Long queue and delayed processing at payment counter',
            'description' => 'Waiting in line for tuition fee assessment takes more than three hours.',
        ]);
        $service->processConcern($concern1);

        $concern2 = $this->createConcern([
            'student_id' => $this->student2->id,
            'workflow_type' => 'non_academic',
            'category' => 'operational_departments',
            'academic_unit_id' => null,
            'dept_id' => $this->deptRegistrar->id,
            'title' => 'Long queue and delayed processing at counter',
            'description' => 'Waiting in line for transcript assessment takes more than three hours.',
        ]);
        $service->processConcern($concern2);

        $this->assertNotEquals(
            $concern1->fresh()->recurring_incident_id,
            $concern2->fresh()->recurring_incident_id
        );
    }

    public function test_04_two_concerns_with_similar_text_in_different_academic_units_not_grouped()
    {
        $service = app(RecurrenceDetectionService::class);

        $concern1 = $this->createConcern([
            'academic_unit_id' => $this->unitA->id,
            'title' => 'Faculty instructor absent for three consecutive weeks',
            'description' => 'Our professor has not attended any lecture sessions this month.',
        ]);
        $service->processConcern($concern1);

        $concern2 = $this->createConcern([
            'student_id' => $this->student2->id,
            'academic_unit_id' => $this->unitB->id,
            'title' => 'Faculty instructor absent for three consecutive weeks',
            'description' => 'Our professor has not attended any lecture sessions this month.',
        ]);
        $service->processConcern($concern2);

        $this->assertNotEquals(
            $concern1->fresh()->recurring_incident_id,
            $concern2->fresh()->recurring_incident_id
        );
    }

    public function test_05_entity_conflicts_such_as_room_numbers_not_grouped()
    {
        $service = app(RecurrenceDetectionService::class);

        $concern1 = $this->createConcern([
            'title' => 'Aircon unit leaking water heavily in room 301',
            'description' => 'The aircon in room 301 is pouring water over the student desks.',
        ]);
        $service->processConcern($concern1);

        $concern2 = $this->createConcern([
            'student_id' => $this->student2->id,
            'title' => 'Aircon unit leaking water heavily in room 402',
            'description' => 'The aircon in room 402 is pouring water over the student desks.',
        ]);
        $service->processConcern($concern2);

        // Conflict on Room 301 vs Room 402 must prevent grouping into same incident
        $this->assertNotEquals(
            $concern1->fresh()->recurring_incident_id,
            $concern2->fresh()->recurring_incident_id
        );
    }

    public function test_06_entity_conflicts_with_course_codes_not_grouped()
    {
        $service = app(RecurrenceDetectionService::class);

        $concern1 = $this->createConcern([
            'title' => 'Final examination conflict schedule for CS101',
            'description' => 'The final exam schedule overlaps with laboratory class for CS101.',
        ]);
        $service->processConcern($concern1);

        $concern2 = $this->createConcern([
            'student_id' => $this->student2->id,
            'title' => 'Final examination conflict schedule for CS201',
            'description' => 'The final exam schedule overlaps with laboratory class for CS201.',
        ]);
        $service->processConcern($concern2);

        // Course code conflict (CS101 vs CS201) must prevent grouping
        $this->assertNotEquals(
            $concern1->fresh()->recurring_incident_id,
            $concern2->fresh()->recurring_incident_id
        );
    }

    public function test_07_conservative_fallback_on_gemini_failure_prevents_false_positive_grouping()
    {
        $service = app(RecurrenceDetectionService::class);

        // Two concerns that have borderline textual token overlap below 0.60
        $concern1 = $this->createConcern([
            'title' => 'Library internet connection drops during peak afternoon hours',
            'description' => 'Wi-Fi network signal repeatedly disconnects on the second floor study area.',
        ]);
        $service->processConcern($concern1);

        $concern2 = $this->createConcern([
            'student_id' => $this->student2->id,
            'title' => 'Computer lab workstation keyboards unresponsive',
            'description' => 'The hardware peripherals on terminal numbers 12 and 15 are broken.',
        ]);
        $service->processConcern($concern2);

        $this->assertNotEquals(
            $concern1->fresh()->recurring_incident_id,
            $concern2->fresh()->recurring_incident_id
        );
    }

    public function test_08_original_concerns_preserved_with_own_ids_timestamps_and_full_text()
    {
        $service = app(RecurrenceDetectionService::class);

        $submittedTime = now()->subMinutes(15);
        $concern = $this->createConcern([
            'title' => 'Network switch failed in server rack',
            'description' => 'All ethernet ports on rack 3 are completely offline.',
            'submitted_at' => $submittedTime,
        ]);
        $originalId = $concern->concern_id;
        $originalDesc = $concern->description;

        $service->processConcern($concern);

        $fresh = Concern::find($originalId);
        $this->assertNotNull($fresh);
        $this->assertEquals($originalId, $fresh->concern_id);
        $this->assertEquals($originalDesc, $fresh->description);
        $this->assertEquals($submittedTime->timestamp, $fresh->submitted_at->timestamp);
        $this->assertNotNull($fresh->recurring_incident_id);
    }

    // ── Counting & Escalation Tests (9 - 17) ──────────────────────────────────

    public function test_09_distinct_student_count_accurately_counts_unique_students_not_total_reports()
    {
        $service = app(RecurrenceDetectionService::class);

        // 3 reports by student1, 2 reports by student2
        for ($i = 0; $i < 3; $i++) {
            $c = $this->createConcern([
                'student_id' => $this->student1->id,
                'title' => 'Repeated power surge in science laboratory',
                'description' => 'Equipment shutting down due to unstable voltage in science laboratory.',
            ]);
            $service->processConcern($c);
        }

        for ($i = 0; $i < 2; $i++) {
            $c = $this->createConcern([
                'student_id' => $this->student2->id,
                'title' => 'Repeated power surge in science laboratory',
                'description' => 'Equipment shutting down due to unstable voltage in science laboratory.',
            ]);
            $service->processConcern($c);
        }

        $incident = RecurringIncident::first();
        $this->assertNotNull($incident);
        $this->assertEquals(2, $incident->unique_students_count);
        $this->assertEquals(5, $incident->total_reports_count);
    }

    public function test_10_multiple_reports_from_same_student_count_as_one_toward_threshold()
    {
        $service = app(RecurrenceDetectionService::class);

        for ($i = 0; $i < 12; $i++) {
            $c = $this->createConcern([
                'student_id' => $this->student1->id,
                'title' => 'Water dispenser empty in academic lobby',
                'description' => 'The drinking station has been empty since Monday in academic lobby.',
            ]);
            $service->processConcern($c);
        }

        $incident = RecurringIncident::first();
        $this->assertNotNull($incident);
        $this->assertEquals(1, $incident->unique_students_count);
        $this->assertEquals(12, $incident->total_reports_count);
        $this->assertEquals('normal', $incident->recurrence_tier);
    }

    public function test_11_escalation_to_high_tier_at_exactly_ten_unique_students()
    {
        $service = app(RecurrenceDetectionService::class);

        for ($i = 1; $i <= 10; $i++) {
            $stu = $this->createStudent($i);
            $c = $this->createConcern([
                'student_id' => $stu->id,
                'title' => 'Broken glass window in study lounge room 202',
                'description' => 'A large crack on the window poses a hazard in study lounge room 202.',
            ]);
            $service->processConcern($c);
        }

        $incident = RecurringIncident::first();
        $this->assertNotNull($incident);
        $this->assertEquals(10, $incident->unique_students_count);
        $this->assertEquals('high', $incident->recurrence_tier);
        $this->assertEquals('high', $incident->priority);
    }

    public function test_12_escalation_to_critical_tier_at_exactly_twenty_unique_students()
    {
        $service = app(RecurrenceDetectionService::class);

        for ($i = 1; $i <= 20; $i++) {
            $stu = $this->createStudent($i);
            $c = $this->createConcern([
                'student_id' => $stu->id,
                'title' => 'Chemical smell originating from storage cabinet room 105',
                'description' => 'Strong volatile vapors leaking from room 105 storage cabinet.',
            ]);
            $service->processConcern($c);
        }

        $incident = RecurringIncident::first();
        $this->assertNotNull($incident);
        $this->assertEquals(20, $incident->unique_students_count);
        $this->assertEquals('critical', $incident->recurrence_tier);
        $this->assertEquals('critical', $incident->priority);
    }

    public function test_13_escalation_to_major_critical_tier_at_exactly_thirty_unique_students()
    {
        $service = app(RecurrenceDetectionService::class);

        for ($i = 1; $i <= 30; $i++) {
            $stu = $this->createStudent($i);
            $c = $this->createConcern([
                'student_id' => $stu->id,
                'title' => 'Structural ceiling plaster collapse hazard main auditorium',
                'description' => 'Pieces of concrete and ceiling plaster fell during assembly in main auditorium.',
            ]);
            $service->processConcern($c);
        }

        $incident = RecurringIncident::first();
        $this->assertNotNull($incident);
        $this->assertEquals(30, $incident->unique_students_count);
        $this->assertEquals('major_critical', $incident->recurrence_tier);
        $this->assertEquals('critical', $incident->priority);
    }

    public function test_14_linked_open_concerns_have_priority_escalated_to_high_at_ten_students()
    {
        $service = app(RecurrenceDetectionService::class);

        $concerns = [];
        for ($i = 1; $i <= 10; $i++) {
            $stu = $this->createStudent($i);
            $c = $this->createConcern([
                'student_id' => $stu->id,
                'priority' => 'low',
                'title' => 'Cafeteria food poisoning complaints cafeteria area',
                'description' => 'Multiple students experiencing stomach ache after eating lunch in cafeteria area.',
            ]);
            $service->processConcern($c);
            $concerns[] = $c;
        }

        $firstConcern = $concerns[0]->fresh();
        $this->assertEquals('high', $firstConcern->priority);
    }

    public function test_15_linked_open_concerns_have_priority_escalated_to_critical_at_twenty_students()
    {
        $service = app(RecurrenceDetectionService::class);

        $concerns = [];
        for ($i = 1; $i <= 20; $i++) {
            $stu = $this->createStudent($i);
            $c = $this->createConcern([
                'student_id' => $stu->id,
                'priority' => 'medium',
                'title' => 'Elevator sudden drop and mechanical fault building B',
                'description' => 'Building B passenger elevator experienced an abrupt drop of one floor.',
            ]);
            $service->processConcern($c);
            $concerns[] = $c;
        }

        $firstConcern = $concerns[0]->fresh();
        $this->assertEquals('critical', $firstConcern->priority);
    }

    public function test_16_manually_overridden_concerns_do_not_have_priority_changed_by_escalation()
    {
        $service = app(RecurrenceDetectionService::class);

        // Concern 1 is manually overridden to Medium
        $c1 = $this->createConcern([
            'student_id' => $this->student1->id,
            'title' => 'Electrical spark observed behind laboratory breaker',
            'description' => 'Visible sparks shooting out from circuit breaker panel in laboratory.',
            'priority' => 'medium',
            'is_priority_overridden' => true,
            'priority_override_reason' => 'Technician scheduled tomorrow morning.',
            'priority_overridden_by' => $this->deanA->id,
            'priority_overridden_at' => now(),
        ]);
        $service->processConcern($c1);

        // Add 19 more unique students to escalate cluster to Critical (20 students)
        for ($i = 2; $i <= 20; $i++) {
            $stu = $this->createStudent($i);
            $c = $this->createConcern([
                'student_id' => $stu->id,
                'title' => 'Electrical spark observed behind laboratory breaker',
                'description' => 'Visible sparks shooting out from circuit breaker panel in laboratory.',
            ]);
            $service->processConcern($c);
        }

        // C1 must preserve Medium because of manual override
        $this->assertEquals('medium', $c1->fresh()->priority);
        $this->assertTrue($c1->fresh()->is_priority_overridden);
    }

    public function test_17_reports_older_than_seven_days_do_not_count_toward_active_threshold()
    {
        $service = app(RecurrenceDetectionService::class);

        // 8 reports submitted 10 days ago (outside 7-day window)
        for ($i = 1; $i <= 8; $i++) {
            $stu = $this->createStudent($i);
            $c = $this->createConcern([
                'student_id' => $stu->id,
                'submitted_at' => now()->subDays(10),
                'title' => 'Library heating system blowing cold air',
                'description' => 'The heating system in the library is failing during winter mornings.',
            ]);
            $service->processConcern($c);
        }

        // 3 reports submitted today (inside 7-day window)
        for ($i = 9; $i <= 11; $i++) {
            $stu = $this->createStudent($i);
            $c = $this->createConcern([
                'student_id' => $stu->id,
                'submitted_at' => now(),
                'title' => 'Library heating system blowing cold air',
                'description' => 'The heating system in the library is failing during winter mornings.',
            ]);
            $service->processConcern($c);
        }

        $incident = RecurringIncident::first();
        $this->assertNotNull($incident);
        // Only the 3 recent reports count toward rolling active unique count
        $this->assertEquals(3, $incident->unique_students_count);
        $this->assertEquals(11, $incident->total_reports_count);
        $this->assertEquals('normal', $incident->recurrence_tier);
    }

    // ── Notifications Tests (18 - 30) ────────────────────────────────────────

    public function test_18_critical_concern_submission_notifies_assigned_department_staff()
    {
        Notification::fake();

        $response = $this->actingAs($this->student1)->postJson('/api/concerns', [
            'title' => 'Active electrical fire in building 1',
            'description' => 'Emergency: flames shooting from electrical room in building 1.',
            'workflow_type' => 'academic',
        ]);
        $response->assertStatus(201);

        $concern = Concern::latest('concern_id')->first();
        $concern->update(['priority' => 'critical']);
        ConcernWorkflowNotification::sendCriticalConcernNotification($concern, 'Initial Submission');

        Notification::assertSentTo($this->staffA, ConcernWorkflowNotification::class);
    }

    public function test_19_critical_concern_submission_notifies_dean_if_academic_workflow()
    {
        Notification::fake();

        $concern = $this->createConcern([
            'workflow_type' => 'academic',
            'academic_unit_id' => $this->unitA->id,
            'priority' => 'critical',
        ]);

        ConcernWorkflowNotification::sendCriticalConcernNotification($concern, 'Initial Submission');

        Notification::assertSentTo($this->deanA, ConcernWorkflowNotification::class);
        Notification::assertNotSentTo($this->deanB, ConcernWorkflowNotification::class);
    }

    public function test_20_critical_concern_submission_notifies_all_active_sao_users()
    {
        Notification::fake();

        $concern = $this->createConcern(['priority' => 'critical']);

        ConcernWorkflowNotification::sendCriticalConcernNotification($concern, 'Initial Submission');

        Notification::assertSentTo($this->sao1, ConcernWorkflowNotification::class);
        Notification::assertSentTo($this->sao2, ConcernWorkflowNotification::class);
    }

    public function test_21_sao_priority_override_to_critical_triggers_critical_notifications()
    {
        Notification::fake();

        $concern = $this->createConcern([
            'dept_id' => $this->deptSao->id,
            'academic_unit_id' => null,
            'workflow_type' => 'non_academic',
            'category' => 'operational_departments',
            'priority' => 'low',
        ]);

        $res = $this->actingAs($this->sao1)->postJson("/api/sao/concerns/{$concern->concern_id}/priority-override", [
            'priority' => 'critical',
            'reason' => 'Escalated due to direct student safety risk.',
        ]);
        $res->assertStatus(200);

        Notification::assertSentTo($this->sao1, ConcernWorkflowNotification::class);
        Notification::assertSentTo($this->sao2, ConcernWorkflowNotification::class);
    }

    public function test_22_dean_priority_override_to_critical_triggers_critical_notifications()
    {
        Notification::fake();

        $concern = $this->createConcern([
            'academic_unit_id' => $this->unitA->id,
            'status' => 'escalated',
            'priority' => 'medium',
        ]);

        $res = $this->actingAs($this->deanA)->postJson("/api/dean/concerns/{$concern->concern_id}/priority-override", [
            'priority' => 'critical',
            'reason' => 'Immediate administrative attention warranted.',
        ]);
        $res->assertStatus(200);

        Notification::assertSentTo($this->staffA, ConcernWorkflowNotification::class);
        Notification::assertSentTo($this->deanA, ConcernWorkflowNotification::class);
        Notification::assertSentTo($this->sao1, ConcernWorkflowNotification::class);
    }

    public function test_23_ai_reanalysis_resulting_in_critical_triggers_critical_notifications()
    {
        Notification::fake();

        $concern = $this->createConcern(['priority' => 'low']);

        // Dispatch Job
        $job = new \App\Jobs\AnalyzeConcernSentiment($concern);
        $mockService = \Mockery::mock(\App\Services\LocalSentimentService::class);
        $mockService->shouldReceive('analyzeConcern')->andReturn([
            'sentiment_score' => -0.9,
            'sentiment_label' => 'negative',
            'priority_suggestion' => 'critical',
            'summary' => 'Severe hazard detected.',
        ]);

        $job->handle($mockService);

        $this->assertEquals('critical', $concern->fresh()->priority);
        Notification::assertSentTo($this->staffA, ConcernWorkflowNotification::class);
        Notification::assertSentTo($this->sao1, ConcernWorkflowNotification::class);
    }

    public function test_24_critical_notification_contains_direct_link_for_staff_and_dean()
    {
        $concern = $this->createConcern([
            'priority' => 'critical',
            'workflow_type' => 'academic',
            'academic_unit_id' => $this->unitA->id,
        ]);

        ConcernWorkflowNotification::sendCriticalConcernNotification($concern, 'Direct Link Test');

        $notif = DB::table('notifications')
            ->where('notifiable_id', $this->staffA->id)
            ->first();

        $this->assertNotNull($notif);
        $data = json_decode($notif->data, true);
        $this->assertEquals("/dept-staff/concerns/{$concern->concern_id}", $data['action_url']);
    }

    public function test_25_critical_notification_contains_option_a_link_for_sao()
    {
        // Concern assigned to CCS (not SAO department)
        $concern = $this->createConcern([
            'priority' => 'critical',
            'workflow_type' => 'academic',
            'academic_unit_id' => $this->unitA->id,
            'dept_id' => null,
        ]);

        ConcernWorkflowNotification::sendCriticalConcernNotification($concern, 'Option A SAO Test');

        $notif = DB::table('notifications')
            ->where('notifiable_id', $this->sao1->id)
            ->first();

        $this->assertNotNull($notif);
        $data = json_decode($notif->data, true);
        // Option A: points to aggregate oversight, never exposes unauthorized direct detail
        $this->assertEquals('/sao/oversight', $data['action_url']);
    }

    public function test_26_recurrence_tier_escalation_to_high_dispatches_notification_to_staff_and_dean()
    {
        Notification::fake();
        $service = app(RecurrenceDetectionService::class);

        for ($i = 1; $i <= 10; $i++) {
            $stu = $this->createStudent($i);
            $c = $this->createConcern([
                'student_id' => $stu->id,
                'title' => 'Projector bulb blown in Lecture Hall 101',
                'description' => 'The projector bulb has burnt out in Lecture Hall 101.',
            ]);
            $service->processConcern($c);
        }

        Notification::assertSentTo($this->staffA, ConcernWorkflowNotification::class);
        Notification::assertSentTo($this->deanA, ConcernWorkflowNotification::class);
    }

    public function test_27_recurrence_tier_escalation_to_critical_dispatches_notification_to_staff_dean_and_sao()
    {
        Notification::fake();
        $service = app(RecurrenceDetectionService::class);

        for ($i = 1; $i <= 20; $i++) {
            $stu = $this->createStudent($i);
            $c = $this->createConcern([
                'student_id' => $stu->id,
                'title' => 'Ceiling water leakage damaging electronics',
                'description' => 'Heavy water leaking over high-voltage lab equipment.',
            ]);
            $service->processConcern($c);
        }

        Notification::assertSentTo($this->staffA, ConcernWorkflowNotification::class);
        Notification::assertSentTo($this->deanA, ConcernWorkflowNotification::class);
        Notification::assertSentTo($this->sao1, ConcernWorkflowNotification::class);
        Notification::assertSentTo($this->sao2, ConcernWorkflowNotification::class);
    }

    public function test_28_recurrence_tier_escalation_to_major_critical_dispatches_notification_to_all_roles()
    {
        Notification::fake();
        $service = app(RecurrenceDetectionService::class);

        for ($i = 1; $i <= 30; $i++) {
            $stu = $this->createStudent($i);
            $c = $this->createConcern([
                'student_id' => $stu->id,
                'title' => 'Massive roof collapse danger in main hall',
                'description' => 'Main hall ceiling sagging severely after rain storm.',
            ]);
            $service->processConcern($c);
        }

        Notification::assertSentTo($this->staffA, ConcernWorkflowNotification::class);
        Notification::assertSentTo($this->deanA, ConcernWorkflowNotification::class);
        Notification::assertSentTo($this->sao1, ConcernWorkflowNotification::class);
    }

    public function test_29_reassigned_concern_sends_critical_alert_to_new_department_only()
    {
        Notification::fake();

        $concern = $this->createConcern([
            'workflow_type' => 'non_academic',
            'category' => 'operational_departments',
            'dept_id' => $this->deptAccounting->id,
            'priority' => 'critical',
        ]);

        // Reassign to Registrar
        $concern->update([
            'dept_id' => $this->deptRegistrar->id,
            'critical_notified_at' => null, // Reset to test reassignment dispatch
        ]);

        $registrarStaff = User::create([
            'name' => 'Registrar Staff',
            'email' => 'reg_staff@test.com',
            'password' => Hash::make('password'),
            'role' => 'dept_staff',
            'dept_id' => $this->deptRegistrar->id,
        ]);

        ConcernWorkflowNotification::sendCriticalConcernNotification($concern, 'Department Reassignment');

        Notification::assertSentTo($registrarStaff, ConcernWorkflowNotification::class);
        Notification::assertNotSentTo($this->staffNonAcademic, ConcernWorkflowNotification::class);
    }

    public function test_30_repeated_reports_at_same_tier_do_not_generate_duplicate_notifications()
    {
        $service = app(RecurrenceDetectionService::class);

        // 10 students escalate to High
        for ($i = 1; $i <= 10; $i++) {
            $stu = $this->createStudent($i);
            $c = $this->createConcern([
                'student_id' => $stu->id,
                'title' => 'Broken stair handrail in west wing',
                'description' => 'The handrail on the west wing staircase is loose.',
            ]);
            $service->processConcern($c);
        }

        $notifCountAfter10 = DB::table('notifications')->count();

        // Add 11th, 12th, 13th students (remains at High tier, no transition)
        for ($i = 11; $i <= 13; $i++) {
            $stu = $this->createStudent($i);
            $c = $this->createConcern([
                'student_id' => $stu->id,
                'title' => 'Broken stair handrail in west wing',
                'description' => 'The handrail on the west wing staircase is loose.',
            ]);
            $service->processConcern($c);
        }

        $notifCountAfter13 = DB::table('notifications')->count();

        // No new tier escalation notifications sent for reports 11, 12, 13
        $this->assertEquals($notifCountAfter10, $notifCountAfter13);
    }

    // ── Authorization & Privacy Tests (31 - 35) ──────────────────────────────

    public function test_31_student_cannot_access_recurring_incident_endpoints()
    {
        $incident = RecurringIncident::create([
            'incident_code' => 'INC-202610-0001',
            'title' => 'Test Cluster',
            'workflow_type' => 'academic',
            'academic_unit_id' => $this->unitA->id,
            'status' => 'open',
            'priority' => 'high',
            'recurrence_tier' => 'high',
            'first_reported_at' => now(),
            'latest_reported_at' => now(),
        ]);

        $this->actingAs($this->student1)->getJson('/api/sao/recurring-incidents')->assertStatus(403);
        $this->actingAs($this->student1)->getJson('/api/dean/recurring-incidents')->assertStatus(403);
        $this->actingAs($this->student1)->getJson('/api/dept-staff/recurring-incidents')->assertStatus(403);
        $this->actingAs($this->student1)->getJson("/api/dean/recurring-incidents/{$incident->id}")->assertStatus(403);
    }

    public function test_32_dean_cannot_view_recurring_incidents_of_other_academic_units()
    {
        $incidentUnitB = RecurringIncident::create([
            'incident_code' => 'INC-202610-0002',
            'title' => 'COE Cluster',
            'workflow_type' => 'academic',
            'academic_unit_id' => $this->unitB->id,
            'status' => 'open',
            'priority' => 'high',
            'recurrence_tier' => 'high',
            'first_reported_at' => now(),
            'latest_reported_at' => now(),
        ]);

        // Dean A (CCS) cannot view Dean B (COE) recurring incident
        $res = $this->actingAs($this->deanA)->getJson("/api/dean/recurring-incidents/{$incidentUnitB->id}");
        $res->assertStatus(403);
    }

    public function test_33_department_staff_cannot_view_recurring_incidents_of_other_departments()
    {
        $incidentAccounting = RecurringIncident::create([
            'incident_code' => 'INC-202610-0003',
            'title' => 'Accounting Cluster',
            'workflow_type' => 'non_academic',
            'dept_id' => $this->deptAccounting->id,
            'status' => 'open',
            'priority' => 'high',
            'recurrence_tier' => 'high',
            'first_reported_at' => now(),
            'latest_reported_at' => now(),
        ]);

        // Staff A (Academic CCS) cannot view Accounting recurring incident
        $res = $this->actingAs($this->staffA)->getJson("/api/dept-staff/recurring-incidents/{$incidentAccounting->id}");
        $res->assertStatus(403);
    }

    public function test_34_department_staff_cannot_override_recurring_incident_priority()
    {
        $incident = RecurringIncident::create([
            'incident_code' => 'INC-202610-0004',
            'title' => 'CCS Lab Cluster',
            'workflow_type' => 'academic',
            'academic_unit_id' => $this->unitA->id,
            'status' => 'open',
            'priority' => 'medium',
            'recurrence_tier' => 'normal',
            'first_reported_at' => now(),
            'latest_reported_at' => now(),
        ]);

        $res = $this->actingAs($this->staffA)->postJson("/api/dept-staff/recurring-incidents/{$incident->id}/priority-override", [
            'priority' => 'critical',
            'reason' => 'Staff attempting priority override.',
        ]);
        $res->assertStatus(403);
    }

    public function test_35_student_concern_api_response_does_not_expose_recurring_incident_details_or_siblings()
    {
        $incident = RecurringIncident::create([
            'incident_code' => 'INC-202610-0005',
            'title' => 'Secret Cluster',
            'workflow_type' => 'academic',
            'academic_unit_id' => $this->unitA->id,
            'status' => 'open',
            'priority' => 'high',
            'recurrence_tier' => 'high',
            'first_reported_at' => now(),
            'latest_reported_at' => now(),
        ]);

        $concern = $this->createConcern([
            'student_id' => $this->student1->id,
            'recurring_incident_id' => $incident->id,
        ]);

        $res = $this->actingAs($this->student1)->getJson("/api/concerns/{$concern->concern_id}");
        $res->assertStatus(200);

        $data = $res->json('concern');
        $this->assertArrayNotHasKey('recurring_incident_id', $data);
        $this->assertArrayNotHasKey('recurring_incident', $data);
        $this->assertArrayNotHasKey('critical_notified_at', $data);
    }

    // ── Phase 6.1 Audit Tests (36 - 43) ───────────────────────────────────────

    public function test_36_sao_cannot_override_recurring_incident_outside_sao_department()
    {
        $incidentCCS = RecurringIncident::create([
            'incident_code' => 'INC-202610-0036',
            'title' => 'CCS Lab Hardware Cluster',
            'workflow_type' => 'academic',
            'academic_unit_id' => $this->unitA->id,
            'status' => 'open',
            'priority' => 'medium',
            'recurrence_tier' => 'normal',
            'first_reported_at' => now(),
            'latest_reported_at' => now(),
        ]);

        $res = $this->actingAs($this->sao1)->postJson("/api/sao/recurring-incidents/{$incidentCCS->id}/priority-override", [
            'priority' => 'critical',
            'reason' => 'SAO attempting unauthorized override outside SAO department.',
        ]);
        $res->assertStatus(403);
    }

    public function test_37_sao_can_override_recurring_incident_assigned_to_sao_department()
    {
        $incidentSao = RecurringIncident::create([
            'incident_code' => 'INC-202610-0037',
            'title' => 'Student ID Renewal Machine Offline',
            'workflow_type' => 'non_academic',
            'dept_id' => $this->deptSao->id,
            'status' => 'open',
            'priority' => 'medium',
            'recurrence_tier' => 'normal',
            'first_reported_at' => now(),
            'latest_reported_at' => now(),
        ]);

        $res = $this->actingAs($this->sao1)->postJson("/api/sao/recurring-incidents/{$incidentSao->id}/priority-override", [
            'priority' => 'critical',
            'reason' => 'SAO authorized priority override for ID machine failure.',
        ]);
        $res->assertStatus(200);

        $this->assertEquals('critical', $incidentSao->fresh()->priority);
        $this->assertTrue($incidentSao->fresh()->is_priority_overridden);
        $this->assertEquals($this->sao1->id, $incidentSao->fresh()->priority_overridden_by);
    }

    public function test_38_sao_cannot_unlink_concern_from_recurring_incident_outside_sao_department()
    {
        $incidentCCS = RecurringIncident::create([
            'incident_code' => 'INC-202610-0038',
            'title' => 'CCS Server Cluster',
            'workflow_type' => 'academic',
            'academic_unit_id' => $this->unitA->id,
            'status' => 'open',
            'priority' => 'high',
            'recurrence_tier' => 'high',
            'first_reported_at' => now(),
            'latest_reported_at' => now(),
        ]);

        $concern = $this->createConcern([
            'student_id' => $this->student1->id,
            'recurring_incident_id' => $incidentCCS->id,
        ]);

        $res = $this->actingAs($this->sao1)->postJson("/api/sao/recurring-incidents/{$incidentCCS->id}/unlink/{$concern->concern_id}");
        $res->assertStatus(403);
    }

    public function test_39_sao_can_unlink_concern_from_recurring_incident_assigned_to_sao_department()
    {
        $incidentSao = RecurringIncident::create([
            'incident_code' => 'INC-202610-0039',
            'title' => 'SAO Counseling Booking Queue Cluster',
            'workflow_type' => 'non_academic',
            'dept_id' => $this->deptSao->id,
            'status' => 'open',
            'priority' => 'high',
            'recurrence_tier' => 'high',
            'first_reported_at' => now(),
            'latest_reported_at' => now(),
        ]);

        $concern = $this->createConcern([
            'student_id' => $this->student1->id,
            'workflow_type' => 'non_academic',
            'category' => 'operational_departments',
            'dept_id' => $this->deptSao->id,
            'recurring_incident_id' => $incidentSao->id,
        ]);

        $res = $this->actingAs($this->sao1)->postJson("/api/sao/recurring-incidents/{$incidentSao->id}/unlink/{$concern->concern_id}");
        $res->assertStatus(200);

        $this->assertNull($concern->fresh()->recurring_incident_id);
    }

    public function test_40_sao_show_masks_individual_concerns_for_incident_outside_sao_department()
    {
        $incidentAccounting = RecurringIncident::create([
            'incident_code' => 'INC-202610-0040',
            'title' => 'Accounting Cashier Cluster',
            'workflow_type' => 'non_academic',
            'dept_id' => $this->deptAccounting->id,
            'status' => 'open',
            'priority' => 'high',
            'recurrence_tier' => 'high',
            'first_reported_at' => now(),
            'latest_reported_at' => now(),
        ]);

        $this->createConcern([
            'student_id' => $this->student1->id,
            'title' => 'Confidential Cashier Issue',
            'workflow_type' => 'non_academic',
            'category' => 'operational_departments',
            'dept_id' => $this->deptAccounting->id,
            'recurring_incident_id' => $incidentAccounting->id,
        ]);

        $res = $this->actingAs($this->sao1)->getJson("/api/sao/recurring-incidents/{$incidentAccounting->id}");
        $res->assertStatus(200);

        // Aggregate incident information is accessible
        $res->assertJsonPath('data.incident_code', 'INC-202610-0040');
        $res->assertJsonPath('data.is_sao_assigned', false);

        // Individual concern records are NOT exposed to SAO (Option A)
        $this->assertEmpty($res->json('data.concerns'));
    }

    public function test_41_sao_show_exposes_linked_concerns_for_incident_assigned_to_sao_department()
    {
        $incidentSao = RecurringIncident::create([
            'incident_code' => 'INC-202610-0041',
            'title' => 'SAO Clearance System Down',
            'workflow_type' => 'non_academic',
            'dept_id' => $this->deptSao->id,
            'status' => 'open',
            'priority' => 'high',
            'recurrence_tier' => 'high',
            'first_reported_at' => now(),
            'latest_reported_at' => now(),
        ]);

        $concern = $this->createConcern([
            'student_id' => $this->student1->id,
            'title' => 'Clearance submission failed',
            'workflow_type' => 'non_academic',
            'category' => 'operational_departments',
            'dept_id' => $this->deptSao->id,
            'recurring_incident_id' => $incidentSao->id,
        ]);

        $res = $this->actingAs($this->sao1)->getJson("/api/sao/recurring-incidents/{$incidentSao->id}");
        $res->assertStatus(200);

        $res->assertJsonPath('data.is_sao_assigned', true);
        $this->assertNotEmpty($res->json('data.concerns'));
        $this->assertEquals($concern->concern_id, $res->json('data.concerns.0.concern_id'));
    }

    public function test_42_manually_overridden_high_concern_escalates_to_critical_at_twenty_students()
    {
        $service = app(RecurrenceDetectionService::class);

        // Concern 1 is manually overridden to High by Dean
        $c1 = $this->createConcern([
            'student_id' => $this->student1->id,
            'title' => 'Severe electrical overheating in server cluster',
            'description' => 'Server power distribution unit running dangerously hot.',
            'priority' => 'high',
            'is_priority_overridden' => true,
            'priority_override_reason' => 'Technician dispatched for immediate inspection.',
            'priority_overridden_by' => $this->deanA->id,
            'priority_overridden_at' => now(),
        ]);
        $service->processConcern($c1);

        // Add 19 more unique students to reach 20-student threshold
        for ($i = 2; $i <= 20; $i++) {
            $stu = $this->createStudent($i);
            $c = $this->createConcern([
                'student_id' => $stu->id,
                'title' => 'Severe electrical overheating in server cluster',
                'description' => 'Server power distribution unit running dangerously hot.',
            ]);
            $service->processConcern($c);
        }

        // Policy: Manual High escalates to Critical at 20-student threshold
        $this->assertEquals('critical', $c1->fresh()->priority);
        // Concern-level override metadata is preserved
        $this->assertTrue($c1->fresh()->is_priority_overridden);
        $this->assertEquals('Technician dispatched for immediate inspection.', $c1->fresh()->priority_override_reason);

        // Audit history was recorded
        $routing = DB::table('concern_routing')
            ->where('concern_id', $c1->concern_id)
            ->where('action', 'priority_escalated')
            ->first();
        $this->assertNotNull($routing);
    }

    public function test_43_department_staff_transfer_reprocesses_recurrence_and_alerts_new_dept_if_critical()
    {
        Notification::fake();

        $registrarStaff = User::create([
            'name' => 'New Registrar Staff',
            'email' => 'new_reg@test.com',
            'password' => Hash::make('password'),
            'role' => 'dept_staff',
            'dept_id' => $this->deptRegistrar->id,
        ]);

        // Critical concern submitted to Accounting
        $concern = $this->createConcern([
            'workflow_type' => 'non_academic',
            'category' => 'operational_departments',
            'dept_id' => $this->deptAccounting->id,
            'priority' => 'critical',
            'title' => 'Cash register malfunction urgent safety hazard',
            'description' => 'Electric short circuit on register table.',
        ]);

        // Transfer to Registrar
        $res = $this->actingAs($this->staffNonAcademic)->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfer", [
            'target_category' => 'operational_departments',
            'target_id' => $this->deptRegistrar->id,
            'notes' => 'Misrouted, belongs to registrar cashier.',
        ]);
        $res->assertStatus(200);

        $fresh = $concern->fresh();
        $this->assertEquals($this->deptRegistrar->id, $fresh->dept_id);

        Notification::assertSentTo($registrarStaff, ConcernWorkflowNotification::class);
    }
}
