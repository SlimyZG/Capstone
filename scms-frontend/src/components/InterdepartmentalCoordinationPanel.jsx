import { useState, useEffect } from 'react';
import {
  ArrowRightLeft,
  Users,
  ShieldCheck,
  ShieldAlert,
  Lock,
  Send,
  CheckCircle2,
  XCircle,
  HelpCircle,
  AlertTriangle,
  Clock,
  FileText,
  Plus,
  X,
  Building2,
  History,
  ExternalLink,
  RefreshCw,
} from 'lucide-react';
import api from '../api';

const ACCESS_MODE_LABELS = {
  primary: { label: 'Primary Responsible Office', color: 'bg-blue-100 text-blue-800 border-blue-200' },
  active_collaborator: { label: 'Active Collaborator Office', color: 'bg-indigo-100 text-indigo-800 border-indigo-200' },
  collaborator: { label: 'Active Collaborator Office', color: 'bg-indigo-100 text-indigo-800 border-indigo-200' },
  pending_collaborator: { label: 'Invited Collaborator (Pending)', color: 'bg-amber-100 text-amber-800 border-amber-200' },
  completed_collaborator: { label: 'Completed Collaborator (Historical Read-Only)', color: 'bg-slate-100 text-slate-700 border-slate-300' },
  pending_transfer_target: { label: 'Pending Transfer Target (Limited Preview)', color: 'bg-amber-100 text-amber-900 border-amber-300' },
  pco_approver: { label: 'Designated PCO Approving Office', color: 'bg-purple-100 text-purple-800 border-purple-200' },
  pco_approver_readonly: { label: 'PCO Approver (Historical Read-Only)', color: 'bg-slate-100 text-slate-700 border-slate-300' },
};

const PCO_STATE_CONFIG = {
  awaiting_department_approval: {
    label: 'Awaiting Department Approval',
    badgeClass: 'bg-amber-100 text-amber-900 border-amber-300',
    panelClass: 'bg-amber-50/90 border-amber-200 text-amber-950',
    icon: Clock,
    description:
      'PCO retains primary ownership of this concern, but cannot begin work requiring authorization until formal approval is granted by the designated approving office.',
  },
  clarification_requested: {
    label: 'Clarification Requested',
    badgeClass: 'bg-orange-100 text-orange-900 border-orange-300',
    panelClass: 'bg-orange-50/90 border-orange-200 text-orange-950',
    icon: HelpCircle,
    description:
      'The approving department requested clarification on the proposed PCO work or scope. Work remains unauthorized until PCO responds and approval is granted.',
  },
  not_authorized_rejected: {
    label: 'Not Authorized to Begin Work',
    badgeClass: 'bg-rose-100 text-rose-900 border-rose-300',
    panelClass: 'bg-rose-50/90 border-rose-200 text-rose-950',
    icon: XCircle,
    description:
      'The department approval request was rejected. PCO is NOT authorized to proceed with the proposed work unless a revised request is submitted and approved.',
  },
  authorized_to_begin_work: {
    label: 'Authorized to Begin Approved Scope of Work',
    badgeClass: 'bg-emerald-100 text-emerald-900 border-emerald-300',
    panelClass: 'bg-emerald-50/90 border-emerald-200 text-emerald-950',
    icon: CheckCircle2,
    description:
      'Formal departmental approval has been granted. PCO is authorized to begin and complete the approved scope of work below.',
  },
};

const formatDateTime = (d) => {
  if (!d) return '—';
  const date = new Date(d);
  if (isNaN(date.getTime())) return '—';
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

/**
 * Top-of-page banners for:
 * 1. Limited Preview mode notice (pending transfer targets)
 * 2. Incoming Handshake Transfer Decision Banner
 * 3. Incoming Collaboration Invitation Decision Banner
 * 4. PCO Work Authorization Status & Approver Review Card
 */
export const CoordinationActionBanners = ({ concern, routePrefix = '/dept-staff', onRefresh }) => {
  const coord = concern?.coordination;
  const [submitting, setSubmitting] = useState('');
  const [errorMsg, setErrorMsg] = useState('');

  // Transfer decision state
  const [showTransferDecline, setShowTransferDecline] = useState(false);
  const [transferDecisionNotes, setTransferDecisionNotes] = useState('');

  // Collaboration decision state
  const [showCollabDecline, setShowCollabDecline] = useState(false);
  const [collabResponseNotes, setCollabResponseNotes] = useState('');

  // PCO Request Modal state (for PCO primary owner)
  const [showPcoRequestModal, setShowPcoRequestModal] = useState(false);
  const [pcoSupersedesId, setPcoSupersedesId] = useState(null);
  const [pcoTargetType, setPcoTargetType] = useState('department');
  const [pcoTargetId, setPcoTargetId] = useState('');
  const [pcoReason, setPcoReason] = useState('');
  const [pcoWorkDesc, setPcoWorkDesc] = useState('');
  const [pcoWorkScope, setPcoWorkScope] = useState('');
  const [pcoSupportingNotes, setPcoSupportingNotes] = useState('');
  const [pcoFiles, setPcoFiles] = useState([]);

  // PCO Clarification response state (for PCO primary owner)
  const [clarificationText, setClarificationText] = useState('');
  const [clarificationScope, setClarificationScope] = useState('');

  // PCO Approver decision state
  const [approverActionMode, setApproverActionMode] = useState({}); // { [reqId]: 'approve' | 'clarify' | 'reject' }
  const [approverNotes, setApproverNotes] = useState({});

  if (!coord) return null;

  const pendingTransfer = coord.pending_transfer;
  const myPendingCollab = coord.my_pending_collaboration;
  const pcoRequests = coord.pco_approval_requests || [];
  const activePcoRequest = pcoRequests.find((r) => !['cancelled', 'superseded'].includes(r.status));
  const eligiblePcoDepts = coord.eligible_pco_approving_departments || [];
  const eligiblePcoUnits = coord.eligible_pco_approving_academic_units || [];

  const handleAcceptTransfer = async () => {
    if (!pendingTransfer) return;
    setErrorMsg('');
    setSubmitting('accept_transfer');
    try {
      await api.post(`${routePrefix}/concerns/${concern.concern_id}/transfers/${pendingTransfer.id}/accept`, {
        decision_notes: transferDecisionNotes.trim() || 'Transfer accepted by receiving department.',
      });
      setTransferDecisionNotes('');
      onRefresh?.();
    } catch (err) {
      setErrorMsg(err.response?.data?.message || 'Failed to accept transfer.');
    } finally {
      setSubmitting('');
    }
  };

  const handleDeclineTransfer = async (e) => {
    e.preventDefault();
    if (!pendingTransfer) return;
    if (!transferDecisionNotes.trim() || transferDecisionNotes.trim().length < 5) {
      setErrorMsg('Please provide a clear explanation (at least 5 characters) for declining this transfer.');
      return;
    }
    setErrorMsg('');
    setSubmitting('decline_transfer');
    try {
      await api.post(`${routePrefix}/concerns/${concern.concern_id}/transfers/${pendingTransfer.id}/decline`, {
        decision_notes: transferDecisionNotes.trim(),
      });
      setShowTransferDecline(false);
      setTransferDecisionNotes('');
      onRefresh?.();
    } catch (err) {
      setErrorMsg(err.response?.data?.message || 'Failed to decline transfer.');
    } finally {
      setSubmitting('');
    }
  };

  const handleAcceptCollab = async () => {
    if (!myPendingCollab) return;
    setErrorMsg('');
    setSubmitting('accept_collab');
    try {
      await api.post(`${routePrefix}/concerns/${concern.concern_id}/collaborations/${myPendingCollab.id}/accept`, {
        response_notes: collabResponseNotes.trim() || 'Collaboration accepted.',
      });
      setCollabResponseNotes('');
      onRefresh?.();
    } catch (err) {
      setErrorMsg(err.response?.data?.message || 'Failed to accept collaboration request.');
    } finally {
      setSubmitting('');
    }
  };

  const handleDeclineCollab = async (e) => {
    e.preventDefault();
    if (!myPendingCollab) return;
    if (!collabResponseNotes.trim() || collabResponseNotes.trim().length < 5) {
      setErrorMsg('Please provide a reason (at least 5 characters) for declining collaboration.');
      return;
    }
    setErrorMsg('');
    setSubmitting('decline_collab');
    try {
      await api.post(`${routePrefix}/concerns/${concern.concern_id}/collaborations/${myPendingCollab.id}/decline`, {
        response_notes: collabResponseNotes.trim(),
      });
      setShowCollabDecline(false);
      setCollabResponseNotes('');
      onRefresh?.();
    } catch (err) {
      setErrorMsg(err.response?.data?.message || 'Failed to decline collaboration request.');
    } finally {
      setSubmitting('');
    }
  };

  const openNewPcoRequestModal = (supersedesReq = null) => {
    setErrorMsg('');
    setPcoSupersedesId(supersedesReq?.id || null);
    if (eligiblePcoDepts.length > 0) {
      setPcoTargetType('department');
      setPcoTargetId(eligiblePcoDepts[0].id.toString());
    } else if (eligiblePcoUnits.length > 0) {
      setPcoTargetType('academic_unit');
      setPcoTargetId(eligiblePcoUnits[0].id.toString());
    } else {
      setPcoTargetId('');
    }
    setPcoReason(supersedesReq ? `Scope amendment for prior approval request #${supersedesReq.id}` : '');
    setPcoWorkDesc(supersedesReq?.proposed_work_description || '');
    setPcoWorkScope(supersedesReq?.work_scope || '');
    setPcoSupportingNotes('');
    setPcoFiles([]);
    setShowPcoRequestModal(true);
  };

  const handleSubmitPcoRequest = async (e) => {
    e.preventDefault();
    if (!pcoTargetId) {
      setErrorMsg('No eligible approving department is configured.');
      return;
    }
    setErrorMsg('');
    setSubmitting('create_pco');
    try {
      const formData = new FormData();
      if (pcoTargetType === 'department') {
        formData.append('approving_dept_id', pcoTargetId);
      } else {
        formData.append('approving_academic_unit_id', pcoTargetId);
      }
      formData.append('approval_reason', pcoReason.trim());
      formData.append('proposed_work_description', pcoWorkDesc.trim());
      formData.append('work_scope', pcoWorkScope.trim());
      if (pcoSupportingNotes.trim()) {
        formData.append('supporting_notes', pcoSupportingNotes.trim());
      }
      if (pcoSupersedesId) {
        formData.append('supersedes_request_id', pcoSupersedesId);
      }
      Array.from(pcoFiles).forEach((file) => {
        formData.append('attachments[]', file);
      });

      await api.post(`${routePrefix}/concerns/${concern.concern_id}/pco-approvals`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      setShowPcoRequestModal(false);
      onRefresh?.();
    } catch (err) {
      setErrorMsg(err.response?.data?.message || 'Failed to submit PCO Department Approval Request.');
    } finally {
      setSubmitting('');
    }
  };

  const handleProvidePcoClarification = async (req) => {
    if (!clarificationText.trim() || clarificationText.trim().length < 5) {
      setErrorMsg('Please enter your clarification response (at least 5 characters).');
      return;
    }
    setErrorMsg('');
    setSubmitting(`clarify_${req.id}`);
    try {
      const payload = {
        supporting_notes: clarificationText.trim(),
      };
      if (clarificationScope.trim()) {
        payload.work_scope = clarificationScope.trim();
      }
      await api.post(
        `${routePrefix}/concerns/${concern.concern_id}/pco-approvals/${req.id}/provide-clarification`,
        payload
      );
      setClarificationText('');
      setClarificationScope('');
      onRefresh?.();
    } catch (err) {
      setErrorMsg(err.response?.data?.message || 'Failed to submit clarification.');
    } finally {
      setSubmitting('');
    }
  };

  const handleCancelPcoRequest = async (req) => {
    const reason = window.prompt('Enter reason for cancelling this PCO approval request (optional):', 'Cancelled by PCO.');
    if (reason === null) return;
    setErrorMsg('');
    setSubmitting(`cancel_pco_${req.id}`);
    try {
      await api.post(`${routePrefix}/concerns/${concern.concern_id}/pco-approvals/${req.id}/cancel`, {
        decision_notes: reason,
      });
      onRefresh?.();
    } catch (err) {
      setErrorMsg(err.response?.data?.message || 'Failed to cancel PCO approval request.');
    } finally {
      setSubmitting('');
    }
  };

  const handlePcoApproverDecision = async (req, decisionType) => {
    const notes = (approverNotes[req.id] || '').trim();
    if ((decisionType === 'reject' || decisionType === 'request-clarification') && notes.length < 5) {
      setErrorMsg(
        decisionType === 'reject'
          ? 'A clear rejection reason (at least 5 characters) is mandatory.'
          : 'Clarification instructions (at least 5 characters) are mandatory.'
      );
      return;
    }
    setErrorMsg('');
    setSubmitting(`pco_decide_${req.id}_${decisionType}`);
    try {
      await api.post(`${routePrefix}/concerns/${concern.concern_id}/pco-approvals/${req.id}/${decisionType}`, {
        decision_notes: notes || 'Approved. PCO is authorized to begin the approved scope of work.',
      });
      setApproverActionMode((prev) => ({ ...prev, [req.id]: null }));
      setApproverNotes((prev) => ({ ...prev, [req.id]: '' }));
      onRefresh?.();
    } catch (err) {
      setErrorMsg(err.response?.data?.message || 'Failed to record PCO approval decision.');
    } finally {
      setSubmitting('');
    }
  };

  const handleOpenPcoAttachment = async (reqId, index, originalName) => {
    try {
      const response = await api.get(
        `${routePrefix}/concerns/${concern.concern_id}/pco-approvals/${reqId}/attachments/${index}`,
        { responseType: 'blob' }
      );
      const blobUrl = window.URL.createObjectURL(
        new Blob([response.data], { type: response.headers['content-type'] || 'application/octet-stream' })
      );
      window.open(blobUrl, '_blank');
    } catch (err) {
      alert(err.response?.data?.message || `Failed to open attachment "${originalName}".`);
    }
  };

  const pcoStateInfo = PCO_STATE_CONFIG[coord.pco_work_authorization_state] || null;
  const showPcoSection = coord.is_pco_owned_concern || pcoRequests.length > 0;
  const canApproverActOnPco =
    activePcoRequest &&
    ['pending', 'clarification_requested'].includes(activePcoRequest.status) &&
    (coord.access_mode === 'pco_approver' || !coord.is_pco_owned_concern);

  return (
    <div className="space-y-4">
      {errorMsg && (
        <div className="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-start justify-between gap-2">
          <div className="flex items-start gap-2">
            <AlertTriangle size={15} className="shrink-0 mt-0.5 text-rose-600" />
            <span className="font-medium">{errorMsg}</span>
          </div>
          <button onClick={() => setErrorMsg('')} className="text-rose-500 hover:text-rose-700">
            <X size={14} />
          </button>
        </div>
      )}

      {/* Limited Preview Security Notice for Pending Transfer Targets */}
      {coord.access_mode === 'pending_transfer_target' && (
        <div className="p-4 rounded-xl bg-amber-50 border border-amber-300 text-amber-950 text-xs flex items-start gap-3 shadow-2xs">
          <Lock size={18} className="text-amber-700 shrink-0 mt-0.5" />
          <div>
            <p className="font-bold text-amber-950">
              Limited Transfer Preview Mode — Internal Department History Restricted
            </p>
            <p className="text-amber-800 mt-0.5 leading-relaxed">
              Your department is evaluating an incoming transfer request. For confidentiality, you are viewing the
              concern preview and handoff summary only. Full internal department message history and student
              correspondence are unlocked only after your department accepts primary ownership.
            </p>
          </div>
        </div>
      )}

      {/* Incoming Handshake Transfer Decision Card */}
      {coord.can_decide_pending_transfer && pendingTransfer && (
        <div className="card p-5 border-2 border-blue-300 bg-blue-50/60 space-y-3">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="flex items-center gap-2">
              <span className="p-2 rounded-lg bg-[#1e3a8a] text-white">
                <ArrowRightLeft size={16} />
              </span>
              <div>
                <h3 className="text-sm font-bold text-slate-900">
                  Incoming Concern Transfer Request from {pendingTransfer.from_office_name}
                </h3>
                <p className="text-[11px] text-slate-600">
                  Initiated by <span className="font-semibold">{pendingTransfer.requested_by_name}</span> •{' '}
                  {formatDateTime(pendingTransfer.created_at)}
                </p>
              </div>
            </div>
            <span className="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
              Awaiting Your Department&apos;s Decision
            </span>
          </div>

          {pendingTransfer.is_reversal && (
            <div className="p-3 rounded-lg bg-amber-100/80 border border-amber-300 text-amber-950 text-xs flex items-start gap-2">
              <AlertTriangle size={15} className="text-amber-700 shrink-0 mt-0.5" />
              <div>
                <span className="font-bold">Repeated / Reversal Transfer Audit Indicator: </span>
                <span>{pendingTransfer.reversal_warning}</span>
              </div>
            </div>
          )}

          <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
            <div className="p-3 bg-white rounded-xl border border-blue-200">
              <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Transfer Reason</p>
              <p className="text-slate-800 whitespace-pre-wrap">{pendingTransfer.transfer_reason}</p>
            </div>
            <div className="p-3 bg-white rounded-xl border border-blue-200">
              <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Handoff Summary</p>
              <p className="text-slate-800 whitespace-pre-wrap">{pendingTransfer.handoff_summary}</p>
            </div>
          </div>

          {!showTransferDecline ? (
            <div className="flex flex-wrap items-center gap-2.5 pt-1">
              <button
                type="button"
                onClick={handleAcceptTransfer}
                disabled={!!submitting}
                className="btn-success py-2 px-4 text-xs flex items-center gap-1.5"
              >
                <CheckCircle2 size={15} />
                <span>
                  {submitting === 'accept_transfer'
                    ? 'Accepting Ownership...'
                    : 'Accept Transfer & Take Primary Ownership'}
                </span>
              </button>
              <button
                type="button"
                onClick={() => setShowTransferDecline(true)}
                disabled={!!submitting}
                className="btn-secondary py-2 px-4 text-xs flex items-center gap-1.5 bg-white text-rose-700 border-rose-200 hover:bg-rose-50"
              >
                <XCircle size={15} />
                <span>Decline Transfer</span>
              </button>
            </div>
          ) : (
            <form onSubmit={handleDeclineTransfer} className="p-3.5 bg-white rounded-xl border border-rose-200 space-y-2.5">
              <label className="block text-xs font-bold text-rose-900">
                Mandatory Explanation for Declining Transfer
              </label>
              <textarea
                rows={2}
                required
                className="input-field text-xs"
                placeholder="Explain why this concern does not belong to your department so the current owner can route it properly..."
                value={transferDecisionNotes}
                onChange={(e) => setTransferDecisionNotes(e.target.value)}
              />
              <div className="flex items-center gap-2">
                <button
                  type="submit"
                  disabled={submitting === 'decline_transfer'}
                  className="px-3.5 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold"
                >
                  {submitting === 'decline_transfer' ? 'Declining...' : 'Confirm Decline Transfer'}
                </button>
                <button
                  type="button"
                  onClick={() => {
                    setShowTransferDecline(false);
                    setTransferDecisionNotes('');
                  }}
                  className="btn-secondary py-1.5 px-3 text-xs"
                >
                  Cancel
                </button>
              </div>
            </form>
          )}
        </div>
      )}

      {/* Incoming Collaboration Invitation Decision Card */}
      {myPendingCollab && (
        <div className="card p-5 border-2 border-indigo-300 bg-indigo-50/60 space-y-3">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="flex items-center gap-2">
              <span className="p-2 rounded-lg bg-indigo-700 text-white">
                <Users size={16} />
              </span>
              <div>
                <h3 className="text-sm font-bold text-slate-900">
                  Collaboration Invitation from {myPendingCollab.primary_office_name}
                </h3>
                <p className="text-[11px] text-slate-600">
                  Requested by <span className="font-semibold">{myPendingCollab.requested_by_name}</span> •{' '}
                  {formatDateTime(myPendingCollab.created_at)} •{' '}
                  <span className="font-semibold text-indigo-800">
                    Primary ownership remains with {myPendingCollab.primary_office_name}
                  </span>
                </p>
              </div>
            </div>
            <span className="px-2.5 py-1 rounded-full text-[11px] font-bold bg-indigo-100 text-indigo-900 border border-indigo-300">
              Collaboration Invited
            </span>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
            <div className="p-3 bg-white rounded-xl border border-indigo-200">
              <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Collaboration Reason</p>
              <p className="text-slate-800 whitespace-pre-wrap">{myPendingCollab.collaboration_reason}</p>
            </div>
            <div className="p-3 bg-white rounded-xl border border-indigo-200">
              <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Requested Assistance</p>
              <p className="text-slate-800 whitespace-pre-wrap">{myPendingCollab.requested_assistance}</p>
            </div>
          </div>

          {!showCollabDecline ? (
            <div className="flex flex-wrap items-center gap-2.5 pt-1">
              <button
                type="button"
                onClick={handleAcceptCollab}
                disabled={!!submitting}
                className="btn-dark py-2 px-4 text-xs flex items-center gap-1.5 bg-indigo-700 hover:bg-indigo-800"
              >
                <CheckCircle2 size={15} />
                <span>{submitting === 'accept_collab' ? 'Accepting...' : 'Accept Collaboration'}</span>
              </button>
              <button
                type="button"
                onClick={() => setShowCollabDecline(true)}
                disabled={!!submitting}
                className="btn-secondary py-2 px-4 text-xs flex items-center gap-1.5 bg-white text-rose-700 border-rose-200 hover:bg-rose-50"
              >
                <XCircle size={15} />
                <span>Decline Request</span>
              </button>
            </div>
          ) : (
            <form onSubmit={handleDeclineCollab} className="p-3.5 bg-white rounded-xl border border-rose-200 space-y-2.5">
              <label className="block text-xs font-bold text-rose-900">
                Mandatory Explanation for Declining Collaboration
              </label>
              <textarea
                rows={2}
                required
                className="input-field text-xs"
                placeholder="Explain why your department cannot assist on this concern..."
                value={collabResponseNotes}
                onChange={(e) => setCollabResponseNotes(e.target.value)}
              />
              <div className="flex items-center gap-2">
                <button
                  type="submit"
                  disabled={submitting === 'decline_collab'}
                  className="px-3.5 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold"
                >
                  {submitting === 'decline_collab' ? 'Declining...' : 'Confirm Decline'}
                </button>
                <button
                  type="button"
                  onClick={() => {
                    setShowCollabDecline(false);
                    setCollabResponseNotes('');
                  }}
                  className="btn-secondary py-1.5 px-3 text-xs"
                >
                  Cancel
                </button>
              </div>
            </form>
          )}
        </div>
      )}

      {/* PCO Work Authorization Status & Department Approval Card */}
      {showPcoSection && (
        <div
          className={`card p-5 border-2 ${
            pcoStateInfo ? pcoStateInfo.panelClass : 'bg-slate-50 border-slate-200 text-slate-900'
          } space-y-4`}
        >
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div className="flex items-start gap-2.5">
              <span className="p-2 rounded-lg bg-[#1e3a8a] text-white shrink-0 mt-0.5">
                <ShieldCheck size={16} />
              </span>
              <div>
                <div className="flex flex-wrap items-center gap-2">
                  <h3 className="text-sm font-bold text-slate-900">
                    PCO Department Approval &amp; Work Authorization
                  </h3>
                  {pcoStateInfo ? (
                    <span
                      className={`px-2.5 py-0.5 rounded-full text-[11px] font-bold border ${pcoStateInfo.badgeClass}`}
                    >
                      {pcoStateInfo.label}
                    </span>
                  ) : (
                    <span className="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-200 text-slate-700">
                      No Active Approval Request
                    </span>
                  )}
                </div>
                <p className="text-xs text-slate-600 mt-1 leading-relaxed">
                  {pcoStateInfo
                    ? pcoStateInfo.description
                    : 'When PCO work requires formal approval from another department before physical/technical work begins, submit a Department Approval Request below. PCO retains primary concern ownership throughout.'}
                </p>
              </div>
            </div>

            {/* PCO Primary Owner Buttons to Initiate / Revise / Amend Scope */}
            {coord.is_primary_owner && coord.is_pco_owned_concern && concern.status !== 'resolved' && (
              <div className="flex flex-wrap items-center gap-2">
                {(!activePcoRequest || activePcoRequest.status === 'rejected') && (
                  <button
                    type="button"
                    onClick={() => openNewPcoRequestModal(null)}
                    className="btn-dark py-2 px-3.5 text-xs flex items-center gap-1.5"
                  >
                    <Plus size={14} />
                    <span>
                      {activePcoRequest?.status === 'rejected'
                        ? 'Submit Revised Department Approval Request'
                        : 'Request Department Approval'}
                    </span>
                  </button>
                )}
                {activePcoRequest?.status === 'approved' && (
                  <button
                    type="button"
                    onClick={() => openNewPcoRequestModal(activePcoRequest)}
                    className="btn-secondary py-1.5 px-3 text-xs flex items-center gap-1.5 bg-white"
                  >
                    <RefreshCw size={13} />
                    <span>Request Scope Amendment / Re-Approval</span>
                  </button>
                )}
              </div>
            )}
          </div>

          {/* Active / Historical PCO Approval Requests List */}
          {pcoRequests.length > 0 && (
            <div className="space-y-3">
              {pcoRequests.map((req) => {
                const isCurrentActive = activePcoRequest && activePcoRequest.id === req.id;
                const mode = approverActionMode[req.id] || null;
                return (
                  <div
                    key={req.id}
                    className={`p-4 rounded-xl bg-white border ${
                      isCurrentActive ? 'border-slate-300 shadow-2xs' : 'border-slate-200 opacity-85'
                    } space-y-3 text-xs`}
                  >
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2.5">
                      <div>
                        <span className="font-bold text-slate-900">
                          Approval Request #{req.id} → {req.approving_office_name}
                        </span>
                        <span className="text-slate-400 ml-2">
                          Requested by {req.requested_by_name} on {formatDateTime(req.created_at)}
                        </span>
                      </div>
                      <div className="flex items-center gap-2">
                        <span
                          className={`px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase ${
                            req.status === 'approved'
                              ? 'bg-emerald-100 text-emerald-800'
                              : req.status === 'rejected'
                              ? 'bg-rose-100 text-rose-800'
                              : req.status === 'clarification_requested'
                              ? 'bg-orange-100 text-orange-800'
                              : req.status === 'pending'
                              ? 'bg-amber-100 text-amber-800'
                              : 'bg-slate-100 text-slate-600'
                          }`}
                        >
                          {req.status.replace(/_/g, ' ')}
                        </span>
                        {req.is_work_authorized && (
                          <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-600 text-white">
                            Work Authorized
                          </span>
                        )}
                      </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                      <div className="p-2.5 bg-slate-50 rounded-lg border border-slate-100">
                        <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                          Reason Approval Needed
                        </p>
                        <p className="text-slate-800 whitespace-pre-wrap">{req.approval_reason}</p>
                      </div>
                      <div className="p-2.5 bg-slate-50 rounded-lg border border-slate-100">
                        <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                          Proposed PCO Action / Work
                        </p>
                        <p className="text-slate-800 whitespace-pre-wrap">{req.proposed_work_description}</p>
                      </div>
                      <div className="p-2.5 bg-blue-50/60 rounded-lg border border-blue-200">
                        <p className="text-[10px] font-bold uppercase tracking-wider text-blue-700 mb-1">
                          {req.status === 'approved' ? 'Approved Work Scope (Locked)' : 'Proposed Work Scope'}
                        </p>
                        <p className="text-slate-900 font-semibold whitespace-pre-wrap">{req.work_scope}</p>
                      </div>
                    </div>

                    {req.supporting_notes && (
                      <div className="p-2.5 bg-slate-50 rounded-lg border border-slate-100">
                        <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-0.5">
                          Supporting Notes / Clarification
                        </p>
                        <p className="text-slate-700 whitespace-pre-wrap">{req.supporting_notes}</p>
                      </div>
                    )}

                    {/* Private PCO Approval Attachments */}
                    {Array.isArray(req.attachments) && req.attachments.length > 0 && (
                      <div className="flex flex-wrap items-center gap-2 pt-1">
                        <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                          Supporting Documents:
                        </span>
                        {req.attachments.map((att, idx) => (
                          <button
                            key={idx}
                            type="button"
                            onClick={() => handleOpenPcoAttachment(req.id, idx, att.original_name)}
                            className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-blue-50 text-slate-700 hover:text-blue-700 border border-slate-200 text-[11px] font-medium"
                          >
                            <FileText size={12} />
                            <span>{att.original_name || `Attachment ${idx + 1}`}</span>
                            <ExternalLink size={11} />
                          </button>
                        ))}
                      </div>
                    )}

                    {/* Decision / Clarification Notes */}
                    {req.decision_notes && (
                      <div
                        className={`p-3 rounded-lg border ${
                          req.status === 'approved'
                            ? 'bg-emerald-50 border-emerald-200 text-emerald-900'
                            : req.status === 'rejected'
                            ? 'bg-rose-50 border-rose-200 text-rose-900'
                            : 'bg-orange-50 border-orange-200 text-orange-900'
                        }`}
                      >
                        <p className="font-bold text-[11px]">
                          {req.approving_office_name} Decision Notes ({req.decided_by_name || 'Approver'} •{' '}
                          {formatDateTime(req.decided_at)}):
                        </p>
                        <p className="mt-0.5 whitespace-pre-wrap">{req.decision_notes}</p>
                      </div>
                    )}

                    {/* PCO Primary Owner Response when Clarification is Requested */}
                    {coord.is_primary_owner &&
                      coord.is_pco_owned_concern &&
                      req.status === 'clarification_requested' && (
                        <div className="p-3.5 bg-orange-50/70 rounded-xl border border-orange-200 space-y-2.5">
                          <p className="font-bold text-orange-950">
                            Provide Clarification to {req.approving_office_name}
                          </p>
                          <textarea
                            rows={2}
                            className="input-field text-xs bg-white"
                            placeholder="Enter clarification details requested by the approving department..."
                            value={clarificationText}
                            onChange={(e) => setClarificationText(e.target.value)}
                          />
                          <input
                            type="text"
                            className="input-field text-xs bg-white"
                            placeholder="Updated work scope (optional — leave blank to keep current scope)"
                            value={clarificationScope}
                            onChange={(e) => setClarificationScope(e.target.value)}
                          />
                          <div className="flex items-center gap-2">
                            <button
                              type="button"
                              onClick={() => handleProvidePcoClarification(req)}
                              disabled={submitting === `clarify_${req.id}`}
                              className="btn-dark py-1.5 px-3 text-xs"
                            >
                              {submitting === `clarify_${req.id}`
                                ? 'Submitting...'
                                : 'Submit Clarification & Re-queue for Approval'}
                            </button>
                            <button
                              type="button"
                              onClick={() => handleCancelPcoRequest(req)}
                              className="btn-secondary py-1.5 px-3 text-xs text-rose-700"
                            >
                              Cancel Request
                            </button>
                          </div>
                        </div>
                      )}

                    {/* PCO Primary Owner Cancel Button when Pending */}
                    {coord.is_primary_owner && coord.is_pco_owned_concern && req.status === 'pending' && (
                      <div className="flex justify-end">
                        <button
                          type="button"
                          onClick={() => handleCancelPcoRequest(req)}
                          disabled={submitting === `cancel_pco_${req.id}`}
                          className="text-[11px] font-semibold text-rose-600 hover:underline"
                        >
                          Cancel Pending Approval Request
                        </button>
                      </div>
                    )}

                    {/* Approving Office Decision Controls */}
                    {isCurrentActive &&
                      canApproverActOnPco &&
                      ['pending', 'clarification_requested'].includes(req.status) && (
                        <div className="pt-3 border-t border-slate-200 space-y-3">
                          <div className="flex flex-wrap items-center justify-between gap-2">
                            <span className="font-bold text-slate-900">
                              Approving Office Decision ({req.approving_office_name})
                            </span>
                            <div className="flex flex-wrap items-center gap-2">
                              <button
                                type="button"
                                onClick={() => setApproverActionMode((p) => ({ ...p, [req.id]: 'approve' }))}
                                className={`py-1.5 px-3 rounded-lg text-xs font-bold flex items-center gap-1 border ${
                                  mode === 'approve'
                                    ? 'bg-emerald-600 text-white border-emerald-600'
                                    : 'bg-emerald-50 text-emerald-800 border-emerald-200 hover:bg-emerald-100'
                                }`}
                              >
                                <CheckCircle2 size={14} />
                                <span>Approve Proposed Work</span>
                              </button>
                              <button
                                type="button"
                                onClick={() =>
                                  setApproverActionMode((p) => ({ ...p, [req.id]: 'request-clarification' }))
                                }
                                className={`py-1.5 px-3 rounded-lg text-xs font-bold flex items-center gap-1 border ${
                                  mode === 'request-clarification'
                                    ? 'bg-orange-600 text-white border-orange-600'
                                    : 'bg-orange-50 text-orange-800 border-orange-200 hover:bg-orange-100'
                                }`}
                              >
                                <HelpCircle size={14} />
                                <span>Request Clarification</span>
                              </button>
                              <button
                                type="button"
                                onClick={() => setApproverActionMode((p) => ({ ...p, [req.id]: 'reject' }))}
                                className={`py-1.5 px-3 rounded-lg text-xs font-bold flex items-center gap-1 border ${
                                  mode === 'reject'
                                    ? 'bg-rose-600 text-white border-rose-600'
                                    : 'bg-rose-50 text-rose-800 border-rose-200 hover:bg-rose-100'
                                }`}
                              >
                                <XCircle size={14} />
                                <span>Reject Request</span>
                              </button>
                            </div>
                          </div>

                          {mode && (
                            <div className="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2.5">
                              <label className="block text-xs font-bold text-slate-800">
                                {mode === 'approve'
                                  ? 'Approval Notes & Authorization Directives (Optional)'
                                  : mode === 'request-clarification'
                                  ? 'Mandatory Clarification Instructions for PCO'
                                  : 'Mandatory Rejection Explanation'}
                              </label>
                              <textarea
                                rows={2}
                                className="input-field text-xs bg-white"
                                placeholder={
                                  mode === 'approve'
                                    ? 'Optional conditions or authorization notes for PCO...'
                                    : mode === 'request-clarification'
                                    ? 'Specify what PCO needs to clarify before work authorization can be granted...'
                                    : 'Explain why this proposed work is rejected...'
                                }
                                value={approverNotes[req.id] || ''}
                                onChange={(e) =>
                                  setApproverNotes((p) => ({ ...p, [req.id]: e.target.value }))
                                }
                              />
                              <div className="flex items-center gap-2">
                                <button
                                  type="button"
                                  onClick={() => handlePcoApproverDecision(req, mode)}
                                  disabled={submitting === `pco_decide_${req.id}_${mode}`}
                                  className={`py-1.5 px-3.5 rounded-lg text-xs font-bold text-white ${
                                    mode === 'approve'
                                      ? 'bg-emerald-600 hover:bg-emerald-700'
                                      : mode === 'request-clarification'
                                      ? 'bg-orange-600 hover:bg-orange-700'
                                      : 'bg-rose-600 hover:bg-rose-700'
                                  }`}
                                >
                                  {submitting === `pco_decide_${req.id}_${mode}`
                                    ? 'Recording...'
                                    : mode === 'approve'
                                    ? 'Confirm Approval & Authorize PCO Work'
                                    : mode === 'request-clarification'
                                    ? 'Send Clarification Request to PCO'
                                    : 'Confirm Rejection (Not Authorized)'}
                                </button>
                                <button
                                  type="button"
                                  onClick={() => setApproverActionMode((p) => ({ ...p, [req.id]: null }))}
                                  className="btn-secondary py-1.5 px-3 text-xs"
                                >
                                  Cancel
                                </button>
                              </div>
                            </div>
                          )}
                        </div>
                      )}
                  </div>
                );
              })}
            </div>
          )}
        </div>
      )}

      {/* Modal for PCO to Create / Revise / Amend Department Approval Request */}
      {showPcoRequestModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl shadow-xl max-w-lg w-full border border-slate-200 overflow-hidden max-h-[90vh] flex flex-col">
            <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
              <div className="flex items-center gap-2">
                <ShieldCheck size={18} className="text-[#1e3a8a]" />
                <div>
                  <h3 className="text-sm font-bold text-slate-900">
                    {pcoSupersedesId
                      ? 'Request Scope Amendment / Re-Approval'
                      : 'Request Department Approval for PCO Work'}
                  </h3>
                  <p className="text-[11px] text-slate-500">
                    PCO retains primary concern ownership while awaiting departmental work authorization
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setShowPcoRequestModal(false)}
                className="text-slate-400 hover:text-slate-600 p-1"
              >
                <X size={18} />
              </button>
            </div>

            <form onSubmit={handleSubmitPcoRequest} className="p-6 space-y-4 overflow-y-auto text-xs">
              {eligiblePcoDepts.length === 0 && eligiblePcoUnits.length === 0 ? (
                <div className="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800">
                  <p className="font-bold">Fail-Closed Security Policy Active</p>
                  <p className="mt-1">
                    No eligible approving departments or academic units are currently configured for PCO approval.
                    Please contact system administration to configure eligible approving offices.
                  </p>
                </div>
              ) : (
                <>
                  {eligiblePcoDepts.length > 0 && eligiblePcoUnits.length > 0 && (
                    <div>
                      <label className="block font-bold text-slate-700 mb-1">Approving Office Type</label>
                      <select
                        className="input-field text-xs"
                        value={pcoTargetType}
                        onChange={(e) => {
                          const nextType = e.target.value;
                          setPcoTargetType(nextType);
                          if (nextType === 'department' && eligiblePcoDepts[0]) {
                            setPcoTargetId(eligiblePcoDepts[0].id.toString());
                          } else if (nextType === 'academic_unit' && eligiblePcoUnits[0]) {
                            setPcoTargetId(eligiblePcoUnits[0].id.toString());
                          }
                        }}
                      >
                        <option value="department">Operational Department</option>
                        <option value="academic_unit">Academic Unit (Dean / Chairperson)</option>
                      </select>
                    </div>
                  )}

                  <div>
                    <label className="block font-bold text-slate-700 mb-1">
                      Approving {pcoTargetType === 'academic_unit' ? 'Academic Unit' : 'Department'} *
                    </label>
                    <select
                      required
                      className="input-field text-xs"
                      value={pcoTargetId}
                      onChange={(e) => setPcoTargetId(e.target.value)}
                    >
                      {(pcoTargetType === 'academic_unit' ? eligiblePcoUnits : eligiblePcoDepts).map((opt) => (
                        <option key={opt.id} value={opt.id}>
                          {opt.name} {opt.code ? `(${opt.code})` : ''}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <label className="block font-bold text-slate-700 mb-1">
                      Reason Departmental Approval is Required *
                    </label>
                    <textarea
                      rows={2}
                      required
                      minLength={5}
                      className="input-field text-xs"
                      placeholder="Explain why authorization from this department/office is needed before PCO begins work..."
                      value={pcoReason}
                      onChange={(e) => setPcoReason(e.target.value)}
                    />
                  </div>

                  <div>
                    <label className="block font-bold text-slate-700 mb-1">
                      Proposed PCO Work / Technical Action *
                    </label>
                    <textarea
                      rows={3}
                      required
                      minLength={5}
                      className="input-field text-xs"
                      placeholder="Describe the physical, technical, or operational work PCO proposes to perform..."
                      value={pcoWorkDesc}
                      onChange={(e) => setPcoWorkDesc(e.target.value)}
                    />
                  </div>

                  <div>
                    <label className="block font-bold text-slate-700 mb-1">
                      Specific Work Scope (Locked Upon Approval) *
                    </label>
                    <textarea
                      rows={2}
                      required
                      minLength={5}
                      className="input-field text-xs"
                      placeholder="Specify exact rooms, equipment, budget/resource impact, or schedule covered by this authorization..."
                      value={pcoWorkScope}
                      onChange={(e) => setPcoWorkScope(e.target.value)}
                    />
                  </div>

                  <div>
                    <label className="block font-bold text-slate-700 mb-1">Supporting Notes (Optional)</label>
                    <textarea
                      rows={2}
                      className="input-field text-xs"
                      placeholder="Additional remarks or context for the approving department..."
                      value={pcoSupportingNotes}
                      onChange={(e) => setPcoSupportingNotes(e.target.value)}
                    />
                  </div>

                  <div>
                    <label className="block font-bold text-slate-700 mb-1">
                      Supporting Attachments (Optional — PDF, JPG, PNG, max 10MB)
                    </label>
                    <input
                      type="file"
                      multiple
                      accept=".pdf,.jpg,.jpeg,.png"
                      className="input-field text-xs py-1.5"
                      onChange={(e) => setPcoFiles(e.target.files)}
                    />
                  </div>
                </>
              )}

              <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setShowPcoRequestModal(false)}
                  className="btn-secondary py-2 px-4 text-xs"
                >
                  Cancel
                </button>
                {(eligiblePcoDepts.length > 0 || eligiblePcoUnits.length > 0) && (
                  <button
                    type="submit"
                    disabled={submitting === 'create_pco'}
                    className="btn-dark py-2 px-4 text-xs"
                  >
                    {submitting === 'create_pco' ? 'Submitting Request...' : 'Submit Approval Request'}
                  </button>
                )}
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

/**
 * Dedicated Internal Department Communication Channel Card.
 * Physically separated from student-facing messages.
 */
export const InternalDepartmentChannelCard = ({ concern, routePrefix = '/dept-staff', onRefresh }) => {
  const coord = concern?.coordination;
  const [messageText, setMessageText] = useState('');
  const [messageType, setMessageType] = useState('internal_note');
  const [posting, setPosting] = useState(false);
  const [errorMsg, setErrorMsg] = useState('');
  const [successMsg, setSuccessMsg] = useState('');

  if (!coord) return null;

  const internalMessages = coord.internal_messages || [];
  const canRead = coord.can_read_internal_messages;
  const canPost = coord.can_post_internal_messages;

  const handlePostInternalMessage = async (e) => {
    e.preventDefault();
    if (!messageText.trim()) return;
    setErrorMsg('');
    setSuccessMsg('');
    setPosting(true);
    try {
      await api.post(`${routePrefix}/concerns/${concern.concern_id}/internal-messages`, {
        body: messageText.trim(),
        message_type: messageType,
      });
      setMessageText('');
      setSuccessMsg('Internal department note posted.');
      onRefresh?.();
    } catch (err) {
      setErrorMsg(err.response?.data?.message || 'Failed to post internal department message.');
    } finally {
      setPosting(false);
    }
  };

  const getMessageTypeBadge = (type) => {
    switch (type) {
      case 'transfer_note':
      case 'transfer_event':
        return 'bg-amber-100 text-amber-800 border-amber-200';
      case 'collaboration_note':
      case 'collaboration_event':
        return 'bg-indigo-100 text-indigo-800 border-indigo-200';
      case 'pco_approval_note':
      case 'pco_approval_event':
        return 'bg-purple-100 text-purple-800 border-purple-200';
      default:
        return 'bg-slate-100 text-slate-700 border-slate-200';
    }
  };

  return (
    <div className="card overflow-hidden border-2 border-indigo-200/80 bg-white">
      <div className="px-6 py-4 border-b border-indigo-100 bg-indigo-950 text-white flex flex-wrap items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <Lock size={15} className="text-indigo-300" />
          <h2 className="text-sm font-bold uppercase tracking-wider">Internal Department Channel</h2>
        </div>
        <div className="flex items-center gap-2">
          <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-indigo-800 text-indigo-100 border border-indigo-700">
            Department-Only • Hidden from Student
          </span>
          {canRead && (
            <span className="text-xs text-indigo-200 font-medium">{internalMessages.length} notes</span>
          )}
        </div>
      </div>

      {!canRead ? (
        <div className="p-8 text-center space-y-2 bg-slate-50">
          <Lock size={24} className="mx-auto text-amber-600" />
          <p className="text-xs font-bold text-slate-800">
            Internal Department History Locked During Transfer Preview
          </p>
          <p className="text-xs text-slate-500 max-w-md mx-auto">
            Pending transfer targets receive limited concern preview and handoff context only. Accept the transfer request above to unlock the full internal department communication thread.
          </p>
        </div>
      ) : (
        <>
          <div className="max-h-[340px] overflow-y-auto p-5 space-y-3 bg-indigo-50/25">
            {internalMessages.length === 0 ? (
              <div className="text-center py-8 text-xs text-slate-400">
                No internal department notes recorded yet. Messages posted here are strictly visible to authorized
                departments (Primary Owner, Collaborators, and PCO Approvers) and never shown to the student.
              </div>
            ) : (
              internalMessages.map((msg) => (
                <div
                  key={msg.id}
                  className="p-3.5 rounded-xl bg-white border border-indigo-100 shadow-2xs space-y-1.5 text-xs"
                >
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex flex-wrap items-center gap-1.5">
                      <span className="font-bold text-slate-900">{msg.sender_name || 'Department Staff'}</span>
                      <span className="px-2 py-0.5 rounded-md bg-blue-50 text-[#1e3a8a] font-semibold text-[10px] border border-blue-200">
                        {msg.sender_department_name || 'Department'}
                      </span>
                      <span
                        className={`px-2 py-0.5 rounded-md font-semibold text-[10px] border capitalize ${getMessageTypeBadge(
                          msg.message_type
                        )}`}
                      >
                        {(msg.message_type || 'internal_note').replace(/_/g, ' ')}
                      </span>
                    </div>
                    <span className="text-[11px] text-slate-400">{formatDateTime(msg.created_at)}</span>
                  </div>
                  <p className="text-slate-700 whitespace-pre-wrap leading-relaxed">{msg.body || msg.message}</p>
                </div>
              ))
            )}
          </div>

          {errorMsg && (
            <div className="px-4 py-2 bg-rose-50 border-t border-rose-200 text-rose-700 text-xs">{errorMsg}</div>
          )}

          {successMsg && !errorMsg && (
            <div className="px-4 py-2 bg-emerald-50 border-t border-emerald-200 text-emerald-700 text-xs">{successMsg}</div>
          )}

          {canPost ? (
            <form onSubmit={handlePostInternalMessage} className="p-4 bg-white border-t border-indigo-100 space-y-2.5">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="text-[11px] font-semibold text-indigo-900 flex items-center gap-1">
                  <ShieldCheck size={13} className="text-indigo-700" />
                  Post confidential department-to-department note
                </span>
                <select
                  className="input-field py-1 px-2 text-[11px] w-auto"
                  value={messageType}
                  onChange={(e) => setMessageType(e.target.value)}
                >
                  <option value="internal_note">General Internal Note</option>
                  <option value="collaboration_note">Collaboration Note</option>
                  <option value="pco_approval_note">PCO Approval Note</option>
                </select>
              </div>
              <div className="flex gap-2">
                <input
                  type="text"
                  className="input-field text-xs"
                  placeholder="Write an internal note for participating departments (invisible to student)..."
                  value={messageText}
                  onChange={(e) => setMessageText(e.target.value)}
                />
                <button
                  type="submit"
                  disabled={posting || !messageText.trim()}
                  className="btn-dark px-4 text-xs shrink-0 bg-indigo-900 hover:bg-indigo-950"
                >
                  <Send size={14} className="mr-1.5" />
                  <span>{posting ? 'Posting...' : 'Post Internal'}</span>
                </button>
              </div>
            </form>
          ) : (
            <div className="px-5 py-3 bg-slate-50 border-t border-slate-200 text-[11px] text-slate-500 flex items-center gap-1.5">
              <Lock size={13} className="text-slate-400" />
              <span>
                {coord.access_mode === 'completed_collaborator'
                  ? 'Your department has completed its collaboration assistance. You retain historical read-only access to this thread.'
                  : 'Read-only access to internal department notes.'}
              </span>
            </div>
          )}
        </>
      )}
    </div>
  );
};

/**
 * Right-column card for:
 * - Current Primary Department & User Access Role Badge
 * - Handshake Transfer Initiation, Pending Cancel, and Immutable Transfer History
 * - Multi-Department Collaboration Request, Active Collaborators, and Mark Assistance Completed
 */
export const CoordinationOwnershipSidebarCard = ({ concern, routePrefix = '/dept-staff', onRefresh }) => {
  const coord = concern?.coordination;
  const [departments, setDepartments] = useState([]);
  const [submitting, setSubmitting] = useState('');
  const [errorMsg, setErrorMsg] = useState('');

  // Transfer Modal state
  const [showTransferModal, setShowTransferModal] = useState(false);
  const [transferToDeptId, setTransferToDeptId] = useState('');
  const [transferReason, setTransferReason] = useState('');
  const [handoffSummary, setHandoffSummary] = useState('');

  // Collaboration Modal state
  const [showCollabModal, setShowCollabModal] = useState(false);
  const [collabDeptId, setCollabDeptId] = useState('');
  const [collabReason, setCollabReason] = useState('');
  const [collabAssistance, setCollabAssistance] = useState('');

  // Toggle transfer history
  const [showHistory, setShowHistory] = useState(false);

  useEffect(() => {
    const loadDepts = async () => {
      try {
        const res = await api.get('/departments');
        const list = Array.isArray(res.data) ? res.data : res.data?.departments || [];
        setDepartments(list);
      } catch (err) {
        console.warn('Could not fetch departments list:', err);
      }
    };
    loadDepts();
  }, []);

  if (!coord) return null;

  const roleMeta = ACCESS_MODE_LABELS[coord.access_mode] || {
    label: coord.access_mode || 'Authorized Staff',
    color: 'bg-slate-100 text-slate-700 border-slate-200',
  };

  const pendingTransfer = coord.pending_transfer;
  const transferHistory = coord.transfer_history || [];
  const collaborations = coord.collaborations || [];
  const myActiveCollab = coord.my_active_collaboration;
  const isOpenConcern = !['resolved', 'closed', 'withdrawn'].includes(concern.status);

  const availableTransferDepts = departments.filter((d) => Number(d.id) !== Number(concern.dept_id));
  const availableCollabDepts = departments.filter(
    (d) =>
      Number(d.id) !== Number(concern.dept_id) &&
      !collaborations.some(
        (c) => Number(c.collaborator_dept_id) === Number(d.id) && ['pending', 'accepted'].includes(c.status)
      )
  );

  // Check if selected transfer target previously owned or transferred this concern
  const selectedTargetCausesReversal =
    transferToDeptId &&
    transferHistory.some(
      (t) =>
        t.status === 'accepted' &&
        (Number(t.from_dept_id) === Number(transferToDeptId) || Number(t.to_dept_id) === Number(transferToDeptId))
    );

  const handleInitiateTransfer = async (e) => {
    e.preventDefault();
    if (!transferToDeptId) return;
    setErrorMsg('');
    setSubmitting('initiate_transfer');
    try {
      await api.post(`${routePrefix}/concerns/${concern.concern_id}/transfers`, {
        to_dept_id: Number(transferToDeptId),
        transfer_reason: transferReason.trim(),
        handoff_summary: handoffSummary.trim(),
      });
      setShowTransferModal(false);
      setTransferReason('');
      setHandoffSummary('');
      onRefresh?.();
    } catch (err) {
      setErrorMsg(err.response?.data?.message || 'Failed to initiate concern transfer.');
    } finally {
      setSubmitting('');
    }
  };

  const handleCancelPendingTransfer = async () => {
    if (!pendingTransfer) return;
    const notes = window.prompt('Enter reason for cancelling this pending transfer request (optional):', 'Cancelled by initiating department.');
    if (notes === null) return;
    setErrorMsg('');
    setSubmitting('cancel_transfer');
    try {
      await api.post(`${routePrefix}/concerns/${concern.concern_id}/transfers/${pendingTransfer.id}/cancel`, {
        decision_notes: notes,
      });
      onRefresh?.();
    } catch (err) {
      setErrorMsg(err.response?.data?.message || 'Failed to cancel pending transfer.');
    } finally {
      setSubmitting('');
    }
  };

  const handleRequestCollaboration = async (e) => {
    e.preventDefault();
    if (!collabDeptId) return;
    setErrorMsg('');
    setSubmitting('request_collab');
    try {
      await api.post(`${routePrefix}/concerns/${concern.concern_id}/collaborations`, {
        collaborator_dept_id: Number(collabDeptId),
        collaboration_reason: collabReason.trim(),
        requested_assistance: collabAssistance.trim(),
      });
      setShowCollabModal(false);
      setCollabReason('');
      setCollabAssistance('');
      onRefresh?.();
    } catch (err) {
      setErrorMsg(err.response?.data?.message || 'Failed to request department collaboration.');
    } finally {
      setSubmitting('');
    }
  };

  const handleCompleteCollaboration = async (collab) => {
    const notes = window.prompt(
      'Provide a brief summary of your completed departmental assistance (optional):',
      'Requested departmental verification/assistance completed.'
    );
    if (notes === null) return;
    setErrorMsg('');
    setSubmitting(`complete_collab_${collab.id}`);
    try {
      await api.post(`${routePrefix}/concerns/${concern.concern_id}/collaborations/${collab.id}/complete`, {
        response_notes: notes,
      });
      onRefresh?.();
    } catch (err) {
      setErrorMsg(err.response?.data?.message || 'Failed to mark collaboration completed.');
    } finally {
      setSubmitting('');
    }
  };

  const primaryOwnerName =
    concern.department?.name || concern.academic_unit?.name || 'Assigned Department';

  return (
    <div className="card p-6 space-y-5">
      <div>
        <h2 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">
          Interdepartmental Coordination
        </h2>

        <div className="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2 text-xs">
          <div className="flex items-center justify-between gap-2">
            <span className="text-slate-400 font-semibold uppercase text-[10px]">Primary Owner</span>
            <span className="font-bold text-slate-900 flex items-center gap-1">
              <Building2 size={13} className="text-[#1e3a8a]" />
              {primaryOwnerName}
            </span>
          </div>
          <div className="flex items-center justify-between gap-2 pt-2 border-t border-slate-200/70">
            <span className="text-slate-400 font-semibold uppercase text-[10px]">Your Role</span>
            <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold border ${roleMeta.color}`}>
              {roleMeta.label}
            </span>
          </div>
        </div>
      </div>

      {errorMsg && (
        <div className="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs">{errorMsg}</div>
      )}

      {/* 1. HANDSHAKE TRANSFER SECTION */}
      <div className="pt-3 border-t border-slate-100 space-y-3">
        <div className="flex items-center justify-between">
          <span className="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
            <ArrowRightLeft size={14} className="text-[#1e3a8a]" />
            Concern Transfer
          </span>
          {transferHistory.length > 0 && (
            <button
              type="button"
              onClick={() => setShowHistory(!showHistory)}
              className="text-[11px] font-bold text-blue-600 hover:underline flex items-center gap-1"
            >
              <History size={12} />
              <span>{showHistory ? 'Hide' : 'History'} ({transferHistory.length})</span>
            </button>
          )}
        </div>

        {pendingTransfer ? (
          <div className="p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs space-y-1.5">
            <div className="flex items-center justify-between">
              <span className="font-bold text-amber-950">
                Pending Transfer → {pendingTransfer.to_office_name}
              </span>
              <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-200 text-amber-900">
                Pending Acceptance
              </span>
            </div>
            <p className="text-[11px] text-amber-900">
              <span className="font-semibold">Reason:</span> {pendingTransfer.transfer_reason}
            </p>
            <p className="text-[11px] text-amber-800">
              Primary responsibility remains with <span className="font-semibold">{primaryOwnerName}</span> until{' '}
              {pendingTransfer.to_office_name} accepts.
            </p>
            {coord.is_primary_owner && (
              <div className="pt-1">
                <button
                  type="button"
                  onClick={handleCancelPendingTransfer}
                  disabled={submitting === 'cancel_transfer'}
                  className="text-[11px] font-bold text-rose-700 hover:underline"
                >
                  {submitting === 'cancel_transfer' ? 'Cancelling...' : 'Cancel Pending Transfer'}
                </button>
              </div>
            )}
          </div>
        ) : (
          coord.is_primary_owner &&
          isOpenConcern && (
            <button
              type="button"
              onClick={() => {
                setErrorMsg('');
                if (availableTransferDepts[0]) {
                  setTransferToDeptId(availableTransferDepts[0].id.toString());
                }
                setShowTransferModal(true);
              }}
              className="w-full btn-secondary py-2 text-xs flex items-center justify-center gap-1.5"
            >
              <ArrowRightLeft size={14} />
              <span>Transfer Concern to Another Department</span>
            </button>
          )
        )}

        {/* Immutable Transfer History */}
        {showHistory && transferHistory.length > 0 && (
          <div className="space-y-2 max-h-56 overflow-y-auto pr-1">
            {transferHistory.map((tr) => (
              <div key={tr.id} className="p-2.5 rounded-lg bg-slate-50 border border-slate-200 text-[11px] space-y-1">
                <div className="flex items-center justify-between gap-1">
                  <span className="font-bold text-slate-800">
                    {tr.from_office_name} → {tr.to_office_name}
                  </span>
                  <span
                    className={`px-1.5 py-0.5 rounded text-[10px] font-bold uppercase ${
                      tr.status === 'accepted'
                        ? 'bg-emerald-100 text-emerald-800'
                        : tr.status === 'declined'
                        ? 'bg-rose-100 text-rose-800'
                        : tr.status === 'cancelled'
                        ? 'bg-slate-200 text-slate-700'
                        : 'bg-amber-100 text-amber-800'
                    }`}
                  >
                    {tr.status}
                  </span>
                </div>
                {tr.is_reversal && (
                  <p className="text-[10px] font-semibold text-amber-800 bg-amber-100/70 px-1.5 py-0.5 rounded">
                    ⚠ {tr.reversal_warning || 'Repeated / Reversal Transfer'}
                  </p>
                )}
                <p className="text-slate-600">
                  <span className="font-semibold">Reason:</span> {tr.transfer_reason}
                </p>
                {tr.decision_notes && (
                  <p className="text-slate-600 italic">
                    <span className="font-semibold not-italic">Decision Note:</span> &ldquo;{tr.decision_notes}&rdquo;
                  </p>
                )}
                <p className="text-[10px] text-slate-400">
                  {formatDateTime(tr.decided_at || tr.created_at)}
                </p>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* 2. MULTI-DEPARTMENT COLLABORATION SECTION */}
      <div className="pt-3 border-t border-slate-100 space-y-3">
        <div className="flex items-center justify-between">
          <span className="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
            <Users size={14} className="text-indigo-700" />
            Collaborating Offices
          </span>
          <span className="text-[11px] text-slate-400 font-semibold">{collaborations.length}</span>
        </div>

        {collaborations.length === 0 ? (
          <p className="text-[11px] text-slate-500">
            No collaborating departments invited yet. Collaboration lets another office assist without changing primary
            ownership.
          </p>
        ) : (
          <div className="space-y-2">
            {collaborations.map((col) => (
              <div
                key={col.id}
                className="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-1.5"
              >
                <div className="flex items-center justify-between gap-2">
                  <span className="font-bold text-slate-900">{col.collaborator_office_name}</span>
                  <span
                    className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${
                      col.status === 'accepted'
                        ? 'bg-indigo-100 text-indigo-800'
                        : col.status === 'completed'
                        ? 'bg-emerald-100 text-emerald-800'
                        : col.status === 'declined'
                        ? 'bg-rose-100 text-rose-800'
                        : 'bg-amber-100 text-amber-800'
                    }`}
                  >
                    {col.status}
                  </span>
                </div>
                <p className="text-[11px] text-slate-600">
                  <span className="font-semibold">Requested Assistance:</span> {col.requested_assistance}
                </p>
                {col.response_notes && (
                  <p className="text-[11px] text-slate-500 italic">
                    &ldquo;{col.response_notes}&rdquo;
                  </p>
                )}
                {myActiveCollab && myActiveCollab.id === col.id && col.status === 'accepted' && (
                  <div className="pt-1.5">
                    <button
                      type="button"
                      onClick={() => handleCompleteCollaboration(col)}
                      disabled={submitting === `complete_collab_${col.id}`}
                      className="w-full btn-success py-1.5 text-xs flex items-center justify-center gap-1"
                    >
                      <CheckCircle2 size={13} />
                      <span>
                        {submitting === `complete_collab_${col.id}`
                          ? 'Completing...'
                          : 'Mark Our Assistance Completed'}
                      </span>
                    </button>
                  </div>
                )}
              </div>
            ))}
          </div>
        )}

        {coord.is_primary_owner && isOpenConcern && availableCollabDepts.length > 0 && (
          <button
            type="button"
            onClick={() => {
              setErrorMsg('');
              if (availableCollabDepts[0]) {
                setCollabDeptId(availableCollabDepts[0].id.toString());
              }
              setShowCollabModal(true);
            }}
            className="w-full btn-secondary py-2 text-xs flex items-center justify-center gap-1.5 text-indigo-800 border-indigo-200 hover:bg-indigo-50"
          >
            <Plus size={14} />
            <span>Request Department Collaboration</span>
          </button>
        )}
      </div>

      {/* Modal: Initiate Handshake Transfer */}
      {showTransferModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl shadow-xl max-w-md w-full border border-slate-200 overflow-hidden">
            <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
              <div className="flex items-center gap-2">
                <ArrowRightLeft size={17} className="text-[#1e3a8a]" />
                <div>
                  <h3 className="text-sm font-bold text-slate-900">Transfer Concern Ownership</h3>
                  <p className="text-[11px] text-slate-500">
                    Your office retains ownership until the receiving department accepts
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setShowTransferModal(false)}
                className="text-slate-400 hover:text-slate-600 p-1"
              >
                <X size={18} />
              </button>
            </div>

            <form onSubmit={handleInitiateTransfer} className="p-6 space-y-4 text-xs">
              <div>
                <label className="block font-bold text-slate-700 mb-1">Receiving Department *</label>
                <select
                  required
                  className="input-field text-xs"
                  value={transferToDeptId}
                  onChange={(e) => setTransferToDeptId(e.target.value)}
                >
                  {availableTransferDepts.map((d) => (
                    <option key={d.id} value={d.id}>
                      {d.name}
                    </option>
                  ))}
                </select>
              </div>

              {selectedTargetCausesReversal && (
                <div className="p-3 rounded-xl bg-amber-50 border border-amber-300 text-amber-950 text-[11px] flex items-start gap-2">
                  <AlertTriangle size={15} className="text-amber-700 shrink-0 mt-0.5" />
                  <div>
                    <p className="font-bold">Repeated / Reversal Transfer Notice</p>
                    <p className="mt-0.5">
                      This department previously owned or transferred this concern. The transfer is permitted (no
                      transfer cap), and an audit indicator will be recorded in the transfer history.
                    </p>
                  </div>
                </div>
              )}

              <div>
                <label className="block font-bold text-slate-700 mb-1">Mandatory Transfer Reason *</label>
                <textarea
                  rows={2}
                  required
                  minLength={5}
                  className="input-field text-xs"
                  placeholder="Why is this concern being transferred to the selected department?"
                  value={transferReason}
                  onChange={(e) => setTransferReason(e.target.value)}
                />
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Mandatory Handoff Summary *</label>
                <textarea
                  rows={3}
                  required
                  minLength={5}
                  className="input-field text-xs"
                  placeholder="Summarize findings so far and what the receiving department needs to handle..."
                  value={handoffSummary}
                  onChange={(e) => setHandoffSummary(e.target.value)}
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setShowTransferModal(false)}
                  className="btn-secondary py-2 px-4 text-xs"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={submitting === 'initiate_transfer'}
                  className="btn-dark py-2 px-4 text-xs"
                >
                  {submitting === 'initiate_transfer' ? 'Sending Request...' : 'Send Transfer Request'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal: Request Multi-Department Collaboration */}
      {showCollabModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl shadow-xl max-w-md w-full border border-slate-200 overflow-hidden">
            <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
              <div className="flex items-center gap-2">
                <Users size={17} className="text-indigo-700" />
                <div>
                  <h3 className="text-sm font-bold text-slate-900">Request Department Collaboration</h3>
                  <p className="text-[11px] text-slate-500">
                    Invite another department to assist while your office retains primary ownership
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setShowCollabModal(false)}
                className="text-slate-400 hover:text-slate-600 p-1"
              >
                <X size={18} />
              </button>
            </div>

            <form onSubmit={handleRequestCollaboration} className="p-6 space-y-4 text-xs">
              <div>
                <label className="block font-bold text-slate-700 mb-1">Participating Department *</label>
                <select
                  required
                  className="input-field text-xs"
                  value={collabDeptId}
                  onChange={(e) => setCollabDeptId(e.target.value)}
                >
                  {availableCollabDepts.map((d) => (
                    <option key={d.id} value={d.id}>
                      {d.name}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Collaboration Reason *</label>
                <textarea
                  rows={2}
                  required
                  minLength={5}
                  className="input-field text-xs"
                  placeholder="Why is multi-department collaboration needed on this concern?"
                  value={collabReason}
                  onChange={(e) => setCollabReason(e.target.value)}
                />
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Requested Assistance *</label>
                <textarea
                  rows={3}
                  required
                  minLength={5}
                  className="input-field text-xs"
                  placeholder="Specify the exact verification, ledger check, or operational action needed from this department..."
                  value={collabAssistance}
                  onChange={(e) => setCollabAssistance(e.target.value)}
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setShowCollabModal(false)}
                  className="btn-secondary py-2 px-4 text-xs"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={submitting === 'request_collab'}
                  className="btn-dark py-2 px-4 text-xs bg-indigo-700 hover:bg-indigo-800"
                >
                  {submitting === 'request_collab' ? 'Sending Invitation...' : 'Send Collaboration Request'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default {
  CoordinationActionBanners,
  InternalDepartmentChannelCard,
  CoordinationOwnershipSidebarCard,
};
