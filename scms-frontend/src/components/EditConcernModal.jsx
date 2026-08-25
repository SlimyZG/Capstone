import { useState, useEffect } from 'react';
import api from '../api';

const EditConcernModal = ({ concern, onClose, onSubmit }) => {
  const [formData, setFormData] = useState({
    title: concern.title || '',
    description: concern.description || '',
    category: concern.category || 'operational_departments',
    target_type: concern.target_type || 'department',
    dept_id: concern.dept_id || '',
    academic_unit_id: concern.academic_unit_id || ''
  });
  
  const [departments, setDepartments] = useState([]);
  const [academicUnits, setAcademicUnits] = useState([]);
  const [loadingTargets, setLoadingTargets] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    const fetchTargets = async () => {
      try {
        const [deptRes, acadRes] = await Promise.all([
          api.get('/departments'),
          api.get('/academic-units')
        ]);
        setDepartments(deptRes.data.departments);
        setAcademicUnits(acadRes.data.academic_units);
      } catch (err) {
        console.error('Failed to fetch targets', err);
      } finally {
        setLoadingTargets(false);
      }
    };
    fetchTargets();
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    
    if (formData.category === 'operational_departments' && !formData.dept_id) {
      return setError('Please select an operational department.');
    }
    if (formData.category === 'academic_units' && !formData.academic_unit_id) {
      return setError('Please select an academic unit.');
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
    <div className="fixed inset-0 bg-slate-900/50 flex items-center justify-center p-4 z-50 overflow-y-auto">
      <div className="bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6 relative my-8">
        <h2 className="text-xl font-bold text-slate-900 mb-4">Edit Concern</h2>
        
        {error && <div className="bg-red-50 text-red-600 p-3 rounded-lg text-sm mb-4">{error}</div>}
        
        {loadingTargets ? (
          <p className="text-sm text-slate-500">Loading form data...</p>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">Title</label>
              <input required type="text" className="input-field w-full" value={formData.title} onChange={e => setFormData({...formData, title: e.target.value})} />
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">Description</label>
              <textarea required rows="4" className="input-field w-full" value={formData.description} onChange={e => setFormData({...formData, description: e.target.value})} />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Category</label>
                <select className="input-field w-full" value={formData.category} onChange={e => setFormData({...formData, category: e.target.value, dept_id: '', academic_unit_id: ''})}>
                  <option value="operational_departments">Operational Departments</option>
                  <option value="academic_units">Academic Units</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Send To</label>
                <div className="flex space-x-4 mt-2">
                  <label className="flex items-center text-sm">
                    <input type="radio" name="target_type" value="department" checked={formData.target_type === 'department'} onChange={e => setFormData({...formData, target_type: e.target.value})} className="mr-2 text-blue-600" />
                    Department
                  </label>
                  <label className="flex items-center text-sm">
                    <input type="radio" name="target_type" value="dean" checked={formData.target_type === 'dean'} onChange={e => setFormData({...formData, target_type: e.target.value})} className="mr-2 text-blue-600" />
                    My Dean
                  </label>
                </div>
              </div>
            </div>

            {formData.category === 'operational_departments' && (
              <div className="p-3 bg-slate-50 border border-slate-200 rounded-lg">
                <label className="block text-sm font-medium text-slate-700 mb-1">Select Operational Department</label>
                <select className="input-field bg-white w-full" required value={formData.dept_id} onChange={e => setFormData({...formData, dept_id: e.target.value})}>
                  <option value="">Choose a department...</option>
                  {departments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                </select>
              </div>
            )}

            {formData.category === 'academic_units' && (
              <div className="p-3 bg-slate-50 border border-slate-200 rounded-lg">
                <label className="block text-sm font-medium text-slate-700 mb-1">Select Academic Unit</label>
                <select className="input-field bg-white w-full" required value={formData.academic_unit_id} onChange={e => setFormData({...formData, academic_unit_id: e.target.value})}>
                  <option value="">Choose an academic unit...</option>
                  {academicUnits.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                </select>
              </div>
            )}


            <div className="flex justify-end gap-3 pt-4">
              <button type="button" onClick={onClose} className="px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 rounded-lg">Cancel</button>
              <button type="submit" disabled={isSubmitting} className="btn-primary">
                {isSubmitting ? 'Saving...' : 'Save Changes'}
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
};

export default EditConcernModal;
