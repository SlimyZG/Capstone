<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Concern;
use App\Models\ConcernRouting;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentsCsvAndPrintReportTest extends TestCase
{
    use RefreshDatabase;

    private AcademicUnit $ccsUnit;
    private AcademicUnit $marineUnit;
    private Department $itDept;
    private Department $cashierDept;
    private Department $saoDept;

    private User $ccsStudent;
    private User $otherStudent;
    private User $itStaff;
    private User $cashierStaff;
    private User $ccsAcademicStaff;
    private User $ccsDean;
    private User $marineDean;
    private User $saoUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ccsUnit = AcademicUnit::create([
            'code' => 'CCS',
            'name' => 'College of Computer Studies',
        ]);

        $this->marineUnit = AcademicUnit::create([
            'code' => 'CME',
            'name' => 'College of Marine Engineering',
        ]);

        $this->itDept = Department::create([
            'code' => 'MIS',
            'name' => 'IT Support / MIS Office',
        ]);

        $this->cashierDept = Department::create([
            'code' => 'CASH',
            'name' => 'Cashier & Accounting Office',
        ]);

        $this->saoDept = Department::create([
            'code' => 'SAO',
            'name' => 'Student Affairs Office (SAO)',
        ]);

        $this->ccsStudent = User::create([
            'name'              => 'Juan Dela Cruz',
            'email'             => 'juan.ccs@uclm.edu.ph',
            'password'          => Hash::make('password123'),
            'role'              => 'student',
            'student_id_number' => '2026-10001',
            'academic_unit_id'  => $this->ccsUnit->id,
        ]);

        $this->otherStudent = User::create([
            'name'              => 'Maria Santos',
            'email'             => 'maria.cme@uclm.edu.ph',
            'password'          => Hash::make('password123'),
            'role'              => 'student',
            'student_id_number' => '2026-10002',
            'academic_unit_id'  => $this->marineUnit->id,
        ]);

        $this->itStaff = User::create([
            'name'     => 'IT Staff Member',
            'email'    => 'it.staff@uclm.edu.ph',
            'password' => Hash::make('password123'),
            'role'     => 'dept_staff',
            'dept_id'  => $this->itDept->id,
        ]);

        $this->cashierStaff = User::create([
            'name'     => 'Cashier Staff Member',
            'email'    => 'cashier.staff@uclm.edu.ph',
            'password' => Hash::make('password123'),
            'role'     => 'dept_staff',
            'dept_id'  => $this->cashierDept->id,
        ]);

        $this->ccsAcademicStaff = User::create([
            'name'             => 'CCS Chairperson Staff',
            'email'            => 'ccs.staff@uclm.edu.ph',
            'password'         => Hash::make('password123'),
            'role'             => 'dept_staff',
            'academic_unit_id' => $this->ccsUnit->id,
        ]);

        $this->ccsDean = User::create([
            'name'             => 'Dean CCS',
            'email'            => 'dean.ccs@uclm.edu.ph',
            'password'         => Hash::make('password123'),
            'role'             => 'dean',
            'academic_unit_id' => $this->ccsUnit->id,
        ]);

        $this->marineDean = User::create([
            'name'             => 'Dean Marine',
            'email'            => 'dean.marine@uclm.edu.ph',
            'password'         => Hash::make('password123'),
            'role'             => 'dean',
            'academic_unit_id' => $this->marineUnit->id,
        ]);

        $this->saoUser = User::create([
            'name'     => 'SAO Director',
            'email'    => 'sao@uclm.edu.ph',
            'password' => Hash::make('password123'),
            'role'     => 'sao',
            'dept_id'  => $this->saoDept->id,
        ]);
    }

    // ─── PHASE 1: ATTACHMENT TESTS ───────────────────────────────────────────

    public function test_student_can_upload_and_view_own_image_and_video_attachments(): void
    {
        Storage::fake('local');

        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $image = UploadedFile::fake()->createWithContent('broken_monitor.png', $pngBytes);
        $video = UploadedFile::fake()->create('flickering_screen.mp4', 512, 'video/mp4');

        $response = $this->actingAs($this->ccsStudent)->postJson('/api/concerns', [
            'title'         => 'Lab 3 Monitor Flickering and Broken Display',
            'description'   => 'The monitor at workstation 5 in Lab 3 is flickering and has dead pixels.',
            'workflow_type' => 'non_academic',
            'dept_id'       => $this->itDept->id,
            'attachments'   => [$image, $video],
        ]);

        $response->assertStatus(201);
        $concernId = $response->json('concern.concern_id');
        $concern = Concern::findOrFail($concernId);

        $this->assertIsArray($concern->attachments);
        $this->assertCount(2, $concern->attachments);

        // Verify files are stored on the private 'local' disk (not public URLs)
        foreach ($concern->attachments as $storedPath) {
            $this->assertStringNotContainsString('/storage/', $storedPath);
            Storage::disk('local')->assertExists($storedPath);
        }

        // Student can stream image attachment (index 0)
        $imgRes = $this->actingAs($this->ccsStudent)
            ->get("/api/concerns/{$concernId}/attachments/0");
        $imgRes->assertStatus(200);
        $this->assertStringContainsString('image/png', (string) $imgRes->headers->get('Content-Type'));
        $this->assertStringContainsString('private', (string) $imgRes->headers->get('Cache-Control'));

        // Student can stream video attachment (index 1)
        $vidRes = $this->actingAs($this->ccsStudent)
            ->get("/api/concerns/{$concernId}/attachments/1");
        $vidRes->assertStatus(200);
        $this->assertStringContainsString('video/mp4', (string) $vidRes->headers->get('Content-Type'));
        $this->assertStringContainsString('bytes', (string) $vidRes->headers->get('Accept-Ranges'));
    }

    public function test_unauthorized_student_cannot_access_another_students_attachments(): void
    {
        Storage::fake('local');

        $path = UploadedFile::fake()->create('receipt.jpg', 64, 'image/jpeg')->storeAs('attachments/1', 'receipt.jpg', 'local');

        $concern = Concern::create([
            'student_id'       => $this->ccsStudent->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => $this->cashierDept->id,
            'target_type'      => 'department',
            'title'            => 'Tuition Receipt Inquiry',
            'description'      => 'Attached is my payment receipt.',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'pending',
            'attachments'      => [$path],
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        // Other student is forbidden
        $res = $this->actingAs($this->otherStudent)
            ->getJson("/api/concerns/{$concern->concern_id}/attachments/0");
        $res->assertStatus(403);

        // Unauthenticated guest is rejected
        $this->app['auth']->forgetGuards();
        $unauthRes = $this->getJson("/api/concerns/{$concern->concern_id}/attachments/0");
        $unauthRes->assertStatus(401);
    }

    public function test_department_staff_attachment_access_follows_department_restrictions(): void
    {
        Storage::fake('local');

        $path = UploadedFile::fake()->create('router_led.png', 64, 'image/png')->storeAs('attachments/10', 'router_led.png', 'local');

        $itConcern = Concern::create([
            'student_id'       => $this->ccsStudent->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => $this->itDept->id,
            'target_type'      => 'department',
            'title'            => 'Router Red Blinking Light',
            'description'      => 'Wi-Fi router has a red blinking light.',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'pending',
            'attachments'      => [$path],
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        // Assigned IT staff CAN access
        $this->actingAs($this->itStaff)
            ->get("/api/concerns/{$itConcern->concern_id}/attachments/0")
            ->assertStatus(200);

        $this->actingAs($this->itStaff)
            ->get("/api/dept-staff/concerns/{$itConcern->concern_id}/attachments/0")
            ->assertStatus(200);

        // Cashier staff CANNOT access IT concern attachment
        $this->actingAs($this->cashierStaff)
            ->getJson("/api/concerns/{$itConcern->concern_id}/attachments/0")
            ->assertStatus(403);

        // CCS academic staff CANNOT access non-academic IT department concern attachment
        $this->actingAs($this->ccsAcademicStaff)
            ->getJson("/api/concerns/{$itConcern->concern_id}/attachments/0")
            ->assertStatus(403);
    }

    public function test_dean_attachment_access_follows_academic_unit_and_workflow_restrictions(): void
    {
        Storage::fake('local');

        $path = UploadedFile::fake()->create('grade_sheet.png', 64, 'image/png')->storeAs('attachments/20', 'grade_sheet.png', 'local');

        // 1. Academic concern still pending at department level (not escalated to Dean)
        $deptLevelAcademic = Concern::create([
            'student_id'       => $this->ccsStudent->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => null,
            'target_type'      => 'department',
            'title'            => 'Pending Grade Inquiry',
            'description'      => 'Still under department chairperson review.',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'status'           => 'pending',
            'attachments'      => [$path],
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        // Dean cannot access department-level pending concern before escalation
        $this->actingAs($this->ccsDean)
            ->getJson("/api/concerns/{$deptLevelAcademic->concern_id}/attachments/0")
            ->assertStatus(403);

        // 2. Escalated to CCS Dean
        $deptLevelAcademic->update(['status' => 'escalated']);

        // CCS Dean CAN now access
        $this->actingAs($this->ccsDean)
            ->get("/api/concerns/{$deptLevelAcademic->concern_id}/attachments/0")
            ->assertStatus(200);

        // Marine Dean CANNOT access CCS concern
        $this->actingAs($this->marineDean)
            ->getJson("/api/concerns/{$deptLevelAcademic->concern_id}/attachments/0")
            ->assertStatus(403);
    }

    public function test_sao_attachment_access_follows_option_a_assigned_concern_restrictions(): void
    {
        Storage::fake('local');

        $path = UploadedFile::fake()->create('id_loss_affidavit.png', 64, 'image/png')->storeAs('attachments/30', 'id_loss_affidavit.png', 'local');

        // Non-SAO concern (assigned to Cashier)
        $cashierConcern = Concern::create([
            'student_id'       => $this->ccsStudent->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => $this->cashierDept->id,
            'target_type'      => 'department',
            'title'            => 'Cashier Assessment Issue',
            'description'      => 'Payment balance mismatch.',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'pending',
            'attachments'      => [$path],
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        // SAO is forbidden from accessing Cashier concern attachment (Option A)
        $this->actingAs($this->saoUser)
            ->getJson("/api/concerns/{$cashierConcern->concern_id}/attachments/0")
            ->assertStatus(403);

        // SAO-assigned concern
        $saoConcern = Concern::create([
            'student_id'       => $this->ccsStudent->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => $this->saoDept->id,
            'target_type'      => 'department',
            'title'            => 'Lost School ID Replacement',
            'description'      => 'Requesting replacement ID card.',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'pending',
            'attachments'      => [$path],
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        // SAO CAN access attachment of concern assigned to SAO
        $this->actingAs($this->saoUser)
            ->get("/api/concerns/{$saoConcern->concern_id}/attachments/0")
            ->assertStatus(200);
    }

    public function test_legacy_public_disk_attachments_and_missing_files_are_handled_safely(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        // Legacy file stored in 'public' disk with '/storage/attachments/...' path
        Storage::disk('public')->put('attachments/legacy_photo.jpg', 'fake-jpeg-bytes');

        $concern = Concern::create([
            'student_id'       => $this->ccsStudent->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => $this->itDept->id,
            'target_type'      => 'department',
            'title'            => 'Legacy Attachment Concern',
            'description'      => 'Testing backward compatibility.',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'pending',
            'attachments'      => [
                '/storage/attachments/legacy_photo.jpg',
                'attachments/missing_file_on_disk.png',
            ],
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        // Index 0 (legacy public disk) succeeds and returns image/jpeg
        $res0 = $this->actingAs($this->ccsStudent)
            ->get("/api/concerns/{$concern->concern_id}/attachments/0");
        $res0->assertStatus(200);
        $this->assertStringContainsString('image/jpeg', (string) $res0->headers->get('Content-Type'));

        // Index 1 (missing file on disk) returns safe 404 without internal storage paths
        $res1 = $this->actingAs($this->ccsStudent)
            ->getJson("/api/concerns/{$concern->concern_id}/attachments/1");
        $res1->assertStatus(404);
        $this->assertStringNotContainsString('storage_path', (string) $res1->getContent());

        // Index 99 (out of bounds) returns 404
        $res99 = $this->actingAs($this->ccsStudent)
            ->getJson("/api/concerns/{$concern->concern_id}/attachments/99");
        $res99->assertStatus(404);
    }

    // ─── PHASE 2: CSV EXPORT TESTS ───────────────────────────────────────────

    public function test_dept_staff_csv_export_succeeds_contains_expected_columns_and_sanitizes_formulas(): void
    {
        $concern = Concern::create([
            'student_id'         => $this->ccsStudent->id,
            'academic_unit_id'   => $this->ccsUnit->id,
            'dept_id'            => $this->itDept->id,
            'target_type'        => 'department',
            'title'              => '=CMD|\' /C calc\'!A0 Malicious Title, with comma',
            'description'        => "Line 1 description\nLine 2 with \"quotes\"",
            'ai_summary'         => 'Concise summary of the lab issue.',
            'category'           => 'operational_departments',
            'issue_category'     => 'IT and Technology',
            'issue_subcategory'  => 'Network / Internet',
            'workflow_type'      => 'non_academic',
            'priority'           => 'high',
            'status'             => 'resolved',
            'sentiment_label'    => 'negative',
            'is_anonymous'       => false,
            'resolution_summary' => 'Replaced damaged network switch in Lab 2.',
            'submitted_at'       => '2026-11-10 09:30:00',
            'last_updated'       => '2026-11-11 14:15:00',
            'resolved_at'        => '2026-11-11 14:15:00',
        ]);

        // Unrelated Cashier concern that must NOT appear in IT Staff CSV
        Concern::create([
            'student_id'       => $this->otherStudent->id,
            'academic_unit_id' => $this->marineUnit->id,
            'dept_id'          => $this->cashierDept->id,
            'target_type'      => 'department',
            'title'            => 'Confidential Cashier Concern',
            'description'      => 'Should not be exported to IT staff.',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'priority'         => 'low',
            'status'           => 'pending',
            'submitted_at'     => '2026-11-10 10:00:00',
            'last_updated'     => '2026-11-10 10:00:00',
        ]);

        $response = $this->actingAs($this->itStaff)
            ->get('/api/dept-staff/reports/export-csv');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));

        $csvContent = $response->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csvContent))));

        // Parse header row
        $headers = str_getcsv($lines[0]);
        $this->assertEquals([
            'Concern ID',
            'Title',
            'Description',
            'Summary',
            'Category',
            'Subcategory',
            'Priority',
            'Status',
            'Workflow Type',
            'Assigned Department / Unit',
            'Student',
            'Sentiment',
            'Submitted At',
            'Last Updated At',
            'Resolved At',
            'Resolution Details',
        ], $headers);

        // Verify IT concern is present and Cashier concern is absent
        $this->assertStringContainsString((string) $concern->concern_id, $csvContent);
        $this->assertStringNotContainsString('Confidential Cashier Concern', $csvContent);

        // Verify formula injection prefix ('=CMD...) and resolution details
        $this->assertStringContainsString("'=CMD", $csvContent);
        $this->assertStringContainsString('Replaced damaged network switch in Lab 2.', $csvContent);
        $this->assertStringContainsString('2026-11-10 09:30:00', $csvContent);
        $this->assertStringContainsString('2026-11-11 14:15:00', $csvContent);
    }

    public function test_csv_export_respects_all_selected_filters_and_handles_empty_results(): void
    {
        Concern::create([
            'student_id'       => $this->ccsStudent->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => $this->itDept->id,
            'target_type'      => 'department',
            'title'            => 'Matching October High Priority Concern',
            'description'      => 'Oct network outage.',
            'category'         => 'operational_departments',
            'issue_category'   => 'IT and Technology',
            'workflow_type'    => 'non_academic',
            'priority'         => 'high',
            'status'           => 'pending',
            'submitted_at'     => '2026-10-15 12:00:00',
            'last_updated'     => '2026-10-15 12:00:00',
        ]);

        Concern::create([
            'student_id'       => $this->ccsStudent->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => $this->itDept->id,
            'target_type'      => 'department',
            'title'            => 'Non-Matching November Low Priority Concern',
            'description'      => 'Nov minor request.',
            'category'         => 'operational_departments',
            'issue_category'   => 'Campus Facilities',
            'workflow_type'    => 'non_academic',
            'priority'         => 'low',
            'status'           => 'resolved',
            'submitted_at'     => '2026-11-12 12:00:00',
            'last_updated'     => '2026-11-12 12:00:00',
            'resolved_at'      => '2026-11-13 12:00:00',
        ]);

        // Filtered request matching only the October High Priority IT concern
        $filteredRes = $this->actingAs($this->itStaff)->get(
            '/api/dept-staff/reports/export-csv?date_from=2026-10-01&date_to=2026-10-31&priority=high&status=pending&category=' . urlencode('IT and Technology')
        );
        $filteredRes->assertStatus(200);
        $filteredCsv = $filteredRes->streamedContent();
        $this->assertStringContainsString('Matching October High Priority Concern', $filteredCsv);
        $this->assertStringNotContainsString('Non-Matching November Low Priority Concern', $filteredCsv);

        // Empty result filter returns valid CSV with headers only
        $emptyRes = $this->actingAs($this->itStaff)->get(
            '/api/dept-staff/reports/export-csv?date_from=2025-01-01&date_to=2025-01-31'
        );
        $emptyRes->assertStatus(200);
        $emptyLines = array_values(array_filter(explode("\n", trim($emptyRes->streamedContent()))));
        $this->assertCount(1, $emptyLines);
        $this->assertEquals('Concern ID', str_getcsv($emptyLines[0])[0]);
    }

    public function test_dean_and_sao_csv_exports_enforce_strict_role_scoping_and_reject_unauthorized_users(): void
    {
        // Escalated CCS academic concern (visible to CCS Dean)
        Concern::create([
            'student_id'       => $this->ccsStudent->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => null,
            'target_type'      => 'department',
            'title'            => 'CCS Escalated Syllabus Issue',
            'description'      => 'Escalated to CCS Dean.',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'priority'         => 'high',
            'status'           => 'escalated',
            'submitted_at'     => '2026-11-05 08:00:00',
            'last_updated'     => '2026-11-05 09:00:00',
        ]);

        // Escalated Marine academic concern (MUST NOT be visible to CCS Dean)
        Concern::create([
            'student_id'       => $this->otherStudent->id,
            'academic_unit_id' => $this->marineUnit->id,
            'dept_id'          => null,
            'target_type'      => 'department',
            'title'            => 'Marine Escalated Lab Issue',
            'description'      => 'Escalated to Marine Dean.',
            'category'         => 'academic_units',
            'workflow_type'    => 'academic',
            'priority'         => 'high',
            'status'           => 'escalated',
            'submitted_at'     => '2026-11-05 08:00:00',
            'last_updated'     => '2026-11-05 09:00:00',
        ]);

        // SAO-assigned concern
        Concern::create([
            'student_id'       => $this->ccsStudent->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => $this->saoDept->id,
            'target_type'      => 'department',
            'title'            => 'SAO Student Handbook Inquiry',
            'description'      => 'Assigned directly to SAO.',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'priority'         => 'medium',
            'status'           => 'in_progress',
            'submitted_at'     => '2026-11-06 10:00:00',
            'last_updated'     => '2026-11-06 10:00:00',
        ]);

        // 1. CCS Dean exports only CCS authorized concerns
        $deanRes = $this->actingAs($this->ccsDean)->get('/api/dean/reports/export-csv');
        $deanRes->assertStatus(200);
        $deanCsv = $deanRes->streamedContent();
        $this->assertStringContainsString('CCS Escalated Syllabus Issue', $deanCsv);
        $this->assertStringNotContainsString('Marine Escalated Lab Issue', $deanCsv);
        $this->assertStringNotContainsString('SAO Student Handbook Inquiry', $deanCsv);

        // 2. SAO exports only SAO-assigned concerns (Option A)
        $saoRes = $this->actingAs($this->saoUser)->get('/api/sao/reports/export-csv');
        $saoRes->assertStatus(200);
        $saoCsv = $saoRes->streamedContent();
        $this->assertStringContainsString('SAO Student Handbook Inquiry', $saoCsv);
        $this->assertStringNotContainsString('CCS Escalated Syllabus Issue', $saoCsv);
        $this->assertStringNotContainsString('Marine Escalated Lab Issue', $saoCsv);

        // 3. Students are forbidden from all CSV export endpoints
        $this->actingAs($this->ccsStudent)->getJson('/api/dept-staff/reports/export-csv')->assertStatus(403);
        $this->actingAs($this->ccsStudent)->getJson('/api/dean/reports/export-csv')->assertStatus(403);
        $this->actingAs($this->ccsStudent)->getJson('/api/sao/reports/export-csv')->assertStatus(403);
    }

    // ─── PHASE 3: PRINT / PDF REPORT DATA & CONSISTENCY TESTS ────────────────

    public function test_department_report_provides_complete_print_metadata_consistent_kpis_and_records(): void
    {
        $c1 = Concern::create([
            'student_id'       => $this->ccsStudent->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => $this->itDept->id,
            'target_type'      => 'department',
            'title'            => 'Critical Server Room Overheating',
            'description'      => 'AC unit in server room stopped working.',
            'ai_summary'       => 'Server room AC failure causing overheating.',
            'category'         => 'operational_departments',
            'issue_category'   => 'IT and Technology',
            'workflow_type'    => 'non_academic',
            'priority'         => 'critical',
            'status'           => 'in_progress',
            'submitted_at'     => '2026-11-10 08:00:00',
            'last_updated'     => '2026-11-10 09:00:00',
        ]);

        $c2 = Concern::create([
            'student_id'         => $this->ccsStudent->id,
            'academic_unit_id'   => $this->ccsUnit->id,
            'dept_id'            => $this->itDept->id,
            'target_type'        => 'department',
            'title'              => 'Lab 1 Printer Jam Resolved',
            'description'        => 'Cleared paper jam and replaced toner.',
            'category'           => 'operational_departments',
            'issue_category'     => 'IT and Technology',
            'workflow_type'      => 'non_academic',
            'priority'           => 'medium',
            'status'             => 'resolved',
            'resolution_summary' => 'Cleared jam and tested print queue.',
            'submitted_at'       => '2026-11-08 10:00:00',
            'last_updated'       => '2026-11-08 14:00:00',
            'resolved_at'        => '2026-11-08 14:00:00',
        ]);

        ConcernRouting::create([
            'concern_id'   => $c2->concern_id,
            'action'       => 'resolved',
            'performed_by' => $this->itStaff->id,
            'notes'        => 'Cleared jam and tested print queue.',
        ]);

        $res = $this->actingAs($this->itStaff)
            ->getJson('/api/dept-staff/reports?date_from=2026-11-01&date_to=2026-11-15');

        $res->assertStatus(200);

        // Verify institutional print report metadata
        $res->assertJsonPath('report_metadata.institution_name', 'University of Cebu Lapu-Lapu and Mandaue');
        $res->assertJsonPath('report_metadata.system_name', 'Student Concern Management System');
        $res->assertJsonPath('report_metadata.report_title', 'Department Concern Report');
        $res->assertJsonPath('report_metadata.assigned_scope', 'IT Support / MIS Office');
        $res->assertJsonPath('report_metadata.generated_by', 'IT Staff Member');

        // Verify KPI consistency with underlying concern records
        $res->assertJsonPath('summary.total', 2);
        $res->assertJsonPath('summary.resolved', 1);
        $res->assertJsonPath('summary.unresolved', 1);
        $res->assertJsonPath('summary.resolution_rate', 50);
        $res->assertJsonPath('summary.critical_unresolved', 1);

        // Verify concern records include resolution notes for the printable table
        $concerns = $res->json('concerns');
        $this->assertCount(2, $concerns);
        $resolvedRecord = collect($concerns)->firstWhere('concern_id', $c2->concern_id);
        $this->assertNotNull($resolvedRecord);
        $this->assertEquals('Cleared jam and tested print queue.', $resolvedRecord['resolution_notes']);

        // Verify empty report filter returns clean zeroed structures
        $emptyRes = $this->actingAs($this->itStaff)
            ->getJson('/api/dept-staff/reports?date_from=2024-01-01&date_to=2024-01-31');
        $emptyRes->assertStatus(200);
        $emptyRes->assertJsonPath('summary.total', 0);
        $emptyRes->assertJsonCount(0, 'concerns');
    }

    public function test_dean_report_print_metadata_and_report_authorization_boundaries(): void
    {
        Concern::create([
            'student_id'       => $this->ccsStudent->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => null,
            'target_type'      => 'dean',
            'title'            => 'Direct CCS Dean Curriculum Petition',
            'description'      => 'Requesting prerequisite waiver evaluation.',
            'category'         => 'academic_units',
            'issue_category'   => 'Academic Concerns',
            'workflow_type'    => 'academic',
            'priority'         => 'high',
            'status'           => 'pending',
            'submitted_at'     => '2026-11-10 09:00:00',
            'last_updated'     => '2026-11-10 09:00:00',
        ]);

        // Marine unit concern (must not leak into CCS Dean report)
        Concern::create([
            'student_id'       => $this->otherStudent->id,
            'academic_unit_id' => $this->marineUnit->id,
            'dept_id'          => null,
            'target_type'      => 'dean',
            'title'            => 'Marine Dean Simulator Request',
            'description'      => 'Bridge simulator scheduling.',
            'category'         => 'academic_units',
            'issue_category'   => 'Academic Concerns',
            'workflow_type'    => 'academic',
            'priority'         => 'medium',
            'status'           => 'pending',
            'submitted_at'     => '2026-11-10 09:00:00',
            'last_updated'     => '2026-11-10 09:00:00',
        ]);

        $deanRes = $this->actingAs($this->ccsDean)
            ->getJson('/api/dean/reports?date_from=2026-11-01&date_to=2026-11-15');
        $deanRes->assertStatus(200);
        $deanRes->assertJsonPath('report_metadata.institution_name', 'University of Cebu Lapu-Lapu and Mandaue');
        $deanRes->assertJsonPath('report_metadata.report_title', 'Academic Unit Concern Report');
        $deanRes->assertJsonPath('report_metadata.assigned_scope', 'College of Computer Studies');
        $deanRes->assertJsonPath('summary.total', 1);
        $deanRes->assertJsonCount(1, 'concerns');
        $this->assertEquals('Direct CCS Dean Curriculum Petition', $deanRes->json('concerns.0.title'));

        // Students and wrong roles are forbidden from report endpoints
        $this->actingAs($this->ccsStudent)->getJson('/api/dept-staff/reports')->assertStatus(403);
        $this->actingAs($this->ccsStudent)->getJson('/api/dean/reports')->assertStatus(403);
        $this->actingAs($this->itStaff)->getJson('/api/dean/reports')->assertStatus(403);
        $this->actingAs($this->ccsDean)->getJson('/api/dept-staff/reports')->assertStatus(403);
    }
}
