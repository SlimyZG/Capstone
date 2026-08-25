<?php

namespace App\Http\Controllers;

use App\Models\Concern;
use App\Models\ConcernRouting;
use App\Models\Message;
use Illuminate\Http\Request;

class ConcernController extends Controller
{
    /**
     * Submit a new concern (student only).
     *
     * Business Rules:
     * - target_type='department': must include dept_id, concern goes to department staff
     * - target_type='dean': bypasses department, goes directly to student's dean
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|in:academic_units,operational_departments',
            'target_type' => 'required|in:department,dean',
            'academic_unit_id' => 'required_if:category,academic_units|nullable|exists:academic_units,id',
            'dept_id' => 'required_if:category,operational_departments|nullable|exists:departments,id',
            'is_anonymous' => 'boolean',
            'attachments.*' => 'nullable|file|mimes:jpeg,png,jpg,pdf,doc,docx|max:5120',
        ]);

        $user = $request->user();

        $concern = Concern::create([
            'student_id' => $user->id,
            'academic_unit_id' => $validated['category'] === 'academic_units' ? $validated['academic_unit_id'] : $user->academic_unit_id,
            'target_type' => $validated['target_type'],
            'dept_id' => $validated['category'] === 'operational_departments' ? $validated['dept_id'] : null,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'category' => $validated['category'],
            'status' => 'pending',
            'is_anonymous' => $validated['is_anonymous'] ?? false,
            'submitted_at' => now(),
            'last_updated' => now(),
        ]);

        $attachmentPaths = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments', 'public');
                $attachmentPaths[] = '/storage/' . $path;
            }
            $concern->update(['attachments' => $attachmentPaths]);
        }

        // Create initial routing entry
        $action = $validated['target_type'] === 'department' ? 'routed_to_dept' : 'escalated_to_dean';
        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => $action,
            'performed_by' => $user->id,
            'notes' => 'Concern submitted by student.',
        ]);

        // Run AI Sentiment Analysis immediately (synchronous)
        try {
            $sentimentService = app(\App\Services\LocalSentimentService::class);
            $result = $sentimentService->analyzeConcern($concern->title, $concern->description);

            $concern->update([
                'sentiment_score' => $result['sentiment_score'] ?? null,
                'sentiment_label' => $result['sentiment_label'] ?? null,
                'priority' => $result['priority_suggestion'] ?? null,
                'ai_summary' => $result['summary'] ?? null,
                'ai_status' => 'complete',
            ]);

            \Illuminate\Support\Facades\DB::table('sentiment_logs')->insert([
                'concern_id' => $concern->concern_id,
                'raw_text_analyzed' => "Title: {$concern->title}\nDescription: {$concern->description}",
                'sentiment_score' => $result['sentiment_score'] ?? null,
                'sentiment_label' => $result['sentiment_label'] ?? null,
                'priority_suggestion' => $result['priority_suggestion'] ?? null,
                'ai_summary' => $result['summary'] ?? null,
                'api_tokens_used' => 0,
                'analyzed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Sentiment analysis failed: ' . $e->getMessage());
            $concern->update(['ai_status' => 'failed']);
        }

        return response()->json([
            'message' => 'Concern submitted successfully.',
            'concern' => $concern->fresh(['academicUnit', 'department', 'student']),
        ], 201);
    }

    /**
     * Get the student's own concerns.
     */
    public function index(Request $request)
    {
        $concerns = Concern::where('student_id', $request->user()->id)
            ->with(['academicUnit', 'department', 'student'])
            ->orderBy('submitted_at', 'desc')
            ->get();

        return response()->json([
            'concerns' => $concerns,
        ]);
    }

    /**
     * Update an existing concern (only if pending).
     */
    public function update(Request $request, int $id)
    {
        $user = $request->user();

        $concern = Concern::where('concern_id', $id)
            ->where('student_id', $user->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|in:academic_units,operational_departments',
            'target_type' => 'required|in:department,dean',
            'academic_unit_id' => 'required_if:category,academic_units|nullable|exists:academic_units,id',
            'dept_id' => 'required_if:category,operational_departments|nullable|exists:departments,id',
            'is_anonymous' => 'boolean',
        ]);

        $concern->update([
            'academic_unit_id' => $validated['category'] === 'academic_units' ? $validated['academic_unit_id'] : $user->academic_unit_id,
            'target_type' => $validated['target_type'],
            'dept_id' => $validated['category'] === 'operational_departments' ? $validated['dept_id'] : null,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'category' => $validated['category'],
            'is_anonymous' => $validated['is_anonymous'] ?? false,
            'last_updated' => now(),
        ]);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'edited',
            'performed_by' => $user->id,
            'notes' => 'Concern edited by student.',
        ]);

        // Re-run AI Sentiment Analysis
        try {
            $sentimentService = app(\App\Services\LocalSentimentService::class);
            $result = $sentimentService->analyzeConcern($concern->title, $concern->description);

            $concern->update([
                'sentiment_score' => $result['sentiment_score'] ?? null,
                'sentiment_label' => $result['sentiment_label'] ?? null,
                'priority' => $result['priority_suggestion'] ?? null,
                'ai_summary' => $result['summary'] ?? null,
                'ai_status' => 'complete',
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Sentiment analysis failed during edit: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Concern updated successfully.',
            'concern' => $concern->fresh(['academicUnit', 'department']),
        ]);
    }

    /**
     * Get a specific concern's details (student can only view their own).
     */
    public function show(Request $request, int $id)
    {
        $concern = Concern::where('concern_id', $id)
            ->where('student_id', $request->user()->id)
            ->with(['academicUnit', 'department', 'routingHistory.performer', 'messages.sender'])
            ->firstOrFail();

        // Filter out internal messages for students
        $concern->messages = $concern->messages->filter(function ($message) {
            return !$message->is_internal;
        })->values();

        return response()->json([
            'concern' => $concern,
        ]);
    }

    /**
     * Send a message on a concern thread (student).
     */
    public function sendMessage(Request $request, int $id)
    {
        $concern = Concern::where('concern_id', $id)
            ->where('student_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        $message = Message::create([
            'concern_id' => $concern->concern_id,
            'sender_id' => $request->user()->id,
            'body' => $validated['body'],
            'is_internal' => false,
        ]);

        // Update last_updated timestamp
        $concern->update(['last_updated' => now()]);

        return response()->json([
            'message' => 'Message sent successfully.',
            'data' => $message->load('sender'),
        ], 201);
    }

    /**
     * Withdraw a concern (student).
     */
    public function withdraw(Request $request, int $id)
    {
        $concern = Concern::where('concern_id', $id)
            ->where('student_id', $request->user()->id)
            ->firstOrFail();

        $concern->update([
            'status' => 'withdrawn',
            'last_updated' => now(),
        ]);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'status_changed',
            'performed_by' => $request->user()->id,
            'notes' => 'Concern withdrawn by student.',
        ]);

        return response()->json([
            'message' => 'Concern withdrawn successfully.',
            'concern' => $concern->fresh(['academicUnit', 'department']),
        ]);
    }

    /**
     * Reopen a withdrawn concern (student).
     */
    public function reopen(Request $request, int $id)
    {
        $concern = Concern::where('concern_id', $id)
            ->where('student_id', $request->user()->id)
            ->where('status', 'withdrawn')
            ->firstOrFail();

        $concern->update([
            'status' => 'pending',
            'last_updated' => now(),
        ]);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'status_changed',
            'performed_by' => $request->user()->id,
            'notes' => 'Concern reopened by student.',
        ]);

        return response()->json([
            'message' => 'Concern reopened successfully.',
            'concern' => $concern->fresh(['academicUnit', 'department']),
        ]);
    }
}
