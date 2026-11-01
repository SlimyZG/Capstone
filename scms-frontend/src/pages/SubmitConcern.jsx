import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { 
  CloudUpload, 
  Send, 
  BookOpen, 
  Building2, 
  AlertCircle, 
  Sparkles, 
  CheckCircle2, 
  Loader2, 
  HelpCircle,
  AlertTriangle,
  GraduationCap
} from 'lucide-react';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';
import ConfirmRoutingModal from '../components/ConfirmRoutingModal';
import PageHeader from '../components/PageHeader';

const SubmitConcern = () => {
  const { user } = useAuth();
  const navigate = useNavigate();

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

  // AI Recommendation State for Non-Academic
  const [aiLoading, setAiLoading] = useState(false);
  const [aiRecommendation, setAiRecommendation] = useState(null);
  const [aiError, setAiError] = useState('');

  const registeredUnitName = user?.academic_unit?.name || null;
  const hasAcademicUnit = Boolean(user?.academic_unit_id && registeredUnitName);

  useEffect(() => {
    const fetchDepartments = async () => {
      try {
        const res = await api.get('/departments');
        setDepartments(res.data.departments || []);
      } catch (err) {
        console.error('Failed to load operational departments', err);
      }
    };
    fetchDepartments();
  }, []);

  // Request AI Department Recommendation for Non-Academic
  const handleRequestAiRecommendation = async () => {
    if (!formData.title.trim() || !formData.description.trim()) {
      setAiError('Please enter both a title and description before requesting an AI recommendation.');
      return;
    }

    setAiLoading(true);
    setAiError('');

    try {
      const res = await api.post('/concerns/recommend-routing', {
        title: formData.title,
        description: formData.description,
      });

      const rec = res.data;
      setAiRecommendation(rec);

      // If a department ID was confidently identified, pre-select it
      if (rec.recommended_department_id) {
        setFormData((prev) => ({
          ...prev,
          dept_id: String(rec.recommended_department_id),
        }));
      }
    } catch (err) {
      console.error('AI Routing recommendation failed:', err);
      setAiError(err.response?.data?.message || 'AI recommendation service is temporarily unavailable. Please select your operational department manually.');
    } finally {
      setAiLoading(false);
    }
  };

  const handlePreSubmit = (e) => {
    e.preventDefault();
    setError('');

    if (!formData.title.trim() || !formData.description.trim()) {
      setError('Please provide both a concern title and detailed description.');
      return;
    }

    // Academic validation: Must have registered academic unit
    if (formData.workflow_type === 'academic' && !hasAcademicUnit) {
      setError('You do not have a registered academic unit on file. Academic concerns cannot be submitted without an assigned college. Please contact administration.');
      return;
    }

    // Non-academic validation: Must have chosen an operational department
    if (formData.workflow_type === 'non_academic' && !formData.dept_id) {
      setError('Please select an operational or administrative department to receive this concern.');
      return;
    }

    setShowRoutingModal(true);
  };

  const handleSubmit = async () => {
    setError('');
    setLoading(true);

    const payload = new FormData();
    payload.append('title', formData.title);
    payload.append('description', formData.description);
    payload.append('workflow_type', formData.workflow_type);

    if (formData.workflow_type === 'non_academic') {
      payload.append('dept_id', formData.dept_id);
    }

    Array.from(attachments).forEach((file) => {
      payload.append('attachments[]', file);
    });

    try {
      await api.post('/concerns', payload, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      setShowRoutingModal(false);
      navigate('/student/concerns');
    } catch (err) {
      setShowRoutingModal(false);
      setError(err.response?.data?.message || 'Submission failed. Please check your information and try again.');
    } finally {
      setLoading(false);
    }
  };

  const getDestinationName = () => {
    if (formData.workflow_type === 'academic') {
      return registeredUnitName
        ? `${registeredUnitName} Chairperson & Academic Dean Review`
        : 'Your Registered Academic Unit';
    }
    const found = departments.find((d) => d.id.toString() === formData.dept_id?.toString());
    return found ? `${found.name} (Review → Administration Approval)` : 'Selected Operational Department';
  };

  return (
    <div className="max-w-3xl mx-auto pb-10">
      <PageHeader
        category="Student Portal"
        title="Submit New Concern"
        subtitle="Submit an academic inquiry or non-academic support concern. Your report will be automatically classified and routed to the responsible department."
        breadcrumbs={[
          { label: 'Dashboard', path: '/student' },
          { label: 'Submit Concern' },
        ]}
      />

      {error && (
        <div className="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl text-sm mb-6 flex items-start gap-3 shadow-2xs">
          <AlertCircle size={18} className="shrink-0 mt-0.5" />
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
              {/* Academic Option */}
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
                  onChange={() => {
                    setFormData({ ...formData, workflow_type: 'academic', dept_id: '' });
                    setAiRecommendation(null);
                    setAiError('');
                  }}
                  className="mt-1 mr-3 text-blue-600 focus:ring-blue-500"
                />
                <div>
                  <div className="flex items-center gap-1.5 font-bold text-slate-900 text-sm">
                    <BookOpen size={16} className="text-blue-600 shrink-0" />
                    <span>Academic Concern</span>
                  </div>
                  <p className="text-xs text-slate-500 mt-1 leading-relaxed">
                    Curriculum, grading, laboratory instructions, prerequisite evaluations, or faculty consultations.
                  </p>
                </div>
              </label>

              {/* Non-Academic Option */}
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
                    Registrar documents, tuition/cashier, student portal, security, medical clinic, scholarships, or facilities.
                  </p>
                </div>
              </label>
            </div>
          </div>

          {/* Academic Workflow Routing Notice */}
          {formData.workflow_type === 'academic' && (
            <div>
              {hasAcademicUnit ? (
                <div className="p-4 bg-blue-50 border border-blue-200 rounded-xl space-y-1.5">
                  <div className="flex items-center gap-2 text-xs font-bold text-[#1e3a8a] uppercase tracking-wider">
                    <GraduationCap size={16} className="text-blue-600" />
                    <span>Designated Academic Unit</span>
                  </div>
                  <div className="text-sm font-extrabold text-slate-900">
                    {registeredUnitName}
                  </div>
                  <p className="text-xs text-slate-600 leading-relaxed">
                    This concern will be automatically assigned to your registered academic college (<strong>{registeredUnitName}</strong>) for review by the Department Chairperson and Academic Dean. No operational department selection is needed.
                  </p>
                </div>
              ) : (
                <div className="p-4 bg-amber-50 border border-amber-300 rounded-xl flex items-start gap-3 text-amber-900">
                  <AlertTriangle size={20} className="shrink-0 text-amber-600 mt-0.5" />
                  <div className="text-xs leading-relaxed">
                    <p className="font-bold text-sm text-amber-950 mb-0.5">Missing Registered Academic Unit</p>
                    <p>
                      Your student profile does not have an assigned academic college or unit. Academic concerns require a registered academic unit for Chairperson and Dean assignment. Please contact the administrator or update your profile before submitting an academic concern.
                    </p>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Concern Title */}
          <div className="space-y-1.5">
            <label className="block text-xs font-bold text-slate-800 uppercase tracking-wider">
              Concern Title <span className="text-red-500">*</span>
            </label>
            <input
              required
              type="text"
              className="input-field"
              value={formData.title}
              onChange={(e) => setFormData({ ...formData, title: e.target.value })}
              placeholder="e.g. Projector Flickering in Room 301 during Lecture or Tuition Receipt Validation"
            />
          </div>

          {/* Detailed Description */}
          <div className="space-y-1.5">
            <label className="block text-xs font-bold text-slate-800 uppercase tracking-wider">
              Detailed Description <span className="text-red-500">*</span>
            </label>
            <textarea
              required
              rows={5}
              className="input-field resize-y"
              value={formData.description}
              onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              placeholder="Please describe what happened, including specific room numbers, dates, transactions, or equipment involved..."
            />
          </div>

          {/* Non-Academic: AI Recommendation Section & Department Selector */}
          {formData.workflow_type === 'non_academic' && (
            <div className="space-y-4 p-5 bg-slate-50/80 rounded-xl border border-slate-200">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                  <label className="block text-xs font-bold text-slate-900 uppercase tracking-wider">
                    Operational Department Destination <span className="text-red-500">*</span>
                  </label>
                  <p className="text-[11px] text-slate-500">
                    Select the operational department or request an AI recommendation based on your description.
                  </p>
                </div>

                <button
                  type="button"
                  onClick={handleRequestAiRecommendation}
                  disabled={aiLoading || !formData.title.trim() || !formData.description.trim()}
                  className="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-lg bg-[#1e3a8a] hover:bg-blue-700 text-white transition-all shadow-2xs disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer shrink-0"
                  title={!formData.title.trim() || !formData.description.trim() ? 'Enter title and description first' : 'Analyze text with AI'}
                >
                  {aiLoading ? (
                    <>
                      <Loader2 size={14} className="animate-spin" />
                      <span>Analyzing with AI...</span>
                    </>
                  ) : (
                    <>
                      <Sparkles size={14} className="text-amber-300" />
                      <span>Get AI Routing Recommendation</span>
                    </>
                  )}
                </button>
              </div>

              {/* AI Error Notification */}
              {aiError && (
                <div className="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 flex items-start gap-2">
                  <AlertCircle size={15} className="text-amber-600 shrink-0 mt-0.5" />
                  <span>{aiError}</span>
                </div>
              )}

              {/* AI Recommendation Result Card */}
              {aiRecommendation && (
                <div className={`p-4 rounded-xl border transition-all ${
                  aiRecommendation.requires_manual_selection
                    ? 'bg-amber-50/70 border-amber-200'
                    : 'bg-blue-50/70 border-blue-200'
                }`}>
                  <div className="flex items-start justify-between gap-3 flex-wrap mb-2">
                    <div className="flex items-center gap-2">
                      <div className="w-6 h-6 rounded-md bg-blue-100 text-[#1e3a8a] flex items-center justify-center">
                        <Sparkles size={14} />
                      </div>
                      <span className="text-xs font-bold text-slate-900">
                        AI Recommended Department
                      </span>
                    </div>

                    <div className="flex items-center gap-1.5">
                      <span className={`px-2 py-0.5 rounded-full text-[11px] font-bold ${
                        aiRecommendation.confidence >= 0.70
                          ? 'bg-emerald-100 text-emerald-800 border border-emerald-200'
                          : 'bg-amber-100 text-amber-800 border border-amber-200'
                      }`}>
                        {Math.round(aiRecommendation.confidence * 100)}% Confidence
                      </span>
                      <span className="text-[10px] text-slate-500 capitalize">
                        ({aiRecommendation.source})
                      </span>
                    </div>
                  </div>

                  {aiRecommendation.recommended_department_name ? (
                    <div className="space-y-1">
                      <div className="text-sm font-black text-slate-900 flex items-center gap-1.5">
                        <CheckCircle2 size={16} className="text-blue-600" />
                        <span>{aiRecommendation.recommended_department_name}</span>
                      </div>
                      <p className="text-xs text-slate-600 leading-relaxed">
                        {aiRecommendation.explanation}
                      </p>
                      {aiRecommendation.requires_manual_selection ? (
                        <p className="text-[11px] text-amber-800 font-semibold mt-1">
                          Notice: Low confidence or multiple operational units may apply. Please confirm or manually select below.
                        </p>
                      ) : (
                        <p className="text-[11px] text-blue-700 font-semibold mt-1">
                          Auto-selected based on analysis. You can change this below if another department is more appropriate.
                        </p>
                      )}
                    </div>
                  ) : (
                    <div className="text-xs text-slate-600">
                      <p className="font-semibold text-slate-800">{aiRecommendation.explanation}</p>
                    </div>
                  )}
                </div>
              )}

              {/* Department Dropdown Selector */}
              <div className="space-y-1.5 pt-1">
                <select
                  required
                  className="input-field bg-white"
                  value={formData.dept_id}
                  onChange={(e) => setFormData({ ...formData, dept_id: e.target.value })}
                >
                  <option value="">Choose an operational department...</option>
                  {departments.map((d) => (
                    <option key={d.id} value={d.id}>
                      {d.name}
                    </option>
                  ))}
                </select>
                <p className="text-[11px] text-slate-500">
                  Workflow Track: Confirmed Department Review &bull; Campus Administration Approval &bull; Student Resolution.
                </p>
              </div>
            </div>
          )}

          {/* Attachments Section */}
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
                <p className="text-xs text-slate-500 mt-1">PNG, JPG, PDF, or DOC files (max 5MB each)</p>
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

          {/* Form Actions */}
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
              disabled={loading || (formData.workflow_type === 'academic' && !hasAcademicUnit)}
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
          targetName={getDestinationName()}
          workflowType={formData.workflow_type}
          loading={loading}
          onConfirm={handleSubmit}
          onCancel={() => setShowRoutingModal(false)}
        />
      )}
    </div>
  );
};

export default SubmitConcern;
