<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Concern;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcernWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_and_non_academic_workflow_dispatch(): void
    {
        $ccsUnit = AcademicUnit::create(['name' => 'College of Computer Studies']);
        $cashierDept = Department::create(['name' => 'Cashier']);

        $student = User::create([
            'name' => 'Automated Test Student',
            'email' => 'student_workflow_test_' . time() . '@uclm.edu.ph',
            'student_id_number' => '8888' . rand(1000, 9999),
            'password' => bcrypt('secret1234'),
            'role' => 'student',
            'academic_unit_id' => $ccsUnit->id,
        ]);

        // 1. Submit Academic Concern
        $academicPayload = [
            'title' => 'Academic Evaluation Review',
            'description' => 'Need clarification on pre-requisite evaluation for Capstone 1.',
            'workflow_type' => 'academic',
        ];

        $responseAcad = $this->actingAs($student)->postJson('/api/concerns', $academicPayload);
        $responseAcad->assertStatus(201);
        $acadData = $responseAcad->json('concern');
        $this->assertEquals('academic', $acadData['workflow_type']);
        $this->assertEquals($ccsUnit->id, $acadData['academic_unit_id']);
        $this->assertNull($acadData['dept_id']);

        // 2. Submit Non-Academic Concern
        $nonAcademicPayload = [
            'title' => 'Tuition Payment Receipt Validation',
            'description' => 'Proof of payment submitted via online bank transfer needs verification.',
            'workflow_type' => 'non_academic',
            'dept_id' => $cashierDept->id,
        ];

        $responseNonAcad = $this->actingAs($student)->postJson('/api/concerns', $nonAcademicPayload);
        $responseNonAcad->assertStatus(201);
        $nonAcadData = $responseNonAcad->json('concern');
        $this->assertEquals('non_academic', $nonAcadData['workflow_type']);
        $this->assertEquals($cashierDept->id, $nonAcadData['dept_id']);

        // 3. Verify Details Endpoint returns routing history and proper workflow_type
        $detailsResp = $this->actingAs($student)->getJson('/api/concerns/' . $acadData['concern_id']);
        $detailsResp->assertStatus(200);
        $this->assertNotEmpty($detailsResp->json('concern.routing_history'));
    }
}
