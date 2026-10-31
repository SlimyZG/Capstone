import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { CloudUpload, Send, BookOpen, Building2, AlertCircle, Info } from 'lucide-react';
import api from '../api';
import ConfirmRoutingModal from '../components/ConfirmRoutingModal';
import PageHeader from '../components/PageHeader';

const SubmitConcern = () => {
  const [formData, setFormData] = useState({
    title: '',
    description: '',
    workflow_type: 'academic', // 'academic' | 'non_academic'
    dept_id: '',
  });
  const [departments, setDepartments] = useState([]);
  const [attachments, setAttachments] = useState([]);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [showRoutingModal, setShowRoutingModal] = useState(false);

  const navigate = useNavigate();

  useEffect(() => {
    const fetchDepartments = async () => {
      try {
        const res = await api.get('/departments');
        setDepartments(res.data.departments || []);
      } catch (err) {
        console.error('Failed to load departments', err);
      }
    };
    fetchDepartments();
  }, []);

  const handlePreSubmit = (e) => {
    e.preventDefault();
    if (!formData.title || !formData.description) return;
    if (formData.workflow_type === 'non_academic' && !formData.dept_id) {
      setError('Please select an administrative or operational department.');
      return;
    }
    setError('');
    setShowRoutingModal(true);
  };

  const handleSubmit = async () => {
    setShowRoutingModal(false);
    setError('');
    setLoading(true);

    const payload = new FormData();
    payload.append('title', formData.title);
    payload.append('description', formData.description);
    payload.append('workflow_type', formData.workflow_type);
    if (formData.workflow_type === 'non_academic') {
      payload.append('dept_id', formData.dept_id);
    }

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

  const getDestinationName = () => {
    if (formData.workflow_type === 'academic') {
      return 'Computer Studies Chairperson / Department Review';
    }
    const found = departments.find(d => d.id.toString() === formData.dept_id?.toString());
    return found ? `${found.name} (Review → CAD Approval)` : 'Selected Department';
  };

  return (
    <div className="max-w-3xl mx-auto pb-10">
      <PageHeader
        category="Student Portal"
        title="Submit New Concern"
        subtitle="Submit an academic inquiry or non-academic support concern. Your report will be automatically classified and routed to the responsible department."
        breadcrumbs={[
          { label: 'Dashboard', path: '/student' },
          { label: 'Submit Concern' }
        ]}
      />

      {error && (
        <div className="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl text-sm mb-6 flex items-center gap-3 shadow-2xs">
          <AlertCircle size={18} className="shrink-0" />
          <span>{error}</span>
        </div>
      )}

      <div className="card p-6 sm:p-8">
        <form onSubmit={handlePreSubmit} className="space-y-6">

          {/* Workflow Type Selection */}
          <div className="space-y-2">
            <label className="block text-xs font-bold text-slate-800 uppercase tracking-wider">
              Concern Classification Track <span className="text-red-500">*</span>
            </label>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <label className={`flex items-start p-4 rounded-xl border-2 cursor-pointer transition-all ${
                formData.workflow_type === 'academic' 
                  ? 'border-blue-600 bg-blue-50/50 shadow-2xs' 
                  : 'border-slate-200 hover:border-slate-300 bg-white'
              }`}>
                <input
                  type="radio"
                  name="workflow_type"
                  value="academic"
                  checked={formData.workflow_type === 'academic'}
                  onChange={() => setFormData({ ...formData, workflow_type: 'academic', dept_id: '' })}
                  className="mt-1 mr-3 text-blue-600 focus:ring-blue-500"
                />
                <div>
                  <div className="flex items-center gap-1.5 font-bold text-slate-900 text-sm">
                    <BookOpen size={16} className="text-blue-600 shrink-0" />
                    <span>Academic Concern</span>
                  </div>
                  <p className="text-xs text-slate-500 mt-1 leading-relaxed">
                    Curriculum, instruction, course scheduling, laboratory equipment, or grading inquiries.
                  </p>
                </div>
              </label>

              <label className={`flex items-start p-4 rounded-xl border-2 cursor-pointer transition-all ${
                formData.workflow_type === 'non_academic' 
                  ? 'border-blue-600 bg-blue-50/50 shadow-2xs' 
                  : 'border-slate-200 hover:border-slate-300 bg-white'
              }`}>
                <input
                  type="radio"
                  name="workflow_type"
                  value="non_academic"
                  checked={formData.workflow_type === 'non_academic'}
                  onChange={() => setFormData({ ...formData, workflow_type: 'non_academic' })}
                  className="mt-1 mr-3 text-blue-600 focus:ring-blue-500"
                />
                <div>
                  <div className="flex items-center gap-1.5 font-bold text-slate-900 text-sm">
                    <Building2 size={16} className="text-[#1e3a8a] shrink-0" />
                    <span>Non-Academic Concern</span>
                  </div>
                  <p className="text-xs text-slate-500 mt-1 leading-relaxed">
                    Registrar documents, cashier / billing, facilities, student affairs, security, or IT infrastructure.
                  </p>
                </div>
              </label>
            </div>
          </div>

          {/* Department Selection for Non-Academic */}
          {formData.workflow_type === 'non_academic' && (
            <div className="space-y-1.5 p-4 bg-slate-50 rounded-xl border border-slate-200">
              <label className="block text-xs font-bold text-slate-800 uppercase tracking-wider">
                Select Responsible Department <span className="text-red-500">*</span>
              </label>
              <select
                required
                className="input-field"
                value={formData.dept_id}
                onChange={e => setFormData({ ...formData, dept_id: e.target.value })}
              >
                <option value="">Choose a department...</option>
                {departments.map(d => (
                  <option key={d.id} value={d.id}>{d.name}</option>
                ))}
              </select>
              <p className="text-[11px] text-slate-500 mt-1">
                Workflow Track: Selected Department Review → Campus Administration Approval → Student Resolution.
              </p>
            </div>
          )}

          {formData.workflow_type === 'academic' && (
            <div className="p-3.5 bg-blue-50/70 border border-blue-200 rounded-lg text-xs text-[#1e3a8a] flex items-center gap-2.5">
              <BookOpen size={16} className="text-blue-600 shrink-0" />
              <span>
                <strong>Academic Workflow:</strong> Student Submission → Chairperson Review → Dean Administrative Review → Resolution.
              </span>
            </div>
          )}

          <div className="space-y-1.5">
            <label className="block text-xs font-bold text-slate-800 uppercase tracking-wider">
              Concern Title <span className="text-red-500">*</span>
            </label>
            <input
              required
              type="text"
              className="input-field"
              value={formData.title}
              onChange={e => setFormData({ ...formData, title: e.target.value })}
              placeholder="e.g. Projector Flickering in Room 301 during Lecture"
            />
          </div>

          <div className="space-y-1.5">
            <label className="block text-xs font-bold text-slate-800 uppercase tracking-wider">
              Detailed Description <span className="text-red-500">*</span>
            </label>
            <textarea
              required
              rows={5}
              className="input-field resize-y"
              value={formData.description}
              onChange={e => setFormData({ ...formData, description: e.target.value })}
              placeholder="Please describe what happened, including specific room numbers, dates, and equipment involved..."
            />
          </div>

          <div className="space-y-1.5">
            <label className="block text-xs font-bold text-slate-800 uppercase tracking-wider">
              Supporting Documentation or Screenshots (Optional)
            </label>
            <div className="border-2 border-dashed border-slate-200 rounded-xl p-6 text-center hover:bg-slate-50/80 transition-colors relative cursor-pointer group">
              <input
                type="file"
                multiple
                accept="image/*,.pdf,.doc,.docx"
                onChange={(e) => setAttachments(e.target.files)}
                className="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
              />
              <div className="flex flex-col items-center justify-center pointer-events-none">
                <CloudUpload size={28} className="text-slate-400 mb-2 group-hover:text-blue-600 transition-colors" />
                <p className="text-sm font-semibold text-slate-700">
                  <span className="text-blue-600">Click to upload</span> or drag and drop
                </p>
                <p className="text-xs text-slate-500 mt-1">PNG, JPG, PDF, or DOC files (max 10MB total)</p>
              </div>

              {attachments.length > 0 && (
                <div className="mt-4 pt-3 border-t border-slate-200 text-left">
                  <p className="text-xs font-bold text-slate-700 mb-1.5">Selected attachments ({attachments.length}):</p>
                  <ul className="text-xs text-slate-600 space-y-1">
                    {Array.from(attachments).map((file, i) => (
                      <li key={i} className="flex items-center gap-1.5">
                        <span className="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                        <span className="truncate">{file.name}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </div>
          </div>

          <div className="pt-4 flex flex-col sm:flex-row justify-end gap-3 border-t border-slate-100">
            <button
              type="button"
              onClick={() => navigate('/student/concerns')}
              className="btn-secondary"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={loading}
              className="btn-dark"
            >
              {loading ? (
                'Submitting...'
              ) : (
                <>
                  <span>Submit Concern</span>
                  <Send size={15} className="ml-2" />
                </>
              )}
            </button>
          </div>
        </form>
      </div>

      {showRoutingModal && (
        <ConfirmRoutingModal
          departmentName={getDestinationName()}
          onConfirm={handleSubmit}
          onCancel={() => setShowRoutingModal(false)}
        />
      )}
    </div>
  );
};

export default SubmitConcern;
