<?php

namespace App\Http\Controllers;

use App\Models\Concern;
use App\Models\RecurringIncident;
use App\Models\RecurringIncidentHistory;
use App\Services\RecurrenceDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecurringIncidentController extends Controller
{
    protected RecurrenceDetectionService $detectionService;

    public function __construct(RecurrenceDetectionService $detectionService)
    {
        $this->detectionService = $detectionService;
    }

    /**
     * -------------------------------------------------------------
     * SAO Endpoints (Institution-Wide with Option A Privacy)
     * -------------------------------------------------------------
     */

    public function saoIndex(Request $request): JsonResponse
    {
        $query = RecurringIncident::with([
            'academicUnit:id,name',
            'department:id,name',
            'priorityOverriddenBy:id,name,role',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('recurrence_tier')) {
            $query->where('recurrence_tier', $request->recurrence_tier);
        }
        if ($request->filled('workflow_type')) {
            $query->where('workflow_type', $request->workflow_type);
        }
        if ($request->filled('academic_unit_id')) {
            $query->where('academic_unit_id', $request->academic_unit_id);
        }
        if ($request->filled('dept_id')) {
            $query->where('dept_id', $request->dept_id);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                  ->orWhere('incident_code', 'like', "%{$s}%")
                  ->orWhere('summary', 'like', "%{$s}%");
            });
        }

        $incidents = $query->orderByDesc('latest_reported_at')->get();

        return response()->json([
            'success' => true,
            'data'    => $incidents,
        ]);
    }

    public function saoShow(Request $request, int $id): JsonResponse
    {
        $incident = RecurringIncident::with([
            'academicUnit:id,name',
            'department:id,name',
            'priorityOverriddenBy:id,name,role',
            'history' => fn ($q) => $q->orderByDesc('created_at'),
        ])->find($id);

        if (!$incident) {
            return response()->json(['message' => 'Recurring incident not found.'], 404);
        }

        $isAssignedToSao = $this->isIncidentAssignedToSao($incident);

        if ($isAssignedToSao) {
            $incident->load([
                'concerns' => fn ($q) => $q->select([
                    'concern_id', 'recurring_incident_id', 'title', 'workflow_type',
                    'category', 'status', 'priority', 'submitted_at', 'academic_unit_id', 'dept_id',
                ])->orderByDesc('submitted_at'),
            ]);

            // Option A Protection: Ensure no student personal identity is attached
            $incident->concerns->each(function ($c) {
                $c->unsetRelation('student');
                $c->makeHidden(['student_id', 'student']);
            });
        } else {
            // Option A: Aggregate-only monitoring for incidents outside SAO department.
            // Do not expose individual student concern details.
            $incident->setRelation('concerns', collect([]));
        }

        $incidentArray = $incident->toArray();
        $incidentArray['is_sao_assigned'] = $isAssignedToSao;

        return response()->json([
            'success' => true,
            'data'    => $incidentArray,
        ]);
    }

    public function saoPriorityOverride(Request $request, int $id): JsonResponse
    {
        $incident = RecurringIncident::find($id);
        if (!$incident) {
            return response()->json(['message' => 'Recurring incident not found.'], 404);
        }

        if (!$this->isIncidentAssignedToSao($incident)) {
            return response()->json([
                'message' => 'Unauthorized. In accordance with SAO Option A boundaries, SAO priority overrides are strictly restricted to recurring incidents assigned to the Student Affairs Office.',
            ], 403);
        }

        $validated = $request->validate([
            'priority' => 'required|in:low,medium,high,critical',
            'reason'   => 'required|string|min:5|max:1000',
        ]);

        $user = $request->user();

        DB::transaction(function () use ($incident, $validated, $user) {
            $oldPriority = $incident->priority;
            $incident->update([
                'priority'                 => $validated['priority'],
                'is_priority_overridden'   => true,
                'priority_override_reason' => $validated['reason'],
                'priority_overridden_by'   => $user->id,
                'priority_overridden_at'   => now(),
            ]);

            RecurringIncidentHistory::create([
                'recurring_incident_id'    => $incident->id,
                'action'                   => 'priority_overridden',
                'performed_by'             => $user->id,
                'performed_by_name'        => $user->name,
                'notes'                    => "Priority manually overridden from '{$oldPriority}' to '{$validated['priority']}' by SAO. Reason: {$validated['reason']}",
                'unique_students_snapshot' => $incident->unique_students_count,
                'created_at'               => now(),
            ]);
        });

        return response()->json([
            'message' => 'Recurring incident priority overridden successfully.',
            'data'    => $incident->fresh(['academicUnit', 'department', 'priorityOverriddenBy:id,name,role', 'history']),
        ]);
    }

    public function saoUnlink(Request $request, int $incidentId, int $concernId): JsonResponse
    {
        $incident = RecurringIncident::find($incidentId);
        if (!$incident) {
            return response()->json(['message' => 'Recurring incident not found.'], 404);
        }

        if (!$this->isIncidentAssignedToSao($incident)) {
            return response()->json([
                'message' => 'Unauthorized. SAO can only manage clusters explicitly assigned to the Student Affairs Office.',
            ], 403);
        }

        $concern = Concern::where('concern_id', $concernId)
            ->where('recurring_incident_id', $incidentId)
            ->first();

        if (!$concern) {
            return response()->json(['message' => 'Concern not found or not linked to this incident.'], 404);
        }

        $this->detectionService->unlinkConcern($concern, $request->user()->id);

        return response()->json([
            'message' => 'Concern unlinked successfully.',
            'data'    => $incident->fresh(['academicUnit', 'department', 'history']),
        ]);
    }

    /**
     * -------------------------------------------------------------
     * Dean Endpoints (Scoped Strictly to Dean's Academic Unit)
     * -------------------------------------------------------------
     */

    public function deanIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->academic_unit_id) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $query = RecurringIncident::where('workflow_type', 'academic')
            ->where('academic_unit_id', $user->academic_unit_id)
            ->with([
                'academicUnit:id,name',
                'department:id,name',
                'priorityOverriddenBy:id,name,role',
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('recurrence_tier')) {
            $query->where('recurrence_tier', $request->recurrence_tier);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                  ->orWhere('incident_code', 'like', "%{$s}%")
                  ->orWhere('summary', 'like', "%{$s}%");
            });
        }

        $incidents = $query->orderByDesc('latest_reported_at')->get();

        return response()->json([
            'success' => true,
            'data'    => $incidents,
        ]);
    }

    public function deanShow(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $incident = RecurringIncident::find($id);

        if (!$incident) {
            return response()->json(['message' => 'Recurring incident not found.'], 404);
        }

        if ($incident->workflow_type !== 'academic' || $incident->academic_unit_id !== $user->academic_unit_id) {
            return response()->json(['message' => 'Unauthorized access to recurring incident outside your academic unit.'], 403);
        }

        $incident->load([
            'academicUnit:id,name',
            'department:id,name',
            'priorityOverriddenBy:id,name,role',
            'history' => fn ($q) => $q->orderByDesc('created_at'),
            'concerns' => fn ($q) => $q->with('student:id,name,student_id_number,email')->orderByDesc('submitted_at'),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $incident,
        ]);
    }

    public function deanPriorityOverride(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $incident = RecurringIncident::find($id);

        if (!$incident) {
            return response()->json(['message' => 'Recurring incident not found.'], 404);
        }

        if ($incident->workflow_type !== 'academic' || $incident->academic_unit_id !== $user->academic_unit_id) {
            return response()->json(['message' => 'Unauthorized. Deans can only override recurring incidents in their academic unit.'], 403);
        }

        $validated = $request->validate([
            'priority' => 'required|in:low,medium,high,critical',
            'reason'   => 'required|string|min:5|max:1000',
        ]);

        DB::transaction(function () use ($incident, $validated, $user) {
            $oldPriority = $incident->priority;
            $incident->update([
                'priority'                 => $validated['priority'],
                'is_priority_overridden'   => true,
                'priority_override_reason' => $validated['reason'],
                'priority_overridden_by'   => $user->id,
                'priority_overridden_at'   => now(),
            ]);

            RecurringIncidentHistory::create([
                'recurring_incident_id'    => $incident->id,
                'action'                   => 'priority_overridden',
                'performed_by'             => $user->id,
                'performed_by_name'        => $user->name,
                'notes'                    => "Priority manually overridden from '{$oldPriority}' to '{$validated['priority']}' by Dean. Reason: {$validated['reason']}",
                'unique_students_snapshot' => $incident->unique_students_count,
                'created_at'               => now(),
            ]);
        });

        return response()->json([
            'message' => 'Recurring incident priority overridden successfully.',
            'data'    => $incident->fresh(['academicUnit', 'department', 'priorityOverriddenBy:id,name,role', 'history']),
        ]);
    }

    public function deanUnlink(Request $request, int $incidentId, int $concernId): JsonResponse
    {
        $user = $request->user();
        $incident = RecurringIncident::find($incidentId);

        if (!$incident) {
            return response()->json(['message' => 'Recurring incident not found.'], 404);
        }

        if ($incident->workflow_type !== 'academic' || $incident->academic_unit_id !== $user->academic_unit_id) {
            return response()->json(['message' => 'Unauthorized action for this incident.'], 403);
        }

        $concern = Concern::where('concern_id', $concernId)
            ->where('recurring_incident_id', $incidentId)
            ->first();

        if (!$concern) {
            return response()->json(['message' => 'Concern not found or not linked to this incident.'], 404);
        }

        $this->detectionService->unlinkConcern($concern, $user->id);

        return response()->json([
            'message' => 'Concern unlinked successfully.',
            'data'    => $incident->fresh(['academicUnit', 'department', 'history']),
        ]);
    }

    /**
     * -------------------------------------------------------------
     * Department Staff Endpoints (Scoped Strictly to Assigned Scope)
     * -------------------------------------------------------------
     */

    public function deptStaffIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = RecurringIncident::with([
            'academicUnit:id,name',
            'department:id,name',
            'priorityOverriddenBy:id,name,role',
        ]);

        $this->scopeForStaff($query, $user);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('recurrence_tier')) {
            $query->where('recurrence_tier', $request->recurrence_tier);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                  ->orWhere('incident_code', 'like', "%{$s}%")
                  ->orWhere('summary', 'like', "%{$s}%");
            });
        }

        $incidents = $query->orderByDesc('latest_reported_at')->get();

        return response()->json([
            'success' => true,
            'data'    => $incidents,
        ]);
    }

    public function deptStaffShow(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $incident = RecurringIncident::find($id);

        if (!$incident) {
            return response()->json(['message' => 'Recurring incident not found.'], 404);
        }

        if (!$this->isStaffAuthorizedForIncident($user, $incident)) {
            return response()->json(['message' => 'Unauthorized access to recurring incident outside your department.'], 403);
        }

        $incident->load([
            'academicUnit:id,name',
            'department:id,name',
            'priorityOverriddenBy:id,name,role',
            'history' => fn ($q) => $q->orderByDesc('created_at'),
            'concerns' => fn ($q) => $q->with('student:id,name,student_id_number,email')->orderByDesc('submitted_at'),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $incident,
        ]);
    }

    public function deptStaffUnlink(Request $request, int $incidentId, int $concernId): JsonResponse
    {
        $user = $request->user();
        $incident = RecurringIncident::find($incidentId);

        if (!$incident) {
            return response()->json(['message' => 'Recurring incident not found.'], 404);
        }

        if (!$this->isStaffAuthorizedForIncident($user, $incident)) {
            return response()->json(['message' => 'Unauthorized action for this incident.'], 403);
        }

        $concern = Concern::where('concern_id', $concernId)
            ->where('recurring_incident_id', $incidentId)
            ->first();

        if (!$concern) {
            return response()->json(['message' => 'Concern not found or not linked to this incident.'], 404);
        }

        $this->detectionService->unlinkConcern($concern, $user->id);

        return response()->json([
            'message' => 'Concern unlinked successfully.',
            'data'    => $incident->fresh(['academicUnit', 'department', 'history']),
        ]);
    }

    public function deptStaffPriorityOverride(Request $request, int $id): JsonResponse
    {
        return response()->json([
            'message' => 'Department staff are not authorized to override recurring incident priority.',
        ], 403);
    }

    /**
     * Helpers for Staff Scoping
     */
    private function scopeForStaff($query, $user): void
    {
        if ($user->academic_unit_id) {
            $query->where('workflow_type', 'academic')
                  ->where('academic_unit_id', $user->academic_unit_id);
        } elseif ($user->dept_id) {
            $query->where('workflow_type', 'non_academic')
                  ->where('dept_id', $user->dept_id);
        } else {
            $query->whereRaw('1 = 0');
        }
    }

    private function isStaffAuthorizedForIncident($user, RecurringIncident $incident): bool
    {
        if ($incident->workflow_type === 'academic') {
            return (int)$incident->academic_unit_id === (int)$user->academic_unit_id;
        }
        return (int)$incident->dept_id === (int)$user->dept_id;
    }

    /**
     * Check if a recurring incident is assigned to the Student Affairs Office (SAO).
     */
    private function isIncidentAssignedToSao(RecurringIncident $incident): bool
    {
        if ($incident->workflow_type !== 'non_academic') {
            return false;
        }

        $saoDept = \App\Models\Department::where('name', 'like', '%SAO%')
            ->orWhere('name', 'like', '%Student Affairs%')
            ->first();

        return $saoDept && (int)$incident->dept_id === (int)$saoDept->id;
    }
}
