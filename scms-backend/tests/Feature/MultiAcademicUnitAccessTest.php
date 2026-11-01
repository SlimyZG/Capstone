<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Concern;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiAcademicUnitAccessTest extends TestCase
{
    use RefreshDatabase;

    private AcademicUnit $ccsUnit;
    private AcademicUnit $marineUnit;
    private AcademicUnit $nursingUnit;

    private Department $cashierDept;
    private Department $registrarDept;
    private Department $saoDept;

    private User $ccsDean;
    private User $marineDean;
    private User $nursingDean;

    private User $ccsStaff;
    private User $marineStaff;
    private User $cashierStaff;

    private User $saoUser;

    private User $ccsStudent;
    private User $marineStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ccsUnit = AcademicUnit::create(['name' => 'College of Computer Studies', 'code' => 'CCS']);
        $this->marineUnit = AcademicUnit::create(['name' => 'College of Marine Transportation', 'code' => 'CMT']);
        $this->nursingUnit = AcademicUnit::create(['name' => 'College of Nursing', 'code' => 'CON']);

        $this->cashierDept = Department::create(['name' => 'Cashier']);
        $this->registrarDept = Department::create(['name' => 'Registrar']);
        $this->saoDept = Department::create(['name' => 'Student Affairs Office']);

        $this->ccsDean = User::create([
            'name' => 'CCS Dean',
            'email' => 'dean_ccs_' . rand(1000, 9999) . '@test.com',
            'password' => bcrypt('password123'),
            'role' => 'dean',
            'academic_unit_id' => $this->ccsUnit->id,
        ]);

        $this->marineDean = User::create([
            'name' => 'Marine Dean',
            'email' => 'dean_marine_' . rand(1000, 9999) . '@test.com',
            'password' => bcrypt('password123'),
            'role' => 'dean',
            'academic_unit_id' => $this->marineUnit->id,
        ]);

        $this->nursingDean = User::create([
            'name' => 'Nursing Dean',
            'email' => 'dean_nursing_' . rand(1000, 9999) . '@test.com',
            'password' => bcrypt('password123'),
            'role' => 'dean',
            'academic_unit_id' => $this->nursingUnit->id,
        ]);

        $this->ccsStaff = User::create([
            'name' => 'CCS Staff',
            'email' => 'staff_ccs_' . rand(1000, 9999) . '@test.com',
            'password' => bcrypt('password123'),
            'role' => 'dept_staff',
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id' => null,
        ]);

        $this->marineStaff = User::create([
            'name' => 'Marine Staff',
            'email' => 'staff_marine_' . rand(1000, 9999) . '@test.com',
            'password' => bcrypt('password123'),
            'role' => 'dept_staff',
            'academic_unit_id' => $this->marineUnit->id,
            'dept_id' => null,
        ]);

        $this->cashierStaff = User::create([
            'name' => 'Cashier Staff',
            'email' => 'staff_cashier_' . rand(1000, 9999) . '@test.com',
            'password' => bcrypt('password123'),
            'role' => 'dept_staff',
            'academic_unit_id' => null,
            'dept_id' => $this->cashierDept->id,
        ]);

        $this->saoUser = User::create([
            'name' => 'SAO Officer',
            'email' => 'sao_officer_' . rand(1000, 9999) . '@test.com',
            'password' => bcrypt('password123'),
            'role' => 'sao',
        ]);

        $this->ccsStudent = User::create([
            'name' => 'CCS Student',
            'email' => 'student_ccs_' . rand(1000, 9999) . '@test.com',
            'student_id_number' => '88880001',
            'password' => bcrypt('password123'),
            'role' => 'student',
            'academic_unit_id' => $this->ccsUnit->id,
        ]);

        $this->marineStudent = User::create([
            'name' => 'Marine Student',
            'email' => 'student_marine_' . rand(1000, 9999) . '@test.com',
            'student_id_number' => '88880002',
            'password' => bcrypt('password123'),
            'role' => 'student',
            'academic_unit_id' => $this->marineUnit->id,
        ]);
    }

    /**
     * Requirement 1: Computer Studies Dean CANNOT see Marine Transportation or Nursing concerns.
     */
    public function test_ccs_dean_cannot_see_marine_or_nursing_concerns(): void
    {
        // Create escalated academic concern for Marine
        $marineConcern = Concern::create([
            'student_id' => $this->marineStudent->id,
            'academic_unit_id' => $this->marineUnit->id,
            'target_type' => 'department',
            'dept_id' => null,
            'title' => 'Marine Bridge Simulator Malfunction',
            'description' => 'ECDIS simulator radar overlay is lagging.',
            'category' => 'academic_units',
            'workflow_type' => 'academic',
            'status' => 'escalated',
            'submitted_at' => now(),
            'last_updated' => now(),
        ]);

        // Create direct dean concern for Nursing
        $nursingConcern = Concern::create([
            'student_id' => $this->marineStudent->id,
            'academic_unit_id' => $this->nursingUnit->id,
            'target_type' => 'dean',
            'dept_id' => null,
            'title' => 'Nursing Clinical Duty Hours',
            'description' => 'Clinical rotation shift overlap issue.',
            'category' => 'academic_units',
            'workflow_type' => 'academic',
            'status' => 'pending',
            'submitted_at' => now(),
            'last_updated' => now(),
        ]);

        // CCS Dean queries inbox
        $response = $this->actingAs($this->ccsDean)->getJson('/api/dean/inbox');
        $response->assertStatus(200);

        $directIds = collect($response->json('direct'))->pluck('concern_id')->toArray();
        $escalatedIds = collect($response->json('escalated'))->pluck('concern_id')->toArray();

        $this->assertNotContains($marineConcern->concern_id, $escalatedIds);
        $this->assertNotContains($marineConcern->concern_id, $directIds);
        $this->assertNotContains($nursingConcern->concern_id, $directIds);
        $this->assertNotContains($nursingConcern->concern_id, $escalatedIds);

        // CCS Dean direct show attempt on Marine concern must fail 404
        $showResponse = $this->actingAs($this->ccsDean)->getJson("/api/dean/concerns/{$marineConcern->concern_id}");
        $showResponse->assertStatus(404);
    }

    /**
     * Requirement 2: Marine Transportation Dean CANNOT see Computer Studies concerns.
     */
    public function test_marine_dean_cannot_see_ccs_concerns(): void
    {
        $ccsConcern = Concern::create([
            'student_id' => $this->ccsStudent->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'target_type' => 'department',
            'dept_id' => null,
            'title' => 'CCS Laboratory Compiler Crash',
            'description' => 'GCC compiler segfaults on C lab workstations.',
            'category' => 'academic_units',
            'workflow_type' => 'academic',
            'status' => 'escalated',
            'submitted_at' => now(),
            'last_updated' => now(),
        ]);

        $response = $this->actingAs($this->marineDean)->getJson('/api/dean/inbox');
        $response->assertStatus(200);

        $escalatedIds = collect($response->json('escalated'))->pluck('concern_id')->toArray();
        $this->assertNotContains($ccsConcern->concern_id, $escalatedIds);

        $showResponse = $this->actingAs($this->marineDean)->getJson("/api/dean/concerns/{$ccsConcern->concern_id}");
        $showResponse->assertStatus(404);
    }

    /**
     * Requirement 3: Marine Transportation Dept Staff CANNOT see unauthorized academic or operational concerns.
     */
    public function test_marine_staff_cannot_see_unauthorized_concerns(): void
    {
        // CCS Academic Concern
        $ccsConcern = Concern::create([
            'student_id' => $this->ccsStudent->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'target_type' => 'department',
            'dept_id' => null,
            'title' => 'CCS Python Version Mismatch',
            'description' => 'Python 3.8 installed instead of 3.12.',
            'category' => 'academic_units',
            'workflow_type' => 'academic',
            'status' => 'pending',
            'submitted_at' => now(),
            'last_updated' => now(),
        ]);

        // Cashier Non-Academic Concern submitted by Marine Student
        $cashierConcern = Concern::create([
            'student_id' => $this->marineStudent->id,
            'academic_unit_id' => $this->marineUnit->id,
            'target_type' => 'department',
            'dept_id' => $this->cashierDept->id,
            'title' => 'Tuition Receipt Not Available',
            'description' => 'Paid tuition yesterday but OR is still unverified.',
            'category' => 'operational_departments',
            'workflow_type' => 'non_academic',
            'status' => 'pending',
            'submitted_at' => now(),
            'last_updated' => now(),
        ]);

        $response = $this->actingAs($this->marineStaff)->getJson('/api/dept-staff/queue');
        $response->assertStatus(200);

        $queueIds = collect($response->json('concerns'))->pluck('concern_id')->toArray();

        // Marine staff must NOT see CCS academic concern
        $this->assertNotContains($ccsConcern->concern_id, $queueIds);
        // Marine staff must NOT see Cashier non-academic concern even though student is Marine
        $this->assertNotContains($cashierConcern->concern_id, $queueIds);
    }

    /**
     * Requirement 4: Operational Department Staff sees their department concerns, regardless of student college.
     */
    public function test_operational_staff_sees_assigned_department_concerns(): void
    {
        // Cashier concern from CCS student
        $cashierCcs = Concern::create([
            'student_id' => $this->ccsStudent->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'target_type' => 'department',
            'dept_id' => $this->cashierDept->id,
            'title' => 'Payment Reconciliation Request',
            'description' => 'Online bank transfer verification.',
            'category' => 'operational_departments',
            'workflow_type' => 'non_academic',
            'status' => 'pending',
            'submitted_at' => now(),
            'last_updated' => now(),
        ]);

        // Cashier concern from Marine student
        $cashierMarine = Concern::create([
            'student_id' => $this->marineStudent->id,
            'academic_unit_id' => $this->marineUnit->id,
            'target_type' => 'department',
            'dept_id' => $this->cashierDept->id,
            'title' => 'Enrollment Downpayment Assessment',
            'description' => 'Downpayment assessment not updating on portal.',
            'category' => 'operational_departments',
            'workflow_type' => 'non_academic',
            'status' => 'pending',
            'submitted_at' => now(),
            'last_updated' => now(),
        ]);

        // Academic concern (Marine)
        $marineAcademic = Concern::create([
            'student_id' => $this->marineStudent->id,
            'academic_unit_id' => $this->marineUnit->id,
            'target_type' => 'department',
            'dept_id' => null,
            'title' => 'Seamanship Lecture Room Conflict',
            'description' => 'Room 302 assigned to two simultaneous classes.',
            'category' => 'academic_units',
            'workflow_type' => 'academic',
            'status' => 'pending',
            'submitted_at' => now(),
            'last_updated' => now(),
        ]);

        $response = $this->actingAs($this->cashierStaff)->getJson('/api/dept-staff/queue');
        $response->assertStatus(200);

        $queueIds = collect($response->json('concerns'))->pluck('concern_id')->toArray();

        $this->assertContains($cashierCcs->concern_id, $queueIds);
        $this->assertContains($cashierMarine->concern_id, $queueIds);
        $this->assertNotContains($marineAcademic->concern_id, $queueIds);
    }

    /**
     * Requirement 5: SAO retains aggregate oversight and Option A individual concern restrictions.
     */
    public function test_sao_retains_oversight_and_option_a_restrictions(): void
    {
        // Academic concern not assigned to SAO
        $unassigned = Concern::create([
            'student_id' => $this->marineStudent->id,
            'academic_unit_id' => $this->marineUnit->id,
            'target_type' => 'department',
            'dept_id' => null,
            'title' => 'Internal Marine Concern',
            'description' => 'Navigation chart conflict.',
            'category' => 'academic_units',
            'workflow_type' => 'academic',
            'status' => 'pending',
            'submitted_at' => now(),
            'last_updated' => now(),
        ]);

        // SAO Oversight endpoint responds 200 with aggregate metrics
        $oversightRes = $this->actingAs($this->saoUser)->getJson('/api/sao/oversight');
        $oversightRes->assertStatus(200)
            ->assertJsonStructure(['summary' => ['total_submitted', 'pending', 'resolved']]);

        // SAO Assigned concerns must NOT list the unassigned concern
        $assignedRes = $this->actingAs($this->saoUser)->getJson('/api/sao/assigned-concerns');
        $assignedRes->assertStatus(200);
        $assignedIds = collect($assignedRes->json('concerns.data'))->pluck('concern_id')->toArray();
        $this->assertNotContains($unassigned->concern_id, $assignedIds);

        // Attempting to show unassigned individual concern details as SAO must fail 403 (Option A)
        $showRes = $this->actingAs($this->saoUser)->getJson("/api/sao/concerns/{$unassigned->concern_id}");
        $showRes->assertStatus(403);
    }

    /**
     * Requirement 6: Students only see their own concerns.
     */
    public function test_student_only_sees_own_concerns(): void
    {
        $marineConcern = Concern::create([
            'student_id' => $this->marineStudent->id,
            'academic_unit_id' => $this->marineUnit->id,
            'target_type' => 'department',
            'dept_id' => null,
            'title' => 'Marine Student Concern',
            'description' => 'Marine concern description.',
            'category' => 'academic_units',
            'workflow_type' => 'academic',
            'status' => 'pending',
            'submitted_at' => now(),
            'last_updated' => now(),
        ]);

        $ccsConcern = Concern::create([
            'student_id' => $this->ccsStudent->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'target_type' => 'department',
            'dept_id' => null,
            'title' => 'CCS Student Concern',
            'description' => 'CCS concern description.',
            'category' => 'academic_units',
            'workflow_type' => 'academic',
            'status' => 'pending',
            'submitted_at' => now(),
            'last_updated' => now(),
        ]);

        // Marine student queries concerns
        $response = $this->actingAs($this->marineStudent)->getJson('/api/concerns');
        $response->assertStatus(200);

        $ids = collect($response->json('concerns'))->pluck('concern_id')->toArray();
        $this->assertContains($marineConcern->concern_id, $ids);
        $this->assertNotContains($ccsConcern->concern_id, $ids);
    }

    /**
     * Requirement 7: Dean and Dept Staff APIs respond 200 for Marine Transportation and CCS accounts.
     */
    public function test_dean_and_staff_apis_respond_200(): void
    {
        // Marine Dean
        $this->actingAs($this->marineDean)->getJson('/api/dean/inbox')->assertStatus(200);
        $this->actingAs($this->marineDean)->getJson('/api/dean/class-list')->assertStatus(200);
        $this->actingAs($this->marineDean)->getJson('/api/dean/recurring-incidents')->assertStatus(200);
        $this->actingAs($this->marineDean)->getJson('/api/dean/reports')->assertStatus(200);

        // Marine Staff
        $this->actingAs($this->marineStaff)->getJson('/api/dept-staff/queue')->assertStatus(200);
        $this->actingAs($this->marineStaff)->getJson('/api/dept-staff/recurring-incidents')->assertStatus(200);
        $this->actingAs($this->marineStaff)->getJson('/api/dept-staff/reports')->assertStatus(200);

        // CCS Dean
        $this->actingAs($this->ccsDean)->getJson('/api/dean/inbox')->assertStatus(200);
        $this->actingAs($this->ccsDean)->getJson('/api/dean/class-list')->assertStatus(200);
        $this->actingAs($this->ccsDean)->getJson('/api/dean/recurring-incidents')->assertStatus(200);
        $this->actingAs($this->ccsDean)->getJson('/api/dean/reports')->assertStatus(200);

        // CCS Staff
        $this->actingAs($this->ccsStaff)->getJson('/api/dept-staff/queue')->assertStatus(200);
        $this->actingAs($this->ccsStaff)->getJson('/api/dept-staff/recurring-incidents')->assertStatus(200);
        $this->actingAs($this->ccsStaff)->getJson('/api/dept-staff/reports')->assertStatus(200);
    }

    /**
     * Requirement 8: Transfer to operational department preserves original academic unit without falling back to unit 1.
     */
    public function test_transfer_to_operational_department_preserves_original_academic_unit_without_fallback_to_unit_one(): void
    {
        // Create an academic concern for Marine Transportation
        $concern = Concern::create([
            'student_id' => $this->marineStudent->id,
            'academic_unit_id' => $this->marineUnit->id,
            'target_type' => 'department',
            'dept_id' => null,
            'title' => 'Marine Lab Transfer Test',
            'description' => 'Tuition billing inquiry mistakenly submitted to Marine staff.',
            'category' => 'academic_units',
            'workflow_type' => 'academic',
            'status' => 'pending',
            'submitted_at' => now(),
            'last_updated' => now(),
        ]);

        $this->assertEquals($this->marineUnit->id, $concern->academic_unit_id);
        $this->assertNotEquals(1, $concern->academic_unit_id);

        // Marine staff transfers the concern to Cashier
        $response = $this->actingAs($this->marineStaff)->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfer", [
            'target_category' => 'operational_departments',
            'target_id' => $this->cashierDept->id,
            'notes' => 'Please redirect to Cashier for fee verification.',
        ]);

        $response->assertStatus(200);

        // Refresh from database
        $concern->refresh();

        // Must now be in operational department
        $this->assertEquals('operational_departments', $concern->category);
        $this->assertEquals('non_academic', $concern->workflow_type);
        $this->assertEquals($this->cashierDept->id, $concern->dept_id);

        // Must retain Marine Transportation academic unit ID and NOT be overwritten with unit 1
        $this->assertEquals($this->marineUnit->id, $concern->academic_unit_id);
        $this->assertNotEquals(1, $concern->academic_unit_id);
    }
}

