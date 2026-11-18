import { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { Paperclip, Send, CheckCircle2, User, Clock, ArrowLeft, X, AlertCircle, ArrowUpRight, RotateCcw, Sparkles } from 'lucide-react';
import api from '../api';
import StatusBadge from '../components/StatusBadge';
import AiCategoryBadge from '../components/AiCategoryBadge';
import AiSummaryCard from '../components/AiSummaryCard';
import ConcernAttachments from '../components/ConcernAttachments';

const StudentConcernDetails = () => {
  const { id } = useParams();
  const { user } = useAuth();
  const navigate = useNavigate();
  const [concern, setConcern] = useState(null);
  const [messages, setMessages] = useState([]);
  const [newMessage, setNewMessage] = useState('');
  const [loading, setLoading] = useState(true);
  const messagesEndRef = useRef(null);

  useEffect(() => {
    fetchData();
  }, [id]);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

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
      const concernRes = await api.get(`/concerns/${id}`);
      setConcern(concernRes.data.concern);
      setMessages(concernRes.data.concern?.messages || []);
    } catch (err) {
      console.error(err);
      setConcern(null);
    } finally {
      setLoading(false);
    }
  };

  const handleSendMessage = async (e) => {
    e.preventDefault();
    if (!newMessage.trim()) return;

    try {
      await api.post(`/concerns/${id}/messages`, { body: newMessage });
      setNewMessage('');
      fetchData();
    } catch (err) {
      console.error(err);
      alert('Failed to send message');
    }
  };

  const formatDate = (dateString, includeTime = false) => {
    if (!dateString) return '—';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '—';
    const options = { month: 'short', day: 'numeric', year: 'numeric' };
    if (includeTime) {
      options.hour = '2-digit';
      options.minute = '2-digit';
    }
    return date.toLocaleDateString('en-US', options);
  };

  if (loading) {
    return (
      <div className="p-16 text-center text-slate-500 flex flex-col items-center justify-center gap-3">
        <div className="w-8 h-8 border-3 border-blue-200 border-t-[#1e3a8a] rounded-full animate-spin"></div>
        <p className="text-xs font-semibold">Loading concern details...</p>
      </div>
    );
  }
  if (!concern) {
    return (
      <div className="text-center py-16 max-w-md mx-auto bg-white rounded-xl shadow-sm border border-slate-200 p-8 my-8">
        <AlertCircle className="mx-auto h-12 w-12 text-slate-400 mb-3" />
        <h3 className="text-base font-bold text-slate-800 mb-1">Concern Not Found</h3>
        <p className="text-slate-500 text-sm mb-6">
          This concern could not be found. It may have been removed or you may not be authorized to view it.
        </p>
        <Link
          to="/student/concerns"
          className="inline-flex items-center justify-center px-4 py-2 bg-[#1e3a8a] text-white text-sm font-medium rounded-lg hover:bg-blue-900 transition-colors"
        >
          ← Back to My Concerns
        </Link>
      </div>
    );
  }

  const departmentName = concern.department ? concern.department.name : concern.academic_unit?.name;
  const isAcademic = concern.workflow_type === 'academic' || concern.category === 'academic_units';

  // Extract routing history safely from concern
  // Laravel camelCase vs snake_case serialization guard:
  const routing = Array.isArray(concern.routing_history) 
    ? concern.routing_history 
    : (Array.isArray(concern.routingHistory) ? concern.routingHistory : []);

  // Find routing record matching specific action
  const findAction = (actionNames) => {
    const names = Array.isArray(actionNames) ? actionNames : [actionNames];
    return routing.find(r => names.includes(r.action));
  };

  /**
   * Proposed Academic Workflow:
   * 1. Student Submission
   * 2. Computer Studies Chairperson Review
   * 3. Forward to Dean
   * 4. Dean Review
   * 5. Resolution / Closure
   *
   * Only mark a stage completed when its corresponding action REALLY occurred in the database!
   */
  const buildAcademicTimeline = () => {
    const submissionRecord = findAction(['submitted_academic', 'routed_to_dept']) || { created_at: concern.submitted_at };
    const chairpersonReviewRecord = findAction(['status_changed', 'forwarded_to_dean', 'escalated_to_dean', 'resolved']);
    const forwardRecord = findAction(['forwarded_to_dean', 'escalated_to_dean']);
    const deanDecision = findAction(['dean_approved', 'approved', 'dean_returned', 'rejected', 'resolved']);
    const resolvedRecord = findAction(['resolved']) || (concern.status === 'resolved' ? { created_at: concern.resolved_at } : null);

    const academicUnitTitle = concern.academic_unit?.name || 'Academic Unit';
    // Determine Stage 2 (Chairperson Review) state
    let stage2Status = 'upcoming';
    let stage2Notes = null;
    let stage2Date = null;
    if (forwardRecord || resolvedRecord) {
      stage2Status = 'completed';
      stage2Date = chairpersonReviewRecord?.created_at || forwardRecord?.created_at;
      stage2Notes = 'Chairperson / Department review completed.';
    } else if (concern.status === 'in_progress' || chairpersonReviewRecord) {
      stage2Status = 'current';
      stage2Date = chairpersonReviewRecord?.created_at || concern.last_updated;
      stage2Notes = chairpersonReviewRecord?.notes || 'Currently under chairperson / department review.';
    } else if (concern.status === 'pending') {
      stage2Status = 'current';
      stage2Notes = `Awaiting initial review by ${academicUnitTitle} Chairperson.`;
    }

    // Determine Stage 3 (Forward to Dean) state
    let stage3Status = 'upcoming';
    let stage3Date = null;
    let stage3Notes = null;
    if (forwardRecord) {
      stage3Status = 'completed';
      stage3Date = forwardRecord.created_at;
      stage3Notes = forwardRecord.notes || 'Endorsed and forwarded to Dean for review.';
    } else if (stage2Status === 'completed' && !forwardRecord && !resolvedRecord) {
      stage3Status = 'current';
    }

    // Determine Stage 4 (Dean Review) state
    let stage4Status = 'upcoming';
    let stage4Date = null;
    let stage4Notes = null;
    if (deanDecision && (deanDecision.action === 'dean_approved' || deanDecision.action === 'approved' || deanDecision.action === 'resolved')) {
      stage4Status = 'completed';
      stage4Date = deanDecision.created_at;
      stage4Notes = deanDecision.notes || 'Dean review completed.';
    } else if (forwardRecord && concern.status === 'escalated') {
      stage4Status = 'current';
      stage4Notes = 'Under review by College Dean.';
    }

    // Determine Stage 5 (Resolution / Closure) state
    let stage5Status = 'upcoming';
    let stage5Date = null;
    let stage5Notes = null;
    if (concern.status === 'resolved') {
      stage5Status = 'completed';
      stage5Date = resolvedRecord?.created_at || concern.resolved_at;
      stage5Notes = resolvedRecord?.notes || 'Concern has been resolved.';
    } else if (stage4Status === 'completed' || concern.status === 'approved') {
      stage5Status = 'current';
      stage5Notes = 'Awaiting final administrative closure.';
    }

    return [
      {
        id: 1,
        title: 'Student Submission',
        description: 'Concern submitted by student',
        status: 'completed',
        date: submissionRecord?.created_at,
        notes: submissionRecord?.notes,
      },
      {
        id: 2,
        title: `${academicUnitTitle} Chairperson Review`,
        description: `Review by Chairperson / ${academicUnitTitle} Academic Staff`,
        status: stage2Status,
        date: stage2Date,
        notes: stage2Notes,
      },
      {
        id: 3,
        title: 'Forward to Dean',
        description: 'Escalated to College Dean for administrative oversight',
        status: stage3Status,
        date: stage3Date,
        notes: stage3Notes,
      },
      {
        id: 4,
        title: 'Dean Review',
        description: 'Dean review & administrative guidance',
        status: stage4Status,
        date: stage4Date,
        notes: stage4Notes,
      },
      {
        id: 5,
        title: 'Resolution / Closure',
        description: 'Final resolution and student notification',
        status: stage5Status,
        date: stage5Date,
        notes: stage5Notes,
      },
    ];
  };

  /**
   * Proposed Non-Academic Workflow:
   * 1. Student Submission
   * 2. Selected Department Review
   * 3. Forward to CAD for Approval
   * 4. CAD Approval / Return
   * 5. Resolution / Closure
   */
  const buildNonAcademicTimeline = () => {
    const submissionRecord = findAction(['submitted_non_academic', 'routed_to_dept']) || { created_at: concern.submitted_at };
    const deptReviewRecord = findAction(['status_changed', 'cad_document_generated', 'cad_submitted_physically', 'forwarded_to_cad', 'resolved']);
    const cadGenRecord = findAction(['cad_document_generated']);
    const cadSubmitRecord = findAction(['cad_submitted_physically', 'forwarded_to_cad']);
    const cadDecisionRecord = findAction(['cad_approved', 'cad_rejected', 'cad_returned', 'approved', 'returned']);
    const resolvedRecord = findAction(['resolved']) || (concern.status === 'resolved' ? { created_at: concern.resolved_at } : null);

    const hasCadWorkflow = Boolean(
      concern.cad_status ||
      concern.forwarded_to_cad_at ||
      cadGenRecord ||
      cadSubmitRecord ||
      cadDecisionRecord
    );

    if (!hasCadWorkflow) {
      // ── Routine 3-Stage Non-Academic Workflow ──
      const isResolved = concern.status === 'resolved';
      const isInProgress = concern.status === 'in_progress' || Boolean(deptReviewRecord);

      return [
        {
          id: 1,
          title: 'Concern Submitted',
          description: `Submitted and routed to ${departmentName || 'Department'}`,
          status: 'completed',
          date: submissionRecord?.created_at,
          notes: 'Concern successfully received by the assigned department.',
        },
        {
          id: 2,
          title: 'Under Department Review',
          description: `Direct processing by ${departmentName || 'department'} staff`,
          status: isResolved ? 'completed' : (isInProgress ? 'current' : 'current'),
          date: deptReviewRecord?.created_at || (isInProgress ? concern.last_updated : null),
          notes: isResolved
            ? 'Department review and implementation completed.'
            : (isInProgress
                ? 'Department staff is actively working on resolving this concern.'
                : `Awaiting initial review by ${departmentName || 'the assigned department'}.`),
        },
        {
          id: 3,
          title: 'Concern Resolved',
          description: 'Official resolution completed by department staff',
          status: isResolved ? 'completed' : 'upcoming',
          date: resolvedRecord?.created_at || concern.resolved_at,
          notes: isResolved ? 'This concern has been officially addressed and closed.' : null,
        },
      ];
    }

    // ── Conditional CAD Approval Workflow (6 Stages) ──
    const isStage2Completed = Boolean(cadGenRecord || cadSubmitRecord || cadDecisionRecord || concern.cad_document_generated_at);
    const isStage3Completed = Boolean(cadSubmitRecord || concern.cad_submitted_at || cadDecisionRecord || concern.cad_decision_at);
    const isStage3Current = (concern.cad_status === 'awaiting_submission' || Boolean(cadGenRecord)) && !isStage3Completed;
    const isStage4Completed = Boolean(cadDecisionRecord || concern.cad_decision || concern.cad_decision_at);
    const isStage4Current = (concern.cad_status === 'awaiting_decision' || Boolean(cadSubmitRecord)) && !isStage4Completed;
    const isStage5Completed = Boolean(cadDecisionRecord || concern.cad_decision || concern.cad_decision_at);
    const isResolved = concern.status === 'resolved';

    let stage5Title = 'Administrative Decision Received';
    let stage5Desc = 'Formal determination rendered by Campus Administrator';
    let stage5Notes = 'Administrative approval granted. Department proceeding with implementation.';
    if (concern.cad_status === 'returned' || concern.cad_decision === 'returned') {
      stage5Title = 'Returned for Department Revision';
      stage5Desc = 'Referred back to department for additional administrative details';
      stage5Notes = 'Referred back to the department for assessment revision.';
    } else if (concern.cad_status === 'rejected' || concern.cad_decision === 'rejected') {
      stage5Title = 'Administrative Determination Recorded';
      stage5Desc = 'Campus Administration has rendered a formal response';
      stage5Notes = 'Campus Administration determination recorded. Department assessing next steps.';
    }

    return [
      {
        id: 1,
        title: 'Concern Submitted',
        description: `Submitted and routed to ${departmentName || 'Department'}`,
        status: 'completed',
        date: submissionRecord?.created_at,
        notes: 'Concern successfully received by the assigned department.',
      },
      {
        id: 2,
        title: 'Department Assessment',
        description: 'Staff evaluated request and identified administrative approval requirement',
        status: isStage2Completed ? 'completed' : 'current',
        date: deptReviewRecord?.created_at,
        notes: 'Department staff determined that Campus Administrator authorization is required.',
      },
      {
        id: 3,
        title: 'Referred for Administrative Approval',
        description: 'Official CAD endorsement document prepared',
        status: isStage3Completed ? 'completed' : (isStage3Current ? 'current' : 'upcoming'),
        date: cadGenRecord?.created_at || concern.cad_document_generated_at,
        notes: 'Official approval document prepared for physical endorsement.',
      },
      {
        id: 4,
        title: 'Awaiting Administrative Decision',
        description: 'Physical paper endorsement submitted to Campus Administrator (CAD)',
        status: isStage4Completed ? 'completed' : (isStage4Current ? 'current' : 'upcoming'),
        date: cadSubmitRecord?.created_at || concern.cad_submitted_at,
        notes: 'Document physically submitted to Campus Administrator for ink endorsement.',
      },
      {
        id: 5,
        title: stage5Title,
        description: stage5Desc,
        status: isStage5Completed ? 'completed' : (isStage4Current ? 'upcoming' : 'upcoming'),
        date: cadDecisionRecord?.created_at || concern.cad_decision_at,
        notes: stage5Notes,
      },
      {
        id: 6,
        title: 'Final Resolution & Implementation',
        description: 'Official closure and departmental execution',
        status: isResolved ? 'completed' : (isStage5Completed ? 'current' : 'upcoming'),
        date: resolvedRecord?.created_at || concern.resolved_at,
        notes: isResolved ? 'Action completed and concern officially closed.' : 'Awaiting final department implementation.',
      },
    ];
  };

  const timeline = isAcademic ? buildAcademicTimeline() : buildNonAcademicTimeline();

  return (
    <div className="pb-10">
      <div className="flex items-center text-sm text-slate-500 mb-6">
        <Link to="/student/concerns" className="hover:text-slate-900">My Concerns</Link>
        <span className="mx-2">/</span>
        <span className="font-medium text-slate-700">REQ-2023-{concern.concern_id.toString().padStart(3, '0')}</span>
      </div>

      <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
        <div>
          <div className="flex items-center gap-2 mb-2 flex-wrap">
            <span className={`px-2.5 py-0.5 rounded-full text-xs font-semibold ${
              isAcademic ? 'bg-blue-50 text-blue-800 border border-blue-200' : 'bg-indigo-50 text-indigo-800 border border-indigo-200'
            }`}>
              {isAcademic ? 'Academic Concern' : 'Non-Academic Concern'}
            </span>
            {concern.issue_category && (
              <AiCategoryBadge
                category={concern.issue_category}
                subcategory={concern.issue_subcategory}
                workflowType={concern.workflow_type}
              />
            )}
          </div>
          <h1 className="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight flex flex-wrap items-center gap-3">
            <span>{concern.title}</span>
            <StatusBadge status={concern.status} />
          </h1>
        </div>
        <Link to="/student/concerns" className="btn-secondary text-xs self-start shrink-0">
          <X size={15} className="mr-1.5" /> Back to Concerns
        </Link>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left Column (Details + Messages) */}
        <div className="lg:col-span-2 space-y-6">
          
          {/* AI Issue Summary (Capped to 300 chars, clean wrapping without cut-off) */}
          {concern.ai_summary && (
            <AiSummaryCard summary={concern.ai_summary} metadata={concern.ai_metadata} />
          )}

          {/* Details Card */}
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div className="px-6 py-5 border-b border-slate-100">
              <h2 className="text-lg font-bold text-slate-900">Concern Details</h2>
            </div>
            <div className="p-6">
              <div className="grid grid-cols-2 gap-y-6 gap-x-4 mb-6">

                <div>
                  <p className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Assigned Department</p>
                  <p className="text-sm font-medium text-slate-900">{departmentName || '—'}</p>
                </div>
                <div>
                  <p className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Submitted On</p>
                  <p className="text-sm font-medium text-slate-900">{formatDate(concern.submitted_at || concern.created_at, true)}</p>
                </div>
                <div>
                  <p className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Workflow Track</p>
                  <p className="text-sm font-medium text-slate-900">
                    {isAcademic 
                      ? `Academic (${concern.academic_unit?.name || 'Academic Unit'} Chairperson → Dean)` 
                      : 'Non-Academic (Department Review → CAD Approval)'}
                  </p>
                </div>
                <div>
                  <p className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Last Update</p>
                  <p className="text-sm font-medium text-slate-900">{formatDate(concern.last_updated, true)}</p>
                </div>
              </div>

              <div>
                <p className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Description</p>
                <div className="bg-slate-50 rounded-lg p-4 text-sm text-slate-700 whitespace-pre-wrap leading-relaxed border border-slate-100">
                  {concern.description}
                </div>
              </div>

              {concern.attachments && concern.attachments.length > 0 && (
                <div className="mt-6">
                  <ConcernAttachments
                    concernId={concern.concern_id}
                    attachments={concern.attachments}
                    titleClassName="text-xs font-bold text-slate-400 uppercase tracking-wider"
                  />
                </div>
              )}
            </div>
          </div>

          {/* Messages Card */}
          <div id="messages" className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col h-[500px]">
            <div className="px-6 py-5 border-b border-slate-100">
              <h2 className="text-lg font-bold text-slate-900">Messages & Updates</h2>
            </div>
            
            <div className="flex-1 overflow-y-auto p-6 space-y-6 bg-slate-50/50">
              {messages.length === 0 ? (
                <div className="text-center text-slate-500 text-sm mt-10">No messages yet. Send a message to start the conversation.</div>
              ) : (
                messages.map((msg, idx) => {
                  const isMine = user && msg.sender_id === user.id;
                  
                  const prevMsgDate = idx > 0 ? new Date(messages[idx-1].created_at).toDateString() : null;
                  const currMsgDate = new Date(msg.created_at).toDateString();
                  const showDate = prevMsgDate !== currMsgDate;

                  return (
                    <div key={msg.id || idx} className="flex flex-col">
                      {showDate && (
                        <div className="flex justify-center mb-6 mt-2">
                          <span className="px-3 py-1 bg-slate-200/50 text-slate-500 rounded-full text-[10px] font-medium uppercase tracking-wide">
                            {formatDate(msg.created_at)}
                          </span>
                        </div>
                      )}
                      
                      <div className={`flex ${isMine ? 'justify-end' : 'justify-start'} mb-4`}>
                        {!isMine && (
                          <div className="w-8 h-8 rounded-full bg-slate-200 flex-shrink-0 flex items-center justify-center mr-3 mt-1 text-slate-500">
                            <User size={16} />
                          </div>
                        )}
                        
                        <div className={`max-w-[75%] ${isMine ? 'items-end' : 'items-start'} flex flex-col`}>
                          {!isMine && (
                            <span className="text-xs text-slate-500 mb-1 ml-1 font-medium">{msg.sender?.name || 'Staff'} ({msg.sender?.role || 'Staff'})</span>
                          )}
                          <div 
                            className={`p-3.5 rounded-2xl text-sm leading-relaxed ${
                              isMine 
                                ? 'bg-blue-600 text-white rounded-tr-sm' 
                                : 'bg-white border border-slate-200 text-slate-800 rounded-tl-sm shadow-2xs'
                            }`}
                          >
                            {msg.body}
                          </div>
                          <span className="text-[10px] text-slate-400 mt-1 mx-1">
                            {new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                          </span>
                        </div>

                        {isMine && (
                          <div className="w-8 h-8 rounded-full bg-blue-100 flex-shrink-0 flex items-center justify-center ml-3 mt-1 text-blue-700 font-bold text-xs">
                            {user?.name?.charAt(0) || 'U'}
                          </div>
                        )}
                      </div>
                    </div>
                  );
                })
              )}
              <div ref={messagesEndRef} />
            </div>
            
            <div className="p-4 bg-white border-t border-slate-200">
              <form onSubmit={handleSendMessage} className="flex items-end gap-3">
                <div className="flex-1 bg-slate-50 border border-slate-200 rounded-2xl overflow-hidden focus-within:ring-1 focus-within:ring-[#1e3a8a] focus-within:border-[#1e3a8a] transition-all">
                  <textarea
                    rows="1"
                    className="w-full bg-transparent px-4 py-3 outline-none text-sm resize-none"
                    placeholder="Type a message or inquiry..."
                    value={newMessage}
                    onChange={(e) => setNewMessage(e.target.value)}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        handleSendMessage(e);
                      }
                    }}
                  />
                </div>
                <button 
                  type="submit" 
                  disabled={!newMessage.trim()}
                  className="p-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 disabled:opacity-50 disabled:hover:bg-blue-600 transition-colors shadow-2xs flex-shrink-0"
                >
                  <Send size={18} className="ml-0.5" />
                </button>
              </form>
            </div>
          </div>
        </div>

        {/* Right Column: Estimated Timeframe + Status Timeline */}
        <div className="space-y-6">
          {concern.prediction && concern.prediction.enabled !== false && (
            <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
              <div className="flex items-center justify-between gap-2 mb-3">
                <div className="flex items-center gap-2">
                  <div className="w-8 h-8 rounded-lg bg-blue-50 text-blue-700 flex items-center justify-center">
                    <Clock size={16} />
                  </div>
                  <div>
                    <h2 className="text-sm font-bold text-slate-900">Estimated Resolution Timeframe</h2>
                    <p className="text-[11px] text-slate-500">Historical resolution pattern estimate</p>
                  </div>
                </div>
                <span className={`px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border ${
                  concern.prediction.confidence_level === 'high'
                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                    : concern.prediction.confidence_level === 'moderate'
                    ? 'bg-blue-50 text-blue-700 border-blue-200'
                    : concern.prediction.confidence_level === 'low'
                    ? 'bg-amber-50 text-amber-800 border-amber-200'
                    : 'bg-slate-100 text-slate-600 border-slate-200'
                }`}>
                  {concern.prediction.confidence_level === 'insufficient_data'
                    ? 'Insufficient Data'
                    : `${concern.prediction.confidence_level} Confidence`}
                </span>
              </div>

              {concern.prediction.available ? (
                <div className="space-y-3">
                  <div className="p-3.5 rounded-xl bg-blue-50/50 border border-blue-100">
                    <p className="text-[10px] font-bold text-blue-700 uppercase tracking-wider mb-0.5">
                      {concern.status === 'resolved' ? 'Predicted Historical Duration' : 'Estimated Duration'}
                    </p>
                    <p className="text-base font-bold text-slate-900">
                      {concern.prediction.estimated_label || `${concern.prediction.estimated_hours}h`}
                    </p>
                    {concern.prediction.estimated_resolved_at && concern.status !== 'resolved' && (
                      <p className="text-xs text-slate-600 mt-1">
                        Estimated around: <span className="font-semibold text-slate-800">{formatDate(concern.prediction.estimated_resolved_at, true)}</span>
                      </p>
                    )}
                    {concern.status === 'resolved' && concern.resolved_at && (
                      <p className="text-xs text-emerald-700 font-medium mt-1">
                        Resolved on: {formatDate(concern.resolved_at, true)}
                      </p>
                    )}
                  </div>
                  <p className="text-[11px] text-slate-500 leading-relaxed">
                    Based on {concern.prediction.sample_size} previously resolved concern(s) with a similar category and priority. {concern.prediction.disclaimer}
                  </p>
                </div>
              ) : (
                <div className="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600">
                  <p className="font-semibold text-slate-800 mb-0.5">Estimate Unavailable</p>
                  <p className="text-[11px] text-slate-500 leading-relaxed">
                    {concern.prediction.disclaimer || 'There are not yet enough similar resolved concerns to provide a reliable timeframe estimate.'}
                  </p>
                </div>
              )}
            </div>
          )}

          <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 sticky top-24">
            <div className="flex items-center justify-between mb-2">
              <h2 className="text-lg font-bold text-slate-900">Workflow Timeline</h2>
            </div>
            <p className="text-xs text-slate-500 mb-6">
              Track actual progress through the {isAcademic ? 'Academic' : 'Non-Academic'} workflow.
            </p>
            
            <div className="relative pl-6 space-y-8 before:absolute before:left-2.5 before:top-3 before:bottom-3 before:w-0.5 before:bg-slate-200">
              {timeline.map((stage) => {
                const isCompleted = stage.status === 'completed';
                const isCurrent = stage.status === 'current';
                const isUpcoming = stage.status === 'upcoming';

                return (
                  <div key={stage.id} className="relative group">
                    {/* Bullet marker */}
                    <div className={`absolute -left-6 top-0.5 w-5 h-5 rounded-full flex items-center justify-center border-2 bg-white transition-all ${
                      isCompleted 
                        ? 'border-emerald-600 bg-emerald-600 text-white' 
                        : isCurrent 
                          ? 'border-blue-600 ring-4 ring-blue-100 text-blue-600' 
                          : 'border-slate-300 text-slate-300'
                    }`}>
                      {isCompleted ? (
                        <CheckCircle2 size={13} className="text-white" />
                      ) : isCurrent ? (
                        <div className="w-2 h-2 rounded-full bg-blue-600 animate-pulse" />
                      ) : (
                        <div className="w-1.5 h-1.5 rounded-full bg-slate-300" />
                      )}
                    </div>

                    {/* Stage details */}
                    <div>
                      <div className="flex items-center justify-between">
                        <span className={`text-sm font-bold ${
                          isCompleted ? 'text-slate-900' : isCurrent ? 'text-blue-700' : 'text-slate-400'
                        }`}>
                          {stage.title}
                        </span>
                        {isCurrent && (
                          <span className="text-[10px] font-bold uppercase tracking-wider bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full border border-blue-200">
                            Current Stage
                          </span>
                        )}
                      </div>

                      <p className={`text-xs mt-0.5 ${isUpcoming ? 'text-slate-400' : 'text-slate-600'}`}>
                        {stage.description}
                      </p>

                      {/* Real timestamp if available */}
                      {stage.date && (
                        <div className="text-[11px] text-slate-500 font-medium mt-1 flex items-center gap-1">
                          <Clock size={11} className="text-slate-400" />
                          {formatDate(stage.date, true)}
                        </div>
                      )}

                      {/* Action notes from real routing records */}
                      {stage.notes && (
                        <div className="mt-2 text-xs p-2.5 rounded-lg bg-slate-50 border border-slate-200 text-slate-700 italic">
                          "{stage.notes}"
                        </div>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>

            <div className="mt-8 pt-4 border-t border-slate-100 text-[11px] text-slate-500 leading-relaxed">
              * Note: Stages represent the proposed institutional workflow and are updated in real-time as personnel review your submission.
            </div>
          </div>
        </div>

      </div>
    </div>
  );
};

export default StudentConcernDetails;
