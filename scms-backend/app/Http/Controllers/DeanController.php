<?php

namespace App\Http\Controllers;

use App\Models\Concern;
use App\Models\ConcernRouting;
use App\Models\Message;
use Illuminate\Http\Request;

class DeanController extends Controller
{
    /**
     * Get the Dean's inbox.
     * Separates concerns into:
     * 1. direct: target_type='dean' with matching academic_unit_id
     * 2. escalated: target_type='department' with status='escalated' and matching academic_unit_id
     */
    public function inbox(Request $request)
    {
        $user = $request->user();
        $academicUnitId = $user->academic_unit_id;

        if (!$academicUnitId) {
            return response()->json([
                'direct' => [],
                'escalated' => [],
                'stats' => [
                    'total_students' => 0,
                    'reports_generated' => 0,
                ],
            ]);
        }

        // Direct concerns sent to "My Dean" strictly within the Dean's academic unit
        $directConcerns = Concern::where('academic_unit_id', $academicUnitId)
            ->where(function ($q) {
                $q->where('workflow_type', 'academic')
                  ->orWhere('category', 'academic_units');
            })
            ->where('target_type', 'dean')
            ->whereIn('status', ['pending', 'in_progress'])
            ->with(['student' => function ($query) {
                $query->select('id', 'name', 'student_id_number', 'academic_unit_id', 'email');
            }, 'academicUnit', 'routingHistory.performer'])
            ->orderBy('submitted_at', 'desc')
            ->get()
            ->map(function ($concern) {
                if ($concern->is_anonymous) {
                    $concern->student->name = 'Anonymous';
                    $concern->student->student_id_number = '********';
                }
                return $concern;
            });

        // Escalated academic concerns strictly within the Dean's academic unit
        $escalatedQuery = Concern::where('academic_unit_id', $academicUnitId)
            ->where(function ($q) {
                $q->where('workflow_type', 'academic')
                  ->orWhere('category', 'academic_units');
            })
            ->where('status', 'escalated');
        
        $escalatedConcerns = $escalatedQuery
            ->with(['student' => function ($query) {
                $query->select('id', 'name', 'student_id_number', 'academic_unit_id', 'email');
            }, 'academicUnit', 'department', 'routingHistory.performer'])
            ->orderBy('last_updated', 'desc')
            ->get()
            ->map(function ($concern) {
                if ($concern->is_anonymous) {
                    $concern->student->name = 'Anonymous';
                    $concern->student->student_id_number = '********';
                }
                return $concern;
            });

        $totalStudents = \App\Models\ClassList::where('academic_unit_id', $academicUnitId)->count();

        // Read-only resolution-target status and historical prediction for each concern (not persisted)
        $sla = app(\App\Services\ConcernSlaService::class);
        $predictor = app(\App\Services\ResolutionPredictionService::class);
        $dataset = $predictor->getCleanHistoricalDataset();
        $directConcerns->each(function ($c) use ($sla, $predictor, $dataset) {
            $c->sla = $sla->describe($c);
            $c->prediction = $predictor->predict($c, $dataset);
        });
        $escalatedConcerns->each(function ($c) use ($sla, $predictor, $dataset) {
            $c->sla = $sla->describe($c);
            $c->prediction = $predictor->predict($c, $dataset);
        });

        return response()->json([
            'direct' => $directConcerns,
            'escalated' => $escalatedConcerns,
            'stats' => [
                'total_students' => $totalStudents,
                'reports_generated' => 0, // Placeholder until reports are implemented
            ],
        ]);
    }

    /**
     * Approve an escalated concern — routes it back to department staff,
     * or for Academic/CAD marks as approved by Dean/CAD.
     */
    public function approve(Request $request, int $id)
    {
        $user = $request->user();

        $query = Concern::where('concern_id', $id)->where('status', 'escalated');
        $this->scopeForDean($query, $user);
        $concern = $query->firstOrFail();

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $isCad = !empty($concern->forwarded_to_cad_at);
        $action = $isCad ? 'cad_approved' : 'dean_approved';
        $defaultNote = $isCad ? 'Approved by CAD (Campus Administration).' : 'Approved by Dean.';

        $concern->update([
            'status' => 'approved',
            'last_updated' => now(),
        ]);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => $action,
            'performed_by' => $user->id,
            'notes' => $validated['notes'] ?? $defaultNote,
        ]);

        // Notify Department Staff and Student about approval
        try {
            $staffQuery = \App\Models\User::where('role', 'dept_staff');
            if ($concern->workflow_type === 'academic' || $concern->category === 'academic_units') {
                $staffQuery->where('academic_unit_id', $concern->academic_unit_id);
            } else {
                $staffQuery->where('dept_id', $concern->dept_id);
            }
            foreach ($staffQuery->get() as $staff) {
                $staff->notify(new \App\Notifications\ConcernWorkflowNotification(
                    $concern->concern_id,
                    $isCad ? 'CAD Approved Concern' : 'Dean Approved Concern',
                    "Concern '{$concern->title}' has been approved by administration.",
                    $action,
                    $concern->workflow_type ?? 'academic',
                    "/dept-staff/concerns/{$concern->concern_id}"
                ));
            }

            if ($concern->student) {
                $concern->student->notify(new \App\Notifications\ConcernWorkflowNotification(
                    $concern->concern_id,
                    'Concern Approved',
                    "Your concern '{$concern->title}' has been approved by administration.",
                    $action,
                    $concern->workflow_type ?? 'academic',
                    "/student/concerns/{$concern->concern_id}"
                ));
            }
        } catch (\Throwable $ne) {
            \Illuminate\Support\Facades\Log::warning('Failed sending approval notification: ' . $ne->getMessage());
        }

        return response()->json([
            'message' => 'Concern approved successfully.',
            'concern' => $concern->fresh(['academicUnit', 'department']),
        ]);
    }

    /**
     * Return a concern for further action / revision (CAD or Dean return).
     */
    public function returnForAction(Request $request, int $id)
    {
        $user = $request->user();

        $query = Concern::where('concern_id', $id)->where('status', 'escalated');
        $this->scopeForDean($query, $user);
        $concern = $query->firstOrFail();

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $isCad = !empty($concern->forwarded_to_cad_at);
        $action = $isCad ? 'cad_returned' : 'dean_returned';
        $defaultNote = $isCad ? 'Returned by CAD for further action / revision.' : 'Returned by Dean for further action.';

        $concern->update([
            'status' => 'returned',
            'last_updated' => now(),
        ]);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => $action,
            'performed_by' => $user->id,
            'notes' => $validated['notes'] ?? $defaultNote,
        ]);

        // Notify Department Staff and Student about return
        try {
            $staffQuery = \App\Models\User::where('role', 'dept_staff');
            if ($concern->workflow_type === 'academic' || $concern->category === 'academic_units') {
                $staffQuery->where('academic_unit_id', $concern->academic_unit_id);
            } else {
                $staffQuery->where('dept_id', $concern->dept_id);
            }
            foreach ($staffQuery->get() as $staff) {
                $staff->notify(new \App\Notifications\ConcernWorkflowNotification(
                    $concern->concern_id,
                    $isCad ? 'CAD Returned Concern' : 'Dean Returned Concern',
                    "Concern '{$concern->title}' was returned for revision / further action.",
                    $action,
                    $concern->workflow_type ?? 'academic',
                    "/dept-staff/concerns/{$concern->concern_id}"
                ));
            }

            if ($concern->student) {
                $concern->student->notify(new \App\Notifications\ConcernWorkflowNotification(
                    $concern->concern_id,
                    'Concern Returned for Revision',
                    "Your concern '{$concern->title}' was returned by administration for revision or further action.",
                    $action,
                    $concern->workflow_type ?? 'academic',
                    "/student/concerns/{$concern->concern_id}"
                ));
            }
        } catch (\Throwable $ne) {
            \Illuminate\Support\Facades\Log::warning('Failed sending return notification: ' . $ne->getMessage());
        }

        return response()->json([
            'message' => 'Concern returned to department staff for further action.',
            'concern' => $concern->fresh(['academicUnit', 'department']),
        ]);
    }

    /**
     * Reject an escalated concern — routes it back to department staff.
     */
    public function reject(Request $request, int $id)
    {
        $user = $request->user();

        $query = Concern::where('concern_id', $id)->where('status', 'escalated');
        $this->scopeForDean($query, $user);
        $concern = $query->firstOrFail();

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $concern->update([
            'status' => 'rejected',
            'last_updated' => now(),
        ]);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'rejected',
            'performed_by' => $user->id,
            'notes' => $validated['notes'] ?? 'Rejected by Dean/CAD.',
        ]);

        return response()->json([
            'message' => 'Concern rejected. Routed back to department.',
            'concern' => $concern->fresh(['academicUnit', 'department']),
        ]);
    }

    /**
     * Resolve a concern directly as Dean (direct concern) or final closure.
     */
    public function resolve(Request $request, int $id)
    {
        $user = $request->user();

        $query = Concern::where('concern_id', $id);
        $this->scopeForDean($query, $user);
        $concern = $query->whereIn('status', ['pending', 'in_progress', 'approved', 'escalated'])->firstOrFail();

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $concern->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'last_updated' => now(),
        ]);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'resolved',
            'performed_by' => $user->id,
            'notes' => $validated['notes'] ?? 'Resolved by Dean.',
        ]);

        \App\Jobs\GenerateResolutionSummary::dispatch($concern);

        // Notify student that concern was resolved by Dean
        try {
            if ($concern->student) {
                $concern->student->notify(new \App\Notifications\ConcernWorkflowNotification(
                    $concern->concern_id,
                    'Concern Resolved by Dean',
                    "Your concern '{$concern->title}' has been resolved by the Dean.",
                    'resolved',
                    $concern->workflow_type ?? 'academic',
                    "/student/concerns/{$concern->concern_id}"
                ));
            }
        } catch (\Throwable $ne) {
            \Illuminate\Support\Facades\Log::warning('Failed sending dean resolve notification: ' . $ne->getMessage());
        }

        return response()->json([
            'message' => 'Concern resolved successfully.',
            'concern' => $concern->fresh(['academicUnit']),
        ]);
    }

    /**
     * Helper to scope concerns that this Dean is authorized to view/act upon.
     *
     * A concern enters the Dean's workflow only when:
     *   - It has been forwarded/escalated to the Dean (status = 'escalated')
     *   - It was submitted directly to the Dean (target_type = 'dean')
     *   - The Dean has already acted on it (status IN approved/returned/rejected/resolved)
     *   - It was forwarded to CAD for non-academic handling (forwarded_to_cad_at IS NOT NULL)
     *
     * Concerns that are still pending or in-progress under department review
     * (target_type = 'department', status IN ['pending','in_progress']) must NOT
     * be accessible here — those belong to the department workflow only.
     */
    private function scopeForDean($query, $user)
    {
        if ($user->academic_unit_id) {
            $query->where('academic_unit_id', $user->academic_unit_id)
                  ->where(function ($q) {
                      $q->where('workflow_type', 'academic')
                        ->orWhere('category', 'academic_units');
                  })
                  ->where(function ($inner) {
                      $inner->where('status', 'escalated')
                            ->orWhere('target_type', 'dean')
                            ->orWhereIn('status', ['approved', 'returned', 'rejected', 'resolved']);
                  });
        } else {
            // Dean with no unit assignment: fail-closed to prevent unauthorized access
            $query->whereRaw('1 = 0');
        }
        return $query;
    }

    /**
     * Get a specific concern's full details for the authorized Dean.
     * Preserves student anonymity by masking identifiers if is_anonymous is true.
     */
    public function show(Request $request, int $id)
    {
        $user = $request->user();

        $query = Concern::where('concern_id', $id);
        $this->scopeForDean($query, $user);

        $concern = $query->with([
            'academicUnit',
            'department',
            'routingHistory.performer',
            'priorityOverriddenBy:id,name,role',
            'messages.sender',
            'student' => function ($q) {
                $q->select('id', 'name', 'student_id_number', 'academic_unit_id', 'email');
            }
        ])->firstOrFail();

        // Mask student identity if anonymous
        if ($concern->is_anonymous && $concern->student) {
            $concern->student->name = 'Anonymous';
            $concern->student->student_id_number = '********';
            $concern->student->email = 'anonymous@university.edu';
        }

        $concern->sla = app(\App\Services\ConcernSlaService::class)->describe($concern);
        $concern->prediction = app(\App\Services\ResolutionPredictionService::class)->predict($concern);

        return response()->json([
            'concern' => $concern,
        ]);
    }

    /**
     * Reply to a concern (send a message).
     */
    public function reply(Request $request, int $id)
    {
        $user = $request->user();

        $query = Concern::where('concern_id', $id);
        $this->scopeForDean($query, $user);
        $concern = $query->firstOrFail();

        $validated = $request->validate([
            'body' => 'required|string',
            'is_internal' => 'boolean',
        ]);

        $message = Message::create([
            'concern_id' => $concern->concern_id,
            'sender_id' => $user->id,
            'body' => $validated['body'],
            'is_internal' => $validated['is_internal'] ?? false,
        ]);

        // Update status to in_progress if still pending (direct concerns)
        if ($concern->status === 'pending') {
            $concern->update([
                'status' => 'in_progress',
                'last_updated' => now(),
            ]);
        } else {
            $concern->update(['last_updated' => now()]);
        }

        // Dispatch in-app notifications to authorized concern participants
        \App\Notifications\ConcernWorkflowNotification::sendNewMessageNotification(
            $concern,
            $message,
            $user
        );

        return response()->json([
            'message' => 'Reply sent successfully.',
            'data' => $message->load('sender'),
        ], 201);
    }

    /**
     * Get messages for a specific concern.
     */
    public function getMessages(Request $request, int $id)
    {
        $user = $request->user();

        $query = Concern::where('concern_id', $id);
        $this->scopeForDean($query, $user);
        $concern = $query->firstOrFail();

        $messages = Message::where('concern_id', $concern->concern_id)
            ->with('sender:id,name,role')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'messages' => $messages,
        ]);
    }

    /**
     * Override priority for a concern within the authorized Dean's workflow.
     */
    public function priorityOverride(Request $request, int $id)
    {
        $user = $request->user();

        // Check if concern exists at all
        $concern = Concern::find($id);
        if (!$concern) {
            return response()->json(['message' => 'Concern not found.'], 404);
        }

        // Verify that this concern is within the authorized Dean workflow scope
        $authorizedConcern = $this->scopeForDean(Concern::where('concern_id', $id), $user)->first();
        if (!$authorizedConcern) {
            return response()->json([
                'message' => 'Unauthorized action for this concern.',
            ], 403);
        }

        $validated = $request->validate([
            'priority' => 'required|in:low,medium,high,critical',
            'reason'   => 'required|string|min:5|max:1000',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($authorizedConcern, $validated, $user) {
            $oldPriority = $authorizedConcern->priority;

            $authorizedConcern->update([
                'priority'                 => $validated['priority'],
                'is_priority_overridden'   => true,
                'priority_override_reason' => $validated['reason'],
                'priority_overridden_by'   => $user->id,
                'priority_overridden_at'   => now(),
                'last_updated'             => now(),
            ]);

            ConcernRouting::create([
                'concern_id'   => $authorizedConcern->concern_id,
                'action'       => 'priority_overridden',
                'performed_by' => $user->id,
                'notes'        => "Priority overridden from '{$oldPriority}' to '{$validated['priority']}'. Reason: {$validated['reason']}",
            ]);
        });

        if ($validated['priority'] === 'critical') {
            try {
                \App\Notifications\ConcernWorkflowNotification::sendCriticalConcernNotification(
                    $authorizedConcern->fresh(),
                    'Dean Priority Override'
                );
            } catch (\Throwable $ce) {
                \Illuminate\Support\Facades\Log::warning('Critical concern notification failed on Dean override: ' . $ce->getMessage());
            }
        }

        $freshConcern = $authorizedConcern->fresh([
            'academicUnit',
            'department',
            'priorityOverriddenBy:id,name,role',
            'routingHistory.performer:id,name,role',
        ]);
        $freshConcern->sla = app(\App\Services\ConcernSlaService::class)->describe($freshConcern);
        $freshConcern->prediction = app(\App\Services\ResolutionPredictionService::class)->predict($freshConcern);

        return response()->json([
            'message' => 'Priority overridden successfully.',
            'concern' => $freshConcern,
        ]);
    }
}
