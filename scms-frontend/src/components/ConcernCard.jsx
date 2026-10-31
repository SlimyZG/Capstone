import { useState, useEffect } from 'react';
import StatusBadge from './StatusBadge';
import PriorityBadge from './PriorityBadge';
import SentimentBadge from './SentimentBadge';
import AiCategoryBadge from './AiCategoryBadge';
import { Paperclip, MessageSquare, ArrowRightLeft, ArrowUpRight, CheckCircle2, RotateCcw } from 'lucide-react';

const ConcernCard = ({ concern, onAction, role, onViewMessages }) => {
  const [notes, setNotes] = useState('');
  const [selectedStatus, setSelectedStatus] = useState(concern.status);

  useEffect(() => {
    setSelectedStatus(concern.status);
  }, [concern.status]);

  const renderActions = () => {
    if (role === 'dept_staff') {
      if (concern.status === 'approved') {
        return (
          <div className="mt-4 flex flex-wrap items-center gap-2">
            <button onClick={() => onAction('resolve', concern.concern_id, notes)} className="btn-success text-xs px-3 py-1.5">
              Resolve
            </button>
            <input 
              type="text" 
              placeholder="Optional notes..." 
              value={notes} 
              onChange={(e) => setNotes(e.target.value)} 
              className="input-field text-xs w-auto py-1.5 flex-1 min-w-[140px]"
            />
          </div>
        );
      }
      if (['pending', 'in_progress', 'resolved'].includes(concern.status)) {
        return (
          <div className="mt-4 flex flex-wrap items-center gap-2">
            <select 
              value={selectedStatus} 
              onChange={(e) => setSelectedStatus(e.target.value)}
              className="input-field text-xs w-auto py-1.5"
            >
              <option value="pending">Pending</option>
              <option value="in_progress">Investigating</option>
              <option value="resolved">Resolved</option>
            </select>
            <button onClick={() => onAction('status', concern.concern_id, notes, selectedStatus)} className="btn-primary py-1.5 px-3 text-xs">
              Update Status
            </button>
            {concern.status !== 'resolved' && (
              <>
                <button onClick={() => onAction('escalate', concern.concern_id, notes)} className="btn-danger py-1.5 px-3 text-xs">
                  Escalate to Dean
                </button>
                <button onClick={() => onAction('open_transfer', concern.concern_id)} className="btn-secondary py-1.5 px-3 text-xs">
                  Transfer
                </button>
              </>
            )}
            <input 
              type="text" 
              placeholder="Optional notes..." 
              value={notes} 
              onChange={(e) => setNotes(e.target.value)} 
              className="input-field text-xs w-auto py-1.5 flex-1 min-w-[140px]"
            />
          </div>
        );
      }
    }

    if (role === 'dean') {
      if (concern.status === 'escalated') {
        return (
          <div className="mt-4 flex flex-wrap items-center gap-2">
            <button onClick={() => onAction('approve', concern.concern_id, notes)} className="btn-success text-xs px-3 py-1.5">
              Approve
            </button>
            <button onClick={() => onAction('reject', concern.concern_id, notes)} className="btn-danger text-xs px-3 py-1.5">
              Reject
            </button>
            <input 
              type="text" 
              placeholder="Optional notes..." 
              value={notes} 
              onChange={(e) => setNotes(e.target.value)} 
              className="input-field text-xs w-auto py-1.5 flex-1 min-w-[140px]"
            />
          </div>
        );
      }
      if (concern.target_type === 'dean' && ['pending', 'in_progress'].includes(concern.status)) {
        return (
          <div className="mt-4 flex flex-wrap items-center gap-2">
            <button onClick={() => onAction('resolve', concern.concern_id, notes)} className="btn-success text-xs px-3 py-1.5">
              Resolve
            </button>
            <input 
              type="text" 
              placeholder="Optional notes..." 
              value={notes} 
              onChange={(e) => setNotes(e.target.value)} 
              className="input-field text-xs w-auto py-1.5 flex-1 min-w-[140px]"
            />
          </div>
        );
      }
    }

    if (role === 'student') {
      if (['pending', 'in_progress', 'escalated'].includes(concern.status)) {
        return (
          <div className="mt-4 flex flex-wrap items-center gap-2">
            {concern.status === 'pending' && (
              <button onClick={() => onAction('open_edit', concern.concern_id)} className="btn-secondary text-xs px-3 py-1.5">
                Edit
              </button>
            )}
            <button onClick={() => onAction('withdraw', concern.concern_id)} className="btn-danger text-xs px-3 py-1.5">
              Withdraw
            </button>
          </div>
        );
      }
      if (concern.status === 'withdrawn') {
        return (
          <div className="mt-4 flex items-center gap-2">
            <button onClick={() => onAction('reopen', concern.concern_id)} className="btn-success text-xs px-3 py-1.5">
              Reopen Concern
            </button>
          </div>
        );
      }
    }

    return null;
  };

  return (
    <div className="card p-5 sm:p-6 mb-4 hover:border-slate-300 transition-all">
      <div className="flex flex-col sm:flex-row justify-between sm:items-start gap-2 mb-3">
        <div>
          <div className="flex items-center gap-2 mb-1.5 flex-wrap">
            <span className="text-xs font-mono font-bold text-slate-500">
              #CON-{concern.concern_id.toString().padStart(4, '0')}
            </span>
            <AiCategoryBadge category={concern.issue_category} subcategory={concern.issue_subcategory} size="xs" />
          </div>
          <h3 className="text-base sm:text-lg font-bold text-slate-900 leading-snug">{concern.title}</h3>
          <p className="text-xs text-slate-500 mt-1">
            Submitted by: <span className="font-semibold text-slate-700">{concern.student?.name || 'Unknown'}</span> 
            {concern.is_anonymous && <span className="text-slate-400 ml-1.5">(Anonymous)</span>}
          </p>
        </div>
        <div className="shrink-0 flex items-center gap-2">
          <StatusBadge status={concern.status} />
        </div>
      </div>

      {concern.transferred_from && (
        <div className="mb-3">
          <span className="inline-flex items-center gap-1 bg-blue-50 text-blue-700 text-xs px-2.5 py-0.5 rounded-md font-semibold border border-blue-200">
            <ArrowRightLeft size={12} />
            Transferred from {concern.transferred_from}
          </span>
        </div>
      )}
      
      <div className="bg-slate-50 p-4 rounded-lg mb-4 text-sm text-slate-700 border border-slate-100 whitespace-pre-wrap leading-relaxed break-words">
        {concern.description}
      </div>

      {concern.attachments && concern.attachments.length > 0 && (
        <div className="mb-4">
          <h4 className="text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Attachments:</h4>
          <div className="flex flex-wrap gap-2.5">
            {concern.attachments.map((path, idx) => {
              const isImage = path.match(/\.(jpeg|jpg|gif|png)$/i);
              const fullUrl = `http://localhost:8000${path}`;
              return isImage ? (
                <a key={idx} href={fullUrl} target="_blank" rel="noopener noreferrer" className="block border border-slate-200 rounded-lg overflow-hidden hover:border-blue-400 transition-colors">
                  <img src={fullUrl} alt={`Attachment ${idx + 1}`} className="h-16 w-auto object-cover" />
                </a>
              ) : (
                <a key={idx} href={fullUrl} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1.5 px-3 py-1.5 border border-slate-200 rounded-lg text-xs font-semibold text-blue-600 hover:bg-blue-50 transition-colors">
                  <Paperclip size={13} />
                  <span>Document {idx + 1}</span>
                </a>
              );
            })}
          </div>
        </div>
      )}

      {/* AI Assessment info for staff & authorized views */}
      {concern.ai_status === 'complete' && (
        <div className="mb-4 p-3.5 rounded-lg border bg-blue-50/60 border-blue-100 space-y-2">
          <div className="flex flex-wrap items-center gap-2">
            <PriorityBadge priority={concern.priority} isOverridden={concern.is_priority_overridden} size="xs" />
            {concern.sentiment_label && (
              <SentimentBadge label={concern.sentiment_label} score={concern.sentiment_score} size="xs" />
            )}
          </div>
          {concern.ai_summary && (
            <p className="text-xs text-slate-700 leading-relaxed break-words">
              <strong className="text-[#1e3a8a]">AI Summary:</strong> {concern.ai_summary}
            </p>
          )}
        </div>
      )}

      {concern.status === 'resolved' && concern.resolution_summary && (
        <div className="mb-4 p-3.5 rounded-lg border bg-emerald-50 border-emerald-200">
          <h4 className="text-xs font-bold text-emerald-900 mb-1">Resolution Summary</h4>
          <p className="text-xs text-emerald-800 leading-relaxed break-words">{concern.resolution_summary}</p>
        </div>
      )}

      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs text-slate-500 mb-4 pt-2 border-t border-slate-100">
        <div>
          <span className="block font-semibold text-slate-600 uppercase tracking-wider text-[10px]">Category</span>
          <span className="capitalize text-slate-800">{concern.category?.replace('_', ' ')}</span>
        </div>
        <div>
          <span className="block font-semibold text-slate-600 uppercase tracking-wider text-[10px]">Workflow Track</span>
          <span className="capitalize text-slate-800">{concern.workflow_type || concern.target_type}</span>
        </div>
        <div>
          <span className="block font-semibold text-slate-600 uppercase tracking-wider text-[10px]">Assigned Target</span>
          <span className="text-slate-800 font-medium">
            {concern.category === 'academic_units' ? (concern.academic_unit?.name || 'N/A') : (concern.department?.name || 'N/A')}
          </span>
        </div>
        <div>
          <span className="block font-semibold text-slate-600 uppercase tracking-wider text-[10px]">Last Updated</span>
          <span className="text-slate-800">
            {new Date(concern.last_updated || concern.submitted_at || Date.now()).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
          </span>
        </div>
      </div>

      <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center border-t border-slate-100 pt-3 gap-2">
        <button onClick={() => onViewMessages(concern.concern_id)} className="inline-flex items-center gap-1.5 text-blue-600 hover:text-blue-800 text-xs font-semibold">
          <MessageSquare size={14} />
          <span>Messages & Activity Trail</span>
        </button>
        {renderActions()}
      </div>
    </div>
  );
};

export default ConcernCard;
