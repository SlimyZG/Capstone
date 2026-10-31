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
                $updateData = [
                    'sentiment_score'            => $result['sentiment_score'] ?? null,
                    'sentiment_label'            => $result['sentiment_label'] ?? null,
                    'ai_priority'                => $result['priority_suggestion'] ?? null,
                    'ai_priority_explanation'    => $result['priority_explanation'] ?? null,
                    'issue_category'             => $result['issue_category'] ?? null,
                    'issue_subcategory'          => $result['issue_subcategory'] ?? null,
                    'classification_explanation' => $result['classification_explanation'] ?? null,
                    'classification_confidence'  => $result['classification_confidence'] ?? null,
                    'ai_summary'                 => $result['summary'] ?? null,
                    'ai_status'                  => 'complete',
                ];

                if (!$this->concern->is_priority_overridden) {
                    $updateData['priority'] = $result['priority_suggestion'] ?? null;
                }

                $this->concern->update($updateData);

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

            // Phase 6: Recurrence processing and critical notifications
            try {
                app(\App\Services\RecurrenceDetectionService::class)->processConcern($this->concern->fresh());
            } catch (\Throwable $re) {
                Log::warning('Recurrence detection failed in AnalyzeConcernSentiment: ' . $re->getMessage());
            }

            if ($this->concern->fresh()->priority === 'critical') {
                try {
                    \App\Notifications\ConcernWorkflowNotification::sendCriticalConcernNotification(
                        $this->concern->fresh(),
                        'AI Sentiment Analysis'
                    );
                } catch (\Throwable $ce) {
                    Log::warning('Critical concern notification failed in AnalyzeConcernSentiment: ' . $ce->getMessage());
                }
            }

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
