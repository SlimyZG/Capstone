<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Concern;
use App\Models\Department;
use App\Models\User;
use App\Services\ResolutionPredictionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PredictiveResolutionTimeTest extends TestCase
{
    use RefreshDatabase;

    private AcademicUnit $ccsUnit;
    private AcademicUnit $cbaUnit;
    private Department $edpDept;
    private Department $registrarDept;
    private Department $saoDept;
    private User $student;
    private User $otherStudent;
    private User $edpStaff;
    private User $registrarStaff;
    private User $ccsStaff;
    private User $ccsDean;
    private User $cbaDean;
    private User $saoUser;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-04-20 12:00:00'));

        $this->ccsUnit = AcademicUnit::create([
            'name' => 'College of Computer Studies',
            'code' => 'CCS',
        ]);

        $this->cbaUnit = AcademicUnit::create([
            'name' => 'College of Business Administration',
            'code' => 'CBA',
        ]);

        $this->edpDept = Department::create([
            'name'        => 'EDP Office',
            'code'        => 'EDP',
            'description' => 'Electronic Data Processing',
        ]);

        $this->registrarDept = Department::create([
            'name'        => 'Registrar Office',
            'code'        => 'REG',
            'description' => 'University Registrar',
        ]);

        $this->saoDept = Department::create([
            'name'        => 'Student Affairs Office (SAO)',
            'code'        => 'SAO',
            'description' => 'Student Affairs',
        ]);

        $this->student = User::factory()->create([
            'role'              => 'student',
            'academic_unit_id'  => $this->ccsUnit->id,
            'student_id_number' => '20230001',
        ]);

        $this->otherStudent = User::factory()->create([
            'role'              => 'student',
            'academic_unit_id'  => $this->cbaUnit->id,
            'student_id_number' => '20230002',
        ]);

        $this->edpStaff = User::factory()->create([
            'role'    => 'dept_staff',
            'dept_id' => $this->edpDept->id,
        ]);

        $this->registrarStaff = User::factory()->create([
            'role'    => 'dept_staff',
            'dept_id' => $this->registrarDept->id,
        ]);

        $this->ccsStaff = User::factory()->create([
            'role'             => 'dept_staff',
            'academic_unit_id' => $this->ccsUnit->id,
        ]);

        $this->ccsDean = User::factory()->create([
            'role'             => 'dean',
            'academic_unit_id' => $this->ccsUnit->id,
        ]);

        $this->cbaDean = User::factory()->create([
            'role'             => 'dean',
            'academic_unit_id' => $this->cbaUnit->id,
        ]);

        $this->saoUser = User::factory()->create([
            'role'    => 'sao',
            'dept_id' => $this->saoDept->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Helper to seed a resolved historical concern with exact duration in hours.
     */
    private function seedResolvedConcern(array $overrides, float $durationHours): Concern
    {
        $submittedAt = Carbon::now()->subDays(20)->subHours((int) round($durationHours));
        $resolvedAt = $submittedAt->copy()->addSeconds((int) round($durationHours * 3600));

        return Concern::create(array_merge([
            'student_id'        => $this->student->id,
            'academic_unit_id'  => $this->ccsUnit->id,
            'dept_id'           => $this->edpDept->id,
            'target_type'       => 'department',
            'title'             => 'Historical Resolved Concern ' . uniqid(),
            'description'       => 'Historical resolved concern description.',
            'category'          => 'operational_departments',
            'issue_category'    => 'IT and Technology',
            'issue_subcategory' => 'Portal Access',
            'workflow_type'     => 'non_academic',
            'status'            => 'resolved',
            'priority'          => 'high',
            'submitted_at'      => $submittedAt,
            'resolved_at'       => $resolvedAt,
            'last_updated'      => $resolvedAt,
        ], $overrides));
    }

    /**
     * 1. Prediction with sufficient historical records.
     */
    public function test_prediction_with_sufficient_historical_records(): void
    {
        $this->seedResolvedConcern(['title' => 'Portal issue 1'], 10.0);
        $this->seedResolvedConcern(['title' => 'Portal issue 2'], 14.0);
        $this->seedResolvedConcern(['title' => 'Portal issue 3'], 18.0);
        $this->seedResolvedConcern(['title' => 'Portal issue 4'], 22.0);

        $target = Concern::create([
            'student_id'        => $this->student->id,
            'academic_unit_id'  => $this->ccsUnit->id,
            'dept_id'           => $this->edpDept->id,
            'target_type'       => 'department',
            'title'             => 'Cannot log into student portal',
            'description'       => 'My portal credentials are rejected.',
            'category'          => 'operational_departments',
            'issue_category'    => 'IT and Technology',
            'issue_subcategory' => 'Portal Access',
            'workflow_type'     => 'non_academic',
            'status'            => 'pending',
            'priority'          => 'high',
            'submitted_at'      => Carbon::now()->subHours(2),
            'last_updated'      => Carbon::now()->subHours(2),
        ]);

        $prediction = app(ResolutionPredictionService::class)->predict($target);

        $this->assertTrue($prediction['available']);
        $this->assertSame('dept_category_subcategory_priority', $prediction['tier']);
        $this->assertSame(4, $prediction['sample_size']);
        // Median of [10, 14, 18, 22] is 16.0 hours
        $this->assertEqualsWithDelta(16.0, $prediction['estimated_hours'], 0.05);
        $this->assertEqualsWithDelta(16.0, $prediction['mean_hours'], 0.05);
        $this->assertSame('moderate', $prediction['confidence_level']);
        $this->assertNotNull($prediction['estimated_resolved_at']);
        $this->assertStringContainsString('estimate', strtolower($prediction['disclaimer']));
    }

    /**
     * 2. Prediction with insufficient historical records.
     */
    public function test_prediction_with_insufficient_historical_records(): void
    {
        // Only 1 historical resolved record (below min_sample_size=3 and min_baseline_sample_size=2)
        $this->seedResolvedConcern(['title' => 'Lone historical case'], 12.0);

        $target = Concern::create([
            'student_id'        => $this->student->id,
            'academic_unit_id'  => $this->ccsUnit->id,
            'dept_id'           => $this->edpDept->id,
            'target_type'       => 'department',
            'title'             => 'New concern with sparse history',
            'description'       => 'Testing insufficient data behavior.',
            'category'          => 'operational_departments',
            'issue_category'    => 'IT and Technology',
            'issue_subcategory' => 'Portal Access',
            'workflow_type'     => 'non_academic',
            'status'            => 'pending',
            'priority'          => 'high',
            'submitted_at'      => Carbon::now(),
            'last_updated'      => Carbon::now(),
        ]);

        $prediction = app(ResolutionPredictionService::class)->predict($target);

        $this->assertFalse($prediction['available']);
        $this->assertSame('insufficient_data', $prediction['tier']);
        $this->assertSame('insufficient_data', $prediction['confidence_level']);
        $this->assertNull($prediction['estimated_hours']);
        $this->assertNull($prediction['estimated_resolved_at']);
    }

    /**
     * 3. Same category and priority matching.
     */
    public function test_same_category_and_priority_matching(): void
    {
        // High priority IT concerns take ~8h
        $this->seedResolvedConcern(['title' => 'IT High 1', 'priority' => 'high', 'issue_subcategory' => 'Wi-Fi'], 6.0);
        $this->seedResolvedConcern(['title' => 'IT High 2', 'priority' => 'high', 'issue_subcategory' => 'Network'], 8.0);
        $this->seedResolvedConcern(['title' => 'IT High 3', 'priority' => 'high', 'issue_subcategory' => 'Hardware'], 10.0);

        // Low priority IT concerns take ~48h
        $this->seedResolvedConcern(['title' => 'IT Low 1', 'priority' => 'low', 'issue_subcategory' => 'Software'], 40.0);
        $this->seedResolvedConcern(['title' => 'IT Low 2', 'priority' => 'low', 'issue_subcategory' => 'Email'], 48.0);
        $this->seedResolvedConcern(['title' => 'IT Low 3', 'priority' => 'low', 'issue_subcategory' => 'Account'], 56.0);

        $highTarget = Concern::create([
            'student_id'        => $this->student->id,
            'academic_unit_id'  => $this->ccsUnit->id,
            'dept_id'           => $this->edpDept->id,
            'target_type'       => 'department',
            'title'             => 'High priority network issue',
            'description'       => 'Lab switch failure.',
            'category'          => 'operational_departments',
            'issue_category'    => 'IT and Technology',
            'issue_subcategory' => 'Other IT',
            'workflow_type'     => 'non_academic',
            'status'            => 'pending',
            'priority'          => 'high',
            'submitted_at'      => Carbon::now(),
            'last_updated'      => Carbon::now(),
        ]);

        $lowTarget = Concern::create([
            'student_id'        => $this->student->id,
            'academic_unit_id'  => $this->ccsUnit->id,
            'dept_id'           => $this->edpDept->id,
            'target_type'       => 'department',
            'title'             => 'Low priority software question',
            'description'       => 'Optional tool installation.',
            'category'          => 'operational_departments',
            'issue_category'    => 'IT and Technology',
            'issue_subcategory' => 'Other IT',
            'workflow_type'     => 'non_academic',
            'status'            => 'pending',
            'priority'          => 'low',
            'submitted_at'      => Carbon::now(),
            'last_updated'      => Carbon::now(),
        ]);

        $predictor = app(ResolutionPredictionService::class);
        $highPred = $predictor->predict($highTarget);
        $lowPred = $predictor->predict($lowTarget);

        $this->assertTrue($highPred['available']);
        $this->assertSame('dept_category_priority', $highPred['tier']);
        $this->assertEqualsWithDelta(8.0, $highPred['estimated_hours'], 0.05);

        $this->assertTrue($lowPred['available']);
        $this->assertSame('dept_category_priority', $lowPred['tier']);
        $this->assertEqualsWithDelta(48.0, $lowPred['estimated_hours'], 0.05);
    }

    /**
     * 4. Department-specific historical estimates.
     */
    public function test_department_specific_historical_estimates(): void
    {
        // EDP resolves Administrative/Document concerns in ~12h
        $this->seedResolvedConcern([
            'title'             => 'EDP Doc 1',
            'dept_id'           => $this->edpDept->id,
            'issue_category'    => 'Administrative and Registrar',
            'issue_subcategory' => 'Records',
            'priority'          => 'medium',
        ], 10.0);
        $this->seedResolvedConcern([
            'title'             => 'EDP Doc 2',
            'dept_id'           => $this->edpDept->id,
            'issue_category'    => 'Administrative and Registrar',
            'issue_subcategory' => 'Records',
            'priority'          => 'medium',
        ], 12.0);
        $this->seedResolvedConcern([
            'title'             => 'EDP Doc 3',
            'dept_id'           => $this->edpDept->id,
            'issue_category'    => 'Administrative and Registrar',
            'issue_subcategory' => 'Records',
            'priority'          => 'medium',
        ], 14.0);

        // Registrar resolves Administrative/Document concerns in ~36h
        $this->seedResolvedConcern([
            'title'             => 'Reg Doc 1',
            'dept_id'           => $this->registrarDept->id,
            'issue_category'    => 'Administrative and Registrar',
            'issue_subcategory' => 'Records',
            'priority'          => 'medium',
        ], 32.0);
        $this->seedResolvedConcern([
            'title'             => 'Reg Doc 2',
            'dept_id'           => $this->registrarDept->id,
            'issue_category'    => 'Administrative and Registrar',
            'issue_subcategory' => 'Records',
            'priority'          => 'medium',
        ], 36.0);
        $this->seedResolvedConcern([
            'title'             => 'Reg Doc 3',
            'dept_id'           => $this->registrarDept->id,
            'issue_category'    => 'Administrative and Registrar',
            'issue_subcategory' => 'Records',
            'priority'          => 'medium',
        ], 40.0);

        $edpConcern = Concern::create([
            'student_id'        => $this->student->id,
            'academic_unit_id'  => $this->ccsUnit->id,
            'dept_id'           => $this->edpDept->id,
            'target_type'       => 'department',
            'title'             => 'EDP record request',
            'description'       => 'Need system record check.',
            'category'          => 'operational_departments',
            'issue_category'    => 'Administrative and Registrar',
            'issue_subcategory' => 'Records',
            'workflow_type'     => 'non_academic',
            'status'            => 'pending',
            'priority'          => 'medium',
            'submitted_at'      => Carbon::now(),
            'last_updated'      => Carbon::now(),
        ]);

        $regConcern = Concern::create([
            'student_id'        => $this->student->id,
            'academic_unit_id'  => $this->ccsUnit->id,
            'dept_id'           => $this->registrarDept->id,
            'target_type'       => 'department',
            'title'             => 'Registrar record request',
            'description'       => 'Need transcript verification.',
            'category'          => 'operational_departments',
            'issue_category'    => 'Administrative and Registrar',
            'issue_subcategory' => 'Records',
            'workflow_type'     => 'non_academic',
            'status'            => 'pending',
            'priority'          => 'medium',
            'submitted_at'      => Carbon::now(),
            'last_updated'      => Carbon::now(),
        ]);

        $predictor = app(ResolutionPredictionService::class);
        $this->assertEqualsWithDelta(12.0, $predictor->predict($edpConcern)['estimated_hours'], 0.05);
        $this->assertEqualsWithDelta(36.0, $predictor->predict($regConcern)['estimated_hours'], 0.05);
    }

    /**
     * 5. Fallback hierarchy behavior (Tier 1 -> Tier 2 -> Tier 3 -> Tier 4 -> Tier 5).
     */
    public function test_fallback_hierarchy_behavior(): void
    {
        $predictor = app(ResolutionPredictionService::class);

        // Seed 3 records in Registrar with category='Facilities and Maintenance', priority='medium'
        $this->seedResolvedConcern([
            'title'             => 'Fac 1',
            'dept_id'           => $this->registrarDept->id,
            'issue_category'    => 'Facilities and Maintenance',
            'issue_subcategory' => 'Aircon',
            'priority'          => 'medium',
        ], 20.0);
        $this->seedResolvedConcern([
            'title'             => 'Fac 2',
            'dept_id'           => $this->registrarDept->id,
            'issue_category'    => 'Facilities and Maintenance',
            'issue_subcategory' => 'Lighting',
            'priority'          => 'medium',
        ], 24.0);
        $this->seedResolvedConcern([
            'title'             => 'Fac 3',
            'dept_id'           => $this->registrarDept->id,
            'issue_category'    => 'Facilities and Maintenance',
            'issue_subcategory' => 'Seating',
            'priority'          => 'low',
        ], 28.0);

        // Target in Registrar with same category='Facilities and Maintenance' but priority='critical':
        // Tier 1 (dept+cat+subcat+prio) = 0
        // Tier 2 (dept+cat+prio) = 0
        // Tier 3 (dept+cat) = 3 -> matches Tier 3!
        $tier3Target = Concern::create([
            'student_id'        => $this->student->id,
            'academic_unit_id'  => $this->ccsUnit->id,
            'dept_id'           => $this->registrarDept->id,
            'target_type'       => 'department',
            'title'             => 'Registrar facility emergency',
            'description'       => 'Water leak.',
            'category'          => 'operational_departments',
            'issue_category'    => 'Facilities and Maintenance',
            'issue_subcategory' => 'Plumbing',
            'workflow_type'     => 'non_academic',
            'status'            => 'pending',
            'priority'          => 'critical',
            'submitted_at'      => Carbon::now(),
            'last_updated'      => Carbon::now(),
        ]);

        $tier3Pred = $predictor->predict($tier3Target);
        $this->assertTrue($tier3Pred['available']);
        $this->assertSame('dept_category', $tier3Pred['tier']);
        $this->assertEqualsWithDelta(24.0, $tier3Pred['estimated_hours'], 0.05);

        // Add one more 'medium' Facilities record in SAO so institution-wide 'Facilities and Maintenance' + 'medium' = 3
        $this->seedResolvedConcern([
            'title'             => 'Fac 4 SAO',
            'dept_id'           => $this->saoDept->id,
            'issue_category'    => 'Facilities and Maintenance',
            'issue_subcategory' => 'Lighting',
            'priority'          => 'medium',
        ], 30.0);

        // Target in EDP with 'Facilities and Maintenance' + 'medium' (EDP has 0 Facilities records, so Tier 1/2/3 fail, Tier 4 succeeds!)
        $tier4Target = Concern::create([
            'student_id'        => $this->student->id,
            'academic_unit_id'  => $this->ccsUnit->id,
            'dept_id'           => $this->edpDept->id,
            'target_type'       => 'department',
            'title'             => 'EDP lighting repair',
            'description'       => 'Bulb replacement.',
            'category'          => 'operational_departments',
            'issue_category'    => 'Facilities and Maintenance',
            'issue_subcategory' => 'Lighting',
            'workflow_type'     => 'non_academic',
            'status'            => 'pending',
            'priority'          => 'medium',
            'submitted_at'      => Carbon::now(),
            'last_updated'      => Carbon::now(),
        ]);

        $tier4Pred = $predictor->predict($tier4Target);
        $this->assertTrue($tier4Pred['available']);
        $this->assertSame('category_priority', $tier4Pred['tier']);
        $this->assertEqualsWithDelta(24.0, $tier4Pred['estimated_hours'], 0.05);

        // Target in EDP with a completely new category 'Financial and Scholarship' + 'medium' -> falls back to Tier 5 priority baseline
        $tier5Target = Concern::create([
            'student_id'        => $this->student->id,
            'academic_unit_id'  => $this->ccsUnit->id,
            'dept_id'           => $this->edpDept->id,
            'target_type'       => 'department',
            'title'             => 'Scholarship inquiry',
            'description'       => 'Stipend schedule.',
            'category'          => 'operational_departments',
            'issue_category'    => 'Financial and Scholarship',
            'issue_subcategory' => 'Scholarship',
            'workflow_type'     => 'non_academic',
            'status'            => 'pending',
            'priority'          => 'medium',
            'submitted_at'      => Carbon::now(),
            'last_updated'      => Carbon::now(),
        ]);

        $tier5Pred = $predictor->predict($tier5Target);
        $this->assertTrue($tier5Pred['available']);
        $this->assertSame('priority_baseline', $tier5Pred['tier']);
        $this->assertSame('low', $tier5Pred['confidence_level']);
    }

    /**
     * 6. Invalid/negative historical resolution durations and synthetic test records excluded.
     */
    public function test_invalid_negative_and_synthetic_historical_records_excluded(): void
    {
        // 3 valid records (10h, 12h, 14h -> median 12h)
        $this->seedResolvedConcern(['title' => 'Valid 1'], 10.0);
        $this->seedResolvedConcern(['title' => 'Valid 2'], 12.0);
        $this->seedResolvedConcern(['title' => 'Valid 3'], 14.0);

        // Invalid: resolved_at BEFORE submitted_at (negative duration)
        Concern::create([
            'student_id'        => $this->student->id,
            'academic_unit_id'  => $this->ccsUnit->id,
            'dept_id'           => $this->edpDept->id,
            'target_type'       => 'department',
            'title'             => 'Negative duration anomaly',
            'description'       => 'Corrupted timestamp.',
            'category'          => 'operational_departments',
            'issue_category'    => 'IT and Technology',
            'issue_subcategory' => 'Portal Access',
            'workflow_type'     => 'non_academic',
            'status'            => 'resolved',
            'priority'          => 'high',
            'submitted_at'      => Carbon::now()->subDays(5),
            'resolved_at'       => Carbon::now()->subDays(10),
            'last_updated'      => Carbon::now()->subDays(5),
        ]);

        // Invalid: resolved_at NULL despite status='resolved'
        Concern::create([
            'student_id'        => $this->student->id,
            'academic_unit_id'  => $this->ccsUnit->id,
            'dept_id'           => $this->edpDept->id,
            'target_type'       => 'department',
            'title'             => 'Missing resolved_at anomaly',
            'description'       => 'Null resolved_at.',
            'category'          => 'operational_departments',
            'issue_category'    => 'IT and Technology',
            'issue_subcategory' => 'Portal Access',
            'workflow_type'     => 'non_academic',
            'status'            => 'resolved',
            'priority'          => 'high',
            'submitted_at'      => Carbon::now()->subDays(5),
            'resolved_at'       => null,
            'last_updated'      => Carbon::now()->subDays(5),
        ]);

        // Synthetic record with SYNTH: prefix
        $this->seedResolvedConcern(['title' => 'SYNTH: Browser QA Synthetic Concern'], 500.0);

        $predictor = app(ResolutionPredictionService::class);
        $dataset = $predictor->getCleanHistoricalDataset();

        $this->assertCount(3, $dataset);
        foreach ($dataset as $row) {
            $this->assertGreaterThan(0, $row['duration_hours']);
            $this->assertStringNotContainsString('SYNTH:', $row['issue_category'] ?? '');
            // Ensure no student PII is stored in the cached cohort
            $this->assertArrayNotHasKey('student_id', $row);
            $this->assertArrayNotHasKey('student_name', $row);
            $this->assertArrayNotHasKey('title', $row);
        }
    }

    /**
     * 7. Unresolved concerns excluded from completed-resolution calculations.
     */
    public function test_unresolved_concerns_excluded_from_completed_resolution_calculations(): void
    {
        $this->seedResolvedConcern(['title' => 'Resolved 1'], 8.0);
        $this->seedResolvedConcern(['title' => 'Resolved 2'], 10.0);
        $this->seedResolvedConcern(['title' => 'Resolved 3'], 12.0);

        // Unresolved concerns (pending, in_progress, escalated, withdrawn) even if resolved_at is accidentally set
        foreach (['pending', 'in_progress', 'escalated', 'withdrawn'] as $status) {
            Concern::create([
                'student_id'        => $this->student->id,
                'academic_unit_id'  => $this->ccsUnit->id,
                'dept_id'           => $this->edpDept->id,
                'target_type'       => 'department',
                'title'             => "Unresolved {$status} concern",
                'description'       => 'Still open or withdrawn.',
                'category'          => 'operational_departments',
                'issue_category'    => 'IT and Technology',
                'issue_subcategory' => 'Portal Access',
                'workflow_type'     => 'non_academic',
                'status'            => $status,
                'priority'          => 'high',
                'submitted_at'      => Carbon::now()->subDays(10),
                'resolved_at'       => Carbon::now()->subDays(1),
                'last_updated'      => Carbon::now()->subDays(1),
            ]);
        }

        $predictor = app(ResolutionPredictionService::class);
        $dataset = $predictor->getCleanHistoricalDataset();

        $this->assertCount(3, $dataset);
    }

    /**
     * 8. Prediction changes dynamically after priority changes (including Dean priority override).
     */
    public function test_prediction_changes_after_priority_override(): void
    {
        // Academic CCS medium priority concerns take ~48h
        $this->seedResolvedConcern([
            'title'            => 'CCS Med 1',
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => null,
            'category'         => 'academic_units',
            'issue_category'   => 'Academic and Curriculum',
            'priority'         => 'medium',
        ], 44.0);
        $this->seedResolvedConcern([
            'title'            => 'CCS Med 2',
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => null,
            'category'         => 'academic_units',
            'issue_category'   => 'Academic and Curriculum',
            'priority'         => 'medium',
        ], 48.0);
        $this->seedResolvedConcern([
            'title'            => 'CCS Med 3',
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => null,
            'category'         => 'academic_units',
            'issue_category'   => 'Academic and Curriculum',
            'priority'         => 'medium',
        ], 52.0);

        // Academic CCS critical priority concerns take ~4h
        $this->seedResolvedConcern([
            'title'            => 'CCS Crit 1',
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => null,
            'category'         => 'academic_units',
            'issue_category'   => 'Academic and Curriculum',
            'priority'         => 'critical',
        ], 3.0);
        $this->seedResolvedConcern([
            'title'            => 'CCS Crit 2',
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => null,
            'category'         => 'academic_units',
            'issue_category'   => 'Academic and Curriculum',
            'priority'         => 'critical',
        ], 4.0);
        $this->seedResolvedConcern([
            'title'            => 'CCS Crit 3',
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => null,
            'category'         => 'academic_units',
            'issue_category'   => 'Academic and Curriculum',
            'priority'         => 'critical',
        ], 5.0);

        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => null,
            'target_type'      => 'dean',
            'title'            => 'Grade computation discrepancy',
            'description'      => 'Final grade missing lab component.',
            'category'         => 'academic_units',
            'issue_category'   => 'Academic and Curriculum',
            'workflow_type'    => 'academic',
            'status'           => 'pending',
            'priority'         => 'medium',
            'submitted_at'     => Carbon::now()->subHour(),
            'last_updated'     => Carbon::now()->subHour(),
        ]);

        // Initial prediction as medium priority (~48h)
        $initialRes = $this->actingAs($this->ccsDean)
            ->getJson("/api/dean/concerns/{$concern->concern_id}")
            ->assertOk();
        $this->assertEqualsWithDelta(48.0, $initialRes->json('concern.prediction.estimated_hours'), 0.05);

        // Dean overrides priority to critical -> prediction recalculates to ~4h
        $overrideRes = $this->actingAs($this->ccsDean)
            ->postJson("/api/dean/concerns/{$concern->concern_id}/priority-override", [
                'priority' => 'critical',
                'reason'   => 'Graduation clearance deadline is tomorrow.',
            ])
            ->assertOk();

        $this->assertEqualsWithDelta(4.0, $overrideRes->json('concern.prediction.estimated_hours'), 0.05);
    }

    /**
     * 9. Prediction changes after category or department changes.
     */
    public function test_prediction_changes_after_category_or_department_changes(): void
    {
        // EDP IT concerns take ~6h
        $this->seedResolvedConcern([
            'title'          => 'EDP IT 1',
            'dept_id'        => $this->edpDept->id,
            'issue_category' => 'IT and Technology',
            'priority'       => 'high',
        ], 5.0);
        $this->seedResolvedConcern([
            'title'          => 'EDP IT 2',
            'dept_id'        => $this->edpDept->id,
            'issue_category' => 'IT and Technology',
            'priority'       => 'high',
        ], 6.0);
        $this->seedResolvedConcern([
            'title'          => 'EDP IT 3',
            'dept_id'        => $this->edpDept->id,
            'issue_category' => 'IT and Technology',
            'priority'       => 'high',
        ], 7.0);

        // Registrar Administrative concerns take ~30h
        $this->seedResolvedConcern([
            'title'          => 'Reg Admin 1',
            'dept_id'        => $this->registrarDept->id,
            'issue_category' => 'Administrative and Registrar',
            'priority'       => 'high',
        ], 28.0);
        $this->seedResolvedConcern([
            'title'          => 'Reg Admin 2',
            'dept_id'        => $this->registrarDept->id,
            'issue_category' => 'Administrative and Registrar',
            'priority'       => 'high',
        ], 30.0);
        $this->seedResolvedConcern([
            'title'          => 'Reg Admin 3',
            'dept_id'        => $this->registrarDept->id,
            'issue_category' => 'Administrative and Registrar',
            'priority'       => 'high',
        ], 32.0);

        $concern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => $this->edpDept->id,
            'target_type'    => 'department',
            'title'          => 'Misrouted concern',
            'description'    => 'Initially routed to EDP.',
            'category'       => 'operational_departments',
            'issue_category' => 'IT and Technology',
            'workflow_type'  => 'non_academic',
            'status'         => 'pending',
            'priority'       => 'high',
            'submitted_at'   => Carbon::now(),
            'last_updated'   => Carbon::now(),
        ]);

        $predictor = app(ResolutionPredictionService::class);
        $this->assertEqualsWithDelta(6.0, $predictor->predict($concern)['estimated_hours'], 0.05);

        // Update department and category -> prediction recalculates to 30h
        $concern->update([
            'dept_id'        => $this->registrarDept->id,
            'issue_category' => 'Administrative and Registrar',
        ]);

        $this->assertEqualsWithDelta(30.0, $predictor->predict($concern->fresh())['estimated_hours'], 0.05);
    }

    /**
     * 10. Student access restrictions.
     */
    public function test_student_access_restrictions_on_predictions(): void
    {
        $this->seedResolvedConcern(['title' => 'Hist 1'], 10.0);
        $this->seedResolvedConcern(['title' => 'Hist 2'], 12.0);
        $this->seedResolvedConcern(['title' => 'Hist 3'], 14.0);

        $ownConcern = Concern::create([
            'student_id'        => $this->student->id,
            'academic_unit_id'  => $this->ccsUnit->id,
            'dept_id'           => $this->edpDept->id,
            'target_type'       => 'department',
            'title'             => 'My student portal concern',
            'description'       => 'Cannot view grades.',
            'category'          => 'operational_departments',
            'issue_category'    => 'IT and Technology',
            'issue_subcategory' => 'Portal Access',
            'workflow_type'     => 'non_academic',
            'status'            => 'pending',
            'priority'          => 'high',
            'submitted_at'      => Carbon::now(),
            'last_updated'      => Carbon::now(),
        ]);

        $otherConcern = Concern::create([
            'student_id'        => $this->otherStudent->id,
            'academic_unit_id'  => $this->cbaUnit->id,
            'dept_id'           => $this->edpDept->id,
            'target_type'       => 'department',
            'title'             => 'Other student concern',
            'description'       => 'Private details.',
            'category'          => 'operational_departments',
            'issue_category'    => 'IT and Technology',
            'issue_subcategory' => 'Portal Access',
            'workflow_type'     => 'non_academic',
            'status'            => 'pending',
            'priority'          => 'high',
            'submitted_at'      => Carbon::now(),
            'last_updated'      => Carbon::now(),
        ]);

        // Student can view sanitized prediction on their own concern
        $res = $this->actingAs($this->student)
            ->getJson("/api/concerns/{$ownConcern->concern_id}")
            ->assertOk();

        $this->assertTrue($res->json('concern.prediction.available'));
        $this->assertEqualsWithDelta(12.0, $res->json('concern.prediction.estimated_hours'), 0.05);
        // Student view does not expose internal tier diagnostics or override fields
        $this->assertArrayNotHasKey('tier', $res->json('concern.prediction'));
        $this->assertArrayNotHasKey('priority_override_reason', $res->json('concern'));

        // Student cannot view another student's concern
        $this->actingAs($this->student)
            ->getJson("/api/concerns/{$otherConcern->concern_id}")
            ->assertNotFound();
    }

    /**
     * 11. Department Staff access restrictions.
     */
    public function test_department_staff_access_restrictions(): void
    {
        $this->seedResolvedConcern(['title' => 'EDP 1', 'dept_id' => $this->edpDept->id], 8.0);
        $this->seedResolvedConcern(['title' => 'EDP 2', 'dept_id' => $this->edpDept->id], 10.0);
        $this->seedResolvedConcern(['title' => 'EDP 3', 'dept_id' => $this->edpDept->id], 12.0);

        $edpConcern = Concern::create([
            'student_id'        => $this->student->id,
            'academic_unit_id'  => $this->ccsUnit->id,
            'dept_id'           => $this->edpDept->id,
            'target_type'       => 'department',
            'title'             => 'EDP assigned concern',
            'description'       => 'EDP scope.',
            'category'          => 'operational_departments',
            'issue_category'    => 'IT and Technology',
            'issue_subcategory' => 'Portal Access',
            'workflow_type'     => 'non_academic',
            'status'            => 'pending',
            'priority'          => 'high',
            'submitted_at'      => Carbon::now(),
            'last_updated'      => Carbon::now(),
        ]);

        $registrarConcern = Concern::create([
            'student_id'        => $this->student->id,
            'academic_unit_id'  => $this->ccsUnit->id,
            'dept_id'           => $this->registrarDept->id,
            'target_type'       => 'department',
            'title'             => 'Registrar assigned concern',
            'description'       => 'Registrar scope.',
            'category'          => 'operational_departments',
            'issue_category'    => 'Administrative and Registrar',
            'issue_subcategory' => 'Transcript',
            'workflow_type'     => 'non_academic',
            'status'            => 'pending',
            'priority'          => 'medium',
            'submitted_at'      => Carbon::now(),
            'last_updated'      => Carbon::now(),
        ]);

        // EDP staff can view prediction on EDP concern
        $this->actingAs($this->edpStaff)
            ->getJson("/api/dept-staff/concerns/{$edpConcern->concern_id}")
            ->assertOk()
            ->assertJsonPath('concern.prediction.available', true);

        // EDP staff cannot view Registrar concern
        $this->actingAs($this->edpStaff)
            ->getJson("/api/dept-staff/concerns/{$registrarConcern->concern_id}")
            ->assertNotFound();

        // EDP staff report includes resolution_analytics scoped only to EDP
        $reportRes = $this->actingAs($this->edpStaff)
            ->getJson('/api/dept-staff/reports')
            ->assertOk();

        $this->assertSame(3, $reportRes->json('resolution_analytics.summary.sample_size'));
    }

    /**
     * 12. Dean academic-unit restrictions.
     */
    public function test_dean_academic_unit_restrictions(): void
    {
        $this->seedResolvedConcern([
            'title'            => 'CCS Resolved 1',
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => null,
            'category'         => 'academic_units',
            'issue_category'   => 'Academic and Curriculum',
            'priority'         => 'high',
        ], 18.0);
        $this->seedResolvedConcern([
            'title'            => 'CCS Resolved 2',
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => null,
            'category'         => 'academic_units',
            'issue_category'   => 'Academic and Curriculum',
            'priority'         => 'high',
        ], 20.0);
        $this->seedResolvedConcern([
            'title'            => 'CCS Resolved 3',
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => null,
            'category'         => 'academic_units',
            'issue_category'   => 'Academic and Curriculum',
            'priority'         => 'high',
        ], 22.0);

        $ccsConcern = Concern::create([
            'student_id'       => $this->student->id,
            'academic_unit_id' => $this->ccsUnit->id,
            'dept_id'          => null,
            'target_type'      => 'dean',
            'title'            => 'CCS escalated concern',
            'description'      => 'CCS unit.',
            'category'         => 'academic_units',
            'issue_category'   => 'Academic and Curriculum',
            'workflow_type'    => 'academic',
            'status'           => 'pending',
            'priority'         => 'high',
            'submitted_at'     => Carbon::now(),
            'last_updated'     => Carbon::now(),
        ]);

        $cbaConcern = Concern::create([
            'student_id'       => $this->otherStudent->id,
            'academic_unit_id' => $this->cbaUnit->id,
            'dept_id'          => null,
            'target_type'      => 'dean',
            'title'            => 'CBA concern',
            'description'      => 'CBA unit.',
            'category'         => 'academic_units',
            'issue_category'   => 'Academic and Curriculum',
            'workflow_type'    => 'academic',
            'status'           => 'pending',
            'priority'         => 'high',
            'submitted_at'     => Carbon::now(),
            'last_updated'     => Carbon::now(),
        ]);

        // CCS Dean can view CCS concern prediction
        $this->actingAs($this->ccsDean)
            ->getJson("/api/dean/concerns/{$ccsConcern->concern_id}")
            ->assertOk()
            ->assertJsonPath('concern.prediction.available', true);

        // CCS Dean cannot view CBA concern
        $this->actingAs($this->ccsDean)
            ->getJson("/api/dean/concerns/{$cbaConcern->concern_id}")
            ->assertNotFound();

        // CCS Dean report includes resolution_analytics scoped to CCS
        $deanReport = $this->actingAs($this->ccsDean)
            ->getJson('/api/dean/reports')
            ->assertOk();

        $this->assertSame(3, $deanReport->json('resolution_analytics.summary.sample_size'));
    }

    /**
     * 13. SAO aggregate and individual-detail Option A restrictions.
     */
    public function test_sao_aggregate_and_individual_option_a_restrictions(): void
    {
        $this->seedResolvedConcern(['title' => 'SAO 1', 'dept_id' => $this->saoDept->id], 12.0);
        $this->seedResolvedConcern(['title' => 'SAO 2', 'dept_id' => $this->saoDept->id], 16.0);
        $this->seedResolvedConcern(['title' => 'SAO 3', 'dept_id' => $this->saoDept->id], 20.0);

        $saoAssigned = Concern::create([
            'student_id'        => $this->student->id,
            'academic_unit_id'  => $this->ccsUnit->id,
            'dept_id'           => $this->saoDept->id,
            'target_type'       => 'department',
            'title'             => 'Student organization accreditation',
            'description'       => 'Assigned to SAO.',
            'category'          => 'operational_departments',
            'issue_category'    => 'IT and Technology',
            'issue_subcategory' => 'Portal Access',
            'workflow_type'     => 'non_academic',
            'status'            => 'pending',
            'priority'          => 'high',
            'submitted_at'      => Carbon::now(),
            'last_updated'      => Carbon::now(),
        ]);

        $nonSaoConcern = Concern::create([
            'student_id'        => $this->student->id,
            'academic_unit_id'  => $this->ccsUnit->id,
            'dept_id'           => $this->edpDept->id,
            'target_type'       => 'department',
            'title'             => 'EDP internal concern',
            'description'       => 'Not assigned to SAO.',
            'category'          => 'operational_departments',
            'issue_category'    => 'IT and Technology',
            'issue_subcategory' => 'Portal Access',
            'workflow_type'     => 'non_academic',
            'status'            => 'pending',
            'priority'          => 'high',
            'submitted_at'      => Carbon::now(),
            'last_updated'      => Carbon::now(),
        ]);

        // SAO receives aggregate resolution_analytics in oversight endpoint
        $oversightRes = $this->actingAs($this->saoUser)
            ->getJson('/api/sao/oversight')
            ->assertOk();

        $this->assertSame(3, $oversightRes->json('resolution_analytics.summary.sample_size'));
        $this->assertEqualsWithDelta(16.0, $oversightRes->json('resolution_analytics.summary.median_resolution_hours'), 0.05);
        $this->assertNotEmpty($oversightRes->json('resolution_analytics.by_priority'));
        $this->assertNotEmpty($oversightRes->json('resolution_analytics.by_department'));

        // SAO can view individual prediction for SAO-assigned concern
        $this->actingAs($this->saoUser)
            ->getJson("/api/sao/concerns/{$saoAssigned->concern_id}")
            ->assertOk()
            ->assertJsonPath('concern.prediction.available', true);

        // SAO is blocked (403 Forbidden) from viewing non-SAO individual concern details
        $this->actingAs($this->saoUser)
            ->getJson("/api/sao/concerns/{$nonSaoConcern->concern_id}")
            ->assertStatus(403);
    }

    /**
     * 14. Existing SLA and escalation regression test:
     * Official SLA deadlines are preserved and never replaced by historical predictions.
     */
    public function test_official_sla_deadline_is_preserved_alongside_prediction(): void
    {
        // Historical resolution takes ~10 hours, while Official SLA for 'high' priority is 48 hours
        $this->seedResolvedConcern(['title' => 'Fast 1', 'priority' => 'high'], 8.0);
        $this->seedResolvedConcern(['title' => 'Fast 2', 'priority' => 'high'], 10.0);
        $this->seedResolvedConcern(['title' => 'Fast 3', 'priority' => 'high'], 12.0);

        $concern = Concern::create([
            'student_id'        => $this->student->id,
            'academic_unit_id'  => $this->ccsUnit->id,
            'dept_id'           => $this->edpDept->id,
            'target_type'       => 'department',
            'title'             => 'High priority concern with both SLA and prediction',
            'description'       => 'Verify SLA target remains 48h while prediction is 10h.',
            'category'          => 'operational_departments',
            'issue_category'    => 'IT and Technology',
            'issue_subcategory' => 'Portal Access',
            'workflow_type'     => 'non_academic',
            'status'            => 'pending',
            'priority'          => 'high',
            'submitted_at'      => Carbon::now(),
            'last_updated'      => Carbon::now(),
        ]);

        $res = $this->actingAs($this->edpStaff)
            ->getJson("/api/dept-staff/concerns/{$concern->concern_id}")
            ->assertOk();

        // Official SLA target is still 72 hours for high priority (from config/sla.php)
        $this->assertEquals(72.0, $res->json('concern.sla.target_hours'));
        // Historical prediction is 10 hours (from ResolutionPredictionService)
        $this->assertEqualsWithDelta(10.0, $res->json('concern.prediction.estimated_hours'), 0.05);
        $this->assertNotEquals(
            $res->json('concern.sla.deadline_at'),
            $res->json('concern.prediction.estimated_resolved_at')
        );
    }

    /**
     * 15. Newly resolved concern automatically flushes cache and updates historical predictions.
     */
    public function test_resolving_concern_flushes_cache_and_updates_predictions(): void
    {
        $this->seedResolvedConcern(['title' => 'Init 1'], 10.0);
        $this->seedResolvedConcern(['title' => 'Init 2'], 20.0);

        $pending = Concern::create([
            'student_id'        => $this->student->id,
            'academic_unit_id'  => $this->ccsUnit->id,
            'dept_id'           => $this->edpDept->id,
            'target_type'       => 'department',
            'title'             => 'Third concern about to be resolved',
            'description'       => 'Will complete Tier 1 sample threshold of 3.',
            'category'          => 'operational_departments',
            'issue_category'    => 'IT and Technology',
            'issue_subcategory' => 'Portal Access',
            'workflow_type'     => 'non_academic',
            'status'            => 'in_progress',
            'priority'          => 'high',
            'submitted_at'      => Carbon::now()->subHours(30),
            'last_updated'      => Carbon::now()->subHours(1),
        ]);

        $newTarget = Concern::create([
            'student_id'        => $this->student->id,
            'academic_unit_id'  => $this->ccsUnit->id,
            'dept_id'           => $this->edpDept->id,
            'target_type'       => 'department',
            'title'             => 'Fourth concern awaiting prediction',
            'description'       => 'Checks if resolving the 3rd concern promotes Tier 5 to Tier 1.',
            'category'          => 'operational_departments',
            'issue_category'    => 'IT and Technology',
            'issue_subcategory' => 'Portal Access',
            'workflow_type'     => 'non_academic',
            'status'            => 'pending',
            'priority'          => 'high',
            'submitted_at'      => Carbon::now(),
            'last_updated'      => Carbon::now(),
        ]);

        $predictor = app(ResolutionPredictionService::class);
        // Before resolving $pending, only 2 resolved records exist -> Tier 5b institution baseline
        $before = $predictor->predict($newTarget);
        $this->assertSame('institution_baseline', $before['tier']);

        // Staff resolves $pending via API (takes 30h)
        $this->actingAs($this->edpStaff)
            ->postJson("/api/dept-staff/concerns/{$pending->concern_id}/resolve", [
                'notes' => 'Resolved portal account lock.',
            ])
            ->assertOk();

        // Cache was automatically invalidated on Concern::saved -> now 3 resolved records exist -> Tier 1!
        $after = $predictor->predict($newTarget);
        $this->assertSame('dept_category_subcategory_priority', $after['tier']);
        $this->assertSame(3, $after['sample_size']);
        $this->assertEqualsWithDelta(20.0, $after['estimated_hours'], 0.05);
    }

    /**
     * 16. Redesigned Reports endpoints return user-friendly analytics sections
     * (Section A summary, Section B activity_trend, Section C attention_required,
     * Section E by_category, Section G insights) while preserving strict role scope.
     */
    public function test_redesigned_reports_payload_sections_and_filters(): void
    {
        // Seed 3 resolved EDP concerns (IT and Technology)
        $this->seedResolvedConcern([
            'title'          => 'EDP Resolved 1',
            'dept_id'        => $this->edpDept->id,
            'issue_category' => 'IT and Technology',
            'priority'       => 'high',
        ], 8.0);
        $this->seedResolvedConcern([
            'title'          => 'EDP Resolved 2',
            'dept_id'        => $this->edpDept->id,
            'issue_category' => 'IT and Technology',
            'priority'       => 'high',
        ], 10.0);
        $this->seedResolvedConcern([
            'title'          => 'EDP Resolved 3',
            'dept_id'        => $this->edpDept->id,
            'issue_category' => 'Facilities and Maintenance',
            'priority'       => 'medium',
        ], 12.0);

        // Seed 1 overdue critical unresolved EDP concern
        Concern::create([
            'student_id'        => $this->student->id,
            'academic_unit_id'  => $this->ccsUnit->id,
            'dept_id'           => $this->edpDept->id,
            'target_type'       => 'department',
            'title'             => 'Overdue Critical Server Outage',
            'description'       => 'Campus Wi-Fi authentication server is down.',
            'category'          => 'operational_departments',
            'issue_category'    => 'IT and Technology',
            'issue_subcategory' => 'Network Outage',
            'workflow_type'     => 'non_academic',
            'status'            => 'pending',
            'priority'          => 'critical',
            'submitted_at'      => Carbon::now()->subHours(40),
            'last_updated'      => Carbon::now()->subHours(40),
        ]);

        // Seed 1 Registrar concern (should NOT appear in EDP staff report)
        Concern::create([
            'student_id'        => $this->student->id,
            'academic_unit_id'  => $this->ccsUnit->id,
            'dept_id'           => $this->registrarDept->id,
            'target_type'       => 'department',
            'title'             => 'Registrar Critical Unresolved',
            'description'       => 'Transcript delay.',
            'category'          => 'operational_departments',
            'issue_category'    => 'Administrative and Registrar',
            'workflow_type'     => 'non_academic',
            'status'            => 'pending',
            'priority'          => 'critical',
            'submitted_at'      => Carbon::now()->subHours(50),
            'last_updated'      => Carbon::now()->subHours(50),
        ]);

        $staffReport = $this->actingAs($this->edpStaff)
            ->getJson('/api/dept-staff/reports')
            ->assertOk();

        $this->assertSame(4, $staffReport->json('summary.total'));
        $this->assertSame(3, $staffReport->json('summary.resolved'));
        $this->assertSame(1, $staffReport->json('summary.unresolved'));
        $this->assertSame(1, $staffReport->json('summary.critical_unresolved'));
        $this->assertSame(1, $staffReport->json('summary.overdue'));
        $this->assertEqualsWithDelta(10.0, $staffReport->json('summary.median_resolution_hours'), 0.05);
        $this->assertNotEmpty($staffReport->json('by_category'));
        $this->assertNotEmpty($staffReport->json('activity_trend'));
        $this->assertNotEmpty($staffReport->json('activity_summary.explanation'));
        $this->assertSame(1, $staffReport->json('attention_required.critical_unresolved_count'));
        $this->assertCount(1, $staffReport->json('attention_required.items'));
        $this->assertSame('Overdue Critical Server Outage', $staffReport->json('attention_required.items.0.title'));
        $this->assertNotEmpty($staffReport->json('insights'));

        // Category filter on Department Staff Report
        $filteredStaffReport = $this->actingAs($this->edpStaff)
            ->getJson('/api/dept-staff/reports?category=' . urlencode('Facilities and Maintenance'))
            ->assertOk();

        $this->assertSame(1, $filteredStaffReport->json('summary.total'));
        $this->assertSame(1, $filteredStaffReport->json('resolution_analytics.summary.sample_size'));
    }
}
