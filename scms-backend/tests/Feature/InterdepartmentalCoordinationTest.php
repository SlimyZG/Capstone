<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Concern;
use App\Models\ConcernCollaboration;
use App\Models\ConcernInternalMessage;
use App\Models\ConcernTransferRequest;
use App\Models\Department;
use App\Models\PcoApprovalEvent;
use App\Models\PcoApprovalRequest;
use App\Models\User;
use App\Notifications\ConcernWorkflowNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InterdepartmentalCoordinationTest extends TestCase
{
    use RefreshDatabase;

    private AcademicUnit $ccsUnit;
    private AcademicUnit $engineeringUnit;

    private Department $accountingDept;
    private Department $cashierDept;
    private Department $registrarDept;
    private Department $scholarshipDept;
    private Department $saoDept;
    private Department $csuDept;
    private Department $pcoDept;
    private Department $libraryDept;

    private User $student;
    private User $accountingStaff;
    private User $cashierStaff;
    private User $registrarStaff;
    private User $scholarshipStaff;
    private User $saoUser;
    private User $csuStaff;
    private User $pcoStaff;
    private User $libraryStaff;
    private User $ccsStaff;
    private User $ccsDean;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ccsUnit = AcademicUnit::create(['name' => 'Computer Studies']);
        $this->engineeringUnit = AcademicUnit::create(['name' => 'Engineering']);

        $this->accountingDept = Department::create(['name' => "Student's Accounting"]);
        $this->cashierDept = Department::create(['name' => 'Cashier']);
        $this->registrarDept = Department::create(['name' => 'Registrar']);
        $this->scholarshipDept = Department::create(['name' => 'Scholarship']);
        $this->saoDept = Department::create(['name' => 'SAO']);
        $this->csuDept = Department::create(['name' => 'CSU']);
        $this->pcoDept = Department::firstOrCreate(['name' => 'PCO']);
        $this->libraryDept = Department::create(['name' => 'Library Office']);

        $this->student = User::create([
            'name' => 'Student Promissory',
            'email' => 'student.coord@uclm.edu.ph',
            'student_id_number' => '2026-90001',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'academic_unit_id' => $this->ccsUnit->id,
        ]);

        $this->accountingStaff = User::create([
            'name' => 'Accounting Staff',
            'email' => 'accounting.staff@uclm.edu.ph',
            'password' => Hash::make('password123'),
            'role' => 'dept_staff',
            'dept_id' => $this->accountingDept->id,
        ]);

        $this->cashierStaff = User::create([
            'name' => 'Cashier Staff',
            'email' => 'cashier.staff@uclm.edu.ph',
            'password' => Hash::make('password123'),
            'role' => 'dept_staff',
            'dept_id' => $this->cashierDept->id,
        ]);

        $this->registrarStaff = User::create([
            'name' => 'Registrar Staff',
            'email' => 'registrar.staff@uclm.edu.ph',
            'password' => Hash::make('password123'),
            'role' => 'dept_staff',
            'dept_id' => $this->registrarDept->id,
        ]);

        $this->scholarshipStaff = User::create([
            'name' => 'Scholarship Staff',
            'email' => 'scholarship.staff@uclm.edu.ph',
            'password' => Hash::make('password123'),
            'role' => 'dept_staff',
            'dept_id' => $this->scholarshipDept->id,
        ]);

        $this->saoUser = User::create([
            'name' => 'SAO Director',
            'email' => 'sao.coord@uclm.edu.ph',
            'password' => Hash::make('password123'),
            'role' => 'sao',
            'dept_id' => $this->saoDept->id,
        ]);

        $this->csuStaff = User::create([
            'name' => 'CSU Security Officer',
            'email' => 'csu.staff@uclm.edu.ph',
            'password' => Hash::make('password123'),
            'role' => 'dept_staff',
            'dept_id' => $this->csuDept->id,
        ]);

        $this->pcoStaff = User::create([
            'name' => 'PCO Property Custodian',
            'email' => 'pco.staff@uclm.edu.ph',
            'password' => Hash::make('password123'),
            'role' => 'dept_staff',
            'dept_id' => $this->pcoDept->id,
        ]);

        $this->libraryStaff = User::create([
            'name' => 'Library Staff',
            'email' => 'library.staff@uclm.edu.ph',
            'password' => Hash::make('password123'),
            'role' => 'dept_staff',
            'dept_id' => $this->libraryDept->id,
        ]);

        $this->ccsStaff = User::create([
            'name' => 'CCS Chairperson',
            'email' => 'ccs.chair@uclm.edu.ph',
            'password' => Hash::make('password123'),
            'role' => 'dept_staff',
            'academic_unit_id' => $this->ccsUnit->id,
        ]);

        $this->ccsDean = User::create([
            'name' => 'CCS Dean',
            'email' => 'ccs.dean@uclm.edu.ph',
            'password' => Hash::make('password123'),
            'role' => 'dean',
            'academic_unit_id' => $this->ccsUnit->id,
        ]);
    }

    private function createOperationalConcern(int $deptId, array $overrides = []): Concern
    {
        return Concern::create(array_merge([
            'student_id' => $this->student->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'target_type' => 'department',
            'dept_id' => $deptId,
            'title' => 'Promissory Note & Tuition Receipt Verification',
            'description' => 'Submitted promissory note and payment receipt; need balance updated before exams.',
            'category' => 'operational_departments',
            'workflow_type' => 'non_academic',
            'status' => 'in_progress',
            'priority' => 'medium',
            'submitted_at' => now(),
            'last_updated' => now(),
        ], $overrides));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 1–5 & 8–9: UNLIMITED HANDSHAKE TRANSFERS, PREVIEW LIMITS, & SAFEGUARDS
    // ══════════════════════════════════════════════════════════════════════════

    public function test_unlimited_handshake_transfers_including_more_than_four_accepted_transfers_and_reversals(): void
    {
        Notification::fake();

        $concern = $this->createOperationalConcern($this->registrarDept->id);

        // Sequence of 5 accepted transfers including A->B->A reversals (no arbitrary 3-transfer cap!)
        $hops = [
            ['from' => $this->registrarStaff, 'fromDept' => $this->registrarDept, 'to' => $this->accountingStaff, 'toDept' => $this->accountingDept, 'expectReversal' => false],
            ['from' => $this->accountingStaff, 'fromDept' => $this->accountingDept, 'to' => $this->cashierStaff, 'toDept' => $this->cashierDept, 'expectReversal' => false],
            ['from' => $this->cashierStaff, 'fromDept' => $this->cashierDept, 'to' => $this->accountingStaff, 'toDept' => $this->accountingDept, 'expectReversal' => true],
            ['from' => $this->accountingStaff, 'fromDept' => $this->accountingDept, 'to' => $this->scholarshipStaff, 'toDept' => $this->scholarshipDept, 'expectReversal' => false],
            ['from' => $this->scholarshipStaff, 'fromDept' => $this->scholarshipDept, 'to' => $this->cashierStaff, 'toDept' => $this->cashierDept, 'expectReversal' => true],
        ];

        foreach ($hops as $index => $hop) {
            $seq = $index + 1;

            // 1. Initiate transfer
            $initRes = $this->actingAs($hop['from'], 'sanctum')
                ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfers", [
                    'to_dept_id' => $hop['toDept']->id,
                    'transfer_reason' => "Hop #{$seq}: Belongs to {$hop['toDept']->name} for verification.",
                    'handoff_summary' => "Hop #{$seq} handoff summary from {$hop['fromDept']->name}.",
                ]);

            $initRes->assertCreated()
                ->assertJsonPath('transfer_request.transfer_sequence', $seq)
                ->assertJsonPath('transfer_request.status', 'pending')
                ->assertJsonPath('transfer_request.is_reversal', $hop['expectReversal']);

            if ($hop['expectReversal'] || $seq >= 3) {
                $this->assertNotNull($initRes->json('reversal_warning'));
            }

            $transferId = $initRes->json('transfer_request.id');

            // 2. While pending, original department STILL owns the concern
            $this->assertEquals($hop['fromDept']->id, $concern->fresh()->dept_id);

            // 3. Target department accepts the transfer
            $acceptRes = $this->actingAs($hop['to'], 'sanctum')
                ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfers/{$transferId}/accept", [
                    'decision_notes' => "Accepted hop #{$seq} by {$hop['toDept']->name}.",
                ]);

            $acceptRes->assertOk()
                ->assertJsonPath('transfer_request.status', 'accepted')
                ->assertJsonPath('concern.dept_id', $hop['toDept']->id);

            // 4. Primary ownership has now switched to the target department
            $this->assertEquals($hop['toDept']->id, $concern->fresh()->dept_id);
            $this->assertEquals($hop['fromDept']->name, $concern->fresh()->transferred_from);
        }

        // Confirm all 5 accepted transfer records are preserved in immutable history
        $this->assertEquals(
            5,
            ConcernTransferRequest::where('concern_id', $concern->concern_id)
                ->where('status', 'accepted')
                ->count()
        );
    }

    public function test_transfer_decline_cancellation_single_pending_limit_and_unauthorized_target_protection(): void
    {
        Notification::fake();

        $concern = $this->createOperationalConcern($this->accountingDept->id);

        // 1. Accounting initiates transfer to Cashier
        $initRes = $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfers", [
                'to_dept_id' => $this->cashierDept->id,
                'transfer_reason' => 'Needs OR validation at Cashier window.',
                'handoff_summary' => 'Student attached bank deposit slip.',
            ])
            ->assertCreated();

        $transferId = $initRes->json('transfer_request.id');

        // 2. Simultaneous second pending transfer on the same concern must be rejected (422)
        $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfers", [
                'to_dept_id' => $this->registrarDept->id,
                'transfer_reason' => 'Simultaneous transfer attempt.',
                'handoff_summary' => 'Should be blocked while Cashier transfer is pending.',
            ])
            ->assertStatus(422);

        // 3. Unauthorized third department (Registrar) cannot accept or decline Cashier's transfer request (403)
        $this->actingAs($this->registrarStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfers/{$transferId}/accept")
            ->assertForbidden();

        $this->actingAs($this->registrarStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfers/{$transferId}/decline", [
                'decision_notes' => 'Unauthorized decline attempt.',
            ])
            ->assertForbidden();

        // 4. Cashier cannot decline without a mandatory explanation (422)
        $this->actingAs($this->cashierStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfers/{$transferId}/decline", [
                'decision_notes' => '   ',
            ])
            ->assertStatus(422);

        // 5. Cashier declines with mandatory reason -> Accounting retains ownership
        $this->actingAs($this->cashierStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfers/{$transferId}/decline", [
                'decision_notes' => 'Promissory notes are approved by Accounting; Cashier only issues OR after payment.',
            ])
            ->assertOk()
            ->assertJsonPath('transfer_request.status', 'declined');

        $this->assertEquals($this->accountingDept->id, $concern->fresh()->dept_id);

        // After declining, Cashier no longer has preview access to the concern (404 scoped out)
        $this->actingAs($this->cashierStaff, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}")
            ->assertNotFound();

        // 6. Accounting initiates a new transfer to Scholarship and then cancels it before decision
        $cancelInitRes = $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfers", [
                'to_dept_id' => $this->scholarshipDept->id,
                'transfer_reason' => 'Check academic scholarship discount.',
                'handoff_summary' => 'Student claims 50% scholarship discount.',
            ])
            ->assertCreated();

        $cancelTransferId = $cancelInitRes->json('transfer_request.id');

        $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfers/{$cancelTransferId}/cancel")
            ->assertOk()
            ->assertJsonPath('transfer_request.status', 'cancelled');

        // Scholarship can no longer accept or preview after cancellation
        $this->actingAs($this->scholarshipStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfers/{$cancelTransferId}/accept")
            ->assertStatus(422);

        $this->actingAs($this->scholarshipStaff, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}")
            ->assertNotFound();
    }

    public function test_pending_transfer_target_receives_limited_preview_and_cannot_read_full_internal_message_history_until_acceptance(): void
    {
        $concern = $this->createOperationalConcern($this->accountingDept->id);

        // Accounting posts a confidential internal note before initiating transfer
        $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/internal-messages", [
                'body' => 'Internal Accounting audit note: verifying prior semester ledger adjustments.',
            ])
            ->assertCreated();

        // Accounting initiates transfer to Cashier
        $initRes = $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfers", [
                'to_dept_id' => $this->cashierDept->id,
                'transfer_reason' => 'Transferring to Cashier for official receipt issuance.',
                'handoff_summary' => 'Ledger verified; Cashier needs to issue duplicate OR.',
            ])
            ->assertCreated();

        $transferId = $initRes->json('transfer_request.id');

        // While transfer is pending, Cashier gets LIMITED preview + handoff context only:
        // - coordination.access_mode === 'pending_transfer_target'
        // - coordination.is_limited_preview === true
        // - coordination.can_read_internal_messages === false
        // - coordination.internal_messages is EMPTY
        $previewRes = $this->actingAs($this->cashierStaff, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}");

        $previewRes->assertOk()
            ->assertJsonPath('concern.coordination.access_mode', 'pending_transfer_target')
            ->assertJsonPath('concern.coordination.is_limited_preview', true)
            ->assertJsonPath('concern.coordination.can_read_internal_messages', false)
            ->assertJsonCount(0, 'concern.coordination.internal_messages')
            ->assertJsonCount(0, 'concern.messages');

        // Direct call to GET /internal-messages and POST /internal-messages by pending transfer target returns 403
        $this->actingAs($this->cashierStaff, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}/internal-messages")
            ->assertForbidden();

        $this->actingAs($this->cashierStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/internal-messages", [
                'body' => 'Should not be able to post before accepting transfer.',
            ])
            ->assertForbidden();

        // Once Cashier accepts the transfer, Cashier becomes primary owner and can read/post internal messages
        $this->actingAs($this->cashierStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfers/{$transferId}/accept")
            ->assertOk();

        $fullRes = $this->actingAs($this->cashierStaff, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}/internal-messages");

        $fullRes->assertOk();
        $this->assertGreaterThanOrEqual(2, count($fullRes->json('internal_messages')));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 6–7: DEPARTMENT COLLABORATION & PERMISSION BOUNDARIES
    // ══════════════════════════════════════════════════════════════════════════

    public function test_collaboration_lifecycle_internal_messaging_completion_and_permission_boundaries(): void
    {
        Notification::fake();

        // Example Use Case: Promissory note concern owned by Student's Accounting, collaborating with Cashier
        $concern = $this->createOperationalConcern($this->accountingDept->id);

        // 1. Accounting requests collaboration from Cashier
        $collabRes = $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/collaborations", [
                'collaborator_dept_id' => $this->cashierDept->id,
                'collaboration_reason' => 'Verify partial tuition payment OR number for promissory note.',
                'requested_assistance' => 'Please confirm OR #88412 amount and posting date in Cashier system.',
            ]);

        $collabRes->assertCreated()
            ->assertJsonPath('collaboration.status', 'pending');

        $collabId = $collabRes->json('collaboration.id');

        // Primary ownership remains with Student's Accounting
        $this->assertEquals($this->accountingDept->id, $concern->fresh()->dept_id);

        // Before accepting, Cashier cannot post internal messages (403)
        $this->actingAs($this->cashierStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/internal-messages", [
                'body' => 'Trying to post before accepting collaboration.',
            ])
            ->assertForbidden();

        // 2. Cashier accepts collaboration
        $this->actingAs($this->cashierStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/collaborations/{$collabId}/accept", [
                'response_notes' => 'Checking Cashier receipt records now.',
            ])
            ->assertOk()
            ->assertJsonPath('collaboration.status', 'accepted');

        // Primary ownership STILL remains with Student's Accounting
        $this->assertEquals($this->accountingDept->id, $concern->fresh()->dept_id);

        // 3. Cashier can now read and post in the Internal Department Channel
        $msgRes = $this->actingAs($this->cashierStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/internal-messages", [
                'body' => 'Verified OR #88412: PHP 5,000 posted on Nov 20, 2026.',
            ]);

        $msgRes->assertCreated()
            ->assertJsonPath('data.sender_department_name', 'Cashier');

        // Accounting is notified of Cashier's internal note; Student is NEVER notified
        Notification::assertSentTo(
            $this->accountingStaff,
            ConcernWorkflowNotification::class,
            fn ($n) => $n->event === 'internal_department_message'
        );
        Notification::assertNotSentTo($this->student, ConcernWorkflowNotification::class);

        // 4. Collaborator permission boundaries: Cashier CANNOT resolve, change status, reply to student, transfer, or invite 3rd-party collaborators (all 403)
        $this->actingAs($this->cashierStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/resolve", [
                'notes' => 'Collaborator trying to resolve primary concern.',
            ])
            ->assertForbidden();

        $this->actingAs($this->cashierStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/status", [
                'status' => 'resolved',
            ])
            ->assertForbidden();

        $this->actingAs($this->cashierStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/reply", [
                'body' => 'Collaborator trying to message the student directly.',
            ])
            ->assertForbidden();

        $this->actingAs($this->cashierStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfers", [
                'to_dept_id' => $this->registrarDept->id,
                'transfer_reason' => 'Collaborator trying to transfer concern.',
                'handoff_summary' => 'Should be forbidden.',
            ])
            ->assertForbidden();

        $this->actingAs($this->cashierStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/collaborations", [
                'collaborator_dept_id' => $this->registrarDept->id,
                'collaboration_reason' => 'Collaborator trying to invite another dept.',
                'requested_assistance' => 'Should be forbidden.',
            ])
            ->assertForbidden();

        // 5. Cashier marks their requested assistance as completed
        $this->actingAs($this->cashierStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/collaborations/{$collabId}/complete", [
                'response_notes' => 'OR #88412 verification completed and posted in internal channel.',
            ])
            ->assertOk()
            ->assertJsonPath('collaboration.status', 'completed');

        // 6. Completed collaborator retains historical read access to the concern and internal messages, but CANNOT post new internal messages (403)
        $this->actingAs($this->cashierStaff, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}")
            ->assertOk()
            ->assertJsonPath('concern.coordination.access_mode', 'completed_collaborator');

        $this->actingAs($this->cashierStaff, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}/internal-messages")
            ->assertOk();

        $this->actingAs($this->cashierStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/internal-messages", [
                'body' => 'Completed collaborator trying to post new internal message.',
            ])
            ->assertForbidden();

        // 7. Primary owner (Accounting) sends official student-facing response and resolves the concern
        $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/reply", [
                'body' => 'Your promissory note and OR #88412 have been verified. Your exam permit is now cleared.',
            ])
            ->assertCreated();

        $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/resolve", [
                'notes' => 'Promissory note cleared after Cashier OR verification.',
            ])
            ->assertOk();

        $this->assertEquals('resolved', $concern->fresh()->status);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 10–17: PCO DEPARTMENT APPROVAL WORKFLOW & INDEPENDENT CAD COMPATIBILITY
    // ══════════════════════════════════════════════════════════════════════════

    public function test_pco_approval_lifecycle_clarification_rejection_resolution_block_scope_amendment_and_cad_independence(): void
    {
        Notification::fake();
        Storage::fake('local');

        $concern = $this->createOperationalConcern($this->pcoDept->id, [
            'title' => 'Replacement of Damaged Electrical Panel in Building B Room 301',
            'description' => 'Breaker box in Room 301 is sparking and needs replacement.',
        ]);

        // 1. PCO cannot target an ineligible department (e.g., Library Office is not in eligible_approving_departments) -> fails closed (422)
        $this->actingAs($this->pcoStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals", [
                'approving_dept_id' => $this->libraryDept->id,
                'approval_reason' => 'Trying to request approval from non-eligible department.',
                'proposed_work_description' => 'Replace electrical panel.',
                'work_scope' => 'Building B Room 301.',
            ])
            ->assertStatus(422);

        // 2. PCO submits valid approval request to CSU (configured eligible approving department) with a private attachment
        $file = UploadedFile::fake()->create('electrical_inspection_report.pdf', 120, 'application/pdf');

        $submitRes = $this->actingAs($this->pcoStaff, 'sanctum')
            ->post("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals", [
                'approving_dept_id' => $this->csuDept->id,
                'approval_reason' => 'Requires safety & power shutoff clearance before PCO replaces main breaker.',
                'proposed_work_description' => 'Isolate power feed and replace 100A distribution panel.',
                'work_scope' => 'Building B, 3rd Floor, Room 301 electrical sub-panel only.',
                'supporting_notes' => 'Estimated 2-hour outage during lunch break.',
                'attachments' => [$file],
            ], ['Accept' => 'application/json']);

        $submitRes->assertCreated()
            ->assertJsonPath('pco_approval_request.status', 'pending')
            ->assertJsonPath('pco_approval_request.is_work_authorized', false);

        $requestId = $submitRes->json('pco_approval_request.id');

        // PCO retains primary ownership
        $this->assertEquals($this->pcoDept->id, $concern->fresh()->dept_id);

        // 3. While approval is 'pending', PCO is BLOCKED from resolving the concern (422)
        $this->actingAs($this->pcoStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/resolve", [
                'notes' => 'Trying to resolve before departmental approval is granted.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('pco_approval_status', 'pending');

        // Approving department (CSU) can stream the private PCO attachment; uninvolved department (Library) gets 403
        $this->actingAs($this->csuStaff, 'sanctum')
            ->get("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals/{$requestId}/attachments/0")
            ->assertOk();

        $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals/{$requestId}/attachments/0")
            ->assertForbidden();

        // 4. CSU requests clarification from PCO
        $this->actingAs($this->csuStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals/{$requestId}/request-clarification", [
                'decision_notes' => 'Please confirm whether fire alarm circuit on 3rd floor will remain powered.',
            ])
            ->assertOk()
            ->assertJsonPath('pco_approval_request.status', 'clarification_requested')
            ->assertJsonPath('pco_approval_request.is_work_authorized', false);

        // PCO is still blocked from resolving while clarification_requested (422)
        $this->actingAs($this->pcoStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/resolve")
            ->assertStatus(422);

        // 5. PCO provides clarification -> status returns to 'pending'
        $this->actingAs($this->pcoStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals/{$requestId}/provide-clarification", [
                'supporting_notes' => 'Confirmed: fire alarm circuit is on dedicated emergency riser and will remain powered.',
            ])
            ->assertOk()
            ->assertJsonPath('pco_approval_request.status', 'pending');

        // 6. CSU rejects the request without explanation -> 422; then rejects with mandatory explanation
        $this->actingAs($this->csuStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals/{$requestId}/reject", [
                'decision_notes' => '',
            ])
            ->assertStatus(422);

        $this->actingAs($this->csuStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals/{$requestId}/reject", [
                'decision_notes' => 'Cannot shut off power during mid-day classes; must schedule after 5:00 PM.',
            ])
            ->assertOk()
            ->assertJsonPath('pco_approval_request.status', 'rejected')
            ->assertJsonPath('pco_approval_request.is_work_authorized', false);

        // PCO is blocked from resolving while request is rejected (422)
        $this->actingAs($this->pcoStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/resolve")
            ->assertStatus(422)
            ->assertJsonPath('pco_approval_status', 'rejected');

        // 7. PCO submits a revised request superseding the rejected request
        $revisedRes = $this->actingAs($this->pcoStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals", [
                'approving_dept_id' => $this->csuDept->id,
                'supersedes_request_id' => $requestId,
                'approval_reason' => 'Rescheduled power isolation and panel replacement to 5:30 PM.',
                'proposed_work_description' => 'Isolate power feed at 5:30 PM and replace 100A panel.',
                'work_scope' => 'Building B, Room 301 electrical sub-panel (5:30 PM–7:30 PM window).',
            ])
            ->assertCreated();

        $revisedId = $revisedRes->json('pco_approval_request.id');
        $this->assertEquals('superseded', PcoApprovalRequest::findOrFail($requestId)->status);

        // 8. Approving department (CSU) CANNOT resolve PCO's concern directly (403)
        $this->actingAs($this->csuStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/resolve")
            ->assertForbidden();

        // 9. CSU formally approves the revised request
        $approveRes = $this->actingAs($this->csuStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals/{$revisedId}/approve", [
                'decision_notes' => 'Approved for 5:30 PM window with CSU guard on site.',
            ]);

        $approveRes->assertOk()
            ->assertJsonPath('pco_approval_request.status', 'approved')
            ->assertJsonPath('pco_approval_request.is_work_authorized', true);

        // Explicit notification sent to PCO stating PCO is AUTHORIZED to begin work
        Notification::assertSentTo(
            $this->pcoStaff,
            ConcernWorkflowNotification::class,
            fn ($n) => $n->event === 'pco_approval_approved'
                && str_contains($n->title, 'Authorized to Begin Work')
                && str_contains($n->message, 'AUTHORIZED to begin')
        );

        // 10. Scope Amendment Test: If PCO materially expands scope to Room 302 as well, PCO submits an amended scope superseding $revisedId -> work authorization resets until re-approved
        $amendedRes = $this->actingAs($this->pcoStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals", [
                'approving_dept_id' => $this->csuDept->id,
                'supersedes_request_id' => $revisedId,
                'approval_reason' => 'Scope expanded to include adjacent Room 302 feeder cable.',
                'proposed_work_description' => 'Replace Room 301 panel and Room 302 feeder line.',
                'work_scope' => 'Building B, Rooms 301 and 302 electrical sub-panels (5:30 PM–8:30 PM).',
            ])
            ->assertCreated()
            ->assertJsonPath('pco_approval_request.is_work_authorized', false);

        $amendedId = $amendedRes->json('pco_approval_request.id');

        // PCO is blocked from resolving until the amended scope is approved
        $this->actingAs($this->pcoStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/resolve")
            ->assertStatus(422);

        // CSU approves the amended scope
        $this->actingAs($this->csuStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals/{$amendedId}/approve", [
                'decision_notes' => 'Amended scope for Rooms 301 and 302 approved.',
            ])
            ->assertOk();

        // 11. Independent CAD Physical Approval compatibility check: PCO can still generate a CAD document without overwriting PCO approval records
        $this->actingAs($this->pcoStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/cad/generate-document", [
                'department_notes' => 'CAD physical capital expenditure sign-off.',
            ])
            ->assertOk()
            ->assertJsonPath('concern.cad_status', 'awaiting_submission');

        // 12. PCO can now resolve the concern after completing the authorized work
        $this->actingAs($this->pcoStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/resolve", [
                'notes' => 'Completed panel and feeder replacement within approved scope.',
            ])
            ->assertOk()
            ->assertJsonPath('concern.status', 'resolved');

        // Verify immutable PCO approval event audit trail recorded all events
        $this->assertGreaterThanOrEqual(
            7,
            PcoApprovalEvent::where('concern_id', $concern->concern_id)->count()
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 18–21: STUDENT PRIVACY, UNAUTHORIZED DEPT ISOLATION, SAO OPTION A, & ACADEMIC WORKFLOW
    // ══════════════════════════════════════════════════════════════════════════

    public function test_student_api_never_exposes_internal_messages_transfers_collaborations_or_pco_approvals(): void
    {
        $concern = $this->createOperationalConcern($this->pcoDept->id);

        // Create internal message, collaboration request, PCO approval request, and declined transfer
        $this->actingAs($this->pcoStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/internal-messages", [
                'body' => 'CONFIDENTIAL INTERNAL NOTE: Do not expose to student.',
            ])
            ->assertCreated();

        $this->actingAs($this->pcoStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/collaborations", [
                'collaborator_dept_id' => $this->accountingDept->id,
                'collaboration_reason' => 'Internal budget check.',
                'requested_assistance' => 'CONFIDENTIAL COLLABORATION: Check maintenance budget code.',
            ])
            ->assertCreated();

        $this->actingAs($this->pcoStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals", [
                'approving_dept_id' => $this->csuDept->id,
                'approval_reason' => 'Internal CSU clearance.',
                'proposed_work_description' => 'CONFIDENTIAL PCO WORK DESCRIPTION.',
                'work_scope' => 'Building B Room 301.',
            ])
            ->assertCreated();

        // Student fetches their concern list and concern detail
        $listRes = $this->actingAs($this->student, 'sanctum')
            ->getJson('/api/concerns')
            ->assertOk();

        $detailRes = $this->actingAs($this->student, 'sanctum')
            ->getJson("/api/concerns/{$concern->concern_id}")
            ->assertOk();

        $studentConcern = $detailRes->json('concern');
        $rawJson = $detailRes->getContent();

        $this->assertArrayNotHasKey('coordination', $studentConcern);
        $this->assertArrayNotHasKey('internal_messages', $studentConcern);
        $this->assertArrayNotHasKey('transfer_requests', $studentConcern);
        $this->assertArrayNotHasKey('collaborations', $studentConcern);
        $this->assertArrayNotHasKey('pco_approval_requests', $studentConcern);

        $this->assertStringNotContainsString('CONFIDENTIAL INTERNAL NOTE', $rawJson);
        $this->assertStringNotContainsString('CONFIDENTIAL COLLABORATION', $rawJson);
        $this->assertStringNotContainsString('CONFIDENTIAL PCO WORK DESCRIPTION', $rawJson);

        // Student cannot access staff internal-messages endpoint (403)
        $this->actingAs($this->student, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}/internal-messages")
            ->assertForbidden();
    }

    public function test_unauthorized_departments_and_uninvolved_sao_option_a_cannot_access_coordination_endpoints(): void
    {
        $concern = $this->createOperationalConcern($this->accountingDept->id);

        // 1. Uninvolved department (Library) cannot view concern (404 scoped out), read/post internal messages, transfer, or collaborate (403)
        $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}")
            ->assertNotFound();

        $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}/internal-messages")
            ->assertForbidden();

        $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/internal-messages", [
                'body' => 'Unauthorized library note.',
            ])
            ->assertForbidden();

        $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfers", [
                'to_dept_id' => $this->cashierDept->id,
                'transfer_reason' => 'Unauthorized transfer.',
                'handoff_summary' => 'Unauthorized.',
            ])
            ->assertForbidden();

        // 2. Uninvolved SAO user (Option A) cannot view Accounting's individual concern or internal messages (403)
        $this->actingAs($this->saoUser, 'sanctum')
            ->getJson("/api/sao/concerns/{$concern->concern_id}")
            ->assertForbidden();

        $this->actingAs($this->saoUser, 'sanctum')
            ->getJson("/api/sao/concerns/{$concern->concern_id}/internal-messages")
            ->assertForbidden();

        // 3. When Accounting explicitly invites SAO as a collaborator and SAO accepts, SAO gains Option A scoped access
        $collabRes = $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/collaborations", [
                'collaborator_dept_id' => $this->saoDept->id,
                'collaboration_reason' => 'Verify student conduct clearance with SAO.',
                'requested_assistance' => 'Confirm student has no active disciplinary hold.',
            ])
            ->assertCreated();

        $collabId = $collabRes->json('collaboration.id');

        $this->actingAs($this->saoUser, 'sanctum')
            ->postJson("/api/sao/concerns/{$concern->concern_id}/collaborations/{$collabId}/accept")
            ->assertOk();

        $this->actingAs($this->saoUser, 'sanctum')
            ->getJson("/api/sao/concerns/{$concern->concern_id}")
            ->assertOk()
            ->assertJsonPath('concern.coordination.access_mode', 'active_collaborator');

        $this->actingAs($this->saoUser, 'sanctum')
            ->postJson("/api/sao/concerns/{$concern->concern_id}/internal-messages", [
                'body' => 'SAO verified: Student has no disciplinary hold.',
            ])
            ->assertCreated();
    }

    public function test_existing_academic_chairperson_and_dean_workflow_remains_unchanged(): void
    {
        $academicConcern = Concern::create([
            'student_id' => $this->student->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'target_type' => 'department',
            'dept_id' => null,
            'title' => 'Capstone Grading Consultation',
            'description' => 'Requesting grade consultation with CCS Chairperson.',
            'category' => 'academic_units',
            'workflow_type' => 'academic',
            'status' => 'pending',
            'priority' => 'medium',
            'submitted_at' => now(),
            'last_updated' => now(),
        ]);

        // CCS Chairperson can view, escalate to Dean, and Dean can approve and resolve
        $this->actingAs($this->ccsStaff, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$academicConcern->concern_id}")
            ->assertOk()
            ->assertJsonPath('concern.coordination.is_primary_owner', true);

        $this->actingAs($this->ccsStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$academicConcern->concern_id}/escalate", [
                'notes' => 'Forwarded to CCS Dean for academic decision.',
            ])
            ->assertOk()
            ->assertJsonPath('concern.status', 'escalated');

        $this->actingAs($this->ccsDean, 'sanctum')
            ->postJson("/api/dean/concerns/{$academicConcern->concern_id}/approve", [
                'notes' => 'Approved by CCS Dean.',
            ])
            ->assertOk()
            ->assertJsonPath('concern.status', 'approved');
    }

    public function test_legacy_unilateral_transfer_payload_is_rejected_and_cannot_bypass_handshake(): void
    {
        $concern = $this->createOperationalConcern($this->accountingDept->id);

        // Calling legacy /transfer with unilateral { target_category, target_id, notes } must be rejected (422) and preserve ownership
        $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfer", [
                'target_category' => 'operational_departments',
                'target_id' => $this->cashierDept->id,
                'notes' => 'Attempted unilateral legacy transfer.',
            ])
            ->assertStatus(422);

        $this->assertSame($this->accountingDept->id, (int) $concern->fresh()->dept_id);
        $this->assertSame(0, ConcernTransferRequest::where('concern_id', $concern->concern_id)->count());

        // Calling /transfer with valid handshake fields initiates a pending transfer request without changing ownership
        $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfer", [
                'to_dept_id' => $this->cashierDept->id,
                'transfer_reason' => 'Handshake transfer via legacy URL.',
                'handoff_summary' => 'Requires Cashier receipt confirmation.',
            ])
            ->assertCreated()
            ->assertJsonPath('transfer_request.status', 'pending');

        $this->assertSame($this->accountingDept->id, (int) $concern->fresh()->dept_id);
    }

    public function test_dean_acting_only_as_pco_approver_cannot_resolve_reply_change_status_or_override_priority_on_pco_concern(): void
    {
        config([
            'scms.pco.eligible_approving_academic_units' => [$this->ccsUnit->name],
        ]);

        $concern = $this->createOperationalConcern($this->pcoDept->id, [
            'title' => 'CCS Computer Lab Aircon Compressor Replacement',
            'status' => 'in_progress',
        ]);

        $reqRes = $this->actingAs($this->pcoStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals", [
                'approving_academic_unit_id' => $this->ccsUnit->id,
                'approval_reason' => 'Requires CCS Dean schedule clearance for lab downtime.',
                'proposed_work_description' => 'Replace compressor in CCS Lab 2.',
                'work_scope' => 'CCS Lab 2 aircon unit #1 during Saturday maintenance window.',
            ])
            ->assertCreated();

        $pcoReqId = $reqRes->json('pco_approval_request.id');

        // CCS Dean can view concern context as pco_approver
        $this->actingAs($this->ccsDean, 'sanctum')
            ->getJson("/api/dean/concerns/{$concern->concern_id}")
            ->assertOk()
            ->assertJsonPath('concern.coordination.access_mode', 'pco_approver')
            ->assertJsonPath('concern.coordination.is_primary_owner', false);

        // CCS Dean CANNOT resolve, approve (academic status), return, reject (academic status), reply to student, or override priority on PCO's concern
        $this->actingAs($this->ccsDean, 'sanctum')
            ->postJson("/api/dean/concerns/{$concern->concern_id}/resolve", ['notes' => 'Unauthorized dean resolve'])
            ->assertForbidden();

        $this->actingAs($this->ccsDean, 'sanctum')
            ->postJson("/api/dean/concerns/{$concern->concern_id}/approve", ['notes' => 'Unauthorized academic approve'])
            ->assertForbidden();

        $this->actingAs($this->ccsDean, 'sanctum')
            ->postJson("/api/dean/concerns/{$concern->concern_id}/return", ['notes' => 'Unauthorized academic return'])
            ->assertForbidden();

        $this->actingAs($this->ccsDean, 'sanctum')
            ->postJson("/api/dean/concerns/{$concern->concern_id}/reject", ['notes' => 'Unauthorized academic reject'])
            ->assertForbidden();

        $this->actingAs($this->ccsDean, 'sanctum')
            ->postJson("/api/dean/concerns/{$concern->concern_id}/reply", ['body' => 'Unauthorized student reply'])
            ->assertForbidden();

        $this->actingAs($this->ccsDean, 'sanctum')
            ->postJson("/api/dean/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'critical',
                'reason' => 'Unauthorized priority override by PCO approver Dean.',
            ])
            ->assertForbidden();

        // CCS Dean CAN post in internal department channel and CAN approve the PCO work request via the PCO approval endpoint
        $this->actingAs($this->ccsDean, 'sanctum')
            ->postJson("/api/dean/concerns/{$concern->concern_id}/internal-messages", [
                'body' => 'CCS Lab 2 will be cleared for Saturday 8 AM.',
                'message_type' => 'pco_approval_note',
            ])
            ->assertCreated();

        $this->actingAs($this->ccsDean, 'sanctum')
            ->postJson("/api/dean/concerns/{$concern->concern_id}/pco-approvals/{$pcoReqId}/approve", [
                'decision_notes' => 'Approved for Saturday maintenance window.',
            ])
            ->assertOk()
            ->assertJsonPath('pco_approval_request.status', 'approved')
            ->assertJsonPath('pco_approval_request.is_work_authorized', true);

        // Even after approving PCO work, CCS Dean still cannot resolve PCO's concern (403)
        $this->actingAs($this->ccsDean, 'sanctum')
            ->postJson("/api/dean/concerns/{$concern->concern_id}/resolve")
            ->assertForbidden();

        $this->assertNotSame('resolved', $concern->fresh()->status);
    }

    public function test_internal_department_message_contract_and_message_types(): void
    {
        $concern = $this->createOperationalConcern($this->accountingDept->id);

        // Missing 'body' (e.g. legacy 'message' key only) must fail validation (422)
        $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/internal-messages", [
                'message' => 'Wrong key without body.',
            ])
            ->assertStatus(422);

        // Valid post with 'body' and 'message_type' stores and returns expected fields
        $postRes = $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/internal-messages", [
                'body' => 'Verified student ledger balance for Q2.',
                'message_type' => 'collaboration_note',
            ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Verified student ledger balance for Q2.')
            ->assertJsonPath('data.message_type', 'collaboration_note');

        $listRes = $this->actingAs($this->accountingStaff, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}/internal-messages")
            ->assertOk();

        $messages = $listRes->json('internal_messages');
        $this->assertNotEmpty($messages);
        $this->assertSame('Verified student ledger balance for Q2.', $messages[0]['body']);
        $this->assertSame('collaboration_note', $messages[0]['message_type']);
    }

    public function test_concern_attachment_streaming_blocks_pending_transfer_and_pending_collaborator_and_allows_accepted_and_completed(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('attachments/999/receipt.pdf', 'PDF-RECEIPT-CONTENT');

        $concern = $this->createOperationalConcern($this->accountingDept->id, [
            'attachments' => ['attachments/999/receipt.pdf'],
        ]);

        // 1. Primary owner can stream concern attachment (200)
        $this->actingAs($this->accountingStaff, 'sanctum')
            ->get("/api/dept-staff/concerns/{$concern->concern_id}/attachments/0")
            ->assertOk();

        // 2. Initiate transfer to Cashier (pending_transfer_target) -> Cashier CANNOT stream attachment (403) and show() hides attachments
        $trRes = $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfers", [
                'to_dept_id' => $this->cashierDept->id,
                'transfer_reason' => 'Receipt verification.',
                'handoff_summary' => 'Check receipt after accepting.',
            ])
            ->assertCreated();

        $this->actingAs($this->cashierStaff, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}")
            ->assertOk()
            ->assertJsonPath('concern.attachments', []);

        $this->actingAs($this->cashierStaff, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}/attachments/0")
            ->assertForbidden();

        // Cancel pending transfer
        $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfers/{$trRes->json('transfer_request.id')}/cancel")
            ->assertOk();

        // 3. Invite Registrar as collaborator (pending_collaborator) -> Registrar CANNOT stream attachment before accepting (403)
        $colRes = $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/collaborations", [
                'collaborator_dept_id' => $this->registrarDept->id,
                'collaboration_reason' => 'Verify enrollment units.',
                'requested_assistance' => 'Check unit load.',
            ])
            ->assertCreated();
        $collabId = $colRes->json('collaboration.id');

        $this->actingAs($this->registrarStaff, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}")
            ->assertOk()
            ->assertJsonPath('concern.attachments', []);

        $this->actingAs($this->registrarStaff, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}/attachments/0")
            ->assertForbidden();

        // 4. Registrar accepts collaboration (active_collaborator) -> can stream attachment (200)
        $this->actingAs($this->registrarStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/collaborations/{$collabId}/accept")
            ->assertOk();

        $this->actingAs($this->registrarStaff, 'sanctum')
            ->get("/api/dept-staff/concerns/{$concern->concern_id}/attachments/0")
            ->assertOk();

        // 5. Registrar marks collaboration completed (completed_collaborator) -> retains historical read-only access to attachment (200)
        $this->actingAs($this->registrarStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/collaborations/{$collabId}/complete")
            ->assertOk();

        $this->actingAs($this->registrarStaff, 'sanctum')
            ->get("/api/dept-staff/concerns/{$concern->concern_id}/attachments/0")
            ->assertOk();
    }

    public function test_pco_attachment_serialization_never_exposes_raw_disk_paths_and_enforces_strict_access(): void
    {
        Storage::fake('local');

        $concern = $this->createOperationalConcern($this->pcoDept->id);
        $file = UploadedFile::fake()->create('electrical_diagram.pdf', 120, 'application/pdf');

        $createRes = $this->actingAs($this->pcoStaff, 'sanctum')
            ->post("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals", [
                'approving_dept_id' => $this->csuDept->id,
                'approval_reason' => 'Main breaker lockout approval.',
                'proposed_work_description' => 'Install new breaker panel.',
                'work_scope' => 'Building C ground floor main panel.',
                'attachments' => [$file],
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $rawCreateJson = $createRes->getContent();
        $this->assertStringNotContainsString('pco_approvals/', $rawCreateJson);
        $this->assertSame(0, $createRes->json('pco_approval_request.attachments.0.index'));
        $this->assertSame('electrical_diagram.pdf', $createRes->json('pco_approval_request.attachments.0.original_name'));

        $pcoReqId = $createRes->json('pco_approval_request.id');

        // PCO detail payload also never exposes raw disk paths
        $pcoShowRes = $this->actingAs($this->pcoStaff, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}")
            ->assertOk();
        $this->assertStringNotContainsString('pco_approvals/', $pcoShowRes->getContent());
        $this->assertSame('electrical_diagram.pdf', $pcoShowRes->json('concern.coordination.pco_approval_requests.0.attachments.0.original_name'));

        // Invite Accounting as a collaborator -> Accounting must NOT receive PCO approval deliberations or PCO attachment metadata
        $colRes = $this->actingAs($this->pcoStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/collaborations", [
                'collaborator_dept_id' => $this->accountingDept->id,
                'collaboration_reason' => 'Check asset tag.',
                'requested_assistance' => 'Confirm asset tag in registry.',
            ])
            ->assertCreated();

        $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/collaborations/{$colRes->json('collaboration.id')}/accept")
            ->assertOk();

        $collabShowRes = $this->actingAs($this->accountingStaff, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}")
            ->assertOk();
        $this->assertEmpty($collabShowRes->json('concern.coordination.pco_approval_requests'));

        // Collaborator cannot download PCO approval attachment (403)
        $this->actingAs($this->accountingStaff, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals/{$pcoReqId}/attachments/0")
            ->assertForbidden();

        // Out-of-bounds attachment index returns 404
        $this->actingAs($this->pcoStaff, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals/{$pcoReqId}/attachments/99")
            ->assertNotFound();
    }

    public function test_pco_approver_loses_active_posting_after_rejection_cancellation_or_superseding_and_stale_approvals_cannot_authorize_new_scope(): void
    {
        $concern = $this->createOperationalConcern($this->pcoDept->id);

        // 1. PCO submits Request #1 to CSU and then cancels it -> CSU has no access and cannot post internal messages
        $req1Res = $this->actingAs($this->pcoStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals", [
                'approving_dept_id' => $this->csuDept->id,
                'approval_reason' => 'Initial request to CSU.',
                'proposed_work_description' => 'Inspect gate lock.',
                'work_scope' => 'Gate 1.',
            ])
            ->assertCreated();
        $req1Id = $req1Res->json('pco_approval_request.id');

        $this->actingAs($this->pcoStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals/{$req1Id}/cancel")
            ->assertOk();

        $this->actingAs($this->csuStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/internal-messages", [
                'body' => 'CSU attempting to post after cancellation.',
            ])
            ->assertForbidden();

        // 2. PCO submits Request #2 to CSU and CSU rejects it -> CSU retains historical read-only access but CANNOT post new internal messages
        $req2Res = $this->actingAs($this->pcoStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals", [
                'approving_dept_id' => $this->csuDept->id,
                'approval_reason' => 'Second request to CSU.',
                'proposed_work_description' => 'Replace gate motor.',
                'work_scope' => 'Gate 1 motor assembly.',
            ])
            ->assertCreated();
        $req2Id = $req2Res->json('pco_approval_request.id');

        $this->actingAs($this->csuStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals/{$req2Id}/reject", [
                'decision_notes' => 'Rejected due to daytime traffic.',
            ])
            ->assertOk();

        $this->actingAs($this->csuStaff, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}/internal-messages")
            ->assertOk();

        $this->actingAs($this->csuStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/internal-messages", [
                'body' => 'CSU attempting to post after rejecting the request.',
            ])
            ->assertForbidden();

        // 3. PCO submits revised Request #3 to Accounting (after rejected Request #2) and Accounting approves it
        $req3Res = $this->actingAs($this->pcoStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals", [
                'approving_dept_id' => $this->accountingDept->id,
                'approval_reason' => 'Budget clearance for evening repair.',
                'proposed_work_description' => 'Evening gate motor replacement.',
                'work_scope' => 'Gate 1 motor assembly after 8 PM.',
            ])
            ->assertCreated();
        $req3Id = $req3Res->json('pco_approval_request.id');

        // Historical rejected Request #2 is preserved in DB with status='rejected', while Request #3 is current ('pending')
        $this->assertSame('rejected', PcoApprovalRequest::find($req2Id)->status);

        $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals/{$req3Id}/approve", [
                'decision_notes' => 'Approved for evening repair.',
            ])
            ->assertOk();

        // 4. Attempting to submit Request #4 after an approved request WITHOUT supersedes_request_id must fail (422)
        $this->actingAs($this->pcoStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals", [
                'approving_dept_id' => $this->csuDept->id,
                'approval_reason' => 'Expanded scope to Gate 2 without superseding.',
                'proposed_work_description' => 'Replace Gate 1 and Gate 2 motors.',
                'work_scope' => 'Gate 1 and Gate 2.',
            ])
            ->assertStatus(422);

        // 5. Submitting Request #4 WITH supersedes_request_id = $req3Id marks Request #3 'superseded', clears approved_pco_scope, blocks PCO resolution, and revokes Accounting's active posting privilege
        $req4Res = $this->actingAs($this->pcoStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/pco-approvals", [
                'approving_dept_id' => $this->csuDept->id,
                'supersedes_request_id' => $req3Id,
                'approval_reason' => 'Expanded scope to Gate 2.',
                'proposed_work_description' => 'Replace Gate 1 and Gate 2 motors.',
                'work_scope' => 'Gate 1 and Gate 2 after 9 PM.',
            ])
            ->assertCreated();

        $this->assertSame('superseded', PcoApprovalRequest::find($req3Id)->status);
        $this->assertFalse((bool) PcoApprovalRequest::find($req3Id)->is_work_authorized);

        $pcoView = $this->actingAs($this->pcoStaff, 'sanctum')
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}")
            ->assertOk();
        $this->assertNull($pcoView->json('concern.coordination.approved_pco_scope'));
        $this->assertSame('awaiting_department_approval', $pcoView->json('concern.coordination.pco_work_authorization_state'));

        // Superseded approver (Accounting) can no longer post active internal messages (403)
        $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/internal-messages", [
                'body' => 'Accounting attempting to post after Request #3 was superseded.',
            ])
            ->assertForbidden();
    }

    public function test_sao_option_a_structured_access_and_priority_override_boundaries(): void
    {
        $concern = $this->createOperationalConcern($this->accountingDept->id, [
            'priority' => 'low',
        ]);

        // 1. Pending transfer to SAO -> limited preview only; SAO CANNOT override priority (403)
        $tr1 = $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfers", [
                'to_dept_id' => $this->saoDept->id,
                'transfer_reason' => 'Student conduct inquiry.',
                'handoff_summary' => 'Please review student welfare aspect.',
            ])
            ->assertCreated();
        $tr1Id = $tr1->json('transfer_request.id');

        $this->actingAs($this->saoUser, 'sanctum')
            ->getJson("/api/sao/concerns/{$concern->concern_id}")
            ->assertOk()
            ->assertJsonPath('concern.coordination.access_mode', 'pending_transfer_target')
            ->assertJsonPath('concern.coordination.is_limited_preview', true);

        $this->actingAs($this->saoUser, 'sanctum')
            ->postJson("/api/sao/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'high',
                'reason' => 'Attempted override during pending transfer preview.',
            ])
            ->assertForbidden();

        // 2. SAO declines the transfer -> even though routing history notes contain "SAO", SAO must NOT retain access (403)
        $this->actingAs($this->saoUser, 'sanctum')
            ->postJson("/api/sao/concerns/{$concern->concern_id}/transfers/{$tr1Id}/decline", [
                'decision_notes' => 'Not an SAO matter; belongs to Accounting.',
            ])
            ->assertOk();

        $this->actingAs($this->saoUser, 'sanctum')
            ->getJson("/api/sao/concerns/{$concern->concern_id}")
            ->assertForbidden();

        $this->actingAs($this->saoUser, 'sanctum')
            ->postJson("/api/sao/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'high',
                'reason' => 'Attempted override after declining transfer.',
            ])
            ->assertForbidden();

        // 3. Cancelled transfer to SAO -> must NOT grant SAO access (403)
        $tr2 = $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfers", [
                'to_dept_id' => $this->saoDept->id,
                'transfer_reason' => 'Mistaken transfer to SAO.',
                'handoff_summary' => 'Will cancel immediately.',
            ])
            ->assertCreated();
        $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfers/{$tr2->json('transfer_request.id')}/cancel")
            ->assertOk();

        $this->actingAs($this->saoUser, 'sanctum')
            ->getJson("/api/sao/concerns/{$concern->concern_id}")
            ->assertForbidden();

        // 4. Accepted transfer to SAO -> SAO becomes primary owner and CAN override priority; then SAO transfers away to Cashier and Cashier accepts -> SAO loses access (403)
        $tr3 = $this->actingAs($this->accountingStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfers", [
                'to_dept_id' => $this->saoDept->id,
                'transfer_reason' => 'Confirmed SAO welfare case.',
                'handoff_summary' => 'Transferring to SAO.',
            ])
            ->assertCreated();

        $this->actingAs($this->saoUser, 'sanctum')
            ->postJson("/api/sao/concerns/{$concern->concern_id}/transfers/{$tr3->json('transfer_request.id')}/accept")
            ->assertOk();

        $this->actingAs($this->saoUser, 'sanctum')
            ->postJson("/api/sao/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'high',
                'reason' => 'SAO primary owner elevating priority.',
            ])
            ->assertOk();

        $trAway = $this->actingAs($this->saoUser, 'sanctum')
            ->postJson("/api/sao/concerns/{$concern->concern_id}/transfers", [
                'to_dept_id' => $this->cashierDept->id,
                'transfer_reason' => 'Welfare cleared; Cashier handles refund.',
                'handoff_summary' => 'Proceed with student refund.',
            ])
            ->assertCreated();

        $this->actingAs($this->cashierStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/transfers/{$trAway->json('transfer_request.id')}/accept")
            ->assertOk();

        // After transferring ownership away, SAO no longer has access merely as historical sender (403)
        $this->actingAs($this->saoUser, 'sanctum')
            ->getJson("/api/sao/concerns/{$concern->concern_id}")
            ->assertForbidden();

        $this->actingAs($this->saoUser, 'sanctum')
            ->postJson("/api/sao/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'critical',
                'reason' => 'Attempted override after transferring ownership away.',
            ])
            ->assertForbidden();

        // 5. Completed collaboration -> SAO retains historical read-only access (200) but CANNOT override priority (403)
        $colRes = $this->actingAs($this->cashierStaff, 'sanctum')
            ->postJson("/api/dept-staff/concerns/{$concern->concern_id}/collaborations", [
                'collaborator_dept_id' => $this->saoDept->id,
                'collaboration_reason' => 'Need SAO endorsement note.',
                'requested_assistance' => 'Confirm student organization membership.',
            ])
            ->assertCreated();
        $collabId = $colRes->json('collaboration.id');

        // Pending collaboration cannot override priority
        $this->actingAs($this->saoUser, 'sanctum')
            ->postJson("/api/sao/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'critical',
                'reason' => 'Attempted override while collaboration is still pending.',
            ])
            ->assertForbidden();

        // Accept and then complete collaboration
        $this->actingAs($this->saoUser, 'sanctum')
            ->postJson("/api/sao/concerns/{$concern->concern_id}/collaborations/{$collabId}/accept")
            ->assertOk();

        $this->actingAs($this->saoUser, 'sanctum')
            ->postJson("/api/sao/concerns/{$concern->concern_id}/collaborations/{$collabId}/complete")
            ->assertOk();

        $this->actingAs($this->saoUser, 'sanctum')
            ->getJson("/api/sao/concerns/{$concern->concern_id}")
            ->assertOk()
            ->assertJsonPath('concern.coordination.access_mode', 'completed_collaborator');

        $this->actingAs($this->saoUser, 'sanctum')
            ->postJson("/api/sao/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'critical',
                'reason' => 'Attempted override after collaboration completed.',
            ])
            ->assertForbidden();
    }
}
