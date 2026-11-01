<?php

namespace App\Http\Controllers;

use App\Models\Concern;
use App\Models\ConcernRouting;
use App\Models\Message;
use Illuminate\Http\Request;

class ConcernController extends Controller
{
    /**
     * Generate an AI-assisted routing recommendation for a non-academic concern.
     * Recommends an operational department from the database based on concern title and description.
     */
    public function recommendRouting(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:10000',
        ]);

        $departments = \App\Models\Department::all();
        if ($departments->isEmpty()) {
            return response()->json([
                'recommended_department_id' => null,
                'recommended_department_name' => null,
                'confidence' => 0.0,
                'explanation' => 'No operational departments found in the system. Please select a department manually.',
                'requires_manual_selection' => true,
                'source' => 'fallback',
            ]);
        }

        $recommendation = null;

        // 1. Attempt recommendation via Gemini AI service
        try {
            $geminiService = app(\App\Services\GeminiSentimentService::class);
            $recommendation = $geminiService->recommendOperationalDepartment(
                $validated['title'],
                $validated['description'],
                $departments
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gemini routing recommendation error: ' . $e->getMessage());
        }

        // 2. Safe local fallback if Gemini is unavailable, returns null, or fails
        if (!$recommendation) {
            $localService = app(\App\Services\LocalSentimentService::class);
            $recommendation = $localService->recommendOperationalDepartment(
                $validated['title'],
                $validated['description'],
                $departments
            );
        }

        return response()->json($recommendation);
    }

    /**
     * Submit a new concern (student only).
     *
     * Workflows supported:
     * - workflow_type='academic': Routed to the student's registered academic unit chairperson / dean.
     * - workflow_type='non_academic': Routed to the selected operational/administrative department staff.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'workflow_type' => 'required|in:academic,non_academic',
            'category' => 'nullable|in:academic_units,operational_departments',
            'target_type' => 'nullable|in:department,dean',
            'dept_id' => 'required_if:workflow_type,non_academic|nullable|exists:departments,id',
            'is_anonymous' => 'boolean',
            'attachments.*' => 'nullable|file|mimes:jpeg,png,jpg,pdf,doc,docx|max:5120',
        ]);

        $user = $request->user();
        $workflowType = $validated['workflow_type'];

        // Determine routing target based on workflow type
        if ($workflowType === 'academic') {
            // Academic concerns must ALWAYS use the authenticated student's registered academic unit.
            if (empty($user->academic_unit_id) || !\App\Models\AcademicUnit::where('id', $user->academic_unit_id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'academic_unit' => ['You do not have a registered academic unit on file. Academic concerns cannot be submitted without an assigned academic unit.'],
                ]);
            }

            // Strictly bind to the authenticated student's registered academic unit
            // Any client-submitted academic_unit_id is intentionally ignored to prevent manipulation
            $academicUnitId = (int) $user->academic_unit_id;
            $deptId = null;
            $category = 'academic_units';
            $targetType = 'department';

            $assignedUnit = \App\Models\AcademicUnit::find($academicUnitId);
            $deptOrUnitName = ($assignedUnit ? $assignedUnit->name : 'Academic Unit') . ' Chairperson & Dean Review';
        } else {
            // For non-academic concerns, link to the student's academic unit to satisfy NOT NULL foreign key constraint
            $academicUnitId = $user->academic_unit_id ?? \App\Models\AcademicUnit::first()?->id ?? 1;
            $deptId = (int) $validated['dept_id'];
            $category = 'operational_departments';
            $targetType = 'department';

            $assignedDept = \App\Models\Department::findOrFail($deptId);
            $deptOrUnitName = $assignedDept->name;
        }

        $concern = Concern::create([
            'student_id' => $user->id,
            'academic_unit_id' => $academicUnitId,
            'dept_id' => $deptId,
            'target_type' => $targetType,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'category' => $category,
            'workflow_type' => $workflowType,
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

        // Create initial routing entry with precise initial action label
        $action = $workflowType === 'academic' ? 'submitted_academic' : 'submitted_non_academic';
        
        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => $action,
            'performed_by' => $user->id,
            'notes' => "Concern submitted by student and routed to {$deptOrUnitName}.",
        ]);

        // Run AI Sentiment Analysis immediately (synchronous)
        // LocalSentimentService always runs for priority + summary derivation.
        // GeminiSentimentService overrides sentiment_score and sentiment_label when available.
        try {
            $localService  = app(\App\Services\LocalSentimentService::class);
            $localResult   = $localService->analyzeConcern($concern->title, $concern->description);

            $geminiService = app(\App\Services\GeminiSentimentService::class);
            $geminiResult  = $geminiService->analyzeConcern($concern->title, $concern->description);

            // Use Gemini sentiment values when available; fall back to local
            $sentimentScore            = $geminiResult['sentiment_score'] ?? $localResult['sentiment_score'];
            $sentimentLabel            = $geminiResult['sentiment_label'] ?? $localResult['sentiment_label'];
            $tokensUsed                = $geminiResult['api_tokens_used'] ?? 0;
            $aiPriority                = $geminiResult['priority_suggestion'] ?? $localResult['priority_suggestion'];
            $aiPriorityExplanation     = $geminiResult['priority_explanation'] ?? $localResult['priority_explanation'];
            $issueCategory             = $geminiResult['issue_category'] ?? $localResult['issue_category'];
            $issueSubcategory          = $geminiResult['issue_subcategory'] ?? $localResult['issue_subcategory'];
            $classificationExplanation = $geminiResult['classification_explanation'] ?? $localResult['classification_explanation'];
            $classificationConfidence  = $geminiResult['classification_confidence'] ?? $localResult['classification_confidence'];
            $aiSummary                 = $geminiResult['summary'] ?? $localResult['summary'];

            $concern->update([
                'sentiment_score'            => $sentimentScore,
                'sentiment_label'            => $sentimentLabel,
                'priority'                   => $aiPriority,
                'ai_priority'                => $aiPriority,
                'ai_priority_explanation'    => $aiPriorityExplanation,
                'is_priority_overridden'     => false,
                'issue_category'             => $issueCategory,
                'issue_subcategory'          => $issueSubcategory,
                'classification_explanation' => $classificationExplanation,
                'classification_confidence'  => $classificationConfidence,
                'ai_summary'                 => $aiSummary,
                'ai_status'                  => 'complete',
            ]);

            \Illuminate\Support\Facades\DB::table('sentiment_logs')->insert([
                'concern_id'         => $concern->concern_id,
                'raw_text_analyzed'  => "Title: {$concern->title}\nDescription: {$concern->description}",
                'sentiment_score'    => $sentimentScore,
                'sentiment_label'    => $sentimentLabel,
                'priority_suggestion' => $aiPriority,
                'ai_summary'         => $aiSummary,
                'api_tokens_used'    => $tokensUsed,
                'analyzed_at'        => now(),
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Sentiment analysis failed: ' . $e->getMessage());
            $concern->update(['ai_status' => 'failed']);
        }

        // Notify assigned department staff
        try {
            $staffQuery = \App\Models\User::where('role', 'dept_staff');
            if ($workflowType === 'academic') {
                $staffQuery->where('academic_unit_id', $academicUnitId);
            } else {
                $staffQuery->where('dept_id', $deptId);
            }
            $targetStaff = $staffQuery->get();

            foreach ($targetStaff as $staff) {
                $staff->notify(new \App\Notifications\ConcernWorkflowNotification(
                    $concern->concern_id,
                    'New Concern Received',
                    "A new {$workflowType} concern '{$concern->title}' has been submitted for review.",
                    'submitted',
                    $workflowType,
                    "/dept-staff/concerns/{$concern->concern_id}"
                ));
            }
        } catch (\Throwable $ne) {
            \Illuminate\Support\Facades\Log::warning('Failed sending submission notification: ' . $ne->getMessage());
        }

        // Phase 6: Recurrence Detection and Critical Concern Notifications
        try {
            app(\App\Services\RecurrenceDetectionService::class)->processConcern($concern);
        } catch (\Throwable $re) {
            \Illuminate\Support\Facades\Log::warning('Recurrence detection failed: ' . $re->getMessage());
        }

        if ($concern->fresh()->priority === 'critical') {
            try {
                \App\Notifications\ConcernWorkflowNotification::sendCriticalConcernNotification($concern->fresh(), 'Initial Submission');
            } catch (\Throwable $ce) {
                \Illuminate\Support\Facades\Log::warning('Critical concern notification failed: ' . $ce->getMessage());
            }
        }

        $freshConcern = $concern->fresh(['academicUnit', 'department', 'student']);
        $freshConcern->makeHidden([
            'priority_override_reason',
            'priority_overridden_by',
            'priority_overridden_at',
            'ai_priority',
            'is_priority_overridden',
            'recurring_incident_id',
            'critical_notified_at',
        ]);
        $freshConcern->prediction = app(\App\Services\ResolutionPredictionService::class)->predictForStudent($freshConcern);

        return response()->json([
            'message' => 'Concern submitted successfully.',
            'concern' => $freshConcern,
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

        $concerns->each(function ($c) {
            $c->makeHidden([
                'priority_override_reason',
                'priority_overridden_by',
                'priority_overridden_at',
                'ai_priority',
                'is_priority_overridden',
                'recurring_incident_id',
                'critical_notified_at',
            ]);
        });

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
            'workflow_type' => 'nullable|in:academic,non_academic',
            'category' => 'nullable|in:academic_units,operational_departments',
            'target_type' => 'nullable|in:department,dean',
            'academic_unit_id' => 'nullable|exists:academic_units,id',
            'dept_id' => 'nullable|exists:departments,id',
            'is_anonymous' => 'boolean',
        ]);

        $workflowType = $validated['workflow_type'] ?? $concern->workflow_type ?? 'non_academic';

        if ($workflowType === 'academic') {
            if (empty($user->academic_unit_id) || !\App\Models\AcademicUnit::where('id', $user->academic_unit_id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'academic_unit' => ['You do not have a registered academic unit on file. Academic concerns cannot be submitted without an assigned academic unit.'],
                ]);
            }
            $academicUnitId = (int) $user->academic_unit_id;
            $deptId = null;
            $category = 'academic_units';
            $targetType = 'department';
        } else {
            $academicUnitId = $concern->academic_unit_id ?? ($user->academic_unit_id ?? \App\Models\AcademicUnit::first()?->id ?? 1);
            $deptId = $validated['dept_id'] ?? $concern->dept_id;
            $category = 'operational_departments';
            $targetType = 'department';
        }

        $concern->update([
            'academic_unit_id' => $academicUnitId,
            'target_type' => $targetType,
            'dept_id' => $deptId,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'category' => $category,
            'workflow_type' => $workflowType,
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

            // Protect effective priority from silent AI reanalysis erasure if it was manually overridden
            if (!$concern->is_priority_overridden) {
                $updateData['priority'] = $result['priority_suggestion'] ?? null;
            }

            $concern->update($updateData);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Sentiment analysis failed during edit: ' . $e->getMessage());
        }

        // Phase 6: Re-process recurrence and check critical notifications
        try {
            app(\App\Services\RecurrenceDetectionService::class)->processConcern($concern);
        } catch (\Throwable $re) {
            \Illuminate\Support\Facades\Log::warning('Recurrence detection update failed: ' . $re->getMessage());
        }

        if ($concern->fresh()->priority === 'critical') {
            try {
                \App\Notifications\ConcernWorkflowNotification::sendCriticalConcernNotification($concern->fresh(), 'Student Edit');
            } catch (\Throwable $ce) {
                \Illuminate\Support\Facades\Log::warning('Critical concern notification failed on edit: ' . $ce->getMessage());
            }
        }

        $freshConcern = $concern->fresh(['academicUnit', 'department']);
        $freshConcern->makeHidden([
            'priority_override_reason',
            'priority_overridden_by',
            'priority_overridden_at',
            'ai_priority',
            'ai_priority_explanation',
            'classification_explanation',
            'is_priority_overridden',
            'recurring_incident_id',
            'critical_notified_at',
        ]);
        $freshConcern->prediction = app(\App\Services\ResolutionPredictionService::class)->predictForStudent($freshConcern);

        return response()->json([
            'message' => 'Concern updated successfully.',
            'concern' => $freshConcern,
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

        // Filter out internal staff-only SLA audit records for students
        if ($concern->relationLoaded('routingHistory')) {
            $concern->setRelation(
                'routingHistory',
                $concern->routingHistory->filter(function ($entry) {
                    return !in_array($entry->action, \App\Services\ConcernSlaService::INTERNAL_ACTIONS, true);
                })->values()
            );
        }

        $concern->makeHidden([
            'priority_override_reason',
            'priority_overridden_by',
            'priority_overridden_at',
            'ai_priority',
            'ai_priority_explanation',
            'classification_explanation',
            'is_priority_overridden',
            'recurring_incident_id',
            'critical_notified_at',
        ]);

        $concern->prediction = app(\App\Services\ResolutionPredictionService::class)->predictForStudent($concern);

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

        // Dispatch in-app notifications to authorized concern participants
        \App\Notifications\ConcernWorkflowNotification::sendNewMessageNotification(
            $concern,
            $message,
            $request->user()
        );

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
