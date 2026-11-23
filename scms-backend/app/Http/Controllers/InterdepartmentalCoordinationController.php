<?php

namespace App\Http\Controllers;

use App\Models\AcademicUnit;
use App\Models\Concern;
use App\Models\ConcernCollaboration;
use App\Models\ConcernInternalMessage;
use App\Models\ConcernRouting;
use App\Models\ConcernTransferRequest;
use App\Models\Department;
use App\Models\PcoApprovalEvent;
use App\Models\PcoApprovalRequest;
use App\Models\User;
use App\Notifications\ConcernWorkflowNotification;
use App\Services\RecurrenceDetectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InterdepartmentalCoordinationController extends Controller
{
    // ── Helper: Resolve User's Effective Department / Unit Context ────────────

    /**
     * Resolve the effective operational department ID for the authenticated user.
     * For role='sao' users without an explicit dept_id set, resolves the SAO department ID.
     */
    public static function resolveUserDeptId(User $user): ?int
    {
        if (!empty($user->dept_id)) {
            return (int) $user->dept_id;
        }

        if ($user->role === 'sao') {
            $saoDept = Department::where('name', 'like', '%SAO%')
                ->orWhere('name', 'like', '%Student Affairs%')
                ->first();
            return $saoDept ? (int) $saoDept->id : null;
        }

        return null;
    }

    /**
     * Resolve the human-readable office name for the user.
     */
    public static function resolveUserOfficeName(User $user): string
    {
        $deptId = self::resolveUserDeptId($user);
        if ($deptId) {
            $dept = Department::find($deptId);
            if ($dept) {
                return $dept->name;
            }
        }

        if ($user->academic_unit_id) {
            $unit = AcademicUnit::find($user->academic_unit_id);
            if ($unit) {
                return $unit->name;
            }
        }

        return match ($user->role) {
            'sao' => 'Student Affairs Office (SAO)',
            'dean' => 'Academic Dean\'s Office',
            default => 'Department Staff',
        };
    }

    /**
     * Determine whether the authenticated user is the CURRENT primary owner of the concern.
     */
    public static function isPrimaryOwner(User $user, Concern $concern): bool
    {
        $userDeptId = self::resolveUserDeptId($user);

        if ($userDeptId && $concern->dept_id && (int) $concern->dept_id === $userDeptId) {
            return true;
        }

        $isAcademic = $concern->workflow_type === 'academic'
            || ($concern->category === 'academic_units' && is_null($concern->dept_id));

        if ($isAcademic && $user->academic_unit_id && (int) $concern->academic_unit_id === (int) $user->academic_unit_id) {
            if ($user->role === 'dept_staff') {
                return true;
            }
            if ($user->role === 'dean' && ($concern->target_type === 'dean' || in_array($concern->status, ['escalated', 'approved', 'returned', 'rejected', 'resolved'], true))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine the authenticated user's coordination access mode on a concern:
     * - 'primary': Current primary owner (full control)
     * - 'active_collaborator': Accepted collaborator (full read + internal messages + complete collaboration)
     * - 'completed_collaborator': Completed collaborator (historical read access + read internal messages; cannot act)
     * - 'pending_collaborator': Invited collaborator awaiting decision (preview + accept/decline collaboration)
     * - 'pending_transfer_target': Target of a pending transfer request (LIMITED preview + handoff context ONLY; NO internal messages)
     * - 'pco_approver': Designated approving department/unit on an active or historical PCO approval request (scoped approval access + internal messages)
     * - null: No access
     */
    public static function resolveAccessMode(User $user, Concern $concern): ?string
    {
        if ($user->role === 'student') {
            return null;
        }

        if (self::isPrimaryOwner($user, $concern)) {
            return 'primary';
        }

        $userDeptId = self::resolveUserDeptId($user);
        $userUnitId = $user->academic_unit_id ? (int) $user->academic_unit_id : null;

        // 1. Check active (accepted) collaboration
        if ($userDeptId) {
            $collabStatuses = ConcernCollaboration::where('concern_id', $concern->concern_id)
                ->where('collaborator_dept_id', $userDeptId)
                ->pluck('status')
                ->all();

            if (in_array('accepted', $collabStatuses, true)) {
                return 'active_collaborator';
            }
        }

        // 2. Check PCO approving department / academic unit role (active vs historical read-only)
        $currentPco = self::getCurrentPcoApprovalRequest($concern);
        if (
            $currentPco
            && in_array($currentPco->status, ['pending', 'clarification_requested', 'approved'], true)
            && self::checkUserAuthorizedPcoApprover($user, $currentPco)
        ) {
            return 'pco_approver';
        }

        // Check historical PCO approver access (rejected or superseded requests; NEVER cancelled requests)
        $historicalPcoRequests = PcoApprovalRequest::where('concern_id', $concern->concern_id)
            ->whereIn('status', ['rejected', 'superseded'])
            ->get();

        foreach ($historicalPcoRequests as $histReq) {
            if (self::checkUserAuthorizedPcoApprover($user, $histReq)) {
                return 'pco_approver_readonly';
            }
        }

        // 3. Check completed collaboration (historical read-only access)
        if ($userDeptId && in_array('completed', $collabStatuses ?? [], true)) {
            return 'completed_collaborator';
        }

        // 4. Check pending collaboration invitation
        if ($userDeptId && in_array('pending', $collabStatuses ?? [], true)) {
            return 'pending_collaborator';
        }

        // 5. Check pending transfer target (LIMITED preview only; NO full internal message history)
        if ($userDeptId) {
            $isPendingTransferTarget = ConcernTransferRequest::where('concern_id', $concern->concern_id)
                ->where('to_dept_id', $userDeptId)
                ->where('status', 'pending')
                ->exists();

            if ($isPendingTransferTarget) {
                return 'pending_transfer_target';
            }
        }

        return null;
    }

    /**
     * Check whether the user is permitted to read the full internal department message history.
     * STRICT SECURITY REQUIREMENT: A pending transfer target (or pending collaborator who hasn't accepted)
     * must NOT read the full internal message history until acceptance.
     */
    public static function canReadInternalMessages(User $user, Concern $concern): bool
    {
        $mode = self::resolveAccessMode($user, $concern);
        return in_array($mode, ['primary', 'active_collaborator', 'completed_collaborator', 'pco_approver', 'pco_approver_readonly'], true);
    }

    /**
     * Check whether the user is permitted to post a new internal department message.
     * Completed collaborators and historical/rejected/superseded PCO approvers have read-only access and cannot continue posting.
     */
    public static function canPostInternalMessages(User $user, Concern $concern): bool
    {
        $mode = self::resolveAccessMode($user, $concern);
        if (in_array($mode, ['primary', 'active_collaborator'], true)) {
            return true;
        }
        if ($mode === 'pco_approver') {
            $currentPco = self::getCurrentPcoApprovalRequest($concern);
            return $currentPco
                && in_array($currentPco->status, ['pending', 'clarification_requested', 'approved'], true)
                && self::checkUserAuthorizedPcoApprover($user, $currentPco);
        }
        return false;
    }

    /**
     * Check whether the user is permitted to stream concern attachments.
     * Pending transfer targets and pending collaborators must NOT stream attachments before accepting.
     */
    public static function canAccessConcernAttachments(User $user, Concern $concern): bool
    {
        $mode = self::resolveAccessMode($user, $concern);
        return in_array($mode, ['primary', 'active_collaborator', 'completed_collaborator', 'pco_approver', 'pco_approver_readonly'], true);
    }

    /**
     * Return the single current (latest non-cancelled, non-superseded) PCO approval request for a concern.
     */
    public static function getCurrentPcoApprovalRequest(Concern $concern): ?PcoApprovalRequest
    {
        return PcoApprovalRequest::where('concern_id', $concern->concern_id)
            ->whereNotIn('status', ['cancelled', 'superseded'])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Check whether a concern is currently blocked from resolution due to an unapproved PCO work authorization request.
     * Rule: If PCO has initiated a Department Approval Request on this concern, PCO cannot resolve the concern
     * while the current request is 'pending', 'clarification_requested', or 'rejected'.
     */
    public static function hasBlockingPcoApproval(Concern $concern): ?PcoApprovalRequest
    {
        $currentRequest = self::getCurrentPcoApprovalRequest($concern);

        if (!$currentRequest) {
            return null;
        }

        if (in_array($currentRequest->status, ['pending', 'clarification_requested', 'rejected'], true)) {
            return $currentRequest;
        }

        if ($currentRequest->status === 'approved' && !$currentRequest->is_work_authorized) {
            return $currentRequest;
        }

        return null;
    }

    /**
     * Build the coordination payload attached to staff/SAO/Dean concern detail responses.
     * Enforces limited preview when $accessMode === 'pending_transfer_target' or 'pending_collaborator',
     * and scopes PCO approval deliberations to PCO owners and designated PCO approvers.
     */
    public static function buildCoordinationPayload(User $user, Concern $concern): array
    {
        $accessMode = self::resolveAccessMode($user, $concern);
        $userDeptId = self::resolveUserDeptId($user);

        $transfers = ConcernTransferRequest::where('concern_id', $concern->concern_id)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $pendingTransfer = $transfers->firstWhere('status', 'pending');

        $collaborations = ConcernCollaboration::where('concern_id', $concern->concern_id)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $allPcoRequests = PcoApprovalRequest::where('concern_id', $concern->concern_id)
            ->with('events')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $canReadInternal = self::canReadInternalMessages($user, $concern);
        $canPostInternal = self::canPostInternalMessages($user, $concern);

        $internalMessages = $canReadInternal
            ? ConcernInternalMessage::where('concern_id', $concern->concern_id)
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->get()
            : collect();

        $isPcoOwner = self::isPcoDepartment($concern->dept_id);
        $currentPco = self::getCurrentPcoApprovalRequest($concern);
        $blockingPco = self::hasBlockingPcoApproval($concern);
        $isCurrentlyAuthorized = $currentPco && $currentPco->status === 'approved' && $currentPco->is_work_authorized;

        $pcoWorkAuthorizationState = 'not_required';
        if ($currentPco) {
            $pcoWorkAuthorizationState = match ($currentPco->status) {
                'approved' => $currentPco->is_work_authorized ? 'authorized_to_begin_work' : 'awaiting_department_approval',
                'clarification_requested' => 'clarification_requested',
                'rejected' => 'not_authorized_rejected',
                default => 'awaiting_department_approval',
            };
        }

        // Scope PCO approval deliberations: only PCO primary owner or designated PCO approver for the request may inspect PCO deliberations
        if ($accessMode === 'primary' && $isPcoOwner) {
            $visiblePcoRequests = $allPcoRequests->values();
        } elseif (in_array($accessMode, ['pco_approver', 'pco_approver_readonly'], true)) {
            $visiblePcoRequests = $allPcoRequests
                ->filter(fn (PcoApprovalRequest $req) => $req->status !== 'cancelled' && self::checkUserAuthorizedPcoApprover($user, $req))
                ->values();
        } else {
            $visiblePcoRequests = collect();
        }

        return [
            'access_mode' => $accessMode,
            'is_primary_owner' => $accessMode === 'primary',
            'is_limited_preview' => in_array($accessMode, ['pending_transfer_target', 'pending_collaborator'], true),
            'can_read_internal_messages' => $canReadInternal,
            'can_post_internal_messages' => $canPostInternal,
            'is_pco_owned_concern' => $isPcoOwner,
            'pco_work_authorization_state' => $pcoWorkAuthorizationState,
            'has_blocking_pco_approval' => $blockingPco !== null,
            'current_pco_approval_request_id' => $currentPco?->id,
            'approved_pco_scope' => $isCurrentlyAuthorized ? $currentPco->work_scope : null,
            'pending_transfer' => $pendingTransfer,
            'can_decide_pending_transfer' => $pendingTransfer
                && $userDeptId
                && (int) $pendingTransfer->to_dept_id === $userDeptId,
            'transfer_history' => $transfers->values(),
            'collaborations' => $collaborations->values(),
            'my_pending_collaboration' => $userDeptId
                ? $collaborations->first(fn ($c) => (int) $c->collaborator_dept_id === $userDeptId && $c->status === 'pending')
                : null,
            'my_active_collaboration' => $userDeptId
                ? $collaborations->first(fn ($c) => (int) $c->collaborator_dept_id === $userDeptId && $c->status === 'accepted')
                : null,
            'pco_approval_requests' => $visiblePcoRequests,
            'eligible_pco_approving_departments' => self::getEligiblePcoApprovingDepartments($concern->dept_id),
            'eligible_pco_approving_academic_units' => self::getEligiblePcoApprovingAcademicUnits(),
            'internal_messages' => $internalMessages->values(),
        ];
    }

    /**
     * Check if a given department ID corresponds to PCO.
     */
    public static function isPcoDepartment(?int $deptId): bool
    {
        if (!$deptId) {
            return false;
        }
        $dept = Department::find($deptId);
        if (!$dept) {
            return false;
        }
        $configuredName = strtolower(trim((string) config('scms.pco.department_name', 'PCO')));
        $actualName = strtolower(trim($dept->name));

        return $actualName === $configuredName
            || str_contains($actualName, 'pco')
            || str_contains($actualName, 'property custodian');
    }

    /**
     * Return eligible operational departments configured to approve PCO work (fails closed if config is empty).
     */
    public static function getEligiblePcoApprovingDepartments(?int $excludeDeptId = null): Collection
    {
        $configured = config('scms.pco.eligible_approving_departments', []);
        if (!is_array($configured) || empty($configured)) {
            return collect();
        }

        $normalized = array_map(fn ($v) => strtolower(trim((string) $v)), $configured);

        return Department::orderBy('name')
            ->get()
            ->filter(function (Department $dept) use ($normalized, $excludeDeptId) {
                if ($excludeDeptId && (int) $dept->id === (int) $excludeDeptId) {
                    return false;
                }
                if (self::isPcoDepartment($dept->id)) {
                    return false;
                }
                if (in_array('*', $normalized, true)) {
                    return true;
                }
                $nameLower = strtolower(trim($dept->name));
                if (in_array((string) $dept->id, $normalized, true) || in_array($nameLower, $normalized, true)) {
                    return true;
                }
                foreach ($normalized as $pattern) {
                    if ($pattern !== '' && !is_numeric($pattern) && str_contains($nameLower, $pattern)) {
                        return true;
                    }
                }
                return false;
            })
            ->values();
    }

    /**
     * Return eligible academic units configured to approve PCO work (fails closed if config is empty).
     */
    public static function getEligiblePcoApprovingAcademicUnits(): Collection
    {
        $configured = config('scms.pco.eligible_approving_academic_units', []);
        if (!is_array($configured) || empty($configured)) {
            return collect();
        }

        $normalized = array_map(fn ($v) => strtolower(trim((string) $v)), $configured);

        return AcademicUnit::orderBy('name')
            ->get()
            ->filter(function (AcademicUnit $unit) use ($normalized) {
                if (in_array('*', $normalized, true)) {
                    return true;
                }
                $nameLower = strtolower(trim($unit->name));
                if (in_array((string) $unit->id, $normalized, true) || in_array($nameLower, $normalized, true)) {
                    return true;
                }
                foreach ($normalized as $pattern) {
                    if ($pattern !== '' && !is_numeric($pattern) && str_contains($nameLower, $pattern)) {
                        return true;
                    }
                }
                return false;
            })
            ->values();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // A. UNLIMITED HANDSHAKE TRANSFERS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Initiate a handshake transfer request to another operational department.
     * - Unlimited accepted transfers are permitted per concern.
     * - Only one pending transfer request per concern may exist at a time.
     * - Current primary department retains ownership until the target department accepts.
     */
    public function initiateTransfer(Request $request, int $id)
    {
        $user = $request->user();
        $concern = Concern::findOrFail($id);

        if (!self::isPrimaryOwner($user, $concern)) {
            return response()->json([
                'message' => 'Unauthorized. Only the current primary owner department may initiate a concern transfer.',
            ], 403);
        }

        if (!in_array($concern->status, ['pending', 'in_progress', 'returned', 'approved'], true)) {
            return response()->json([
                'message' => 'Only active, unresolved concerns can be transferred to another department.',
            ], 422);
        }

        if (in_array($concern->cad_status, ['awaiting_submission', 'awaiting_decision'], true)) {
            return response()->json([
                'message' => 'Cannot initiate a department transfer while a physical CAD approval document is actively awaiting submission or decision.',
            ], 422);
        }

        $validated = $request->validate([
            'to_dept_id' => 'required|integer|exists:departments,id',
            'transfer_reason' => 'required|string|min:5|max:500',
            'handoff_summary' => 'required|string|min:5|max:5000',
        ]);

        $targetDept = Department::findOrFail((int) $validated['to_dept_id']);

        if ($concern->dept_id && (int) $concern->dept_id === (int) $targetDept->id) {
            return response()->json([
                'message' => 'The concern is already assigned to ' . $targetDept->name . '.',
            ], 422);
        }

        return DB::transaction(function () use ($user, $concern, $targetDept, $validated) {
            $lockedConcern = Concern::where('concern_id', $concern->concern_id)->lockForUpdate()->firstOrFail();

            $existingPending = ConcernTransferRequest::where('concern_id', $lockedConcern->concern_id)
                ->where('status', 'pending')
                ->first();

            if ($existingPending) {
                return response()->json([
                    'message' => 'A pending transfer request to ' . $existingPending->to_office_name . ' already exists for this concern. Wait for a decision or cancel the pending transfer first.',
                ], 422);
            }

            $fromOfficeName = $lockedConcern->department?->name
                ?? $lockedConcern->academicUnit?->name
                ?? self::resolveUserOfficeName($user);

            $priorTransfers = ConcernTransferRequest::where('concern_id', $lockedConcern->concern_id)->get();
            $sequence = $priorTransfers->count() + 1;

            // Detect reversal / repeated bounce without blocking the transfer
            $previouslyInvolvedTarget = $priorTransfers->contains(function (ConcernTransferRequest $tr) use ($targetDept) {
                return ((int) $tr->from_dept_id === (int) $targetDept->id && $tr->status === 'accepted')
                    || ((int) $tr->to_dept_id === (int) $targetDept->id && $tr->status === 'declined');
            });

            $reversalWarning = null;
            if ($previouslyInvolvedTarget) {
                $reversalWarning = "Audit Note: This concern was previously transferred from or declined by {$targetDept->name} (Transfer Request #{$sequence}). Repeated transfers remain permitted.";
            } elseif ($sequence >= 3) {
                $reversalWarning = "Audit Note: This is transfer request #{$sequence} on this concern. Ensure the handoff summary clearly explains why {$targetDept->name} is the responsible primary office.";
            }

            $transferRequest = ConcernTransferRequest::create([
                'concern_id' => $lockedConcern->concern_id,
                'from_dept_id' => $lockedConcern->dept_id,
                'from_academic_unit_id' => $lockedConcern->academic_unit_id,
                'from_office_name' => $fromOfficeName,
                'to_dept_id' => $targetDept->id,
                'to_academic_unit_id' => null,
                'to_office_name' => $targetDept->name,
                'requested_by' => $user->id,
                'requested_by_name' => $user->name,
                'transfer_reason' => trim($validated['transfer_reason']),
                'handoff_summary' => trim($validated['handoff_summary']),
                'transfer_sequence' => $sequence,
                'is_reversal' => $previouslyInvolvedTarget,
                'reversal_warning' => $reversalWarning,
                'status' => 'pending',
            ]);

            ConcernRouting::create([
                'concern_id' => $lockedConcern->concern_id,
                'action' => 'transfer_requested',
                'performed_by' => $user->id,
                'notes' => "Transfer requested (#{$sequence}) from {$fromOfficeName} to {$targetDept->name}. Reason: {$transferRequest->transfer_reason}. Handoff Summary: {$transferRequest->handoff_summary}"
                    . ($reversalWarning ? " [{$reversalWarning}]" : ''),
            ]);

            ConcernInternalMessage::create([
                'concern_id' => $lockedConcern->concern_id,
                'sender_id' => $user->id,
                'sender_name' => $user->name,
                'sender_role' => $user->role,
                'sender_dept_id' => self::resolveUserDeptId($user),
                'sender_academic_unit_id' => $user->academic_unit_id,
                'sender_department_name' => $fromOfficeName,
                'message_type' => 'transfer_event',
                'body' => "Initiated transfer request (#{$sequence}) to {$targetDept->name}. Reason: {$transferRequest->transfer_reason} | Handoff Summary: {$transferRequest->handoff_summary}",
            ]);

            // Notify target department staff (and SAO users if target department is SAO)
            $this->notifyDepartmentStaff(
                $targetDept->id,
                $lockedConcern,
                'Incoming Concern Transfer Request',
                "{$fromOfficeName} requested to transfer concern '{$lockedConcern->title}' to {$targetDept->name}. Ownership remains with {$fromOfficeName} until your department accepts.",
                'transfer_requested',
                $user->id
            );

            return response()->json([
                'message' => "Transfer request sent to {$targetDept->name}. Your department retains primary ownership until {$targetDept->name} accepts.",
                'transfer_request' => $transferRequest,
                'reversal_warning' => $reversalWarning,
            ], 201);
        });
    }

    /**
     * Accept a pending transfer request (Target Department only).
     * Atomically transfers primary ownership to the accepting department.
     */
    public function acceptTransfer(Request $request, int $id, int $transferId)
    {
        $user = $request->user();
        $userDeptId = self::resolveUserDeptId($user);

        $concern = Concern::findOrFail($id);
        $transferRequest = ConcernTransferRequest::where('concern_id', $concern->concern_id)
            ->where('id', $transferId)
            ->firstOrFail();

        if ($transferRequest->status !== 'pending') {
            return response()->json([
                'message' => "This transfer request is no longer pending (current status: {$transferRequest->status}).",
            ], 422);
        }

        if (!$userDeptId || (int) $transferRequest->to_dept_id !== $userDeptId) {
            return response()->json([
                'message' => 'Unauthorized. Only staff assigned to the receiving department (' . $transferRequest->to_office_name . ') may accept this transfer request.',
            ], 403);
        }

        $validated = $request->validate([
            'decision_notes' => 'nullable|string|max:2000',
        ]);

        return DB::transaction(function () use ($user, $concern, $transferRequest, $validated) {
            $lockedConcern = Concern::where('concern_id', $concern->concern_id)->lockForUpdate()->firstOrFail();
            $lockedTransfer = ConcernTransferRequest::where('id', $transferRequest->id)->lockForUpdate()->firstOrFail();

            if ($lockedTransfer->status !== 'pending') {
                return response()->json([
                    'message' => 'This transfer request has already been processed.',
                ], 422);
            }

            $targetDept = Department::findOrFail($lockedTransfer->to_dept_id);
            $oldOfficeName = $lockedTransfer->from_office_name;

            $lockedTransfer->update([
                'status' => 'accepted',
                'decided_by' => $user->id,
                'decided_by_name' => $user->name,
                'decision_notes' => !empty($validated['decision_notes']) ? trim($validated['decision_notes']) : 'Transfer accepted by receiving department.',
                'decided_at' => now(),
            ]);

            // Atomically update primary ownership on the concern while preserving academic_unit_id
            $lockedConcern->update([
                'category' => 'operational_departments',
                'workflow_type' => 'non_academic',
                'dept_id' => $targetDept->id,
                'transferred_from' => $oldOfficeName,
                'last_updated' => now(),
            ]);

            // If the new primary owner was previously an active/pending collaborator, close that collaboration slice cleanly
            ConcernCollaboration::where('concern_id', $lockedConcern->concern_id)
                ->where('collaborator_dept_id', $targetDept->id)
                ->whereIn('status', ['pending', 'accepted'])
                ->update([
                    'status' => 'completed',
                    'responded_by' => $user->id,
                    'responded_by_name' => $user->name,
                    'response_notes' => 'Department became the primary owner via accepted transfer.',
                    'completed_at' => now(),
                ]);

            $routingNote = "Transferred from {$oldOfficeName} to {$targetDept->name} (accepted by {$user->name}). Reason: {$lockedTransfer->transfer_reason}";
            if (!empty($validated['decision_notes'])) {
                $routingNote .= ' Acceptance Notes: ' . trim($validated['decision_notes']);
            }

            ConcernRouting::create([
                'concern_id' => $lockedConcern->concern_id,
                'action' => 'transferred',
                'performed_by' => $user->id,
                'notes' => $routingNote,
            ]);

            ConcernInternalMessage::create([
                'concern_id' => $lockedConcern->concern_id,
                'sender_id' => $user->id,
                'sender_name' => $user->name,
                'sender_role' => $user->role,
                'sender_dept_id' => $targetDept->id,
                'sender_academic_unit_id' => $user->academic_unit_id,
                'sender_department_name' => $targetDept->name,
                'message_type' => 'transfer_event',
                'body' => "Transfer (#{$lockedTransfer->transfer_sequence}) accepted by {$targetDept->name}. {$targetDept->name} is now the primary owner of this concern.",
            ]);

            // Re-evaluate recurrence grouping in the new primary department context
            if ($lockedConcern->recurring_incident_id) {
                try {
                    app(RecurrenceDetectionService::class)->unlinkConcern($lockedConcern, $user->id);
                } catch (\Throwable $e) {
                    Log::warning('Recurrence unlink failed after transfer acceptance: ' . $e->getMessage());
                }
            }
            try {
                app(RecurrenceDetectionService::class)->processConcern($lockedConcern->fresh());
            } catch (\Throwable $re) {
                Log::warning('Recurrence detection failed after transfer acceptance: ' . $re->getMessage());
            }

            // If concern is Critical, alert the new primary department
            if ($lockedConcern->fresh()->priority === 'critical') {
                $lockedConcern->update(['critical_notified_at' => null]);
                try {
                    ConcernWorkflowNotification::sendCriticalConcernNotification(
                        $lockedConcern->fresh(),
                        'Department Transfer Accepted'
                    );
                } catch (\Throwable $ce) {
                    Log::warning('Critical notification failed after transfer acceptance: ' . $ce->getMessage());
                }
            }

            // Notify previous department staff
            if ($lockedTransfer->from_dept_id) {
                $this->notifyDepartmentStaff(
                    (int) $lockedTransfer->from_dept_id,
                    $lockedConcern,
                    "Transfer Accepted by {$targetDept->name}",
                    "{$targetDept->name} accepted the transfer of concern '{$lockedConcern->title}' and is now the primary owner.",
                    'transfer_accepted',
                    $user->id
                );
            }

            // Notify student cleanly that their concern is now handled by the new primary department
            try {
                if ($lockedConcern->student) {
                    $lockedConcern->student->notify(new ConcernWorkflowNotification(
                        (int) $lockedConcern->concern_id,
                        "Concern Transferred to {$targetDept->name}",
                        "Your concern '{$lockedConcern->title}' has been transferred to {$targetDept->name} for handling.",
                        'transfer_accepted',
                        $lockedConcern->workflow_type ?? 'non_academic',
                        "/student/concerns/{$lockedConcern->concern_id}"
                    ));
                }
            } catch (\Throwable $ne) {
                Log::warning('Failed notifying student of accepted transfer: ' . $ne->getMessage());
            }

            return response()->json([
                'message' => "Transfer accepted. {$targetDept->name} is now the primary department responsible for this concern.",
                'transfer_request' => $lockedTransfer->fresh(),
                'concern' => $lockedConcern->fresh(['academicUnit', 'department']),
            ]);
        });
    }

    /**
     * Decline a pending transfer request (Target Department only; mandatory explanation).
     * Original department retains primary ownership.
     */
    public function declineTransfer(Request $request, int $id, int $transferId)
    {
        $user = $request->user();
        $userDeptId = self::resolveUserDeptId($user);

        $concern = Concern::findOrFail($id);
        $transferRequest = ConcernTransferRequest::where('concern_id', $concern->concern_id)
            ->where('id', $transferId)
            ->firstOrFail();

        if ($transferRequest->status !== 'pending') {
            return response()->json([
                'message' => "This transfer request is no longer pending (current status: {$transferRequest->status}).",
            ], 422);
        }

        if (!$userDeptId || (int) $transferRequest->to_dept_id !== $userDeptId) {
            return response()->json([
                'message' => 'Unauthorized. Only staff assigned to the receiving department (' . $transferRequest->to_office_name . ') may decline this transfer request.',
            ], 403);
        }

        $validated = $request->validate([
            'decision_notes' => 'required|string|min:5|max:2000',
        ]);

        return DB::transaction(function () use ($user, $concern, $transferRequest, $validated) {
            $lockedTransfer = ConcernTransferRequest::where('id', $transferRequest->id)->lockForUpdate()->firstOrFail();
            if ($lockedTransfer->status !== 'pending') {
                return response()->json(['message' => 'This transfer request has already been processed.'], 422);
            }

            $reason = trim($validated['decision_notes']);

            $lockedTransfer->update([
                'status' => 'declined',
                'decided_by' => $user->id,
                'decided_by_name' => $user->name,
                'decision_notes' => $reason,
                'decided_at' => now(),
            ]);

            ConcernRouting::create([
                'concern_id' => $concern->concern_id,
                'action' => 'transfer_declined',
                'performed_by' => $user->id,
                'notes' => "Transfer (#{$lockedTransfer->transfer_sequence}) declined by {$lockedTransfer->to_office_name}. Reason: {$reason}. {$lockedTransfer->from_office_name} retains primary ownership.",
            ]);

            ConcernInternalMessage::create([
                'concern_id' => $concern->concern_id,
                'sender_id' => $user->id,
                'sender_name' => $user->name,
                'sender_role' => $user->role,
                'sender_dept_id' => $lockedTransfer->to_dept_id,
                'sender_academic_unit_id' => $user->academic_unit_id,
                'sender_department_name' => $lockedTransfer->to_office_name,
                'message_type' => 'transfer_event',
                'body' => "Transfer (#{$lockedTransfer->transfer_sequence}) declined by {$lockedTransfer->to_office_name}. Reason: {$reason}. {$lockedTransfer->from_office_name} retains primary ownership.",
            ]);

            if ($lockedTransfer->from_dept_id) {
                $this->notifyDepartmentStaff(
                    (int) $lockedTransfer->from_dept_id,
                    $concern,
                    "Transfer Declined by {$lockedTransfer->to_office_name}",
                    "{$lockedTransfer->to_office_name} declined the transfer request for '{$concern->title}'. Your department retains primary ownership. Reason: {$reason}",
                    'transfer_declined',
                    $user->id
                );
            }

            return response()->json([
                'message' => "Transfer request declined. {$lockedTransfer->from_office_name} retains primary ownership.",
                'transfer_request' => $lockedTransfer->fresh(),
            ]);
        });
    }

    /**
     * Cancel a pending transfer request (Initiating Primary Owner only).
     */
    public function cancelTransfer(Request $request, int $id, int $transferId)
    {
        $user = $request->user();
        $concern = Concern::findOrFail($id);

        if (!self::isPrimaryOwner($user, $concern)) {
            return response()->json([
                'message' => 'Unauthorized. Only the current primary owner department may cancel a pending transfer request.',
            ], 403);
        }

        $transferRequest = ConcernTransferRequest::where('concern_id', $concern->concern_id)
            ->where('id', $transferId)
            ->firstOrFail();

        if ($transferRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending transfer requests can be cancelled.',
            ], 422);
        }

        $validated = $request->validate([
            'decision_notes' => 'nullable|string|max:1000',
        ]);

        $transferRequest->update([
            'status' => 'cancelled',
            'decided_by' => $user->id,
            'decided_by_name' => $user->name,
            'decision_notes' => !empty($validated['decision_notes']) ? trim($validated['decision_notes']) : 'Cancelled by initiating department.',
            'decided_at' => now(),
        ]);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'transfer_cancelled',
            'performed_by' => $user->id,
            'notes' => "Pending transfer (#{$transferRequest->transfer_sequence}) to {$transferRequest->to_office_name} cancelled by {$transferRequest->from_office_name}.",
        ]);

        return response()->json([
            'message' => 'Pending transfer request cancelled.',
            'transfer_request' => $transferRequest->fresh(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // B. MULTI-DEPARTMENT COLLABORATION
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Request collaboration from another operational department (Primary Owner only).
     * Does NOT change primary ownership.
     */
    public function requestCollaboration(Request $request, int $id)
    {
        $user = $request->user();
        $concern = Concern::findOrFail($id);

        if (!self::isPrimaryOwner($user, $concern)) {
            return response()->json([
                'message' => 'Unauthorized. Only the current primary owner department may request collaboration from another department.',
            ], 403);
        }

        if (in_array($concern->status, ['resolved', 'closed', 'withdrawn'], true)) {
            return response()->json([
                'message' => 'Cannot request collaboration on a resolved or closed concern.',
            ], 422);
        }

        $validated = $request->validate([
            'collaborator_dept_id' => 'required|integer|exists:departments,id',
            'collaboration_reason' => 'required|string|min:5|max:500',
            'requested_assistance' => 'required|string|min:5|max:5000',
        ]);

        $collabDept = Department::findOrFail((int) $validated['collaborator_dept_id']);

        if ($concern->dept_id && (int) $concern->dept_id === (int) $collabDept->id) {
            return response()->json([
                'message' => $collabDept->name . ' is already the primary owner of this concern.',
            ], 422);
        }

        $existingActive = ConcernCollaboration::where('concern_id', $concern->concern_id)
            ->where('collaborator_dept_id', $collabDept->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->first();

        if ($existingActive) {
            return response()->json([
                'message' => "{$collabDept->name} already has a {$existingActive->status} collaboration on this concern.",
            ], 422);
        }

        $primaryOfficeName = $concern->department?->name
            ?? $concern->academicUnit?->name
            ?? self::resolveUserOfficeName($user);

        $collaboration = ConcernCollaboration::create([
            'concern_id' => $concern->concern_id,
            'primary_dept_id' => $concern->dept_id,
            'primary_academic_unit_id' => $concern->academic_unit_id,
            'primary_office_name' => $primaryOfficeName,
            'collaborator_dept_id' => $collabDept->id,
            'collaborator_office_name' => $collabDept->name,
            'requested_by' => $user->id,
            'requested_by_name' => $user->name,
            'collaboration_reason' => trim($validated['collaboration_reason']),
            'requested_assistance' => trim($validated['requested_assistance']),
            'status' => 'pending',
        ]);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'collaboration_requested',
            'performed_by' => $user->id,
            'notes' => "Collaboration requested by {$primaryOfficeName} from {$collabDept->name}. Reason: {$collaboration->collaboration_reason}. Requested Assistance: {$collaboration->requested_assistance}",
        ]);

        ConcernInternalMessage::create([
            'concern_id' => $concern->concern_id,
            'sender_id' => $user->id,
            'sender_name' => $user->name,
            'sender_role' => $user->role,
            'sender_dept_id' => self::resolveUserDeptId($user),
            'sender_academic_unit_id' => $user->academic_unit_id,
            'sender_department_name' => $primaryOfficeName,
            'message_type' => 'collaboration_event',
            'body' => "Invited {$collabDept->name} to collaborate. Requested Assistance: {$collaboration->requested_assistance}",
        ]);

        $this->notifyDepartmentStaff(
            $collabDept->id,
            $concern,
            'Department Collaboration Requested',
            "{$primaryOfficeName} requested collaboration from {$collabDept->name} on concern '{$concern->title}'. Requested assistance: " . Str::limit($collaboration->requested_assistance, 90),
            'collaboration_requested',
            $user->id
        );

        return response()->json([
            'message' => "Collaboration request sent to {$collabDept->name}.",
            'collaboration' => $collaboration,
        ], 201);
    }

    /**
     * Accept a pending collaboration invitation (Invited Collaborator Department only).
     */
    public function acceptCollaboration(Request $request, int $id, int $collabId)
    {
        $user = $request->user();
        $userDeptId = self::resolveUserDeptId($user);

        $concern = Concern::findOrFail($id);
        $collaboration = ConcernCollaboration::where('concern_id', $concern->concern_id)
            ->where('id', $collabId)
            ->firstOrFail();

        if ($collaboration->status !== 'pending') {
            return response()->json([
                'message' => "This collaboration invitation is no longer pending (current status: {$collaboration->status}).",
            ], 422);
        }

        if (!$userDeptId || (int) $collaboration->collaborator_dept_id !== $userDeptId) {
            return response()->json([
                'message' => 'Unauthorized. Only staff assigned to ' . $collaboration->collaborator_office_name . ' may accept this collaboration request.',
            ], 403);
        }

        $validated = $request->validate([
            'response_notes' => 'nullable|string|max:2000',
        ]);

        $collaboration->update([
            'status' => 'accepted',
            'responded_by' => $user->id,
            'responded_by_name' => $user->name,
            'response_notes' => !empty($validated['response_notes']) ? trim($validated['response_notes']) : 'Collaboration accepted.',
            'accepted_at' => now(),
        ]);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'collaboration_accepted',
            'performed_by' => $user->id,
            'notes' => "{$collaboration->collaborator_office_name} accepted collaboration request from {$collaboration->primary_office_name}.",
        ]);

        ConcernInternalMessage::create([
            'concern_id' => $concern->concern_id,
            'sender_id' => $user->id,
            'sender_name' => $user->name,
            'sender_role' => $user->role,
            'sender_dept_id' => $userDeptId,
            'sender_academic_unit_id' => $user->academic_unit_id,
            'sender_department_name' => $collaboration->collaborator_office_name,
            'message_type' => 'collaboration_event',
            'body' => "{$collaboration->collaborator_office_name} accepted the collaboration request and joined the internal department channel.",
        ]);

        if ($collaboration->primary_dept_id) {
            $this->notifyDepartmentStaff(
                (int) $collaboration->primary_dept_id,
                $concern,
                "Collaboration Accepted by {$collaboration->collaborator_office_name}",
                "{$collaboration->collaborator_office_name} accepted your collaboration request on '{$concern->title}' and can now coordinate via the internal department channel.",
                'collaboration_accepted',
                $user->id
            );
        }

        return response()->json([
            'message' => "Collaboration accepted. {$collaboration->collaborator_office_name} can now post updates in the internal department channel.",
            'collaboration' => $collaboration->fresh(),
        ]);
    }

    /**
     * Decline a pending collaboration invitation (Invited Collaborator Department only; mandatory reason).
     */
    public function declineCollaboration(Request $request, int $id, int $collabId)
    {
        $user = $request->user();
        $userDeptId = self::resolveUserDeptId($user);

        $concern = Concern::findOrFail($id);
        $collaboration = ConcernCollaboration::where('concern_id', $concern->concern_id)
            ->where('id', $collabId)
            ->firstOrFail();

        if ($collaboration->status !== 'pending') {
            return response()->json([
                'message' => "This collaboration invitation is no longer pending (current status: {$collaboration->status}).",
            ], 422);
        }

        if (!$userDeptId || (int) $collaboration->collaborator_dept_id !== $userDeptId) {
            return response()->json([
                'message' => 'Unauthorized. Only staff assigned to ' . $collaboration->collaborator_office_name . ' may decline this collaboration request.',
            ], 403);
        }

        $validated = $request->validate([
            'response_notes' => 'required|string|min:5|max:2000',
        ]);

        $reason = trim($validated['response_notes']);

        $collaboration->update([
            'status' => 'declined',
            'responded_by' => $user->id,
            'responded_by_name' => $user->name,
            'response_notes' => $reason,
            'declined_at' => now(),
        ]);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'collaboration_declined',
            'performed_by' => $user->id,
            'notes' => "{$collaboration->collaborator_office_name} declined collaboration. Reason: {$reason}",
        ]);

        ConcernInternalMessage::create([
            'concern_id' => $concern->concern_id,
            'sender_id' => $user->id,
            'sender_name' => $user->name,
            'sender_role' => $user->role,
            'sender_dept_id' => $userDeptId,
            'sender_academic_unit_id' => $user->academic_unit_id,
            'sender_department_name' => $collaboration->collaborator_office_name,
            'message_type' => 'collaboration_event',
            'body' => "{$collaboration->collaborator_office_name} declined the collaboration request. Reason: {$reason}",
        ]);

        if ($collaboration->primary_dept_id) {
            $this->notifyDepartmentStaff(
                (int) $collaboration->primary_dept_id,
                $concern,
                "Collaboration Declined by {$collaboration->collaborator_office_name}",
                "{$collaboration->collaborator_office_name} declined the collaboration request on '{$concern->title}'. Reason: {$reason}",
                'collaboration_declined',
                $user->id
            );
        }

        return response()->json([
            'message' => 'Collaboration request declined.',
            'collaboration' => $collaboration->fresh(),
        ]);
    }

    /**
     * Mark an accepted collaboration as completed (Accepted Collaborator or Primary Owner).
     * Once completed, the collaborator retains historical read access but can no longer post active updates.
     */
    public function completeCollaboration(Request $request, int $id, int $collabId)
    {
        $user = $request->user();
        $userDeptId = self::resolveUserDeptId($user);

        $concern = Concern::findOrFail($id);
        $collaboration = ConcernCollaboration::where('concern_id', $concern->concern_id)
            ->where('id', $collabId)
            ->firstOrFail();

        if ($collaboration->status !== 'accepted') {
            return response()->json([
                'message' => 'Only an accepted, active collaboration can be marked as completed.',
            ], 422);
        }

        $isCollaborator = $userDeptId && (int) $collaboration->collaborator_dept_id === $userDeptId;
        $isPrimary = self::isPrimaryOwner($user, $concern);

        if (!$isCollaborator && !$isPrimary) {
            return response()->json([
                'message' => 'Unauthorized. Only the collaborating department or the primary owner department may complete this collaboration.',
            ], 403);
        }

        $validated = $request->validate([
            'response_notes' => 'nullable|string|max:2000',
        ]);

        $summary = !empty($validated['response_notes'])
            ? trim($validated['response_notes'])
            : "Requested assistance completed by {$collaboration->collaborator_office_name}.";

        $collaboration->update([
            'status' => 'completed',
            'responded_by' => $user->id,
            'responded_by_name' => $user->name,
            'response_notes' => $summary,
            'completed_at' => now(),
        ]);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'collaboration_completed',
            'performed_by' => $user->id,
            'notes' => "Collaboration with {$collaboration->collaborator_office_name} marked completed. Summary: {$summary}",
        ]);

        ConcernInternalMessage::create([
            'concern_id' => $concern->concern_id,
            'sender_id' => $user->id,
            'sender_name' => $user->name,
            'sender_role' => $user->role,
            'sender_dept_id' => $userDeptId,
            'sender_academic_unit_id' => $user->academic_unit_id,
            'sender_department_name' => self::resolveUserOfficeName($user),
            'message_type' => 'collaboration_event',
            'body' => "Collaboration task for {$collaboration->collaborator_office_name} marked as completed. Summary: {$summary}",
        ]);

        if ($isCollaborator && $collaboration->primary_dept_id) {
            $this->notifyDepartmentStaff(
                (int) $collaboration->primary_dept_id,
                $concern,
                "Collaboration Completed by {$collaboration->collaborator_office_name}",
                "{$collaboration->collaborator_office_name} marked their requested assistance as completed on '{$concern->title}'. Summary: " . Str::limit($summary, 90),
                'collaboration_completed',
                $user->id
            );
        }

        return response()->json([
            'message' => 'Collaboration marked as completed.',
            'collaboration' => $collaboration->fresh(),
        ]);
    }

    /**
     * Revoke a pending or accepted collaboration (Primary Owner only).
     */
    public function revokeCollaboration(Request $request, int $id, int $collabId)
    {
        $user = $request->user();
        $concern = Concern::findOrFail($id);

        if (!self::isPrimaryOwner($user, $concern)) {
            return response()->json([
                'message' => 'Unauthorized. Only the primary owner department may revoke a collaboration.',
            ], 403);
        }

        $collaboration = ConcernCollaboration::where('concern_id', $concern->concern_id)
            ->where('id', $collabId)
            ->firstOrFail();

        if (!in_array($collaboration->status, ['pending', 'accepted'], true)) {
            return response()->json([
                'message' => 'Only pending or accepted collaborations can be revoked.',
            ], 422);
        }

        $validated = $request->validate([
            'response_notes' => 'nullable|string|max:1000',
        ]);

        $collaboration->update([
            'status' => 'revoked',
            'responded_by' => $user->id,
            'responded_by_name' => $user->name,
            'response_notes' => !empty($validated['response_notes']) ? trim($validated['response_notes']) : 'Revoked by primary department.',
            'revoked_at' => now(),
        ]);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'collaboration_revoked',
            'performed_by' => $user->id,
            'notes' => "Collaboration with {$collaboration->collaborator_office_name} revoked by {$collaboration->primary_office_name}.",
        ]);

        return response()->json([
            'message' => 'Collaboration revoked.',
            'collaboration' => $collaboration->fresh(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // C. PCO DEPARTMENT APPROVAL WORKFLOW
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * PCO submits a formal Department Approval Request before beginning specified work.
     * - Allowed only when the concern is owned by PCO and the authenticated user is PCO staff.
     * - Target approving department or academic unit must be in the configured eligible list (fails closed if not eligible).
     * - If superseding/amending a prior approved request's scope, marks the prior request 'superseded' and requires fresh approval.
     */
    public function submitPcoApprovalRequest(Request $request, int $id)
    {
        $user = $request->user();
        $concern = Concern::findOrFail($id);

        if (!self::isPrimaryOwner($user, $concern) || !self::isPcoDepartment($concern->dept_id)) {
            return response()->json([
                'message' => 'Unauthorized. Only PCO staff managing a PCO-assigned concern may submit a PCO Department Approval Request.',
            ], 403);
        }

        if (in_array($concern->status, ['resolved', 'closed', 'withdrawn'], true)) {
            return response()->json([
                'message' => 'Cannot request department work approval on a resolved or closed concern.',
            ], 422);
        }

        $validated = $request->validate([
            'approving_dept_id' => 'nullable|integer|exists:departments,id',
            'approving_academic_unit_id' => 'nullable|integer|exists:academic_units,id',
            'approval_reason' => 'required|string|min:5|max:500',
            'proposed_work_description' => 'required|string|min:5|max:5000',
            'work_scope' => 'required|string|min:5|max:5000',
            'supporting_notes' => 'nullable|string|max:5000',
            'supersedes_request_id' => 'nullable|integer|exists:pco_approval_requests,id',
            'attachments.*' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,pdf,doc,docx|max:20480',
        ]);

        $targetDeptId = !empty($validated['approving_dept_id']) ? (int) $validated['approving_dept_id'] : null;
        $targetUnitId = !empty($validated['approving_academic_unit_id']) ? (int) $validated['approving_academic_unit_id'] : null;

        if ((!$targetDeptId && !$targetUnitId) || ($targetDeptId && $targetUnitId)) {
            return response()->json([
                'message' => 'Please specify either an eligible approving operational department or an eligible approving academic unit.',
            ], 422);
        }

        // Fail-closed eligibility verification against config('scms.pco.*')
        $approvingOfficeName = null;
        if ($targetDeptId) {
            $eligibleDepts = self::getEligiblePcoApprovingDepartments($concern->dept_id);
            $matchedDept = $eligibleDepts->firstWhere('id', $targetDeptId);
            if (!$matchedDept) {
                return response()->json([
                    'message' => 'The selected operational department is not configured as an eligible approving authority for PCO work requests.',
                ], 422);
            }
            $approvingOfficeName = $matchedDept->name;
        } else {
            $eligibleUnits = self::getEligiblePcoApprovingAcademicUnits();
            $matchedUnit = $eligibleUnits->firstWhere('id', $targetUnitId);
            if (!$matchedUnit) {
                return response()->json([
                    'message' => 'The selected academic unit is not configured as an eligible approving authority for PCO work requests.',
                ], 422);
            }
            $approvingOfficeName = $matchedUnit->name;
        }

        return DB::transaction(function () use ($request, $user, $concern, $validated, $targetDeptId, $targetUnitId, $approvingOfficeName) {
            $supersededId = !empty($validated['supersedes_request_id']) ? (int) $validated['supersedes_request_id'] : null;

            // 1. Check if there is already an active pending/clarification request that is not being superseded
            $activePending = PcoApprovalRequest::where('concern_id', $concern->concern_id)
                ->whereIn('status', ['pending', 'clarification_requested'])
                ->orderByDesc('id')
                ->first();

            if ($activePending && $supersededId !== (int) $activePending->id) {
                return response()->json([
                    'message' => "A PCO Department Approval Request to {$activePending->approving_office_name} is already active ({$activePending->status}). Wait for a decision, provide clarification, or cancel it first.",
                ], 422);
            }

            // 2. If an approved PCO request already exists, a new request MUST explicitly supersede it
            $activeApproved = PcoApprovalRequest::where('concern_id', $concern->concern_id)
                ->where('status', 'approved')
                ->orderByDesc('id')
                ->first();

            if ($activeApproved && $supersededId !== (int) $activeApproved->id) {
                return response()->json([
                    'message' => "An approved PCO Department Approval Request (#{$activeApproved->id}) already exists for this concern. To amend or replace the approved work scope, you must explicitly specify supersedes_request_id = {$activeApproved->id}.",
                    'errors' => [
                        'supersedes_request_id' => ["You must explicitly supersede the current approved request (#{$activeApproved->id}) when requesting a new work scope."],
                    ],
                ], 422);
            }

            if ($supersededId) {
                $priorReq = PcoApprovalRequest::where('concern_id', $concern->concern_id)
                    ->where('id', $supersededId)
                    ->first();

                if (!$priorReq) {
                    return response()->json([
                        'message' => 'The specified supersedes_request_id does not belong to this concern.',
                    ], 422);
                }

                if (in_array($priorReq->status, ['cancelled', 'superseded'], true)) {
                    return response()->json([
                        'message' => "Cannot supersede PCO request #{$priorReq->id} because its status is already '{$priorReq->status}'.",
                    ], 422);
                }

                $priorReq->update([
                    'status' => 'superseded',
                    'is_work_authorized' => false,
                ]);

                PcoApprovalEvent::create([
                    'pco_approval_request_id' => $priorReq->id,
                    'concern_id' => $concern->concern_id,
                    'event_type' => 'scope_amended',
                    'actor_id' => $user->id,
                    'actor_name' => $user->name,
                    'actor_role' => $user->role,
                    'actor_office_name' => $concern->department?->name ?? 'PCO',
                    'notes' => 'Work scope amended or revised; superseded by a new PCO Department Approval Request requiring fresh authorization.',
                ]);
            } else {
                // If following a rejected request without explicit supersedes_request_id, link to the latest rejected request while preserving its rejected history
                $latestRejected = PcoApprovalRequest::where('concern_id', $concern->concern_id)
                    ->where('status', 'rejected')
                    ->orderByDesc('id')
                    ->first();
                if ($latestRejected) {
                    $supersededId = (int) $latestRejected->id;
                }
            }

            $attachmentEntries = [];
            if ($request->hasFile('attachments')) {
                foreach (array_values($request->file('attachments')) as $idx => $file) {
                    $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
                    $origFullName = basename((string) $file->getClientOriginalName());
                    $origName = pathinfo($origFullName, PATHINFO_FILENAME);
                    $safeName = substr(trim((string) preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $origName), '_.-') ?: 'pco_doc', 0, 60);
                    $filename = sprintf('pco_%d_%d_%s_%s.%s', $concern->concern_id, $idx, uniqid(), $safeName, $ext);
                    $path = $file->storeAs("pco_approvals/{$concern->concern_id}", $filename, 'local');
                    $attachmentEntries[] = [
                        'path' => $path,
                        'original_name' => $origFullName ?: "{$safeName}.{$ext}",
                    ];
                }
            }

            $pcoOfficeName = $concern->department?->name ?? 'PCO';

            $pcoRequest = PcoApprovalRequest::create([
                'concern_id' => $concern->concern_id,
                'supersedes_request_id' => $supersededId,
                'requesting_dept_id' => $concern->dept_id,
                'requesting_office_name' => $pcoOfficeName,
                'requested_by' => $user->id,
                'requested_by_name' => $user->name,
                'approving_dept_id' => $targetDeptId,
                'approving_academic_unit_id' => $targetUnitId,
                'approving_office_name' => $approvingOfficeName,
                'approval_reason' => trim($validated['approval_reason']),
                'proposed_work_description' => trim($validated['proposed_work_description']),
                'work_scope' => trim($validated['work_scope']),
                'supporting_notes' => !empty($validated['supporting_notes']) ? trim($validated['supporting_notes']) : null,
                'attachments' => !empty($attachmentEntries) ? $attachmentEntries : null,
                'status' => 'pending',
                'is_work_authorized' => false,
            ]);

            PcoApprovalEvent::create([
                'pco_approval_request_id' => $pcoRequest->id,
                'concern_id' => $concern->concern_id,
                'event_type' => 'submitted',
                'actor_id' => $user->id,
                'actor_name' => $user->name,
                'actor_role' => $user->role,
                'actor_office_name' => $pcoOfficeName,
                'notes' => "Submitted PCO Department Approval Request to {$approvingOfficeName}. Reason: {$pcoRequest->approval_reason} | Proposed Work: {$pcoRequest->proposed_work_description} | Scope: {$pcoRequest->work_scope}",
            ]);

            ConcernRouting::create([
                'concern_id' => $concern->concern_id,
                'action' => 'pco_approval_requested',
                'performed_by' => $user->id,
                'notes' => "PCO requested department work approval from {$approvingOfficeName}. Scope: {$pcoRequest->work_scope}",
            ]);

            ConcernInternalMessage::create([
                'concern_id' => $concern->concern_id,
                'sender_id' => $user->id,
                'sender_name' => $user->name,
                'sender_role' => $user->role,
                'sender_dept_id' => $concern->dept_id,
                'sender_academic_unit_id' => $user->academic_unit_id,
                'sender_department_name' => $pcoOfficeName,
                'message_type' => 'pco_approval_event',
                'body' => "Submitted PCO Department Approval Request to {$approvingOfficeName}. Proposed Work: {$pcoRequest->proposed_work_description} | Scope: {$pcoRequest->work_scope}. Work is awaiting formal authorization.",
            ]);

            if ($targetDeptId) {
                $this->notifyDepartmentStaff(
                    $targetDeptId,
                    $concern,
                    'PCO Department Approval Required',
                    "{$pcoOfficeName} requested formal authorization from {$approvingOfficeName} before beginning work on '{$concern->title}'. Scope: " . Str::limit($pcoRequest->work_scope, 90),
                    'pco_approval_requested',
                    $user->id
                );
            } elseif ($targetUnitId) {
                $this->notifyAcademicUnitApprovers(
                    $targetUnitId,
                    $concern,
                    'PCO Department Approval Required',
                    "{$pcoOfficeName} requested formal authorization from {$approvingOfficeName} before beginning work on '{$concern->title}'. Scope: " . Str::limit($pcoRequest->work_scope, 90),
                    'pco_approval_requested',
                    $user->id
                );
            }

            return response()->json([
                'message' => "PCO Department Approval Request submitted to {$approvingOfficeName}. Work requiring authorization cannot begin until formal approval is granted.",
                'pco_approval_request' => $pcoRequest->load('events'),
            ], 201);
        });
    }

    /**
     * Approving department grants formal authorization for PCO's proposed work and scope.
     */
    public function approvePcoRequest(Request $request, int $id, int $requestId)
    {
        $user = $request->user();
        $concern = Concern::findOrFail($id);
        $pcoRequest = PcoApprovalRequest::where('concern_id', $concern->concern_id)
            ->where('id', $requestId)
            ->firstOrFail();

        if (!in_array($pcoRequest->status, ['pending', 'clarification_requested'], true)) {
            return response()->json([
                'message' => "This PCO approval request cannot be approved from its current status ({$pcoRequest->status}).",
            ], 422);
        }

        if (!$this->isUserAuthorizedPcoApprover($user, $pcoRequest)) {
            return response()->json([
                'message' => "Unauthorized. Only authorized personnel assigned to {$pcoRequest->approving_office_name} may approve this PCO work request.",
            ], 403);
        }

        $validated = $request->validate([
            'decision_notes' => 'nullable|string|max:2000',
        ]);

        $notes = !empty($validated['decision_notes'])
            ? trim($validated['decision_notes'])
            : "Approved by {$pcoRequest->approving_office_name}. PCO is authorized to begin the specified scope of work.";

        $pcoRequest->update([
            'status' => 'approved',
            'is_work_authorized' => true,
            'decided_by' => $user->id,
            'decided_by_name' => $user->name,
            'decided_by_role' => $user->role,
            'decision_notes' => $notes,
            'decided_at' => now(),
        ]);

        PcoApprovalEvent::create([
            'pco_approval_request_id' => $pcoRequest->id,
            'concern_id' => $concern->concern_id,
            'event_type' => 'approved',
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'actor_role' => $user->role,
            'actor_office_name' => $pcoRequest->approving_office_name,
            'notes' => "APPROVED: PCO is authorized to begin work strictly within the approved scope ({$pcoRequest->work_scope}). Notes: {$notes}",
        ]);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'pco_approval_approved',
            'performed_by' => $user->id,
            'notes' => "PCO work formally APPROVED by {$pcoRequest->approving_office_name} ({$user->name}). Authorized Scope: {$pcoRequest->work_scope}. Notes: {$notes}",
        ]);

        ConcernInternalMessage::create([
            'concern_id' => $concern->concern_id,
            'sender_id' => $user->id,
            'sender_name' => $user->name,
            'sender_role' => $user->role,
            'sender_dept_id' => self::resolveUserDeptId($user),
            'sender_academic_unit_id' => $user->academic_unit_id,
            'sender_department_name' => $pcoRequest->approving_office_name,
            'message_type' => 'pco_approval_event',
            'body' => "FORMAL APPROVAL GRANTED by {$pcoRequest->approving_office_name}: PCO is now AUTHORIZED to begin work within the approved scope ({$pcoRequest->work_scope}). Notes: {$notes}",
        ]);

        // Notify PCO staff with explicit work-authorization wording
        $this->notifyDepartmentStaff(
            (int) $pcoRequest->requesting_dept_id,
            $concern,
            'PCO Work Approved — Authorized to Begin Work',
            "{$pcoRequest->approving_office_name} formally APPROVED your request on '{$concern->title}'. PCO is now AUTHORIZED to begin the approved scope of work: " . Str::limit($pcoRequest->work_scope, 90),
            'pco_approval_approved',
            $user->id
        );

        return response()->json([
            'message' => "PCO work request formally approved. {$pcoRequest->requesting_office_name} is now authorized to begin the approved scope of work.",
            'pco_approval_request' => $pcoRequest->fresh('events'),
        ]);
    }

    /**
     * Approving department rejects PCO's proposed work (mandatory explanation).
     */
    public function rejectPcoRequest(Request $request, int $id, int $requestId)
    {
        $user = $request->user();
        $concern = Concern::findOrFail($id);
        $pcoRequest = PcoApprovalRequest::where('concern_id', $concern->concern_id)
            ->where('id', $requestId)
            ->firstOrFail();

        if (!in_array($pcoRequest->status, ['pending', 'clarification_requested'], true)) {
            return response()->json([
                'message' => "This PCO approval request cannot be rejected from its current status ({$pcoRequest->status}).",
            ], 422);
        }

        if (!$this->isUserAuthorizedPcoApprover($user, $pcoRequest)) {
            return response()->json([
                'message' => "Unauthorized. Only authorized personnel assigned to {$pcoRequest->approving_office_name} may reject this PCO work request.",
            ], 403);
        }

        $validated = $request->validate([
            'decision_notes' => 'required|string|min:5|max:2000',
        ]);

        $reason = trim($validated['decision_notes']);

        $pcoRequest->update([
            'status' => 'rejected',
            'is_work_authorized' => false,
            'decided_by' => $user->id,
            'decided_by_name' => $user->name,
            'decided_by_role' => $user->role,
            'decision_notes' => $reason,
            'decided_at' => now(),
        ]);

        PcoApprovalEvent::create([
            'pco_approval_request_id' => $pcoRequest->id,
            'concern_id' => $concern->concern_id,
            'event_type' => 'rejected',
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'actor_role' => $user->role,
            'actor_office_name' => $pcoRequest->approving_office_name,
            'notes' => "REJECTED (Not Authorized to Begin Work): {$reason}",
        ]);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'pco_approval_rejected',
            'performed_by' => $user->id,
            'notes' => "PCO work request REJECTED by {$pcoRequest->approving_office_name} ({$user->name}). PCO is NOT authorized to begin work. Reason: {$reason}",
        ]);

        ConcernInternalMessage::create([
            'concern_id' => $concern->concern_id,
            'sender_id' => $user->id,
            'sender_name' => $user->name,
            'sender_role' => $user->role,
            'sender_dept_id' => self::resolveUserDeptId($user),
            'sender_academic_unit_id' => $user->academic_unit_id,
            'sender_department_name' => $pcoRequest->approving_office_name,
            'message_type' => 'pco_approval_event',
            'body' => "PCO WORK REQUEST REJECTED by {$pcoRequest->approving_office_name}: PCO is NOT authorized to proceed with this work scope. Reason: {$reason}",
        ]);

        $this->notifyDepartmentStaff(
            (int) $pcoRequest->requesting_dept_id,
            $concern,
            'PCO Work Request Rejected — Not Authorized',
            "{$pcoRequest->approving_office_name} REJECTED your work request on '{$concern->title}'. PCO is NOT authorized to begin the proposed work. Reason: {$reason}",
            'pco_approval_rejected',
            $user->id
        );

        return response()->json([
            'message' => 'PCO work request rejected. PCO is not authorized to begin the proposed work.',
            'pco_approval_request' => $pcoRequest->fresh('events'),
        ]);
    }

    /**
     * Approving department requests clarification or additional information from PCO.
     */
    public function requestPcoClarification(Request $request, int $id, int $requestId)
    {
        $user = $request->user();
        $concern = Concern::findOrFail($id);
        $pcoRequest = PcoApprovalRequest::where('concern_id', $concern->concern_id)
            ->where('id', $requestId)
            ->firstOrFail();

        if ($pcoRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Clarification can only be requested on a pending PCO approval request.',
            ], 422);
        }

        if (!$this->isUserAuthorizedPcoApprover($user, $pcoRequest)) {
            return response()->json([
                'message' => "Unauthorized. Only authorized personnel assigned to {$pcoRequest->approving_office_name} may request clarification.",
            ], 403);
        }

        $validated = $request->validate([
            'decision_notes' => 'required|string|min:5|max:2000',
        ]);

        $question = trim($validated['decision_notes']);

        $pcoRequest->update([
            'status' => 'clarification_requested',
            'is_work_authorized' => false,
            'decided_by' => $user->id,
            'decided_by_name' => $user->name,
            'decided_by_role' => $user->role,
            'decision_notes' => $question,
            'decided_at' => now(),
        ]);

        PcoApprovalEvent::create([
            'pco_approval_request_id' => $pcoRequest->id,
            'concern_id' => $concern->concern_id,
            'event_type' => 'clarification_requested',
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'actor_role' => $user->role,
            'actor_office_name' => $pcoRequest->approving_office_name,
            'notes' => "Clarification Requested (Work Not Yet Authorized): {$question}",
        ]);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'pco_approval_clarification_requested',
            'performed_by' => $user->id,
            'notes' => "{$pcoRequest->approving_office_name} requested clarification on PCO work request: {$question}",
        ]);

        ConcernInternalMessage::create([
            'concern_id' => $concern->concern_id,
            'sender_id' => $user->id,
            'sender_name' => $user->name,
            'sender_role' => $user->role,
            'sender_dept_id' => self::resolveUserDeptId($user),
            'sender_academic_unit_id' => $user->academic_unit_id,
            'sender_department_name' => $pcoRequest->approving_office_name,
            'message_type' => 'pco_approval_event',
            'body' => "Clarification requested by {$pcoRequest->approving_office_name} on PCO work request (Work is NOT yet authorized): {$question}",
        ]);

        $this->notifyDepartmentStaff(
            (int) $pcoRequest->requesting_dept_id,
            $concern,
            'Clarification Requested on PCO Work Approval',
            "{$pcoRequest->approving_office_name} requested clarification on '{$concern->title}'. PCO is NOT yet authorized to begin work. Inquiry: {$question}",
            'pco_approval_clarification_requested',
            $user->id
        );

        return response()->json([
            'message' => 'Clarification requested from PCO. Work remains unauthorized until formal approval is granted.',
            'pco_approval_request' => $pcoRequest->fresh('events'),
        ]);
    }

    /**
     * PCO responds to a clarification request, returning the approval request to 'pending'.
     */
    public function providePcoClarification(Request $request, int $id, int $requestId)
    {
        $user = $request->user();
        $concern = Concern::findOrFail($id);

        if (!self::isPrimaryOwner($user, $concern) || !self::isPcoDepartment($concern->dept_id)) {
            return response()->json([
                'message' => 'Unauthorized. Only PCO staff managing this concern may provide clarification.',
            ], 403);
        }

        $pcoRequest = PcoApprovalRequest::where('concern_id', $concern->concern_id)
            ->where('id', $requestId)
            ->firstOrFail();

        if ($pcoRequest->status !== 'clarification_requested') {
            return response()->json([
                'message' => 'Clarification can only be provided when the request status is clarification_requested.',
            ], 422);
        }

        $validated = $request->validate([
            'supporting_notes' => 'required|string|min:5|max:5000',
            'attachments.*' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,pdf,doc,docx|max:20480',
        ]);

        $existingAttachments = $pcoRequest->getRawAttachments();
        if ($request->hasFile('attachments')) {
            foreach (array_values($request->file('attachments')) as $idx => $file) {
                $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
                $origFullName = basename((string) $file->getClientOriginalName());
                $origName = pathinfo($origFullName, PATHINFO_FILENAME);
                $safeName = substr(trim((string) preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $origName), '_.-') ?: 'pco_clarif', 0, 60);
                $filename = sprintf('pco_%d_c%d_%s_%s.%s', $concern->concern_id, $idx, uniqid(), $safeName, $ext);
                $path = $file->storeAs("pco_approvals/{$concern->concern_id}", $filename, 'local');
                $existingAttachments[] = [
                    'path' => $path,
                    'original_name' => $origFullName ?: "{$safeName}.{$ext}",
                ];
            }
        }

        $clarificationText = trim($validated['supporting_notes']);
        $combinedNotes = $pcoRequest->supporting_notes
            ? ($pcoRequest->supporting_notes . "\n[Clarification]: " . $clarificationText)
            : $clarificationText;

        $pcoRequest->update([
            'status' => 'pending',
            'is_work_authorized' => false,
            'supporting_notes' => $combinedNotes,
            'attachments' => !empty($existingAttachments) ? $existingAttachments : null,
        ]);

        PcoApprovalEvent::create([
            'pco_approval_request_id' => $pcoRequest->id,
            'concern_id' => $concern->concern_id,
            'event_type' => 'clarification_provided',
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'actor_role' => $user->role,
            'actor_office_name' => $pcoRequest->requesting_office_name,
            'notes' => "Clarification provided by PCO: {$clarificationText}",
        ]);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'pco_approval_clarification_provided',
            'performed_by' => $user->id,
            'notes' => "PCO provided clarification to {$pcoRequest->approving_office_name}: {$clarificationText}",
        ]);

        ConcernInternalMessage::create([
            'concern_id' => $concern->concern_id,
            'sender_id' => $user->id,
            'sender_name' => $user->name,
            'sender_role' => $user->role,
            'sender_dept_id' => $concern->dept_id,
            'sender_academic_unit_id' => $user->academic_unit_id,
            'sender_department_name' => $pcoRequest->requesting_office_name,
            'message_type' => 'pco_approval_event',
            'body' => "Clarification provided by {$pcoRequest->requesting_office_name} to {$pcoRequest->approving_office_name}: {$clarificationText}",
        ]);

        if ($pcoRequest->approving_dept_id) {
            $this->notifyDepartmentStaff(
                (int) $pcoRequest->approving_dept_id,
                $concern,
                'PCO Clarification Provided — Awaiting Approval Decision',
                "{$pcoRequest->requesting_office_name} provided the requested clarification for '{$concern->title}'. Please review and record an approval or rejection decision.",
                'pco_approval_clarification_provided',
                $user->id
            );
        } elseif ($pcoRequest->approving_academic_unit_id) {
            $this->notifyAcademicUnitApprovers(
                (int) $pcoRequest->approving_academic_unit_id,
                $concern,
                'PCO Clarification Provided — Awaiting Approval Decision',
                "{$pcoRequest->requesting_office_name} provided the requested clarification for '{$concern->title}'. Please review and record an approval or rejection decision.",
                'pco_approval_clarification_provided',
                $user->id
            );
        }

        return response()->json([
            'message' => "Clarification submitted to {$pcoRequest->approving_office_name}. Request is now pending approval.",
            'pco_approval_request' => $pcoRequest->fresh('events'),
        ]);
    }

    /**
     * Cancel a pending or clarification-requested PCO approval request (PCO only).
     */
    public function cancelPcoRequest(Request $request, int $id, int $requestId)
    {
        $user = $request->user();
        $concern = Concern::findOrFail($id);

        if (!self::isPrimaryOwner($user, $concern) || !self::isPcoDepartment($concern->dept_id)) {
            return response()->json([
                'message' => 'Unauthorized. Only PCO staff managing this concern may cancel a PCO approval request.',
            ], 403);
        }

        $pcoRequest = PcoApprovalRequest::where('concern_id', $concern->concern_id)
            ->where('id', $requestId)
            ->firstOrFail();

        if (!in_array($pcoRequest->status, ['pending', 'clarification_requested'], true)) {
            return response()->json([
                'message' => 'Only pending or clarification-requested PCO approval requests can be cancelled.',
            ], 422);
        }

        $pcoRequest->update([
            'status' => 'cancelled',
            'is_work_authorized' => false,
        ]);

        PcoApprovalEvent::create([
            'pco_approval_request_id' => $pcoRequest->id,
            'concern_id' => $concern->concern_id,
            'event_type' => 'cancelled',
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'actor_role' => $user->role,
            'actor_office_name' => $pcoRequest->requesting_office_name,
            'notes' => 'PCO Department Approval Request cancelled by PCO.',
        ]);

        ConcernRouting::create([
            'concern_id' => $concern->concern_id,
            'action' => 'pco_approval_cancelled',
            'performed_by' => $user->id,
            'notes' => "PCO cancelled pending approval request to {$pcoRequest->approving_office_name}.",
        ]);

        return response()->json([
            'message' => 'PCO Department Approval Request cancelled.',
            'pco_approval_request' => $pcoRequest->fresh('events'),
        ]);
    }

    /**
     * Stream a supporting attachment belonging to a PCO approval request.
     * Accessible only to the PCO primary department and the designated approving department/unit (excluding cancelled requests).
     */
    public function showPcoAttachment(Request $request, int $id, int $requestId, int $index)
    {
        $user = $request->user();
        $concern = Concern::findOrFail($id);
        $pcoRequest = PcoApprovalRequest::where('concern_id', $concern->concern_id)
            ->where('id', $requestId)
            ->firstOrFail();

        $isPcoOwner = self::isPrimaryOwner($user, $concern) && self::isPcoDepartment($concern->dept_id);
        $isApprover = $pcoRequest->status !== 'cancelled' && $this->isUserAuthorizedPcoApprover($user, $pcoRequest);

        if (!$isPcoOwner && !$isApprover) {
            return response()->json([
                'message' => 'Unauthorized. You do not have permission to view attachments for this PCO approval request.',
            ], 403);
        }

        $attachments = $pcoRequest->getRawAttachments();
        if ($index < 0 || !array_key_exists($index, $attachments)) {
            return response()->json(['message' => 'PCO attachment not found.'], 404);
        }

        $entry = $attachments[$index];
        $rawPath = is_array($entry) ? (string) ($entry['path'] ?? '') : (string) $entry;
        $downloadName = is_array($entry) && !empty($entry['original_name'])
            ? basename((string) $entry['original_name'])
            : basename($rawPath);

        if ($rawPath === '' || str_contains($rawPath, '..') || str_contains($rawPath, "\0") || !Storage::disk('local')->exists($rawPath)) {
            return response()->json(['message' => 'PCO attachment file not found in private storage.'], 404);
        }

        return Storage::disk('local')->response($rawPath, $downloadName ?: basename($rawPath), [
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    /**
     * Verify whether the authenticated user is an eligible approver for the given PCO request.
     */
    public static function checkUserAuthorizedPcoApprover(User $user, PcoApprovalRequest $pcoRequest): bool
    {
        $allowedRoles = (array) config('scms.pco.approver_roles', ['dept_staff', 'sao', 'dean']);
        if (!in_array($user->role, $allowedRoles, true)) {
            return false;
        }

        $userDeptId = self::resolveUserDeptId($user);
        if ($pcoRequest->approving_dept_id && $userDeptId && (int) $pcoRequest->approving_dept_id === $userDeptId) {
            // Confirm department is still in the configured eligible list (fail-closed)
            $eligible = self::getEligiblePcoApprovingDepartments($pcoRequest->requesting_dept_id);
            return $eligible->contains('id', (int) $pcoRequest->approving_dept_id);
        }

        if ($pcoRequest->approving_academic_unit_id && $user->academic_unit_id && (int) $pcoRequest->approving_academic_unit_id === (int) $user->academic_unit_id) {
            $eligibleUnits = self::getEligiblePcoApprovingAcademicUnits();
            return $eligibleUnits->contains('id', (int) $pcoRequest->approving_academic_unit_id);
        }

        return false;
    }

    private function isUserAuthorizedPcoApprover(User $user, PcoApprovalRequest $pcoRequest): bool
    {
        return self::checkUserAuthorizedPcoApprover($user, $pcoRequest);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // D. DEDICATED INTERNAL DEPARTMENT MESSAGES
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * List internal department coordination messages for an authorized participant.
     * STRICT SECURITY REQUIREMENT:
     * - Students are never allowed.
     * - Pending transfer targets receive 403 (they only get limited preview + handoff context until they accept).
     * - Uninvolved departments and uninvolved SAO (Option A) receive 403.
     */
    public function getInternalMessages(Request $request, int $id)
    {
        $user = $request->user();
        $concern = Concern::findOrFail($id);

        if (!self::canReadInternalMessages($user, $concern)) {
            return response()->json([
                'message' => 'Unauthorized. You do not have permission to read the internal department communication history for this concern.',
            ], 403);
        }

        $messages = ConcernInternalMessage::where('concern_id', $concern->concern_id)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'internal_messages' => $messages,
        ]);
    }

    /**
     * Post a new message to the dedicated internal department communication channel.
     * - Allowed only for current Primary Owner, Active (accepted) Collaborators, and designated PCO Approving Office.
     * - Completed collaborators and pending transfer targets cannot post.
     * - Students are NEVER notified and can NEVER view these messages.
     */
    public function postInternalMessage(Request $request, int $id)
    {
        $user = $request->user();
        $concern = Concern::findOrFail($id);

        if (!self::canPostInternalMessages($user, $concern)) {
            return response()->json([
                'message' => 'Unauthorized. Only the primary department, active accepted collaborators, or an active approving department may post to the internal department channel.',
            ], 403);
        }

        $validated = $request->validate([
            'body' => 'required|string|min:1|max:5000',
            'message_type' => 'nullable|string|in:internal_note,transfer_note,collaboration_note,pco_approval_note,transfer_event,collaboration_event,pco_approval_event',
        ]);

        $officeName = self::resolveUserOfficeName($user);
        $msgType = !empty($validated['message_type']) ? $validated['message_type'] : 'internal_note';

        $internalMessage = ConcernInternalMessage::create([
            'concern_id' => $concern->concern_id,
            'sender_id' => $user->id,
            'sender_name' => $user->name,
            'sender_role' => $user->role,
            'sender_dept_id' => self::resolveUserDeptId($user),
            'sender_academic_unit_id' => $user->academic_unit_id,
            'sender_department_name' => $officeName,
            'message_type' => $msgType,
            'body' => trim($validated['body']),
        ]);

        // Notify authorized internal participants (Primary Dept + Active Collaborators + Active PCO Approvers), excluding sender and NEVER students
        $this->notifyInternalMessageParticipants($concern, $internalMessage, $user);

        return response()->json([
            'message' => 'Internal department message posted.',
            'data' => $internalMessage,
        ], 201);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // E. NOTIFICATION DISPATCH HELPERS (STAFF-ONLY; NEVER STUDENTS)
    // ══════════════════════════════════════════════════════════════════════════

    private function notifyDepartmentStaff(
        int $deptId,
        Concern $concern,
        string $title,
        string $message,
        string $event,
        ?int $excludeUserId = null
    ): void {
        try {
            $dept = Department::find($deptId);
            $isSaoDept = $dept && (str_contains(strtolower($dept->name), 'sao') || str_contains(strtolower($dept->name), 'student affairs'));

            $recipients = User::where('dept_id', $deptId)
                ->whereIn('role', ['dept_staff', 'sao'])
                ->get();

            if ($isSaoDept) {
                $saoUsers = User::where('role', 'sao')->get();
                $recipients = $recipients->merge($saoUsers);
            }

            $recipients = $recipients
                ->filter(function ($u) use ($excludeUserId) {
                    if (!$u || !$u->id) {
                        return false;
                    }
                    if (property_exists($u, 'is_active') && $u->is_active === false) {
                        return false;
                    }
                    if ($excludeUserId && (int) $u->id === (int) $excludeUserId) {
                        return false;
                    }
                    return $u->role !== 'student';
                })
                ->unique('id');

            foreach ($recipients as $recipient) {
                $url = $recipient->role === 'sao'
                    ? "/sao/concerns/{$concern->concern_id}"
                    : "/dept-staff/concerns/{$concern->concern_id}";

                $recipient->notify(new ConcernWorkflowNotification(
                    (int) $concern->concern_id,
                    $title,
                    $message,
                    $event,
                    $concern->workflow_type ?? 'non_academic',
                    $url
                ));
            }
        } catch (\Throwable $e) {
            Log::warning("Failed sending interdepartmental notification ({$event}): " . $e->getMessage());
        }
    }

    private function notifyAcademicUnitApprovers(
        int $academicUnitId,
        Concern $concern,
        string $title,
        string $message,
        string $event,
        ?int $excludeUserId = null
    ): void {
        try {
            $recipients = User::where('academic_unit_id', $academicUnitId)
                ->whereIn('role', ['dean', 'dept_staff'])
                ->get()
                ->filter(fn ($u) => $u && $u->id && (!$excludeUserId || (int) $u->id !== (int) $excludeUserId) && $u->role !== 'student')
                ->unique('id');

            foreach ($recipients as $recipient) {
                $url = $recipient->role === 'dean'
                    ? "/dean/concerns/{$concern->concern_id}"
                    : "/dept-staff/concerns/{$concern->concern_id}";

                $recipient->notify(new ConcernWorkflowNotification(
                    (int) $concern->concern_id,
                    $title,
                    $message,
                    $event,
                    $concern->workflow_type ?? 'non_academic',
                    $url
                ));
            }
        } catch (\Throwable $e) {
            Log::warning("Failed sending academic unit PCO notification ({$event}): " . $e->getMessage());
        }
    }

    private function notifyInternalMessageParticipants(
        Concern $concern,
        ConcernInternalMessage $internalMessage,
        User $sender
    ): void {
        try {
            $deptIds = collect();
            if ($concern->dept_id) {
                $deptIds->push((int) $concern->dept_id);
            }

            // Active accepted collaborators
            $acceptedCollabDepts = ConcernCollaboration::where('concern_id', $concern->concern_id)
                ->where('status', 'accepted')
                ->pluck('collaborator_dept_id');
            $deptIds = $deptIds->merge($acceptedCollabDepts);

            // Active PCO approving departments
            $activePcoDepts = PcoApprovalRequest::where('concern_id', $concern->concern_id)
                ->whereIn('status', ['pending', 'clarification_requested', 'approved'])
                ->whereNotNull('approving_dept_id')
                ->pluck('approving_dept_id');
            $deptIds = $deptIds->merge($activePcoDepts);

            $uniqueDeptIds = $deptIds->filter()->map(fn ($id) => (int) $id)->unique()->values();

            $snippet = Str::limit($internalMessage->body, 80);
            $title = "New Internal Department Note: {$concern->title}";
            $msgText = "{$internalMessage->sender_name} ({$internalMessage->sender_department_name}): {$snippet}";

            foreach ($uniqueDeptIds as $dId) {
                $this->notifyDepartmentStaff(
                    $dId,
                    $concern,
                    $title,
                    $msgText,
                    'internal_department_message',
                    (int) $sender->id
                );
            }

            // Also check active PCO approving academic units if any
            $activePcoUnits = PcoApprovalRequest::where('concern_id', $concern->concern_id)
                ->whereIn('status', ['pending', 'clarification_requested', 'approved'])
                ->whereNotNull('approving_academic_unit_id')
                ->pluck('approving_academic_unit_id')
                ->unique();

            foreach ($activePcoUnits as $uId) {
                $this->notifyAcademicUnitApprovers(
                    (int) $uId,
                    $concern,
                    $title,
                    $msgText,
                    'internal_department_message',
                    (int) $sender->id
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Failed sending internal message notifications: ' . $e->getMessage());
        }
    }
}
