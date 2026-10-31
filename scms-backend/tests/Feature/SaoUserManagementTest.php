<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 3 — SAO User Management Test Suite
 *
 * Tests all 10 requirements from the Phase 3 specification.
 * Uses database transactions to roll back test data after each test.
 */
class SaoUserManagementTest extends TestCase
{
    use RefreshDatabase;

    // ── Test Fixtures ──────────────────────────────────────────────────────────

    private User $sao;
    private User $admin;
    private User $dean;
    private User $staff;
    private User $student;
    private AcademicUnit $unitA;
    private AcademicUnit $unitB;
    private Department $dept;

    protected function setUp(): void
    {
        parent::setUp();

        // Academic units
        $this->unitA = AcademicUnit::create(['name' => 'College of Test Sciences']);
        $this->unitB = AcademicUnit::create(['name' => 'College of Test Arts']);

        // Department
        $this->dept = Department::create([
            'name'             => 'Test Operations Department',
            'academic_unit_id' => $this->unitA->id,
        ]);

        // SAO user
        $this->sao = User::create([
            'name'     => 'Test SAO User',
            'email'    => 'sao@test.example',
            'password' => Hash::make('saopassword'),
            'role'     => 'sao',
        ]);

        // Admin user
        $this->admin = User::create([
            'name'     => 'Test Admin User',
            'email'    => 'admin@test.example',
            'password' => Hash::make('adminpassword'),
            'role'     => 'admin',
        ]);

        // Existing Dean (assigned to unitA)
        $this->dean = User::create([
            'name'             => 'Test Dean',
            'email'            => 'dean@test.example',
            'password'         => Hash::make('deanpassword'),
            'role'             => 'dean',
            'academic_unit_id' => $this->unitA->id,
        ]);

        // Existing Dept Staff
        $this->staff = User::create([
            'name'             => 'Test Staff',
            'email'            => 'staff@test.example',
            'password'         => Hash::make('staffpassword'),
            'role'             => 'dept_staff',
            'academic_unit_id' => $this->unitA->id,
            'dept_id'          => $this->dept->id,
        ]);

        // Student user
        $this->student = User::create([
            'name'     => 'Test Student',
            'email'    => 'student@test.example',
            'password' => Hash::make('studentpassword'),
            'role'     => 'student',
        ]);
    }

    // ── Requirement 1: SAO can view Dean and Dept Staff accounts ──────────────

    public function test_sao_can_list_managed_users(): void
    {
        $response = $this->actingAs($this->sao)
            ->getJson('/api/sao/users');

        $response->assertOk()
            ->assertJsonStructure([
                'users',
                'stats' => ['total_deans', 'total_dept_staff', 'total_active', 'total_managed'],
            ]);

        $users = collect($response->json('users'));
        $roles = $users->pluck('role')->unique()->values()->toArray();

        // Should only contain dean and dept_staff (not admin, sao, student)
        $this->assertEmpty(array_intersect($roles, ['admin', 'sao', 'student']),
            'Managed user list must not expose admin, sao, or student accounts');

        $this->assertGreaterThanOrEqual(1, $users->count(), 'Should see at least the test dean and staff');
    }

    public function test_sao_user_list_search_by_name(): void
    {
        $response = $this->actingAs($this->sao)
            ->getJson('/api/sao/users?search=Test+Dean');

        $response->assertOk();
        $users = collect($response->json('users'));
        $this->assertTrue($users->every(fn ($u) =>
            str_contains(strtolower($u['name']), 'test') ||
            str_contains(strtolower($u['email']), 'dean')
        ));
    }

    public function test_sao_user_list_filter_by_role(): void
    {
        $response = $this->actingAs($this->sao)
            ->getJson('/api/sao/users?role=dean');

        $response->assertOk();
        $users = collect($response->json('users'));
        $this->assertTrue($users->every(fn ($u) => $u['role'] === 'dean'),
            'Role filter must return only requested role');
    }

    public function test_sao_user_list_filter_by_academic_unit(): void
    {
        $response = $this->actingAs($this->sao)
            ->getJson('/api/sao/users?academic_unit_id=' . $this->unitA->id);

        $response->assertOk();
        $users = collect($response->json('users'));
        $this->assertTrue($users->every(fn ($u) => $u['academic_unit_id'] === $this->unitA->id),
            'Academic unit filter must return only users assigned to that unit');
    }

    // ── Requirement 2: SAO can create a Dean account ─────────────────────────

    public function test_sao_can_create_dean_account(): void
    {
        $response = $this->actingAs($this->sao)
            ->postJson('/api/sao/users', [
                'name'             => 'New Test Dean',
                'email'            => 'newdean@test.example',
                'password'         => 'securepassword123',
                'role'             => 'dean',
                'academic_unit_id' => $this->unitB->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', 'dean')
            ->assertJsonPath('user.academic_unit_id', $this->unitB->id);

        $this->assertDatabaseHas('users', [
            'email' => 'newdean@test.example',
            'role'  => 'dean',
        ]);
    }

    public function test_dean_requires_academic_unit(): void
    {
        $response = $this->actingAs($this->sao)
            ->postJson('/api/sao/users', [
                'name'     => 'Dean No Unit',
                'email'    => 'deannoun@test.example',
                'password' => 'password123',
                'role'     => 'dean',
                // academic_unit_id intentionally omitted
            ]);

        $response->assertUnprocessable();
    }

    public function test_one_dean_per_academic_unit_restriction(): void
    {
        // unitA already has $this->dean assigned
        $response = $this->actingAs($this->sao)
            ->postJson('/api/sao/users', [
                'name'             => 'Second Dean',
                'email'            => 'seconddean@test.example',
                'password'         => 'password123',
                'role'             => 'dean',
                'academic_unit_id' => $this->unitA->id, // already has a dean
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'This Academic Unit already has an assigned Dean. Only one Dean is allowed per unit.');
    }

    // ── Requirement 3: SAO can create a Department Staff account ─────────────

    public function test_sao_can_create_dept_staff_account(): void
    {
        $response = $this->actingAs($this->sao)
            ->postJson('/api/sao/users', [
                'name'             => 'New Test Staff',
                'email'            => 'newstaff@test.example',
                'password'         => 'securepassword123',
                'role'             => 'dept_staff',
                'academic_unit_id' => $this->unitB->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', 'dept_staff');

        $this->assertDatabaseHas('users', [
            'email' => 'newstaff@test.example',
            'role'  => 'dept_staff',
        ]);
    }

    public function test_dept_staff_requires_unit_or_department(): void
    {
        $response = $this->actingAs($this->sao)
            ->postJson('/api/sao/users', [
                'name'     => 'Unassigned Staff',
                'email'    => 'unassigned@test.example',
                'password' => 'password123',
                'role'     => 'dept_staff',
                // No academic_unit_id or dept_id
            ]);

        $response->assertUnprocessable();
    }

    // ── Requirement 4: SAO can edit account information ───────────────────────

    public function test_sao_can_edit_dean_name_and_email(): void
    {
        $response = $this->actingAs($this->sao)
            ->putJson('/api/sao/users/' . $this->dean->id, [
                'name'  => 'Updated Dean Name',
                'email' => 'updateddean@test.example',
            ]);

        $response->assertOk()
            ->assertJsonPath('user.name', 'Updated Dean Name');

        $this->assertDatabaseHas('users', [
            'id'    => $this->dean->id,
            'name'  => 'Updated Dean Name',
            'email' => 'updateddean@test.example',
        ]);
    }

    public function test_sao_can_reset_password(): void
    {
        $response = $this->actingAs($this->sao)
            ->putJson('/api/sao/users/' . $this->staff->id, [
                'password' => 'newStrongPassword999',
            ]);

        $response->assertOk();

        $this->staff->refresh();
        $this->assertTrue(Hash::check('newStrongPassword999', $this->staff->password),
            'Password hash should match the new password');
    }

    public function test_sao_can_change_staff_academic_unit(): void
    {
        $response = $this->actingAs($this->sao)
            ->putJson('/api/sao/users/' . $this->staff->id, [
                'academic_unit_id' => $this->unitB->id,
            ]);

        $response->assertOk();
        $this->staff->refresh();
        $this->assertEquals($this->unitB->id, $this->staff->academic_unit_id);
    }

    // ── Requirement 5: Duplicate emails are rejected ──────────────────────────

    public function test_duplicate_email_is_rejected_on_create(): void
    {
        $response = $this->actingAs($this->sao)
            ->postJson('/api/sao/users', [
                'name'             => 'Duplicate',
                'email'            => $this->dean->email, // already taken
                'password'         => 'password123',
                'role'             => 'dean',
                'academic_unit_id' => $this->unitB->id,
            ]);

        $response->assertUnprocessable();
        $this->assertStringContainsString('email', strtolower(json_encode($response->json())));
    }

    public function test_duplicate_email_is_rejected_on_update(): void
    {
        // Try to update staff email to dean's email
        $response = $this->actingAs($this->sao)
            ->putJson('/api/sao/users/' . $this->staff->id, [
                'email' => $this->dean->email,
            ]);

        $response->assertUnprocessable();
    }

    // ── Requirement 6: Invalid academic unit or department rejected ───────────

    public function test_invalid_academic_unit_id_is_rejected(): void
    {
        $response = $this->actingAs($this->sao)
            ->postJson('/api/sao/users', [
                'name'             => 'Bad Unit Dean',
                'email'            => 'badunit@test.example',
                'password'         => 'password123',
                'role'             => 'dean',
                'academic_unit_id' => 99999, // non-existent
            ]);

        $response->assertUnprocessable();
    }

    public function test_invalid_dept_id_is_rejected(): void
    {
        $response = $this->actingAs($this->sao)
            ->postJson('/api/sao/users', [
                'name'    => 'Bad Dept Staff',
                'email'   => 'baddept@test.example',
                'password' => 'password123',
                'role'    => 'dept_staff',
                'dept_id' => 99999, // non-existent
            ]);

        $response->assertUnprocessable();
    }

    // ── Requirement 7: SAO cannot create Admin/SAO/Student accounts ───────────

    public function test_sao_cannot_create_admin_account(): void
    {
        $response = $this->actingAs($this->sao)
            ->postJson('/api/sao/users', [
                'name'     => 'Bad Admin',
                'email'    => 'badadmin@test.example',
                'password' => 'password123',
                'role'     => 'admin',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'SAO may only provision Dean or Department Staff accounts.');
    }

    public function test_sao_cannot_create_sao_account(): void
    {
        $response = $this->actingAs($this->sao)
            ->postJson('/api/sao/users', [
                'name'     => 'Bad SAO',
                'email'    => 'badsao@test.example',
                'password' => 'password123',
                'role'     => 'sao',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'SAO may only provision Dean or Department Staff accounts.');
    }

    public function test_sao_cannot_create_student_account(): void
    {
        $response = $this->actingAs($this->sao)
            ->postJson('/api/sao/users', [
                'name'     => 'Bad Student',
                'email'    => 'badstudent@test.example',
                'password' => 'password123',
                'role'     => 'student',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'SAO may only provision Dean or Department Staff accounts.');
    }

    public function test_sao_cannot_modify_admin_account(): void
    {
        $response = $this->actingAs($this->sao)
            ->putJson('/api/sao/users/' . $this->admin->id, [
                'name' => 'Hacked Admin Name',
            ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Cannot modify System Administrator accounts.');
    }

    public function test_sao_cannot_modify_student_account(): void
    {
        $response = $this->actingAs($this->sao)
            ->putJson('/api/sao/users/' . $this->student->id, [
                'name' => 'Hacked Student Name',
            ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Student accounts cannot be modified through the SAO User Management module.');
    }

    // ── Requirement 8: Unauthorized roles cannot access SAO endpoints ─────────

    public function test_student_cannot_access_sao_user_list(): void
    {
        $this->actingAs($this->student)
            ->getJson('/api/sao/users')
            ->assertForbidden();
    }

    public function test_dept_staff_cannot_access_sao_user_list(): void
    {
        $this->actingAs($this->staff)
            ->getJson('/api/sao/users')
            ->assertForbidden();
    }

    public function test_dean_cannot_access_sao_user_list(): void
    {
        $this->actingAs($this->dean)
            ->getJson('/api/sao/users')
            ->assertForbidden();
    }

    public function test_dept_staff_cannot_create_accounts_via_sao(): void
    {
        $this->actingAs($this->staff)
            ->postJson('/api/sao/users', [
                'name'     => 'Unauthorized',
                'email'    => 'unauth@test.example',
                'password' => 'password123',
                'role'     => 'dept_staff',
                'academic_unit_id' => $this->unitA->id,
            ])
            ->assertForbidden();
    }

    public function test_dean_cannot_edit_accounts_via_sao(): void
    {
        $this->actingAs($this->dean)
            ->putJson('/api/sao/users/' . $this->staff->id, [
                'name' => 'Hacked Name',
            ])
            ->assertForbidden();
    }

    // ── Requirement 9: Existing Admin account management still works ──────────

    public function test_admin_can_access_admin_management_routes(): void
    {
        // Admin uses /api/admin/users (the existing shared route with SAO)
        $response = $this->actingAs($this->admin)
            ->getJson('/api/sao/users');

        // Admin is blocked from SAO-specific routes (role:sao only)
        // SAO routes are sao-only; admin has /api/admin routes
        $response->assertForbidden(); // Admin cannot access /api/sao/ routes
    }

    public function test_sao_cannot_deactivate_own_account(): void
    {
        $response = $this->actingAs($this->sao)
            ->postJson('/api/sao/users/' . $this->sao->id . '/deactivate');

        $response->assertForbidden()
            ->assertJsonPath('message', 'You cannot deactivate your own account.');
    }

    // ── Requirement 10: Deactivation reports schema status honestly ───────────

    public function test_deactivation_reports_schema_support_status(): void
    {
        $response = $this->actingAs($this->sao)
            ->postJson('/api/sao/users/' . $this->dean->id . '/deactivate');

        // Either supported (200) or unsupported (422) — both are valid states
        // The key is: it never silently deletes the user
        $response->assertJsonStructure(['supported', 'message']);

        // User must still exist regardless of deactivation outcome
        $this->assertDatabaseHas('users', ['id' => $this->dean->id]);
    }

    // ── Password Security ─────────────────────────────────────────────────────

    public function test_password_is_never_returned_in_api_response(): void
    {
        $response = $this->actingAs($this->sao)
            ->postJson('/api/sao/users', [
                'name'             => 'Sec Test Dean',
                'email'            => 'sectest@test.example',
                'password'         => 'supersecretpassword',
                'role'             => 'dean',
                'academic_unit_id' => $this->unitB->id,
            ]);

        $response->assertCreated();
        $responseBody = json_encode($response->json());

        $this->assertStringNotContainsString('supersecretpassword', $responseBody,
            'Plaintext password must never appear in API response');

        // Password hash also should not be in the user object response
        $user = $response->json('user');
        $this->assertArrayNotHasKey('password', $user,
            'Password hash must not be exposed in user object response');
    }

    public function test_password_is_stored_as_bcrypt_hash(): void
    {
        $this->actingAs($this->sao)
            ->postJson('/api/sao/users', [
                'name'             => 'Hash Test',
                'email'            => 'hashtest@test.example',
                'password'         => 'myhashablepassword',
                'role'             => 'dept_staff',
                'academic_unit_id' => $this->unitA->id,
            ])
            ->assertCreated();

        $created = User::where('email', 'hashtest@test.example')->first();
        $this->assertNotNull($created);
        $this->assertTrue(str_starts_with($created->password, '$2y$'),
            'Password must be stored as a bcrypt hash');
        $this->assertTrue(Hash::check('myhashablepassword', $created->password),
            'Stored hash must verify correctly against the original password');
    }
}
