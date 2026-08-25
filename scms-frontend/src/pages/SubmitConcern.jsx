import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { CloudUpload, Send } from 'lucide-react';
import api from '../api';
import ConfirmRoutingModal from '../components/ConfirmRoutingModal';

const SubmitConcern = () => {
  const [formData, setFormData] = useState({
    title: '',
    description: '',
    // Hardcoding for now since the mock removes the dropdowns and relies on AI routing
    category: 'academic_units',
    target_type: 'department',
    dept_id: '',
    academic_unit_id: '1' // Fallback to first academic unit for the mockup
  });
  const [attachments, setAttachments] = useState([]);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [showRoutingModal, setShowRoutingModal] = useState(false);
  
  const navigate = useNavigate();

  const handlePreSubmit = (e) => {
    e.preventDefault();
    if (!formData.title || !formData.description) return;
    setShowRoutingModal(true);
  };

  const handleSubmit = async () => {
    setShowRoutingModal(false);
    setError('');
    setLoading(true);

    const payload = new FormData();
    Object.keys(formData).forEach(key => {
      payload.append(key, formData[key]);
    });
    
    Array.from(attachments).forEach(file => {
      payload.append('attachments[]', file);
    });

    try {
      await api.post('/concerns', payload, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      });
      navigate('/student/concerns');
    } catch (err) {
      setError(err.response?.data?.message || 'Submission failed.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="max-w-3xl mx-auto pb-10">
      <div className="mb-8 text-center">
        <h1 className="text-3xl font-bold text-slate-900 tracking-tight">Submit New Concern</h1>
        <p className="text-slate-600 mt-2 text-sm">Please provide details about your concern so our support team can assist you effectively.</p>
      </div>

      {error && <div className="bg-red-50 text-red-600 p-4 rounded-lg text-sm mb-6 max-w-2xl mx-auto">{error}</div>}

      <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-8 sm:p-10">
        <form onSubmit={handlePreSubmit} className="space-y-6">
          
          <div className="space-y-1.5">
            <label className="block text-xs font-bold text-slate-800">Title</label>
            <input 
              required 
              type="text" 
              className="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all placeholder:text-slate-400 font-medium outline-none" 
              value={formData.title} 
              onChange={e => setFormData({...formData, title: e.target.value})} 
              placeholder="Brief summary of your concern" 
            />
          </div>

          <div className="space-y-1.5">
            <label className="block text-xs font-bold text-slate-800">Description</label>
            <textarea 
              required 
              rows="6" 
              className="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-900 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all placeholder:text-slate-400 font-medium outline-none resize-none" 
              value={formData.description} 
              onChange={e => setFormData({...formData, description: e.target.value})} 
              placeholder="Provide detailed information about the issue..." 
            />
          </div>

          <div className="space-y-1.5">
            <label className="block text-xs font-bold text-slate-800">Attachment (Optional)</label>
            <div className="border-2 border-dashed border-slate-200 rounded-xl p-8 text-center hover:bg-slate-50 transition-colors relative cursor-pointer group">
              <input 
                type="file" 
                multiple 
                accept="image/*,.pdf,.doc,.docx"
                onChange={(e) => setAttachments(e.target.files)}
                className="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
              />
              <div className="flex flex-col items-center justify-center pointer-events-none">
                <CloudUpload size={32} className="text-slate-400 mb-3 group-hover:text-blue-500 transition-colors" />
                <p className="text-sm font-medium text-slate-700">
                  <span className="text-[#1e3a8a] font-bold">Click to upload</span> or drag and drop
                </p>
                <p className="text-xs text-slate-500 mt-1">SVG, PNG, JPG or PDF (max. 10MB)</p>
              </div>
              
              {attachments.length > 0 && (
                <div className="mt-4 pt-4 border-t border-slate-200">
                  <p className="text-xs font-bold text-slate-700 mb-2">Selected files:</p>
                  <ul className="text-xs text-slate-600 space-y-1">
                    {Array.from(attachments).map((file, i) => (
                      <li key={i}>{file.name}</li>
                    ))}
                  </ul>
                </div>
              )}
            </div>
          </div>

          <div className="pt-6 flex justify-end gap-4 border-t border-slate-100">
            <button 
              type="button" 
              onClick={() => navigate('/student/concerns')} 
              className="py-2.5 px-6 bg-white border border-slate-200 text-slate-700 font-semibold text-sm rounded-lg hover:bg-slate-50 transition-colors shadow-sm"
            >
              Cancel
            </button>
            <button 
              type="submit" 
              disabled={loading} 
              className="py-2.5 px-6 bg-[#002b80] hover:bg-[#001c54] text-white font-semibold text-sm rounded-lg transition-colors shadow-sm flex items-center gap-2"
            >
              {loading ? 'Submitting...' : 'Submit Concern'}
              <Send size={16} className={loading ? 'hidden' : ''} />
            </button>
          </div>
        </form>
      </div>

      {showRoutingModal && (
        <ConfirmRoutingModal 
          departmentName="Registrar's Office"
          onConfirm={handleSubmit}
          onCancel={() => setShowRoutingModal(false)}
        />
      )}
    </div>
  );
};

export default SubmitConcern;
