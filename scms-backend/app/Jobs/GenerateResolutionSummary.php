<?php

namespace App\Jobs;

use App\Models\Concern;
use App\Models\Message;
use App\Services\AnthropicService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateResolutionSummary implements ShouldQueue
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
    public function handle(AnthropicService $anthropic)
    {
        try {
            // Fetch messages with sender info
            $messages = Message::where('concern_id', $this->concern->concern_id)
                ->with('sender')
                ->orderBy('created_at', 'asc')
                ->get();

            if ($messages->isEmpty()) {
                // If there are no messages, generate a basic summary from title/desc
                $threadContent = "Title: {$this->concern->title}\nDescription: {$this->concern->description}\nStatus: Resolved without messages.";
            } else {
                $threadContent = "Title: {$this->concern->title}\nDescription: {$this->concern->description}\n\nThread:\n";
                foreach ($messages as $msg) {
                    $role = $msg->sender->role ?? 'Unknown';
                    $threadContent .= "[{$msg->created_at}] {$role}: {$msg->body}\n";
                }
            }

            $result = $anthropic->generateResolutionSummary($threadContent);

            if (!$result) {
                $this->fail(new \Exception('AnthropicService returned null for resolution summary.'));
                return;
            }

            $this->concern->update([
                'resolution_summary' => $result['summary'] ?? null,
            ]);

            // TODO: Trigger email notification to the student containing $result['summary']
            // Mail::to($this->concern->student->email)->send(new ConcernResolvedMail($this->concern));

        } catch (\Exception $e) {
            $this->fail($e);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception)
    {
        Log::error('GenerateResolutionSummary failed: ' . $exception->getMessage());
    }
}
