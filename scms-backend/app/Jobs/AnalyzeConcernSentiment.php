<?php

namespace App\Jobs;

use App\Models\Concern;
use App\Services\LocalSentimentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalyzeConcernSentiment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $concern;

    /**
     * Create a new job instance.
     */
    public function __construct(Concern $concern)
    {
        $this->concern = $concern;
    }

    /**
     * Execute the job.
     */
    public function handle(LocalSentimentService $sentimentService)
    {
        try {
            $result = $sentimentService->analyzeConcern(
                $this->concern->title,
                $this->concern->description
            );

            DB::transaction(function () use ($result) {
                $this->concern->update([
                    'sentiment_score' => $result['sentiment_score'] ?? null,
                    'sentiment_label' => $result['sentiment_label'] ?? null,
                    'priority' => $result['priority_suggestion'] ?? null,
                    'ai_summary' => $result['summary'] ?? null,
                    'ai_status' => 'complete',
                ]);

                DB::table('sentiment_logs')->insert([
                    'concern_id' => $this->concern->concern_id,
                    'raw_text_analyzed' => "Title: {$this->concern->title}\nDescription: {$this->concern->description}",
                    'sentiment_score' => $result['sentiment_score'] ?? null,
                    'sentiment_label' => $result['sentiment_label'] ?? null,
                    'priority_suggestion' => $result['priority_suggestion'] ?? null,
                    'ai_summary' => $result['summary'] ?? null,
                    'api_tokens_used' => 0,
                    'analyzed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        } catch (\Exception $e) {
            Log::error('AnalyzeConcernSentiment failed: ' . $e->getMessage());
            $this->concern->update(['ai_status' => 'failed']);
            $this->fail($e);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception)
    {
        Log::error('AnalyzeConcernSentiment job failed: ' . $exception->getMessage());
        $this->concern->update(['ai_status' => 'failed']);
    }
}
