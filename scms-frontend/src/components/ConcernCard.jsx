import { useState, useEffect } from 'react';
import StatusBadge from './StatusBadge';

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
          <div className="mt-4 flex space-x-2">
            <button onClick={() => onAction('resolve', concern.concern_id, notes)} className="btn-success text-xs px-3 py-1">Resolve</button>
            <input 
              type="text" 
              placeholder="Optional notes..." 
              value={notes} 
              onChange={(e) => setNotes(e.target.value)} 
              className="input-field text-xs w-auto py-1"
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
              className="input-field text-xs w-auto py-1"
            >
              <option value="pending">Pending</option>
              <option value="in_progress">Investigating</option>
              <option value="resolved">Resolved</option>
            </select>
            <button onClick={() => onAction('status', concern.concern_id, notes, selectedStatus)} className="btn-primary py-1 px-3 text-xs">Update Status</button>
            {concern.status !== 'resolved' && (
              <>
                <button onClick={() => onAction('escalate', concern.concern_id, notes)} className="btn-danger py-1 px-3 text-xs">Escalate to Dean</button>
                <button onClick={() => onAction('open_transfer', concern.concern_id)} className="bg-amber-100 hover:bg-amber-200 text-amber-800 font-semibold rounded-lg shadow-sm border border-amber-200 transition-all focus:ring-2 focus:ring-amber-500/20 outline-none py-1 px-3 text-xs">Transfer</button>
              </>
            )}
            <input 
              type="text" 
              placeholder="Optional notes..." 
              value={notes} 
              onChange={(e) => setNotes(e.target.value)} 
              className="input-field text-xs w-auto py-1 flex-grow"
            />
          </div>
        );
      }
    }

    if (role === 'dean') {
      if (concern.status === 'escalated') {
        return (
          <div className="mt-4 flex space-x-2">
            <button onClick={() => onAction('approve', concern.concern_id, notes)} className="btn-success text-xs">Approve</button>
            <button onClick={() => onAction('reject', concern.concern_id, notes)} className="btn-danger text-xs">Reject</button>
            <input 
              type="text" 
              placeholder="Optional notes..." 
              value={notes} 
              onChange={(e) => setNotes(e.target.value)} 
              className="input-field text-xs w-auto py-1"
            />
          </div>
        );
      }
      if (concern.target_type === 'dean' && ['pending', 'in_progress'].includes(concern.status)) {
        return (
          <div className="mt-4 flex space-x-2">
            <button onClick={() => onAction('resolve', concern.concern_id, notes)} className="btn-success text-xs">Resolve</button>
            <input 
              type="text" 
              placeholder="Optional notes..." 
              value={notes} 
              onChange={(e) => setNotes(e.target.value)} 
              className="input-field text-xs w-auto py-1"
            />
          </div>
        );
      }
    }

    if (role === 'student') {
      if (['pending', 'in_progress', 'escalated'].includes(concern.status)) {
        return (
          <div className="mt-4 flex space-x-2">
            {concern.status === 'pending' && (
              <button onClick={() => onAction('open_edit', concern.concern_id)} className="bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg shadow-sm border border-slate-200 transition-all focus:ring-2 focus:ring-slate-500/20 outline-none py-1 px-3 text-xs">Edit</button>
            )}
            <button onClick={() => onAction('withdraw', concern.concern_id)} className="btn-danger text-xs px-3 py-1">Withdraw</button>
          </div>
        );
      }
      if (concern.status === 'withdrawn') {
        return (
          <div className="mt-4 flex space-x-2">
            <button onClick={() => onAction('reopen', concern.concern_id)} className="btn-success text-xs px-3 py-1">Reopen Concern</button>
          </div>
        );
      }
    }

    return null;
  };

  return (
    <div className="card p-6 mb-4 hover:shadow-md transition-shadow">
      <div className="flex justify-between items-start mb-4">
        <div>
          <h3 className="text-lg font-bold text-slate-900">{concern.title}</h3>
          <p className="text-sm text-slate-500 mt-1">
            Submitted by: <span className="font-medium text-slate-700">{concern.student?.name || 'Unknown'}</span> 
            {concern.is_anonymous && <span className="text-xs text-slate-400 ml-2">(Anonymous)</span>}
          </p>
        </div>
        <StatusBadge status={concern.status} />
      </div>

      {concern.transferred_from && (
        <div className="mb-3">
          <span className="bg-purple-100 text-purple-800 text-xs px-2.5 py-1 rounded-md font-medium border border-purple-200">
            Transferred from {concern.transferred_from}
          </span>
        </div>
      )}
      
      <div className="bg-slate-50 p-4 rounded-lg mb-4 text-sm text-slate-700 border border-slate-100 whitespace-pre-wrap">
        {concern.description}
      </div>

      {concern.attachments && concern.attachments.length > 0 && (
        <div className="mb-4">
          <h4 className="text-sm font-semibold text-slate-700 mb-2">Attachments:</h4>
          <div className="flex flex-wrap gap-3">
            {concern.attachments.map((path, idx) => {
              const isImage = path.match(/\.(jpeg|jpg|gif|png)$/i);
              const fullUrl = `http://localhost:8000${path}`;
              return isImage ? (
                <a key={idx} href={fullUrl} target="_blank" rel="noopener noreferrer" className="block border border-slate-200 rounded overflow-hidden hover:border-blue-400 transition-colors">
                  <img src={fullUrl} alt={`Attachment ${idx + 1}`} className="h-20 w-auto object-cover" />
                </a>
              ) : (
                <a key={idx} href={fullUrl} target="_blank" rel="noopener noreferrer" className="flex items-center space-x-2 p-2 border border-slate-200 rounded text-sm text-blue-600 hover:bg-blue-50 transition-colors">
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                  <span>Document {idx + 1}</span>
                </a>
              );
            })}
          </div>
        </div>
      )}

      {concern.ai_status === 'pending' && (
        <div className="mb-4 text-sm text-amber-600 bg-amber-50 p-3 rounded-lg border border-amber-200 flex items-center">
          <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-amber-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          Analyzing sentiment and priority...
        </div>
      )}

      {concern.ai_status === 'complete' && (
        <div className="mb-4 p-4 rounded-lg border bg-blue-50 border-blue-100">
          <div className="flex flex-wrap items-center gap-3 mb-2">
            <span className={`px-2.5 py-0.5 rounded-full text-xs font-medium uppercase tracking-wider
              ${concern.priority === 'critical' ? 'bg-red-100 text-red-800' : 
                concern.priority === 'high' ? 'bg-orange-100 text-orange-800' : 
                concern.priority === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'}`}>
              {concern.priority} Priority
            </span>
            <span className="text-xs font-medium text-slate-600 px-2 py-0.5 bg-slate-200 rounded-full">
              Sentiment: <span className="capitalize">{concern.sentiment_label}</span> ({concern.sentiment_score})
            </span>
          </div>
          <p className="text-sm text-slate-700"><strong>AI Summary:</strong> {concern.ai_summary}</p>
        </div>
      )}

      {concern.status === 'resolved' && concern.resolution_summary && (
        <div className="mb-4 p-4 rounded-lg border bg-green-50 border-green-200">
          <h4 className="text-sm font-bold text-green-900 mb-1">Resolution Summary</h4>
          <p className="text-sm text-green-800">{concern.resolution_summary}</p>
        </div>
      )}

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs text-slate-500 mb-4">
        <div>
          <span className="block font-medium text-slate-700">Category</span>
          <span className="capitalize">{concern.category?.replace('_', ' ')}</span>
        </div>
        <div>
          <span className="block font-medium text-slate-700">Target Path</span>
          <span className="capitalize">{concern.target_type}</span>
        </div>
        <div>
          <span className="block font-medium text-slate-700">Assigned To</span>
          {concern.category === 'academic_units' ? (concern.academic_unit?.name || 'N/A') : (concern.department?.name || 'N/A')}
        </div>
        <div>
          <span className="block font-medium text-slate-700">Last Updated</span>
          {new Date(concern.last_updated).toLocaleString()}
        </div>
      </div>

      <div className="flex justify-between items-center border-t border-slate-100 pt-4 mt-2">
        <button onClick={() => onViewMessages(concern.concern_id)} className="text-blue-600 hover:text-blue-800 text-sm font-medium">
          View Messages / History
        </button>
        {renderActions()}
      </div>
    </div>
  );
};

export default ConcernCard;
