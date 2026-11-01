<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Concern;
use App\Models\Department;
use App\Models\User;
use App\Services\GeminiSentimentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class IntelligentConcernRoutingTest extends TestCase
{
    use RefreshDatabase;

    private AcademicUnit $nursingUnit;
    private AcademicUnit $ccsUnit;
    private Department $cashierDept;
    private Department $registrarDept;
    private Department $edpDept;
    private User $nursingStudent;
    private User $unassignedStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nursingUnit = AcademicUnit::create(['name' => 'College of Nursing']);
        $this->ccsUnit = AcademicUnit::create(['name' => 'College of Computer Studies']);

        $this->cashierDept = Department::create(['name' => 'Cashier']);
        $this->registrarDept = Department::create(['name' => 'Registrar']);
        $this->edpDept = Department::create(['name' => 'EDP ( Electronics Data Processing )']);

        $this->nursingStudent = User::create([
            'name' => 'Nursing Student',
            'email' => 'nursing_student_' . time() . '@uclm.edu.ph',
            'student_id_number' => '1111' . rand(1000, 9999),
            'password' => bcrypt('password123'),
            'role' => 'student',
            'academic_unit_id' => $this->nursingUnit->id,
        ]);

        $this->unassignedStudent = User::create([
            'name' => 'Unassigned Student',
            'email' => 'unassigned_student_' . time() . '@uclm.edu.ph',
            'student_id_number' => '2222' . rand(1000, 9999),
            'password' => bcrypt('password123'),
            'role' => 'student',
            'academic_unit_id' => null,
        ]);
    }

    /**
     * Requirement 1: Academic concern uses the student's registered academic unit.
     * Requirement 2: Academic concern never defaults to Computer Studies.
     */
    public function test_academic_concern_uses_student_registered_academic_unit_and_never_defaults_to_computer_studies(): void
    {
        $payload = [
            'title' => 'Clinical Duty Rotation Inquiry',
            'description' => 'Inquiry regarding clinical duty schedule at partner hospital for NURS 301.',
            'workflow_type' => 'academic',
        ];

        $response = $this->actingAs($this->nursingStudent)->postJson('/api/concerns', $payload);

        $response->assertStatus(201);
        $concernData = $response->json('concern');

        // Must strictly use the Nursing academic unit, NOT Computer Studies
        $this->assertEquals($this->nursingUnit->id, $concernData['academic_unit_id']);
        $this->assertNotEquals($this->ccsUnit->id, $concernData['academic_unit_id']);
        $this->assertEquals('academic', $concernData['workflow_type']);
        $this->assertNull($concernData['dept_id']);

        // Check routing note reflects student's academic unit
        $this->assertDatabaseHas('concern_routing', [
            'concern_id' => $concernData['concern_id'],
            'action' => 'submitted_academic',
        ]);

        $dbConcern = Concern::with('routingHistory')->find($concernData['concern_id']);
        $this->assertStringContainsString('College of Nursing', $dbConcern->routingHistory->first()->notes);
        $this->assertStringNotContainsString('Computer Studies', $dbConcern->routingHistory->first()->notes);
    }

    /**
     * Requirement 3: Academic submission fails when the student has no registered academic unit.
     */
    public function test_academic_submission_fails_when_student_has_no_registered_academic_unit(): void
    {
        $payload = [
            'title' => 'General Curriculum Question',
            'description' => 'I would like to inquire about my curriculum evaluation.',
            'workflow_type' => 'academic',
        ];

        $response = $this->actingAs($this->unassignedStudent)->postJson('/api/concerns', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['academic_unit']);
        $this->assertStringContainsString('do not have a registered academic unit', $response->json('errors.academic_unit.0'));

        // Verify zero concerns were created
        $this->assertEquals(0, Concern::where('student_id', $this->unassignedStudent->id)->count());
    }

    /**
     * Requirement 4: Academic concern cannot be redirected by manipulating the request.
     */
    public function test_academic_concern_cannot_be_redirected_by_manipulating_request(): void
    {
        // Nursing student attempts to send academic_unit_id pointing to CCS (or non-existent 9999)
        $forgedPayload = [
            'title' => 'Manipulated Unit Header Test',
            'description' => 'Attempting to inject a different academic unit id.',
            'workflow_type' => 'academic',
            'academic_unit_id' => $this->ccsUnit->id, // Forged!
        ];

        $response = $this->actingAs($this->nursingStudent)->postJson('/api/concerns', $forgedPayload);

        $response->assertStatus(201);
        $concernData = $response->json('concern');

        // Must still be assigned strictly to student's registered Nursing unit!
        $this->assertEquals($this->nursingUnit->id, $concernData['academic_unit_id']);
        $this->assertNotEquals($this->ccsUnit->id, $concernData['academic_unit_id']);
    }

    /**
     * Requirement 5: Non-academic concern receives a valid AI department recommendation via API.
     * Requirement 6: AI recommendations only reference existing operational departments.
     */
    public function test_recommend_routing_api_returns_valid_existing_operational_department(): void
    {
        $payload = [
            'title' => 'Tuition Fee Payment Receipt Verification',
            'description' => 'I made a bank transfer payment for my tuition installment and need the official receipt validated at the cashier counter.',
        ];

        $response = $this->actingAs($this->nursingStudent)->postJson('/api/concerns/recommend-routing', $payload);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'recommended_department_id',
            'recommended_department_name',
            'confidence',
            'explanation',
            'requires_manual_selection',
            'source',
        ]);

        $data = $response->json();
        $this->assertEquals($this->cashierDept->id, $data['recommended_department_id']);
        $this->assertEquals($this->cashierDept->name, $data['recommended_department_name']);
        $this->assertGreaterThanOrEqual(0.60, $data['confidence']);
        $this->assertFalse($data['requires_manual_selection']);

        // Verify the recommended department exists in the database
        $this->assertDatabaseHas('departments', [
            'id' => $data['recommended_department_id'],
            'name' => $data['recommended_department_name'],
        ]);
    }

    /**
     * Requirement 7: Student can change the recommended department.
     * Requirement 8: Student must confirm the final department.
     */
    public function test_student_can_change_and_must_confirm_final_department(): void
    {
        // 1. Get recommendation for a payment topic (recommends Cashier)
        $recPayload = [
            'title' => 'Tuition Payment Question',
            'description' => 'Tuition payment issue, but student wants to submit to Registrar instead.',
        ];
        $recResponse = $this->actingAs($this->nursingStudent)->postJson('/api/concerns/recommend-routing', $recPayload);
        $recResponse->assertStatus(200);

        // 2. Student decides to submit to Registrar instead of Cashier
        $finalPayload = [
            'title' => 'Tuition Payment Question',
            'description' => 'Tuition payment issue, but student wants to submit to Registrar instead.',
            'workflow_type' => 'non_academic',
            'dept_id' => $this->registrarDept->id, // Changed by student
        ];

        $response = $this->actingAs($this->nursingStudent)->postJson('/api/concerns', $finalPayload);
        $response->assertStatus(201);

        $concernData = $response->json('concern');
        $this->assertEquals($this->registrarDept->id, $concernData['dept_id']);
        $this->assertEquals('non_academic', $concernData['workflow_type']);

        // Confirm DB record has Registrar, not Cashier
        $this->assertDatabaseHas('concerns', [
            'concern_id' => $concernData['concern_id'],
            'dept_id' => $this->registrarDept->id,
        ]);
    }

    /**
     * Requirement 9: Invalid department IDs are rejected.
     */
    public function test_invalid_department_ids_are_rejected(): void
    {
        $invalidPayload = [
            'title' => 'Payment Question',
            'description' => 'Some description about non-academic problem.',
            'workflow_type' => 'non_academic',
            'dept_id' => 99999, // Non-existent!
        ];

        $response = $this->actingAs($this->nursingStudent)->postJson('/api/concerns', $invalidPayload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['dept_id']);
    }

    /**
     * Requirement 10: Gemini failure triggers the fallback mechanism.
     */
    public function test_gemini_failure_triggers_fallback_mechanism(): void
    {
        // Mock Gemini service to throw or return null
        $geminiMock = Mockery::mock(GeminiSentimentService::class);
        $geminiMock->shouldReceive('recommendOperationalDepartment')
            ->once()
            ->andReturn(null);

        // Allow other Gemini calls (sentiment analysis during concern save) to pass or return null
        $geminiMock->shouldReceive('analyzeConcern')->andReturn(null);

        $this->app->instance(GeminiSentimentService::class, $geminiMock);

        $payload = [
            'title' => 'Portal Wi-Fi Network Problem',
            'description' => 'Cannot connect to student portal over campus wifi internet connection.',
        ];

        $response = $this->actingAs($this->nursingStudent)->postJson('/api/concerns/recommend-routing', $payload);

        $response->assertStatus(200);
        $data = $response->json();

        // Fallback mechanism must still identify EDP
        $this->assertEquals('fallback', $data['source']);
        $this->assertEquals($this->edpDept->id, $data['recommended_department_id']);
        $this->assertEquals($this->edpDept->name, $data['recommended_department_name']);
        $this->assertNotEmpty($data['explanation']);
    }

    /**
     * Requirement 11: Low-confidence recommendations allow manual selection.
     */
    public function test_low_confidence_or_ambiguous_text_requires_manual_selection(): void
    {
        $ambiguousPayload = [
            'title' => 'Hello Question',
            'description' => 'I would like to inquire about something general that has no specific keywords.',
        ];

        $response = $this->actingAs($this->nursingStudent)->postJson('/api/concerns/recommend-routing', $ambiguousPayload);

        $response->assertStatus(200);
        $data = $response->json();

        // Must flag that manual selection is required
        $this->assertTrue($data['requires_manual_selection']);
        $this->assertLessThan(0.60, $data['confidence']);
    }

    /**
     * Requirement 12: Existing authorization and concern submission functionality still works.
     */
    public function test_non_students_cannot_submit_concerns(): void
    {
        $dean = User::create([
            'name' => 'Dean User',
            'email' => 'dean_test_' . time() . '@uclm.edu.ph',
            'password' => bcrypt('password123'),
            'role' => 'dean',
            'academic_unit_id' => $this->nursingUnit->id,
        ]);

        $payload = [
            'title' => 'Dean Unauthorized Submit Test',
            'description' => 'Dean attempting to call student submission endpoint.',
            'workflow_type' => 'academic',
        ];

        // Non-student (dean) cannot access student concern submission
        $response = $this->actingAs($dean)->postJson('/api/concerns', $payload);
        $response->assertStatus(403);
    }

    public function test_unauthenticated_guests_cannot_access_concern_submission(): void
    {
        $payload = [
            'title' => 'Guest Submit Test',
            'description' => 'Unauthenticated guest attempting to call student submission endpoint.',
            'workflow_type' => 'academic',
        ];

        // Guest cannot access student concern submission
        $guestResponse = $this->postJson('/api/concerns', $payload);
        $guestResponse->assertStatus(401);
    }
}

