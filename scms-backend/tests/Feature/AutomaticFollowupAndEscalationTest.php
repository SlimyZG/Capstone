<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Concern;
use App\Models\ConcernRouting;
use App\Models\Department;
use App\Models\User;
use App\Notifications\ConcernWorkflowNotification;
use App\Services\ConcernSlaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Phase 8 — Automatic Follow-up (75% Threshold) & Resolution-Deadline Escalation Test Suite
 *
 * Covers all 14 required verification scenarios:
 *  1. Concern below the 75% threshold.
 *  2. Concern reaching the 75% threshold.
 *  3. Concern receives only one follow-up notification.
 *  4. Concern reaching its resolution deadline.
 *  5. Concern already resolved before the 75% threshold.
 *  6. Concern already resolved before its deadline.
 *  7. Concern whose priority changes.
 *  8. Concern reassigned to another department.
 *  9. Academic escalation to the correct Dean.
 * 10. Department Staff access restrictions.
 * 11. Dean academic-unit restrictions.
 * 12. SAO individual-detail restrictions & student internal-audit shielding.
 * 13. Duplicate scheduler / Artisan command execution idempotency.
 * 14. Non-academic deadline behavior preserves existing workflow (notify-only).
 */
class AutomaticFollowupAndEscalationTest extends TestCase
{
    use RefreshDatabase;

    private AcademicUnit $ccsUnit;
    private AcademicUnit $marineUnit;
    private Department $edpDept;
    private Department $cashierDept;
    private Department $saoDept;

    private User $student;
    private User $ccsStaff;
    private User $edpStaff;
    private User $cashierStaff;
    private User $ccsDean;
    private User $marineDean;
    private User $saoUser;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sla.enabled' => true,
            'sla.targets_hours' => [
                'critical' => 24,
                'high'     => 72,
                'medium'   => 120,
                'low'      => 168,
            ],
            'sla.followup_threshold'    => 0.75,
            'sla.approaching_threshold' => 0.50,
            'sla.deadline_action' => [
                'academic'     => 'escalate_to_dean',
                'non_academic' => 'notify_only',
            ],
        ]);

        $this->ccsUnit    = AcademicUnit::create(['name' => 'College of Computer Studies']);
        $this->marineUnit = AcademicUnit::create(['name' => 'College of Maritime Education']);

        $this->edpDept     = Department::create(['name' => 'EDP Office']);
        $this->cashierDept = Department::create(['name' => 'Cashier']);
        $this->saoDept     = Department::create(['name' => 'SAO ( Student Affairs Office )']);

        $this->student = User::create([
            'name'              => 'Test Student',
            'email'             => 'student.sla@scms.test',
            'student_id_number' => '24000001',
            'password'          => Hash::make('password123'),
            'role'              => 'student',
            'academic_unit_id'  => $this->ccsUnit->id,
        ]);

        $this->ccsStaff = User::create([
            'name'             => 'CCS Staff',
            'email'            => 'staff.ccs.sla@scms.test',
            'password'         => Hash::make('password123'),
            'role'             => 'dept_staff',
            'academic_unit_id' => $this->ccsUnit->id,
        ]);

        $this->edpStaff = User::create([
            'name'     => 'EDP Staff',
            'email'    => 'staff.edp.sla@scms.test',
            'password' => Hash::make('password123'),
            'role'     => 'dept_staff',
            'dept_id'  => $this->edpDept->id,
        ]);

        $this->cashierStaff = User::create([
            'name'     => 'Cashier Staff',
            'email'    => 'staff.cashier.sla@scms.test',
            'password' => Hash::make('password123'),
            'role'     => 'dept_staff',
            'dept_id'  => $this->cashierDept->id,
        ]);

        $this->ccsDean = User::create([
            'name'             => 'Dean CCS',
            'email'            => 'dean.ccs.sla@scms.test',
            'password'         => Hash::make('password123'),
            'role'             => 'dean',
            'academic_unit_id' => $this->ccsUnit->id,
        ]);

        $this->marineDean = User::create([
            'name'             => 'Dean Marine',
            'email'            => 'dean.marine.sla@scms.test',
            'password'         => Hash::make('password123'),
            'role'             => 'dean',
            'academic_unit_id' => $this->marineUnit->id,
        ]);

        $this->saoUser = User::create([
            'name'     => 'SAO Officer',
            'email'    => 'sao.sla@scms.test',
            'password' => Hash::make('password123'),
            'role'     => 'sao',
            'dept_id'  => $this->saoDept->id,
        ]);
    }

    // ── 1. Concern below the 75% threshold ────────────────────────────────────

    public function test_01_concern_below_75_percent_threshold_does_not_trigger_followup(): void
    {
        Notification::fake();

        // Critical target = 24h; 75% = 18h. Submitted 10 hours ago (41.7%).
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'target_type'      => 'department',
            'title'            => 'Lab Projector Flickering',
            'description'      => 'Projector in Lab 1 flickers intermittently.',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'status'           => 'pending',
            'priority'         => 'critical',
            'submitted_at'     => now()->subHours(10),
        ]);

        $stats = app(ConcernSlaService::class)->process();

        $this->assertEquals(1, $stats['scanned']);
        $this->assertEquals(0, $stats['followups']);
        $this->assertEquals(0, $stats['escalations']);
        $this->assertEquals('pending', $concern->fresh()->status);

        $sla = app(ConcernSlaService::class)->describe($concern->fresh());
        $this->assertEquals('normal', $sla['state']);
        $this->assertNull($sla['followup_sent_at']);

        Notification::assertNothingSent();
    }

    // ── 2. Concern reaching the 75% threshold ─────────────────────────────────

    public function test_02_concern_reaching_75_percent_threshold_triggers_staff_followup_without_escalating(): void
    {
        Notification::fake();

        // Critical target = 24h; 75% = 18h. Submitted 19 hours ago (79.2%, 5h remaining).
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'target_type'      => 'department',
            'title'            => 'Missing Final Exam Permit',
            'description'      => 'Permit not showing on portal.',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'status'           => 'pending',
            'priority'         => 'critical',
            'submitted_at'     => now()->subHours(19),
        ]);

        $stats = app(ConcernSlaService::class)->process();

        $this->assertEquals(1, $stats['followups']);
        $this->assertEquals(0, $stats['escalations']);

        // Status must NOT become escalated at 75%
        $this->assertEquals('pending', $concern->fresh()->status);

        // Audit record created in concern_routing with remaining time
        $routing = ConcernRouting::where('concern_id', $concern->concern_id)
            ->where('action', ConcernSlaService::ACTION_FOLLOWUP)
            ->first();
        $this->assertNotNull($routing);
        $this->assertStringContainsString('75%', $routing->notes);
        $this->assertStringContainsString('Remaining time:', $routing->notes);

        // Responsible CCS staff notified; Dean and other staff NOT notified
        Notification::assertSentTo(
            $this->ccsStaff,
            ConcernWorkflowNotification::class,
            fn ($n) => $n->event === 'sla_followup'
                && $n->concernId === $concern->concern_id
                && str_contains($n->message, 'Remaining time:')
        );
        Notification::assertNotSentTo($this->ccsDean, ConcernWorkflowNotification::class);
        Notification::assertNotSentTo($this->edpStaff, ConcernWorkflowNotification::class);
        Notification::assertNotSentTo($this->student, ConcernWorkflowNotification::class);

        $sla = app(ConcernSlaService::class)->describe($concern->fresh());
        $this->assertEquals('followup_sent', $sla['state']);
        $this->assertNotNull($sla['followup_sent_at']);
    }

    // ── 3. Concern receives only one follow-up notification ──────────────────

    public function test_03_concern_receives_only_one_followup_notification(): void
    {
        Notification::fake();

        // High target = 72h; 75% = 54h. Submitted 56 hours ago.
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'target_type'      => 'department',
            'title'            => 'Course Enrollment Block',
            'description'      => 'Cannot enroll in prerequisite subject.',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'status'           => 'in_progress',
            'priority'         => 'high',
            'submitted_at'     => now()->subHours(56),
        ]);

        $service = app(ConcernSlaService::class);
        $firstRun = $service->process();
        $secondRun = $service->process();

        $this->assertEquals(1, $firstRun['followups']);
        $this->assertEquals(0, $secondRun['followups']);

        $this->assertEquals(
            1,
            ConcernRouting::where('concern_id', $concern->concern_id)
                ->where('action', ConcernSlaService::ACTION_FOLLOWUP)
                ->count()
        );
        Notification::assertSentToTimes($this->ccsStaff, ConcernWorkflowNotification::class, 1);
    }

    // ── 4. Concern reaching its resolution deadline ──────────────────────────

    public function test_04_academic_concern_reaching_deadline_escalates_to_dean(): void
    {
        Notification::fake();

        // Critical target = 24h. Submitted 25 hours ago (104%).
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'target_type'      => 'department',
            'title'            => 'Lab Server Unreachable',
            'description'      => 'Database server in Lab 3 is offline.',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'status'           => 'pending',
            'priority'         => 'critical',
            'submitted_at'     => now()->subHours(25),
        ]);

        $stats = app(ConcernSlaService::class)->process();

        $this->assertEquals(0, $stats['followups']);
        $this->assertEquals(1, $stats['escalations']);

        $fresh = $concern->fresh();
        $this->assertEquals('escalated', $fresh->status);

        // Both sla_deadline_reached and forwarded_to_dean recorded in audit trail
        $this->assertDatabaseHas('concern_routing', [
            'concern_id' => $concern->concern_id,
            'action'     => ConcernSlaService::ACTION_DEADLINE,
        ]);
        $this->assertDatabaseHas('concern_routing', [
            'concern_id' => $concern->concern_id,
            'action'     => 'forwarded_to_dean',
        ]);

        $sla = app(ConcernSlaService::class)->describe($fresh);
        $this->assertEquals('escalated', $sla['state']);
        $this->assertTrue($sla['escalated_by_sla']);
        $this->assertNotNull($sla['deadline_reached_at']);
    }

    // ── 5. Concern already resolved before the 75% threshold ─────────────────

    public function test_05_concern_resolved_before_75_percent_threshold_is_ignored(): void
    {
        Notification::fake();

        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'target_type'      => 'department',
            'title'            => 'Resolved Early',
            'description'      => 'Resolved after 4 hours.',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'status'           => 'resolved',
            'priority'         => 'critical',
            'submitted_at'     => now()->subHours(20),
            'resolved_at'      => now()->subHours(16),
        ]);

        $stats = app(ConcernSlaService::class)->process();

        $this->assertEquals(0, $stats['scanned']);
        $this->assertEquals(0, $stats['followups']);
        $this->assertEquals(0, $stats['escalations']);

        $sla = app(ConcernSlaService::class)->describe($concern->fresh());
        $this->assertEquals('resolved', $sla['state']);
        $this->assertTrue($sla['resolved_within_target']);

        Notification::assertNothingSent();
    }

    // ── 6. Concern already resolved before its deadline ──────────────────────

    public function test_06_concern_resolved_after_75_percent_but_before_deadline_does_not_escalate(): void
    {
        Notification::fake();

        // Critical (24h): submitted 26h ago, resolved 4h ago (at 22h, after 75% = 18h, before 24h deadline).
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'target_type'      => 'department',
            'title'            => 'Resolved Before Deadline',
            'description'      => 'Resolved at hour 22.',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'status'           => 'resolved',
            'priority'         => 'critical',
            'submitted_at'     => now()->subHours(26),
            'resolved_at'      => now()->subHours(4),
        ]);

        $stats = app(ConcernSlaService::class)->process();

        $this->assertEquals(0, $stats['escalations']);
        $this->assertEquals('resolved', $concern->fresh()->status);

        $sla = app(ConcernSlaService::class)->describe($concern->fresh());
        $this->assertEquals('resolved', $sla['state']);
        $this->assertTrue($sla['resolved_within_target']);

        Notification::assertNothingSent();
    }

    // ── 7. Concern whose priority changes ────────────────────────────────────

    public function test_07_concern_priority_change_recalculates_resolution_target_and_triggers_followup(): void
    {
        Notification::fake();

        // Submitted 20 hours ago as Low (168h target -> 11.9% elapsed -> below 75%).
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'target_type'      => 'department',
            'title'            => 'Priority Upgraded Concern',
            'description'      => 'Initially low, upgraded to critical.',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'status'           => 'in_progress',
            'priority'         => 'low',
            'submitted_at'     => now()->subHours(20),
        ]);

        $service = app(ConcernSlaService::class);
        $this->assertEquals(0, $service->process()['followups']);

        // Priority changes to Critical (24h target -> 20/24 = 83.3% >= 75%).
        $concern->update(['priority' => 'critical']);

        $slaBeforeRun = $service->describe($concern->fresh());
        $this->assertEquals(24.0, $slaBeforeRun['target_hours']);
        $this->assertGreaterThanOrEqual(0.75, $slaBeforeRun['elapsed_ratio']);

        $stats = $service->process();
        $this->assertEquals(1, $stats['followups']);
        Notification::assertSentTo($this->ccsStaff, ConcernWorkflowNotification::class);
    }

    // ── 8. Concern reassigned to another department ──────────────────────────

    public function test_08_concern_reassigned_to_another_department_notifies_new_department_without_duplicate_followup(): void
    {
        Notification::fake();

        // Medium target = 120h; 75% = 90h. Submitted 95 hours ago to EDP.
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => $this->edpDept->id,
            'target_type'      => 'department',
            'title'            => 'Portal Payment Receipt Issue',
            'description'      => 'Receipt not reflected.',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'pending',
            'priority'         => 'medium',
            'submitted_at'     => now()->subHours(95),
        ]);

        // Transfer from EDP to Cashier BEFORE scheduler runs
        $this->actingAs($this->edpStaff)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfer", [
                'target_category' => 'operational_departments',
                'target_id'       => $this->cashierDept->id,
                'notes'           => 'Billing issue belongs to Cashier.',
            ])
            ->assertOk();

        Notification::fake();

        $service = app(ConcernSlaService::class);
        $firstRun = $service->process();
        $this->assertEquals(1, $firstRun['followups']);

        // New department (Cashier) notified; old department (EDP) NOT notified
        Notification::assertSentTo($this->cashierStaff, ConcernWorkflowNotification::class);
        Notification::assertNotSentTo($this->edpStaff, ConcernWorkflowNotification::class);

        // If transferred again after follow-up was already sent, no duplicate follow-up is sent
        $concern->update(['dept_id' => $this->edpDept->id]);
        $secondRun = $service->process();
        $this->assertEquals(0, $secondRun['followups']);
    }

    // ── 9. Academic escalation to the correct Dean ───────────────────────────

    public function test_09_academic_escalation_routes_and_notifies_only_the_correct_academic_unit_dean(): void
    {
        Notification::fake();

        // High target = 72h. Submitted 75 hours ago in CCS unit.
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'target_type'      => 'department',
            'title'            => 'CCS Curriculum Petition',
            'description'      => 'Unanswered past 72h deadline.',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'status'           => 'in_progress',
            'priority'         => 'high',
            'submitted_at'     => now()->subHours(75),
        ]);

        app(ConcernSlaService::class)->process();

        // CCS Dean notified; Marine Dean NOT notified
        Notification::assertSentTo(
            $this->ccsDean,
            ConcernWorkflowNotification::class,
            fn ($n) => $n->event === 'sla_auto_escalated' && $n->concernId === $concern->concern_id
        );
        Notification::assertNotSentTo($this->marineDean, ConcernWorkflowNotification::class);

        // CCS Dean sees the auto-escalated concern in /api/dean/inbox
        $inbox = $this->actingAs($this->ccsDean)->getJson('/api/dean/inbox')->assertOk();
        $escalatedIds = collect($inbox->json('escalated'))->pluck('concern_id')->all();
        $this->assertContains($concern->concern_id, $escalatedIds);
    }

    // ── 10. Department Staff access restrictions ─────────────────────────────

    public function test_10_department_staff_can_only_view_sla_status_for_their_assigned_department(): void
    {
        $edpConcern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => $this->edpDept->id,
            'target_type'      => 'department',
            'title'            => 'EDP Account Locked',
            'description'      => 'Locked out of LMS.',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'pending',
            'priority'         => 'high',
            'submitted_at'     => now()->subHours(40),
        ]);

        // EDP staff can access and sees SLA payload
        $this->actingAs($this->edpStaff)
            ->getJson("/api/dept-staff/concerns/{$edpConcern->concern_id}")
            ->assertOk()
            ->assertJsonPath('concern.sla.state', 'approaching')
            ->assertJsonPath('concern.sla.target_hours', 72);

        // Cashier staff cannot access EDP's concern (404)
        $this->actingAs($this->cashierStaff)
            ->getJson("/api/dept-staff/concerns/{$edpConcern->concern_id}")
            ->assertNotFound();
    }

    // ── 11. Dean academic-unit restrictions ──────────────────────────────────

    public function test_11_dean_can_only_view_escalated_concerns_within_their_own_academic_unit(): void
    {
        $ccsConcern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'target_type'      => 'department',
            'title'            => 'Overdue CCS Academic Concern',
            'description'      => 'Overdue for CCS Dean.',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'status'           => 'pending',
            'priority'         => 'critical',
            'submitted_at'     => now()->subHours(30),
        ]);

        app(ConcernSlaService::class)->process();

        // CCS Dean can view the escalated concern and its SLA metadata
        $this->actingAs($this->ccsDean)
            ->getJson("/api/dean/concerns/{$ccsConcern->concern_id}")
            ->assertOk()
            ->assertJsonPath('concern.status', 'escalated')
            ->assertJsonPath('concern.sla.escalated_by_sla', true);

        // Marine Dean is blocked from viewing CCS concern (404)
        $this->actingAs($this->marineDean)
            ->getJson("/api/dean/concerns/{$ccsConcern->concern_id}")
            ->assertNotFound();
    }

    // ── 12. SAO individual-detail restrictions & student privacy ─────────────

    public function test_12_sao_retains_option_a_restrictions_and_students_do_not_see_internal_sla_audit_logs(): void
    {
        $ccsConcern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'target_type'      => 'department',
            'title'            => 'CCS Lab Aircon Broken',
            'description'      => 'Air conditioning not working.',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'status'           => 'pending',
            'priority'         => 'critical',
            'submitted_at'     => now()->subHours(19),
        ]);

        app(ConcernSlaService::class)->process();

        // SAO is blocked from viewing individual non-SAO concern details (403 Option A)
        $this->actingAs($this->saoUser)
            ->getJson("/api/sao/concerns/{$ccsConcern->concern_id}")
            ->assertForbidden();

        // SAO CAN view aggregate SLA counts on /api/sao/oversight
        $this->actingAs($this->saoUser)
            ->getJson('/api/sao/oversight')
            ->assertOk()
            ->assertJsonPath('sla_monitoring.supported', true)
            ->assertJsonPath('sla_monitoring.followup_sent_count', 1);

        // Student viewing their own concern does NOT receive internal sla_followup_75 entries
        $studentRes = $this->actingAs($this->student)
            ->getJson("/api/concerns/{$ccsConcern->concern_id}")
            ->assertOk();

        $studentActions = collect($studentRes->json('concern.routing_history'))->pluck('action')->all();
        $this->assertNotContains(ConcernSlaService::ACTION_FOLLOWUP, $studentActions);
        $this->assertNotContains(ConcernSlaService::ACTION_DEADLINE, $studentActions);
    }

    // ── 13. Duplicate scheduler execution via Artisan command ────────────────

    public function test_13_artisan_command_is_idempotent_across_repeated_scheduler_executions(): void
    {
        Notification::fake();

        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'target_type'      => 'department',
            'title'            => 'Scheduler Idempotency Concern',
            'description'      => 'Past deadline.',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'status'           => 'pending',
            'priority'         => 'critical',
            'submitted_at'     => now()->subHours(28),
        ]);

        $this->artisan('concerns:process-sla')->assertExitCode(0);
        $this->artisan('concerns:process-sla')->assertExitCode(0);
        $this->artisan('concerns:process-sla')->assertExitCode(0);

        $this->assertEquals('escalated', $concern->fresh()->status);
        $this->assertEquals(
            1,
            ConcernRouting::where('concern_id', $concern->concern_id)
                ->where('action', ConcernSlaService::ACTION_DEADLINE)
                ->count()
        );
        Notification::assertSentToTimes($this->ccsDean, ConcernWorkflowNotification::class, 1);
    }

    // ── 14. Non-academic workflow preservation on deadline ───────────────────

    public function test_14_non_academic_concern_reaching_deadline_sends_overdue_notice_without_altering_workflow(): void
    {
        Notification::fake();

        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => $this->edpDept->id,
            'target_type'      => 'department',
            'title'            => 'Wi-Fi Voucher Request',
            'description'      => 'Non-academic operational request.',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'in_progress',
            'priority'         => 'critical',
            'submitted_at'     => now()->subHours(26),
        ]);

        $stats = app(ConcernSlaService::class)->process();

        $this->assertEquals(0, $stats['escalations']);
        $this->assertEquals(1, $stats['overdue_notices']);

        // Non-academic workflow status is preserved (remains in_progress, not escalated to CAD/Dean)
        $this->assertEquals('in_progress', $concern->fresh()->status);
        $this->assertNull($concern->fresh()->forwarded_to_cad_at);

        Notification::assertSentTo(
            $this->edpStaff,
            ConcernWorkflowNotification::class,
            fn ($n) => $n->event === 'sla_overdue' && $n->concernId === $concern->concern_id
        );
        Notification::assertNotSentTo($this->ccsDean, ConcernWorkflowNotification::class);
    }
}
