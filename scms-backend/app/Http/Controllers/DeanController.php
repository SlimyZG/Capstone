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

        // Direct concerns sent to "My Dean"
        $directConcerns = Concern::where('academic_unit_id', $academicUnitId)
            ->where('target_type', 'dean')
            ->whereIn('status', ['pending', 'in_progress'])
            ->with(['student' => function ($query) {
                $query->select('id', 'name', 'student_id_number', 'academic_unit_id');
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

        // Escalated concerns from department staff
        $escalatedConcerns = Concern::where('academic_unit_id', $academicUnitId)
            ->where('target_type', 'department')
            ->where('status', 'escalated')
            ->with(['student' => function ($query) {
                $query->select('id', 'name', 'student_id_number', 'academic_unit_id');
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

        return response()->json([
            'direct' => $directConcerns,
            'escalated' => $escalatedConcerns,
        ]);
    }

    /**
     * Approve an escalated concern — routes it back to department staff.
     */
    public function approve(Request $request, int $id)
    {
        $user = $request->user();

        $concern = Concern::where('concern_id', $id)
            ->where('academic_unit_id', $user->academic_unit_id)
            ->where('status', 'escalated')
            ->firstOrFail();

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $concern->update([
            'status' => 'approved',
            'last_updated' => now(),
        ]);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'approved',
            'performed_by' => $user->id,
            'notes' => $validated['notes'] ?? 'Approved by Dean.',
        ]);

        return response()->json([
            'message' => 'Concern approved. Routed back to department.',
            'concern' => $concern->fresh(['academicUnit', 'department']),
        ]);
    }

    /**
     * Reject an escalated concern — routes it back to department staff.
     */
    public function reject(Request $request, int $id)
    {
        $user = $request->user();

        $concern = Concern::where('concern_id', $id)
            ->where('academic_unit_id', $user->academic_unit_id)
            ->where('status', 'escalated')
            ->firstOrFail();

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
            'notes' => $validated['notes'] ?? 'Rejected by Dean.',
        ]);

        return response()->json([
            'message' => 'Concern rejected. Routed back to department.',
            'concern' => $concern->fresh(['academicUnit', 'department']),
        ]);
    }

    /**
     * Resolve a direct concern (target_type='dean').
     * Dean resolves it from start to finish.
     */
    public function resolve(Request $request, int $id)
    {
        $user = $request->user();

        $concern = Concern::where('concern_id', $id)
            ->where('academic_unit_id', $user->academic_unit_id)
            ->where('target_type', 'dean')
            ->whereIn('status', ['pending', 'in_progress'])
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
            'notes' => $validated['notes'] ?? 'Resolved by Dean.',
        ]);

        \App\Jobs\GenerateResolutionSummary::dispatch($concern);

        return response()->json([
            'message' => 'Concern resolved successfully.',
            'concern' => $concern->fresh(['academicUnit']),
        ]);
    }

    /**
     * Reply to a concern (send a message).
     */
    public function reply(Request $request, int $id)
    {
        $user = $request->user();

        $concern = Concern::where('concern_id', $id)
            ->where('academic_unit_id', $user->academic_unit_id)
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

        // Update status to in_progress if still pending (direct concerns)
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
     * Get messages for a specific concern.
     */
    public function getMessages(Request $request, int $id)
    {
        $user = $request->user();

        $concern = Concern::where('concern_id', $id)
            ->where('academic_unit_id', $user->academic_unit_id)
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
