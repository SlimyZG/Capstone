<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Concern;
use App\Models\ConcernRouting;
use App\Models\Department;
use App\Models\User;
use App\Services\LocalSentimentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConditionalCadWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private AcademicUnit $ccsUnit;
    private AcademicUnit $marineUnit;
    private Department $cashierDept;
    private Department $facilitiesDept;
    private Department $saoDept;

    private User $cashierStaff;
    private User $facilitiesStaff;
    private User $ccsDean;
    private User $saoUser;
    private User $student;
    private User $otherStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ccsUnit = AcademicUnit::create(['name' => 'College of Computer Studies', 'code' => 'CCS']);
        $this->marineUnit = AcademicUnit::create(['name' => 'College of Marine Transportation', 'code' => 'CMT']);

        $this->cashierDept = Department::create(['name' => 'Cashier']);
        $this->facilitiesDept = Department::create(['name' => 'Facilities & Maintenance']);
        $this->saoDept = Department::create(['name' => 'Student Affairs Office']);

        $this->cashierStaff = User::create([
            'name' => 'Cashier Staff User',
            'email' => 'cashier_staff@test.com',
            'password' => bcrypt('password123'),
            'role' => 'dept_staff',
            'dept_id' => $this->cashierDept->id,
            'academic_unit_id' => null,
        ]);

        $this->facilitiesStaff = User::create([
            'name' => 'Facilities Staff User',
            'email' => 'facilities_staff@test.com',
            'password' => bcrypt('password123'),
            'role' => 'dept_staff',
            'dept_id' => $this->facilitiesDept->id,
            'academic_unit_id' => null,
        ]);

        $this->ccsDean = User::create([
            'name' => 'CCS Dean User',
            'email' => 'dean_ccs@test.com',
            'password' => bcrypt('password123'),
            'role' => 'dean',
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id' => null,
        ]);

        $this->saoUser = User::create([
            'name' => 'SAO Director',
            'email' => 'sao_user@test.com',
            'password' => bcrypt('password123'),
            'role' => 'sao',
            'dept_id' => $this->saoDept->id,
            'academic_unit_id' => null,
        ]);

        $this->student = User::create([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@test.com',
            'password' => bcrypt('password123'),
            'role' => 'student',
            'student_id_number' => '2026-0001',
            'academic_unit_id' => $this->ccsUnit->id,
            'year_level' => 3,
        ]);

        $this->otherStudent = User::create([
            'name' => 'Maria Santos',
            'email' => 'maria@test.com',
            'password' => bcrypt('password123'),
            'role' => 'student',
            'student_id_number' => '2026-0002',
            'academic_unit_id' => $this->marineUnit->id,
            'year_level' => 2,
        ]);
    }

    /**
     * Helper to create a non-academic concern routed to cashier.
     */
    private function createNonAcademicConcern(array $attributes = []): Concern
    {
        return Concern::create(array_merge([
            'student_id' => $this->student->id,
            'dept_id' => $this->cashierDept->id,
            'academic_unit_id' => $this->student->academic_unit_id,
            'category' => 'operational_departments',
            'workflow_type' => 'non_academic',
            'target_type' => 'department',
            'title' => 'Routine Tuition Inquiry',
            'description' => 'Inquiring about partial balance breakdown.',
            'status' => 'pending',
            'priority' => 'normal',
            'ai_priority' => 'normal',
            'issue_category' => 'Non-Academic Concerns',
            'issue_subcategory' => 'Cashier / Payment',
            'is_anonymous' => false,
            'submitted_at' => now(),
            'last_updated' => now(),
        ], $attributes));
    }

    public function test_routine_normal_priority_non_academic_concern_can_be_resolved_directly_without_cad(): void
    {
        $concern = $this->createNonAcademicConcern(['priority' => 'normal']);

        $response = $this->actingAs($this->cashierStaff)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/resolve", [
                'notes' => 'Handled directly over the counter.'
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('concerns', [
            'concern_id' => $concern->concern_id,
            'status' => 'resolved',
            'cad_status' => null,
        ]);
    }

    public function test_routine_medium_priority_non_academic_concern_does_not_auto_forward_to_cad(): void
    {
        $concern = $this->createNonAcademicConcern(['priority' => 'medium']);

        // Check queue
        $queueResponse = $this->actingAs($this->cashierStaff)->getJson('/api/dept-staff/queue');
        $queueResponse->assertStatus(200);

        $concernData = collect($queueResponse->json('concerns'))->firstWhere('concern_id', $concern->concern_id);
        $this->assertNotNull($concernData);
        $this->assertEquals('pending', $concernData['status']);
        $this->assertNull($concernData['cad_status'] ?? null);

        // Can still be directly updated or resolved without CAD
        $res = $this->actingAs($this->cashierStaff)->postJson("/api/dept-staff/concerns/{$concern->concern_id}/status", [
            'status' => 'in_progress',
        ]);
        $res->assertStatus(200);
        $this->assertEquals('in_progress', $concern->fresh()->status);
        $this->assertNull($concern->fresh()->cad_status);
    }

    public function test_high_priority_non_academic_concern_can_be_reviewed_and_initiated_for_cad(): void
    {
        $concern = $this->createNonAcademicConcern([
            'priority' => 'high',
            'title' => 'Urgent Tuition Discrepancy Preventing Exam Entry',
            'description' => 'System balance reflects unpaid status despite bank receipt verified.',
        ]);

        $response = $this->actingAs($this->cashierStaff)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/generate-document", [
                'department_notes' => 'Requires executive override from Campus Administrator due to exam cutoff.'
            ]);

        $response->assertStatus(200);
        $this->assertEquals('awaiting_submission', $concern->fresh()->cad_status);
        $this->assertNotNull($concern->fresh()->cad_document_generated_at);
        $this->assertEquals('Requires executive override from Campus Administrator due to exam cutoff.', $concern->fresh()->cad_department_notes);

        $this->assertDatabaseHas('concern_routing', [
            'concern_id' => $concern->concern_id,
            'action' => 'cad_document_generated',
            'performed_by' => $this->cashierStaff->id,
        ]);
    }

    public function test_critical_non_academic_concern_triggers_notification_and_can_enter_cad(): void
    {
        Notification::fake();

        $concern = $this->createNonAcademicConcern([
            'priority' => 'critical',
            'title' => 'Severe Cashier POS Fire / System Failure',
            'description' => 'Terminal outage affecting entire campus fee collections.',
        ]);

        $initResponse = $this->actingAs($this->cashierStaff)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/generate-document", [
                'department_notes' => 'Critical emergency hardware replacement requiring CAD emergency allocation.'
            ]);

        $initResponse->assertStatus(200);
        $this->assertEquals('awaiting_submission', $concern->fresh()->cad_status);
    }

    public function test_authorized_staff_can_generate_and_fetch_cad_approval_document_payload(): void
    {
        $concern = $this->createNonAcademicConcern([
            'priority' => 'high',
            'ai_priority' => 'high',
            'cad_status' => 'awaiting_submission',
            'cad_department_notes' => 'Special CAD authorization needed.',
        ]);

        $response = $this->actingAs($this->cashierStaff)
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/document");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'document_data' => [
                    'institution' => ['name', 'office', 'form_title', 'provisional_notice'],
                    'reference_number',
                    'generated_at',
                    'generated_by_staff' => ['id', 'name', 'department'],
                    'student' => ['name', 'student_id', 'program_unit', 'is_anonymous'],
                    'concern' => ['concern_id', 'title', 'description', 'priority', 'priority_label'],
                    'department_assessment',
                    'cad_status',
                ]
            ]);

        $doc = $response->json('document_data');
        $this->assertStringContainsString('UNIVERSITY OF CEBU', $doc['institution']['name']);
        $this->assertStringContainsString('Staff Confirmed', $doc['concern']['priority_label']);
        $this->assertEquals('Juan Dela Cruz', $doc['student']['name']);
        $this->assertEquals('2026-0001', $doc['student']['student_id']);
    }

    public function test_unauthorized_users_cannot_generate_cad_document(): void
    {
        $concern = $this->createNonAcademicConcern();

        // 1. Staff from different department (Facilities) cannot generate CAD document for Cashier concern
        $facilitiesRes = $this->actingAs($this->facilitiesStaff)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/generate-document", [
                'department_notes' => 'Unauthorized attempt'
            ]);
        $facilitiesRes->assertStatus(404); // Scoped to staff department

        // 2. Student cannot generate CAD document
        $studentRes = $this->actingAs($this->student)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/generate-document", [
                'department_notes' => 'Student attempt'
            ]);
        $studentRes->assertStatus(403);
    }

    public function test_authorized_staff_can_record_physical_submission_to_cad(): void
    {
        Notification::fake();

        $concern = $this->createNonAcademicConcern([
            'cad_status' => 'awaiting_submission',
            'cad_document_generated_at' => now(),
        ]);

        $response = $this->actingAs($this->cashierStaff)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/record-submission", [
                'submission_notes' => 'Hand-delivered to CAD administrative officer receiving desk.'
            ]);

        $response->assertStatus(200);
        $fresh = $concern->fresh();
        $this->assertEquals('awaiting_decision', $fresh->cad_status);
        $this->assertNotNull($fresh->cad_submitted_at);
        $this->assertEquals($this->cashierStaff->id, $fresh->cad_submitted_by);
        $this->assertEquals('Hand-delivered to CAD administrative officer receiving desk.', $fresh->cad_submission_notes);
        $this->assertEquals('in_progress', $fresh->status);

        $this->assertDatabaseHas('concern_routing', [
            'concern_id' => $concern->concern_id,
            'action' => 'cad_submitted_physically',
            'performed_by' => $this->cashierStaff->id,
        ]);
    }

    public function test_authorized_staff_can_upload_signed_cad_document_and_record_approval(): void
    {
        Storage::fake('local');
        Notification::fake();

        $concern = $this->createNonAcademicConcern([
            'cad_status' => 'awaiting_decision',
            'cad_submitted_at' => now(),
            'cad_submitted_by' => $this->cashierStaff->id,
        ]);

        $file = UploadedFile::fake()->create('signed_cad_approval.pdf', 300, 'application/pdf');

        $response = $this->actingAs($this->cashierStaff)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/upload-decision", [
                'decision' => 'approved',
                'decision_date' => now()->toDateString(),
                'decision_notes' => 'Authorized by Campus Administrator for immediate fee waiver implementation.',
                'signed_document' => $file,
            ]);

        $response->assertStatus(200);

        $fresh = $concern->fresh();
        $this->assertEquals('approved', $fresh->cad_status);
        $this->assertEquals('approved', $fresh->cad_decision);
        $this->assertNotNull($fresh->cad_decision_at);
        $this->assertEquals($this->cashierStaff->id, $fresh->cad_decision_by);
        $this->assertEquals('Authorized by Campus Administrator for immediate fee waiver implementation.', $fresh->cad_decision_notes);
        $this->assertNotNull($fresh->cad_signed_document_path);

        // CAD approval alone does NOT auto-resolve; concern remains in_progress for staff execution!
        $this->assertEquals('in_progress', $fresh->status);

        // Verify stored in private local disk
        Storage::disk('local')->assertExists($fresh->cad_signed_document_path);

        $this->assertDatabaseHas('concern_routing', [
            'concern_id' => $concern->concern_id,
            'action' => 'cad_approved',
            'performed_by' => $this->cashierStaff->id,
        ]);
    }

    public function test_unauthorized_users_cannot_upload_signed_cad_document(): void
    {
        Storage::fake('local');
        $concern = $this->createNonAcademicConcern(['cad_status' => 'awaiting_decision']);
        $file = UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf');

        // Staff from another department
        $facRes = $this->actingAs($this->facilitiesStaff)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/upload-decision", [
                'decision' => 'approved',
                'signed_document' => $file,
            ]);
        $facRes->assertStatus(404);

        // Student
        $studentRes = $this->actingAs($this->student)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/upload-decision", [
                'decision' => 'approved',
                'signed_document' => $file,
            ]);
        $studentRes->assertStatus(403);
    }

    public function test_cad_rejection_decision_leaves_concern_open_for_department_handling(): void
    {
        Storage::fake('local');
        $concern = $this->createNonAcademicConcern(['cad_status' => 'awaiting_decision']);
        $file = UploadedFile::fake()->create('signed_rejection.pdf', 200, 'application/pdf');

        $response = $this->actingAs($this->cashierStaff)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/upload-decision", [
                'decision' => 'rejected',
                'decision_notes' => 'Administrative request declined due to policy constraints.',
                'signed_document' => $file,
            ]);

        $response->assertStatus(200);
        $fresh = $concern->fresh();
        $this->assertEquals('rejected', $fresh->cad_status);
        $this->assertEquals('rejected', $fresh->cad_decision);
        // Concern remains open (in_progress) so department staff can inform student or execute alternative action
        $this->assertEquals('in_progress', $fresh->status);
    }

    public function test_cad_returned_decision_leaves_concern_open_for_revision(): void
    {
        Storage::fake('local');
        $concern = $this->createNonAcademicConcern(['cad_status' => 'awaiting_decision']);
        $file = UploadedFile::fake()->create('signed_return.png', 200, 'image/png');

        $response = $this->actingAs($this->cashierStaff)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/upload-decision", [
                'decision' => 'returned',
                'decision_notes' => 'Please attach original official receipt before CAD can approve.',
                'signed_document' => $file,
            ]);

        $response->assertStatus(200);
        $fresh = $concern->fresh();
        $this->assertEquals('returned', $fresh->cad_status);
        $this->assertEquals('returned', $fresh->status);
    }

    public function test_cad_approval_alone_does_not_auto_resolve_concern_staff_must_finalize(): void
    {
        Storage::fake('local');
        $concern = $this->createNonAcademicConcern(['cad_status' => 'awaiting_decision']);
        $file = UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf');

        // 1. Record CAD Approval
        $this->actingAs($this->cashierStaff)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/upload-decision", [
                'decision' => 'approved',
                'signed_document' => $file,
            ]);

        $this->assertNotEquals('resolved', $concern->fresh()->status);
        $this->assertEquals('approved', $concern->fresh()->cad_status);

        // 2. Department staff executes final resolution
        $resolveRes = $this->actingAs($this->cashierStaff)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/resolve", [
                'notes' => 'Tuition adjustment applied following CAD authorization.',
            ]);

        $resolveRes->assertStatus(200);
        $this->assertEquals('resolved', $concern->fresh()->status);
        $this->assertNotNull($concern->fresh()->resolved_at);
    }

    public function test_students_cannot_download_confidential_signed_cad_document(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->create('confidential_cad.pdf', 200, 'application/pdf')
            ->storeAs('cad_documents/99', 'cad_doc_99.pdf', 'local');

        $concern = $this->createNonAcademicConcern([
            'cad_status' => 'approved',
            'cad_signed_document_path' => $path,
        ]);

        // Student attempt to download via staff route -> 403 Forbidden
        $response = $this->actingAs($this->student)
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/signed-document");
        $response->assertStatus(403);

        // Student attempt via SAO route -> 403 Forbidden
        $saoResponse = $this->actingAs($this->student)
            ->getJson("/api/sao/concerns/{$concern->concern_id}/cad/signed-document");
        $saoResponse->assertStatus(403);

        // Student show concern does not expose file path or internal notes
        $showRes = $this->actingAs($this->student)->getJson("/api/concerns/{$concern->concern_id}");
        $showRes->assertStatus(200);
        $showData = $showRes->json('concern');
        $this->assertArrayNotHasKey('cad_signed_document_path', $showData);
        $this->assertArrayNotHasKey('cad_department_notes', $showData);
        $this->assertArrayNotHasKey('cad_submission_notes', $showData);
        $this->assertArrayNotHasKey('cad_decision_notes', $showData);
    }

    public function test_sao_cannot_download_signed_cad_document_unless_assigned_to_sao_option_a(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->create('confidential_cad.pdf', 200, 'application/pdf')
            ->storeAs('cad_documents/99', 'cad_doc_99.pdf', 'local');

        // Cashier concern NOT assigned to SAO
        $cashierConcern = $this->createNonAcademicConcern([
            'cad_status' => 'approved',
            'cad_signed_document_path' => $path,
        ]);

        // SAO attempting to download Cashier CAD document -> 403 Forbidden (Option A boundary)
        $unauthSaoRes = $this->actingAs($this->saoUser)
            ->getJson("/api/sao/concerns/{$cashierConcern->concern_id}/cad/signed-document");
        $unauthSaoRes->assertStatus(403);

        // SAO-assigned concern
        $saoConcern = $this->createNonAcademicConcern([
            'dept_id' => $this->saoDept->id,
            'cad_status' => 'approved',
            'cad_signed_document_path' => $path,
        ]);

        // Authorized download for SAO-assigned concern
        $authSaoRes = $this->actingAs($this->saoUser)
            ->get("/api/sao/concerns/{$saoConcern->concern_id}/cad/signed-document");
        $authSaoRes->assertStatus(200);
    }

    public function test_academic_workflow_and_academic_unit_isolation_remain_intact(): void
    {
        // Academic concern for CCS
        $academicConcern = Concern::create([
            'student_id' => $this->student->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'category' => 'academic_units',
            'workflow_type' => 'academic',
            'target_type' => 'dean',
            'title' => 'Curriculum Prerequisite Waiver',
            'description' => 'Requesting prerequisite waiver for IT Capstone.',
            'status' => 'pending',
            'priority' => 'high',
            'submitted_at' => now(),
            'last_updated' => now(),
        ]);

        // Academic concerns cannot be processed through CAD endpoints
        $cadAttempt = $this->actingAs($this->cashierStaff)
            ->postJson("/api/dept-staff/concerns/{$academicConcern->concern_id}/cad/generate-document");
        // Not in cashier scope
        $cadAttempt->assertStatus(404);

        // Dean inbox works
        $deanRes = $this->actingAs($this->ccsDean)->getJson('/api/dean/inbox');
        $deanRes->assertStatus(200);
        $directIds = collect($deanRes->json('direct'))->pluck('concern_id');
        $this->assertTrue($directIds->contains($academicConcern->concern_id));
    }

    public function test_classification_consistency_does_not_tag_non_academic_concern_with_academic_category(): void
    {
        $service = app(LocalSentimentService::class);

        // Staff / personnel complaint routed to operational department
        $result = $service->analyzeConcern(
            'Unprofessional staff attitude at cashier window',
            'The employee was rude when I asked about tuition breakdown.',
            'non_academic'
        );

        $this->assertNotEquals('Academic Concerns', $result['issue_category']);
        $this->assertEquals('Non-Academic Concerns', $result['issue_category']);
    }

    // ── Peer harassment / Guidance classification (local path) ──────────────

    public function test_local_peer_harassment_is_non_academic_peer_conflict_not_personnel_grievance(): void
    {
        $service = app(LocalSentimentService::class);

        $r = $service->determineClassification(
            'Classmate keeps harassing me',
            'A classmate has been harassing and bullying me in the hallway. I want to talk to the Guidance Center counselor.',
            'non_academic'
        );
        $this->assertEquals('Non-Academic Concerns', $r['issue_category']);
        $this->assertEquals('Peer Conflict / Harassment', $r['issue_subcategory']);

        $r2 = $service->determineClassification(
            'Another student behaves rudely',
            'A student has a bad attitude and bad conduct towards me. This is a complaint about a classmate.',
            'non_academic'
        );
        $this->assertNotEquals('Personnel / Staff Grievance', $r2['issue_subcategory']);
        $this->assertEquals('Non-Academic Concerns', $r2['issue_category']);
    }

    public function test_local_guidance_center_request_is_student_affairs_and_staff_complaint_is_personnel(): void
    {
        $service = app(LocalSentimentService::class);

        $guidance = $service->determineClassification(
            'Request counseling',
            'I would like a consultation at the Guidance Center because I feel overwhelmed.',
            'non_academic'
        );
        $this->assertEquals('Non-Academic Concerns', $guidance['issue_category']);
        $this->assertEquals('Student Affairs', $guidance['issue_subcategory']);

        $staff = $service->determineClassification(
            'Rude cashier employee',
            'The cashier employee showed rudeness and a poor service attitude at the window.',
            'non_academic'
        );
        $this->assertEquals('Personnel / Staff Grievance', $staff['issue_subcategory']);
    }

    public function test_short_keywords_match_whole_words_only(): void
    {
        $service = app(LocalSentimentService::class);

        // "or" (Cashier / Payment keyword) must not match inside "counselor"
        $r = $service->determineClassification('Counselor meeting', 'I need the counselor', 'non_academic');
        $this->assertNotEquals('Cashier / Payment', $r['issue_subcategory']);
    }

    public function test_instructor_teaching_concerns_remains_available_for_academic_concerns(): void
    {
        $service = app(LocalSentimentService::class);

        $r = $service->determineClassification(
            'Instructor has not posted grades',
            'My instructor has not posted grades and the syllabus is unclear.',
            'academic'
        );
        $this->assertEquals('Academic Concerns', $r['issue_category']);
        $this->assertEquals('Instructor / Teaching Concerns', $r['issue_subcategory']);
    }

    // ── Gemini classification path ──────────────────────────────────────────

    private function fakeGeminiResponse(string $category, string $subcategory): void
    {
        config([
            'services.gemini.enabled' => true,
            'services.gemini.api_key' => 'test-key',
        ]);
        \Illuminate\Support\Facades\Http::fake([
            'generativelanguage.googleapis.com/*' => \Illuminate\Support\Facades\Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'sentiment_label' => 'negative',
                        'sentiment_score' => -0.4,
                        'issue_category' => $category,
                        'issue_subcategory' => $subcategory,
                        'classification_explanation' => 'Test.',
                        'classification_confidence' => 0.9,
                        'priority_suggestion' => 'medium',
                        'priority_explanation' => 'Test.',
                        'summary' => 'Test summary.',
                    ])]]],
                ]],
                'usageMetadata' => ['totalTokenCount' => 10],
            ], 200),
        ]);
    }

    public function test_gemini_path_never_pairs_non_academic_workflow_with_academic_category_or_subcategory(): void
    {
        $this->fakeGeminiResponse('Academic Concerns', 'Instructor / Teaching Concerns');

        $result = app(\App\Services\GeminiSentimentService::class)->analyzeConcern(
            'Classmate harassment',
            'A classmate harasses me. I want Guidance Center help.',
            'non_academic'
        );

        $this->assertNotNull($result);
        $this->assertEquals('Non-Academic Concerns', $result['issue_category']);
        $this->assertArrayNotHasKey('issue_subcategory', $result);

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            $text = $request['contents'][0]['parts'][0]['text'] ?? '';
            return str_contains($text, 'NON-ACADEMIC workflow')
                && str_contains($text, 'Peer Conflict / Harassment')
                && !str_contains($request->url(), 'test-key'); // key travels in header, not URL
        });
    }

    public function test_gemini_path_keeps_instructor_subcategory_for_academic_workflow(): void
    {
        $this->fakeGeminiResponse('Academic Concerns', 'Instructor / Teaching Concerns');

        $result = app(\App\Services\GeminiSentimentService::class)->analyzeConcern(
            'Instructor grading',
            'The instructor grading is inconsistent.',
            'academic'
        );

        $this->assertEquals('Academic Concerns', $result['issue_category']);
        $this->assertEquals('Instructor / Teaching Concerns', $result['issue_subcategory']);
    }

    public function test_gemini_peer_harassment_subcategory_is_preserved_for_non_academic(): void
    {
        $this->fakeGeminiResponse('Non-Academic Concerns', 'Peer Conflict / Harassment');

        $result = app(\App\Services\GeminiSentimentService::class)->analyzeConcern('Bullying', 'A student bullies me.', 'non_academic');

        $this->assertEquals('Non-Academic Concerns', $result['issue_category']);
        $this->assertEquals('Peer Conflict / Harassment', $result['issue_subcategory']);
    }

    // ── CAD priority logic ──────────────────────────────────────────────────

    public function test_critical_concern_notifies_assigned_staff_and_sao_but_never_auto_initiates_cad(): void
    {
        $concern = $this->createNonAcademicConcern(['priority' => 'critical', 'ai_priority' => 'critical']);

        \App\Notifications\ConcernWorkflowNotification::sendCriticalConcernNotification($concern->fresh(), 'Test');

        $this->assertGreaterThan(0, $this->cashierStaff->fresh()->notifications()->count());
        $this->assertGreaterThan(0, $this->saoUser->fresh()->notifications()->count());
        $this->assertEquals(0, $this->facilitiesStaff->fresh()->notifications()->count());

        $fresh = $concern->fresh();
        $this->assertNull($fresh->cad_status);
        $this->assertNull($fresh->forwarded_to_cad_at);
        $this->assertEquals('pending', $fresh->status);
    }

    public function test_staff_can_manually_initiate_cad_for_a_low_priority_concern_when_needed(): void
    {
        $concern = $this->createNonAcademicConcern(['priority' => 'low']);

        $this->actingAs($this->cashierStaff)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/generate-document", [
                'department_notes' => 'Needs administrator sign-off despite low AI priority.',
            ])->assertStatus(200);

        $this->assertEquals('awaiting_submission', $concern->fresh()->cad_status);
    }

    // ── Workflow integrity / state machine ──────────────────────────────────

    public function test_cad_actions_cannot_reopen_a_resolved_concern(): void
    {
        Storage::fake('local');
        $concern = $this->createNonAcademicConcern([
            'status' => 'resolved',
            'resolved_at' => now(),
            'cad_status' => 'awaiting_decision',
        ]);

        $this->actingAs($this->cashierStaff)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/upload-decision", [
                'decision' => 'approved',
                'signed_document' => UploadedFile::fake()->create('s.pdf', 50, 'application/pdf'),
            ])->assertStatus(409);

        $this->actingAs($this->cashierStaff)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/record-submission")
            ->assertStatus(409);

        $this->actingAs($this->cashierStaff)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/generate-document")
            ->assertStatus(409);

        $this->assertEquals('resolved', $concern->fresh()->status);
        $this->assertEquals('awaiting_decision', $concern->fresh()->cad_status);
    }

    public function test_cad_steps_must_follow_order(): void
    {
        Storage::fake('local');
        $concern = $this->createNonAcademicConcern();

        // Submission before document generation
        $this->actingAs($this->cashierStaff)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/record-submission")
            ->assertStatus(409);

        // Decision before submission
        $concern->update(['cad_status' => 'awaiting_submission']);
        $this->actingAs($this->cashierStaff)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/upload-decision", [
                'decision' => 'approved',
                'signed_document' => UploadedFile::fake()->create('s.pdf', 50, 'application/pdf'),
            ])->assertStatus(409);

        $this->assertNull($concern->fresh()->cad_signed_document_path);
    }

    public function test_regenerating_after_return_starts_a_new_round_and_keeps_history(): void
    {
        Storage::fake('local');
        $concern = $this->createNonAcademicConcern(['cad_status' => 'awaiting_decision']);

        $this->actingAs($this->cashierStaff)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/upload-decision", [
                'decision' => 'returned',
                'decision_notes' => 'Add receipt.',
                'signed_document' => UploadedFile::fake()->create('s.pdf', 50, 'application/pdf'),
            ])->assertStatus(200);
        $this->assertEquals('returned', $concern->fresh()->cad_status);

        $this->actingAs($this->cashierStaff)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/generate-document", [
                'department_notes' => 'Receipt attached.',
            ])->assertStatus(200);

        $fresh = $concern->fresh();
        $this->assertEquals('awaiting_submission', $fresh->cad_status);
        $this->assertNull($fresh->cad_decision);
        $this->assertDatabaseHas('concern_routing', ['concern_id' => $concern->concern_id, 'action' => 'cad_returned']);

        // Can proceed through a second round
        $this->actingAs($this->cashierStaff)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/record-submission")
            ->assertStatus(200);
        $this->assertEquals('awaiting_decision', $concern->fresh()->cad_status);
    }

    // ── Upload validation, audit and access ─────────────────────────────────

    public function test_upload_validation_rejects_bad_files_and_decisions(): void
    {
        Storage::fake('local');
        $concern = $this->createNonAcademicConcern(['cad_status' => 'awaiting_decision']);
        $url = "/api/dept-staff/concerns/{$concern->concern_id}/cad/upload-decision";

        $this->actingAs($this->cashierStaff)->postJson($url, [
            'decision' => 'approved',
            'signed_document' => UploadedFile::fake()->create('evil.php', 10, 'text/x-php'),
        ])->assertStatus(422);

        $this->actingAs($this->cashierStaff)->postJson($url, [
            'decision' => 'approved',
            'signed_document' => UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf'),
        ])->assertStatus(422);

        $this->actingAs($this->cashierStaff)->postJson($url, [
            'decision' => 'maybe',
            'signed_document' => UploadedFile::fake()->create('s.pdf', 10, 'application/pdf'),
        ])->assertStatus(422);

        $this->actingAs($this->cashierStaff)->postJson($url, ['decision' => 'approved'])->assertStatus(422);

        $this->assertEquals('awaiting_decision', $concern->fresh()->cad_status);
        $this->assertNull($concern->fresh()->cad_signed_document_path);
    }

    public function test_audit_history_records_correct_staff_and_timestamps(): void
    {
        Storage::fake('local');
        $concern = $this->createNonAcademicConcern();

        $this->actingAs($this->cashierStaff)->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/generate-document", ['department_notes' => 'x'])->assertStatus(200);
        $this->actingAs($this->cashierStaff)->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/record-submission")->assertStatus(200);
        $this->actingAs($this->cashierStaff)->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/upload-decision", [
            'decision' => 'rejected',
            'signed_document' => UploadedFile::fake()->create('s.pdf', 50, 'application/pdf'),
        ])->assertStatus(200);

        $entries = ConcernRouting::where('concern_id', $concern->concern_id)
            ->whereIn('action', ['cad_document_generated', 'cad_submitted_physically', 'cad_rejected'])
            ->get();

        $this->assertCount(3, $entries);
        foreach ($entries as $entry) {
            $this->assertEquals($this->cashierStaff->id, $entry->performed_by);
            $this->assertEquals('Cashier Staff User', $entry->performed_by_name);
            $this->assertNotNull($entry->created_at);
        }

        $fresh = $concern->fresh();
        $this->assertEquals($this->cashierStaff->id, $fresh->cad_submitted_by);
        $this->assertEquals($this->cashierStaff->id, $fresh->cad_decision_by);
        $this->assertNotNull($fresh->cad_submitted_at);
        $this->assertNotNull($fresh->cad_decision_at);
    }

    public function test_dean_and_other_roles_cannot_access_signed_cad_documents(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->create('c.pdf', 50, 'application/pdf')->storeAs('cad_documents/1', 'c.pdf', 'local');
        $concern = $this->createNonAcademicConcern(['cad_status' => 'approved', 'cad_signed_document_path' => $path]);

        foreach (["/api/dean/concerns/{$concern->concern_id}/cad/signed-document"] as $url) {
            $status = $this->actingAs($this->ccsDean)->getJson($url)->status();
            $this->assertContains($status, [403, 404, 405]);
        }

        // Another department's staff is scoped out
        $this->actingAs($this->facilitiesStaff)
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/signed-document")
            ->assertStatus(404);

        // Owning department staff succeed
        $this->actingAs($this->cashierStaff)
            ->get("/api/dept-staff/concerns/{$concern->concern_id}/cad/signed-document")
            ->assertStatus(200);

        // Student never sees CAD internals via the show endpoint
        $json = $this->actingAs($this->student)->getJson("/api/concerns/{$concern->concern_id}")->json('concern');
        $this->assertArrayNotHasKey('cad_signed_document_path', $json);
    }

    public function test_unauthenticated_users_cannot_reach_cad_endpoints(): void
    {
        $concern = $this->createNonAcademicConcern(['cad_status' => 'approved']);

        $this->getJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/signed-document")->assertStatus(401);
        $this->getJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/document")->assertStatus(401);
        $this->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/generate-document")->assertStatus(401);
        $this->getJson("/api/sao/concerns/{$concern->concern_id}/cad/signed-document")->assertStatus(401);
    }

    public function test_cad_document_masks_anonymous_student_identity(): void
    {
        $concern = $this->createNonAcademicConcern(['is_anonymous' => true]);

        $doc = $this->actingAs($this->cashierStaff)
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/document")
            ->assertStatus(200)
            ->json('document_data');

        $this->assertStringNotContainsString('Juan', $doc['student']['name']);
        $this->assertEquals('********', $doc['student']['student_id']);
    }

    public function test_legacy_forward_to_cad_endpoint_returns_410_and_does_not_notify_deans(): void
    {
        Notification::fake();
        $concern = $this->createNonAcademicConcern(['status' => 'pending']);

        $response = $this->actingAs($this->cashierStaff)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/forward-cad", [
                'notes' => 'Attempting legacy forward',
            ]);

        $response->assertStatus(410);
        $response->assertJsonFragment([
            'message' => 'Direct automated forwarding to CAD is disabled. Non-academic concerns requiring CAD approval must use the conditional physical document workflow (generate document, record physical submission, and upload signed determination).'
        ]);

        $this->assertEquals('pending', $concern->fresh()->status);
        $this->assertNull($concern->fresh()->forwarded_to_cad_at);
        Notification::assertNothingSent();
    }

    public function test_peer_harassment_concern_routes_to_guidance_center(): void
    {
        $guidanceDept = Department::create(['name' => 'Guidance Center']);
        $allDepts = Department::all();

        $service = app(LocalSentimentService::class);
        $recommendation = $service->recommendOperationalDepartment(
            'Bullying and peer harassment in campus corridor',
            'A fellow student has been bullying and harassing me repeatedly, threatening me after classes.',
            $allDepts
        );

        $this->assertEquals($guidanceDept->id, $recommendation['recommended_department_id']);
        $this->assertEquals('Guidance Center', $recommendation['recommended_department_name']);
    }

    public function test_provisional_template_contains_neutral_title_and_no_invented_codes(): void
    {
        $concern = $this->createNonAcademicConcern();

        $response = $this->actingAs($this->cashierStaff)
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/document");

        $response->assertStatus(200);
        $doc = $response->json('document_data');

        $this->assertEquals('SCMS Administrative Approval Request', $doc['institution']['form_title']);
        $this->assertArrayNotHasKey('form_code', $doc['institution']);
        $this->assertStringContainsString('Provisional SCMS-generated', $doc['institution']['provisional_notice']);
    }

    public function test_cad_signed_document_serves_via_configured_disk_response(): void
    {
        $diskName = config('filesystems.cad_disk', 'local');
        Storage::fake($diskName);

        $file = UploadedFile::fake()->create('signed_resolution.pdf', 80, 'application/pdf');
        $concern = $this->createNonAcademicConcern(['cad_status' => 'awaiting_decision']);

        $uploadRes = $this->actingAs($this->cashierStaff)
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/upload-decision", [
                'decision' => 'approved',
                'signed_document' => $file,
            ]);
        $uploadRes->assertStatus(200);

        $downloadRes = $this->actingAs($this->cashierStaff)
            ->get("/api/dept-staff/concerns/{$concern->concern_id}/cad/signed-document");

        $downloadRes->assertStatus(200);
        $this->assertStringContainsString('inline', $downloadRes->headers->get('Content-Disposition') ?? '');
        $this->assertStringContainsString('private', $downloadRes->headers->get('Cache-Control') ?? '');
    }
}
