import { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { 
  Paperclip, 
  Send, 
  CheckCircle2, 
  ChevronLeft, 
  Sparkles, 
  User, 
  Clock, 
  Sliders,
  X,
  AlertTriangle,
  ShieldCheck,
  Building2,
  Inbox
} from 'lucide-react';
import api from '../api';
import StatusBadge from '../components/StatusBadge';
import PriorityBadge from '../components/PriorityBadge';
import AiCategoryBadge from '../components/AiCategoryBadge';
import AiSummaryCard from '../components/AiSummaryCard';
import SlaStatusBadge from '../components/SlaStatusBadge';
import SlaTrackingCard from '../components/SlaTrackingCard';

const SaoConcernDetails = () => {
  const { id } = useParams();
  const { user } = useAuth();
  const navigate = useNavigate();

  const [concern, setConcern] = useState(null);
  const [messages, setMessages] = useState([]);
  const [newMessage, setNewMessage] = useState('');
  const [loading, setLoading] = useState(true);
  const [errorMsg, setErrorMsg] = useState('');

  // Priority override modal state
  const [showOverrideModal, setShowOverrideModal] = useState(false);
  const [selectedPriority, setSelectedPriority] = useState('medium');
  const [overrideReason, setOverrideReason] = useState('');
  const [overrideError, setOverrideError] = useState('');
  const [overrideSubmitting, setOverrideSubmitting] = useState(false);

  const messagesEndRef = useRef(null);

  useEffect(() => {
    fetchConcernData();
  }, [id]);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const fetchConcernData = async () => {
    setLoading(true);
    setErrorMsg('');
    try {
      const res = await api.get(`/sao/concerns/${id}`);
      setConcern(res.data.concern);
      setMessages(res.data.concern.messages || []);
    } catch (err) {
      if (err.response?.status === 403) {
        setErrorMsg(err.response?.data?.message || 'Access Denied: This concern is not assigned to the Student Affairs Office.');
      } else if (err.response?.status === 404) {
        setErrorMsg('Concern not found.');
      } else {
        setErrorMsg('Failed to load concern details.');
      }
    } finally {
      setLoading(false);
    }
  };

  const handleSendMessage = async (e) => {
    e.preventDefault();
    if (!newMessage.trim()) return;

    try {
      await api.post(`/sao/concerns/${id}/reply`, { body: newMessage, is_internal: false });
      setNewMessage('');
      fetchConcernData();
    } catch (err) {
      console.error(err);
      alert(err.response?.data?.message || 'Failed to send message.');
    }
  };

  const openOverrideModal = () => {
    setSelectedPriority(concern?.priority || 'medium');
    setOverrideReason('');
    setOverrideError('');
    setShowOverrideModal(true);
  };

  const handleOverrideSubmit = async (e) => {
    e.preventDefault();
    setOverrideError('');
    if (!overrideReason.trim() || overrideReason.trim().length < 5) {
      setOverrideError('A justification of at least 5 characters is required.');
      return;
    }
    setOverrideSubmitting(true);
    try {
      const res = await api.post(`/sao/concerns/${id}/priority-override`, {
        priority: selectedPriority,
        reason: overrideReason.trim(),
      });
      setConcern(res.data.concern);
      setShowOverrideModal(false);
      fetchConcernData();
    } catch (err) {
      setOverrideError(err.response?.data?.message || 'Failed to override priority.');
    } finally {
      setOverrideSubmitting(false);
    }
  };

  const formatDate = (dateStr, includeTime = true) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      ...(includeTime ? { hour: '2-digit', minute: '2-digit' } : {})
    });
  };

  const getStatusBadge = (st) => {
    switch (st) {
      case 'pending':
        return <span className="inline-flex items-center gap-1 px-3 py-1 bg-amber-50 text-amber-700 rounded-full text-xs font-bold border border-amber-200">⏱ Pending</span>;
      case 'in_progress':
        return <span className="inline-flex items-center gap-1 px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-xs font-bold border border-blue-200">🔄 In Progress</span>;
      case 'escalated':
        return <span className="inline-flex items-center gap-1 px-3 py-1 bg-purple-50 text-purple-700 rounded-full text-xs font-bold border border-purple-200">⬆ Escalated</span>;
      case 'resolved':
        return <span className="inline-flex items-center gap-1 px-3 py-1 bg-emerald-50 text-emerald-700 rounded-full text-xs font-bold border border-emerald-200">✓ Resolved</span>;
      case 'rejected':
        return <span className="inline-flex items-center gap-1 px-3 py-1 bg-rose-50 text-rose-700 rounded-full text-xs font-bold border border-rose-200">✕ Rejected</span>;
      default:
        return <span className="inline-flex items-center gap-1 px-3 py-1 bg-slate-100 text-slate-700 rounded-full text-xs font-bold capitalize">{st}</span>;
    }
  };

  if (loading) {
    return (
      <div className="p-16 text-center text-slate-500 flex flex-col items-center justify-center gap-3">
        <div className="w-8 h-8 border-3 border-blue-200 border-t-[#1e3a8a] rounded-full animate-spin"></div>
        <p className="text-xs font-semibold">Loading SAO assigned concern details...</p>
      </div>
    );
  }

  if (errorMsg || !concern) {
    return (
      <div className="max-w-2xl mx-auto py-16 text-center space-y-4 px-4">
        <div className="w-12 h-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mx-auto border border-rose-200">
          <AlertTriangle size={24} />
        </div>
        <h2 className="text-xl font-bold text-slate-900">Access Restricted</h2>
        <p className="text-sm text-slate-600 leading-relaxed bg-slate-50 p-4 rounded-xl border border-slate-200 text-left">
          {errorMsg || 'You are not authorized to view this concern.'}
        </p>
        <div>
          <Link
            to="/sao/assigned-concerns"
            className="inline-flex items-center gap-1.5 px-4 py-2 bg-[#1e3a8a] text-white rounded-xl text-xs font-bold hover:bg-blue-900 transition-colors"
          >
            <ChevronLeft size={14} /> Back to Assigned Concerns
          </Link>
        </div>
      </div>
    );
  }

  const studentName = concern.student?.name || (concern.is_anonymous ? 'Anonymous Student' : 'Student');
  const studentId = concern.student?.student_id_number || (concern.is_anonymous ? '********' : '—');
  const departmentName = concern.department?.name || 'Student Affairs Office';

  return (
    <div className="pb-12 max-w-6xl mx-auto">
      {/* Breadcrumb */}
      <div className="flex items-center text-sm text-slate-500 mb-6">
        <Link to="/sao/assigned-concerns" className="hover:text-slate-900 flex items-center gap-1">
          <ChevronLeft size={16} /> Assigned Concerns
        </Link>
        <span className="mx-2">›</span>
        <span className="font-medium text-slate-700">Case #SAO-{concern.concern_id.toString().padStart(4, '0')}</span>
      </div>

      {/* Header */}
      <div className="mb-6">
        <div className="flex flex-wrap items-center gap-2 mb-2">
          <span className="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 border border-blue-200 flex items-center gap-1">
            <ShieldCheck size={12} />
            SAO Assigned Concern
          </span>
          {concern.issue_category && (
            <span className="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
              {concern.issue_category} {concern.issue_subcategory ? `› ${concern.issue_subcategory}` : ''}
            </span>
          )}
          <span className="px-2.5 py-0.5 bg-slate-100 text-slate-600 rounded-full text-xs font-bold">
            ID: #SAO-{concern.concern_id.toString().padStart(4, '0')}
          </span>
          {getStatusBadge(concern.status)}
          <SlaStatusBadge sla={concern.sla} status={concern.status} size="sm" showRemaining={true} />
        </div>
        <h1 className="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">{concern.title}</h1>
        <p className="text-slate-500 text-xs sm:text-sm mt-1.5">
          Submitted {formatDate(concern.submitted_at)} • Last updated {formatDate(concern.last_updated)}
        </p>
      </div>

      {/* Grid Layout */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left 2 Cols: Main Content */}
        <div className="lg:col-span-2 space-y-6">
          {/* AI Summary (if available) */}
          {concern.ai_summary && (
            <div className="bg-blue-50 border border-blue-200 rounded-2xl p-5">
              <div className="flex items-center gap-2 mb-2">
                <div className="h-6 w-6 rounded-md bg-blue-600 flex items-center justify-center text-white">
                  <Sparkles size={13} />
                </div>
                <span className="text-xs font-bold text-blue-900 uppercase tracking-wider">AI Summary</span>
              </div>
              <p className="text-sm text-blue-900 leading-relaxed break-words whitespace-normal">{concern.ai_summary}</p>
            </div>
          )}

          {/* Description */}
          <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 className="text-sm font-bold text-slate-900 uppercase tracking-wider mb-3">Concern Description</h2>
            <div className="text-sm text-slate-700 whitespace-pre-wrap leading-relaxed bg-slate-50 rounded-xl p-4 border border-slate-100">
              {concern.description}
            </div>

            {concern.attachments && concern.attachments.length > 0 && (
              <div className="mt-5 pt-4 border-t border-slate-100">
                <p className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Attachments ({concern.attachments.length})</p>
                <div className="flex flex-wrap gap-2">
                  {concern.attachments.map((att, idx) => {
                    const filePath = typeof att === 'string' ? att : (att?.file_path || att?.path || '');
                    const fileName = typeof att === 'string' ? `Attachment ${idx + 1}` : (att?.file_name || att?.name || `Attachment ${idx + 1}`);
                    if (!filePath) return null;
                    const fullUrl = filePath.startsWith('http') ? filePath : `http://localhost:8000${filePath.startsWith('/') ? '' : '/'}${filePath}`;
                    return (
                      <a
                        key={idx}
                        href={fullUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-medium text-slate-700 hover:bg-slate-50 shadow-sm"
                      >
                        <Paperclip size={12} className="text-slate-400" />
                        {fileName}
                      </a>
                    );
                  })}
                </div>
              </div>
            )}
          </div>

          {/* Workflow Routing History Timeline */}
          <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 className="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4">Routing & Action History</h2>
            {(!concern.routing_history || concern.routing_history.length === 0) ? (
              <p className="text-xs text-slate-400">No routing events recorded yet.</p>
            ) : (
              <div className="space-y-4">
                {concern.routing_history.map((record, i) => (
                  <div key={record.id || i} className="flex items-start gap-3 text-xs">
                    <div className="w-2 h-2 rounded-full bg-blue-600 mt-1.5 flex-shrink-0" />
                    <div className="flex-1">
                      <div className="flex items-center justify-between">
                        <span className="font-bold text-slate-900 capitalize">
                          {record.action?.replace(/_/g, ' ')}
                        </span>
                        <span className="text-slate-400 text-[11px]">
                          {formatDate(record.created_at)}
                        </span>
                      </div>
                      <p className="text-slate-600 mt-0.5">{record.notes || 'Action logged.'}</p>
                      {(record.performer || record.performed_by_name || record.actor_name) && (
                        <p className="text-[10px] text-slate-400 mt-0.5">
                          By: {record.performer?.name || record.performed_by_name || record.actor_name}
                          {record.performer?.role ? ` (${record.performer.role})` : ' (Historical Record)'}
                        </p>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Communication Thread */}
          <div id="messages" className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col h-[450px]">
            <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
              <h2 className="text-sm font-bold text-slate-900 uppercase tracking-wider">Messages & Remarks</h2>
              <span className="text-xs text-slate-400">{messages.length} messages</span>
            </div>

            <div className="flex-1 overflow-y-auto p-5 space-y-4 bg-slate-50/50">
              {messages.length === 0 ? (
                <div className="text-center text-slate-400 text-xs py-10">No messages in this thread yet.</div>
              ) : (
                messages.map((msg, idx) => {
                  const isMine = user && msg.sender_id === user.id;
                  return (
                    <div key={msg.id || idx} className={`flex ${isMine ? 'justify-end' : 'justify-start'} mb-3`}>
                      <div className={`max-w-[75%] flex flex-col ${isMine ? 'items-end' : 'items-start'}`}>
                        <span className="text-[11px] text-slate-400 mb-1 font-medium">
                          {msg.sender?.name || 'Staff'} ({msg.sender?.role || 'Staff'}) • {formatDate(msg.created_at)}
                        </span>
                        <div className={`p-3.5 rounded-2xl text-xs sm:text-sm leading-relaxed ${
                          isMine 
                            ? 'bg-[#1e3a8a] text-white rounded-tr-sm' 
                            : 'bg-white border border-slate-200 text-slate-800 rounded-tl-sm shadow-sm'
                        }`}>
                          {msg.body}
                        </div>
                      </div>
                    </div>
                  );
                })
              )}
              <div ref={messagesEndRef} />
            </div>

            <div className="p-4 bg-white border-t border-slate-200">
              <form onSubmit={handleSendMessage} className="flex items-center gap-2">
                <input
                  type="text"
                  className="flex-1 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                  placeholder="Type an official SAO response or administrative remark..."
                  value={newMessage}
                  onChange={(e) => setNewMessage(e.target.value)}
                />
                <button
                  type="submit"
                  disabled={!newMessage.trim()}
                  className="px-4 py-2.5 bg-[#1e3a8a] hover:bg-blue-900 text-white rounded-xl text-xs sm:text-sm font-semibold transition-colors disabled:opacity-50"
                >
                  Send
                </button>
              </form>
            </div>
          </div>
        </div>

        {/* Right Col: Priority Management & Metadata */}
        <div className="space-y-6">
          {/* Resolution Timeframe & Automatic Follow-up Card */}
          <SlaTrackingCard concern={concern} />

          {/* Metadata Card */}
          <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Metadata Overview</h2>
            <div className="space-y-3 text-xs">
              <div>
                <p className="text-slate-400 font-semibold mb-0.5">Assigned Department</p>
                <p className="text-slate-900 font-bold">{departmentName}</p>
              </div>
              <div>
                <p className="text-slate-400 font-semibold mb-0.5">Student Submitter</p>
                <p className="text-slate-900 font-bold">{studentName}</p>
                <p className="text-slate-500 text-[11px]">ID: {studentId}</p>
              </div>
              <div>
                <p className="text-slate-400 font-semibold mb-0.5">Workflow Track</p>
                <p className="text-slate-900 font-bold capitalize">{concern.workflow_type?.replace('_', ' ') || 'Non Academic'}</p>
              </div>
              <div>
                <p className="text-slate-400 font-semibold mb-0.5">Category</p>
                <p className="text-slate-900 font-bold capitalize">{concern.category?.replace(/_/g, ' ') || 'General'}</p>
              </div>
              {concern.issue_category && (
                <div>
                  <p className="text-slate-400 font-semibold mb-0.5">Issue Classification</p>
                  <p className="text-slate-900 font-bold">{concern.issue_category}</p>
                  {concern.issue_subcategory && (
                    <p className="text-slate-500 text-[11px]">{concern.issue_subcategory}</p>
                  )}
                </div>
              )}

              {/* Priority Section with Override */}
              <div className="pt-3 border-t border-slate-100">
                <div className="flex items-center justify-between mb-1.5">
                  <p className="text-slate-400 font-semibold">Priority</p>
                  <button
                    type="button"
                    onClick={openOverrideModal}
                    className="inline-flex items-center gap-1 text-[11px] font-bold text-blue-600 hover:text-blue-800 hover:underline cursor-pointer"
                  >
                    <Sliders size={12} /> Override Priority
                  </button>
                </div>

                <div className="flex items-center gap-2">
                  <PriorityBadge priority={concern.priority} isOverridden={concern.is_priority_overridden} size="sm" />
                </div>

                {concern.is_priority_overridden && (
                  <div className="mt-2.5 p-2.5 bg-slate-50 rounded-lg border border-slate-200 text-[11px] space-y-1">
                    <p className="text-slate-500">
                      <span className="font-semibold text-slate-700">AI Suggested:</span>{' '}
                      <span className="capitalize">{concern.ai_priority || 'N/A'}</span>
                    </p>
                    {concern.priority_override_reason && (
                      <p className="text-slate-500">
                        <span className="font-semibold text-slate-700">Reason:</span>{' '}
                        {concern.priority_override_reason}
                      </p>
                    )}
                    {concern.priority_overridden_by && (
                      <p className="text-slate-400 text-[10px]">
                        By {concern.priority_overridden_by?.name || (typeof concern.priority_overridden_by === 'string' ? concern.priority_overridden_by : 'SAO')} • {formatDate(concern.priority_overridden_at)}
                      </p>
                    )}
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Priority Override Modal */}
      {showOverrideModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-lg max-w-md w-full border border-slate-200 overflow-hidden animate-in fade-in duration-150">
            <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
              <div className="flex items-center gap-2">
                <div className="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center">
                  <Sliders size={16} />
                </div>
                <div>
                  <h3 className="text-sm font-bold text-slate-900">SAO Override Priority</h3>
                  <p className="text-[11px] text-slate-500">Adjust the administrative priority for this SAO concern</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setShowOverrideModal(false)}
                className="text-slate-400 hover:text-slate-600 rounded-lg p-1"
              >
                <X size={18} />
              </button>
            </div>

            <form onSubmit={handleOverrideSubmit} className="p-6 space-y-4">
              {overrideError && (
                <div className="p-3 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700 flex items-start gap-2">
                  <AlertTriangle size={15} className="flex-shrink-0 mt-0.5" />
                  <span>{overrideError}</span>
                </div>
              )}

              {/* Status Comparison */}
              <div className="grid grid-cols-2 gap-3 p-3 bg-slate-50 rounded-xl border border-slate-100 text-xs">
                <div>
                  <span className="text-[10px] text-slate-400 uppercase font-bold tracking-wider block">Current Priority</span>
                  <span className="font-bold text-slate-800 capitalize">{concern.priority || 'Medium'}</span>
                </div>
                <div>
                  <span className="text-[10px] text-slate-400 uppercase font-bold tracking-wider block">AI Recommendation</span>
                  <span className="font-bold text-blue-700 capitalize">{concern.ai_priority || concern.priority || 'Normal'}</span>
                </div>
              </div>

              {/* Priority Select */}
              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">
                  New Priority Level <span className="text-red-500">*</span>
                </label>
                <div className="grid grid-cols-2 gap-2">
                  {[
                    { value: 'low', label: 'Low' },
                    { value: 'medium', label: 'Medium' },
                    { value: 'high', label: 'High' },
                    { value: 'critical', label: 'Critical' },
                  ].map((lvl) => (
                    <label
                      key={lvl.value}
                      className={`flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer text-xs font-semibold transition-all ${
                        selectedPriority === lvl.value
                          ? 'border-blue-600 bg-blue-50/60 ring-2 ring-blue-500/20 text-blue-900'
                          : 'border-slate-200 hover:bg-slate-50 text-slate-700'
                      }`}
                    >
                      <input
                        type="radio"
                        name="priorityLevel"
                        value={lvl.value}
                        checked={selectedPriority === lvl.value}
                        onChange={(e) => setSelectedPriority(e.target.value)}
                        className="text-blue-600 focus:ring-blue-500"
                      />
                      <span>{lvl.label}</span>
                    </label>
                  ))}
                </div>
              </div>

              {/* Justification Textarea */}
              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">
                  Justification / Reason <span className="text-red-500">*</span>
                </label>
                <textarea
                  rows={3}
                  value={overrideReason}
                  onChange={(e) => setOverrideReason(e.target.value)}
                  placeholder="Provide an explanation for overriding this priority..."
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                  required
                />
                <p className="text-[10px] text-slate-400 mt-1">Minimum 5 characters. This justification is logged into the immutable audit trail.</p>
              </div>

              {/* Modal Buttons */}
              <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setShowOverrideModal(false)}
                  disabled={overrideSubmitting}
                  className="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={overrideSubmitting}
                  className="px-4 py-2 rounded-xl text-xs font-bold bg-[#1e3a8a] hover:bg-blue-900 text-white shadow-sm transition-colors disabled:opacity-60"
                >
                  {overrideSubmitting ? 'Saving...' : 'Confirm Override'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default SaoConcernDetails;
