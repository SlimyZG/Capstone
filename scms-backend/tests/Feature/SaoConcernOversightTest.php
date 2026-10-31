<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Concern;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 4 — SAO Concern Oversight Module Test Suite
 *
 * Tests all requirements from Phase 4 specification:
 * - Aggregate statistics
 * - Unit and department breakdown
 * - Critical concerns monitoring
 * - Escalation & SLA monitoring
 * - Recurring concern patterns
 * - Option A strict access boundaries (SAO cannot access individual non-SAO concerns)
 * - Role-based authorization
 */
class SaoConcernOversightTest extends TestCase
{
    use RefreshDatabase;

    private User $sao;
    private User $admin;
    private User $dean;
    private User $staff;
    private User $student;
    private AcademicUnit $unitA;
    private AcademicUnit $unitB;
    private Department $deptA;
    private Department $saoDept;

    protected function setUp(): void
    {
        parent::setUp();

        // Academic Units
        $this->unitA = AcademicUnit::create(['name' => 'College of Computing']);
        $this->unitB = AcademicUnit::create(['name' => 'College of Engineering']);

        // Departments
        $this->deptA = Department::create(['name' => 'Cashier']);
        $this->saoDept = Department::create(['name' => 'SAO ( Student Affairs Office )']);

        // Users
        $this->sao = User::create([
            'name'     => 'SAO Administrator',
            'email'    => 'sao@admin.com',
            'password' => Hash::make('password123'),
            'role'     => 'sao',
        ]);

        $this->admin = User::create([
            'name'     => 'System Admin',
            'email'    => 'admin@scms.local',
            'password' => Hash::make('password123'),
            'role'     => 'admin',
        ]);

        $this->dean = User::create([
            'name'             => 'Dean User',
            'email'            => 'dean@scms.local',
            'password'         => Hash::make('password123'),
            'role'             => 'dean',
            'academic_unit_id' => $this->unitA->id,
        ]);

        $this->staff = User::create([
            'name'             => 'Staff User',
            'email'            => 'staff@scms.local',
            'password'         => Hash::make('password123'),
            'role'             => 'dept_staff',
            'dept_id'          => $this->deptA->id,
        ]);

        $this->student = User::create([
            'name'              => 'Student User',
            'email'             => 'student@scms.local',
            'student_id_number' => '12345678',
            'password'          => Hash::make('password123'),
            'role'              => 'student',
            'academic_unit_id'  => $this->unitA->id,
        ]);

        // Seed some concerns
        // Concern 1: Academic, unit A, Critical, in_progress
        Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unitA->id,
            'target_type'      => 'department',
            'title'            => 'Grades Incomplete',
            'description'      => 'Missing grades for second semester',
            'category'         => 'Academic Record',
            'workflow_type'    => 'academic',
            'status'           => 'in_progress',
            'priority'         => 'critical',
            'submitted_at'     => now()->subDays(2),
        ]);

        // Concern 2: Non-Academic, Cashier (deptA), resolved
        Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unitA->id,
            'dept_id'          => $this->deptA->id,
            'target_type'      => 'department',
            'title'            => 'Payment Verification',
            'description'      => 'Tuition fee official receipt pending',
            'category'         => 'Finance',
            'workflow_type'    => 'non_academic',
            'status'           => 'resolved',
            'priority'         => 'low',
            'submitted_at'     => now()->subDays(5),
            'resolved_at'      => now()->subDays(1),
        ]);

        // Concern 3: Non-Academic, SAO department, unit B, pending
        Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unitB->id,
            'dept_id'          => $this->saoDept->id,
            'target_type'      => 'department',
            'title'            => 'ID Card Replacement',
            'description'      => 'Lost ID card replacement request',
            'category'         => 'Student Services',
            'workflow_type'    => 'non_academic',
            'status'           => 'pending',
            'priority'         => 'medium',
            'submitted_at'     => now()->subDay(),
        ]);
    }

    // ── 1. SAO Access & Summary Verification ─────────────────────────────────

    public function test_sao_can_access_oversight_summary(): void
    {
        $response = $this->actingAs($this->sao)
            ->getJson('/api/sao/oversight');

        $response->assertOk()
            ->assertJsonStructure([
                'summary' => [
                    'total_submitted',
                    'pending',
                    'in_progress',
                    'resolved',
                    'rejected',
                    'escalated',
                    'critical',
                    'high_priority',
                    'active_critical',
                    'recent_critical',
                    'awaiting_dean_review',
                    'awaiting_cad_approval',
                    'avg_resolution_hours',
                    'avg_resolution_days',
                ],
                'sla_monitoring' => [
                    'supported',
                    'overdue_count',
                    'approaching_count',
                    'notice',
                ],
                'critical_breakdown',
                'academic_units',
                'departments',
                'categories',
                'status_distribution',
                'priority_distribution',
                'recurring_issues' => [
                    'by_department',
                    'by_academic_unit',
                ],
                'monthly_trend',
            ]);

        $this->assertEquals(3, $response->json('summary.total_submitted'));
        $this->assertEquals(1, $response->json('summary.critical'));
        $this->assertEquals(1, $response->json('summary.active_critical'));
        $this->assertEquals(1, $response->json('summary.resolved'));
        $this->assertEquals(1, $response->json('summary.pending'));
    }

    // ── 2. Role-Based Access Controls ────────────────────────────────────────

    public function test_student_cannot_access_sao_oversight(): void
    {
        $this->actingAs($this->student)
            ->getJson('/api/sao/oversight')
            ->assertForbidden();
    }

    public function test_dept_staff_cannot_access_sao_oversight(): void
    {
        $this->actingAs($this->staff)
            ->getJson('/api/sao/oversight')
            ->assertForbidden();
    }

    public function test_dean_cannot_access_sao_oversight(): void
    {
        $this->actingAs($this->dean)
            ->getJson('/api/sao/oversight')
            ->assertForbidden();
    }

    public function test_unauthenticated_cannot_access_sao_oversight(): void
    {
        $this->getJson('/api/sao/oversight')
            ->assertUnauthorized();
    }

    // ── 3. Filter Verification ───────────────────────────────────────────────

    public function test_oversight_filters_by_academic_unit(): void
    {
        $response = $this->actingAs($this->sao)
            ->getJson('/api/sao/oversight?academic_unit_id=' . $this->unitB->id);

        $response->assertOk();
        $this->assertEquals(1, $response->json('summary.total_submitted'));
        $this->assertEquals(1, $response->json('summary.pending'));
    }

    public function test_oversight_filters_by_department(): void
    {
        $response = $this->actingAs($this->sao)
            ->getJson('/api/sao/oversight?dept_id=' . $this->deptA->id);

        $response->assertOk();
        $this->assertEquals(1, $response->json('summary.total_submitted'));
        $this->assertEquals(1, $response->json('summary.resolved'));
    }

    public function test_oversight_filters_by_status(): void
    {
        $response = $this->actingAs($this->sao)
            ->getJson('/api/sao/oversight?status=pending');

        $response->assertOk();
        $this->assertEquals(1, $response->json('summary.total_submitted'));
        $this->assertEquals(1, $response->json('summary.pending'));
    }

    public function test_oversight_filters_by_priority(): void
    {
        $response = $this->actingAs($this->sao)
            ->getJson('/api/sao/oversight?priority=critical');

        $response->assertOk();
        $this->assertEquals(1, $response->json('summary.total_submitted'));
        $this->assertEquals(1, $response->json('summary.critical'));
    }

    public function test_oversight_filters_by_date_range(): void
    {
        $from = now()->subDays(3)->format('Y-m-d');
        $to   = now()->format('Y-m-d');

        $response = $this->actingAs($this->sao)
            ->getJson("/api/sao/oversight?date_from={$from}&date_to={$to}");

        $response->assertOk();
        // Should include concern 1 (2 days ago) and concern 3 (1 day ago), but not concern 2 (5 days ago)
        $this->assertEquals(2, $response->json('summary.total_submitted'));
    }

    // ── 4. Option A — Strict Individual Concern Access Boundaries ─────────────

    public function test_sao_cannot_access_individual_concern_not_assigned_to_sao(): void
    {
        // Concern 1 is in unitA (College of Computing), not assigned to SAO
        $concern = Concern::where('title', 'Grades Incomplete')->first();

        $response = $this->actingAs($this->sao)
            ->getJson('/api/sao/concerns/' . $concern->concern_id);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Unauthorized. In accordance with strict SAO oversight boundaries (Option A), SAO administrative oversight is restricted to institution-wide aggregate monitoring. Individual concern details and internal communications may only be viewed when the concern is explicitly assigned to the Student Affairs Office.');
    }

    public function test_sao_can_access_individual_concern_assigned_to_sao_department(): void
    {
        // Concern 3 is assigned to the SAO department ($this->saoDept)
        $concern = Concern::where('title', 'ID Card Replacement')->first();

        $response = $this->actingAs($this->sao)
            ->getJson('/api/sao/concerns/' . $concern->concern_id);

        $response->assertOk()
            ->assertJsonPath('concern.title', 'ID Card Replacement')
            ->assertJsonPath('concern.dept_id', $this->saoDept->id);
    }

    // ── 5. SLA Status & Metric Integrity ─────────────────────────────────────

    public function test_oversight_reports_honest_sla_schema_status(): void
    {
        $response = $this->actingAs($this->sao)
            ->getJson('/api/sao/oversight');

        $response->assertOk();
        $sla = $response->json('sla_monitoring');

        $this->assertTrue($sla['supported'], 'SLA monitoring should be reported as supported when configured');
        $this->assertEquals(1, $sla['overdue_count']);
        $this->assertNotEmpty($sla['notice']);

        // When disabled via configuration, reports supported=false with 0 counts
        config(['sla.enabled' => false]);
        $disabledRes = $this->actingAs($this->sao)->getJson('/api/sao/oversight');
        $disabledRes->assertOk();
        $this->assertFalse($disabledRes->json('sla_monitoring.supported'));
        $this->assertEquals(0, $disabledRes->json('sla_monitoring.overdue_count'));
    }

    public function test_average_resolution_time_is_computed_from_database_timestamps(): void
    {
        $response = $this->actingAs($this->sao)
            ->getJson('/api/sao/oversight');

        $response->assertOk();
        $summary = $response->json('summary');

        // Concern 2 was submitted 5 days ago and resolved 1 day ago (approx 96 hours / 4 days)
        $this->assertNotNull($summary['avg_resolution_hours']);
        $this->assertGreaterThan(90, $summary['avg_resolution_hours']);
        $this->assertLessThan(100, $summary['avg_resolution_hours']);
        $this->assertEquals(4.0, $summary['avg_resolution_days']);
    }

    public function test_active_critical_excludes_resolved_concerns(): void
    {
        // Resolve the critical concern
        $critical = Concern::where('title', 'Grades Incomplete')->first();
        $critical->update([
            'status'      => 'resolved',
            'resolved_at' => now(),
        ]);

        $response = $this->actingAs($this->sao)
            ->getJson('/api/sao/oversight');

        $response->assertOk();
        $summary = $response->json('summary');

        // Total critical is still 1, but active critical must now be 0
        $this->assertEquals(1, $summary['critical']);
        $this->assertEquals(0, $summary['active_critical']);
    }
}
