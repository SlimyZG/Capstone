import { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { Paperclip, Send, CheckCircle2, ArrowUpCircle, ArrowRightLeft, ChevronLeft, Sparkles, User } from 'lucide-react';
import api from '../api';

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

  const fetchData = async () => {
    try {
      const msgRes = await api.get(`/dept-staff/concerns/${id}/messages`);
      // Get concern details from queue list — no single concern endpoint for staff, so filter from queue
      const queueRes = await api.get('/dept-staff/queue');
      const found = queueRes.data.concerns.find(c => c.concern_id.toString() === id.toString());
      setConcern(found || null);
      setMessages(msgRes.data.messages || []);
    } catch (err) {
      console.error(err);
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
        if (!window.confirm('Escalate this concern to the Dean?')) { setActionLoading(''); return; }
        await api.post(`/dept-staff/concerns/${id}/escalate`);
      }
      fetchData();
    } catch (err) {
      alert(err.response?.data?.message || 'Action failed.');
    } finally {
      setActionLoading('');
    }
  };

  const getPriorityBadge = (priority) => {
    switch ((priority || '').toLowerCase()) {
      case 'high': return <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-bold border border-red-200"><span className="w-2 h-2 rounded-full bg-red-500"></span>High Priority</span>;
      case 'medium': return <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold border border-amber-200"><span className="w-2 h-2 rounded-full bg-amber-500"></span>Medium Priority</span>;
      default: return <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-bold">Low Priority</span>;
    }
  };

  const getStatusBadge = (status) => {
    switch (status) {
      case 'pending': return <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-50 text-amber-600 rounded-full text-xs font-bold border border-amber-200">⏱ Pending</span>;
      case 'in_progress': return <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50 text-blue-600 rounded-full text-xs font-bold border border-blue-200">🔄 In Progress</span>;
      case 'resolved': return <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-700 rounded-full text-xs font-bold border border-emerald-200">✅ Resolved</span>;
      case 'escalated': return <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-purple-50 text-purple-600 rounded-full text-xs font-bold border border-purple-200">⬆ Escalated</span>;
      default: return <span className="px-3 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-bold">{status}</span>;
    }
  };

  const formatDate = (d) => {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  };

  const getInitials = (name) => {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
  };

  if (loading) return <div className="text-slate-500 p-6">Loading concern details...</div>;
  if (!concern) return (
    <div className="text-center py-16">
      <p className="text-slate-500 text-sm mb-4">Concern not found or not in your queue.</p>
      <Link to="/dept-staff/concerns" className="text-[#1e3a8a] text-sm font-bold">← Back to Concerns</Link>
    </div>
  );

  const studentName = concern.student?.name || 'Unknown Student';
  const studentId = concern.student?.student_id_number || '—';
  const departmentName = concern.department?.name || concern.academic_unit?.name || '—';
  const canResolve = ['pending', 'in_progress', 'approved'].includes(concern.status);
  const canMarkInProgress = concern.status === 'pending';
  const canEscalate = ['pending', 'in_progress'].includes(concern.status);

  return (
    <div className="pb-12">
      {/* Breadcrumb */}
      <div className="flex items-center text-sm text-slate-500 mb-6">
        <Link to="/dept-staff/concerns" className="hover:text-slate-900 flex items-center gap-1">
          <ChevronLeft size={16} /> Concerns
        </Link>
        <span className="mx-2">›</span>
        <span className="font-medium text-slate-700">Ticket #REQ-{concern.concern_id.toString().padStart(4, '0')}</span>
      </div>

      {/* Header */}
      <div className="mb-6">
        <div className="flex flex-wrap gap-2 mb-3">
          {getPriorityBadge(concern.priority)}
          <span className="px-3 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-bold">ID: #REQ-{concern.concern_id.toString().padStart(4, '0')}</span>
          {getStatusBadge(concern.status)}
        </div>
        <h1 className="text-3xl font-bold text-slate-900 tracking-tight">{concern.title}</h1>
        <p className="text-slate-500 text-sm mt-2">
          Submitted by <span className="font-semibold text-slate-700">{studentName} (Student ID: {studentId})</span> • {formatDate(concern.submitted_at)}
        </p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left Column */}
        <div className="lg:col-span-2 space-y-6">

          {/* AI Summary */}
          {concern.ai_summary && (
            <div className="bg-blue-50 border border-blue-200 rounded-2xl p-5">
              <div className="flex items-center gap-2 mb-3">
                <div className="h-7 w-7 rounded-lg bg-blue-600 flex items-center justify-center flex-shrink-0">
                  <Sparkles size={14} className="text-white" />
                </div>
                <span className="text-sm font-bold text-blue-800">AI Issue Summary</span>
              </div>
              <p className="text-sm text-blue-900 leading-relaxed">{concern.ai_summary}</p>
            </div>
          )}

          {/* Original Request */}
          <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div className="px-6 py-5 border-b border-slate-100 flex items-center gap-2">
              <div className="text-slate-500">📄</div>
              <h2 className="text-base font-bold text-slate-900">Original Request Details</h2>
            </div>
            <div className="p-6">
              <div className="text-sm text-slate-700 whitespace-pre-wrap leading-relaxed bg-slate-50 rounded-xl p-4 border border-slate-100 mb-4">
                {concern.description}
              </div>
              {concern.attachments && concern.attachments.length > 0 && (
                <div>
                  <p className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Attachments ({concern.attachments.length})</p>
                  <div className="flex flex-wrap gap-2">
                    {concern.attachments.map((att, i) => (
                      <a
                        key={i}
                        href={`http://localhost:8000/storage/${att.file_path || att}`}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-2 px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs text-slate-700 font-medium hover:bg-slate-50 transition-colors shadow-sm"
                      >
                        <Paperclip size={12} className="text-slate-400" />
                        {att.file_name || `Attachment ${i + 1}`}
                      </a>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Communication Thread */}
          <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col h-[500px]">
            <div className="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
              <div className="flex items-center gap-2">
                <span className="text-slate-500">💬</span>
                <h2 className="text-base font-bold text-slate-900">Communication Thread</h2>
              </div>
              <span className="text-xs text-slate-400 font-medium">Student {concern.status === 'resolved' ? 'offline' : 'online'}</span>
            </div>

            <div className="flex-1 overflow-y-auto p-5 space-y-4 bg-slate-50/50">
              {messages.length === 0 ? (
                <div className="text-center text-slate-400 text-sm mt-10">No messages yet. Start the conversation.</div>
              ) : (
                messages.map((msg, idx) => {
                  const isMine = msg.sender_id === user.id;
                  const prevDate = idx > 0 ? new Date(messages[idx - 1].created_at).toDateString() : null;
                  const currDate = new Date(msg.created_at).toDateString();
                  const showDate = prevDate !== currDate;

                  return (
                    <div key={msg.id}>
                      {showDate && (
                        <div className="flex justify-center my-4">
                          <span className="px-3 py-1 bg-slate-200/50 text-slate-500 rounded-full text-[10px] font-medium uppercase tracking-wide">
                            {new Date(msg.created_at).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}
                          </span>
                        </div>
                      )}
                      {msg.is_internal && (
                        <div className="flex justify-center my-2">
                          <span className="px-3 py-1 bg-slate-100 text-slate-400 rounded-full text-[10px] font-semibold uppercase tracking-widest">
                            {msg.body}
                          </span>
                        </div>
                      )}
                      {!msg.is_internal && (
                        <div className={`flex ${isMine ? 'justify-end' : 'justify-start'} mb-3`}>
                          {!isMine && (
                            <div className="h-8 w-8 rounded-full bg-amber-100 text-amber-700 flex-shrink-0 flex items-center justify-center text-xs font-bold mr-3 mt-1">
                              {getInitials(msg.sender?.name || studentName)}
                            </div>
                          )}
                          <div className={`max-w-[72%] flex flex-col ${isMine ? 'items-end' : 'items-start'}`}>
                            {!isMine && (
                              <span className="text-xs text-slate-500 mb-1 ml-1 font-medium">
                                {msg.sender?.name || studentName} {new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                              </span>
                            )}
                            <div className={`p-3.5 rounded-2xl text-sm leading-relaxed ${
                              isMine
                                ? 'bg-[#1e3a8a] text-white rounded-tr-sm'
                                : 'bg-white border border-slate-200 text-slate-800 rounded-tl-sm shadow-sm'
                            }`}>
                              {msg.body}
                            </div>
                            {isMine && (
                              <span className="text-[10px] text-slate-400 mt-1 mr-1">
                                {new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                              </span>
                            )}
                          </div>
                          {isMine && (
                            <div className="h-8 w-8 rounded-full bg-blue-100 text-blue-700 flex-shrink-0 flex items-center justify-center text-xs font-bold ml-3 mt-1">
                              {getInitials(user?.name)}
                            </div>
                          )}
                        </div>
                      )}
                    </div>
                  );
                })
              )}
              <div ref={messagesEndRef} />
            </div>

            <div className="p-4 bg-white border-t border-slate-200">
              <form onSubmit={handleSendMessage} className="flex items-end gap-3">
                <button type="button" className="p-2 text-slate-400 hover:text-slate-600 rounded-full transition-colors flex-shrink-0">
                  <Paperclip size={20} />
                </button>
                <div className="flex-1 bg-slate-50 border border-slate-200 rounded-2xl focus-within:ring-2 focus-within:ring-blue-500/20 focus-within:border-blue-500 transition-all overflow-hidden">
                  <textarea
                    rows="1"
                    className="w-full bg-transparent px-4 py-3 outline-none text-sm resize-none text-slate-900 placeholder:text-slate-400"
                    placeholder={`Type your message to ${studentName.split(' ')[0]}...`}
                    value={newMessage}
                    onChange={e => setNewMessage(e.target.value)}
                    onKeyDown={e => {
                      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSendMessage(e); }
                    }}
                  />
                </div>
                <button
                  type="submit"
                  disabled={!newMessage.trim()}
                  className="h-10 px-4 bg-[#1e3a8a] text-white rounded-full hover:bg-blue-900 disabled:opacity-50 transition-colors shadow-sm flex-shrink-0 flex items-center gap-1.5 text-sm font-semibold"
                >
                  <Send size={15} /> Send
                </button>
              </form>
            </div>
          </div>
        </div>

        {/* Right Column */}
        <div className="space-y-6">
          {/* Resolution Actions */}
          <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 className="text-base font-bold text-slate-900 mb-4">Resolution Actions</h2>
            <div className="space-y-3">
              {canResolve && (
                <button
                  onClick={() => handleAction('resolve')}
                  disabled={!!actionLoading}
                  className="w-full flex items-center justify-center gap-2 py-2.5 px-4 bg-[#1e3a8a] hover:bg-[#152960] text-white text-sm font-bold rounded-xl transition-colors shadow-sm disabled:opacity-60"
                >
                  <CheckCircle2 size={16} />
                  {actionLoading === 'resolve' ? 'Resolving...' : 'Resolve Request'}
                </button>
              )}
              {canMarkInProgress && (
                <button
                  onClick={() => handleAction('in_progress')}
                  disabled={!!actionLoading}
                  className="w-full flex items-center justify-center gap-2 py-2.5 px-4 bg-white border-2 border-slate-200 hover:border-blue-300 hover:bg-blue-50 text-slate-700 text-sm font-bold rounded-xl transition-colors shadow-sm disabled:opacity-60"
                >
                  <ArrowUpCircle size={16} />
                  {actionLoading === 'in_progress' ? 'Updating...' : 'Mark In Progress'}
                </button>
              )}
              {canEscalate && (
                <button
                  onClick={() => handleAction('escalate')}
                  disabled={!!actionLoading}
                  className="w-full flex items-center justify-center gap-2 py-2.5 px-4 bg-white border-2 border-slate-200 hover:border-purple-300 hover:bg-purple-50 text-slate-700 text-sm font-bold rounded-xl transition-colors shadow-sm disabled:opacity-60"
                >
                  <ArrowRightLeft size={16} />
                  {actionLoading === 'escalate' ? 'Escalating...' : 'Escalate to Dean'}
                </button>
              )}
              {concern.status === 'resolved' && (
                <div className="text-center py-4 text-slate-400 text-sm flex flex-col items-center gap-2">
                  <CheckCircle2 size={24} className="text-emerald-400" />
                  This concern has been resolved.
                </div>
              )}
              {concern.status === 'escalated' && (
                <div className="text-center py-4 text-slate-400 text-sm flex flex-col items-center gap-2">
                  <ArrowUpCircle size={24} className="text-purple-400" />
                  This concern has been escalated to the Dean.
                </div>
              )}
            </div>
          </div>

          {/* Student Information */}
          <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Student Information</h2>
            <div className="flex items-center gap-3 mb-5">
              <div className="h-10 w-10 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center text-sm font-bold flex-shrink-0">
                {getInitials(studentName)}
              </div>
              <div>
                <p className="font-bold text-slate-900 text-sm">{studentName}</p>
                <p className="text-xs text-slate-500">{studentId}@university.edu</p>
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4 text-xs">
              <div>
                <p className="text-slate-400 font-semibold uppercase tracking-wider mb-0.5">Department</p>
                <p className="text-slate-800 font-semibold">{departmentName}</p>
              </div>
              <div>
                <p className="text-slate-400 font-semibold uppercase tracking-wider mb-0.5">Student ID</p>
                <p className="text-slate-800 font-semibold">{studentId}</p>
              </div>
              <div>
                <p className="text-slate-400 font-semibold uppercase tracking-wider mb-0.5">Category</p>
                <p className="text-slate-800 font-semibold">{concern.category === 'academic_units' ? 'Academic' : 'Operational'}</p>
              </div>
              <div>
                <p className="text-slate-400 font-semibold uppercase tracking-wider mb-0.5">Priority</p>
                <p className={`font-bold ${(concern.priority || '').toLowerCase() === 'high' ? 'text-red-600' : (concern.priority || '').toLowerCase() === 'medium' ? 'text-amber-600' : 'text-slate-600'}`}>
                  {concern.priority ? concern.priority.charAt(0).toUpperCase() + concern.priority.slice(1) : '—'}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default DeptStaffConcernDetails;
