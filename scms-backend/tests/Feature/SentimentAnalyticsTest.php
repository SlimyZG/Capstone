<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Concern;
use App\Models\User;
use App\Services\GeminiSentimentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SentimentAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_unauthenticated_user_cannot_access_analytics(): void
    {
        $response = $this->getJson('/api/analytics/sentiment');
        $response->assertStatus(401);

        $response2 = $this->getJson('/api/analytics/trends');
        $response2->assertStatus(401);
    }

    public function test_student_is_forbidden_from_analytics(): void
    {
        $unit = AcademicUnit::create(['name' => 'College of Computer Studies']);

        $student = User::create([
            'name'              => 'Student User',
            'email'             => 'student@uclm.edu.ph',
            'student_id_number' => '12345678',
            'password'          => bcrypt('password'),
            'role'              => 'student',
            'academic_unit_id'  => $unit->id,
        ]);

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/analytics/sentiment')
            ->assertStatus(403);
    }

    public function test_dept_staff_can_access_sentiment_analytics_scoped(): void
    {
        $unit = AcademicUnit::create(['name' => 'College of Computer Studies']);

        $staff = User::create([
            'name'              => 'Staff User',
            'email'             => 'staff@uclm.edu.ph',
            'student_id_number' => '87654321',
            'password'          => bcrypt('password'),
            'role'              => 'dept_staff',
            'academic_unit_id'  => $unit->id,
        ]);

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/analytics/sentiment')
            ->assertStatus(200);
    }

    public function test_dean_can_access_sentiment_analytics_with_empty_data(): void
    {
        $unit = AcademicUnit::create(['name' => 'College of Computer Studies']);

        $dean = User::create([
            'name'              => 'Dean User',
            'email'             => 'dean@uclm.edu.ph',
            'student_id_number' => '99999999',
            'password'          => bcrypt('password'),
            'role'              => 'dean',
            'academic_unit_id'  => $unit->id,
        ]);

        $response = $this->actingAs($dean, 'sanctum')->getJson('/api/analytics/sentiment');

        $response->assertStatus(200)
            ->assertJson([
                'distribution' => [
                    'positive' => 0,
                    'neutral'  => 0,
                    'negative' => 0,
                    'urgent'   => 0,
                ],
                'flagged' => [],
                'trend'   => [],
                'themes'  => [],
            ]);
    }

    public function test_sentiment_distribution_separates_urgent_and_counts_accurately(): void
    {
        $unit = AcademicUnit::create(['name' => 'College of Computer Studies']);

        $dean = User::create([
            'name'              => 'Dean User',
            'email'             => 'dean@uclm.edu.ph',
            'student_id_number' => '99999999',
            'password'          => bcrypt('password'),
            'role'              => 'dean',
            'academic_unit_id'  => $unit->id,
        ]);

        $student = User::create([
            'name'              => 'John Doe',
            'email'             => 'john@uclm.edu.ph',
            'student_id_number' => '11112222',
            'password'          => bcrypt('password'),
            'role'              => 'student',
            'academic_unit_id'  => $unit->id,
        ]);

        // Create concerns with different sentiments
        Concern::create([
            'student_id'       => $student->id,
            'academic_unit_id' => $unit->id,
            'target_type'      => 'department',
            'title'            => 'Good experience with library',
            'description'      => 'Everything was helpful and smooth.',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'pending',
            'sentiment_score'  => 0.8,
            'sentiment_label'  => 'positive',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        Concern::create([
            'student_id'       => $student->id,
            'academic_unit_id' => $unit->id,
            'target_type'      => 'department',
            'title'            => 'Slow wifi connection in lab',
            'description'      => 'Internet keeps disconnecting constantly.',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'pending',
            'sentiment_score'  => -0.6,
            'sentiment_label'  => 'negative',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        Concern::create([
            'student_id'       => $student->id,
            'academic_unit_id' => $unit->id,
            'target_type'      => 'department',
            'title'            => 'Urgent safety hazard',
            'description'      => 'Exposed electrical wires in hallway.',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'pending',
            'priority'         => 'critical',
            'sentiment_score'  => -0.9,
            'sentiment_label'  => 'urgent',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        $response = $this->actingAs($dean, 'sanctum')->getJson('/api/analytics/sentiment');

        $response->assertStatus(200);
        $data = $response->json();

        // Urgent must NOT be counted as negative
        $this->assertEquals(1, $data['distribution']['positive']);
        $this->assertEquals(1, $data['distribution']['negative']);
        $this->assertEquals(1, $data['distribution']['urgent']);
        $this->assertEquals(0, $data['distribution']['neutral']);

        // Flagged must contain the urgent/critical concern
        $this->assertCount(1, $data['flagged']);
        $this->assertEquals('Urgent safety hazard', $data['flagged'][0]['title']);

        // Themes must extract frequent keywords while excluding student names (John, Doe)
        $themeWords = array_column($data['themes'], 'text');
        $this->assertNotContains('john', $themeWords);
        $this->assertNotContains('doe', $themeWords);
        $this->assertNotContains('the', $themeWords);
        $this->assertContains('wifi', $themeWords);
    }

    public function test_dean_is_scoped_to_their_academic_unit(): void
    {
        $unitA = AcademicUnit::create(['name' => 'College of Computer Studies']);
        $unitB = AcademicUnit::create(['name' => 'College of Engineering']);

        $deanA = User::create([
            'name'              => 'Dean CCS',
            'email'             => 'dean_ccs@uclm.edu.ph',
            'student_id_number' => '10000001',
            'password'          => bcrypt('password'),
            'role'              => 'dean',
            'academic_unit_id'  => $unitA->id,
        ]);

        $studentB = User::create([
            'name'              => 'Student Eng',
            'email'             => 'student_eng@uclm.edu.ph',
            'student_id_number' => '20000002',
            'password'          => bcrypt('password'),
            'role'              => 'student',
            'academic_unit_id'  => $unitB->id,
        ]);

        // Create concern in Unit B
        Concern::create([
            'student_id'       => $studentB->id,
            'academic_unit_id' => $unitB->id,
            'target_type'      => 'department',
            'title'            => 'Engineering Lab issue',
            'description'      => 'Lab equipment problem.',
            'category'         => 'operational_departments',
            'workflow_type'    => 'non_academic',
            'status'           => 'pending',
            'sentiment_score'  => -0.5,
            'sentiment_label'  => 'negative',
            'submitted_at'     => now(),
            'last_updated'     => now(),
        ]);

        // Dean A should see 0 concerns
        $response = $this->actingAs($deanA, 'sanctum')->getJson('/api/analytics/sentiment');
        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('distribution.negative'));
    }

    public function test_trends_endpoint_returns_stats_even_if_narrative_fails(): void
    {
        $unit = AcademicUnit::create(['name' => 'College of Computer Studies']);

        $dean = User::create([
            'name'              => 'Dean CCS',
            'email'             => 'dean_ccs@uclm.edu.ph',
            'student_id_number' => '10000001',
            'password'          => bcrypt('password'),
            'role'              => 'dean',
            'academic_unit_id'  => $unit->id,
        ]);

        // Mock GeminiSentimentService to simulate offline/failure
        $this->mock(GeminiSentimentService::class, function ($mock) {
            $mock->shouldReceive('generateTrendNarrative')->andReturn(null);
        });

        $response = $this->actingAs($dean, 'sanctum')->getJson('/api/analytics/trends');

        $response->assertStatus(200);
        $this->assertArrayHasKey('stats', $response->json());
        $this->assertArrayHasKey('total_concerns_last_30_days', $response->json('stats'));
    }

    public function test_word_frequency_and_phrase_analytics_extracts_real_words_and_phrases_and_strips_pii(): void
    {
        $unit = AcademicUnit::create(['name' => 'College of Computer Studies']);

        $dean = User::create([
            'name'              => 'Maria Clara',
            'email'             => 'mariaclara@uclm.edu.ph',
            'student_id_number' => '90001111',
            'password'          => bcrypt('password'),
            'role'              => 'dean',
            'academic_unit_id'  => $unit->id,
        ]);

        $student = User::create([
            'name'              => 'crisostomo ibarra',
            'email'             => 'crisostomo.ibarra@uclm.edu.ph',
            'student_id_number' => '20269988',
            'password'          => bcrypt('password'),
            'role'              => 'student',
            'academic_unit_id'  => $unit->id,
        ]);

        Concern::create([
            'student_id'        => $student->id,
            'academic_unit_id'  => $unit->id,
            'target_type'       => 'department',
            'title'             => 'Slow internet connection on laboratory pc',
            'description'       => 'Crisostomo Ibarra 20269988 reports that the internet connection on the laboratory pc disconnects during programming class.',
            'category'          => 'academic',
            'issue_category'    => 'IT and Technology',
            'issue_subcategory' => 'Network / Internet',
            'workflow_type'     => 'academic',
            'status'            => 'pending',
            'priority'          => 'high',
            'sentiment_score'   => -0.65,
            'sentiment_label'   => 'negative',
            'submitted_at'      => now(),
            'last_updated'      => now(),
        ]);

        Concern::create([
            'student_id'        => $student->id,
            'academic_unit_id'  => $unit->id,
            'target_type'       => 'department',
            'title'             => 'Unstable internet connection in laboratory pc room',
            'description'       => 'The classroom projector works well, but the internet connection on every laboratory pc is down.',
            'category'          => 'academic',
            'issue_category'    => 'IT and Technology',
            'issue_subcategory' => 'Network / Internet',
            'workflow_type'     => 'academic',
            'status'            => 'in_progress',
            'priority'          => 'high',
            'sentiment_score'   => -0.55,
            'sentiment_label'   => 'negative',
            'submitted_at'      => now(),
            'last_updated'      => now(),
        ]);

        Concern::create([
            'student_id'        => $student->id,
            'academic_unit_id'  => $unit->id,
            'target_type'       => 'department',
            'title'             => 'Grade inquiry for capstone project',
            'description'       => 'Submitting a polite grade inquiry regarding our final project evaluation.',
            'category'          => 'academic',
            'issue_category'    => 'Academic Concerns',
            'issue_subcategory' => 'Instructor / Teaching Concerns',
            'workflow_type'     => 'academic',
            'status'            => 'resolved',
            'priority'          => 'low',
            'sentiment_score'   => 0.60,
            'sentiment_label'   => 'positive',
            'submitted_at'      => now()->subDays(2),
            'resolved_at'       => now()->subDay(),
            'last_updated'      => now()->subDay(),
        ]);

        $response = $this->actingAs($dean, 'sanctum')->getJson('/api/analytics/sentiment');
        $response->assertStatus(200);

        $textAnalytics = $response->json('text_analytics');
        $this->assertNotNull($textAnalytics);
        $this->assertEquals(3, $textAnalytics['total_concerns_analyzed']);

        $topWords = array_column($textAnalytics['top_words'], 'word');
        $this->assertContains('internet', $topWords);
        $this->assertContains('connection', $topWords);
        $this->assertContains('laboratory', $topWords);

        // PII (student & dean names, email local-parts, IDs) must be strictly excluded
        $this->assertNotContains('crisostomo', $topWords);
        $this->assertNotContains('ibarra', $topWords);
        $this->assertNotContains('maria', $topWords);
        $this->assertNotContains('clara', $topWords);
        $this->assertNotContains('20269988', $topWords);

        // Recurring 2-word phrases must be extracted from real concern text
        $topPhrases = array_column($textAnalytics['top_phrases'], 'phrase');
        $this->assertContains('internet connection', $topPhrases);
        $this->assertContains('laboratory pc', $topPhrases);

        // Ensure PII never leaks into phrases
        foreach ($topPhrases as $phrase) {
            $this->assertStringNotContainsString('crisostomo', $phrase);
            $this->assertStringNotContainsString('ibarra', $phrase);
        }

        // Test sentiment-based word/phrase filter (Positive only)
        $posResponse = $this->actingAs($dean, 'sanctum')
            ->getJson('/api/analytics/sentiment?text_sentiment=positive');
        $posResponse->assertStatus(200);
        $this->assertEquals(1, $posResponse->json('text_analytics.total_concerns_analyzed'));
        $posWords = array_column($posResponse->json('text_analytics.top_words'), 'word');
        $this->assertContains('grade', $posWords);
        $this->assertNotContains('disconnects', $posWords);

        // Test category-based word/phrase filter (IT and Technology only)
        $catResponse = $this->actingAs($dean, 'sanctum')
            ->getJson('/api/analytics/sentiment?text_category=' . urlencode('IT and Technology'));
        $catResponse->assertStatus(200);
        $this->assertEquals(2, $catResponse->json('text_analytics.total_concerns_analyzed'));

        // Verify overview_analytics and resolution_analytics are included
        $this->assertArrayHasKey('overview_analytics', $response->json());
        $this->assertArrayHasKey('resolution_analytics', $response->json());
        $this->assertEquals(3, $response->json('overview_analytics.summary.total_concerns'));
    }

    public function test_sao_sentiment_analytics_provides_institution_wide_aggregates_and_enforces_option_a_on_flagged(): void
    {
        $unit = AcademicUnit::create(['name' => 'College of Computer Studies']);
        $saoDept = \App\Models\Department::create(['name' => 'Student Affairs Office (SAO)']);
        $itDept  = \App\Models\Department::create(['name' => 'IT Support Services']);

        $sao = User::create([
            'name'              => 'SAO Director',
            'email'             => 'sao@uclm.edu.ph',
            'student_id_number' => '80001111',
            'password'          => bcrypt('password'),
            'role'              => 'sao',
        ]);

        $student = User::create([
            'name'              => 'Student One',
            'email'             => 's1@uclm.edu.ph',
            'student_id_number' => '30001111',
            'password'          => bcrypt('password'),
            'role'              => 'student',
            'academic_unit_id'  => $unit->id,
        ]);

        // Unassigned to SAO (assigned to IT Dept)
        Concern::create([
            'student_id'        => $student->id,
            'academic_unit_id'  => $unit->id,
            'dept_id'           => $itDept->id,
            'target_type'       => 'department',
            'title'             => 'Critical IT server outage',
            'description'       => 'Network switch failure in building.',
            'category'          => 'operational_departments',
            'issue_category'    => 'IT and Technology',
            'workflow_type'     => 'non_academic',
            'status'            => 'pending',
            'priority'          => 'critical',
            'sentiment_score'   => -0.85,
            'sentiment_label'   => 'urgent',
            'submitted_at'      => now(),
            'last_updated'      => now(),
        ]);

        // Assigned to SAO
        Concern::create([
            'student_id'        => $student->id,
            'academic_unit_id'  => $unit->id,
            'dept_id'           => $saoDept->id,
            'target_type'       => 'department',
            'title'             => 'Urgent student welfare concern',
            'description'       => 'Student organization event safety issue.',
            'category'          => 'operational_departments',
            'issue_category'    => 'Student Services',
            'workflow_type'     => 'non_academic',
            'status'            => 'pending',
            'priority'          => 'critical',
            'sentiment_score'   => -0.90,
            'sentiment_label'   => 'urgent',
            'submitted_at'      => now(),
            'last_updated'      => now(),
        ]);

        $response = $this->actingAs($sao, 'sanctum')->getJson('/api/analytics/sentiment');
        $response->assertStatus(200);

        // Institution-wide aggregate includes both concerns
        $this->assertEquals(2, $response->json('distribution.urgent'));
        $this->assertEquals(2, $response->json('flagged_summary.total_scope_flagged'));

        // Option A: Individual flagged list for SAO only exposes the SAO-assigned concern
        $flagged = $response->json('flagged');
        $this->assertCount(1, $flagged);
        $this->assertEquals('Urgent student welfare concern', $flagged[0]['title']);
    }
}
