import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { Package, Loader2, CheckCircle2 } from 'lucide-react';
import api from '../api';

const DeptStaffDashboard = () => {
  const { user } = useAuth();
  const [concerns, setConcerns] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchQueue = async () => {
      try {
        const res = await api.get('/dept-staff/queue');
        setConcerns(res.data.concerns);
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchQueue();
  }, []);

  const newCount = concerns.filter(c => c.status === 'pending').length;
  const inProgressCount = concerns.filter(c => c.status === 'in_progress').length;
  const resolvedCount = concerns.filter(c => c.status === 'resolved').length;

  const recent = [...concerns].sort((a, b) => new Date(b.submitted_at) - new Date(a.submitted_at)).slice(0, 5);

  const getPriorityBadge = (priority) => {
    switch ((priority || '').toLowerCase()) {
      case 'high': return <span className="px-2.5 py-1 bg-red-50 text-red-600 rounded text-xs font-semibold border border-red-100">High</span>;
      case 'medium': return <span className="px-2.5 py-1 bg-amber-50 text-amber-600 rounded text-xs font-semibold border border-amber-100">Medium</span>;
      case 'low': return <span className="px-2.5 py-1 bg-slate-100 text-slate-600 rounded text-xs font-semibold">Low</span>;
      default: return <span className="px-2.5 py-1 bg-slate-100 text-slate-500 rounded text-xs font-semibold">—</span>;
    }
  };

  const getStatusBadge = (status) => {
    switch (status) {
      case 'pending': return <span className="px-2.5 py-1 bg-blue-50 text-blue-600 rounded text-xs font-semibold border border-blue-100">New</span>;
      case 'in_progress': return <span className="px-2.5 py-1 bg-amber-50 text-amber-600 rounded text-xs font-semibold border border-amber-100">In Progress</span>;
      case 'resolved': return <span className="px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded text-xs font-semibold border border-emerald-100">Resolved</span>;
      case 'escalated': return <span className="px-2.5 py-1 bg-purple-50 text-purple-600 rounded text-xs font-semibold border border-purple-100">Escalated</span>;
      default: return <span className="px-2.5 py-1 bg-slate-100 text-slate-600 rounded text-xs font-semibold">{status}</span>;
    }
  };

  const formatDate = (d) => {
    if (!d) return '—';
    const date = new Date(d);
    const now = new Date();
    const diffMs = now - date;
    const diffHrs = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    if (diffHrs < 1) return `Today, ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
    if (diffDays === 0) return `Today, ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
    if (diffDays === 1) return `Yesterday, ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  };

  return (
    <div>
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-slate-900 tracking-tight">Welcome, Department Head.</h1>
        <p className="text-slate-500 mt-1.5 text-sm">Here is the current overview of institutional concerns.</p>
      </div>

      {/* Summary cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
          <div className="flex justify-between items-start mb-8">
            <div className="h-10 w-10 bg-red-50 rounded-lg flex items-center justify-center border border-red-100">
              <Package size={20} className="text-red-500" />
            </div>
            {newCount > 0 && (
              <span className="px-2 py-0.5 bg-red-50 text-red-600 text-[10px] font-bold uppercase tracking-wider rounded-md border border-red-100">
                +{newCount > 1 ? `${newCount}` : '1'} today
              </span>
            )}
          </div>
          <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">New Concerns</p>
          <h3 className="text-4xl font-bold text-slate-900">{loading ? '—' : newCount}</h3>
        </div>

        <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
          <div className="h-10 w-10 bg-amber-50 rounded-lg flex items-center justify-center border border-amber-100 mb-8">
            <Loader2 size={20} className="text-amber-500" />
          </div>
          <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">In Progress</p>
          <h3 className="text-4xl font-bold text-slate-900">{loading ? '—' : inProgressCount}</h3>
        </div>

        <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
          <div className="h-10 w-10 bg-blue-50 rounded-lg flex items-center justify-center border border-blue-100 mb-8">
            <CheckCircle2 size={20} className="text-blue-600" />
          </div>
          <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Resolved</p>
          <h3 className="text-4xl font-bold text-slate-900">{loading ? '—' : resolvedCount}</h3>
        </div>
      </div>

      {/* Recent Activity */}
      <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="px-6 py-5 border-b border-slate-100 flex justify-between items-center">
          <h2 className="text-lg font-bold text-slate-900">Recent Activity</h2>
          <Link to="/dept-staff/concerns" className="text-sm font-bold text-[#1e3a8a] hover:text-blue-800">View All</Link>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="text-[10px] uppercase text-slate-500 font-semibold border-b border-slate-100 bg-slate-50/60">
              <tr>
                <th className="px-6 py-3">ID</th>
                <th className="px-6 py-3">Description</th>
                <th className="px-6 py-3">Priority</th>
                <th className="px-6 py-3">Status</th>
                <th className="px-6 py-3">Date</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan="5" className="px-6 py-8 text-center text-slate-400">Loading...</td></tr>
              ) : recent.length === 0 ? (
                <tr><td colSpan="5" className="px-6 py-8 text-center text-slate-400">No recent activity.</td></tr>
              ) : (
                recent.map(c => (
                  <tr key={c.concern_id} className="border-b border-slate-100 hover:bg-slate-50/60 transition-colors">
                    <td className="px-6 py-4 font-bold text-[#1e3a8a] whitespace-nowrap">
                      <Link to={`/dept-staff/concerns/${c.concern_id}`}>#C-{c.concern_id.toString().padStart(4, '0')}</Link>
                    </td>
                    <td className="px-6 py-4 text-slate-700 max-w-xs truncate">{c.title}</td>
                    <td className="px-6 py-4">{getPriorityBadge(c.priority)}</td>
                    <td className="px-6 py-4">{getStatusBadge(c.status)}</td>
                    <td className="px-6 py-4 text-slate-500 text-xs whitespace-nowrap">{formatDate(c.submitted_at)}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};

export default DeptStaffDashboard;
