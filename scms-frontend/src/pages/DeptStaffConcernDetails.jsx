import { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { Paperclip, Send, CheckCircle2, ArrowUpCircle, ArrowRightLeft, ChevronLeft, Sparkles, User, AlertCircle } from 'lucide-react';
import api from '../api';
import StatusBadge from '../components/StatusBadge';
import PriorityBadge from '../components/PriorityBadge';
import AiCategoryBadge from '../components/AiCategoryBadge';
import AiSummaryCard from '../components/AiSummaryCard';
import EmptyState from '../components/EmptyState';
import SlaStatusBadge from '../components/SlaStatusBadge';
import SlaTrackingCard from '../components/SlaTrackingCard';

const DeptStaffConcernDetails = () => {
  const { id } = useParams();
  const { user } = useAuth();
  const navigate = useNavigate();
  const [concern, setConcern] = useState(null);
  const [messages, setMessages] = useState([]);
  const [newMessage, setNewMessage] = useState('');
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState('');
  const messagesEndRef = useRef(null);

  useEffect(() => { fetchData(); }, [id]);
  useEffect(() => { messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' }); }, [messages]);
  useEffect(() => {
    if (window.location.hash === '#messages') {
      const el = document.getElementById('messages');
      if (el) {
        setTimeout(() => {
          el.scrollIntoView({ behavior: 'smooth' });
        }, 200);
      }
    }
  }, [loading]);

  const fetchData = async () => {
    try {
      const res = await api.get(`/dept-staff/concerns/${id}`);
      setConcern(res.data.concern);
      setMessages(res.data.concern.messages || []);
    } catch (err) {
      console.warn('Direct fetch failed, trying queue fallback:', err);
      try {
        const queueRes = await api.get('/dept-staff/queue');
        const found = queueRes.data.concerns.find(c => c.concern_id.toString() === id.toString());
        setConcern(found || null);
        if (found) {
          const msgRes = await api.get(`/dept-staff/concerns/${id}/messages`);
          setMessages(msgRes.data.messages || []);
        }
      } catch (fallbackErr) {
        console.error(fallbackErr);
        setConcern(null);
      }
    } finally {
      setLoading(false);
    }
  };

  const handleSendMessage = async (e) => {
    e.preventDefault();
    if (!newMessage.trim()) return;
    try {
      await api.post(`/dept-staff/concerns/${id}/reply`, { body: newMessage, is_internal: false });
      setNewMessage('');
      fetchData();
    } catch (err) {
      console.error(err);
      alert('Failed to send message');
    }
  };

  const handleAction = async (action) => {
    setActionLoading(action);
    try {
      if (action === 'resolve') {
        await api.post(`/dept-staff/concerns/${id}/resolve`);
      } else if (action === 'in_progress') {
        await api.post(`/dept-staff/concerns/${id}/status`, { status: 'in_progress' });
      } else if (action === 'escalate') {
        const confirmMsg = isAcademic 
          ? 'Forward this academic concern to the Dean for review?' 
          : 'Escalate this concern to the Dean?';
        if (!window.confirm(confirmMsg)) { setActionLoading(''); return; }
        await api.post(`/dept-staff/concerns/${id}/escalate`);
      } else if (action === 'forward_cad') {
        if (!window.confirm('Forward this non-academic concern to CAD (Campus Administration) for approval?')) { 
          setActionLoading(''); 
          return; 
        }
        await api.post(`/dept-staff/concerns/${id}/forward-cad`);
      }
      fetchData();
    } catch (err) {
      alert(err.response?.data?.message || 'Action failed.');
    } finally {
      setActionLoading('');
    }
  };

  const formatDate = (d) => {
    if (!d) return '—';
    const date = new Date(d);
    if (isNaN(date.getTime())) return '—';
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  };

  const getInitials = (name) => {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
  };

  if (loading) {
    return (
      <div className="p-16 text-center text-slate-500 flex flex-col items-center justify-center gap-3">
        <div className="w-8 h-8 border-3 border-blue-200 border-t-[#1e3a8a] rounded-full animate-spin"></div>
        <p className="text-xs font-semibold">Loading concern details...</p>
      </div>
    );
  }
  if (!concern) return (
    <div className="text-center py-16 card p-8 max-w-md mx-auto my-8">
      <AlertCircle className="mx-auto h-12 w-12 text-slate-400 mb-3" />
      <h3 className="text-base font-bold text-slate-800 mb-1">Concern Not Found</h3>
      <p className="text-slate-500 text-sm mb-4">This concern could not be found or is not in your department queue.</p>
      <Link to="/dept-staff/concerns" className="btn-dark text-xs">← Back to Concerns</Link>
    </div>
  );

  const studentName = concern.student?.name || 'Unknown Student';
  const studentId = concern.student?.student_id_number || '—';
  const departmentName = concern.department?.name || concern.academic_unit?.name || '—';
  const isAcademic = concern.workflow_type === 'academic' || concern.category === 'academic_units';
  const canResolve = ['pending', 'in_progress', 'approved', 'returned'].includes(concern.status);
  const canMarkInProgress = ['pending', 'returned'].includes(concern.status);
  const canEscalate = ['pending', 'in_progress', 'returned'].includes(concern.status);

  return (
    <div className="pb-12 space-y-6">
      {/* Breadcrumb & Navigation */}
      <div className="flex items-center text-xs text-slate-500">
        <Link to="/dept-staff/concerns" className="hover:text-blue-600 flex items-center gap-1 font-medium">
          <ChevronLeft size={14} /> Concern Queue
        </Link>
        <span className="mx-2">›</span>
        <span className="font-semibold text-slate-700">#REQ-{concern.concern_id.toString().padStart(4, '0')}</span>
      </div>

      {/* Header */}
      <div className="card p-6 bg-white">
        <div className="flex flex-wrap items-center gap-2 mb-3">
          <PriorityBadge priority={concern.priority} isOverridden={concern.is_priority_overridden} size="sm" />
          <StatusBadge status={concern.status} size="sm" />
          <SlaStatusBadge sla={concern.sla} status={concern.status} size="sm" showRemaining={true} />
          {concern.issue_category && (
            <AiCategoryBadge
              category={concern.issue_category}
              subcategory={concern.issue_subcategory}
              confidence={concern.classification_confidence}
              showConfidence={true}
              size="sm"
            />
          )}
          <span className="px-2.5 py-0.5 bg-slate-100 text-slate-600 rounded-full text-xs font-mono font-bold">
            ID: #REQ-{concern.concern_id.toString().padStart(4, '0')}
          </span>
        </div>
        <h1 className="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">{concern.title}</h1>
        <p className="text-slate-500 text-xs sm:text-sm mt-2">
          Submitted by <span className="font-semibold text-slate-700">{studentName} (ID: {studentId})</span> • {formatDate(concern.submitted_at || concern.created_at)}
        </p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left Column: Details, AI Summary, Messages */}
        <div className="lg:col-span-2 space-y-6">

          {/* AI Summary Card */}
          {concern.ai_summary && (
            <AiSummaryCard summary={concern.ai_summary} />
          )}

          {/* Original Request Details */}
          <div className="card">
            <div className="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
              <h2 className="text-sm font-bold text-slate-900 uppercase tracking-wider">Student Description</h2>
              <span className="text-xs text-slate-400 font-medium">
                Track: {isAcademic ? 'Academic' : 'Operational'}
              </span>
            </div>
            <div className="p-6">
              <div className="text-sm text-slate-700 whitespace-pre-wrap leading-relaxed bg-slate-50 rounded-xl p-4 border border-slate-100 mb-4 break-words">
                {concern.description}
              </div>
              {concern.attachments && concern.attachments.length > 0 && (
                <div>
                  <p className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Attachments ({concern.attachments.length})</p>
                  <div className="flex flex-wrap gap-2">
                    {concern.attachments.map((att, i) => {
                      const filePath = typeof att === 'string' ? att : (att?.file_path || att?.path || '');
                      const fileName = typeof att === 'string' ? `Attachment ${i + 1}` : (att?.file_name || att?.name || `Attachment ${i + 1}`);
                      if (!filePath) return null;
                      const fullUrl = filePath.startsWith('http') ? filePath : `http://localhost:8000${filePath.startsWith('/') ? '' : '/'}${filePath}`;
                      return (
                        <a
                          key={i}
                          href={fullUrl}
                          target="_blank"
                          rel="noreferrer"
                          className="btn-secondary py-1.5 px-3 text-xs inline-flex items-center gap-1.5 text-blue-600 hover:text-blue-800"
                        >
                          <Paperclip size={13} />
                          <span>{fileName}</span>
                        </a>
                      );
                    })}
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Workflow Routing & Follow-up Audit History */}
          {Array.isArray(concern.routing_history) && concern.routing_history.length > 0 && (
            <div className="card p-6">
              <h2 className="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4">Routing &amp; Follow-up History</h2>
              <div className="space-y-3">
                {concern.routing_history.map((record, i) => (
                  <div key={record.id || i} className="flex items-start gap-3 text-xs">
                    <div className="w-2 h-2 rounded-full bg-[#1e3a8a] mt-1.5 shrink-0" />
                    <div className="flex-1">
                      <div className="flex items-center justify-between gap-2">
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
                        </p>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Communication Thread */}
          <div id="messages" className="card flex flex-col h-[520px]">
            <div className="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
              <h2 className="text-sm font-bold text-slate-900 uppercase tracking-wider">Communication Activity</h2>
              <span className="text-xs text-slate-500 font-medium">{messages.length} messages</span>
            </div>

            <div className="flex-1 overflow-y-auto p-5 space-y-4 bg-slate-50/30">
              {messages.length === 0 ? (
                <EmptyState
                  title="No messages yet"
                  description="Use the input below to reply to the student or provide instructional guidance."
                  className="border-0 shadow-none py-12"
                />
              ) : (
                messages.map((msg, idx) => {
                  const isMine = msg.sender_id === user?.id;
                  return (
                    <div key={msg.id || idx} className={`flex ${isMine ? 'justify-end' : 'justify-start'}`}>
                      <div className={`max-w-md ${isMine ? 'items-end' : 'items-start'} flex flex-col`}>
                        <div className="flex items-center gap-1.5 text-[10px] text-slate-400 mb-1 px-1">
                          <span className="font-bold text-slate-600">{isMine ? 'You (Staff)' : (msg.sender?.name || studentName)}</span>
                          <span>•</span>
                          <span>{new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                        </div>
                        <div className={`p-3.5 rounded-xl text-xs sm:text-sm leading-relaxed break-words ${
                          isMine 
                            ? 'bg-[#1e3a8a] text-white rounded-tr-xs shadow-2xs' 
                            : 'bg-white border border-slate-200 text-slate-800 rounded-tl-xs shadow-2xs'
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
              <form onSubmit={handleSendMessage} className="flex gap-2">
                <input
                  type="text"
                  placeholder="Type an official response or request more details..."
                  className="input-field"
                  value={newMessage}
                  onChange={(e) => setNewMessage(e.target.value)}
                />
                <button
                  type="submit"
                  disabled={!newMessage.trim()}
                  className="btn-dark px-4 shrink-0"
                >
                  <Send size={15} className="mr-1.5" />
                  <span>Send</span>
                </button>
              </form>
            </div>
          </div>
        </div>

        {/* Right Column: Actions & Student Info */}
        <div className="space-y-6">
          {/* Resolution Timeframe & Automatic Follow-up Card */}
          <SlaTrackingCard concern={concern} />

          {/* Resolution Actions */}
          <div className="card p-6">
            <h2 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4">Workflow Actions</h2>
            <div className="space-y-2.5">
              {canResolve && (
                <button
                  onClick={() => handleAction('resolve')}
                  disabled={!!actionLoading}
                  className="w-full btn-success py-2.5 text-xs flex items-center justify-center gap-1.5"
                >
                  <CheckCircle2 size={16} />
                  <span>{actionLoading === 'resolve' ? 'Resolving...' : 'Resolve Concern'}</span>
                </button>
              )}

              {canMarkInProgress && (
                <button
                  onClick={() => handleAction('in_progress')}
                  disabled={!!actionLoading}
                  className="w-full btn-secondary py-2.5 text-xs flex items-center justify-center gap-1.5"
                >
                  <ArrowUpCircle size={16} />
                  <span>{actionLoading === 'in_progress' ? 'Updating...' : 'Mark In Progress'}</span>
                </button>
              )}

              {/* Academic Forward to Dean */}
              {canEscalate && isAcademic && (
                <button
                  onClick={() => handleAction('escalate')}
                  disabled={!!actionLoading}
                  className="w-full btn-dark py-2.5 text-xs flex items-center justify-center gap-1.5"
                >
                  <ArrowUpCircle size={16} />
                  <span>{actionLoading === 'escalate' ? 'Forwarding...' : 'Forward to Dean (Review)'}</span>
                </button>
              )}

              {/* Non-Academic Forward to CAD */}
              {canEscalate && !isAcademic && (
                <button
                  onClick={() => handleAction('forward_cad')}
                  disabled={!!actionLoading}
                  className="w-full btn-dark py-2.5 text-xs flex items-center justify-center gap-1.5"
                >
                  <ArrowUpCircle size={16} />
                  <span>{actionLoading === 'forward_cad' ? 'Forwarding...' : 'Forward to CAD for Approval'}</span>
                </button>
              )}

              {concern.status === 'resolved' && (
                <div className="p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-800 text-xs flex items-center gap-2">
                  <CheckCircle2 size={16} className="text-emerald-600 shrink-0" />
                  <span>This concern has been officially resolved.</span>
                </div>
              )}
              {concern.status === 'escalated' && (
                <div className="p-3 bg-blue-50 border border-blue-200 rounded-lg text-blue-800 text-xs flex items-center gap-2">
                  <ArrowUpCircle size={16} className="text-blue-600 shrink-0" />
                  <span>Forwarded for higher administrative review.</span>
                </div>
              )}
            </div>
          </div>

          {/* Priority & Student Info Card */}
          <div className="card p-6">
            <h2 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4">Metadata & Diagnostic</h2>
            
            <div className="space-y-4 text-xs">
              <div>
                <p className="text-slate-400 font-semibold uppercase tracking-wider text-[10px] mb-1">Student Contact</p>
                <div className="flex items-center gap-2.5">
                  <div className="h-8 w-8 rounded-full bg-blue-100 text-[#1e3a8a] flex items-center justify-center font-bold text-xs shrink-0">
                    {getInitials(studentName)}
                  </div>
                  <div>
                    <p className="font-bold text-slate-900">{studentName}</p>
                    <p className="text-slate-500">ID: {studentId}</p>
                  </div>
                </div>
              </div>

              <div className="pt-3 border-t border-slate-100">
                <p className="text-slate-400 font-semibold uppercase tracking-wider text-[10px] mb-1">Assigned Department</p>
                <p className="font-semibold text-slate-800">{departmentName}</p>
              </div>

              <div className="pt-3 border-t border-slate-100">
                <p className="text-slate-400 font-semibold uppercase tracking-wider text-[10px] mb-1">Priority Level</p>
                <PriorityBadge priority={concern.priority} isOverridden={concern.is_priority_overridden} size="xs" />
              </div>

              {concern.ai_priority_explanation && (
                <div className="pt-3 border-t border-slate-100">
                  <p className="text-slate-400 font-semibold uppercase tracking-wider text-[10px] mb-1">AI Priority Assessment</p>
                  <div className="p-3 bg-blue-50/70 rounded-lg border border-blue-200 text-slate-800 text-xs leading-relaxed">
                    <span className="font-bold text-[#1e3a8a]">Assessment: </span>
                    {concern.ai_priority_explanation}
                  </div>
                </div>
              )}

              {concern.classification_explanation && (
                <div className="pt-3 border-t border-slate-100">
                  <p className="text-slate-400 font-semibold uppercase tracking-wider text-[10px] mb-1">Classification Rationale</p>
                  <p className="text-slate-600 italic leading-relaxed text-[11px] bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                    "{concern.classification_explanation}"
                  </p>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default DeptStaffConcernDetails;
