import { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { Paperclip, Send, CheckCircle2, User, Clock, ArrowLeft, X } from 'lucide-react';
import api from '../api';

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

  const fetchData = async () => {
    try {
      const concernRes = await api.get(`/concerns/${id}`);
      setConcern(concernRes.data.concern);
      setMessages(concernRes.data.concern.messages || []);
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
      await api.post(`/concerns/${id}/messages`, { body: newMessage });
      setNewMessage('');
      fetchData();
    } catch (err) {
      console.error(err);
      alert('Failed to send message');
    }
  };

  const getStatusBadge = (status) => {
    switch (status) {
      case 'submitted': return <span className="px-2.5 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-bold uppercase tracking-wider flex items-center w-max"><Clock size={12} className="mr-1" /> Pending</span>;
      case 'in_progress': return <span className="px-2.5 py-1 bg-amber-50 text-amber-700 rounded-full text-xs font-bold uppercase tracking-wider flex items-center w-max"><span className="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1.5"></span> In Progress</span>;
      case 'resolved': return <span className="px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-full text-xs font-bold uppercase tracking-wider flex items-center w-max"><CheckCircle2 size={12} className="mr-1" /> Resolved</span>;
      default: return <span className="px-2.5 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-bold uppercase tracking-wider">{status}</span>;
    }
  };

  const formatDate = (dateString, includeTime = false) => {
    const date = new Date(dateString);
    const options = { month: 'short', day: 'numeric', year: 'numeric' };
    if (includeTime) {
      options.hour = '2-digit';
      options.minute = '2-digit';
    }
    return date.toLocaleDateString('en-US', options);
  };

  if (loading) return <div className="text-slate-500">Loading concern details...</div>;
  if (!concern) return <div className="text-slate-500">Concern not found.</div>;

  const departmentName = concern.department ? concern.department.name : concern.academic_unit?.name;
  
  // Fake timeline data since we don't have a real timeline table in the DB, 
  // we'll infer it from the concern status and created/updated dates
  const timeline = [
    { status: 'Submitted', date: concern.created_at, active: true },
    { status: `Received by ${departmentName}`, date: concern.created_at, active: true },
    { status: 'Assigned to Staff', date: concern.updated_at, active: concern.status === 'in_progress' || concern.status === 'resolved' },
    { status: 'In Progress', date: concern.updated_at, active: concern.status === 'in_progress' || concern.status === 'resolved' },
    { status: 'Resolved', date: concern.updated_at, active: concern.status === 'resolved' }
  ];

  return (
    <div className="pb-10">
      <div className="flex items-center text-sm text-slate-500 mb-6">
        <Link to="/student/concerns" className="hover:text-slate-900">My Concerns</Link>
        <span className="mx-2">/</span>
        <span className="font-medium text-slate-700">REQ-2023-{concern.concern_id.toString().padStart(3, '0')}</span>
      </div>

      <div className="flex justify-between items-start mb-6">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 tracking-tight flex items-center">
            {concern.title}
            <span className="ml-4 mt-1">{getStatusBadge(concern.status)}</span>
          </h1>
        </div>
        <Link to="/student/concerns" className="px-4 py-2 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 rounded-md text-sm font-semibold flex items-center transition-colors shadow-sm">
          <X size={16} className="mr-2" /> Close Concern
        </Link>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left Column (Details + Messages) */}
        <div className="lg:col-span-2 space-y-6">
          
          {/* Details Card */}
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div className="px-6 py-5 border-b border-slate-100">
              <h2 className="text-lg font-bold text-slate-900">Concern Details</h2>
            </div>
            <div className="p-6">
              <div className="grid grid-cols-2 gap-y-6 gap-x-4 mb-6">

                <div>
                  <p className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Department</p>
                  <p className="text-sm font-medium text-slate-900">{departmentName}</p>
                </div>
                <div>
                  <p className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Submitted On</p>
                  <p className="text-sm font-medium text-slate-900">{formatDate(concern.created_at, true)}</p>
                </div>
                <div>
                  <p className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Category</p>
                  <p className="text-sm font-medium text-slate-900">{concern.category === 'academic_units' ? 'Academic Records' : 'Operational'}</p>
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
                  <div className="flex flex-wrap gap-2">
                    {concern.attachments.map(att => (
                      <a 
                        key={att.id} 
                        href={`http://localhost:8000/storage/${att.file_path}`}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center px-3 py-1.5 bg-blue-50 text-blue-700 rounded-full text-xs font-medium border border-blue-100 hover:bg-blue-100 transition-colors"
                      >
                        <Paperclip size={12} className="mr-1.5" />
                        {att.file_name}
                      </a>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Messages Card */}
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col h-[500px]">
            <div className="px-6 py-5 border-b border-slate-100">
              <h2 className="text-lg font-bold text-slate-900">Messages</h2>
            </div>
            
            <div className="flex-1 overflow-y-auto p-6 space-y-6 bg-slate-50/50">
              {messages.length === 0 ? (
                <div className="text-center text-slate-500 text-sm mt-10">No messages yet. Send a message to start the conversation.</div>
              ) : (
                messages.map((msg, idx) => {
                  const isMine = msg.sender_id === user.id;
                  
                  // Simple date divider logic
                  const prevMsgDate = idx > 0 ? new Date(messages[idx-1].created_at).toDateString() : null;
                  const currMsgDate = new Date(msg.created_at).toDateString();
                  const showDate = prevMsgDate !== currMsgDate;

                  return (
                    <div key={msg.id} className="flex flex-col">
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
                            <span className="text-xs text-slate-500 mb-1 ml-1 font-medium">{msg.user?.name} ({msg.user?.role})</span>
                          )}
                          <div 
                            className={`p-3.5 rounded-2xl text-sm leading-relaxed ${
                              isMine 
                                ? 'bg-[#002b80] text-white rounded-tr-sm' 
                                : 'bg-white border border-slate-200 text-slate-800 rounded-tl-sm shadow-sm'
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
                <button type="button" className="p-2.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full transition-colors flex-shrink-0">
                  <Paperclip size={20} />
                </button>
                <div className="flex-1 bg-slate-50 border border-slate-200 rounded-2xl overflow-hidden focus-within:ring-1 focus-within:ring-[#1e3a8a] focus-within:border-[#1e3a8a] transition-all">
                  <textarea
                    rows="1"
                    className="w-full bg-transparent px-4 py-3 outline-none text-sm resize-none"
                    placeholder="Type a message..."
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
                  className="p-3 bg-[#002b80] text-white rounded-full hover:bg-blue-900 disabled:opacity-50 disabled:hover:bg-[#002b80] transition-colors shadow-sm flex-shrink-0"
                >
                  <Send size={18} className="ml-0.5" />
                </button>
              </form>
            </div>
          </div>
        </div>

        {/* Right Column (Status Timeline) */}
        <div>
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 sticky top-24">
            <h2 className="text-lg font-bold text-slate-900 mb-6">Status Timeline</h2>
            
            <div className="space-y-6 relative before:absolute before:inset-0 before:ml-3.5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-slate-200 before:z-0">
              
              {timeline.map((step, index) => {
                const isActive = step.active;
                const isLast = index === timeline.length - 1;
                
                return (
                  <div key={index} className="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
                    {/* Icon */}
                    <div className={`flex items-center justify-center w-7 h-7 rounded-full border-2 bg-white z-10 shrink-0 ${isActive ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300 text-slate-300'}`}>
                      {isActive ? <CheckCircle2 size={16} /> : <div className="w-2 h-2 rounded-full bg-slate-300" />}
                    </div>
                    
                    {/* Content */}
                    <div className="w-[calc(100%-2rem)] md:w-[calc(50%-2.5rem)] pl-4 md:pl-0 md:odd:pr-6 md:even:pl-6">
                      <div className="flex flex-col">
                        <span className={`text-sm font-bold ${isActive ? 'text-slate-900' : 'text-slate-400'}`}>{step.status}</span>
                        {isActive && <span className="text-[10px] font-medium text-slate-500 mt-0.5">{formatDate(step.date, true)}</span>}
                      </div>
                    </div>
                  </div>
                );
              })}
              
            </div>
          </div>
        </div>

      </div>
    </div>
  );
};

export default StudentConcernDetails;
