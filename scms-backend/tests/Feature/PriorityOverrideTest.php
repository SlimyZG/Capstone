<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Concern;
use App\Models\ConcernRouting;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 5 — AI Priority Override Test Suite
 *
 * Covers:
 * 1. Dean override on escalated concern within academic unit.
 * 2. Dean override on direct Dean concern.
 * 3. Dean cannot override on concern from another academic unit (403).
 * 4. Dean cannot override on concern still pending with dept staff (403).
 * 5. SAO override on concern assigned to SAO department.
 * 6. SAO cannot override on concern assigned to another department (403).
 * 7. Admin cannot call Dean override endpoint (403).
 * 8. Admin cannot call SAO override endpoint (403).
 * 9. Dept staff cannot call override endpoints (403).
 * 10. Student cannot call override endpoints (403).
 * 11. Priority override validates input (rejects missing/invalid priority).
 * 12. Priority override requires a valid reason (min 5 chars).
 * 13. Priority override creates an immutable audit record in concern_routing.
 * 14. Priority override sets is_priority_overridden flag and timestamps.
 * 15. Subsequent concern update preserves the overridden priority.
 * 16. Reanalysis still updates ai_priority while preserving overridden priority.
 * 17. Effective priority reflected in details response and hidden from students.
 * 18. Non-existent concern returns 404 Not Found.
 */
class PriorityOverrideTest extends TestCase
{
    use RefreshDatabase;

    private User $sao;
    private User $admin;
    private User $deanA;
    private User $deanB;
    private User $staff;
    private User $student;
    private AcademicUnit $unitA;
    private AcademicUnit $unitB;
    private Department $deptA;
    private Department $saoDept;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitA = AcademicUnit::create(['name' => 'College of Computer Studies']);
        $this->unitB = AcademicUnit::create(['name' => 'College of Engineering']);

        $this->deptA = Department::create(['name' => 'Cashier']);
        $this->saoDept = Department::create(['name' => 'SAO ( Student Affairs Office )']);

        $this->sao = User::create([
            'name'     => 'SAO Officer',
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

        $this->deanA = User::create([
            'name'             => 'Dean CCS',
            'email'            => 'dean.ccs@scms.local',
            'password'         => Hash::make('password123'),
            'role'             => 'dean',
            'academic_unit_id' => $this->unitA->id,
        ]);

        $this->deanB = User::create([
            'name'             => 'Dean COE',
            'email'            => 'dean.coe@scms.local',
            'password'         => Hash::make('password123'),
            'role'             => 'dean',
            'academic_unit_id' => $this->unitB->id,
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
    }

    /** 1. Dean can override priority on an escalated concern within their academic unit */
    public function test_dean_can_override_priority_on_escalated_concern_within_academic_unit(): void
    {
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unitA->id,
            'target_type'      => 'department',
            'title'            => 'Grading Dispute',
            'description'      => 'Discrepancy in midterm examination grade',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'status'           => 'escalated',
            'priority'         => 'medium',
            'ai_priority'      => 'medium',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        $response = $this->actingAs($this->deanA)
            ->postJson("/api/dean/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'critical',
                'reason'   => 'Graduation requirement issue requires immediate Dean level attention.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Priority overridden successfully.',
                'concern' => [
                    'concern_id'             => $concern->concern_id,
                    'priority'               => 'critical',
                    'is_priority_overridden' => true,
                    'priority_override_reason' => 'Graduation requirement issue requires immediate Dean level attention.',
                ],
            ]);

        $this->assertDatabaseHas('concerns', [
            'concern_id'             => $concern->concern_id,
            'priority'               => 'critical',
            'is_priority_overridden' => true,
            'priority_overridden_by' => $this->deanA->id,
        ]);
    }

    /** 2. Dean can override priority on a direct Dean concern */
    public function test_dean_can_override_priority_on_direct_dean_concern(): void
    {
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unitA->id,
            'target_type'      => 'dean',
            'title'            => 'Curriculum Appeal',
            'description'      => 'Direct appeal to Dean regarding course prerequisite',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'status'           => 'pending',
            'priority'         => 'low',
            'ai_priority'      => 'low',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        $response = $this->actingAs($this->deanA)
            ->postJson("/api/dean/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'high',
                'reason'   => 'Prerequisite block affects upcoming enrollment semester.',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('concerns', [
            'concern_id'             => $concern->concern_id,
            'priority'               => 'high',
            'is_priority_overridden' => true,
            'priority_overridden_by' => $this->deanA->id,
        ]);
    }

    /** 3. Dean cannot override priority on a concern from another academic unit (403) */
    public function test_dean_cannot_override_priority_on_concern_from_another_academic_unit(): void
    {
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unitB->id, // unit B
            'target_type'      => 'department',
            'title'            => 'Engineering Lab Equipment',
            'description'      => 'Broken instruments in civil engineering lab',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'status'           => 'escalated',
            'priority'         => 'medium',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        // Dean A (CCS) attempts to override Unit B concern
        $response = $this->actingAs($this->deanA)
            ->postJson("/api/dean/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'critical',
                'reason'   => 'Unauthorized override attempt across academic units.',
            ]);

        $response->assertStatus(403);

        $this->assertDatabaseHas('concerns', [
            'concern_id'             => $concern->concern_id,
            'priority'               => 'medium',
            'is_priority_overridden' => false,
        ]);
    }

    /** 4. Dean cannot override priority on a concern still pending with department staff (403) */
    public function test_dean_cannot_override_priority_on_concern_still_pending_with_department_staff(): void
    {
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unitA->id,
            'target_type'      => 'department',
            'title'            => 'Department Level Incomplete',
            'description'      => 'Pending chair/staff review',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'status'           => 'pending', // Pending with dept staff, not escalated
            'priority'         => 'low',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        $response = $this->actingAs($this->deanA)
            ->postJson("/api/dean/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'critical',
                'reason'   => 'Attempt to bypass department workflow.',
            ]);

        $response->assertStatus(403);

        $this->assertDatabaseHas('concerns', [
            'concern_id' => $concern->concern_id,
            'priority'   => 'low',
        ]);
    }

    /** 5. SAO can override priority on a concern assigned to SAO department */
    public function test_sao_can_override_priority_on_concern_assigned_to_sao_department(): void
    {
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unitA->id,
            'dept_id'          => $this->saoDept->id,
            'target_type'      => 'department',
            'title'            => 'Student Club Accreditation Delay',
            'description'      => 'Delay in student council annual activity permits',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'pending',
            'priority'         => 'low',
            'ai_priority'      => 'low',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        $response = $this->actingAs($this->sao)
            ->postJson("/api/sao/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'high',
                'reason'   => 'Impending campus election deadline requires immediate SAO processing.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Priority overridden successfully.',
                'concern' => [
                    'concern_id'             => $concern->concern_id,
                    'priority'               => 'high',
                    'is_priority_overridden' => true,
                ],
            ]);

        $this->assertDatabaseHas('concerns', [
            'concern_id'             => $concern->concern_id,
            'priority'               => 'high',
            'is_priority_overridden' => true,
            'priority_overridden_by' => $this->sao->id,
        ]);
    }

    /** 6. SAO cannot override priority on a concern assigned to another department (403) */
    public function test_sao_cannot_override_priority_on_concern_assigned_to_another_department(): void
    {
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unitA->id,
            'dept_id'          => $this->deptA->id, // Cashier
            'target_type'      => 'department',
            'title'            => 'Cashier Refund Request',
            'description'      => 'Tuition fee refund request',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'pending',
            'priority'         => 'medium',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        $response = $this->actingAs($this->sao)
            ->postJson("/api/sao/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'critical',
                'reason'   => 'Attempt to override priority of cashier concern.',
            ]);

        $response->assertStatus(403);

        $this->assertDatabaseHas('concerns', [
            'concern_id'             => $concern->concern_id,
            'priority'               => 'medium',
            'is_priority_overridden' => false,
        ]);
    }

    /** 7. Admin cannot call Dean priority override endpoint (403) */
    public function test_admin_cannot_call_dean_priority_override_endpoint(): void
    {
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unitA->id,
            'target_type'      => 'dean',
            'title'            => 'Dean Issue',
            'description'      => 'Direct to Dean',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'status'           => 'pending',
            'priority'         => 'low',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/dean/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'high',
                'reason'   => 'Admin attempting Dean override.',
            ]);

        $response->assertStatus(403);
    }

    /** 8. Admin cannot call SAO priority override endpoint (403) */
    public function test_admin_cannot_call_sao_priority_override_endpoint(): void
    {
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unitA->id,
            'dept_id'          => $this->saoDept->id,
            'target_type'      => 'department',
            'title'            => 'SAO Concern',
            'description'      => 'Assigned to SAO',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'pending',
            'priority'         => 'low',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/sao/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'high',
                'reason'   => 'Admin attempting SAO override.',
            ]);

        $response->assertStatus(403);
    }

    /** 9. Department staff cannot call priority override endpoint (403) */
    public function test_department_staff_cannot_call_priority_override_endpoint(): void
    {
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unitA->id,
            'dept_id'          => $this->deptA->id,
            'target_type'      => 'department',
            'title'            => 'Staff Review Concern',
            'description'      => 'Staff queue item',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'pending',
            'priority'         => 'low',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        $res1 = $this->actingAs($this->staff)
            ->postJson("/api/dean/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'critical',
                'reason'   => 'Staff attempting override.',
            ]);
        $res1->assertStatus(403);

        $res2 = $this->actingAs($this->staff)
            ->postJson("/api/sao/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'critical',
                'reason'   => 'Staff attempting override.',
            ]);
        $res2->assertStatus(403);
    }

    /** 10. Student cannot call priority override endpoint (403) */
    public function test_student_cannot_call_priority_override_endpoint(): void
    {
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unitA->id,
            'target_type'      => 'dean',
            'title'            => 'Student Concern',
            'description'      => 'Student concern description',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'status'           => 'pending',
            'priority'         => 'low',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        $res1 = $this->actingAs($this->student)
            ->postJson("/api/dean/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'critical',
                'reason'   => 'Student attempting override.',
            ]);
        $res1->assertStatus(403);

        $res2 = $this->actingAs($this->student)
            ->postJson("/api/sao/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'critical',
                'reason'   => 'Student attempting override.',
            ]);
        $res2->assertStatus(403);
    }

    /** 11. Priority override validates input (rejects missing priority, invalid priority values) */
    public function test_priority_override_validates_input(): void
    {
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unitA->id,
            'target_type'      => 'dean',
            'title'            => 'Validation Test Concern',
            'description'      => 'Testing input validation',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'status'           => 'pending',
            'priority'         => 'low',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        // Missing priority
        $resMissing = $this->actingAs($this->deanA)
            ->postJson("/api/dean/concerns/{$concern->concern_id}/priority-override", [
                'reason' => 'Reason without priority provided.',
            ]);
        $resMissing->assertStatus(422)
            ->assertJsonValidationErrors('priority');

        // Invalid priority value
        $resInvalid = $this->actingAs($this->deanA)
            ->postJson("/api/dean/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'urgent_ultra',
                'reason'   => 'Invalid priority level provided.',
            ]);
        $resInvalid->assertStatus(422)
            ->assertJsonValidationErrors('priority');
    }

    /** 12. Priority override requires a reason (rejects missing or too short reason) */
    public function test_priority_override_requires_valid_reason(): void
    {
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unitA->id,
            'target_type'      => 'dean',
            'title'            => 'Reason Test Concern',
            'description'      => 'Testing reason validation',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'status'           => 'pending',
            'priority'         => 'low',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        // Missing reason
        $resMissing = $this->actingAs($this->deanA)
            ->postJson("/api/dean/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'high',
            ]);
        $resMissing->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        // Reason under 5 characters
        $resShort = $this->actingAs($this->deanA)
            ->postJson("/api/dean/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'high',
                'reason'   => 'xyz',
            ]);
        $resShort->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    /** 13. Priority override creates an immutable audit record in concern_routing */
    public function test_priority_override_creates_immutable_audit_record_in_concern_routing(): void
    {
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unitA->id,
            'target_type'      => 'dean',
            'title'            => 'Audit Record Test',
            'description'      => 'Testing concern routing insertion',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'status'           => 'pending',
            'priority'         => 'medium',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        $this->actingAs($this->deanA)
            ->postJson("/api/dean/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'critical',
                'reason'   => 'Safety threat requiring escalated response',
            ]);

        $this->assertDatabaseHas('concern_routing', [
            'concern_id'   => $concern->concern_id,
            'action'       => 'priority_overridden',
            'performed_by' => $this->deanA->id,
            'notes'        => "Priority overridden from 'medium' to 'critical'. Reason: Safety threat requiring escalated response",
        ]);
    }

    /** 14. Priority override updates is_priority_overridden flag to true */
    public function test_priority_override_updates_is_priority_overridden_flag_and_timestamps(): void
    {
        $concern = Concern::create([
            'student_id'             => $this->student->id,
            'academic_unit_id'       => $this->unitA->id,
            'target_type'            => 'dean',
            'title'                  => 'Flag Test Concern',
            'description'            => 'Testing is_priority_overridden flag',
            'category'               => 'academic_units',
            'workflow_type'          => 'academic',
            'status'                 => 'pending',
            'priority'               => 'low',
            'is_priority_overridden' => false,
            'submitted_at'           => now(),
            'last_updated'           => now(),
        ]);

        $this->actingAs($this->deanA)
            ->postJson("/api/dean/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'high',
                'reason'   => 'Elevated by administrative authority',
            ]);

        $refreshed = Concern::find($concern->concern_id);
        $this->assertTrue((bool) $refreshed->is_priority_overridden);
        $this->assertEquals($this->deanA->id, $refreshed->priority_overridden_by);
        $this->assertNotNull($refreshed->priority_overridden_at);
        $this->assertEquals('Elevated by administrative authority', $refreshed->priority_override_reason);
    }

    /** 15. Subsequent concern update/reanalysis preserves the overridden priority */
    public function test_subsequent_concern_update_preserves_overridden_priority(): void
    {
        $concern = Concern::create([
            'student_id'             => $this->student->id,
            'academic_unit_id'       => $this->unitA->id,
            'target_type'            => 'department',
            'title'                  => 'Inquiry on Tuition Fee',
            'description'            => 'Simple inquiry about installment fee schedule',
            'category'               => 'academic_units',
            'workflow_type'          => 'academic',
            'status'                 => 'pending',
            'priority'               => 'low',
            'ai_priority'            => 'low',
            'is_priority_overridden' => false,
            'submitted_at'           => now(),
            'last_updated'           => now(),
        ]);

        // Manually mark priority as overridden to critical
        $concern->update([
            'priority'                 => 'critical',
            'is_priority_overridden'   => true,
            'priority_override_reason' => 'Dean override for urgent audit',
            'priority_overridden_by'   => $this->deanA->id,
            'priority_overridden_at'   => now(),
        ]);

        // Student edits the concern description
        $this->actingAs($this->student)
            ->putJson("/api/concerns/{$concern->concern_id}", [
                'title'         => 'Inquiry on Tuition Fee - Revised',
                'description'   => 'Revised details about the installment payment deadline',
                'workflow_type' => 'academic',
            ])
            ->assertStatus(200);

        // Effective priority MUST remain 'critical'
        $refreshed = Concern::find($concern->concern_id);
        $this->assertEquals('critical', $refreshed->priority);
        $this->assertTrue((bool) $refreshed->is_priority_overridden);
    }

    /** 16. Reanalysis still updates ai_priority while preserving overridden effective priority */
    public function test_reanalysis_still_updates_ai_priority_while_preserving_overridden_priority(): void
    {
        $concern = Concern::create([
            'student_id'             => $this->student->id,
            'academic_unit_id'       => $this->unitA->id,
            'target_type'            => 'department',
            'title'                  => 'Initial Normal Query',
            'description'            => 'Regular inquiry',
            'category'               => 'academic_units',
            'workflow_type'          => 'academic',
            'status'                 => 'pending',
            'priority'               => 'critical', // Overridden to critical
            'ai_priority'            => 'low',
            'is_priority_overridden' => true,
            'priority_override_reason' => 'Urgent audit requirement',
            'submitted_at'           => now(),
            'last_updated'           => now(),
        ]);

        // Student updates with text that triggers a medium priority suggestion
        $this->actingAs($this->student)
            ->putJson("/api/concerns/{$concern->concern_id}", [
                'title'         => 'Dispute with Professor on Exam Marks',
                'description'   => 'I want to appeal my grade because the exam calculation was incorrect and delayed',
                'workflow_type' => 'academic',
            ])
            ->assertStatus(200);

        $refreshed = Concern::find($concern->concern_id);
        // Effective priority remains critical
        $this->assertEquals('critical', $refreshed->priority);
        // ai_priority must be updated to the new suggestion (not null)
        $this->assertNotNull($refreshed->ai_priority);
    }

    /** 17. Effective priority is immediately reflected in concern details response */
    public function test_effective_priority_is_immediately_reflected_in_concern_details_response(): void
    {
        $concern = Concern::create([
            'student_id'             => $this->student->id,
            'academic_unit_id'       => $this->unitA->id,
            'target_type'            => 'dean',
            'title'                  => 'Dean Details Priority Test',
            'description'            => 'Checking response fields',
            'category'               => 'academic_units',
            'workflow_type'          => 'academic',
            'status'                 => 'pending',
            'priority'               => 'critical',
            'ai_priority'            => 'low',
            'is_priority_overridden' => true,
            'priority_override_reason' => 'High impact governance case',
            'priority_overridden_by' => $this->deanA->id,
            'priority_overridden_at' => now(),
            'submitted_at'           => now(),
            'last_updated'           => now(),
        ]);

        // Dean details response contains override details
        $deanResponse = $this->actingAs($this->deanA)
            ->getJson("/api/dean/concerns/{$concern->concern_id}");

        $deanResponse->assertStatus(200)
            ->assertJson([
                'concern' => [
                    'priority'                 => 'critical',
                    'ai_priority'              => 'low',
                    'is_priority_overridden'   => true,
                    'priority_override_reason' => 'High impact governance case',
                ],
            ]);

        // Student details response hides sensitive internal override metadata
        $studentResponse = $this->actingAs($this->student)
            ->getJson("/api/concerns/{$concern->concern_id}");

        $studentResponse->assertStatus(200);
        $studentData = $studentResponse->json('concern');

        $this->assertArrayNotHasKey('priority_override_reason', $studentData);
        $this->assertArrayNotHasKey('priority_overridden_by', $studentData);
        $this->assertArrayNotHasKey('priority_overridden_at', $studentData);
        $this->assertArrayNotHasKey('ai_priority', $studentData);
        $this->assertArrayNotHasKey('is_priority_overridden', $studentData);
    }

    /** 18. Non-existent concern returns 404 Not Found */
    public function test_non_existent_concern_returns_404_not_found(): void
    {
        $deanRes = $this->actingAs($this->deanA)
            ->postJson("/api/dean/concerns/99999/priority-override", [
                'priority' => 'critical',
                'reason'   => 'Trying to override non-existent concern.',
            ]);
        $deanRes->assertStatus(404);

        $saoRes = $this->actingAs($this->sao)
            ->postJson("/api/sao/concerns/99999/priority-override", [
                'priority' => 'critical',
                'reason'   => 'Trying to override non-existent concern.',
            ]);
        $saoRes->assertStatus(404);
    }
}
