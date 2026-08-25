import { useState, useEffect } from 'react';
import api from '../api';

const MessageThread = ({ concernId, role, onClose }) => {
  const [messages, setMessages] = useState([]);
  const [newMsg, setNewMsg] = useState('');
  const [isInternal, setIsInternal] = useState(false);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchMessages();
  }, [concernId]);

  const fetchMessages = async () => {
    try {
      const endpoint = role === 'student' ? `/concerns/${concernId}` : `/${role.replace('_', '-')}/concerns/${concernId}/messages`;
      const res = await api.get(endpoint);
      setMessages(role === 'student' ? res.data.concern.messages : res.data.messages);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleSend = async (e) => {
    e.preventDefault();
    if (!newMsg.trim()) return;
    
    try {
      const endpoint = role === 'student' ? `/concerns/${concernId}/messages` : `/${role.replace('_', '-')}/concerns/${concernId}/reply`;
      const payload = { body: newMsg };
      if (role !== 'student') payload.is_internal = isInternal;

      await api.post(endpoint, payload);
      setNewMsg('');
      setIsInternal(false);
      fetchMessages();
    } catch (err) {
      console.error(err);
    }
  };

  if (loading) return <div className="p-4">Loading messages...</div>;

  return (
    <div className="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div className="p-4 border-b border-slate-200 flex justify-between items-center">
          <h3 className="text-lg font-bold">Message Thread</h3>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-600">&times; Close</button>
        </div>
        
        <div className="flex-grow overflow-y-auto p-4 space-y-4 bg-slate-50">
          {messages.length === 0 ? (
            <p className="text-center text-slate-500 text-sm">No messages yet.</p>
          ) : (
            messages.map((m) => (
              <div key={m.id} className={`flex flex-col ${m.sender?.role === role ? 'items-end' : 'items-start'}`}>
                <div className={`max-w-[80%] rounded-lg p-3 ${
                  m.is_internal ? 'bg-amber-50 border border-amber-200 text-amber-900' :
                  m.sender?.role === role ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200'
                }`}>
                  <p className="text-sm">{m.body}</p>
                  <div className={`text-[10px] mt-1 ${m.sender?.role === role && !m.is_internal ? 'text-blue-100' : 'text-slate-400'}`}>
                    {m.sender?.name} {m.is_internal && '(Internal Note)'} - {new Date(m.created_at).toLocaleString()}
                  </div>
                </div>
              </div>
            ))
          )}
        </div>

        <div className="p-4 border-t border-slate-200">
          <form onSubmit={handleSend} className="flex flex-col space-y-2">
            <textarea
              className="input-field"
              rows="2"
              placeholder="Type your message..."
              value={newMsg}
              onChange={(e) => setNewMsg(e.target.value)}
            />
            <div className="flex justify-between items-center">
              {role !== 'student' ? (
                <label className="flex items-center text-sm text-slate-600">
                  <input type="checkbox" checked={isInternal} onChange={(e) => setIsInternal(e.target.checked)} className="mr-2 rounded text-blue-600" />
                  Internal Note (Hidden from student)
                </label>
              ) : <div></div>}
              <button type="submit" className="btn-primary">Send Message</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
};

export default MessageThread;
