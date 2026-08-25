import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { Users, AlertTriangle, FileText, CheckCircle2 } from 'lucide-react';
import api from '../api';

const DeanDashboard = () => {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [directConcerns, setDirectConcerns] = useState([]);
  const [escalatedConcerns, setEscalatedConcerns] = useState([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState('');

  useEffect(() => { fetchInbox(); }, []);

  const fetchInbox = async () => {
    try {
      const res = await api.get('/dean/inbox');
      setDirectConcerns(res.data.direct || []);
      setEscalatedConcerns(res.data.escalated || []);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleResolve = async (concernId) => {
    if (!window.confirm('Resolve this concern?')) return;
    setActionLoading(concernId.toString());
    try {
      await api.post(`/dean/concerns/${concernId}/resolve`);
      fetchInbox();
    } catch (err) {
      alert(err.response?.data?.message || 'Action failed.');
    } finally {
      setActionLoading('');
    }
  };

  const allConcerns = [...escalatedConcerns, ...directConcerns];
  const priority = allConcerns.slice(0, 6);
  const escalatedCount = escalatedConcerns.length;
  const totalStudents = 1250; // placeholder — no endpoint for total students count
  const reportsGenerated = 24; // placeholder

  const getInitials = (name) => (name || '?').split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);

  const avatarColors = [
    'bg-blue-100 text-blue-700',
    'bg-amber-100 text-amber-700',
    'bg-emerald-100 text-emerald-700',
    'bg-purple-100 text-purple-700',
    'bg-red-100 text-red-700',
    'bg-teal-100 text-teal-700',
  ];

  const formatDate = (d) => {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  };

  const firstName = user?.name?.split(' ')[0] || 'Dean';

  return (
    <div>
      <div className="mb-2">
        <h2 className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Overview</h2>
      </div>

      <div className="mb-6">
        <h1 className="text-2xl font-bold text-slate-900">Welcome back, {firstName}.</h1>
        <p className="text-slate-500 text-sm mt-1">Here is the current status of academic affairs for today.</p>
      </div>

      {/* Metric cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
        {/* Total Students */}
        <div className="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
          <div className="flex justify-between items-start mb-6">
            <div className="h-10 w-10 bg-slate-100 rounded-lg flex items-center justify-center">
              <Users size={20} className="text-slate-500" />
            </div>
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total</span>
          </div>
          <p className="text-xs text-slate-500 font-medium mb-0.5">Total Students</p>
          <p className="text-3xl font-bold text-slate-900">{totalStudents.toLocaleString()}</p>
        </div>

        {/* Escalated Concerns — urgent highlight */}
        <div className="bg-red-50 rounded-xl border border-red-200 p-5 shadow-sm">
          <div className="flex justify-between items-start mb-6">
            <div className="h-10 w-10 bg-red-100 rounded-lg flex items-center justify-center">
              <AlertTriangle size={20} className="text-red-500" />
            </div>
            {escalatedCount > 0 && (
              <span className="px-2 py-0.5 bg-red-500 text-white text-[10px] font-bold uppercase tracking-wider rounded-full flex items-center gap-1">
                <span className="w-1.5 h-1.5 rounded-full bg-white"></span> Urgent
              </span>
            )}
          </div>
          <p className="text-xs text-red-700 font-medium mb-0.5">Escalated Concerns</p>
          <p className="text-3xl font-bold text-red-700">{loading ? '—' : escalatedCount}</p>
        </div>

        {/* Reports Generated */}
        <div className="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
          <div className="flex justify-between items-start mb-6">
            <div className="h-10 w-10 bg-slate-100 rounded-lg flex items-center justify-center">
              <FileText size={20} className="text-slate-500" />
            </div>
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">This Week</span>
          </div>
          <p className="text-xs text-slate-500 font-medium mb-0.5">Reports Generated</p>
          <p className="text-3xl font-bold text-slate-900">{reportsGenerated}</p>
        </div>
      </div>

      {/* Priority Escalations table */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h2 className="text-base font-bold text-slate-900">Priority Escalations</h2>
            <p className="text-xs text-slate-500 mt-0.5">Cases requiring immediate dean intervention.</p>
          </div>
          <Link to="/dean/escalated" className="px-3 py-1.5 border border-slate-200 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-colors flex items-center gap-1.5">
            <span>≡</span> Filter
          </Link>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="text-[10px] uppercase text-slate-400 font-semibold bg-slate-50/60 border-b border-slate-100">
              <tr>
                <th className="px-6 py-3">Case ID</th>
                <th className="px-6 py-3">Student</th>
                <th className="px-6 py-3">Email</th>
                <th className="px-6 py-3">Status</th>
                <th className="px-6 py-3">Action</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan="5" className="px-6 py-10 text-center text-slate-400">Loading...</td></tr>
              ) : priority.length === 0 ? (
                <tr><td colSpan="5" className="px-6 py-10 text-center text-slate-400">No escalated concerns.</td></tr>
              ) : (
                priority.map((c, idx) => {
                  const colorClass = avatarColors[idx % avatarColors.length];
                  return (
                    <tr key={c.concern_id} className="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                      <td className="px-6 py-4 font-bold text-slate-700 whitespace-nowrap text-xs">
                        #ESC-2023-{c.concern_id.toString().padStart(3, '0')}
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center gap-3">
                          <div className={`h-8 w-8 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-bold ${colorClass}`}>
                            {getInitials(c.student?.name)}
                          </div>
                          <div>
                            <p className="font-semibold text-slate-800 text-sm">{c.student?.name || 'Unknown'}</p>
                            <p className="text-[10px] text-slate-400">ID: {c.student?.student_id_number || '—'}</p>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4 text-slate-500 text-xs">
                        {c.student?.student_id_number ? `${c.student.student_id_number.toLowerCase()}@university.edu` : '—'}
                      </td>
                      <td className="px-6 py-4">
                        <span className="px-2.5 py-1 bg-red-50 text-red-600 text-[10px] font-bold uppercase tracking-wider rounded-full border border-red-100">Urgent Review</span>
                      </td>
                      <td className="px-6 py-4">
                        <button
                          onClick={() => handleResolve(c.concern_id)}
                          disabled={actionLoading === c.concern_id.toString()}
                          className="inline-flex items-center gap-1.5 text-[#1e3a8a] text-sm font-bold hover:text-blue-900 disabled:opacity-50"
                        >
                          <CheckCircle2 size={15} />
                          {actionLoading === c.concern_id.toString() ? 'Resolving...' : 'Resolve'}
                        </button>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

        {!loading && allConcerns.length > 0 && (
          <div className="px-6 py-3 border-t border-slate-100">
            <Link to="/dean/escalated" className="text-sm font-bold text-[#1e3a8a] hover:text-blue-800">
              View All Activities
            </Link>
          </div>
        )}
      </div>
    </div>
  );
};

export default DeanDashboard;
