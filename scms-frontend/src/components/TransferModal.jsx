import { useState, useEffect } from 'react';
import api from '../api';

const TransferModal = ({ concernId, onClose, onSubmit }) => {
  const [targetCategory, setTargetCategory] = useState('operational_departments');
  const [targetId, setTargetId] = useState('');
  const [notes, setNotes] = useState('');
  
  const [departments, setDepartments] = useState([]);
  const [academicUnits, setAcademicUnits] = useState([]);
  const [loading, setLoading] = useState(true);

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
        console.error('Failed to fetch transfer targets', err);
      } finally {
        setLoading(false);
      }
    };
    fetchTargets();
  }, []);

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!targetId) return alert('Please select a destination.');
    onSubmit(concernId, { target_category: targetCategory, target_id: targetId, notes });
  };

  return (
    <div className="fixed inset-0 bg-slate-900/50 flex items-center justify-center p-4 z-50">
      <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 relative">
        <h2 className="text-xl font-bold text-slate-900 mb-4">Transfer Concern</h2>
        
        {loading ? (
          <p className="text-sm text-slate-500">Loading targets...</p>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1">Target Category</label>
              <select 
                value={targetCategory}
                onChange={(e) => {
                  setTargetCategory(e.target.value);
                  setTargetId('');
                }}
                className="input-field w-full"
              >
                <option value="operational_departments">Operational Department</option>
                <option value="academic_units">Academic Unit</option>
              </select>
            </div>

            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1">Destination</label>
              <select 
                value={targetId}
                onChange={(e) => setTargetId(e.target.value)}
                className="input-field w-full"
                required
              >
                <option value="">Select Destination...</option>
                {targetCategory === 'operational_departments' 
                  ? departments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)
                  : academicUnits.map(a => <option key={a.id} value={a.id}>{a.name}</option>)
                }
              </select>
            </div>

            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1">Reason for Transfer (Optional)</label>
              <textarea 
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                className="input-field w-full min-h-[80px]"
                placeholder="e.g. Sent to wrong department by student."
              />
            </div>

            <div className="flex justify-end gap-3 pt-4">
              <button type="button" onClick={onClose} className="px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 rounded-lg">Cancel</button>
              <button type="submit" className="btn-primary">Confirm Transfer</button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
};

export default TransferModal;
