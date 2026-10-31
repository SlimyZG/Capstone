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
 * Phase 5.1 — SAO Override Interface & Audit History Hardening Test Suite
 *
 * Covers:
 * 1. SAO can see concerns assigned to its department in /api/sao/assigned-concerns.
 * 2. SAO cannot see other departments' concerns in /api/sao/assigned-concerns.
 * 3. SAO can open an authorized individual concern (/api/sao/concerns/{id}).
 * 4. SAO cannot open an unauthorized individual concern from another department (403).
 * 5. SAO can override an authorized concern's priority.
 * 6. SAO cannot override an unassigned concern (403).
 * 7. Non-SAO roles (student, dean, dept_staff, admin) cannot access /api/sao/assigned-concerns (403).
 * 8. Deleting a user preserves their routing history (performed_by becomes null, record remains).
 * 9. Historical actor name is preserved via performed_by_name snapshot after user deletion.
 * 10. ConcernRouting::create automatically snapshots performer's name into performed_by_name.
 */
class SaoAssignedConcernsAndAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $sao;
    private User $admin;
    private User $dean;
    private User $staff;
    private User $student;
    private AcademicUnit $unit;
    private Department $saoDept;
    private Department $cashierDept;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = AcademicUnit::create(['name' => 'College of Computer Studies']);

        $this->saoDept = Department::create(['name' => 'SAO ( Student Affairs Office )']);
        $this->cashierDept = Department::create(['name' => 'Cashier']);

        $this->sao = User::create([
            'name'     => 'SAO Director',
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
            'name'             => 'CCS Dean',
            'email'            => 'dean@scms.local',
            'password'         => Hash::make('password123'),
            'role'             => 'dean',
            'academic_unit_id' => $this->unit->id,
        ]);

        $this->staff = User::create([
            'name'     => 'Cashier Staff',
            'email'    => 'staff@scms.local',
            'password' => Hash::make('password123'),
            'role'     => 'dept_staff',
            'dept_id'  => $this->cashierDept->id,
        ]);

        $this->student = User::create([
            'name'              => 'Student User',
            'email'             => 'student@scms.local',
            'student_id_number' => '12345678',
            'password'          => Hash::make('password123'),
            'role'              => 'student',
            'academic_unit_id'  => $this->unit->id,
        ]);
    }

    /** 1. SAO can see concerns assigned to its department in /api/sao/assigned-concerns */
    public function test_sao_can_see_concerns_assigned_to_its_department(): void
    {
        // Concern assigned to SAO
        $saoConcern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unit->id,
            'dept_id'          => $this->saoDept->id,
            'target_type'      => 'department',
            'title'            => 'Student Council Election Permit',
            'description'      => 'Need approval for campaign permits',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'pending',
            'priority'         => 'low',
            'ai_priority'      => 'low',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        $response = $this->actingAs($this->sao)->getJson('/api/sao/assigned-concerns');

        $response->assertStatus(200);
        $concerns = $response->json('concerns.data');
        $this->assertNotEmpty($concerns);
        $this->assertEquals($saoConcern->concern_id, $concerns[0]['concern_id']);
        $this->assertEquals('Student Council Election Permit', $concerns[0]['title']);
    }

    /** 2. SAO cannot see other departments' concerns in /api/sao/assigned-concerns */
    public function test_sao_cannot_see_other_departments_concerns(): void
    {
        // Concern assigned to Cashier
        $cashierConcern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unit->id,
            'dept_id'          => $this->cashierDept->id,
            'target_type'      => 'department',
            'title'            => 'Tuition Refund Overpayment',
            'description'      => 'Cashier department payment issue',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'pending',
            'priority'         => 'medium',
            'ai_priority'      => 'medium',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        $response = $this->actingAs($this->sao)->getJson('/api/sao/assigned-concerns');

        $response->assertStatus(200);
        $concerns = $response->json('concerns.data');
        $ids = array_column($concerns, 'concern_id');
        $this->assertNotContains($cashierConcern->concern_id, $ids);
    }

    /** 3. SAO can open an authorized individual concern */
    public function test_sao_can_open_authorized_individual_concern(): void
    {
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unit->id,
            'dept_id'          => $this->saoDept->id,
            'target_type'      => 'department',
            'title'            => 'Club Accreditation',
            'description'      => 'SAO approval required for campus club',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'pending',
            'priority'         => 'low',
            'ai_priority'      => 'low',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        $response = $this->actingAs($this->sao)->getJson("/api/sao/concerns/{$concern->concern_id}");

        $response->assertStatus(200)
            ->assertJson([
                'concern' => [
                    'concern_id' => $concern->concern_id,
                    'title'      => 'Club Accreditation',
                ],
            ]);
    }

    /** 4. SAO cannot open an unauthorized individual concern from another department (403) */
    public function test_sao_cannot_open_unauthorized_individual_concern(): void
    {
        $cashierConcern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unit->id,
            'dept_id'          => $this->cashierDept->id,
            'target_type'      => 'department',
            'title'            => 'Cashier Refund',
            'description'      => 'Not assigned to SAO',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'pending',
            'priority'         => 'medium',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        $response = $this->actingAs($this->sao)->getJson("/api/sao/concerns/{$cashierConcern->concern_id}");

        $response->assertStatus(403);
    }

    /** 5. SAO can override an authorized concern's priority */
    public function test_sao_can_override_authorized_concern_priority(): void
    {
        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unit->id,
            'dept_id'          => $this->saoDept->id,
            'target_type'      => 'department',
            'title'            => 'Campus Security Escort Request',
            'description'      => 'Night shift student organization safety concern',
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
                'priority' => 'critical',
                'reason'   => 'Immediate student night travel security threat.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Priority overridden successfully.',
                'concern' => [
                    'priority'               => 'critical',
                    'is_priority_overridden' => true,
                    'priority_override_reason' => 'Immediate student night travel security threat.',
                ],
            ]);

        $this->assertDatabaseHas('concerns', [
            'concern_id'             => $concern->concern_id,
            'priority'               => 'critical',
            'is_priority_overridden' => true,
            'priority_overridden_by' => $this->sao->id,
        ]);
    }

    /** 6. SAO cannot override an unassigned concern (403) */
    public function test_sao_cannot_override_unassigned_concern(): void
    {
        $cashierConcern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unit->id,
            'dept_id'          => $this->cashierDept->id,
            'target_type'      => 'department',
            'title'            => 'Cashier Receipt',
            'description'      => 'Not for SAO',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'pending',
            'priority'         => 'low',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        $response = $this->actingAs($this->sao)
            ->postJson("/api/sao/concerns/{$cashierConcern->concern_id}/priority-override", [
                'priority' => 'critical',
                'reason'   => 'Attempted override on non-SAO concern.',
            ]);

        $response->assertStatus(403);
    }

    /** 7. Non-SAO roles cannot access /api/sao/assigned-concerns (403) */
    public function test_non_sao_roles_cannot_access_sao_assigned_concerns(): void
    {
        // Student
        $this->actingAs($this->student)->getJson('/api/sao/assigned-concerns')->assertStatus(403);
        // Dean
        $this->actingAs($this->dean)->getJson('/api/sao/assigned-concerns')->assertStatus(403);
        // Dept Staff
        $this->actingAs($this->staff)->getJson('/api/sao/assigned-concerns')->assertStatus(403);
        // Admin
        $this->actingAs($this->admin)->getJson('/api/sao/assigned-concerns')->assertStatus(403);
    }

    /** 8. Deleting a user preserves their routing history (performed_by becomes null, record remains) */
    public function test_deleting_user_preserves_routing_history_records(): void
    {
        $temporaryStaff = User::create([
            'name'     => 'Temporary Staff Member',
            'email'    => 'temp.staff@scms.local',
            'password' => Hash::make('password123'),
            'role'     => 'dept_staff',
            'dept_id'  => $this->cashierDept->id,
        ]);

        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unit->id,
            'dept_id'          => $this->cashierDept->id,
            'target_type'      => 'department',
            'title'            => 'Audit Preservation Test Concern',
            'description'      => 'Testing that routing records survive actor deletion',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'in_progress',
            'priority'         => 'medium',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        $routing = ConcernRouting::create([
            'concern_id'        => $concern->concern_id,
            'action'            => 'status_changed',
            'performed_by'      => $temporaryStaff->id,
            'performed_by_name' => 'Temporary Staff Member',
            'notes'             => 'Initial investigation started by temporary staff.',
        ]);

        $routingId = $routing->id;

        // Delete the temporary staff user
        $temporaryStaff->delete();

        // The routing record MUST still exist in database
        $this->assertDatabaseHas('concern_routing', [
            'id'                 => $routingId,
            'concern_id'         => $concern->concern_id,
            'performed_by'       => null,
            'performed_by_name'  => 'Temporary Staff Member',
            'notes'              => 'Initial investigation started by temporary staff.',
        ]);
    }

    /** 9. Historical actor name is preserved via performed_by_name snapshot after user deletion */
    public function test_historical_actor_name_is_preserved_via_performed_by_name_snapshot(): void
    {
        $formerOfficer = User::create([
            'name'     => 'Director Jane Doe',
            'email'    => 'jane.doe@scms.local',
            'password' => Hash::make('password123'),
            'role'     => 'sao',
        ]);

        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unit->id,
            'dept_id'          => $this->saoDept->id,
            'target_type'      => 'department',
            'title'            => 'Snapshot Field Integrity Test',
            'description'      => 'Testing snapshot accessor',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'pending',
            'priority'         => 'low',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        $routing = ConcernRouting::create([
            'concern_id'        => $concern->concern_id,
            'action'            => 'priority_overridden',
            'performed_by'      => $formerOfficer->id,
            'performed_by_name' => 'Director Jane Doe',
            'notes'             => "Priority overridden from 'low' to 'critical'.",
        ]);

        // Delete user
        $formerOfficer->delete();

        $freshRouting = ConcernRouting::find($routing->id);
        $this->assertNull($freshRouting->performed_by);
        $this->assertEquals('Director Jane Doe', $freshRouting->performed_by_name);
        $this->assertEquals('Director Jane Doe', $freshRouting->actor_name);
    }

    /** 10. ConcernRouting::create automatically snapshots performer name into performed_by_name */
    public function test_concern_routing_create_automatically_snapshots_performer_name(): void
    {
        $officer = User::create([
            'name'     => 'Active Officer Smith',
            'email'    => 'smith@scms.local',
            'password' => Hash::make('password123'),
            'role'     => 'sao',
        ]);

        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->unit->id,
            'dept_id'          => $this->saoDept->id,
            'target_type'      => 'department',
            'title'            => 'Automatic Snapshot Test',
            'description'      => 'Model boot event check',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'pending',
            'priority'         => 'low',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        // Create without passing performed_by_name explicitly
        $routing = ConcernRouting::create([
            'concern_id'   => $concern->concern_id,
            'action'       => 'approved',
            'performed_by' => $officer->id,
            'notes'        => 'Action taken.',
        ]);

        $this->assertEquals('Active Officer Smith', $routing->performed_by_name);
        $this->assertDatabaseHas('concern_routing', [
            'id'                => $routing->id,
            'performed_by_name' => 'Active Officer Smith',
        ]);
    }
}
