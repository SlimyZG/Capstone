import { useState, useEffect } from 'react';
import { X, AlertCircle } from 'lucide-react';
import api from '../api';

const EditConcernModal = ({ concern, onClose, onSubmit }) => {
  const [formData, setFormData] = useState({
    title: concern.title || '',
    description: concern.description || '',
    workflow_type: concern.workflow_type || (concern.category === 'academic_units' ? 'academic' : 'non_academic'),
    dept_id: concern.dept_id || '',
  });
  
  const [departments, setDepartments] = useState([]);
  const [loadingTargets, setLoadingTargets] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    const fetchTargets = async () => {
      try {
        const deptRes = await api.get('/departments');
        setDepartments(deptRes.data.departments || []);
      } catch (err) {
        console.error('Failed to fetch departments', err);
      } finally {
        setLoadingTargets(false);
      }
    };
    fetchTargets();
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    
    if (formData.workflow_type === 'non_academic' && !formData.dept_id) {
      return setError('Please select an operational or administrative department.');
    }

    setIsSubmitting(true);
    try {
      await onSubmit(concern.concern_id, formData);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to update concern.');
      setIsSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 z-50 overflow-y-auto">
      <div className="bg-white rounded-xl shadow-lg border border-slate-200 w-full max-w-2xl p-6 relative my-8 animate-in fade-in zoom-in-95 duration-150">
        <div className="flex justify-between items-center pb-4 border-b border-slate-100 mb-5">
          <div>
            <h2 className="text-xl font-bold text-slate-900">Edit Concern</h2>
            <p className="text-xs text-slate-500 mt-0.5">Update details while your concern is pending initial review</p>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100"
          >
            <X size={20} />
          </button>
        </div>
        
        {error && (
          <div className="bg-red-50 border border-red-200 text-red-700 p-3 rounded-lg text-sm mb-4 flex items-center gap-2">
            <AlertCircle size={16} className="shrink-0" />
            <span>{error}</span>
          </div>
        )}
        
        {loadingTargets ? (
          <p className="text-sm text-slate-500 py-6 text-center">Loading department options...</p>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Title</label>
              <input
                required
                type="text"
                className="input-field"
                value={formData.title}
                onChange={e => setFormData({ ...formData, title: e.target.value })}
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Description</label>
              <textarea
                required
                rows={5}
                className="input-field"
                value={formData.description}
                onChange={e => setFormData({ ...formData, description: e.target.value })}
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Concern Type</label>
              <div className="flex gap-4">
                <label className="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                  <input
                    type="radio"
                    name="workflow_type"
                    value="academic"
                    checked={formData.workflow_type === 'academic'}
                    onChange={() => setFormData({ ...formData, workflow_type: 'academic', dept_id: '' })}
                    className="text-blue-600 focus:ring-blue-500"
                  />
                  <span>Academic Concern</span>
                </label>
                <label className="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                  <input
                    type="radio"
                    name="workflow_type"
                    value="non_academic"
                    checked={formData.workflow_type === 'non_academic'}
                    onChange={() => setFormData({ ...formData, workflow_type: 'non_academic' })}
                    className="text-blue-600 focus:ring-blue-500"
                  />
                  <span>Non-Academic Concern</span>
                </label>
              </div>
            </div>

            {formData.workflow_type === 'non_academic' && (
              <div>
                <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                  Target Department
                </label>
                <select
                  required
                  className="input-field"
                  value={formData.dept_id}
                  onChange={e => setFormData({ ...formData, dept_id: e.target.value })}
                >
                  <option value="">Select Department...</option>
                  {departments.map(d => (
                    <option key={d.dept_id || d.id} value={d.dept_id || d.id}>
                      {d.name}
                    </option>
                  ))}
                </select>
              </div>
            )}

            <div className="flex justify-end gap-3 pt-4 border-t border-slate-100">
              <button
                type="button"
                onClick={onClose}
                className="btn-secondary"
                disabled={isSubmitting}
              >
                Cancel
              </button>
              <button
                type="submit"
                className="btn-primary"
                disabled={isSubmitting}
              >
                {isSubmitting ? 'Saving Changes...' : 'Save Changes'}
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
};

export default EditConcernModal;
