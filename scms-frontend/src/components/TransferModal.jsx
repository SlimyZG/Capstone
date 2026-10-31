import { useState, useEffect } from 'react';
import { X, ArrowRightLeft, AlertCircle } from 'lucide-react';
import api from '../api';

const TransferModal = ({ concernId, onClose, onSubmit }) => {
  const [targetCategory, setTargetCategory] = useState('operational_departments');
  const [targetId, setTargetId] = useState('');
  const [notes, setNotes] = useState('');
  
  const [departments, setDepartments] = useState([]);
  const [academicUnits, setAcademicUnits] = useState([]);
  const [loading, setLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    const fetchTargets = async () => {
      try {
        const [deptRes, acadRes] = await Promise.all([
          api.get('/departments'),
          api.get('/academic-units')
        ]);
        setDepartments(deptRes.data.departments || []);
        setAcademicUnits(acadRes.data.academic_units || []);
      } catch (err) {
        console.error('Failed to fetch transfer targets', err);
        setError('Failed to load target departments.');
      } finally {
        setLoading(false);
      }
    };
    fetchTargets();
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!targetId) return setError('Please select a destination department or academic unit.');
    setError('');
    setIsSubmitting(true);
    try {
      await onSubmit(concernId, { target_category: targetCategory, target_id: targetId, notes });
    } catch (err) {
      setError(err.response?.data?.message || 'Transfer failed.');
      setIsSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
      <div className="bg-white rounded-xl shadow-lg border border-slate-200 w-full max-w-md p-6 relative animate-in fade-in zoom-in-95 duration-150">
        <div className="flex justify-between items-center pb-4 border-b border-slate-100 mb-5">
          <div className="flex items-center gap-2">
            <div className="h-8 w-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
              <ArrowRightLeft size={16} />
            </div>
            <div>
              <h2 className="text-lg font-bold text-slate-900">Transfer Concern</h2>
              <p className="text-xs text-slate-500">Reassign to appropriate department</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100"
          >
            <X size={18} />
          </button>
        </div>
        
        {error && (
          <div className="bg-red-50 border border-red-200 text-red-700 p-3 rounded-lg text-xs mb-4 flex items-center gap-2">
            <AlertCircle size={14} className="shrink-0" />
            <span>{error}</span>
          </div>
        )}
        
        {loading ? (
          <p className="text-sm text-slate-500 py-6 text-center">Loading departments...</p>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                Target Category
              </label>
              <select 
                value={targetCategory}
                onChange={(e) => {
                  setTargetCategory(e.target.value);
                  setTargetId('');
                }}
                className="input-field"
              >
                <option value="operational_departments">Operational Department</option>
                <option value="academic_units">Academic Unit</option>
              </select>
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                Destination {targetCategory === 'academic_units' ? 'Academic Unit' : 'Department'}
              </label>
              <select
                required
                value={targetId}
                onChange={(e) => setTargetId(e.target.value)}
                className="input-field"
              >
                <option value="">Select Destination...</option>
                {targetCategory === 'academic_units'
                  ? academicUnits.map((u) => (
                      <option key={u.id} value={u.id}>
                        {u.name}
                      </option>
                    ))
                  : departments.map((d) => (
                      <option key={d.dept_id || d.id} value={d.dept_id || d.id}>
                        {d.name}
                      </option>
                    ))}
              </select>
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                Transfer Notes (Optional)
              </label>
              <textarea
                rows={3}
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                placeholder="Reason for transferring this concern..."
                className="input-field text-xs"
              />
            </div>

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
                {isSubmitting ? 'Transferring...' : 'Confirm Transfer'}
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
};

export default TransferModal;
