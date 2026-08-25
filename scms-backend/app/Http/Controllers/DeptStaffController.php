<?php

namespace App\Http\Controllers;

use App\Models\Concern;
use App\Models\ConcernRouting;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;

class DeptStaffController extends Controller
{
    /**
     * Get the queue of concerns routed to this staff's department.
     * Shows concerns with target_type='department' and matching dept_id.
     * Also shows concerns that were approved/rejected by the dean back to this department.
     */
    public function queue(Request $request)
    {
        $user = $request->user();

        $concerns = Concern::where(function ($q) use ($user) {
                if ($user->dept_id) $q->where('dept_id', $user->dept_id);
                if ($user->academic_unit_id) $q->where('academic_unit_id', $user->academic_unit_id);
            })
            ->where('target_type', 'department')
            ->whereIn('status', ['pending', 'in_progress', 'approved', 'rejected', 'resolved'])
            ->with(['student' => function ($query) {
                $query->select('id', 'name', 'student_id_number', 'academic_unit_id');
            }, 'academicUnit', 'department', 'routingHistory.performer'])
            ->orderBy('submitted_at', 'desc')
            ->get()
            ->map(function ($concern) {
                // Mask student identity if anonymous
                if ($concern->is_anonymous) {
                    $concern->student->name = 'Anonymous';
                    $concern->student->student_id_number = '********';
                }
                return $concern;
            });

        return response()->json([
            'concerns' => $concerns,
        ]);
    }

    /**
     * Reply to a concern (send a message to the student).
     */
    public function reply(Request $request, int $id)
    {
        $user = $request->user();

        $concern = Concern::where('concern_id', $id)
            ->where(function ($q) use ($user) {
                if ($user->dept_id) $q->where('dept_id', $user->dept_id);
                if ($user->academic_unit_id) $q->where('academic_unit_id', $user->academic_unit_id);
            })
            ->firstOrFail();

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

        // Update status to in_progress if still pending
        if ($concern->status === 'pending') {
            $concern->update([
                'status' => 'in_progress',
                'last_updated' => now(),
            ]);
        } else {
            $concern->update(['last_updated' => now()]);
        }

        return response()->json([
            'message' => 'Reply sent successfully.',
            'data' => $message->load('sender'),
        ], 201);
    }

    /**
     * Resolve a concern directly.
     */
    public function resolve(Request $request, int $id)
    {
        $user = $request->user();

        $concern = Concern::where('concern_id', $id)
            ->where(function ($q) use ($user) {
                if ($user->dept_id) $q->where('dept_id', $user->dept_id);
                if ($user->academic_unit_id) $q->where('academic_unit_id', $user->academic_unit_id);
            })
            ->whereIn('status', ['pending', 'in_progress', 'approved'])
            ->firstOrFail();

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
            'notes' => $validated['notes'] ?? 'Resolved by department staff.',
        ]);

        \App\Jobs\GenerateResolutionSummary::dispatch($concern);

        return response()->json([
            'message' => 'Concern resolved successfully.',
            'concern' => $concern->fresh(['academicUnit', 'department']),
        ]);
    }

    /**
     * Update the status of a concern (pending, in_progress, resolved).
     */
    public function updateStatus(Request $request, int $id)
    {
        $user = $request->user();

        $concern = Concern::where('concern_id', $id)
            ->where(function ($q) use ($user) {
                if ($user->dept_id) $q->where('dept_id', $user->dept_id);
                if ($user->academic_unit_id) $q->where('academic_unit_id', $user->academic_unit_id);
            })
            ->firstOrFail();

        $validated = $request->validate([
            'status' => 'required|in:pending,in_progress,resolved',
            'notes' => 'nullable|string',
        ]);

        $updateData = [
            'status' => $validated['status'],
            'last_updated' => now(),
        ];
        
        if ($validated['status'] === 'resolved') {
            $updateData['resolved_at'] = now();
        }

        $concern->update($updateData);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'status_changed',
            'performed_by' => $user->id,
            'notes' => 'Status changed to ' . str_replace('_', ' ', $validated['status']) . '. ' . ($validated['notes'] ? 'Notes: ' . $validated['notes'] : ''),
        ]);

        if ($validated['status'] === 'resolved') {
            \App\Jobs\GenerateResolutionSummary::dispatch($concern);
        }

        return response()->json([
            'message' => 'Concern status updated successfully.',
            'concern' => $concern->fresh(['academicUnit', 'department']),
        ]);
    }

    /**
     * Escalate a concern to the Dean of the student's academic unit.
     */
    public function escalate(Request $request, int $id)
    {
        $user = $request->user();

        $concern = Concern::where('concern_id', $id)
            ->where(function ($q) use ($user) {
                if ($user->dept_id) $q->where('dept_id', $user->dept_id);
                if ($user->academic_unit_id) $q->where('academic_unit_id', $user->academic_unit_id);
            })
            ->whereIn('status', ['pending', 'in_progress'])
            ->firstOrFail();

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $concern->update([
            'status' => 'escalated',
            'last_updated' => now(),
        ]);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'escalated_to_dean',
            'performed_by' => $user->id,
            'notes' => $validated['notes'] ?? 'Escalated to Dean for review.',
        ]);

        return response()->json([
            'message' => 'Concern escalated to Dean successfully.',
            'concern' => $concern->fresh(['academicUnit', 'department']),
        ]);
    }

    /**
     * Transfer a concern to a different department or academic unit.
     */
    public function transfer(Request $request, int $id)
    {
        $user = $request->user();

        $concern = Concern::where('concern_id', $id)
            ->where(function ($q) use ($user) {
                if ($user->dept_id) $q->where('dept_id', $user->dept_id);
                if ($user->academic_unit_id) $q->where('academic_unit_id', $user->academic_unit_id);
            })
            ->whereIn('status', ['pending', 'in_progress'])
            ->firstOrFail();

        $validated = $request->validate([
            'target_category' => 'required|in:operational_departments,academic_units',
            'target_id' => 'required|integer',
            'notes' => 'nullable|string',
        ]);

        $oldTarget = $concern->category === 'academic_units' ? ($concern->academicUnit->name ?? 'Unknown') : ($concern->department->name ?? 'Unknown');

        // Update target
        if ($validated['target_category'] === 'academic_units') {
            $unit = \App\Models\AcademicUnit::findOrFail($validated['target_id']);
            $concern->update([
                'category' => 'academic_units',
                'academic_unit_id' => $unit->id,
                'dept_id' => null,
                'transferred_from' => $oldTarget,
                'last_updated' => now(),
            ]);
            $newTarget = $unit->name;
        } else {
            $dept = \App\Models\Department::findOrFail($validated['target_id']);
            $concern->update([
                'category' => 'operational_departments',
                'academic_unit_id' => null,
                'dept_id' => $dept->id,
                'transferred_from' => $oldTarget,
                'last_updated' => now(),
            ]);
            $newTarget = $dept->name;
        }

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'transferred',
            'performed_by' => $user->id,
            'notes' => "Transferred from {$oldTarget} to {$newTarget}. " . ($validated['notes'] ? "Reason: " . $validated['notes'] : ''),
        ]);

        return response()->json([
            'message' => 'Concern transferred successfully.',
        ]);
    }

    /**
     * Get messages for a specific concern.
     */
    public function getMessages(Request $request, int $id)
    {
        $user = $request->user();

        $concern = Concern::where('concern_id', $id)
            ->where(function ($q) use ($user) {
                if ($user->dept_id) $q->where('dept_id', $user->dept_id);
                if ($user->academic_unit_id) $q->where('academic_unit_id', $user->academic_unit_id);
            })
            ->firstOrFail();

        $messages = Message::where('concern_id', $concern->concern_id)
            ->with('sender:id,name,role')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'messages' => $messages,
        ]);
    }
}
