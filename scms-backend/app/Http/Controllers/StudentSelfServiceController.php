<?php

namespace App\Http\Controllers;

use App\Models\SelfServiceFeedback;
use App\Models\SelfServiceKbEntry;
use App\Models\SelfServiceSearchLog;
use App\Models\SelfServiceTask;
use Database\Seeders\SelfServiceKnowledgeBaseSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentSelfServiceController extends Controller
{
    /**
     * Sensitive and urgent safety patterns that must bypass ordinary self-service checklists
     * and immediately surface formal reporting or emergency assistance pathways.
     */
    private const SENSITIVE_PATTERNS = [
        'sexual_harassment' => [
            'keywords' => ['sexual harassment', 'sexual favor', 'malicious touching', 'sexual advance', 'lewd', 'ra 7877', 'safe spaces', 'molest', 'inappropriate touching'],
            'entry_code' => 'S1',
            'title' => 'Formal Reporting Pathway: Sexual Harassment Policy',
            'responsible_office' => 'Student Affairs Office (SAO Director) / Disciplinary Committee Chairperson, University Chancellor, University President, or any University Official (with Guidance Center counseling support)',
            'source_reference' => 'University of Cebu Student Manual (June 2019 Edition), pages 84–87',
            'message' => 'Concerns involving sexual harassment are handled through formal institutional reporting and investigation channels — never as ordinary self-service checklists. You may file a written report with the Student Disciplinary Committee Chairperson (SAO Director), University Chancellor, University President, or any University Official, or submit a formal concern directly below.',
            'privacy_notice' => 'Do not include unnecessary sensitive personal details if you prefer to discuss them in person with authorized SAO/CODI or Guidance personnel. Confidentiality follows verified University policy.',
            'is_emergency' => false,
        ],
        'violence_bullying' => [
            'keywords' => ['physical assault', 'assault', 'assaulted', 'violence', 'violent', 'punched', 'punching', 'beaten', 'mauling', 'fistfight', 'rumble', 'deadly weapon', 'knife', 'gun', 'threat', 'threatened', 'threatening', 'bullying', 'bullied', 'cyberbullying', 'intimidation', 'extortion', 'harassment', 'harassed'],
            'entry_code' => 'S2',
            'title' => 'Immediate Safety & Formal Reporting Pathway: Violence, Threats, Bullying, or Assault',
            'responsible_office' => 'Civil Security Unit (CSU) (for immediate physical safety), Student Affairs Office (SAO Director), College Dean, and Guidance Center',
            'source_reference' => 'University of Cebu Student Manual (June 2019 Edition), pages 10, 67, 69–80',
            'message' => 'Safety, violence, threat, and bullying concerns bypass self-service task checklists. If there is an immediate physical threat or active incident on campus, alert the Civil Security Unit (CSU) immediately. For formal disciplinary investigation and protective action (including Preventive Suspension under page 80), report to the Student Affairs Office (SAO) or your College Dean, or submit a formal concern directly below.',
            'privacy_notice' => 'Under Page 77 of the Student Manual, formal disciplinary complaints require complainant identification so due process can be observed.',
            'is_emergency' => true,
        ],
        'hazing' => [
            'keywords' => ['hazing', 'initiation rite', 'fraternity initiation', 'sorority initiation', 'forced initiation', 'forced recruitment', 'neophyte', 'paddle', 'paddling', 'anti-hazing'],
            'entry_code' => 'S3',
            'title' => 'Emergency & Formal Reporting Pathway: Anti-Hazing Policy',
            'responsible_office' => 'Student Affairs Office (SAO Director), Civil Security Unit (CSU Head), College Dean/Chairperson, or Police Authorities',
            'source_reference' => 'University of Cebu Student Manual (June 2019 Edition), pages 47, 72, 87–93',
            'message' => 'Hazing and forced fraternity/sorority initiation or recruitment are strictly prohibited and bypass self-service checklists. Report immediately to the Student Affairs Office (SAO Director), Civil Security Unit (CSU Head), your College Dean/Chairperson, or police authorities, or submit a formal concern directly below.',
            'privacy_notice' => 'Confidentiality and protection follow verified University policy and the Anti-Hazing Law.',
            'is_emergency' => true,
        ],
        'disciplinary_complaint' => [
            'keywords' => ['disciplinary complaint', 'disciplinary investigation', 'student disciplinary', 'complaint against a student', 'report a student', 'vandalism', 'forgery', 'estafa', 'theft', 'stolen'],
            'entry_code' => 'S4',
            'title' => 'Formal Reporting Pathway: Filing a Formal Student Disciplinary Complaint',
            'responsible_office' => 'Student Affairs Office (SAO Director), College Dean, or Principal; heard by the Student Disciplinary Committee',
            'source_reference' => 'University of Cebu Student Manual (June 2019 Edition), pages 77–82',
            'message' => 'Under the Student Manual, a formal complaint against a student for a serious offense or misconduct is filed in writing with the Office of Student Affairs (SAO), the College Dean, or the Principal. This is a formal reporting process, not a self-service checklist. You may also submit a formal concern directly below.',
            'privacy_notice' => 'Under Page 77 of the Student Manual, no anonymous complaint is entertained in formal disciplinary prosecution so that due process can be observed. UCLM-specific contact details and office hours are pending institutional confirmation.',
            'is_emergency' => false,
        ],
        'medical_emergency' => [
            'keywords' => ['medical emergency', 'unconscious', 'fainted', 'severe bleeding', 'bleeding heavily', 'heart attack', 'seizure', 'difficulty breathing', 'cannot breathe', 'ambulance', 'emergency hospital', 'medico-legal', 'poisoning', 'fracture', 'head injury'],
            'entry_code' => 'S5',
            'title' => 'Emergency Assistance Pathway: Campus Medical Emergency & Hospital Referral',
            'responsible_office' => 'University Medical and Dental Clinic (University Physician / Clinic Nurses) and Civil Security Unit (CSU)',
            'source_reference' => 'University of Cebu Student Manual (June 2019 Edition), pages 57–59',
            'message' => 'MEDICAL EMERGENCY ALERT: If you or another student is experiencing an acute medical emergency or injury on campus, proceed to or alert the University Medical and Dental Clinic and the Civil Security Unit (CSU) immediately. Under the Student Manual (pp. 57–58), complicated medical/dental cases are referred immediately by the University clinic to nearby hospitals, and medico-legal cases are referred to a government hospital.',
            'privacy_notice' => 'Do not wait for an online response during a medical emergency. Alert the nearest clinic nurse, security guard (CSU), or faculty member immediately.',
            'is_emergency' => true,
        ],
        'confidential_drug_testing' => [
            'keywords' => ['drug test', 'drug testing', 'random drug', 'confirmatory drug', 'positive drug test', 'ra 9165', 'drug counselor'],
            'entry_code' => 'S7',
            'title' => 'Confidential Health Pathway: Random Drug Testing Protocol',
            'responsible_office' => 'University Drug Testing Coordinator and Drug Counselor (Guidance and Counseling Services)',
            'source_reference' => 'University of Cebu Student Manual (June 2019 Edition), pages 97–105',
            'message' => 'Under the Student Manual (pp. 97–105) and Republic Act No. 9165, random drug testing records are strictly confidential and a positive result under random drug testing is not a ground for expulsion or disciplinary action. Please consult the designated Drug Testing Coordinator or Guidance Counselor in person.',
            'privacy_notice' => 'IMPORTANT PRIVACY SAFEGUARD: Never submit or upload confidential medical or drug-testing results through online forms.',
            'is_emergency' => false,
        ],
        'pregnancy_protection' => [
            'keywords' => ['pregnancy discrimination', 'pregnant out of wedlock', 'expelled for pregnancy', 'kicked out for being pregnant', 'refused admission pregnant'],
            'entry_code' => 'S6',
            'title' => 'Formal Protection Pathway: Policy on Gender Sensitivity (Pregnancy Non-Discrimination)',
            'responsible_office' => 'Student Affairs Office (SAO), Guidance and Counseling Services, Medical Clinic, and College Dean\'s Office',
            'source_reference' => 'University of Cebu Student Manual (June 2019 Edition), pages 63, 93–94',
            'message' => 'Under Section C.1.2 of the Student Manual (p. 94), discrimination against female students who become pregnant out of wedlock is prohibited, and the University shall not turn out or refuse admission solely on account of pregnancy. Pregnant students also qualify for a Uniform Exemption Card at SAO (p. 63). You may consult SAO, the Guidance Center, or submit a formal concern below.',
            'privacy_notice' => 'Avoid submitting unnecessary sensitive medical records online; consult SAO or the Guidance Center directly.',
            'is_emergency' => false,
        ],
    ];

    /**
     * Ensure knowledge base entries are seeded in the database without duplicating or overwriting edits.
     */
    private function ensureKnowledgeBaseSeeded(): void
    {
        if (SelfServiceKbEntry::count() === 0) {
            app(SelfServiceKnowledgeBaseSeeder::class)->run();
        }
    }

    /**
     * Get the 12 discovery categories with counts of currently PUBLISHED entries.
     */
    public function categories(Request $request)
    {
        $this->ensureKnowledgeBaseSeeded();

        $publishedCounts = SelfServiceKbEntry::publishedForStudents()
            ->select('category', DB::raw('COUNT(*) as published_count'))
            ->groupBy('category')
            ->pluck('published_count', 'category');

        $categories = collect(SelfServiceKnowledgeBaseSeeder::discoveryCategories())
            ->map(function (array $cat) use ($publishedCounts) {
                $cat['published_entries_count'] = (int) ($publishedCounts[$cat['id']] ?? 0);
                return $cat;
            })
            ->values();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    /**
     * Search or browse approved & published knowledge base entries.
     *
     * STRICT RULES:
     * 1. Only entries with approval_status='Approved', publication_status='Published', and lifecycle_stage='Published'
     *    are ever returned as verified student guidance.
     * 2. Sensitive/urgent queries or selecting "Safety and Security" immediately trigger the formal reporting /
     *    emergency assistance guardrail without delaying the student with a self-help checklist.
     * 3. If no published guidance exists for the query/category, clearly inform the student that verified
     *    guidance is currently unavailable and direct them to the responsible office or formal concern submission.
     */
    public function entries(Request $request)
    {
        $this->ensureKnowledgeBaseSeeded();

        $validated = $request->validate([
            'q' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:64',
        ]);

        $queryText = trim((string) ($validated['q'] ?? ''));
        $selectedCategory = trim((string) ($validated['category'] ?? ''));

        // 1. Evaluate Sensitive & Urgent Guardrails first
        $sensitiveGuardrail = $this->detectSensitiveGuardrail($queryText, $selectedCategory);

        // 2. Retrieve strictly PUBLISHED entries
        $publishedQuery = SelfServiceKbEntry::publishedForStudents();

        if ($selectedCategory !== '' && $selectedCategory !== 'All') {
            $publishedQuery->where('category', $selectedCategory);
        }

        $allPublished = $publishedQuery->orderBy('entry_code')->get();

        // 3. Filter and rank by natural language search query if provided
        if ($queryText !== '') {
            $scored = $this->rankPublishedEntries($allPublished, $queryText);
        } else {
            $scored = $allPublished->map(fn (SelfServiceKbEntry $entry) => $this->formatStudentEntry($entry))->values();
        }

        // 4. Record anonymized/aggregated search discovery log (only when student searches or selects a category)
        if ($queryText !== '' || $selectedCategory !== '') {
            SelfServiceSearchLog::create([
                'student_id' => $request->user()?->id,
                'query' => $queryText !== '' ? $queryText : null,
                'selected_category' => $selectedCategory !== '' ? $selectedCategory : null,
                'matched_entries_count' => $scored->count(),
                'has_published_match' => $scored->isNotEmpty(),
                'triggered_sensitive_guardrail' => $sensitiveGuardrail['triggered'],
                'escalated_to_formal_concern' => false,
            ]);
        }

        $unavailableNotice = null;
        if ($scored->isEmpty() && !$sensitiveGuardrail['triggered']) {
            $unavailableNotice = [
                'status' => 'verified_guidance_unavailable',
                'title' => 'Verified Self-Service Guidance Not Yet Available',
                'message' => 'Verified UCLM self-service guidance for this topic is currently undergoing institutional review or is not yet published. Please visit the appropriate university office directly or proceed to the formal Submit Concern process below.',
                'recommended_office' => $this->suggestOfficeForUnavailableTopic($selectedCategory, $queryText),
            ];
        }

        return response()->json([
            'entries' => $scored,
            'total_published' => $scored->count(),
            'sensitive_guardrail' => $sensitiveGuardrail,
            'unavailable_notice' => $unavailableNotice,
        ]);
    }

    /**
     * Retrieve a single published knowledge base entry for a student.
     * Unpublished entries strictly return 404 so students can never retrieve unapproved content.
     */
    public function showEntry(Request $request, string $idOrCode)
    {
        $this->ensureKnowledgeBaseSeeded();

        $entry = SelfServiceKbEntry::publishedForStudents()
            ->where(function ($q) use ($idOrCode) {
                if (is_numeric($idOrCode)) {
                    $q->where('id', (int) $idOrCode)->orWhere('entry_code', strtoupper($idOrCode));
                } else {
                    $q->where('entry_code', strtoupper($idOrCode));
                }
            })
            ->first();

        if (!$entry) {
            return response()->json([
                'message' => 'Verified knowledge base guidance not found or not currently published.',
            ], 404);
        }

        return response()->json([
            'entry' => $this->formatStudentEntry($entry),
        ]);
    }

    /**
     * List the authenticated student's own self-service tasks.
     * Checks whether the underlying KB entry has been updated or unpublished since the task was saved.
     */
    public function myTasks(Request $request)
    {
        $studentId = $request->user()->id;

        $tasks = SelfServiceTask::where('student_id', $studentId)
            ->with('kbEntry')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (SelfServiceTask $task) => $this->formatStudentTask($task));

        return response()->json([
            'tasks' => $tasks,
        ]);
    }

    /**
     * Start (or resume an existing active) Hybrid Guided Self-Service Task from a published KB entry.
     *
     * STRICT RULES:
     * - Only published entries can generate a task.
     * - Sensitive entries (is_sensitive = true or category = 'Safety and Security') CANNOT be turned into
     *   self-help checklists; they return a 422 directing the student to the formal reporting pathway.
     * - Never creates a formal Concern record.
     */
    public function startTask(Request $request)
    {
        $validated = $request->validate([
            'kb_entry_id' => 'required|integer|exists:self_service_kb_entries,id',
            'force_new' => 'nullable|boolean',
        ]);

        $student = $request->user();
        $entry = SelfServiceKbEntry::find($validated['kb_entry_id']);

        if (!$entry || !$entry->isPublishedForStudents()) {
            return response()->json([
                'message' => 'Cannot create a self-service task from an unpublished or unapproved knowledge base entry.',
            ], 403);
        }

        if ($entry->is_sensitive || $entry->category === 'Safety and Security') {
            return response()->json([
                'message' => 'Safety, security, and sensitive concerns use formal reporting pathways and cannot be converted into self-service checklists.',
                'requires_formal_reporting' => true,
            ], 422);
        }

        $checklist = $this->validChecklistSteps($entry->steps);
        if (empty($checklist)) {
            return response()->json([
                'message' => 'This guidance does not currently have a valid checklist, so a guided task cannot be started. Please contact the responsible office or submit a formal concern.',
            ], 422);
        }

        // Check if student already has an in-progress/paused task for this entry and version
        if (empty($validated['force_new'])) {
            $existing = SelfServiceTask::where('student_id', $student->id)
                ->where('kb_entry_id', $entry->id)
                ->whereIn('status', ['in_progress', 'paused', 'blocked'])
                ->latest('updated_at')
                ->first();

            if ($existing) {
                $existing->load('kbEntry');
                return response()->json([
                    'message' => 'Resumed your existing self-service task.',
                    'task' => $this->formatStudentTask($existing),
                    'resumed' => true,
                ]);
            }
        }

        $steps = $checklist;
        $totalSteps = count($steps);

        $task = SelfServiceTask::create([
            'student_id' => $student->id,
            'kb_entry_id' => $entry->id,
            'entry_code' => $entry->entry_code,
            'kb_version' => (int) $entry->version,
            'task_title' => $entry->title,
            'category' => $entry->category,
            'responsible_office' => $entry->responsible_office,
            'source_reference' => $entry->source_reference,
            'manual_page_number' => $entry->manual_page_number,
            'steps_snapshot' => $steps,
            'requirements_snapshot' => $entry->requirements ?? [],
            'completed_step_ids' => [],
            'blocked_steps' => [],
            'total_steps' => $totalSteps,
            'completed_steps_count' => 0,
            'progress_percentage' => 0,
            'status' => 'in_progress',
        ]);

        $task->load('kbEntry');

        return response()->json([
            'message' => 'Self-service guided checklist started.',
            'task' => $this->formatStudentTask($task),
            'resumed' => false,
        ], 201);
    }

    /**
     * Show a single self-service task owned by the authenticated student.
     */
    public function showTask(Request $request, int $id)
    {
        $task = SelfServiceTask::with('kbEntry')->find($id);

        if (!$task) {
            return response()->json(['message' => 'Self-service task not found.'], 404);
        }

        if ((int) $task->student_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized. You can only access your own self-service tasks.',
            ], 403);
        }

        return response()->json([
            'task' => $this->formatStudentTask($task),
        ]);
    }

    /**
     * Update progress on a student's self-service task:
     * - Toggle completed steps
     * - Save/pause progress
     * - Report that a step cannot be completed (blocked step)
     * - Finish the task (only when all steps are checked or explicitly finished as self-reported)
     *
     * STRICT RULES:
     * - Checking a single step in a multi-step procedure does NOT mark the entire task as completed.
     * - Student completion is self-reported and never creates or resolves a formal Concern record.
     */
    public function updateTask(Request $request, int $id)
    {
        $task = SelfServiceTask::with('kbEntry')->find($id);

        if (!$task) {
            return response()->json(['message' => 'Self-service task not found.'], 404);
        }

        if ((int) $task->student_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized. You cannot modify another student\'s self-service task.',
            ], 403);
        }

        $validated = $request->validate([
            'completed_step_ids' => 'nullable|array',
            'completed_step_ids.*' => 'integer|min:1',
            'status' => 'nullable|in:in_progress,paused,blocked,completed',
            'student_notes' => 'nullable|string|max:2000',
            'blocked_step_number' => 'nullable|integer|min:1',
            'blocked_reason' => 'nullable|string|max:1000',
        ]);

        $stepsSnapshot = is_array($task->steps_snapshot) ? $task->steps_snapshot : [];
        $validStepNumbers = collect($stepsSnapshot)
            ->pluck('step_number')
            ->map(fn ($n) => (int) $n)
            ->all();
        $totalSteps = count($validStepNumbers);

        $completedStepIds = $task->completed_step_ids ?? [];
        if (array_key_exists('completed_step_ids', $validated)) {
            $completedStepIds = collect($validated['completed_step_ids'] ?? [])
                ->map(fn ($n) => (int) $n)
                ->filter(fn ($n) => in_array($n, $validStepNumbers, true))
                ->unique()
                ->values()
                ->all();
        }

        $completedCount = count($completedStepIds);
        $percentage = $totalSteps > 0
            ? (int) round(($completedCount / $totalSteps) * 100)
            : 0;

        // Handle reporting a step that the student cannot complete
        $blockedSteps = is_array($task->blocked_steps) ? $task->blocked_steps : [];

        // Completing a step clears its previously reported blocker.
        $blockedSteps = collect($blockedSteps)
            ->reject(fn ($b) => in_array((int) ($b['step_number'] ?? 0), $completedStepIds, true))
            ->values()
            ->all();

        if (!empty($validated['blocked_step_number'])) {
            $stepNum = (int) $validated['blocked_step_number'];

            if (!in_array($stepNum, $validStepNumbers, true)) {
                return response()->json([
                    'message' => 'The reported step number does not exist in this checklist.',
                    'errors' => ['blocked_step_number' => ['Invalid step number for this task.']],
                ], 422);
            }

            if (in_array($stepNum, $completedStepIds, true)) {
                return response()->json([
                    'message' => 'A completed step cannot be reported as blocked. Uncheck it first.',
                    'errors' => ['blocked_step_number' => ['Step is already marked completed.']],
                ], 422);
            }

            $blockedSteps = collect($blockedSteps)
                ->reject(fn ($b) => (int) ($b['step_number'] ?? 0) === $stepNum)
                ->push([
                    'step_number' => $stepNum,
                    'reason' => trim((string) ($validated['blocked_reason'] ?? 'Unable to complete this step.')),
                    'reported_at' => now()->toIso8601String(),
                ])
                ->values()
                ->all();
        }

        // Determine task status:
        // Never mark a multi-step procedure as completed merely because one step is checked.
        $requestedStatus = $validated['status'] ?? null;
        if (!empty($validated['blocked_step_number'])) {
            $newStatus = 'blocked';
        } elseif ($requestedStatus === 'paused') {
            $newStatus = 'paused';
        } elseif ($requestedStatus === 'completed') {
            if ($totalSteps > 0 && $completedCount < $totalSteps) {
                return response()->json([
                    'message' => 'Please complete all steps in the checklist before marking the self-service task as finished, or pause your task to resume later.',
                ], 422);
            }
            $newStatus = 'completed';
        } elseif ($totalSteps > 0 && $completedCount === $totalSteps) {
            $newStatus = 'completed';
        } else {
            $newStatus = 'in_progress';
        }

        $task->update([
            'completed_step_ids' => $completedStepIds,
            'blocked_steps' => $blockedSteps,
            'total_steps' => $totalSteps,
            'completed_steps_count' => $completedCount,
            'progress_percentage' => $percentage,
            'status' => $newStatus,
            'student_notes' => array_key_exists('student_notes', $validated)
                ? $validated['student_notes']
                : $task->student_notes,
            'completed_at' => $newStatus === 'completed' ? ($task->completed_at ?? now()) : null,
        ]);

        return response()->json([
            'message' => 'Self-service task progress saved.',
            'task' => $this->formatStudentTask($task->fresh('kbEntry')),
        ]);
    }

    /**
     * Restart a task using the latest approved & published version of the knowledge base entry,
     * preserving the historical task record as 'restarted'.
     */
    public function restartTaskWithLatestVersion(Request $request, int $id)
    {
        $oldTask = SelfServiceTask::with('kbEntry')->find($id);

        if (!$oldTask) {
            return response()->json(['message' => 'Self-service task not found.'], 404);
        }

        if ((int) $oldTask->student_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized. You can only restart your own self-service tasks.',
            ], 403);
        }

        $entry = $oldTask->kbEntry;
        if (!$entry || !$entry->isPublishedForStudents()) {
            return response()->json([
                'message' => 'The latest version of this knowledge base entry is not currently published.',
            ], 422);
        }

        $restartChecklist = $this->validChecklistSteps($entry->steps);
        if (empty($restartChecklist)) {
            return response()->json([
                'message' => 'The latest guidance does not currently have a valid checklist, so the task cannot be restarted.',
            ], 422);
        }

        return DB::transaction(function () use ($oldTask, $entry, $request, $restartChecklist) {
            // Preserve the historical record untouched in its snapshot, marking status as 'restarted'
            $oldTask->update([
                'status' => 'restarted',
            ]);

            $steps = $restartChecklist;
            $newTask = SelfServiceTask::create([
                'student_id' => $request->user()->id,
                'kb_entry_id' => $entry->id,
                'entry_code' => $entry->entry_code,
                'kb_version' => (int) $entry->version,
                'task_title' => $entry->title,
                'category' => $entry->category,
                'responsible_office' => $entry->responsible_office,
                'source_reference' => $entry->source_reference,
                'manual_page_number' => $entry->manual_page_number,
                'steps_snapshot' => $steps,
                'requirements_snapshot' => $entry->requirements ?? [],
                'completed_step_ids' => [],
                'blocked_steps' => [],
                'total_steps' => count($steps),
                'completed_steps_count' => 0,
                'progress_percentage' => 0,
                'status' => 'in_progress',
            ]);

            $newTask->load('kbEntry');

            return response()->json([
                'message' => 'Task restarted using the latest approved guidance version. Your previous progress record was preserved.',
                'task' => $this->formatStudentTask($newTask),
                'previous_task_id' => $oldTask->id,
            ], 201);
        });
    }

    /**
     * Record helpful / not helpful feedback on a published knowledge base entry.
     * Strictly separate from formal concern submission.
     */
    public function submitFeedback(Request $request)
    {
        $validated = $request->validate([
            'kb_entry_id' => 'required|integer|exists:self_service_kb_entries,id',
            'task_id' => 'nullable|integer|exists:self_service_tasks,id',
            'is_helpful' => 'required|boolean',
            'comment' => 'nullable|string|max:1000',
        ]);

        $student = $request->user();
        $entry = SelfServiceKbEntry::find($validated['kb_entry_id']);

        if (!$entry || !$entry->isPublishedForStudents()) {
            return response()->json([
                'message' => 'Feedback can only be submitted for published knowledge base entries.',
            ], 403);
        }

        if (!empty($validated['task_id'])) {
            $task = SelfServiceTask::find($validated['task_id']);
            if (!$task || (int) $task->student_id !== (int) $student->id) {
                return response()->json([
                    'message' => 'Unauthorized task reference for feedback.',
                ], 403);
            }
        }

        $feedback = SelfServiceFeedback::updateOrCreate(
            [
                'student_id' => $student->id,
                'kb_entry_id' => $entry->id,
            ],
            [
                'task_id' => $validated['task_id'] ?? null,
                'is_helpful' => (bool) $validated['is_helpful'],
                'comment' => $validated['comment'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Thank you for your feedback on this self-service guidance.',
            'feedback' => $feedback,
        ], 201);
    }

    /**
     * Detect whether a search query or category selection involves a sensitive, urgent, or safety concern.
     */
    private function detectSensitiveGuardrail(string $queryText, string $selectedCategory): array
    {
        $lower = strtolower($queryText);
        $matchedPathways = [];

        if ($selectedCategory === 'Safety and Security') {
            foreach (['sexual_harassment', 'violence_bullying', 'hazing', 'disciplinary_complaint'] as $key) {
                $matchedPathways[$key] = self::SENSITIVE_PATTERNS[$key];
            }
        }

        if ($lower !== '') {
            foreach (self::SENSITIVE_PATTERNS as $type => $config) {
                foreach ($config['keywords'] as $kw) {
                    if (str_contains($lower, $kw)) {
                        $matchedPathways[$type] = $config;
                        break;
                    }
                }
            }
        }

        $pathwaysList = array_values($matchedPathways);
        $hasEmergency = collect($pathwaysList)->contains(fn ($p) => !empty($p['is_emergency']));

        return [
            'triggered' => !empty($pathwaysList),
            'is_emergency' => $hasEmergency,
            'bypass_self_service_tasks' => !empty($pathwaysList),
            'pathways' => $pathwaysList,
        ];
    }

    /**
     * Rank published knowledge base entries deterministically against the student's natural language query.
     */
    private function rankPublishedEntries($publishedEntries, string $queryText)
    {
        $lowerQuery = strtolower($queryText);
        $tokens = collect(preg_split('/[^a-z0-9]+/i', $lowerQuery))
            ->map(fn ($t) => trim($t))
            ->filter(fn ($t) => strlen($t) >= 2 && !in_array($t, ['how', 'the', 'and', 'for', 'can', 'what', 'where', 'when', 'who', 'why', 'with', 'from'], true))
            ->values()
            ->all();

        return $publishedEntries
            ->map(function (SelfServiceKbEntry $entry) use ($lowerQuery, $tokens) {
                $score = 0;
                $titleLower = strtolower($entry->title);
                $questionsLower = strtolower(implode(' ', $entry->common_questions ?? []));
                $answerLower = strtolower($entry->approved_answer);
                $officeLower = strtolower($entry->responsible_office);
                $categoryLower = strtolower($entry->category);

                if (str_contains($titleLower, $lowerQuery)) {
                    $score += 15;
                }
                if (str_contains($questionsLower, $lowerQuery)) {
                    $score += 12;
                }
                if (str_contains($answerLower, $lowerQuery)) {
                    $score += 8;
                }

                foreach ($tokens as $token) {
                    if (str_contains($titleLower, $token)) {
                        $score += 5;
                    }
                    if (str_contains($questionsLower, $token)) {
                        $score += 4;
                    }
                    if (str_contains($categoryLower, $token)) {
                        $score += 3;
                    }
                    if (str_contains($answerLower, $token)) {
                        $score += 2;
                    }
                    if (str_contains($officeLower, $token)) {
                        $score += 1;
                    }
                }

                return [
                    'score' => $score,
                    'entry' => $entry,
                ];
            })
            ->filter(fn ($item) => $item['score'] > 0)
            ->sortByDesc('score')
            ->map(fn ($item) => $this->formatStudentEntry($item['entry']))
            ->values();
    }

    /**
     * Format a published knowledge base entry for student consumption.
     * Internal approval metadata, reviewer identities, and supporting references are excluded.
     */
    private function formatStudentEntry(SelfServiceKbEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'entry_code' => $entry->entry_code,
            'title' => $entry->title,
            'category' => $entry->category,
            'common_questions' => $entry->common_questions ?? [],
            'approved_answer' => $entry->approved_answer,
            'steps' => $entry->steps ?? [],
            'requirements' => $entry->requirements ?? [],
            'responsible_office' => $entry->responsible_office,
            'source_reference' => $entry->source_reference,
            'manual_page_number' => $entry->manual_page_number,
            'is_sensitive' => (bool) $entry->is_sensitive,
            'sensitive_pathway_type' => $entry->sensitive_pathway_type,
            'version' => (int) $entry->version,
            'updated_at' => $entry->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Format a student's saved task, including outdated-guidance detection when the KB entry version advances.
     */
    private function formatStudentTask(SelfServiceTask $task): array
    {
        $latestEntry = $task->kbEntry;
        $latestPublishedVersion = ($latestEntry && $latestEntry->isPublishedForStudents())
            ? (int) $latestEntry->version
            : null;

        $isOutdatedVersion = $latestPublishedVersion !== null && (int) $task->kb_version < $latestPublishedVersion;
        $isEntryUnpublished = !$latestEntry || !$latestEntry->isPublishedForStudents();

        return [
            'id' => $task->id,
            'student_id' => $task->student_id,
            'kb_entry_id' => $task->kb_entry_id,
            'entry_code' => $task->entry_code,
            'kb_version' => (int) $task->kb_version,
            'latest_kb_version' => $latestPublishedVersion,
            'is_outdated_guidance' => $isOutdatedVersion || $isEntryUnpublished,
            'outdated_notice' => $isOutdatedVersion
                ? "This task was started using Version {$task->kb_version} of the guidance. Version {$latestPublishedVersion} is now available. Your saved instructions remain intact, or you can restart using the latest approved version."
                : ($isEntryUnpublished
                    ? 'This knowledge base entry is currently undergoing revision by UCLM personnel. Your saved progress record is preserved.'
                    : null),
            'can_restart_with_latest' => $isOutdatedVersion && !$isEntryUnpublished,
            'task_title' => $task->task_title,
            'category' => $task->category,
            'responsible_office' => $task->responsible_office,
            'source_reference' => $task->source_reference,
            'manual_page_number' => $task->manual_page_number,
            'steps' => $task->steps_snapshot ?? [],
            'requirements' => $task->requirements_snapshot ?? [],
            'completed_step_ids' => $task->completed_step_ids ?? [],
            'blocked_steps' => $task->blocked_steps ?? [],
            'total_steps' => (int) $task->total_steps,
            'completed_steps_count' => (int) $task->completed_steps_count,
            'progress_percentage' => (int) $task->progress_percentage,
            'status' => $task->status,
            'completion_type' => 'self_reported_student_checklist',
            'completion_disclaimer' => 'Checklist completion is self-reported for your personal tracking and is distinct from official university office processing.',
            'student_notes' => $task->student_notes,
            'created_at' => $task->created_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
            'completed_at' => $task->completed_at?->toIso8601String(),
        ];
    }

    /**
     * Return only structurally valid checklist steps (positive integer step_number and a title).
     */
    private function validChecklistSteps($steps): array
    {
        if (!is_array($steps)) {
            return [];
        }

        return array_values(array_filter($steps, function ($s) {
            return is_array($s)
                && isset($s['step_number']) && (int) $s['step_number'] >= 1
                && trim((string) ($s['title'] ?? '')) !== '';
        }));
    }

    /**
     * Suggest the appropriate university office when verified self-service guidance is not yet published.
     */
    private function suggestOfficeForUnavailableTopic(string $category, string $queryText): string
    {
        return match ($category) {
            'Academic' => 'Your Academic College Dean\'s Office / Department Chairperson or the Registrar\'s Office',
            'Enrollment' => 'Office of the University Registrar and your College Dean\'s Office',
            'Financial' => 'Accounting Department, Cashier\'s Office, or Scholarship Office',
            'Student ID' => 'Student Affairs Office (SAO) and Civil Security Unit (CSU)',
            'Library' => 'UCLM Library and Electronic Resource Services',
            'Facilities' => 'Building Administration Office or your College Dean\'s Office',
            'Technology' => 'Electronic Data Processing (EDP) / Information Resource and Technology Advancement Center',
            'Student Services' => 'Student Affairs Office (SAO)',
            'Medical' => 'University Medical and Dental Clinic',
            'Guidance' => 'Guidance and Counseling Services (Guidance Center)',
            'Safety and Security' => 'Student Affairs Office (SAO) and Civil Security Unit (CSU)',
            default => 'Student Affairs Office (SAO) or the relevant UCLM operational department',
        };
    }
}
