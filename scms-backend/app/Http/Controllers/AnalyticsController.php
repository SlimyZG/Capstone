<?php

namespace App\Http\Controllers;

use App\Models\Concern;
use App\Services\AnthropicService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Get sentiment statistics for the dashboard.
     */
    public function getSentiment(Request $request)
    {
        $user = $request->user();
        $query = Concern::query();

        // Scope data for deans
        if ($user->role === 'dean') {
            $query->where('academic_unit_id', $user->academic_unit_id);
        }

        $distribution = (clone $query)
            ->whereNotNull('sentiment_label')
            ->select('sentiment_label', DB::raw('count(*) as count'))
            ->groupBy('sentiment_label')
            ->pluck('count', 'sentiment_label');

        $flaggedConcerns = (clone $query)
            ->where(function ($q) {
                $q->where('sentiment_score', '<', -0.7)
                  ->orWhere('priority', 'critical');
            })
            ->with(['student:id,name,student_id_number', 'academicUnit', 'department'])
            ->orderBy('submitted_at', 'desc')
            ->take(50)
            ->get()
            ->map(function ($concern) {
                if ($concern->is_anonymous) {
                    $concern->student->name = 'Anonymous';
                    $concern->student->student_id_number = '********';
                }
                return $concern;
            });
            
        // Trend over time (last 30 days)
        $trendOverTime = (clone $query)
            ->where('submitted_at', '>=', now()->subDays(30))
            ->whereNotNull('sentiment_score')
            ->select(DB::raw('DATE(submitted_at) as date'), DB::raw('AVG(sentiment_score) as avg_score'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'distribution' => [
                'positive' => $distribution['positive'] ?? 0,
                'neutral' => $distribution['neutral'] ?? 0,
                'negative' => $distribution['negative'] ?? 0,
                'urgent' => $distribution['urgent'] ?? 0,
            ],
            'flagged' => $flaggedConcerns,
            'trend' => $trendOverTime,
        ]);
    }

    /**
     * Get trend analysis narrative and stats.
     */
    public function getTrends(Request $request, AnthropicService $anthropic)
    {
        $user = $request->user();
        $query = Concern::query();

        if ($user->role === 'dean') {
            $query->where('academic_unit_id', $user->academic_unit_id);
        }

        // Gather basic stats for the narrative prompt
        $stats = [
            'total_concerns_last_30_days' => (clone $query)->where('submitted_at', '>=', now()->subDays(30))->count(),
            'total_resolved' => (clone $query)->where('status', 'resolved')->where('submitted_at', '>=', now()->subDays(30))->count(),
            'priority_breakdown' => (clone $query)->where('submitted_at', '>=', now()->subDays(30))
                ->select('priority', DB::raw('count(*) as count'))
                ->groupBy('priority')
                ->pluck('count', 'priority'),
            'sentiment_breakdown' => (clone $query)->where('submitted_at', '>=', now()->subDays(30))
                ->select('sentiment_label', DB::raw('count(*) as count'))
                ->groupBy('sentiment_label')
                ->pluck('count', 'sentiment_label'),
        ];

        $result = $anthropic->generateTrendNarrative(json_encode($stats));

        return response()->json([
            'stats' => $stats,
            'narrative' => $result['narrative'] ?? 'Trend analysis unavailable at this time.',
        ]);
    }
}
