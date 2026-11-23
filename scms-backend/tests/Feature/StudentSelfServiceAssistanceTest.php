<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Concern;
use App\Models\Department;
use App\Models\SelfServiceFeedback;
use App\Models\SelfServiceKbEntry;
use App\Models\SelfServiceKbRevision;
use App\Models\SelfServiceTask;
use App\Models\User;
use Database\Seeders\SelfServiceKnowledgeBaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentSelfServiceAssistanceTest extends TestCase
{
    use RefreshDatabase;

    private AcademicUnit $academicUnit;
    private Department $saoDepartment;
    private User $studentA;
    private User $studentB;
    private User $saoUser;
    private User $deptStaffUser;
    private User $deanUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->academicUnit = AcademicUnit::create(['name' => 'Computer Studies']);
        $this->saoDepartment = Department::create(['name' => 'SAO']);

        $this->studentA = User::factory()->create([
            'name' => 'Student Alpha',
            'email' => 'student.alpha@uclm.edu.ph',
            'role' => 'student',
            'academic_unit_id' => $this->academicUnit->id,
        ]);

        $this->studentB = User::factory()->create([
            'name' => 'Student Beta',
            'email' => 'student.beta@uclm.edu.ph',
            'role' => 'student',
            'academic_unit_id' => $this->academicUnit->id,
        ]);

        $this->saoUser = User::factory()->create([
            'name' => 'SAO Director',
            'email' => 'sao.director@uclm.edu.ph',
            'role' => 'sao',
            'dept_id' => $this->saoDepartment->id,
        ]);

        $this->deptStaffUser = User::factory()->create([
            'name' => 'Registrar Staff',
            'email' => 'registrar.staff@uclm.edu.ph',
            'role' => 'dept_staff',
            'dept_id' => $this->saoDepartment->id,
        ]);

        $this->deanUser = User::factory()->create([
            'name' => 'CCS Dean',
            'email' => 'ccs.dean@uclm.edu.ph',
            'role' => 'dean',
            'academic_unit_id' => $this->academicUnit->id,
        ]);

        $this->seed(SelfServiceKnowledgeBaseSeeder::class);
    }

    /**
     * Helper to formally approve and publish a specific KB entry via SAO.
     */
    private function approveAndPublishEntry(string $entryCode): SelfServiceKbEntry
    {
        $entry = SelfServiceKbEntry::where('entry_code', $entryCode)->firstOrFail();

        $this->actingAs($this->saoUser, 'sanctum')
            ->putJson("/api/sao/self-service/entries/{$entry->id}", [
                'approval_status' => 'Approved',
                'validated_by' => 'Atty. Maria Santos',
                'validated_role' => 'UCLM SAO Director',
                'validated_office' => 'Student Affairs Office',
                'supporting_reference' => 'Signed UCLM Review Packet 01',
            ])
            ->assertOk();

        $this->actingAs($this->saoUser, 'sanctum')
            ->postJson("/api/sao/self-service/entries/{$entry->id}/publish")
            ->assertOk();

        return $entry->fresh();
    }

    public function test_all_seeded_entries_start_pending_and_unpublished(): void
    {
        $totalCount = SelfServiceKbEntry::count();
        $this->assertEquals(46, $totalCount);
        $this->assertEquals(39, SelfServiceKbEntry::where('is_sensitive', false)->count());
        $this->assertEquals(7, SelfServiceKbEntry::where('is_sensitive', true)->count());
        $this->assertEquals($totalCount, SelfServiceKbEntry::where('approval_status', 'Pending UCLM Validation')->count());
        $this->assertEquals($totalCount, SelfServiceKbEntry::where('publication_status', 'Unpublished')->count());
        $this->assertEquals(0, SelfServiceKbEntry::publishedForStudents()->count());
    }

    public function test_students_can_access_need_help_and_browse_12_categories(): void
    {
        $response = $this->actingAs($this->studentA, 'sanctum')
            ->getJson('/api/self-service/categories');

        $response->assertOk()
            ->assertJsonCount(12, 'categories');

        $categoryNames = collect($response->json('categories'))->pluck('name')->all();
        $this->assertEquals([
            'Academic',
            'Enrollment',
            'Financial',
            'Student ID',
            'Library',
            'Facilities',
            'Technology',
            'Student Services',
            'Medical',
            'Guidance',
            'Safety and Security',
            'Other Concerns',
        ], $categoryNames);
    }

    public function test_unpublished_entries_cannot_be_retrieved_by_students(): void
    {
        // With all 41 entries unpublished initially, student search returns 0 entries + unavailable_notice
        $listRes = $this->actingAs($this->studentA, 'sanctum')
            ->getJson('/api/self-service/entries?q=lost+school+id');

        $listRes->assertOk()
            ->assertJsonCount(0, 'entries')
            ->assertJsonPath('unavailable_notice.status', 'verified_guidance_unavailable');

        // Direct lookup of unpublished entry A1 must return 404
        $entryA1 = SelfServiceKbEntry::where('entry_code', 'A1')->firstOrFail();

        $this->actingAs($this->studentA, 'sanctum')
            ->getJson("/api/self-service/entries/{$entryA1->id}")
            ->assertNotFound();

        $this->actingAs($this->studentA, 'sanctum')
            ->getJson('/api/self-service/entries/A1')
            ->assertNotFound();

        // Attempting to start a guided task on unpublished entry A1 must return 403
        $this->actingAs($this->studentA, 'sanctum')
            ->postJson('/api/self-service/tasks', ['kb_entry_id' => $entryA1->id])
            ->assertForbidden();
    }

    public function test_only_published_knowledge_base_entries_are_returned_to_students(): void
    {
        $publishedA1 = $this->approveAndPublishEntry('A1');

        $listRes = $this->actingAs($this->studentA, 'sanctum')
            ->getJson('/api/self-service/entries');

        $listRes->assertOk()
            ->assertJsonCount(1, 'entries')
            ->assertJsonPath('entries.0.entry_code', 'A1')
            ->assertJsonPath('entries.0.id', $publishedA1->id);

        // Unpublished entry B1 still returns 404
        $this->actingAs($this->studentA, 'sanctum')
            ->getJson('/api/self-service/entries/B1')
            ->assertNotFound();

        // Published entry A1 returns 200 and excludes internal approval/reviewer metadata from students
        $showRes = $this->actingAs($this->studentA, 'sanctum')
            ->getJson('/api/self-service/entries/A1')
            ->assertOk()
            ->assertJsonPath('entry.entry_code', 'A1');

        $studentEntry = $showRes->json('entry');
        $this->assertArrayNotHasKey('validated_by', $studentEntry);
        $this->assertArrayNotHasKey('validated_role', $studentEntry);
        $this->assertArrayNotHasKey('validated_office', $studentEntry);
        $this->assertArrayNotHasKey('supporting_reference', $studentEntry);
        $this->assertArrayNotHasKey('approval_recorded_by', $studentEntry);
        $this->assertArrayNotHasKey('approval_status', $studentEntry);
        $this->assertArrayNotHasKey('publication_status', $studentEntry);
    }

    public function test_students_can_create_save_complete_steps_and_resume_their_own_tasks(): void
    {
        $publishedA1 = $this->approveAndPublishEntry('A1'); // A1 has 7 steps

        // 1. Start task
        $startRes = $this->actingAs($this->studentA, 'sanctum')
            ->postJson('/api/self-service/tasks', [
                'kb_entry_id' => $publishedA1->id,
            ]);

        $startRes->assertCreated()
            ->assertJsonPath('task.entry_code', 'A1')
            ->assertJsonPath('task.total_steps', 7)
            ->assertJsonPath('task.completed_steps_count', 0)
            ->assertJsonPath('task.status', 'in_progress');

        $taskId = $startRes->json('task.id');

        // 2. Complete individual steps (steps 1 and 2 of 7) -> must NOT mark the entire task completed
        $stepUpdateRes = $this->actingAs($this->studentA, 'sanctum')
            ->putJson("/api/self-service/tasks/{$taskId}", [
                'completed_step_ids' => [1, 2],
            ]);

        $stepUpdateRes->assertOk()
            ->assertJsonPath('task.completed_steps_count', 2)
            ->assertJsonPath('task.progress_percentage', 29)
            ->assertJsonPath('task.status', 'in_progress');

        // 3. Attempting to force status='completed' when only 2 of 7 steps are checked must be rejected (422)
        $this->actingAs($this->studentA, 'sanctum')
            ->putJson("/api/self-service/tasks/{$taskId}", [
                'completed_step_ids' => [1, 2],
                'status' => 'completed',
            ])
            ->assertStatus(422);

        // 4. Pause task to resume later
        $pauseRes = $this->actingAs($this->studentA, 'sanctum')
            ->putJson("/api/self-service/tasks/{$taskId}", [
                'completed_step_ids' => [1, 2, 3],
                'status' => 'paused',
            ]);

        $pauseRes->assertOk()
            ->assertJsonPath('task.status', 'paused')
            ->assertJsonPath('task.completed_steps_count', 3);

        // 5. Resume task via startTask or myTasks
        $resumeRes = $this->actingAs($this->studentA, 'sanctum')
            ->postJson('/api/self-service/tasks', [
                'kb_entry_id' => $publishedA1->id,
            ]);

        $resumeRes->assertOk()
            ->assertJsonPath('resumed', true)
            ->assertJsonPath('task.id', $taskId)
            ->assertJsonPath('task.completed_steps_count', 3);

        // 6. Complete all 7 steps
        $finishRes = $this->actingAs($this->studentA, 'sanctum')
            ->putJson("/api/self-service/tasks/{$taskId}", [
                'completed_step_ids' => [1, 2, 3, 4, 5, 6, 7],
                'status' => 'completed',
            ]);

        $finishRes->assertOk()
            ->assertJsonPath('task.status', 'completed')
            ->assertJsonPath('task.progress_percentage', 100)
            ->assertJsonPath('task.completion_type', 'self_reported_student_checklist');

        // Confirm zero formal concerns were created
        $this->assertEquals(0, Concern::count());
    }

    public function test_students_cannot_access_or_modify_another_students_task(): void
    {
        $publishedA1 = $this->approveAndPublishEntry('A1');

        $startRes = $this->actingAs($this->studentA, 'sanctum')
            ->postJson('/api/self-service/tasks', [
                'kb_entry_id' => $publishedA1->id,
            ])
            ->assertCreated();

        $taskId = $startRes->json('task.id');

        // Student B cannot view Student A's task
        $this->actingAs($this->studentB, 'sanctum')
            ->getJson("/api/self-service/tasks/{$taskId}")
            ->assertForbidden();

        // Student B cannot update Student A's task
        $this->actingAs($this->studentB, 'sanctum')
            ->putJson("/api/self-service/tasks/{$taskId}", [
                'completed_step_ids' => [1],
            ])
            ->assertForbidden();

        // Student B cannot restart Student A's task
        $this->actingAs($this->studentB, 'sanctum')
            ->postJson("/api/self-service/tasks/{$taskId}/restart")
            ->assertForbidden();

        // Student B's myTasks list is empty
        $this->actingAs($this->studentB, 'sanctum')
            ->getJson('/api/self-service/tasks')
            ->assertOk()
            ->assertJsonCount(0, 'tasks');
    }

    public function test_updating_kb_entry_preserves_saved_task_snapshot_and_flags_outdated_guidance(): void
    {
        $publishedA1 = $this->approveAndPublishEntry('A1');

        $startRes = $this->actingAs($this->studentA, 'sanctum')
            ->postJson('/api/self-service/tasks', [
                'kb_entry_id' => $publishedA1->id,
            ])
            ->assertCreated();

        $taskId = $startRes->json('task.id');
        $originalTitle = $startRes->json('task.task_title');

        // SAO updates the published KB entry content -> version increments to 2, entry is unpublished and requires reapproval
        $this->actingAs($this->saoUser, 'sanctum')
            ->putJson("/api/sao/self-service/entries/{$publishedA1->id}", [
                'title' => 'Replacement of Lost School ID (Updated 2026 UCLM Procedure)',
                'approved_answer' => 'Updated 2026 answer verified by SAO.',
                'revision_summary' => 'Updated ID printing office per SAO review.',
            ])
            ->assertOk()
            ->assertJsonPath('reapproval_required', true)
            ->assertJsonPath('entry.version', 2)
            ->assertJsonPath('entry.approval_status', 'Pending UCLM Validation')
            ->assertJsonPath('entry.publication_status', 'Unpublished');

        // SAO formally re-approves and re-publishes v2 so students can restart with the newly published v2
        $this->approveAndPublishEntry('A1');

        // Student A's existing task preserves its v1 snapshot and notifies that v2 is available
        $taskRes = $this->actingAs($this->studentA, 'sanctum')
            ->getJson("/api/self-service/tasks/{$taskId}");

        $taskRes->assertOk()
            ->assertJsonPath('task.kb_version', 1)
            ->assertJsonPath('task.latest_kb_version', 2)
            ->assertJsonPath('task.is_outdated_guidance', true)
            ->assertJsonPath('task.can_restart_with_latest', true)
            ->assertJsonPath('task.task_title', $originalTitle);

        // Student A restarts with latest version -> v1 historical record is preserved as 'restarted', new v2 task created
        $restartRes = $this->actingAs($this->studentA, 'sanctum')
            ->postJson("/api/self-service/tasks/{$taskId}/restart");

        $restartRes->assertCreated()
            ->assertJsonPath('task.kb_version', 2)
            ->assertJsonPath('task.is_outdated_guidance', false)
            ->assertJsonPath('previous_task_id', $taskId);

        $this->assertEquals('restarted', SelfServiceTask::findOrFail($taskId)->status);
    }

    public function test_helpfulness_feedback_is_recorded_separately_and_does_not_create_or_resolve_formal_concerns(): void
    {
        $publishedA1 = $this->approveAndPublishEntry('A1');

        // Create an existing pending formal concern for Student A
        $formalConcern = Concern::create([
            'student_id' => $this->studentA->id,
            'academic_unit_id' => $this->academicUnit->id,
            'dept_id' => $this->saoDepartment->id,
            'target_type' => 'department',
            'title' => 'Formal Concern About Lost ID',
            'description' => 'Need formal assistance from SAO regarding my lost ID.',
            'category' => 'operational_departments',
            'workflow_type' => 'non_academic',
            'status' => 'pending',
            'submitted_at' => now(),
            'last_updated' => now(),
        ]);

        // Submit helpful feedback on self-service entry A1
        $feedbackRes = $this->actingAs($this->studentA, 'sanctum')
            ->postJson('/api/self-service/feedback', [
                'kb_entry_id' => $publishedA1->id,
                'is_helpful' => true,
                'comment' => 'Clear step-by-step instructions.',
            ]);

        $feedbackRes->assertCreated()
            ->assertJsonPath('feedback.is_helpful', true);

        // Start and complete self-service task for A1
        $taskRes = $this->actingAs($this->studentA, 'sanctum')
            ->postJson('/api/self-service/tasks', ['kb_entry_id' => $publishedA1->id])
            ->assertCreated();

        $this->actingAs($this->studentA, 'sanctum')
            ->putJson("/api/self-service/tasks/{$taskRes->json('task.id')}", [
                'completed_step_ids' => [1, 2, 3, 4, 5, 6, 7],
                'status' => 'completed',
            ])
            ->assertOk();

        // Confirm no additional formal concern was created and the existing formal concern remains 'pending'
        $this->assertEquals(1, Concern::count());
        $this->assertEquals('pending', $formalConcern->fresh()->status);
        $this->assertEquals(1, SelfServiceFeedback::count());
    }

    public function test_sensitive_concerns_trigger_guardrail_and_bypass_ordinary_self_service_tasks(): void
    {
        // 1. Querying sexual harassment, bullying/violence, hazing, or medical emergency triggers sensitive_guardrail
        $harassmentRes = $this->actingAs($this->studentA, 'sanctum')
            ->getJson('/api/self-service/entries?q=sexual+harassment+by+instructor');

        $harassmentRes->assertOk()
            ->assertJsonPath('sensitive_guardrail.triggered', true)
            ->assertJsonPath('sensitive_guardrail.bypass_self_service_tasks', true);

        $medicalEmergencyRes = $this->actingAs($this->studentA, 'sanctum')
            ->getJson('/api/self-service/entries?q=student+fainted+medical+emergency');

        $medicalEmergencyRes->assertOk()
            ->assertJsonPath('sensitive_guardrail.triggered', true)
            ->assertJsonPath('sensitive_guardrail.is_emergency', true);

        // 2. Even if sensitive entry S1 is approved and published by SAO, it cannot be turned into a self-service task checklist
        $publishedS1 = $this->approveAndPublishEntry('S1');

        $this->actingAs($this->studentA, 'sanctum')
            ->postJson('/api/self-service/tasks', [
                'kb_entry_id' => $publishedS1->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('requires_formal_reporting', true);
    }

    public function test_unauthorized_roles_cannot_manage_or_publish_knowledge_base_entries(): void
    {
        $entryA1 = SelfServiceKbEntry::where('entry_code', 'A1')->firstOrFail();

        foreach ([$this->studentA, $this->deptStaffUser, $this->deanUser] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser, 'sanctum')
                ->getJson('/api/sao/self-service/entries')
                ->assertForbidden();

            $this->actingAs($unauthorizedUser, 'sanctum')
                ->putJson("/api/sao/self-service/entries/{$entryA1->id}", [
                    'approval_status' => 'Approved',
                    'validated_by' => 'Unauthorized User',
                    'validated_role' => 'Staff',
                ])
                ->assertForbidden();

            $this->actingAs($unauthorizedUser, 'sanctum')
                ->postJson("/api/sao/self-service/entries/{$entryA1->id}/publish")
                ->assertForbidden();
        }
    }

    public function test_sao_publication_controls_enforce_separate_approval_and_publication_states(): void
    {
        $entryB1 = SelfServiceKbEntry::where('entry_code', 'B1')->firstOrFail();

        // 1. SAO cannot publish an entry that is still 'Pending UCLM Validation'
        $this->actingAs($this->saoUser, 'sanctum')
            ->postJson("/api/sao/self-service/entries/{$entryB1->id}/publish")
            ->assertStatus(422);

        // 2. SAO cannot mark an entry as 'Approved' without validated_by and validated_role
        $this->actingAs($this->saoUser, 'sanctum')
            ->putJson("/api/sao/self-service/entries/{$entryB1->id}", [
                'approval_status' => 'Approved',
                'validated_by' => '',
                'validated_role' => '',
            ])
            ->assertStatus(422);

        // 3. Verbal confirmation without formal approval is preserved but cannot be published
        $this->actingAs($this->saoUser, 'sanctum')
            ->putJson("/api/sao/self-service/entries/{$entryB1->id}", [
                'approval_status' => 'Verbal Confirmation Pending Formal Approval',
            ])
            ->assertOk();

        $this->actingAs($this->saoUser, 'sanctum')
            ->postJson("/api/sao/self-service/entries/{$entryB1->id}/publish")
            ->assertStatus(422);

        // 4. Formal approval with validator name, role, office, and supporting reference keeps publication_status='Unpublished' until SAO publishes
        $this->actingAs($this->saoUser, 'sanctum')
            ->putJson("/api/sao/self-service/entries/{$entryB1->id}", [
                'approval_status' => 'Approved',
                'validated_by' => 'Ms. Elena Cruz',
                'validated_role' => 'Chief Librarian',
                'validated_office' => 'UCLM Library',
                'supporting_reference' => 'Signed UCLM Review Packet 04',
            ])
            ->assertOk()
            ->assertJsonPath('entry.approval_status', 'Approved')
            ->assertJsonPath('entry.publication_status', 'Unpublished')
            ->assertJsonPath('entry.approval_recorded_by', $this->saoUser->id);

        // Still not visible to students before publication
        $this->actingAs($this->studentA, 'sanctum')
            ->getJson('/api/self-service/entries/B1')
            ->assertNotFound();

        // 5. SAO explicitly publishes B1 -> now visible to students
        $this->actingAs($this->saoUser, 'sanctum')
            ->postJson("/api/sao/self-service/entries/{$entryB1->id}/publish")
            ->assertOk()
            ->assertJsonPath('entry.publication_status', 'Published');

        $this->actingAs($this->studentA, 'sanctum')
            ->getJson('/api/self-service/entries/B1')
            ->assertOk()
            ->assertJsonPath('entry.entry_code', 'B1');

        // 6. SAO marks B1 as Needs Revision -> automatically unpublishes from student retrieval
        $this->actingAs($this->saoUser, 'sanctum')
            ->postJson("/api/sao/self-service/entries/{$entryB1->id}/needs-revision", [
                'reason' => 'Library operating hours updated for new semester.',
            ])
            ->assertOk()
            ->assertJsonPath('entry.publication_status', 'Unpublished')
            ->assertJsonPath('entry.lifecycle_stage', 'Needs Revision');

        $this->actingAs($this->studentA, 'sanctum')
            ->getJson('/api/self-service/entries/B1')
            ->assertNotFound();
    }

    public function test_formal_concern_submission_workflow_remains_directly_accessible(): void
    {
        $response = $this->actingAs($this->studentA, 'sanctum')
            ->postJson('/api/concerns', [
                'title' => 'Broken Air Conditioning in Room 402',
                'description' => 'The aircon unit in Room 402 is leaking water and not cooling during our morning lecture.',
                'workflow_type' => 'academic',
            ]);

        $response->assertCreated()
            ->assertJsonPath('concern.title', 'Broken Air Conditioning in Room 402')
            ->assertJsonPath('concern.workflow_type', 'academic');
    }

    public function test_seeder_is_idempotent_and_preserves_approved_published_entries_and_revisions(): void
    {
        // 1. SAO approves and publishes A1, and creates a substantive revision + re-approval (version 2)
        $entryA1 = $this->approveAndPublishEntry('A1');

        $this->actingAs($this->saoUser, 'sanctum')
            ->putJson("/api/sao/self-service/entries/{$entryA1->id}", [
                'title' => 'Replacement of Lost School ID (Verified 2026 UCLM Workflow)',
                'approved_answer' => 'Official 2026 UCLM approved procedure for ID replacement.',
                'revision_summary' => 'Verified with SAO and MIS.',
            ])
            ->assertOk();

        $publishedV2 = $this->approveAndPublishEntry('A1');
        $this->assertEquals(2, $publishedV2->version);
        $this->assertEquals(1, SelfServiceKbRevision::where('kb_entry_id', $publishedV2->id)->count());

        // 2. Delete one unapproved entry (G4) to simulate a missing entry code, then rerun the seeder twice
        SelfServiceKbEntry::where('entry_code', 'G4')->delete();
        $this->assertEquals(45, SelfServiceKbEntry::count());

        $this->seed(SelfServiceKnowledgeBaseSeeder::class);
        $this->seed(SelfServiceKnowledgeBaseSeeder::class);

        // 3. Verify total count is restored to 46, G4 was inserted as Pending/Unpublished, and A1 was NOT overwritten
        $this->assertEquals(46, SelfServiceKbEntry::count());

        $restoredG4 = SelfServiceKbEntry::where('entry_code', 'G4')->firstOrFail();
        $this->assertEquals('Pending UCLM Validation', $restoredG4->approval_status);
        $this->assertEquals('Unpublished', $restoredG4->publication_status);

        $preservedA1 = SelfServiceKbEntry::where('entry_code', 'A1')->firstOrFail();
        $this->assertEquals('Replacement of Lost School ID (Verified 2026 UCLM Workflow)', $preservedA1->title);
        $this->assertEquals('Official 2026 UCLM approved procedure for ID replacement.', $preservedA1->approved_answer);
        $this->assertEquals('Approved', $preservedA1->approval_status);
        $this->assertEquals('Published', $preservedA1->publication_status);
        $this->assertEquals('Published', $preservedA1->lifecycle_stage);
        $this->assertEquals('Atty. Maria Santos', $preservedA1->validated_by);
        $this->assertEquals('UCLM SAO Director', $preservedA1->validated_role);
        $this->assertEquals('Student Affairs Office', $preservedA1->validated_office);
        $this->assertEquals('Signed UCLM Review Packet 01', $preservedA1->supporting_reference);
        $this->assertEquals($this->saoUser->id, $preservedA1->approval_recorded_by);
        $this->assertEquals(2, $preservedA1->version);
        $this->assertEquals(1, SelfServiceKbRevision::where('kb_entry_id', $preservedA1->id)->count());
    }

    public function test_approval_requires_all_four_validator_and_supporting_reference_fields_and_records_sao_auditor(): void
    {
        $entryC1 = SelfServiceKbEntry::where('entry_code', 'C1')->firstOrFail();

        $completePayload = [
            'approval_status' => 'Approved',
            'validated_by' => 'Dr. Roberto Lim',
            'validated_role' => 'University Registrar',
            'validated_office' => 'Office of the Registrar',
            'supporting_reference' => 'Signed UCLM Review Packet 02 (Registrar)',
        ];

        // Each of the 4 required approval evidence fields must reject blank values when setting Approved
        foreach (['validated_by', 'validated_role', 'validated_office', 'supporting_reference'] as $requiredField) {
            $invalidPayload = $completePayload;
            $invalidPayload[$requiredField] = '   ';

            $this->actingAs($this->saoUser, 'sanctum')
                ->putJson("/api/sao/self-service/entries/{$entryC1->id}", $invalidPayload)
                ->assertStatus(422)
                ->assertJsonValidationErrors([$requiredField]);
        }

        // Complete payload succeeds and records approval_recorded_by + validated_at
        $approveRes = $this->actingAs($this->saoUser, 'sanctum')
            ->putJson("/api/sao/self-service/entries/{$entryC1->id}", $completePayload);

        $approveRes->assertOk()
            ->assertJsonPath('entry.approval_status', 'Approved')
            ->assertJsonPath('entry.publication_status', 'Unpublished')
            ->assertJsonPath('entry.approval_recorded_by', $this->saoUser->id)
            ->assertJsonPath('entry.supporting_reference', 'Signed UCLM Review Packet 02 (Registrar)');

        $this->assertNotNull($entryC1->fresh()->validated_at);
    }

    public function test_substantive_edit_on_approved_or_published_entry_increments_version_snapshots_approval_unpublishes_and_requires_reapproval(): void
    {
        $publishedB1 = $this->approveAndPublishEntry('B1');
        $this->assertEquals(1, $publishedB1->version);
        $this->assertEquals('Approved', $publishedB1->approval_status);
        $this->assertEquals('Published', $publishedB1->publication_status);

        // 1. Non-substantive metadata edit (validation_notes) on an Approved + Published entry does NOT invalidate approval
        $nonSubstantiveRes = $this->actingAs($this->saoUser, 'sanctum')
            ->putJson("/api/sao/self-service/entries/{$publishedB1->id}", [
                'validation_notes' => 'Verified fine schedule with Chief Librarian.',
            ]);

        $nonSubstantiveRes->assertOk()
            ->assertJsonPath('reapproval_required', false)
            ->assertJsonPath('entry.version', 1)
            ->assertJsonPath('entry.approval_status', 'Approved')
            ->assertJsonPath('entry.publication_status', 'Published');

        // 2. Substantive edit (changing responsible_office or approved_answer) snapshots v1 approval, increments version to 2, unpublishes, and resets to Pending UCLM Validation
        $substantiveRes = $this->actingAs($this->saoUser, 'sanctum')
            ->putJson("/api/sao/self-service/entries/{$publishedB1->id}", [
                'approved_answer' => 'Updated library borrowing limit and renewal rule for 2026.',
                'revision_summary' => 'Updated borrowing period per Library packet.',
            ]);

        $substantiveRes->assertOk()
            ->assertJsonPath('reapproval_required', true)
            ->assertJsonPath('entry.version', 2)
            ->assertJsonPath('entry.approval_status', 'Pending UCLM Validation')
            ->assertJsonPath('entry.publication_status', 'Unpublished')
            ->assertJsonPath('entry.lifecycle_stage', 'Under Office Review');

        // Verify v1 snapshot in self_service_kb_revisions preserved the prior approval metadata
        $revision = SelfServiceKbRevision::where('kb_entry_id', $publishedB1->id)
            ->where('version', 1)
            ->firstOrFail();

        $this->assertEquals('Approved', $revision->approval_status);
        $this->assertEquals('Published', $revision->publication_status);
        $this->assertEquals('Atty. Maria Santos', $revision->validated_by);
        $this->assertEquals('UCLM SAO Director', $revision->validated_role);
        $this->assertEquals('Student Affairs Office', $revision->validated_office);
        $this->assertEquals('Signed UCLM Review Packet 01', $revision->supporting_reference);
        $this->assertEquals($this->saoUser->id, $revision->approval_recorded_by);

        // Students can no longer retrieve B1 while it awaits reapproval
        $this->actingAs($this->studentA, 'sanctum')
            ->getJson('/api/self-service/entries/B1')
            ->assertNotFound();

        // SAO cannot publish B1 v2 before re-approving it
        $this->actingAs($this->saoUser, 'sanctum')
            ->postJson("/api/sao/self-service/entries/{$publishedB1->id}/publish")
            ->assertStatus(422);
    }

    public function test_nested_step_and_requirements_validation_and_empty_checklist_publication_block(): void
    {
        $entryA1 = SelfServiceKbEntry::where('entry_code', 'A1')->firstOrFail();

        // 1. Duplicate step_number in steps array must fail with 422
        $this->actingAs($this->saoUser, 'sanctum')
            ->putJson("/api/sao/self-service/entries/{$entryA1->id}", [
                'steps' => [
                    ['step_number' => 1, 'title' => 'Step One', 'instructions' => 'Do step one.'],
                    ['step_number' => 1, 'title' => 'Duplicate Step One', 'instructions' => 'Duplicate number.'],
                ],
            ])
            ->assertStatus(422);

        // 2. Non-positive step_number or missing instructions must fail with 422
        $this->actingAs($this->saoUser, 'sanctum')
            ->putJson("/api/sao/self-service/entries/{$entryA1->id}", [
                'steps' => [
                    ['step_number' => 0, 'title' => 'Invalid StepZero', 'instructions' => ''],
                ],
            ])
            ->assertStatus(422);

        // 3. Non-string requirement item must fail with 422
        $this->actingAs($this->saoUser, 'sanctum')
            ->putJson("/api/sao/self-service/entries/{$entryA1->id}", [
                'requirements' => [['nested' => 'invalid']],
            ])
            ->assertStatus(422);

        // 4. Emptying steps on a standard entry and approving it must still block publication (422)
        $this->actingAs($this->saoUser, 'sanctum')
            ->putJson("/api/sao/self-service/entries/{$entryA1->id}", [
                'steps' => [],
                'approval_status' => 'Approved',
                'validated_by' => 'Atty. Maria Santos',
                'validated_role' => 'UCLM SAO Director',
                'validated_office' => 'Student Affairs Office',
                'supporting_reference' => 'Signed Packet 01',
            ])
            ->assertOk();

        $this->actingAs($this->saoUser, 'sanctum')
            ->postJson("/api/sao/self-service/entries/{$entryA1->id}/publish")
            ->assertStatus(422);
    }

    public function test_guided_task_rejects_invalid_blocked_step_number_removes_blocker_on_completion_and_blocks_empty_checklist_task_start(): void
    {
        $publishedA1 = $this->approveAndPublishEntry('A1'); // 7 steps (1..7)

        $startRes = $this->actingAs($this->studentA, 'sanctum')
            ->postJson('/api/self-service/tasks', [
                'kb_entry_id' => $publishedA1->id,
            ])
            ->assertCreated();

        $taskId = $startRes->json('task.id');

        // 1. Blocking a non-existent step number (e.g., 99) must return 422
        $this->actingAs($this->studentA, 'sanctum')
            ->putJson("/api/self-service/tasks/{$taskId}", [
                'blocked_step_number' => 99,
                'blocker_reason' => 'Invalid step number test.',
            ])
            ->assertStatus(422);

        // 2. Blocking valid step 3 succeeds and marks task 'blocked'
        $blockRes = $this->actingAs($this->studentA, 'sanctum')
            ->putJson("/api/self-service/tasks/{$taskId}", [
                'completed_step_ids' => [1, 2],
                'blocked_step_number' => 3,
                'blocker_reason' => 'Waiting for notarized Affidavit of Loss.',
            ]);

        $blockRes->assertOk()
            ->assertJsonPath('task.status', 'blocked')
            ->assertJsonCount(1, 'task.blocked_steps')
            ->assertJsonPath('task.blocked_steps.0.step_number', 3);

        // 3. Attempting to block an already completed step in the same request must return 422
        $this->actingAs($this->studentA, 'sanctum')
            ->putJson("/api/self-service/tasks/{$taskId}", [
                'completed_step_ids' => [1, 2],
                'blocked_step_number' => 2,
                'blocker_reason' => 'Cannot block completed step 2.',
            ])
            ->assertStatus(422);

        // 4. Completing step 3 automatically removes step 3 from blocked_steps and transitions task back to in_progress
        $unblockRes = $this->actingAs($this->studentA, 'sanctum')
            ->putJson("/api/self-service/tasks/{$taskId}", [
                'completed_step_ids' => [1, 2, 3],
            ]);

        $unblockRes->assertOk()
            ->assertJsonPath('task.status', 'in_progress')
            ->assertJsonCount(0, 'task.blocked_steps');

        // 5. If a standard published entry somehow has empty steps in the DB, starting a task returns 422
        $publishedB1 = $this->approveAndPublishEntry('B1');
        SelfServiceKbEntry::where('id', $publishedB1->id)->update(['steps' => json_encode([])]);

        $this->actingAs($this->studentA, 'sanctum')
            ->postJson('/api/self-service/tasks', [
                'kb_entry_id' => $publishedB1->id,
            ])
            ->assertStatus(422);
    }

    public function test_s4_disciplinary_complaint_triggers_sensitive_guardrail_and_blocks_task_creation(): void
    {
        // 1. Searching disciplinary complaint keywords triggers S4 pathway in sensitive_guardrail
        $disciplinaryRes = $this->actingAs($this->studentA, 'sanctum')
            ->getJson('/api/self-service/entries?q=filing+a+formal+disciplinary+complaint+for+theft');

        $disciplinaryRes->assertOk()
            ->assertJsonPath('sensitive_guardrail.triggered', true)
            ->assertJsonPath('sensitive_guardrail.bypass_self_service_tasks', true);

        $pathwayCodes = collect($disciplinaryRes->json('sensitive_guardrail.pathways'))
            ->pluck('entry_code')
            ->all();
        $this->assertContains('S4', $pathwayCodes);

        // 2. Selecting Safety and Security category also includes S4 among the formal reporting pathways
        $safetyRes = $this->actingAs($this->studentA, 'sanctum')
            ->getJson('/api/self-service/entries?category=Safety+and+Security');

        $safetyRes->assertOk()
            ->assertJsonPath('sensitive_guardrail.triggered', true);

        $safetyCodes = collect($safetyRes->json('sensitive_guardrail.pathways'))
            ->pluck('entry_code')
            ->all();
        $this->assertContains('S4', $safetyCodes);

        // 3. Even if S4 is approved and published, attempting to start a self-service task on S4 returns 422
        $publishedS4 = $this->approveAndPublishEntry('S4');

        $this->actingAs($this->studentA, 'sanctum')
            ->postJson('/api/self-service/tasks', [
                'kb_entry_id' => $publishedS4->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('requires_formal_reporting', true);
    }

    public function test_sao_can_save_ordinary_edits_and_draft_metadata_while_pending_without_approving_or_publishing(): void
    {
        $entryA3 = SelfServiceKbEntry::where('entry_code', 'A3')->firstOrFail();
        $this->assertEquals('Pending UCLM Validation', $entryA3->approval_status);
        $this->assertEquals('Unpublished', $entryA3->publication_status);
        $this->assertEquals(1, $entryA3->version);

        // 1. SAO saves ordinary metadata/draft validator fields and content edits while approval_status remains Pending UCLM Validation
        $saveRes = $this->actingAs($this->saoUser, 'sanctum')
            ->putJson("/api/sao/self-service/entries/{$entryA3->id}", [
                'approval_status' => 'Pending UCLM Validation',
                'validated_by' => 'Draft Reviewer Name',
                'validated_office' => 'Student Affairs Office (SAO)',
                'validation_notes' => 'Draft packet under review by SAO.',
                'approved_answer' => 'Updated draft answer for Uniform Exemption Application while still pending formal sign-off.',
                'revision_summary' => 'Refined draft wording before final sign-off.',
            ]);

        $saveRes->assertOk()
            ->assertJsonPath('reapproval_required', false)
            ->assertJsonPath('entry.approval_status', 'Pending UCLM Validation')
            ->assertJsonPath('entry.publication_status', 'Unpublished')
            ->assertJsonPath('entry.validated_by', 'Draft Reviewer Name')
            ->assertJsonPath('entry.validated_office', 'Student Affairs Office (SAO)')
            ->assertJsonPath('entry.validation_notes', 'Draft packet under review by SAO.')
            ->assertJsonPath('entry.approved_answer', 'Updated draft answer for Uniform Exemption Application while still pending formal sign-off.')
            ->assertJsonPath('entry.version', 2);

        // 2. Confirm saving did NOT record formal approval timestamp/auditor or publish the entry
        $freshA3 = $entryA3->fresh();
        $this->assertNull($freshA3->validated_at);
        $this->assertNull($freshA3->approval_recorded_by);
        $this->assertNull($freshA3->published_by);
        $this->assertNull($freshA3->published_at);

        // 3. Confirm revision snapshot v1 was created and is returned by the detail endpoint GET /api/sao/self-service/entries/{id}
        $detailRes = $this->actingAs($this->saoUser, 'sanctum')
            ->getJson("/api/sao/self-service/entries/{$entryA3->id}");

        $detailRes->assertOk()
            ->assertJsonCount(1, 'entry.revisions')
            ->assertJsonPath('entry.revisions.0.version', 1)
            ->assertJsonPath('entry.revisions.0.change_notes', 'Refined draft wording before final sign-off.');

        // 4. Confirm the pending entry still cannot be published and remains inaccessible to students
        $this->actingAs($this->saoUser, 'sanctum')
            ->postJson("/api/sao/self-service/entries/{$entryA3->id}/publish")
            ->assertStatus(422);

        $this->actingAs($this->studentA, 'sanctum')
            ->getJson('/api/self-service/entries/A3')
            ->assertNotFound();
    }
}
